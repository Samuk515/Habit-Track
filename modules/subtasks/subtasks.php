<?php
require __DIR__ . '/../../includes/auth.php';
requireLogin();
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/db.php';

$userId = $_SESSION['user_id'];
$errors = [];

$habitId = $_GET['habit_id'] ?? ($_POST['habit_id'] ?? '');
if (filter_var($habitId, FILTER_VALIDATE_INT) === false) {
    die('Invalid habit.');
}

$ownerStmt = $pdo->prepare('SELECT HABIT.habit_id, HABIT.habit_name FROM HABIT
    INNER JOIN CATEGORY ON HABIT.category_id = CATEGORY.category_id
    WHERE HABIT.habit_id = ? AND CATEGORY.user_id = ?');
$ownerStmt->execute([$habitId, $userId]);
$habit = $ownerStmt->fetch();
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
      $orderStmt = $pdo->prepare('SELECT COALESCE(MAX(order_no), 0) + 1 AS next_order FROM SUBTASK WHERE habit_id = ?');
      $orderStmt->execute([$habitId]);
      $nextOrder = (int) $orderStmt->fetchColumn();

      $insertStmt = $pdo->prepare('INSERT INTO SUBTASK (habit_id, subtask_name, description, is_optional, order_no) VALUES (?, ?, ?, ?, ?)');
      $insertStmt->execute([$habitId, $subtaskName, $description, $isOptional, $nextOrder]);
    }
    }

    if ($action === 'delete') {
    $subtaskId = $_POST['subtask_id'] ?? '';

    if (filter_var($subtaskId, FILTER_VALIDATE_INT) !== false) {
      $deleteStmt = $pdo->prepare('DELETE FROM SUBTASK WHERE subtask_id = ? AND habit_id = ?');
      $deleteStmt->execute([$subtaskId, $habitId]);
    }
    }
}

$subtaskStmt = $pdo->prepare('SELECT * FROM SUBTASK WHERE habit_id = ? ORDER BY order_no ASC');
$subtaskStmt->execute([$habitId]);
$subtasks = $subtaskStmt->fetchAll();
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
      <a href="dashboard.php" class="nav-item">Dashboard</a>
      <a href="habits.php" class="nav-item active">Habits</a>
      <a href="categories.php" class="nav-item">Categories</a>
      <div style="margin-top:auto;">
        <a href="logout.php" class="nav-item">Logout</a>
      </div>
    </div>
    <div class="main-content">
      <a href="habits.php" style="font-size:13px;color:var(--muted);">← Back to Habits</a>
      <div class="page-header">
        <h1><?php echo htmlspecialchars($habit['habit_name']); ?> — Subtasks</h1>
      </div>

      <?php foreach ($errors as $err): ?>
        <div class="error-box"><?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>

      <div class="auth-card" style="max-width:500px;margin-bottom:24px;">
        <form method="POST" action="subtasks.php?habit_id=<?php echo $habitId; ?>">
          <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="habit_id" value="<?php echo $habitId; ?>">

          <div class="field"><input type="text" name="subtask_name" placeholder="Subtask name" required></div>
          <div class="field"><input type="text" name="description" placeholder="Description (optional)"></div>
          <div class="field" style="display:flex;align-items:center;gap:8px;">
            <input type="checkbox" name="is_optional" id="is_optional" style="width:auto;">
            <label for="is_optional" style="font-size:14px;color:var(--muted);">This subtask is optional</label>
          </div>

          <button type="submit" class="btn-primary">Add Subtask</button>
        </form>
      </div>

      <?php if (empty($subtasks)): ?>
        <div class="empty-state"><p>No subtasks yet.</p></div>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:10px;">
          <?php foreach ($subtasks as $s): ?>
            <div class="auth-card" style="max-width:500px;padding:16px 20px;display:flex;justify-content:space-between;align-items:center;">
              <div>
                <div style="font-weight:600;">
                  <?php echo htmlspecialchars($s['subtask_name']); ?>
                  <?php if ($s['is_optional']): ?>
                    <span style="font-size:11px;color:var(--muted);font-weight:400;">(optional)</span>
                  <?php endif; ?>
                </div>
                <?php if ($s['description']): ?>
                  <div style="font-size:12px;color:var(--muted);margin-top:2px;"><?php echo htmlspecialchars($s['description']); ?></div>
                <?php endif; ?>
              </div>
              <form method="POST" action="subtasks.php?habit_id=<?php echo $habitId; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="habit_id" value="<?php echo $habitId; ?>">
                <input type="hidden" name="subtask_id" value="<?php echo $s['subtask_id']; ?>">
                <button type="submit" style="background:none;border:1px solid var(--border);color:var(--coral);border-radius:8px;padding:6px 12px;font-size:13px;cursor:pointer;">Delete</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
