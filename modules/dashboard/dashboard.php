<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/auth.php';
requireLogin();
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/db.php';

$userId = (int) $_SESSION['user_id'];
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
        } else {
            $habitId = (int) $habitId;
        }

        // Ownership check — HABIT has no user_id column directly, so
        // this walks HABIT -> CATEGORY -> user_id, same JOIN pattern
        // used everywhere else below HABIT in the schema.
        if (empty($errors)) {
            $ownerStmt = mysqli_prepare($conn, 'SELECT HABIT.habit_id FROM HABIT
                INNER JOIN CATEGORY ON HABIT.category_id = CATEGORY.category_id
                WHERE HABIT.habit_id = ? AND CATEGORY.user_id = ?');
            mysqli_stmt_bind_param($ownerStmt, 'ii', $habitId, $userId);
            mysqli_stmt_execute($ownerStmt);
            $ownerResult = mysqli_stmt_get_result($ownerStmt);
            $habit = mysqli_fetch_assoc($ownerResult);
            mysqli_stmt_close($ownerStmt);

            if (!$habit) {
                $errors[] = 'Habit not found.';
            }
        }

        // Only touch HABIT_LOG once ownership is confirmed and the
        // habit id is a validated int — no write before both checks pass.
        if (empty($errors)) {
            $logStmt = mysqli_prepare($conn, 'SELECT log_id FROM HABIT_LOG WHERE habit_id = ? AND log_date = CURDATE()');
            mysqli_stmt_bind_param($logStmt, 'i', $habitId);
            mysqli_stmt_execute($logStmt);
            $logResult = mysqli_stmt_get_result($logStmt);
            $todayLog = mysqli_fetch_assoc($logResult);
            mysqli_stmt_close($logStmt);

            if ($todayLog) {
                $logId = (int) $todayLog['log_id'];
                $deleteStmt = mysqli_prepare($conn, 'DELETE FROM HABIT_LOG WHERE log_id = ?');
                mysqli_stmt_bind_param($deleteStmt, 'i', $logId);
                mysqli_stmt_execute($deleteStmt);
                mysqli_stmt_close($deleteStmt);
            } else {
                $insertStmt = mysqli_prepare($conn, "INSERT INTO HABIT_LOG (habit_id, log_date, status) VALUES (?, CURDATE(), 'done')");
                mysqli_stmt_bind_param($insertStmt, 'i', $habitId);
                mysqli_stmt_execute($insertStmt);
                mysqli_stmt_close($insertStmt);
            }

            calculateAndSaveStreak($conn, $habitId);

            redirect('dashboard.php');
        }
    }
}

$stmt = mysqli_prepare($conn, 'SELECT
    HABIT.habit_id, HABIT.habit_name, HABIT.habit_nature,
    CATEGORY.category_name,
    HABIT_LOG.status AS today_status,
    COALESCE(STREAK.current_streak, 0) AS current_streak
  FROM HABIT
  INNER JOIN CATEGORY ON HABIT.category_id = CATEGORY.category_id
  LEFT JOIN HABIT_LOG ON HABIT_LOG.habit_id = HABIT.habit_id AND HABIT_LOG.log_date = CURDATE()
  LEFT JOIN STREAK ON STREAK.habit_id = HABIT.habit_id
  WHERE CATEGORY.user_id = ?
  ORDER BY HABIT.created_at DESC');
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$habits = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Dashboard — Habit Track</title>
  <link rel="stylesheet" href="dashboard.css?v=20260801-2">
</head>
<body>
  <div class="app-layout">
    <div class="sidebar">
      <?php require __DIR__ . '/../../includes/logo.php'; ?>
      <a href="dashboard.php" class="nav-item active">Dashboard</a>
      <a href="../habits/habits.php" class="nav-item">Habits</a>
      <a href="../categories/categories.php" class="nav-item">Categories</a>
      <div class="sidebar-footer">
        <a href="../auth/logout.php" class="nav-item">Logout</a>
      </div>
    </div>
    <div class="main-content">
      <div class="page-header">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
      </div>

      <?php foreach ($errors as $err): ?>
        <div class="error-box"><?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>

      <?php if (empty($habits)): ?>
        <div class="empty-state">
          <p>No habits yet — <a href="../habits/habits.php">create your first one →</a></p>
        </div>
      <?php else: ?>
        <div class="habit-grid">
          <?php foreach ($habits as $h): ?>
            <?php $isDone = $h['today_status'] === 'done'; ?>
            <div class="auth-card habit-card">
              <div class="habit-category"><?php echo htmlspecialchars($h['category_name']); ?></div>
              <?php if ($h['current_streak'] > 0): ?>
                <div class="habit-streak">🔥 <?php echo (int) $h['current_streak']; ?></div>
              <?php endif; ?>
              <div class="habit-name"><?php echo htmlspecialchars($h['habit_name']); ?></div>
              <form method="POST" action="dashboard.php">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="toggle_done">
                <input type="hidden" name="habit_id" value="<?php echo $h['habit_id']; ?>">
                <button type="submit" class="btn-primary<?php echo $isDone ? ' btn-done' : ''; ?>">
                  <?php echo $isDone ? '✓ Done' : 'Mark done'; ?>
                </button>
              </form>
              <?php if ($h['habit_nature'] === 'bad'): ?>
                <a href="../bad-habit-progress/bad-habit-progress.php?habit_id=<?php echo $h['habit_id']; ?>" class="habit-bad-link">Log occurrence</a>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>