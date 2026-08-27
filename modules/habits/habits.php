<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/auth.php';
requireLogin();
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/db.php';

const MEASUREMENT_TYPES = ['boolean', 'count', 'duration', 'weight', 'distance', 'rating', 'percentage', 'steps', 'custom', 'money', 'time_of_day', 'score', 'volume', 'partial'];
const NO_TARGET_TYPES = ['boolean', 'partial'];
const BOUNDED_TYPES = [
    'rating' => [1, 5],
    'percentage' => [0, 100],
];

function formatTargetValueForInput(string $measurementType, ?int $targetValue): string
{
    if ($targetValue === null) {
        return '';
    }
    if ($measurementType === 'time_of_day') {
        $hh = intdiv($targetValue, 60);
        $mm = $targetValue % 60;
        return sprintf('%02d:%02d', $hh, $mm);
    }
    return (string) $targetValue;
}

function formatTargetValueForDisplay(string $measurementType, ?int $targetValue): string
{
    if ($targetValue === null) {
        return '—';
    }
    if ($measurementType === 'time_of_day') {
        return formatTargetValueForInput($measurementType, $targetValue);
    }
    return (string) $targetValue;
}

$errors = [];
$userId = (int) $_SESSION['user_id'];

// Failed-edit state, if a POST update below fails validation — used
// to reopen the dialog with the attempted values instead of losing them.
$failedEditHabitId = null;
$failedEditValues = null;

$catStmt = mysqli_prepare($conn, 'SELECT category_id, category_name FROM CATEGORY WHERE user_id = ? ORDER BY category_name');
mysqli_stmt_bind_param($catStmt, 'i', $userId);
mysqli_stmt_execute($catStmt);
$catResult = mysqli_stmt_get_result($catStmt);
$categories = mysqli_fetch_all($catResult, MYSQLI_ASSOC);
mysqli_stmt_close($catStmt);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'update') {

        $habitName = trim($_POST['habit_name'] ?? '');
        $categoryId = $_POST['category_id'] ?? '';
        $habitNature = $_POST['habit_nature'] ?? '';
        $measurementType = $_POST['measurement_type'] ?? '';
        $targetValueRaw = trim($_POST['target_value'] ?? '');
        $targetType = $_POST['target_type'] ?? '';
        $description = trim($_POST['description'] ?? '');

        if ($habitName === '') {
            $errors[] = 'Habit name is required.';
        }

        $ownsCategory = false;
        foreach ($categories as $cat) {
            if ((string) $cat['category_id'] === (string) $categoryId) {
                $ownsCategory = true;
                break;
            }
        }
        if (!$ownsCategory) {
            $errors[] = 'Please select a valid category.';
        }

        if (!in_array($habitNature, ['good', 'bad'], true)) {
            $errors[] = 'Please select a valid habit type.';
        }

        if (!in_array($measurementType, MEASUREMENT_TYPES, true)) {
            $errors[] = 'Please select a valid measurement type.';
        }

        if (!in_array($targetType, ['daily', 'twice a week', 'weekly'], true)) {
            $errors[] = 'Please select a valid target type.';
        }

        $targetValue = null;
        if (in_array($measurementType, NO_TARGET_TYPES, true)) {
            $targetValue = null;
        } elseif ($measurementType === 'time_of_day') {
            if ($targetValueRaw !== '') {
                if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $targetValueRaw)) {
                    $errors[] = 'Please enter a valid time.';
                } else {
                    [$hh, $mm] = explode(':', $targetValueRaw);
                    $targetValue = ((int) $hh) * 60 + (int) $mm;
                }
            }
        } elseif ($targetValueRaw === '') {
            $targetValue = null;
        } elseif (filter_var($targetValueRaw, FILTER_VALIDATE_INT) === false) {
            $errors[] = 'Target value must be a whole number.';
        } else {
            $targetValue = (int) $targetValueRaw;
            if (isset(BOUNDED_TYPES[$measurementType])) {
                [$min, $max] = BOUNDED_TYPES[$measurementType];
                if ($targetValue < $min || $targetValue > $max) {
                    $errors[] = "Target value must be between $min and $max.";
                }
            } elseif ($targetValue < 0) {
                $errors[] = 'Target value cannot be negative.';
            }
        }

        if ($action === 'add') {
            if (empty($errors)) {
                $categoryId = (int) $categoryId;

                $stmt = mysqli_prepare($conn, 'INSERT INTO HABIT (category_id, habit_name, habit_nature, measurement_type, target_value, target_type, description) VALUES (?, ?, ?, ?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'isssiss', $categoryId, $habitName, $habitNature, $measurementType, $targetValue, $targetType, $description);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                header('Location: habits.php?success=add');
                exit;
            }
        }

        if ($action === 'update') {
            $habitId = $_POST['habit_id'] ?? '';
            if (filter_var($habitId, FILTER_VALIDATE_INT) === false) {
                $errors[] = 'Invalid habit.';
            } else {
                $habitId = (int) $habitId;
            }

            if (empty($errors)) {
                $categoryId = (int) $categoryId;

                $stmt = mysqli_prepare($conn, 'UPDATE HABIT
                    SET habit_name = ?, category_id = ?, habit_nature = ?, measurement_type = ?, target_value = ?, target_type = ?, description = ?
                    WHERE habit_id = ? AND category_id IN (SELECT category_id FROM CATEGORY WHERE user_id = ?)');
                mysqli_stmt_bind_param($stmt, 'sissisiii', $habitName, $categoryId, $habitNature, $measurementType, $targetValue, $targetType, $description, $habitId, $userId);
                mysqli_stmt_execute($stmt);
                $affected = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);

                if ($affected === 0) {
                    $errors[] = 'Habit not found.';
                } else {
                    header('Location: habits.php?success=update');
                    exit;
                }
            }

            if (!empty($errors)) {
                $failedEditHabitId = $habitId ?? null;
                $failedEditValues = [
                    'id' => $failedEditHabitId,
                    'name' => $habitName,
                    'category_id' => (int) $categoryId,
                    'nature' => $habitNature,
                    'measurement_type' => $measurementType,
                    'target_value' => $targetValue,
                    'target_type' => $targetType,
                    'description' => $description,
                ];
            }
        }
    }

    if ($action === 'delete') {
        $habitId = $_POST['habit_id'] ?? '';

        if (filter_var($habitId, FILTER_VALIDATE_INT) !== false) {
            $habitId = (int) $habitId;

            $stmt = mysqli_prepare($conn, 'DELETE FROM HABIT WHERE habit_id = ? AND
            category_id IN (SELECT category_id FROM CATEGORY WHERE user_id = ?)');
            mysqli_stmt_bind_param($stmt, 'ii', $habitId, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            header('Location: habits.php?success=delete');
            exit;
        }
    }
}

// DataTables handles pagination client-side now — fetch everything,
// no LIMIT/OFFSET or page math needed server-side anymore.
$habitStmt = mysqli_prepare($conn, 'SELECT HABIT.*, CATEGORY.category_name
  FROM HABIT INNER JOIN CATEGORY ON HABIT.category_id = CATEGORY.category_id
  WHERE CATEGORY.user_id = ?
  ORDER BY HABIT.created_at DESC');
mysqli_stmt_bind_param($habitStmt, 'i', $userId);
mysqli_stmt_execute($habitStmt);
$habitResult = mysqli_stmt_get_result($habitStmt);
$habits = mysqli_fetch_all($habitResult, MYSQLI_ASSOC);
mysqli_stmt_close($habitStmt);

$measurementLabels = [
    'boolean' => 'Simple (yes/no)',
    'count' => 'Count',
    'duration' => 'Duration (min)',
    'weight' => 'Weight (kg)',
    'distance' => 'Distance (km)',
    'rating' => 'Rating (1–5)',
    'percentage' => 'Percentage',
    'steps' => 'Steps',
    'custom' => 'Custom',
    'money' => 'Money (Rs.)',
    'time_of_day' => 'Time of day',
    'score' => 'Score',
    'volume' => 'Volume (ml)',
    'partial' => 'Partial completion',
];

// Every habit's full data, for the edit dialog to look up client-side
// — no AJAX round trip needed when Edit is clicked.
$habitsForJs = array_map(function ($h) {
    return [
        'id' => (int) $h['habit_id'],
        'name' => $h['habit_name'],
        'category_id' => (int) $h['category_id'],
        'nature' => $h['habit_nature'],
        'measurement_type' => $h['measurement_type'],
        'target_value' => $h['target_value'] !== null ? (int) $h['target_value'] : null,
        'target_type' => $h['target_type'],
        'description' => $h['description'],
    ];
}, $habits);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Habits — Habit Track</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
  <link href="https://cdn.datatables.net/v/dt/dt-3.0.2/datatables.min.css" rel="stylesheet">
  <link rel="stylesheet" href="habits.css?v=20260801-6">
</head>
<body>
  <script>window.SERVER_ERRORS = <?php echo json_encode($errors, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
  <div class="app-layout">
    <div class="sidebar">
      <?php require __DIR__ . '/../../includes/logo.php'; ?>
      <a href="../dashboard/dashboard.php" class="nav-item">Dashboard</a>
      <a href="habits.php" class="nav-item active">Habits</a>
      <a href="../categories/categories.php" class="nav-item">Categories</a>
      <a href="../reminders/reminders.php" class="nav-item">Reminders</a>
      <a href="../calendar/calendar.php" class="nav-item">Calendar</a>
      <div class="sidebar-footer">
        <a href="../auth/logout.php" class="nav-item">Logout</a>
      </div>
    </div>
    <div class="main-content">
      <div class="page-header"><h1>Habits</h1></div>

      <?php foreach ($errors as $err): ?>
        <div class="error-box"><?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>

      <?php
      $renderMeasurementOptions = function () use ($measurementLabels) {
          foreach ($measurementLabels as $value => $label) {
              echo "<option value=\"" . htmlspecialchars($value) . "\">" . htmlspecialchars($label) . "</option>";
          }
      };
      ?>

      <?php if (empty($categories)): ?>
        <div class="empty-state">
          <p>You need at least one category before adding a habit.</p>
          <a href="../categories/categories.php">Create a category →</a>
        </div>
      <?php else: ?>
        <div class="auth-card habit-form-card">
          <form method="POST" action="habits.php">
            <input type="hidden" name="action" value="add">

            <div class="field"><input type="text" name="habit_name" placeholder="Habit name" required></div>

            <div class="field">
              <select name="category_id" required class="select-input">
                <option value="">Select category</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="field">
              <select name="habit_nature" class="select-input">
                <option value="good">Good habit</option>
                <option value="bad">Bad habit</option>
              </select>
            </div>

            <div class="field">
              <select name="measurement_type" class="select-input measurement-select">
                <?php $renderMeasurementOptions(); ?>
              </select>
            </div>

            <div class="field target-value-field"><input type="number" name="target_value" placeholder="Target value"></div>

            <div class="field">
              <select name="target_type" class="select-input">
                <option value="daily">Daily</option>
                <option value="twice a week">Twice a week</option>
                <option value="weekly">Weekly</option>
              </select>
            </div>

            <div class="field"><input type="text" name="description" placeholder="Description (optional)"></div>

            <button type="submit" class="btn-primary">Add Habit</button>
          </form>
        </div>
      <?php endif; ?>

      <?php if (empty($habits)): ?>
        <div class="empty-state"><p>No habits yet.</p></div>
      <?php else: ?>
        <table id="habits-table" class="data-table">
          <thead>
            <tr>
              <th>Habit</th>
              <th>Category</th>
              <th>Type</th>
              <th>Measure</th>
              <th>Target</th>
              <th>Frequency</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($habits as $h): ?>
              <tr>
                <td><?php echo htmlspecialchars($h['habit_name']); ?></td>
                <td><?php echo htmlspecialchars($h['category_name']); ?></td>
                <td><span class="badge-<?php echo $h['habit_nature']; ?>"><?php echo ucfirst($h['habit_nature']); ?></span></td>
                <td><?php echo htmlspecialchars($measurementLabels[$h['measurement_type']] ?? $h['measurement_type']); ?></td>
                <td><?php echo htmlspecialchars(formatTargetValueForDisplay($h['measurement_type'], $h['target_value'] !== null ? (int) $h['target_value'] : null)); ?></td>
                <td><?php echo ucfirst($h['target_type']); ?></td>
                <td class="actions-cell">
                  <button type="button" class="btn-edit" onclick="openEditHabitDialog(<?php echo (int) $h['habit_id']; ?>)">Edit</button>
                  <a href="../subtasks/subtasks.php?habit_id=<?php echo $h['habit_id']; ?>" class="link-purple">Manage subtasks</a>
                  <?php if ($h['habit_nature'] === 'bad'): ?>
                    <a href="../bad-habit-progress/bad-habit-progress.php?habit_id=<?php echo $h['habit_id']; ?>" class="link-coral">Log progress</a>
                  <?php endif; ?>
                  <form method="POST" action="habits.php" style="display:inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="habit_id" value="<?php echo $h['habit_id']; ?>">
                    <button type="submit" class="btn-delete">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <!-- Edit dialog — jQuery UI manages show/hide; lives outside the
           table entirely so DataTables never has to reason about it. -->
      <div id="edit-habit-dialog" title="Edit Habit" style="display:none;">
        <form method="POST" action="habits.php" id="edit-habit-form">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="habit_id" id="edit-habit-id">

          <div class="field"><input type="text" name="habit_name" id="edit-habit-name" required></div>

          <div class="field">
            <select name="category_id" id="edit-habit-category" required class="select-input">
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <select name="habit_nature" id="edit-habit-nature" class="select-input">
              <option value="good">Good habit</option>
              <option value="bad">Bad habit</option>
            </select>
          </div>

          <div class="field">
            <select name="measurement_type" id="edit-habit-measurement" class="select-input measurement-select">
              <?php $renderMeasurementOptions(); ?>
            </select>
          </div>

          <div class="field target-value-field"><input type="number" name="target_value" id="edit-habit-target-value" placeholder="Target value"></div>

          <div class="field">
            <select name="target_type" id="edit-habit-target-type" class="select-input">
              <option value="daily">Daily</option>
              <option value="twice a week">Twice a week</option>
              <option value="weekly">Weekly</option>
            </select>
          </div>

          <div class="field"><input type="text" name="description" id="edit-habit-description" placeholder="Description (optional)"></div>

          <button type="submit" class="btn-primary">Save changes</button>
        </form>
      </div>

    </div>
  </div>

  <script>
    window.HABITS_DATA = <?php echo json_encode($habitsForJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.FAILED_EDIT = <?php echo $failedEditValues ? json_encode($failedEditValues, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : 'null'; ?>;
  </script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" integrity="sha512-bLT0Qm9VnAYZDflyKcBaQ2gg0hSYNQrJ8RilYldYQ1FxQYoCLtUjuuRuZo+fjqhx/qtq/1itJ0C2ejDxltZVFg==" crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
  <script src="https://cdn.datatables.net/v/dt/dt-3.0.2/datatables.min.js"></script>
  <script src="habits.js"></script>
  <script src="habits-datatable.js"></script>
  <script src="/assets/js/toast.js"></script>
</body>
</html>