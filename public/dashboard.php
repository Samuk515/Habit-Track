<?php
require '../includes/auth.php';
requireLogin();
require '../includes/csrf.php';
require __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') { 
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('Invalid request.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_done') {
      
        $habitId = $_POST['habit_id'] ?? '';

        if (filter_var($habitId, FILTER_VALIDATE_INT) === false) {
            $errors[] = 'Invalid habit selected.';
        }

        if (empty($errors)) {
            $ownerStmt = $pdo->prepare('SELECT HABIT.habit_id FROM HABIT
                INNER JOIN CATEGORY ON HABIT.category_id = CATEGORY.category_id
                WHERE HABIT.habit_id = ? AND CATEGORY.user_id = ?');
            $ownerStmt->execute([$habitId, $userId]);
            $habit = $ownerStmt->fetch();

            if (!$habit) {
                $errors[] = 'Habit not found.';
            }
        }

        if (empty($errors)) {
            $logStmt = $pdo->prepare('SELECT log_id FROM HABIT_LOG WHERE habit_id = ? AND log_date = CURDATE()');
            $logStmt->execute([$habitId]);
            $todayLog = $logStmt->fetch();
            if ($todayLog) {
                $deleteStmt = $pdo->prepare('DELETE FROM HABIT_LOG WHERE log_id = ?');
                $deleteStmt->execute([$todayLog['log_id']]);
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO HABIT_LOG (habit_id, log_date, status) VALUES (?, CURDATE(), 'done')");
                $insertStmt->execute([$habitId]);
            }

            header('Location: dashboard.php');
            exit;
        }
    }
}
$stmt = $pdo->prepare('SELECT
    HABIT.habit_id, HABIT.habit_name, HABIT.habit_nature,
    CATEGORY.category_name,
    HABIT_LOG.status AS today_status
  FROM HABIT
  INNER JOIN CATEGORY ON HABIT.category_id = CATEGORY.category_id
  LEFT JOIN HABIT_LOG ON HABIT_LOG.habit_id = HABIT.habit_id AND HABIT_LOG.log_date = CURDATE()
  WHERE CATEGORY.user_id = ?
  ORDER BY HABIT.created_at DESC');
$stmt->execute([$userId]);
$habits = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Dashboard — Habit Track</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="app-layout">
    <div class="sidebar">
      <?php require '../includes/logo.php'; ?>
      <a href="dashboard.php" class="nav-item active">Dashboard</a>
      <a href="habits.php" class="nav-item">Habits</a>
      <a href="categories.php" class="nav-item">Categories</a>
      <div style="margin-top:auto;">
        <a href="logout.php" class="nav-item">Logout</a>
      </div>
    </div>
    <div class="main-content">
      <div class="page-header">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
        <?php foreach ($errors as $err): ?>
          <div class="error-box"><?php echo htmlspecialchars($err); ?></div>
        <?php endforeach; ?>
      </div>

      <?php if (empty($habits)): ?>
        <div class="empty-state">
          <p>No habits yet — <a href="habits.php">create your first one →</a></p>
        </div>
      <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;">
          <?php foreach ($habits as $h): ?>
            <?php $isDone = $h['today_status'] === 'done'; ?>
            <div class="auth-card" style="padding:16px 18px;">
              <div style="font-size:11px;color:var(--muted);margin-bottom:6px;"><?php echo htmlspecialchars($h['category_name']); ?></div>
              <div style="font-weight:600;margin-bottom:14px;"><?php echo htmlspecialchars($h['habit_name']); ?></div>
              <form method="POST" action="dashboard.php">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="toggle_done">
                <input type="hidden" name="habit_id" value="<?php echo $h['habit_id']; ?>">
                <button type="submit" class="btn-primary" style="<?php echo $isDone ? 'background:#1DD1A1;' : ''; ?>">
                  <?php echo $isDone ? '✓ Done' : 'Mark done'; ?>
                </button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
