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

$ownerStmt = mysqli_prepare($conn, 'SELECT HABIT.habit_id, HABIT.habit_name, HABIT.habit_nature FROM HABIT
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
if ($habit['habit_nature'] !== 'bad') {
    die('This page only applies to bad habits.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('Invalid request.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $value = trim($_POST['value'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($value === '') {
            $value = null;
        } elseif (filter_var($value, FILTER_VALIDATE_INT) === false) {
            $errors[] = 'Value must be a whole number.';
        } else {
            $value = (int) $value;
        }

        if (strlen($notes) > 255) {
            $errors[] = 'Notes must be 255 characters or less.';
        }

        // Same ordering that fixed the original phantom-row bug:
        // every validation branch above completes, and only then does
        // anything touch HABIT_LOG or Bad_Habit_Progress. No write is
        // reachable on a validation failure.
        if (empty($errors)) {
            $logStmt = mysqli_prepare($conn, 'SELECT log_id FROM HABIT_LOG WHERE habit_id = ? AND log_date = CURDATE()');
            mysqli_stmt_bind_param($logStmt, 'i', $habitId);
            mysqli_stmt_execute($logStmt);
            $logResult = mysqli_stmt_get_result($logStmt);
            $todayLog = mysqli_fetch_assoc($logResult);
            mysqli_stmt_close($logStmt);

            if ($todayLog) {
                $logId = (int) $todayLog['log_id'];
            } else {
                $createLog = mysqli_prepare($conn, "INSERT INTO HABIT_LOG (habit_id, log_date, status) VALUES (?, CURDATE(), 'done')");
                mysqli_stmt_bind_param($createLog, 'i', $habitId);
                mysqli_stmt_execute($createLog);
                mysqli_stmt_close($createLog);
                // mysqli's equivalent of $pdo->lastInsertId() —
                // reads the auto-increment id from the connection,
                // not from the statement.
                $logId = (int) mysqli_insert_id($conn);
            }

            $stmt = mysqli_prepare($conn, 'INSERT INTO Bad_Habit_Progress (log_id, log_date, value, notes) VALUES (?, CURDATE(), ?, ?)');
            mysqli_stmt_bind_param($stmt, 'iis', $logId, $value, $notes);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect('bad-habit-progress.php?habit_id=' . $habitId);
        }
    }

    if ($action === 'delete') {
        $progressId = $_POST['progress_id'] ?? '';

        if (filter_var($progressId, FILTER_VALIDATE_INT) === false) {
            $errors[] = 'Invalid progress entry.';
        }

        if (empty($errors)) {
            $progressId = (int) $progressId;

            // Ownership chain: progress -> log -> habit. habit_id here
            // is already the verified-owned one from the top of the file.
            $stmt = mysqli_prepare($conn, 'DELETE FROM Bad_Habit_Progress
                WHERE progress_id = ?
                  AND log_id IN (SELECT log_id FROM HABIT_LOG WHERE habit_id = ?)');
            mysqli_stmt_bind_param($stmt, 'ii', $progressId, $habitId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect('bad-habit-progress.php?habit_id=' . $habitId);
        }
    }
}

$progressStmt = mysqli_prepare($conn, 'SELECT Bad_Habit_Progress.*, HABIT_LOG.log_date AS log_date
    FROM Bad_Habit_Progress
    INNER JOIN HABIT_LOG ON Bad_Habit_Progress.log_id = HABIT_LOG.log_id
    WHERE HABIT_LOG.habit_id = ?
    ORDER BY Bad_Habit_Progress.log_date DESC, Bad_Habit_Progress.progress_id DESC');
mysqli_stmt_bind_param($progressStmt, 'i', $habitId);
mysqli_stmt_execute($progressStmt);
$progressResult = mysqli_stmt_get_result($progressStmt);
$progressEntries = mysqli_fetch_all($progressResult, MYSQLI_ASSOC);
mysqli_stmt_close($progressStmt);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Progress — Habit Track</title>
  <link rel="stylesheet" href="bad-habit-progress.css?v=20260801-2">
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
        <h1><?php echo htmlspecialchars($habit['habit_name']); ?> — Progress</h1>
      </div>

      <?php foreach ($errors as $err): ?>
        <div class="error-box"><?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>

      <div class="auth-card progress-form-card">
        <form method="POST" action="bad-habit-progress.php?habit_id=<?php echo $habitId; ?>">
          <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="habit_id" value="<?php echo $habitId; ?>">

          <div class="field"><input type="number" name="value" placeholder="Quantity (optional)"></div>
          <div class="field"><input type="text" name="notes" placeholder="Notes — what happened, any trigger?"></div>

          <button type="submit" class="btn-primary btn-danger">Add entry</button>
        </form>
      </div>

      <?php if (empty($progressEntries)): ?>
        <div class="empty-state"><p>No entries yet — that's a good thing.</p></div>
      <?php else: ?>
        <div class="progress-list">
          <?php foreach ($progressEntries as $p): ?>
            <div class="auth-card progress-card">
              <div>
                <div class="progress-date"><?php echo htmlspecialchars($p['log_date']); ?></div>
                <div class="progress-value">
                  <?php echo $p['value'] !== null ? htmlspecialchars($p['value']) : 'Occurred'; ?>
                </div>
                <?php if ($p['notes']): ?>
                  <div class="progress-notes"><?php echo htmlspecialchars($p['notes']); ?></div>
                <?php endif; ?>
              </div>
              <form method="POST" action="bad-habit-progress.php?habit_id=<?php echo $habitId; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="habit_id" value="<?php echo $habitId; ?>">
                <input type="hidden" name="progress_id" value="<?php echo $p['progress_id']; ?>">
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