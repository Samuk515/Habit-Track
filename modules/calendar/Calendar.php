<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/auth.php';
requireLogin();
require __DIR__ . '/../../includes/db.php';

$userId = (int) $_SESSION['user_id'];
$perPage = 15;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

// Fetched once, used two ways: sliced in PHP for the paginated table
// below, and passed whole to JS as the data source for the month
// grid — a capped LIMIT avoids an unbounded query as activity grows.
$eventStmt = mysqli_prepare($conn, 'SELECT CALENDAR_EVENT.*, SUBTASK.subtask_name, HABIT.habit_name
    FROM CALENDAR_EVENT
    INNER JOIN SUBTASK ON CALENDAR_EVENT.subtask_id = SUBTASK.subtask_id
    INNER JOIN HABIT ON SUBTASK.habit_id = HABIT.habit_id
    INNER JOIN CATEGORY ON HABIT.category_id = CATEGORY.category_id
    WHERE CATEGORY.user_id = ?
    ORDER BY CALENDAR_EVENT.event_date DESC, CALENDAR_EVENT.event_id DESC
    LIMIT 2000');
mysqli_stmt_bind_param($eventStmt, 'i', $userId);
mysqli_stmt_execute($eventStmt);
$eventResult = mysqli_stmt_get_result($eventStmt);
$allEvents = mysqli_fetch_all($eventResult, MYSQLI_ASSOC);
mysqli_stmt_close($eventStmt);

$totalEvents = count($allEvents);
$totalPages = max(1, (int) ceil($totalEvents / $perPage));
$page = min($page, $totalPages);
$pageEvents = array_slice($allEvents, ($page - 1) * $perPage, $perPage);

// Data for the JS month-grid — every event, not just this page's
// slice, so switching months client-side needs no reload.
$eventsForJs = array_map(function ($e) {
    return [
        'date' => $e['event_date'],
        'label' => $e['label'],
        'habit' => $e['habit_name'],
    ];
}, $allEvents);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Calendar — Habit Track</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="calendar.css?v=20260801-2">
</head>
<body>
  <div class="app-layout">
    <div class="sidebar">
      <?php require __DIR__ . '/../../includes/logo.php'; ?>
      <a href="../dashboard/dashboard.php" class="nav-item">Dashboard</a>
      <a href="../habits/habits.php" class="nav-item">Habits</a>
      <a href="../categories/categories.php" class="nav-item">Categories</a>
      <a href="../reminders/reminders.php" class="nav-item">Reminders</a>
      <a href="calendar.php" class="nav-item active">Calendar</a>
      <div class="sidebar-footer">
        <a href="../auth/logout.php" class="nav-item">Logout</a>
      </div>
    </div>
    <div class="main-content">
      <div class="page-header"><h1>Calendar</h1></div>
    

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

      <?php if (empty($allEvents)): ?>
        <div class="empty-state calendar-empty-below"><p>No activity yet. Log a subtask on the Subtasks page and it'll show up here automatically.</p></div>
      <?php else: ?>
        <h2 class="section-heading">Full activity log</h2>
        <table class="data-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Habit</th>
              <th>Event</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pageEvents as $e): ?>
              <tr>
                <td><?php echo htmlspecialchars($e['event_date']); ?></td>
                <td><?php echo htmlspecialchars($e['habit_name']); ?></td>
                <td><?php echo htmlspecialchars($e['label']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
          <div class="list-pagination">
            <?php if ($page > 1): ?>
              <a class="pagination-link" href="calendar.php?page=<?php echo $page - 1; ?>">Previous</a>
            <?php endif; ?>
            <span class="pagination-status">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
            <?php if ($page < $totalPages): ?>
              <a class="pagination-link" href="calendar.php?page=<?php echo $page + 1; ?>">Next</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <script>
    window.CALENDAR_EVENTS = <?php echo json_encode($eventsForJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  </script>
  <script src="calendar.js"></script>
</body>
</html>