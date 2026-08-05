<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/auth.php';
requireLogin();
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/db.php';

$userId = (int) $_SESSION['user_id'];
$errors = [];

$habitId = $_GET['habit_id'] ?? ($_POST['habit_id'] ?? '');
if (filter_var($habitId, FILTER_VALIDATE_INT) === false) {
    die('Invalid habit.');
}
$habitId = (int) $habitId;

// Ownership verified ONCE, here, at the top of the file — every
// query below trusts this $habitId without re-checking, because it's
// already scoped to a habit that belongs to CATEGORY.user_id = $userId.
$ownerStmt = mysqli_prepare($conn, 'SELECT HABIT.habit_id, HABIT.habit_name FROM HABIT
    INNER JOIN CATEGORY ON HABIT.category_id = CATEGORY.category_id
    WHERE HABIT.habit_id = ? AND CATEGORY.user_id = ?');
mysqli_stmt_bind_param($ownerStmt, 'ii', $habitId, $userId);
mysqli_stmt_execute($ownerStmt);
$ownerResult = mysqli_stmt_get_result($ownerStmt);
$habit = mysqli_fetch_assoc($ownerResult);
mysqli_stmt_close($ownerStmt);

if (!$habit) {
    die('Habit not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('Invalid request.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $subtaskName = trim($_POST['subtask_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isOptional = isset($_POST['is_optional']) ? 1 : 0;

        if ($subtaskName === '') {
            $errors[] = 'Subtask name is required.';
        }

        if ($description === '') {
            $description = null;
        }

        if (empty($errors)) {
            // mysqli has no fetchColumn() — pull the single column out
            // of the fetched row manually.
            $orderStmt = mysqli_prepare($conn, 'SELECT COALESCE(MAX(order_no), 0) + 1 AS next_order FROM SUBTASK WHERE habit_id = ?');
            mysqli_stmt_bind_param($orderStmt, 'i', $habitId);
            mysqli_stmt_execute($orderStmt);
            $orderResult = mysqli_stmt_get_result($orderStmt);
            $orderRow = mysqli_fetch_assoc($orderResult);
            $nextOrder = (int) $orderRow['next_order'];
            mysqli_stmt_close($orderStmt);

            $insertStmt = mysqli_prepare($conn, 'INSERT INTO SUBTASK (habit_id, subtask_name, description, is_optional, order_no) VALUES (?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($insertStmt, 'issii', $habitId, $subtaskName, $description, $isOptional, $nextOrder);
            mysqli_stmt_execute($insertStmt);
            mysqli_stmt_close($insertStmt);

            redirect('subtasks.php?habit_id=' . $habitId);
        }
    }

    if ($action === 'delete') {
        $subtaskId = $_POST['subtask_id'] ?? '';

        if (filter_var($subtaskId, FILTER_VALIDATE_INT) !== false) {
            $subtaskId = (int) $subtaskId;

            // habit_id is already the verified-owned one from the top
            // of the file — this WHERE clause can't touch another
            // user's subtask even if subtask_id were guessed/tampered.
            $deleteStmt = mysqli_prepare($conn, 'DELETE FROM SUBTASK WHERE subtask_id = ? AND habit_id = ?');
            mysqli_stmt_bind_param($deleteStmt, 'ii', $subtaskId, $habitId);
            mysqli_stmt_execute($deleteStmt);
            mysqli_stmt_close($deleteStmt);

            redirect('subtasks.php?habit_id=' . $habitId);
        }
    }
}

$subtaskStmt = mysqli_prepare($conn, 'SELECT * FROM SUBTASK WHERE habit_id = ? ORDER BY order_no ASC');
mysqli_stmt_bind_param($subtaskStmt, 'i', $habitId);
mysqli_stmt_execute($subtaskStmt);
$subtaskResult = mysqli_stmt_get_result($subtaskStmt);
$subtasks = mysqli_fetch_all($subtaskResult, MYSQLI_ASSOC);
mysqli_stmt_close($subtaskStmt);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Subtasks — Habit Track</title>
  <link rel="stylesheet" href="subtasks.css?v=20260801-2">
</head>
<body>
  <div class="app-layout">
    <div class="sidebar">
      <?php require __DIR__ . '/../../includes/logo.php'; ?>
      <a href="../dashboard/dashboard.php" class="nav-item">Dashboard</a>
      <a href="../habits/habits.php" class="nav-item active">Habits</a>
      <a href="../categories/categories.php" class="nav-item">Categories</a>
      <div class="sidebar-footer">
        <a href="../auth/logout.php" class="nav-item">Logout</a>
      </div>
    </div>
    <div class="main-content">
      <a href="../habits/habits.php" class="back-link">← Back to Habits</a>
      <div class="page-header">
        <h1><?php echo htmlspecialchars($habit['habit_name']); ?> — Subtasks</h1>
      </div>

      <?php foreach ($errors as $err): ?>
        <div class="error-box"><?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>

      <div class="auth-card subtask-form-card">
        <form method="POST" action="subtasks.php?habit_id=<?php echo $habitId; ?>">
          <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="habit_id" value="<?php echo $habitId; ?>">

          <div class="field"><input type="text" name="subtask_name" placeholder="Subtask name" required></div>
          <div class="field"><input type="text" name="description" placeholder="Description (optional)"></div>
          <div class="field field-checkbox">
            <input type="checkbox" name="is_optional" id="is_optional" class="checkbox-input">
            <label for="is_optional" class="checkbox-label">This subtask is optional</label>
          </div>

          <button type="submit" class="btn-primary">Add Subtask</button>
        </form>
      </div>

      <?php if (empty($subtasks)): ?>
        <div class="empty-state"><p>No subtasks yet.</p></div>
      <?php else: ?>
        <div class="subtask-list">
          <?php foreach ($subtasks as $s): ?>
            <div class="auth-card subtask-card">
              <div>
                <div class="subtask-name">
                  <?php echo htmlspecialchars($s['subtask_name']); ?>
                  <?php if ($s['is_optional']): ?>
                    <span class="subtask-optional-badge">(optional)</span>
                  <?php endif; ?>
                </div>
                <?php if ($s['description']): ?>
                  <div class="subtask-desc"><?php echo htmlspecialchars($s['description']); ?></div>
                <?php endif; ?>
              </div>
              <form method="POST" action="subtasks.php?habit_id=<?php echo $habitId; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="habit_id" value="<?php echo $habitId; ?>">
                <input type="hidden" name="subtask_id" value="<?php echo $s['subtask_id']; ?>">
                <button type="submit" class="btn-delete">Delete</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>