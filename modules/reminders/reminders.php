<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/auth.php';
requireLogin();
require __DIR__ . '/../../includes/db.php';

$userId = (int) $_SESSION['user_id'];
$errors = [];

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
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $subtaskIdRaw = $_POST['subtask_id'] ?? '';
        $label = trim($_POST['label'] ?? '');
        $reminderTime = trim($_POST['reminder_time'] ?? '');
        $reminderType = $_POST['reminder_type'] ?? '';

        // "extra" (or empty) means this reminder isn't tied to any
        // subtask — a deliberate, documented relaxation of the
        // originally-mandatory REMINDER -> SUBTASK relationship.
        $isExtra = ($subtaskIdRaw === '' || $subtaskIdRaw === 'extra');
        $subtaskId = null;

        if ($isExtra) {
            if ($label === '') {
                $errors[] = 'Please enter a label for this reminder.';
            }
        } else {
            $ownsSubtask = false;
            foreach ($subtaskOptions as $opt) {
                if ((string) $opt['subtask_id'] === (string) $subtaskIdRaw) {
                    $ownsSubtask = true;
                    break;
                }
            }
            if (!$ownsSubtask) {
                $errors[] = 'Please select a valid subtask.';
            } else {
                $subtaskId = (int) $subtaskIdRaw;
            }
            // Subtask-linked reminders derive their display name via
            // the JOIN below — a stored label would just go stale the
            // moment the subtask got renamed, so it's intentionally null.
            $label = null;
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
            $stmt = mysqli_prepare($conn, 'INSERT INTO REMINDER (user_id, subtask_id, label, reminder_time, reminder_type, is_active) VALUES (?, ?, ?, ?, ?, 1)');
            mysqli_stmt_bind_param($stmt, 'iisss', $userId, $subtaskId, $label, $reminderTime, $reminderType);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            header('Location: reminders.php');
            exit;
        }
    }

    if ($action === 'toggle_active') {
        $reminderId = $_POST['reminder_id'] ?? '';

        if (filter_var($reminderId, FILTER_VALIDATE_INT) !== false) {
            $reminderId = (int) $reminderId;

            // Direct user_id check now — simpler than the old JOIN
            // subquery, and it's the only way to verify ownership of
            // an Extra reminder anyway, since those have no subtask
            // to walk back through.
            $stmt = mysqli_prepare($conn, 'UPDATE REMINDER SET is_active = NOT is_active WHERE reminder_id = ? AND user_id = ?');
            mysqli_stmt_bind_param($stmt, 'ii', $reminderId, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            header('Location: reminders.php');
            exit;
        }
    }

    if ($action === 'delete') {
        $reminderId = $_POST['reminder_id'] ?? '';

        if (filter_var($reminderId, FILTER_VALIDATE_INT) !== false) {
            $reminderId = (int) $reminderId;

            $stmt = mysqli_prepare($conn, 'DELETE FROM REMINDER WHERE reminder_id = ? AND user_id = ?');
            mysqli_stmt_bind_param($stmt, 'ii', $reminderId, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            header('Location: reminders.php');
            exit;
        }
    }
}

// LEFT JOIN, not INNER — an INNER JOIN would silently drop every
// Extra reminder from this list, since they have no matching SUBTASK
// row by definition.
$reminderStmt = mysqli_prepare($conn, 'SELECT REMINDER.*, SUBTASK.subtask_name, HABIT.habit_name
    FROM REMINDER
    LEFT JOIN SUBTASK ON REMINDER.subtask_id = SUBTASK.subtask_id
    LEFT JOIN HABIT ON SUBTASK.habit_id = HABIT.habit_id
    WHERE REMINDER.user_id = ?
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
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="reminders.css?v=20260801-3">
</head>
<body>
  <div class="app-layout">
    <div class="sidebar">
      <?php require __DIR__ . '/../../includes/logo.php'; ?>
      <a href="../dashboard/dashboard.php" class="nav-item">Dashboard</a>
      <a href="../habits/habits.php" class="nav-item">Habits</a>
      <a href="../categories/categories.php" class="nav-item">Categories</a>
      <a href="reminders.php" class="nav-item active">Reminders</a>
      <a href="../calendar/calendar.php" class="nav-item">Calendar</a>
      <div class="sidebar-footer">
        <a href="../auth/logout.php" class="nav-item">Logout</a>
      </div>
    </div>
    <div class="main-content">
      <div class="page-header"><h1>Reminders</h1></div>

      <div class="notification-bar">
        <button type="button" id="enable-notifications-btn" class="btn-notify">🔔 Enable notifications</button>
        <span id="notification-status" class="notification-status"></span>
      </div>

      <?php foreach ($errors as $err): ?>
        <div class="error-box"><?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>

      <div class="auth-card reminder-form-card">
        <form method="POST" action="reminders.php">
          <input type="hidden" name="action" value="add">

          <div class="field">
            <select name="subtask_id" id="reminder-subtask-select" class="select-input" required>
              <option value="" disabled selected>Select subtask or Extra</option>
              <option value="extra">— Extra (not linked to a subtask) —</option>
              <?php foreach ($subtaskOptions as $opt): ?>
                <option value="<?php echo $opt['subtask_id']; ?>"><?php echo htmlspecialchars($opt['habit_name'] . ' — ' . $opt['subtask_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field reminder-label-field" id="reminder-label-wrapper">
            <input type="text" name="label" placeholder="What's this reminder for? (e.g. Study session)">
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

      <?php if (empty($reminders)): ?>
        <div class="empty-state"><p>No reminders yet.</p></div>
      <?php else: ?>
        <div class="reminder-list">
          <?php foreach ($reminders as $r): ?>
            <?php
              $isExtra = $r['subtask_id'] === null;
              $displayLabel = $isExtra ? $r['label'] : ($r['habit_name'] . ' — ' . $r['subtask_name']);
            ?>
            <div class="auth-card reminder-row<?php echo $r['is_active'] ? '' : ' reminder-row-inactive'; ?>">
              <div>
                <div class="reminder-time"><?php echo htmlspecialchars(substr($r['reminder_time'], 0, 5)); ?></div>
                <div class="reminder-type"><?php echo htmlspecialchars(ucfirst($r['reminder_type'])); ?><?php echo $r['is_active'] ? '' : ' · paused'; ?></div>
                <div class="reminder-source">
                  <?php echo htmlspecialchars((string) $displayLabel); ?>
                  <?php if ($isExtra): ?><span class="badge-extra">Extra</span><?php endif; ?>
                </div>
              </div>
              <div class="reminder-actions">
                <form method="POST" action="reminders.php">
                  <input type="hidden" name="action" value="toggle_active">
                  <input type="hidden" name="reminder_id" value="<?php echo $r['reminder_id']; ?>">
                  <button type="submit" class="btn-toggle"><?php echo $r['is_active'] ? 'Pause' : 'Resume'; ?></button>
                </form>
                <form method="POST" action="reminders.php">
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
  <script>
    window.ACTIVE_REMINDERS = <?php
        $activeRemindersForJs = [];
        foreach ($reminders as $r) {
            if (!$r['is_active']) {
                continue;
            }
            $isExtraJs = $r['subtask_id'] === null;
            $activeRemindersForJs[] = [
                'id' => (int) $r['reminder_id'],
                'time' => substr($r['reminder_time'], 0, 5),
                'label' => $isExtraJs ? $r['label'] : ($r['habit_name'] . ' — ' . $r['subtask_name']),
                'type' => $r['reminder_type'],
            ];
        }
        echo json_encode($activeRemindersForJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?>;
  </script>
  <script src="reminders.js"></script>
</body>
</html>