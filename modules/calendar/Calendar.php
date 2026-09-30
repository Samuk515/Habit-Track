<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/auth.php';
requireLogin();
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/db.php';

$userId = (int) $_SESSION['user_id'];

$csrfToken = generateCsrfToken();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_milestone') {
      if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $_SESSION['flash_error'] = 'Invalid request. Please try again.';
        header('Location: calendar.php');
        exit;
      }

      $title = $_POST['title'] ?? '';
        $eventDate = $_POST['event_date'] ?? '';
      $description = $_POST['description'] ?? null;
      $result = add_calendar_milestone($conn, $userId, $eventDate, $title, $description);

      $_SESSION[$result['success'] ? 'flash_success' : 'flash_error'] = $result['success']
        ? 'Milestone added.'
        : $result['error'];
      header('Location: calendar.php' . ($result['success'] ? '?success=add' : ''));
      exit;
    }

    if ($action === 'add_event') {
      if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $_SESSION['flash_error'] = 'Invalid request. Please try again.';
        header('Location: calendar.php');
        exit;
      }

      $title = trim($_POST['title'] ?? '');
      $eventDate = $_POST['event_date'] ?? '';
      $description = trim($_POST['description'] ?? '');
      $dateObj = DateTime::createFromFormat('Y-m-d', $eventDate);
      if ($title === '' || strlen($title) > 100) {
        $errors[] = 'Event title is required (max 100 characters).';
      }
      if (!$dateObj || $dateObj->format('Y-m-d') !== $eventDate) {
        $errors[] = 'Please enter a valid event date.';
      }

      if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO CALENDAR_EVENT (user_id, label, event_date, description, event_type) VALUES (?, ?, ?, ?, 'event')");
        mysqli_stmt_bind_param($stmt, 'isss', $userId, $title, $eventDate, $description);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header('Location: calendar.php?success=add');
        exit;
      }
    }

    if ($action === 'add_reminder') {
      if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $_SESSION['flash_error'] = 'Invalid request. Please try again.';
        header('Location: calendar.php');
        exit;
      }

      $label = trim($_POST['reminder_label'] ?? '');
      $reminderTime = trim($_POST['reminder_time'] ?? '');
      $reminderType = $_POST['reminder_type'] ?? '';
      if ($label === '') {
        $errors[] = 'Reminder label is required.';
      }
      if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $reminderTime)) {
        $errors[] = 'Please enter a valid reminder time.';
      } else {
        $reminderTime .= ':00';
      }
      if (!in_array($reminderType, ['once', 'daily', 'weekly'], true)) {
        $errors[] = 'Please select a valid reminder type.';
      }

      if (empty($errors)) {
        $stmt = mysqli_prepare($conn, 'INSERT INTO REMINDER (user_id, subtask_id, label, reminder_time, reminder_type, is_active) VALUES (?, NULL, ?, ?, ?, 1)');
        mysqli_stmt_bind_param($stmt, 'isss', $userId, $label, $reminderTime, $reminderType);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header('Location: calendar.php?success=reminder');
        exit;
      }
    }

    if ($action === 'delete_milestone') {
        $eventId = $_POST['event_id'] ?? '';

        if (filter_var($eventId, FILTER_VALIDATE_INT) !== false) {
            $eventId = (int) $eventId;

            $stmt = mysqli_prepare($conn, "DELETE FROM CALENDAR_EVENT WHERE event_id = ? AND user_id = ? AND event_type IN ('milestone', 'event')");
            mysqli_stmt_bind_param($stmt, 'ii', $eventId, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            header('Location: calendar.php?success=delete');
            exit;
        }
    }
}

// The calendar grid and activity table share this event dataset.
$eventStmt = mysqli_prepare($conn, 'SELECT CALENDAR_EVENT.*,
    SUBTASK.subtask_name,
    COALESCE(HABIT_VIA_SUBTASK.habit_name, HABIT_DIRECT.habit_name) AS habit_name
    FROM CALENDAR_EVENT
    LEFT JOIN SUBTASK ON CALENDAR_EVENT.subtask_id = SUBTASK.subtask_id
    LEFT JOIN HABIT AS HABIT_VIA_SUBTASK ON SUBTASK.habit_id = HABIT_VIA_SUBTASK.habit_id
    LEFT JOIN HABIT AS HABIT_DIRECT ON CALENDAR_EVENT.habit_id = HABIT_DIRECT.habit_id
    WHERE CALENDAR_EVENT.user_id = ?
    ORDER BY CALENDAR_EVENT.event_date DESC, CALENDAR_EVENT.event_id DESC');
mysqli_stmt_bind_param($eventStmt, 'i', $userId);
mysqli_stmt_execute($eventStmt);
$eventResult = mysqli_stmt_get_result($eventStmt);
$allEvents = mysqli_fetch_all($eventResult, MYSQLI_ASSOC);
mysqli_stmt_close($eventStmt);

$eventsForJs = array_map(function ($e) {
    return [
        'date' => $e['event_date'],
        'label' => $e['label'],
      'habit' => $e['habit_name'] ?: 'Manual milestone',
      'description' => $e['description'] ?? '',
        'type' => $e['event_type'],
    ];
}, $allEvents);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Calendar — Habit Track</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/v/dt/dt-3.0.2/datatables.min.css">
  <link rel="stylesheet" href="Calendar.css?v=20260801-3">
</head>
<body>
<script>window.SERVER_ERRORS = <?php echo json_encode($errors, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
  <div class="app-layout">
    <div class="sidebar">
      <?php require __DIR__ . '/../../includes/logo.php'; ?>
      <a href="../dashboard/dashboard.php" class="nav-item">Dashboard</a>
      <a href="../habits/habits.php" class="nav-item">Habits</a>
      <a href="../categories/categories.php" class="nav-item">Categories</a>
      <a href="../reminders/reminders.php" class="nav-item">Reminders</a>
      <a href="calendar.php" class="nav-item active">Calendar</a>
      <a href="../settings/settings.php" class="nav-item">Settings</a>
      <div class="sidebar-footer">
        <a href="../auth/logout.php" class="nav-item">Logout</a>
      </div>
    </div>
    <div class="main-content">
      <div class="page-header"><h1>Calendar</h1></div>
      <p class="calendar-subtitle">Most entries here are auto-generated by logging a subtask. You can also add your own milestones below to mark progress manually.</p>

      <div class="calendar-widget">
        <div class="calendar-header">
          <button type="button" id="cal-prev" class="btn-cal-nav" aria-label="Previous month">‹</button>
          <h2 id="cal-month-label"></h2>
          <button type="button" id="cal-next" class="btn-cal-nav" aria-label="Next month">›</button>
        </div>
        <div class="calendar-grid" id="cal-grid"></div>
        <div class="calendar-day-detail" id="cal-day-detail">
          <p class="cal-detail-empty">Click a day to see what happened.</p>
        </div>
      </div>

      <div class="settings-section">
        <h2 class="section-heading">Add an Event</h2>
        <div class="auth-card">
          <form method="POST" action="calendar.php">
            <input type="hidden" name="action" value="add_event">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <div class="field">
              <input type="text" name="title" maxlength="100" placeholder="Event title" required>
            </div>
            <div class="field">
              <input type="date" name="event_date" required>
            </div>
            <div class="field">
              <textarea name="description" placeholder="Description (optional)"></textarea>
            </div>
            <button type="submit" class="btn-primary">Add Event</button>
          </form>
        </div>
      </div>

      <div class="settings-section">
        <h2 class="section-heading">Add a Reminder</h2>
        <div class="auth-card">
          <form method="POST" action="calendar.php">
            <input type="hidden" name="action" value="add_reminder">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <div class="field"><input type="text" name="reminder_label" maxlength="255" placeholder="Reminder label" required></div>
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
      </div>

      <?php if (empty($allEvents)): ?>
        <div class="empty-state calendar-empty-below"><p>No activity yet. Log a subtask on the Subtasks page and it'll show up here automatically.</p></div>
      <?php else: ?>
        <h2 class="section-heading">Full activity log</h2>
        <table id="calendar-table" class="data-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Habit</th>
              <th>Event</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($allEvents as $e): ?>
              <tr>
                <td><?php echo htmlspecialchars($e['event_date']); ?></td>
                <td><?php echo htmlspecialchars($e['habit_name'] ?: 'Manual milestone'); ?></td>
                <td>
                  <?php echo htmlspecialchars($e['label']); ?>
                  <?php if (!empty($e['description'])): ?>
                    <br><small><?php echo htmlspecialchars($e['description']); ?></small>
                  <?php endif; ?>
                  <?php if (in_array($e['event_type'], ['milestone', 'event'], true)): ?>
                    <span class="badge-milestone"><?php echo $e['event_type'] === 'milestone' ? 'Milestone' : 'Event'; ?></span>
                  <?php endif; ?>
                </td>
                <td class="actions-cell">
                  <?php if ($e['event_type'] === 'milestone'): ?>
                    <form method="POST" action="calendar.php">
                      <input type="hidden" name="action" value="delete_milestone">
                      <input type="hidden" name="event_id" value="<?php echo $e['event_id']; ?>">
                      <button type="button" class="btn-delete" data-confirm-message="Delete this milestone? This cannot be undone.">Delete</button>
                    </form>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <script>
    window.CALENDAR_EVENTS = <?php echo json_encode($eventsForJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  </script>
  <script src="Calendar.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" integrity="sha512-bLT0Qm9VnAYZDflyKcBaQ2gg0hSYNQrJ8RilYldYQ1FxQYoCLtUjuuRuZo+fjqhx/qtq/1itJ0C2ejDxltZVFg==" crossorigin="anonymous"></script>
  <script src="https://cdn.datatables.net/v/dt/dt-3.0.2/datatables.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="/assets/js/confirm-delete.js"></script>
  <script src="/assets/js/toast.js"></script>
  <script>
    $(function () {
      $('#calendar-table').DataTable({
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[0, 'desc']]
      });
    });
  </script>
</body>
</html>