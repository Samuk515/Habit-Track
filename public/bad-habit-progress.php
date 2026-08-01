<?php
require '../includes/auth.php';
requireLogin();
require '../includes/csrf.php';
require __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'];
$errors = [];

$habitId = $_GET['habit_id'] ?? ($_POST['habit_id'] ?? '');
if (filter_var($habitId, FILTER_VALIDATE_INT) === false) {
    die('Invalid habit.');
}

$ownerStmt = $pdo->prepare('SELECT HABIT.habit_id, HABIT.habit_name, HABIT.habit_nature FROM HABIT
    INNER JOIN CATEGORY ON HABIT.category_id = CATEGORY.category_id
    WHERE HABIT.habit_id = ? AND CATEGORY.user_id = ?');
$ownerStmt->execute([$habitId, $userId]);
$habit = $ownerStmt->fetch();

if (!$habit) {
    die('Habit not found.');
}
if ($habit['habit_nature'] !== 'bad') {
    die('This page only applies to bad habits.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Verify CSRF token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('Invalid request.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        // 2. Grab and validate: value (optional, must be integer if provided — same
        //    FILTER_VALIDATE_INT pattern as target_value in habits.php), notes (optional text)
        $value = trim($_POST['value'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($value === '') {
            $value = null;
        } elseif (filter_var($value, FILTER_VALIDATE_INT) === false) {
            $errors[] = 'Value must be a whole number.';
        }

        if (strlen($notes) > 255) {
            $errors[] = 'Notes must be 255 characters or less.';
        }

        if (empty($errors)) {
            // 3. Only after validation passes, ensure today's log exists.
            $logStmt = $pdo->prepare('SELECT log_id FROM HABIT_LOG WHERE habit_id = ? AND log_date = CURDATE()');
            $logStmt->execute([$habitId]);
            $todayLog = $logStmt->fetch();

            if ($todayLog) {
                $logId = $todayLog['log_id'];
            } else {
                $createLog = $pdo->prepare("INSERT INTO HABIT_LOG (habit_id, log_date, status) VALUES (?, CURDATE(), 'done')");
                $createLog->execute([$habitId]);
                $logId = $pdo->lastInsertId();
            }

            // 4. Insert the individual progress occurrence under today's log.
            $stmt = $pdo->prepare('INSERT INTO Bad_Habit_Progress (log_id, log_date, value, notes) VALUES (?, CURDATE(), ?, ?)');
            $stmt->execute([$logId, $value, $notes]);

            header('Location: bad-habit-progress.php?habit_id=' . urlencode((string) $habitId));
            exit;
        }
    }

    if ($action === 'delete') {
        // 5. Grab progress_id from $_POST, validate it's an integer
        $progressId = $_POST['progress_id'] ?? '';

        // 6. Delete, scoped through the log chain back to this verified habit:
        //    DELETE FROM Bad_Habit_Progress
        //    WHERE progress_id = ?
        //      AND log_id IN (SELECT log_id FROM HABIT_LOG WHERE habit_id = ?)
        //    bind $progressId, $habitId
        if (filter_var($progressId, FILTER_VALIDATE_INT) === false) {
            $errors[] = 'Invalid progress entry.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('DELETE FROM Bad_Habit_Progress
                WHERE progress_id = ?
                  AND log_id IN (SELECT log_id FROM HABIT_LOG WHERE habit_id = ?)');
            $stmt->execute([$progressId, $habitId]);

            header('Location: bad-habit-progress.php?habit_id=' . urlencode((string) $habitId));
            exit;
        }
    }
}

// 7. Fetch progress entries for this habit across all days, most recent first:
//    SELECT Bad_Habit_Progress.*, HABIT_LOG.log_date AS log_date
//    FROM Bad_Habit_Progress
//    INNER JOIN HABIT_LOG ON Bad_Habit_Progress.log_id = HABIT_LOG.log_id
//    WHERE HABIT_LOG.habit_id = ?
//    ORDER BY Bad_Habit_Progress.log_date DESC, Bad_Habit_Progress.progress_id DESC
$progressStmt = $pdo->prepare('SELECT Bad_Habit_Progress.*, HABIT_LOG.log_date AS log_date
    FROM Bad_Habit_Progress
    INNER JOIN HABIT_LOG ON Bad_Habit_Progress.log_id = HABIT_LOG.log_id
    WHERE HABIT_LOG.habit_id = ?
    ORDER BY Bad_Habit_Progress.log_date DESC, Bad_Habit_Progress.progress_id DESC');
$progressStmt->execute([$habitId]);
$progressEntries = $progressStmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Progress — Habit Track</title>
  <link rel="stylesheet" href="assets/css/style.css?v=20260801-2">
</head>
<body>
  <div class="app-layout">
    <div class="sidebar">
      <?php require '../includes/logo.php'; ?>
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
        <h1><?php echo htmlspecialchars($habit['habit_name']); ?> — Progress</h1>
      </div>

      <?php foreach ($errors as $err): ?>
        <div class="error-box"><?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>

      <div class="auth-card" style="max-width:500px;margin-bottom:24px;">
        <form method="POST" action="bad-habit-progress.php?habit_id=<?php echo $habitId; ?>">
          <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="habit_id" value="<?php echo $habitId; ?>">

          <div class="field"><input type="number" name="value" placeholder="Quantity (optional)"></div>
          <div class="field"><input type="text" name="notes" placeholder="Notes — what happened, any trigger?"></div>

          <button type="submit" class="btn-primary" style="background:var(--coral);">Add entry</button>
        </form>
      </div>

      <?php if (empty($progressEntries)): ?>
        <div class="empty-state"><p>No entries yet — that's a good thing.</p></div>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:10px;">
          <?php foreach ($progressEntries as $p): ?>
            <div class="auth-card" style="max-width:500px;padding:16px 20px;display:flex;justify-content:space-between;align-items:center;">
              <div>
                <div style="font-size:12px;color:var(--muted);margin-bottom:2px;"><?php echo htmlspecialchars($p['log_date']); ?></div>
                <div style="font-weight:600;">
                  <?php echo $p['value'] !== null ? htmlspecialchars($p['value']) : 'Occurred'; ?>
                </div>
                <?php if ($p['notes']): ?>
                  <div style="font-size:12px;color:var(--muted);margin-top:2px;"><?php echo htmlspecialchars($p['notes']); ?></div>
                <?php endif; ?>
              </div>
              <form method="POST" action="bad-habit-progress.php?habit_id=<?php echo $habitId; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="habit_id" value="<?php echo $habitId; ?>">
                <input type="hidden" name="progress_id" value="<?php echo $p['progress_id']; ?>">
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
