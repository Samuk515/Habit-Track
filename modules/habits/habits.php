<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/auth.php';
requireLogin();
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/db.php';

$errors = [];
$userId = (int) $_SESSION['user_id'];

$catStmt = mysqli_prepare($conn, 'SELECT category_id, category_name FROM CATEGORY WHERE user_id = ? ORDER BY category_name');
mysqli_stmt_bind_param($catStmt, 'i', $userId);
mysqli_stmt_execute($catStmt);
$catResult = mysqli_stmt_get_result($catStmt);
$categories = mysqli_fetch_all($catResult, MYSQLI_ASSOC);
mysqli_stmt_close($catStmt);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('Invalid request.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {

        $habitName = trim($_POST['habit_name'] ?? '');
        $categoryId = $_POST['category_id'] ?? '';
        $habitNature = $_POST['habit_nature'] ?? '';
        $measurementType = $_POST['measurement_type'] ?? '';
        $targetValue = trim($_POST['target_value'] ?? '');
        $targetType = $_POST['target_type'] ?? '';
        $description = trim($_POST['description'] ?? '');

        if ($habitName === '') {
            $errors[] = 'Habit name is required.';
        }

        // Ownership check against the $categories list already fetched
        // above (belongs to this user) — not a fresh query, since we
        // already have exactly the set of categories this user owns.
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

        if (!in_array($measurementType, ['boolean', 'count', 'duration'], true)) {
            $errors[] = 'Please select a valid measurement type.';
        }

        if (!in_array($targetType, ['daily', 'weekly'], true)) {
            $errors[] = 'Please select a valid target type.';
        }

        // Cast only happens on the validated-int branch, so an empty
        // target value stays PHP null (-> SQL NULL) rather than
        // becoming (int) null == 0, which would be a different,
        // wrong value in the database.
        if ($targetValue === '') {
            $targetValue = null;
        } elseif (filter_var($targetValue, FILTER_VALIDATE_INT) === false) {
            $errors[] = 'Target value must be a whole number.';
        } else {
            $targetValue = (int) $targetValue;
        }

        if (empty($errors)) {
            $categoryId = (int) $categoryId;

            $stmt = mysqli_prepare($conn, 'INSERT INTO HABIT (category_id, habit_name, habit_nature, measurement_type, target_value, target_type, description) VALUES (?, ?, ?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'isssiss', $categoryId, $habitName, $habitNature, $measurementType, $targetValue, $targetType, $description);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect('habits.php');
        }
    }

    if ($action === 'delete') {
        $habitId = $_POST['habit_id'] ?? '';

        if (filter_var($habitId, FILTER_VALIDATE_INT) !== false) {
            $habitId = (int) $habitId;

            // Ownership via subquery instead of JOIN — different shape,
            // same guarantee: only deletes if the habit's category
            // belongs to this user.
            $stmt = mysqli_prepare($conn, 'DELETE FROM HABIT WHERE habit_id = ? AND
            category_id IN (SELECT category_id FROM CATEGORY WHERE user_id = ?)');
            mysqli_stmt_bind_param($stmt, 'ii', $habitId, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect('habits.php');
        }
    }
}

$habitStmt = mysqli_prepare($conn, 'SELECT HABIT.*, CATEGORY.category_name
  FROM HABIT INNER JOIN CATEGORY ON HABIT.category_id = CATEGORY.category_id WHERE CATEGORY.user_id = ?
   ORDER BY HABIT.created_at DESC');
mysqli_stmt_bind_param($habitStmt, 'i', $userId);
mysqli_stmt_execute($habitStmt);
$habitResult = mysqli_stmt_get_result($habitStmt);
$habits = mysqli_fetch_all($habitResult, MYSQLI_ASSOC);
mysqli_stmt_close($habitStmt);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Habits — Habit Track</title>
  <link rel="stylesheet" href="habits.css?v=20260801-2">
</head>
<body>
  <div class="app-layout">
    <div class="sidebar">
      <?php require __DIR__ . '/../../includes/logo.php'; ?>
      <a href="../dashboard/dashboard.php" class="nav-item">Dashboard</a>
      <a href="habits.php" class="nav-item active">Habits</a>
      <a href="../categories/categories.php" class="nav-item">Categories</a>
      <div class="sidebar-footer">
        <a href="../auth/logout.php" class="nav-item">Logout</a>
      </div>
    </div>
    <div class="main-content">
      <div class="page-header"><h1>Habits</h1></div>

      <?php foreach ($errors as $err): ?>
        <div class="error-box"><?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>

      <div class="habits-layout">

        <?php if (empty($categories)): ?>
          <div class="empty-state flex-fill">
            <p>You need at least one category before adding a habit.</p>
            <a href="../categories/categories.php">Create a category →</a>
          </div>
        <?php else: ?>
          <div class="auth-card habit-form-card">
            <form method="POST" action="habits.php">
              <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
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
                <select name="measurement_type" class="select-input">
                  <option value="boolean">Simple (did / didn't)</option>
                  <option value="count">Count (e.g. glasses of water)</option>
                  <option value="duration">Duration (minutes)</option>
                </select>
              </div>

              <div class="field"><input type="number" name="target_value" placeholder="Target value (if count/duration)"></div>

              <div class="field">
                <select name="target_type" class="select-input">
                  <option value="daily">Daily</option>
                  <option value="weekly">Weekly</option>
                </select>
              </div>

              <div class="field"><input type="text" name="description" placeholder="Description (optional)"></div>

              <button type="submit" class="btn-primary">Add Habit</button>
            </form>
          </div>
        <?php endif; ?>

        <?php if (empty($habits)): ?>
          <div class="empty-state flex-fill"><p>No habits yet.</p></div>
        <?php else: ?>
          <div class="habit-list">
            <?php foreach ($habits as $h): ?>
              <div class="auth-card habit-row">
                <div>
                  <div class="habit-title"><?php echo htmlspecialchars($h['habit_name']); ?></div>
                  <div class="habit-row-category"><?php echo htmlspecialchars($h['category_name']); ?></div>
                </div>
                <div class="habit-row-actions">
                  <a href="../subtasks/subtasks.php?habit_id=<?php echo $h['habit_id']; ?>" class="link-purple">Manage subtasks</a>
                  <?php if ($h['habit_nature'] === 'bad'): ?>
                    <a href="../bad-habit-progress/bad-habit-progress.php?habit_id=<?php echo $h['habit_id']; ?>" class="link-coral">Log progress</a>
                  <?php endif; ?>
                  <form method="POST" action="habits.php">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="habit_id" value="<?php echo $h['habit_id']; ?>">
                    <button type="submit" class="btn-delete">Delete</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</body>
</html>