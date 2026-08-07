<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/auth.php';
requireLogin();
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/db.php';

$userId = (int) $_SESSION['user_id'];
$errors = [];

// Every subtask this user owns, for the "add reminder" dropdown —
// plays the same role habits.php's $categories list plays there.
$subtaskOptStmt = mysqli_prepare($conn, 'SELECT SUBTASK.subtask_id, SUBTASK.subtask_name, HABIT.habit_name
    FROM SUBTASK
    INNER JOIN HABIT ON SUBTASK.habit_id = HABIT.habit_id
    INNER JOIN CATEGORY ON HABIT.category_id = CATEGORY.category_id
    WHERE CATEGORY.user_id = ?
    ORDER BY HABIT.habit_name, SUBTASK.order_no');
mysqli_stmt_bind_param($subtaskOptStmt, 'i', $userId);
mysqli_stmt_execute($subtaskOptStmt);
$subtaskOptResult = mysqli_stmt_get_result($subtaskOptStmt);
$subtaskOptions = mysqli_fetch_all($subtaskOptResult, MYSQLI_ASSOC);
mysqli_stmt_close($subtaskOptStmt);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('Invalid request.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $subtaskId = $_POST['subtask_id'] ?? '';
        $reminderTime = trim($_POST['reminder_time'] ?? '');
        $reminderType = $_POST['reminder_type'] ?? '';

        // Ownership check against the $subtaskOptions list already
        // fetched above — same pattern as habits.php's category check.
        $ownsSubtask = false;
        foreach ($subtaskOptions as $opt) {
            if ((string) $opt['subtask_id'] === (string) $subtaskId) {
                $ownsSubtask = true;
                break;
            }
        }
        if (!$ownsSubtask) {
            $errors[] = 'Please select a valid subtask.';
        }

        if ($reminderTime === '' || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $reminderTime)) {
            $errors[] = 'Please enter a valid time.';
        } else {
            $reminderTime .= ':00';
        }

        if (!in_array($reminderType, ['once', 'daily', 'weekly'], true)) {
            $errors[] = 'Please select a valid reminder type.';
        }

        if (empty($errors)) {
            $subtaskId = (int) $subtaskId;
            $stmt = mysqli_prepare($conn, 'INSERT INTO REMINDER (subtask_id, reminder_time, reminder_type, is_active) VALUES (?, ?, ?, 1)');
            mysqli_stmt_bind_param($stmt, 'iss', $subtaskId, $reminderTime, $reminderType);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect('reminders.php');
        }
    }

    if ($action === 'toggle_active') {
        $reminderId = $_POST['reminder_id'] ?? '';

        if (filter_var($reminderId, FILTER_VALIDATE_INT) !== false) {
            $reminderId = (int) $reminderId;

            // No single pre-verified subtask_id to lean on here — this
            // page spans every subtask the user owns, so ownership is
            // enforced inline via the full JOIN chain, same one used
            // at the top of subtasks.php and reminders.php (old version).
            $stmt = mysqli_prepare($conn, 'UPDATE REMINDER SET is_active = NOT is_active
                WHERE reminder_id = ?
                  AND subtask_id IN (
                    SELECT SUBTASK.subtask_id FROM SUBTASK
                    INNER JOIN HABIT ON SUBTASK.habit_id = HABIT.habit_id
                    INNER JOIN CATEGORY ON HABIT.category_id = CATEGORY.category_id
                    WHERE CATEGORY.user_id = ?
                  )');
            mysqli_stmt_bind_param($stmt, 'ii', $reminderId, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect('reminders.php');
        }
    }

    if ($action === 'delete') {
        $reminderId = $_POST['reminder_id'] ?? '';

        if (filter_var($reminderId, FILTER_VALIDATE_INT) !== false) {
            $reminderId = (int) $reminderId;

            $stmt = mysqli_prepare($conn, 'DELETE FROM REMINDER
                WHERE reminder_id = ?
                  AND subtask_id IN (
                    SELECT SUBTASK.subtask_id FROM SUBTASK
                    INNER JOIN HABIT ON SUBTASK.habit_id = HABIT.habit_id
                    INNER JOIN CATEGORY ON HABIT.category_id = CATEGORY.category_id
                    WHERE CATEGORY.user_id = ?
                  )');
            mysqli_stmt_bind_param($stmt, 'ii', $reminderId, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect('reminders.php');
        }
    }
}

$reminderStmt = mysqli_prepare($conn, 'SELECT REMINDER.*, SUBTASK.subtask_name, HABIT.habit_name
    FROM REMINDER
    INNER JOIN SUBTASK ON REMINDER.subtask_id = SUBTASK.subtask_id
    INNER JOIN HABIT ON SUBTASK.habit_id = HABIT.habit_id
    INNER JOIN CATEGORY ON HABIT.category_id = CATEGORY.category_id
    WHERE CATEGORY.user_id = ?
    ORDER BY REMINDER.reminder_time ASC');
mysqli_stmt_bind_param($reminderStmt, 'i', $userId);
mysqli_stmt_execute($reminderStmt);
$reminderResult = mysqli_stmt_get_result($reminderStmt);
$reminders = mysqli_fetch_all($reminderResult, MYSQLI_ASSOC);
mysqli_stmt_close($reminderStmt);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Reminders — Habit Track</title>
  <link rel="stylesheet" href="reminders.css?v=20260801-2">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <div class="app-layout">
    <div class="sidebar">
      <?php require __DIR__ . '/../../includes/logo.php'; ?>
      <a href="../dashboard/dashboard.php" class="nav-item">Dashboard</a>
      <a href="../habits/habits.php" class="nav-item">Habits</a>
      <a href="../categories/categories.php" class="nav-item">Categories</a>
      <a href="reminders.php" class="nav-item active">Reminders</a>
      <div class="sidebar-footer">
        <a href="../auth/logout.php" class="nav-item">Logout</a>
      </div>
    </div>
    <div class="main-content">
      <div class="page-header"><h1>Reminders</h1></div>

      <?php foreach ($errors as $err): ?>
        <div class="error-box"><?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>

      <?php if (empty($subtaskOptions)): ?>
        <div class="empty-state">
          <p>You need at least one subtask before adding a reminder.</p>
          <a href="../habits/habits.php">Go to Habits →</a>
        </div>
      <?php else: ?>
        <div class="auth-card reminder-form-card">
          <form method="POST" action="reminders.php">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="action" value="add">

            <div class="field">
              <select name="subtask_id" class="select-input" required>
                <option value="">Select subtask</option>
                <?php foreach ($subtaskOptions as $opt): ?>
                  <option value="<?php echo $opt['subtask_id']; ?>"><?php echo htmlspecialchars($opt['habit_name'] . ' — ' . $opt['subtask_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="field"><input type="time" name="reminder_time" required></div>

            <div class="field">
              <select name="reminder_type" class="select-input" required>
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="once">Once</option>
              </select>
            </div>

            <button type="submit" class="btn-primary">Add Reminder</button>
          </form>
        </div>
      <?php endif; ?>

      <?php if (empty($reminders)): ?>
        <div class="empty-state"><p>No reminders yet.</p></div>
      <?php else: ?>
        <div class="reminder-list">
          <?php foreach ($reminders as $r): ?>
            <div class="auth-card reminder-row<?php echo $r['is_active'] ? '' : ' reminder-row-inactive'; ?>">
              <div>
                <div class="reminder-time"><?php echo htmlspecialchars(substr($r['reminder_time'], 0, 5)); ?></div>
                <div class="reminder-type"><?php echo htmlspecialchars(ucfirst($r['reminder_type'])); ?><?php echo $r['is_active'] ? '' : ' · paused'; ?></div>
                <div class="reminder-source"><?php echo htmlspecialchars($r['habit_name'] . ' — ' . $r['subtask_name']); ?></div>
              </div>
              <div class="reminder-actions">
                <form method="POST" action="reminders.php">
                  <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                  <input type="hidden" name="action" value="toggle_active">
                  <input type="hidden" name="reminder_id" value="<?php echo $r['reminder_id']; ?>">
                  <button type="submit" class="btn-toggle"><?php echo $r['is_active'] ? 'Pause' : 'Resume'; ?></button>
                </form>
                <form method="POST" action="reminders.php">
                  <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="reminder_id" value="<?php echo $r['reminder_id']; ?>">
                  <button type="submit" class="btn-delete">Delete</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>