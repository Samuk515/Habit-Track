<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/auth.php';
requireLogin();
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/db.php';

// Bounded types get a min/max check; everything else just needs to be
// a non-negative whole number. boolean/partial skip target_value
// entirely (handled separately, below).
const MEASUREMENT_TYPES = ['boolean', 'count', 'duration', 'weight', 'distance', 'rating', 'percentage', 'steps', 'custom', 'money', 'time_of_day', 'score', 'volume', 'partial'];
const NO_TARGET_TYPES = ['boolean', 'partial'];
const BOUNDED_TYPES = [
    'rating' => [1, 5],
    'percentage' => [0, 100],
];

// time_of_day is stored as minutes-since-midnight in the same INT
// column everything else uses — there's no separate time column in
// the locked schema, so this is the compliant way to fit a clock
// time into target_value without an ER change.
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
$perPage = 10;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$editHabitId = filter_var($_GET['edit_habit_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$editValues = null;

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

        // Target value handling branches by type: no-target types
        // ignore whatever was submitted, time_of_day expects "HH:MM"
        // and gets encoded to minutes-since-midnight, everything else
        // is a plain whole number (with bounds checked where relevant).
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

                header('Location: habits.php?page=' . $page);
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
                    header('Location: habits.php?page=' . $page);
                    exit;
                }
            }

            if (!empty($errors)) {
                $editHabitId = $habitId ?? null;
                $editValues = [
                    'habit_id' => $editHabitId,
                    'habit_name' => $habitName,
                    'category_id' => $categoryId,
                    'habit_nature' => $habitNature,
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

            header('Location: habits.php?page=' . $page);
            exit;
        }
    }
}

$countStmt = mysqli_prepare($conn, 'SELECT COUNT(*) FROM HABIT WHERE category_id IN (SELECT category_id FROM CATEGORY WHERE user_id = ?)');
mysqli_stmt_bind_param($countStmt, 'i', $userId);
mysqli_stmt_execute($countStmt);
mysqli_stmt_bind_result($countStmt, $totalHabits);
mysqli_stmt_fetch($countStmt);
mysqli_stmt_close($countStmt);
$totalPages = (int) ceil($totalHabits / $perPage);

if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$habitStmt = mysqli_prepare($conn, 'SELECT HABIT.*, CATEGORY.category_name
  FROM HABIT INNER JOIN CATEGORY ON HABIT.category_id = CATEGORY.category_id
  WHERE CATEGORY.user_id = ?
  ORDER BY HABIT.created_at DESC
  LIMIT ? OFFSET ?');
mysqli_stmt_bind_param($habitStmt, 'iii', $userId, $perPage, $offset);
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
?>
<!DOCTYPE html>
<html>
<head>
  <title>Habits — Habit Track</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="habits.css?v=20260801-6">
</head>
<body>
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
      // Shared across the Add form and every Edit form — one place to
      // change the option list rather than duplicating it per form.
      $renderMeasurementOptions = function (string $selected) use ($measurementLabels) {
          foreach ($measurementLabels as $value => $label) {
              $sel = $value === $selected ? 'selected' : '';
              echo "<option value=\"" . htmlspecialchars($value) . "\" $sel>" . htmlspecialchars($label) . "</option>";
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
          <form method="POST" action="habits.php?page=<?php echo $page; ?>">
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
                <?php $renderMeasurementOptions('boolean'); ?>
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
        <table class="data-table">
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
              <?php $isEditing = ($editHabitId !== null && (int) $h['habit_id'] === (int) $editHabitId); ?>

              <?php if ($isEditing): ?>
                <?php $ev = $editValues ?? $h; ?>
                <tr class="edit-row" id="habit-<?php echo $h['habit_id']; ?>">
                  <td colspan="7">
                    <form method="POST" action="habits.php?page=<?php echo $page; ?>">
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="habit_id" value="<?php echo $h['habit_id']; ?>">

                      <div class="field"><input type="text" name="habit_name" value="<?php echo htmlspecialchars((string) $ev['habit_name']); ?>" required></div>

                      <div class="field">
                        <select name="category_id" required class="select-input">
                          <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>" <?php echo ((int) $cat['category_id'] === (int) $ev['category_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['category_name']); ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>

                      <div class="field">
                        <select name="habit_nature" class="select-input">
                          <option value="good" <?php echo $ev['habit_nature'] === 'good' ? 'selected' : ''; ?>>Good habit</option>
                          <option value="bad" <?php echo $ev['habit_nature'] === 'bad' ? 'selected' : ''; ?>>Bad habit</option>
                        </select>
                      </div>

                      <div class="field">
                        <select name="measurement_type" class="select-input measurement-select">
                          <?php $renderMeasurementOptions((string) $ev['measurement_type']); ?>
                        </select>
                      </div>

                      <div class="field target-value-field">
                        <input type="number" name="target_value" value="<?php echo htmlspecialchars(formatTargetValueForInput((string) $ev['measurement_type'], $ev['target_value'] !== null ? (int) $ev['target_value'] : null)); ?>" placeholder="Target value">
                      </div>

                      <div class="field">
                        <select name="target_type" class="select-input">
                          <option value="daily" <?php echo $ev['target_type'] === 'daily' ? 'selected' : ''; ?>>Daily</option>
                          <option value="twice a week" <?php echo $ev['target_type'] === 'twice a week' ? 'selected' : ''; ?>>Twice a week</option>
                          <option value="weekly" <?php echo $ev['target_type'] === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                        </select>
                      </div>

                      <div class="field"><input type="text" name="description" value="<?php echo htmlspecialchars((string) ($ev['description'] ?? '')); ?>" placeholder="Description (optional)"></div>

                      <div class="edit-form-actions">
                        <button type="submit" class="btn-primary">Save changes</button>
                        <a href="habits.php?page=<?php echo $page; ?>" class="btn-cancel">Cancel</a>
                      </div>
                    </form>
                  </td>
                </tr>

              <?php else: ?>
                <tr id="habit-<?php echo $h['habit_id']; ?>">
                  <td><?php echo htmlspecialchars($h['habit_name']); ?></td>
                  <td><?php echo htmlspecialchars($h['category_name']); ?></td>
                  <td><span class="badge-<?php echo $h['habit_nature']; ?>"><?php echo ucfirst($h['habit_nature']); ?></span></td>
                  <td><?php echo htmlspecialchars($measurementLabels[$h['measurement_type']] ?? $h['measurement_type']); ?></td>
                  <td><?php echo htmlspecialchars(formatTargetValueForDisplay($h['measurement_type'], $h['target_value'] !== null ? (int) $h['target_value'] : null)); ?></td>
                  <td><?php echo ucfirst($h['target_type']); ?></td>
                  <td class="actions-cell">
                    <a href="habits.php?page=<?php echo $page; ?>&edit_habit_id=<?php echo $h['habit_id']; ?>#habit-<?php echo $h['habit_id']; ?>" class="btn-edit">Edit</a>
                    <a href="../subtasks/subtasks.php?habit_id=<?php echo $h['habit_id']; ?>" class="link-purple">Manage subtasks</a>
                    <?php if ($h['habit_nature'] === 'bad'): ?>
                      <a href="../bad-habit-progress/bad-habit-progress.php?habit_id=<?php echo $h['habit_id']; ?>" class="link-coral">Log progress</a>
                    <?php endif; ?>
                    <form method="POST" action="habits.php?page=<?php echo $page; ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="habit_id" value="<?php echo $h['habit_id']; ?>">
                      <button type="submit" class="btn-delete">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endif; ?>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
          <div class="list-pagination">
            <?php if ($page > 1): ?>
              <a class="pagination-link" href="habits.php?page=<?php echo $page - 1; ?>">Previous</a>
            <?php endif; ?>

            <span class="pagination-status">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>

            <?php if ($page < $totalPages): ?>
              <a class="pagination-link" href="habits.php?page=<?php echo $page + 1; ?>">Next</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>

    </div>
  </div>
  <script src="habits.js"></script>
</body>
</html>