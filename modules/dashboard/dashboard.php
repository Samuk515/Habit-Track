<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/auth.php';
requireLogin();
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/db.php';

$userId = (int) $_SESSION['user_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $ownerStmt = mysqli_prepare($conn, 'SELECT HABIT.habit_id, HABIT.habit_name FROM HABIT
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
              $clearEventStmt = mysqli_prepare($conn, 'DELETE FROM CALENDAR_EVENT WHERE ref_id = ?');
              mysqli_stmt_bind_param($clearEventStmt, 'i', $logId);
              mysqli_stmt_execute($clearEventStmt);
              mysqli_stmt_close($clearEventStmt);

                $deleteStmt = mysqli_prepare($conn, 'DELETE FROM HABIT_LOG WHERE log_id = ?');
                mysqli_stmt_bind_param($deleteStmt, 'i', $logId);
                mysqli_stmt_execute($deleteStmt);
                mysqli_stmt_close($deleteStmt);
            } else {
                $insertStmt = mysqli_prepare($conn, "INSERT INTO HABIT_LOG (habit_id, log_date, status) VALUES (?, CURDATE(), 'done')");
                mysqli_stmt_bind_param($insertStmt, 'i', $habitId);
                mysqli_stmt_execute($insertStmt);
              $logId = (int) mysqli_insert_id($conn);
                mysqli_stmt_close($insertStmt);

              $eventLabel = $habit['habit_name'];
              $eventStmt = mysqli_prepare($conn, "INSERT INTO CALENDAR_EVENT (user_id, habit_id, label, event_date, event_type, ref_id) VALUES (?, ?, ?, CURDATE(), 'habit_log', ?)");
              mysqli_stmt_bind_param($eventStmt, 'iisi', $userId, $habitId, $eventLabel, $logId);
              mysqli_stmt_execute($eventStmt);
              mysqli_stmt_close($eventStmt);
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
  COALESCE(STREAK.current_streak, 0) AS current_streak,
  COALESCE(STREAK.longest_streak, 0) AS longest_streak
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

$logStmt = mysqli_prepare($conn, 'SELECT HABIT_LOG.habit_id, HABIT_LOG.log_date
  FROM HABIT_LOG
  INNER JOIN HABIT ON HABIT_LOG.habit_id = HABIT.habit_id
  INNER JOIN CATEGORY ON HABIT.category_id = CATEGORY.category_id
  WHERE CATEGORY.user_id = ?
    AND HABIT_LOG.status = \'done\'
    AND HABIT_LOG.log_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
  ORDER BY HABIT_LOG.log_date ASC');
mysqli_stmt_bind_param($logStmt, 'i', $userId);
mysqli_stmt_execute($logStmt);
$logResult = mysqli_stmt_get_result($logStmt);
$recentLogs = mysqli_fetch_all($logResult, MYSQLI_ASSOC);
mysqli_stmt_close($logStmt);

$logsByHabit = [];
foreach ($recentLogs as $log) {
  $habitLogId = (int) $log['habit_id'];
  $logDate = $log['log_date'];
  $logsByHabit[$habitLogId][$logDate] = true;
}

$today = new DateTimeImmutable('today');
$habitInsights = [];
$monthlyDone = 0;
$weeklyDone = 0;
$overallCurrentStreak = 0;
$overallLongestStreak = 0;

foreach ($habits as $habitIndex => $habit) {
  $habitId = (int) $habit['habit_id'];
  $weeklyDates = [];
  $monthlyHabitDone = 0;

  for ($offset = 29; $offset >= 0; $offset--) {
    $date = $today->sub(new DateInterval('P' . $offset . 'D'))->format('Y-m-d');
    $isDone = !empty($logsByHabit[$habitId][$date]);
    if ($isDone) {
      $monthlyHabitDone++;
    }
    if ($offset < 7) {
      $weeklyDates[] = [
        'label' => $today->sub(new DateInterval('P' . $offset . 'D'))->format('D'),
        'done' => $isDone,
      ];
    }
  }

  $weeklyHabitDone = count(array_filter($weeklyDates, static fn (array $date): bool => $date['done']));
  $monthlyDone += $monthlyHabitDone;
  $weeklyDone += $weeklyHabitDone;
  $overallCurrentStreak = max($overallCurrentStreak, (int) $habit['current_streak']);
  $overallLongestStreak = max($overallLongestStreak, (int) $habit['longest_streak']);

  $habits[$habitIndex]['weekly_percentage'] = (int) round(($weeklyHabitDone / 7) * 100);
  $habits[$habitIndex]['monthly_percentage'] = (int) round(($monthlyHabitDone / 30) * 100);
  $habits[$habitIndex]['weekly_dates'] = $weeklyDates;
}

$habitCount = count($habits);
$weeklyExpected = $habitCount * 7;
$monthlyExpected = $habitCount * 30;
$weeklyPercentage = $weeklyExpected > 0 ? (int) round(($weeklyDone / $weeklyExpected) * 100) : 0;
$monthlyPercentage = $monthlyExpected > 0 ? (int) round(($monthlyDone / $monthlyExpected) * 100) : 0;
$missedDays = max(0, $monthlyExpected - $monthlyDone);
usort($habits, static function (array $first, array $second): int {
  return $second['monthly_percentage'] <=> $first['monthly_percentage'];
});
$mostConsistent = $habits[0] ?? null;
$leastConsistent = $habits ? $habits[count($habits) - 1] : null;
?>
<!DOCTYPE html>
<html>
<head>
  <title>Dashboard — Habit Track</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="dashboard.css?v=20260927-1">
</head>
<body>
  <div class="app-layout">
    <div class="sidebar">
      <?php require __DIR__ . '/../../includes/logo.php'; ?>
      <a href="dashboard.php" class="nav-item active">Dashboard</a>
      <a href="../habits/habits.php" class="nav-item">Habits</a>
      <a href="../categories/categories.php" class="nav-item">Categories</a>
      <a href="../reminders/reminders.php" class="nav-item">Reminders</a>
      <a href="../calendar/calendar.php" class="nav-item">Calendar</a>
      <a href="../settings/settings.php" class="nav-item">Settings</a>
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
                <input type="hidden" name="action" value="toggle_done">
                <input type="hidden" name="habit_id" value="<?php echo $h['habit_id']; ?>">
                <button type="submit" class="btn-primary<?php echo $isDone ? ' btn-done' : ''; ?>">
                  <?php echo $isDone ? 'Undo' : 'Mark done'; ?>
                </button>
              </form>
              <?php if ($h['habit_nature'] === 'bad'): ?>
                <a href="../bad-habit-progress/bad-habit-progress.php?habit_id=<?php echo $h['habit_id']; ?>" class="habit-bad-link">Log occurrence</a>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>

        <section class="insights-section" aria-labelledby="insights-heading">
          <div class="section-heading-row">
            <div>
              <p class="eyebrow">Your rhythm</p>
              <h2 id="insights-heading" class="section-heading">Habit Insights</h2>
            </div>
            <span class="insights-period">Last 30 days</span>
          </div>

          <div class="insight-summary-grid">
            <div class="insight-stat"><span>Weekly completion</span><strong><?php echo $weeklyPercentage; ?>%</strong><small><?php echo $weeklyDone; ?> of <?php echo $weeklyExpected; ?> check-ins</small></div>
            <div class="insight-stat"><span>Monthly completion</span><strong><?php echo $monthlyPercentage; ?>%</strong><small><?php echo $monthlyDone; ?> of <?php echo $monthlyExpected; ?> check-ins</small></div>
            <div class="insight-stat"><span>Current streak</span><strong><?php echo $overallCurrentStreak; ?></strong><small>best active streak</small></div>
            <div class="insight-stat"><span>Longest streak</span><strong><?php echo $overallLongestStreak; ?></strong><small>days completed</small></div>
            <div class="insight-stat"><span>Missed days</span><strong><?php echo $missedDays; ?></strong><small>in the last 30 days</small></div>
          </div>

          <div class="insights-main-grid">
            <div class="insight-panel consistency-panel">
              <div class="panel-heading"><div><h3>Consistency snapshot</h3><p>Based on the last 30 days.</p></div></div>
              <?php if ($mostConsistent): ?>
                <div class="consistency-row"><span>Most consistent</span><strong><?php echo htmlspecialchars($mostConsistent['habit_name']); ?></strong><b><?php echo $mostConsistent['monthly_percentage']; ?>%</b></div>
                <div class="consistency-row"><span>Needs attention</span><strong><?php echo htmlspecialchars($leastConsistent['habit_name']); ?></strong><b><?php echo $leastConsistent['monthly_percentage']; ?>%</b></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="insight-panel habit-progress-panel">
            <div class="panel-heading"><div><h3>Habit-by-habit progress</h3><p>Daily completion across the current week.</p></div></div>
            <div class="habit-progress-list">
              <?php foreach ($habits as $habit): ?>
                <div class="habit-progress-row">
                  <div class="habit-progress-meta"><strong><?php echo htmlspecialchars($habit['habit_name']); ?></strong><span><?php echo $habit['weekly_percentage']; ?>% this week · <?php echo (int) $habit['current_streak']; ?> day streak</span></div>
                  <div class="progress-bars">
                    <?php foreach ($habit['weekly_dates'] as $date): ?>
                      <div class="progress-day"><span class="progress-bar <?php echo $date['done'] ? 'progress-bar-done' : ''; ?>"></span><small><?php echo htmlspecialchars($date['label']); ?></small></div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </section>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>