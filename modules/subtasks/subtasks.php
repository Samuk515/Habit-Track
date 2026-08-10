<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/auth.php';
requireLogin();
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/db.php';

$userId = (int) $_SESSION['user_id'];
$errors = [];

$habitId = $_GET['habit_id'] ?? ($_POST['habit_id'] ?? '');
if (filter_var($habitId, FILTER_VALIDATE_INT) === false) {
    die('Invalid habit.');
}
$habitId = (int) $habitId;

$perPage = 10;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$editSubtaskId = filter_var($_GET['edit_subtask_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$editValues = null;
$logModeSubtaskId = filter_var($_GET['log_subtask_id'] ?? null, FILTER_VALIDATE_INT) ?: null;

// Ownership verified ONCE, here, at the top of the file — every
// query below trusts this $habitId without re-checking.
$ownerStmt = mysqli_prepare($conn, 'SELECT HABIT.habit_id, HABIT.habit_name FROM HABIT
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'update') {
        $subtaskName = trim($_POST['subtask_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isOptional = isset($_POST['is_optional']) ? 1 : 0;

        if ($subtaskName === '') {
            $errors[] = 'Subtask name is required.';
        }

        if ($description === '') {
            $description = null;
        }

        if ($action === 'add') {
            if (empty($errors)) {
                $orderStmt = mysqli_prepare($conn, 'SELECT COALESCE(MAX(order_no), 0) + 1 AS next_order FROM SUBTASK WHERE habit_id = ?');
                mysqli_stmt_bind_param($orderStmt, 'i', $habitId);
                mysqli_stmt_execute($orderStmt);
                $orderResult = mysqli_stmt_get_result($orderStmt);
                $orderRow = mysqli_fetch_assoc($orderResult);
                $nextOrder = (int) $orderRow['next_order'];
                mysqli_stmt_close($orderStmt);

                $insertStmt = mysqli_prepare($conn, 'INSERT INTO SUBTASK (habit_id, subtask_name, description, is_optional, order_no) VALUES (?, ?, ?, ?, ?)');
                mysqli_stmt_bind_param($insertStmt, 'issii', $habitId, $subtaskName, $description, $isOptional, $nextOrder);
                mysqli_stmt_execute($insertStmt);
                mysqli_stmt_close($insertStmt);

                header('Location: subtasks.php?habit_id=' . $habitId . '&page=' . $page);
                exit;
            }
        }

        if ($action === 'update') {
            $subtaskId = $_POST['subtask_id'] ?? '';
            if (filter_var($subtaskId, FILTER_VALIDATE_INT) === false) {
                $errors[] = 'Invalid subtask.';
            } else {
                $subtaskId = (int) $subtaskId;
            }

            if (empty($errors)) {
                $stmt = mysqli_prepare($conn, 'UPDATE SUBTASK SET subtask_name = ?, description = ?, is_optional = ? WHERE subtask_id = ? AND habit_id = ?');
                mysqli_stmt_bind_param($stmt, 'ssiii', $subtaskName, $description, $isOptional, $subtaskId, $habitId);
                mysqli_stmt_execute($stmt);
                $affected = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);

                if ($affected === 0) {
                    $errors[] = 'Subtask not found.';
                } else {
                    header('Location: subtasks.php?habit_id=' . $habitId . '&page=' . $page);
                    exit;
                }
            }

            if (!empty($errors)) {
                $editSubtaskId = $subtaskId ?? null;
                $editValues = [
                    'subtask_name' => $subtaskName,
                    'description' => $description,
                    'is_optional' => $isOptional,
                ];
            }
        }
    }

    if ($action === 'delete') {
        $subtaskId = $_POST['subtask_id'] ?? '';

        if (filter_var($subtaskId, FILTER_VALIDATE_INT) !== false) {
            $subtaskId = (int) $subtaskId;

            $deleteStmt = mysqli_prepare($conn, 'DELETE FROM SUBTASK WHERE subtask_id = ? AND habit_id = ?');
            mysqli_stmt_bind_param($deleteStmt, 'ii', $subtaskId, $habitId);
            mysqli_stmt_execute($deleteStmt);
            mysqli_stmt_close($deleteStmt);

            header('Location: subtasks.php?habit_id=' . $habitId . '&page=' . $page);
            exit;
        }
    }

    if ($action === 'log_completion') {
        $logSubtaskId = $_POST['subtask_id'] ?? '';

        if (filter_var($logSubtaskId, FILTER_VALIDATE_INT) === false) {
            $errors[] = 'Invalid subtask.';
        } else {
            $logSubtaskId = (int) $logSubtaskId;

            // Confirm this subtask actually belongs to the
            // verified-owned habit before logging against it.
            $subCheckStmt = mysqli_prepare($conn, 'SELECT subtask_id, subtask_name FROM SUBTASK WHERE subtask_id = ? AND habit_id = ?');
            mysqli_stmt_bind_param($subCheckStmt, 'ii', $logSubtaskId, $habitId);
            mysqli_stmt_execute($subCheckStmt);
            $subCheckResult = mysqli_stmt_get_result($subCheckStmt);
            $subCheckRow = mysqli_fetch_assoc($subCheckResult);
            mysqli_stmt_close($subCheckStmt);

            if (!$subCheckRow) {
                $errors[] = 'Subtask not found.';
            } else {
                $logValueRaw = trim($_POST['log_value'] ?? '');
                $logUnit = trim($_POST['log_unit'] ?? '');

                $logValue = null;
                if ($logValueRaw !== '') {
                    if (filter_var($logValueRaw, FILTER_VALIDATE_INT) === false) {
                        $errors[] = 'Value must be a whole number.';
                    } else {
                        $logValue = (int) $logValueRaw;
                    }
                }
                if ($logUnit === '') {
                    $logUnit = null;
                }

                if (empty($errors)) {
                    // UNIQUE(habit_id, log_date) on HABIT_LOG means at
                    // most one row per habit per day — so this is an
                    // upsert. Logging a different subtask today
                    // overwrites today's row rather than adding a new one.
                    $existingStmt = mysqli_prepare($conn, 'SELECT log_id FROM HABIT_LOG WHERE habit_id = ? AND log_date = CURDATE()');
                    mysqli_stmt_bind_param($existingStmt, 'i', $habitId);
                    mysqli_stmt_execute($existingStmt);
                    $existingResult = mysqli_stmt_get_result($existingStmt);
                    $existing = mysqli_fetch_assoc($existingResult);
                    mysqli_stmt_close($existingStmt);

                    if ($existing) {
                        $logId = (int) $existing['log_id'];
                        $updateStmt = mysqli_prepare($conn, "UPDATE HABIT_LOG SET subhabit_id = ?, value = ?, unit = ?, status = 'done' WHERE log_id = ?");
                        mysqli_stmt_bind_param($updateStmt, 'iisi', $logSubtaskId, $logValue, $logUnit, $logId);
                        mysqli_stmt_execute($updateStmt);
                        mysqli_stmt_close($updateStmt);
                    } else {
                        $insertStmt = mysqli_prepare($conn, "INSERT INTO HABIT_LOG (habit_id, subhabit_id, log_date, value, unit, status) VALUES (?, ?, CURDATE(), ?, ?, 'done')");
                        mysqli_stmt_bind_param($insertStmt, 'iiis', $habitId, $logSubtaskId, $logValue, $logUnit);
                        mysqli_stmt_execute($insertStmt);
                        mysqli_stmt_close($insertStmt);
                        $logId = (int) mysqli_insert_id($conn);
                    }

                    // Auto-populate the calendar from this subtask
                    // activity — this is the ONLY place CALENDAR_EVENT
                    // rows get created anywhere in the app, per the
                    // "no manual event creation" requirement. Clear any
                    // previous event tied to this exact log row first,
                    // so re-logging the same day updates the calendar
                    // entry instead of duplicating it.
                    $clearEventStmt = mysqli_prepare($conn, "DELETE FROM CALENDAR_EVENT WHERE ref_id = ? AND event_type = 'subtask_log'");
                    mysqli_stmt_bind_param($clearEventStmt, 'i', $logId);
                    mysqli_stmt_execute($clearEventStmt);
                    mysqli_stmt_close($clearEventStmt);

                    $eventLabel = $subCheckRow['subtask_name'];
                    if ($logValue !== null) {
                        $eventLabel .= ' (' . $logValue . ($logUnit ? ' ' . $logUnit : '') . ')';
                    }

                    $insertEventStmt = mysqli_prepare($conn, "INSERT INTO CALENDAR_EVENT (subtask_id, label, event_date, event_type, ref_id) VALUES (?, ?, CURDATE(), 'subtask_log', ?)");
                    mysqli_stmt_bind_param($insertEventStmt, 'isi', $logSubtaskId, $eventLabel, $logId);
                    mysqli_stmt_execute($insertEventStmt);
                    mysqli_stmt_close($insertEventStmt);

                    // Any write to HABIT_LOG affects the habit's streak,
                    // regardless of which page/action produced the row.
                    calculateAndSaveStreak($conn, $habitId);

                    header('Location: subtasks.php?habit_id=' . $habitId . '&page=' . $page);
                    exit;
                }
            }

            if (!empty($errors)) {
                $logModeSubtaskId = $logSubtaskId;
            }
        }
    }

    if ($action === 'clear_log') {
        $logSubtaskId = $_POST['subtask_id'] ?? '';

        if (filter_var($logSubtaskId, FILTER_VALIDATE_INT) !== false) {
            $logSubtaskId = (int) $logSubtaskId;

            // Find the log_id first, so its calendar event can be
            // cleaned up before the HABIT_LOG row itself is gone —
            // once deleted, there's nothing left to look ref_id up by.
            $findStmt = mysqli_prepare($conn, 'SELECT log_id FROM HABIT_LOG WHERE habit_id = ? AND log_date = CURDATE() AND subhabit_id = ?');
            mysqli_stmt_bind_param($findStmt, 'ii', $habitId, $logSubtaskId);
            mysqli_stmt_execute($findStmt);
            $findResult = mysqli_stmt_get_result($findStmt);
            $foundLog = mysqli_fetch_assoc($findResult);
            mysqli_stmt_close($findStmt);

            if ($foundLog) {
                $logIdToClear = (int) $foundLog['log_id'];
                $clearEventStmt = mysqli_prepare($conn, "DELETE FROM CALENDAR_EVENT WHERE ref_id = ? AND event_type = 'subtask_log'");
                mysqli_stmt_bind_param($clearEventStmt, 'i', $logIdToClear);
                mysqli_stmt_execute($clearEventStmt);
                mysqli_stmt_close($clearEventStmt);
            }

            // Only clears if today's log actually belongs to THIS
            // subtask — prevents wiping a different subtask's log via
            // a stale or tampered request.
            $stmt = mysqli_prepare($conn, 'DELETE FROM HABIT_LOG WHERE habit_id = ? AND log_date = CURDATE() AND subhabit_id = ?');
            mysqli_stmt_bind_param($stmt, 'ii', $habitId, $logSubtaskId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            calculateAndSaveStreak($conn, $habitId);

            header('Location: subtasks.php?habit_id=' . $habitId . '&page=' . $page);
            exit;
        }
    }

    if ($action === 'move_up' || $action === 'move_down') {
        $subtaskId = $_POST['subtask_id'] ?? '';

        if (filter_var($subtaskId, FILTER_VALIDATE_INT) !== false) {
            $subtaskId = (int) $subtaskId;

            $curStmt = mysqli_prepare($conn, 'SELECT order_no FROM SUBTASK WHERE subtask_id = ? AND habit_id = ?');
            mysqli_stmt_bind_param($curStmt, 'ii', $subtaskId, $habitId);
            mysqli_stmt_execute($curStmt);
            $curResult = mysqli_stmt_get_result($curStmt);
            $current = mysqli_fetch_assoc($curResult);
            mysqli_stmt_close($curStmt);

            if ($current) {
                $currentOrder = (int) $current['order_no'];
                $comparator = $action === 'move_up' ? '<' : '>';
                $direction = $action === 'move_up' ? 'DESC' : 'ASC';

                $neighborStmt = mysqli_prepare($conn, "SELECT subtask_id, order_no FROM SUBTASK WHERE habit_id = ? AND order_no $comparator ? ORDER BY order_no $direction LIMIT 1");
                mysqli_stmt_bind_param($neighborStmt, 'ii', $habitId, $currentOrder);
                mysqli_stmt_execute($neighborStmt);
                $neighborResult = mysqli_stmt_get_result($neighborStmt);
                $neighbor = mysqli_fetch_assoc($neighborResult);
                mysqli_stmt_close($neighborStmt);

                if ($neighbor) {
                    $neighborId = (int) $neighbor['subtask_id'];
                    $neighborOrder = (int) $neighbor['order_no'];

                    // Three-step swap via a temporary sentinel value —
                    // avoids a transient duplicate order_no mid-swap if
                    // (habit_id, order_no) is ever made UNIQUE later.
                    $tempStmt = mysqli_prepare($conn, 'UPDATE SUBTASK SET order_no = -1 WHERE subtask_id = ? AND habit_id = ?');
                    mysqli_stmt_bind_param($tempStmt, 'ii', $subtaskId, $habitId);
                    mysqli_stmt_execute($tempStmt);
                    mysqli_stmt_close($tempStmt);

                    $stmt1 = mysqli_prepare($conn, 'UPDATE SUBTASK SET order_no = ? WHERE subtask_id = ? AND habit_id = ?');
                    mysqli_stmt_bind_param($stmt1, 'iii', $currentOrder, $neighborId, $habitId);
                    mysqli_stmt_execute($stmt1);
                    mysqli_stmt_close($stmt1);

                    $stmt2 = mysqli_prepare($conn, 'UPDATE SUBTASK SET order_no = ? WHERE subtask_id = ? AND habit_id = ?');
                    mysqli_stmt_bind_param($stmt2, 'iii', $neighborOrder, $subtaskId, $habitId);
                    mysqli_stmt_execute($stmt2);
                    mysqli_stmt_close($stmt2);
                }
            }

            header('Location: subtasks.php?habit_id=' . $habitId . '&page=' . $page);
            exit;
        }
    }
}

// Today's single log row for this habit (if any) — UNIQUE(habit_id,
// log_date) guarantees there's at most one, so a plain fetch is safe.
// LEFT JOIN pulls the logged subtask's name too, for the "logged a
// different subtask today" note.
$todayLogStmt = mysqli_prepare($conn, 'SELECT HABIT_LOG.subhabit_id, HABIT_LOG.value, HABIT_LOG.unit, SUBTASK.subtask_name AS logged_subtask_name
    FROM HABIT_LOG
    LEFT JOIN SUBTASK ON HABIT_LOG.subhabit_id = SUBTASK.subtask_id
    WHERE HABIT_LOG.habit_id = ? AND HABIT_LOG.log_date = CURDATE()');
mysqli_stmt_bind_param($todayLogStmt, 'i', $habitId);
mysqli_stmt_execute($todayLogStmt);
$todayLogResult = mysqli_stmt_get_result($todayLogStmt);
$todayLog = mysqli_fetch_assoc($todayLogResult);
mysqli_stmt_close($todayLogStmt);

$countStmt = mysqli_prepare($conn, 'SELECT COUNT(*) FROM SUBTASK WHERE habit_id = ?');
mysqli_stmt_bind_param($countStmt, 'i', $habitId);
mysqli_stmt_execute($countStmt);
mysqli_stmt_bind_result($countStmt, $totalSubtasks);
mysqli_stmt_fetch($countStmt);
mysqli_stmt_close($countStmt);
$totalPages = (int) ceil($totalSubtasks / $perPage);

if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$subtaskStmt = mysqli_prepare($conn, 'SELECT * FROM SUBTASK WHERE habit_id = ? ORDER BY order_no ASC LIMIT ? OFFSET ?');
mysqli_stmt_bind_param($subtaskStmt, 'iii', $habitId, $perPage, $offset);
mysqli_stmt_execute($subtaskStmt);
$subtaskResult = mysqli_stmt_get_result($subtaskStmt);
$subtasks = mysqli_fetch_all($subtaskResult, MYSQLI_ASSOC);
mysqli_stmt_close($subtaskStmt);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Subtasks — Habit Track</title>
  <link rel="stylesheet" href="subtasks.css?v=20260801-5">
</head>
<body>
  <div class="app-layout">
    <div class="sidebar">
      <?php require __DIR__ . '/../../includes/logo.php'; ?>
      <a href="../dashboard/dashboard.php" class="nav-item">Dashboard</a>
      <a href="../habits/habits.php" class="nav-item active">Habits</a>
      <a href="../categories/categories.php" class="nav-item">Categories</a>
      <a href="../reminders/reminders.php" class="nav-item">Reminders</a>
      <a href="../calendar/calendar.php" class="nav-item">Calendar</a>
      <div class="sidebar-footer">
        <a href="../auth/logout.php" class="nav-item">Logout</a>
      </div>
    </div>
    <div class="main-content">
      <a href="../habits/habits.php" class="back-link">← Back to Habits</a>
      <div class="page-header">
        <h1><?php echo htmlspecialchars($habit['habit_name']); ?> — Subtasks</h1>
      </div>

      <?php foreach ($errors as $err): ?>
        <div class="error-box"><?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>

      <div class="auth-card subtask-form-card">
        <form method="POST" action="subtasks.php?habit_id=<?php echo $habitId; ?>&page=<?php echo $page; ?>">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="habit_id" value="<?php echo $habitId; ?>">

          <div class="field"><input type="text" name="subtask_name" placeholder="Subtask name" required></div>
          <div class="field"><input type="text" name="description" placeholder="Description (optional)"></div>
          <div class="field field-checkbox">
            <input type="checkbox" name="is_optional" id="is_optional" class="checkbox-input">
            <label for="is_optional" class="checkbox-label">This subtask is optional</label>
          </div>

          <button type="submit" class="btn-primary">Add Subtask</button>
        </form>
      </div>

      <?php if (empty($subtasks)): ?>
        <div class="empty-state"><p>No subtasks yet.</p></div>
      <?php else: ?>
        <table class="data-table">
          <thead>
            <tr>
              <th>Subtask</th>
              <th>Description</th>
              <th>Optional</th>
              <th>Today</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($subtasks as $s): ?>
              <?php
                $isEditing = ($editSubtaskId !== null && (int) $s['subtask_id'] === (int) $editSubtaskId);
                $isLoggingRow = (!$isEditing && $logModeSubtaskId !== null && (int) $s['subtask_id'] === (int) $logModeSubtaskId);
                $isLoggedToday = ($todayLog && (int) $todayLog['subhabit_id'] === (int) $s['subtask_id']);
              ?>

              <?php if ($isEditing): ?>
                <?php $ev = $editValues ?? $s; ?>
                <tr class="edit-row" id="subtask-<?php echo $s['subtask_id']; ?>">
                  <td colspan="5">
                    <form method="POST" action="subtasks.php?habit_id=<?php echo $habitId; ?>&page=<?php echo $page; ?>">
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="habit_id" value="<?php echo $habitId; ?>">
                      <input type="hidden" name="subtask_id" value="<?php echo $s['subtask_id']; ?>">

                      <div class="field"><input type="text" name="subtask_name" value="<?php echo htmlspecialchars((string) $ev['subtask_name']); ?>" required></div>
                      <div class="field"><input type="text" name="description" value="<?php echo htmlspecialchars((string) ($ev['description'] ?? '')); ?>" placeholder="Description (optional)"></div>
                      <div class="field field-checkbox">
                        <input type="checkbox" name="is_optional" id="edit-is_optional-<?php echo $s['subtask_id']; ?>" class="checkbox-input" <?php echo $ev['is_optional'] ? 'checked' : ''; ?>>
                        <label for="edit-is_optional-<?php echo $s['subtask_id']; ?>" class="checkbox-label">This subtask is optional</label>
                      </div>

                      <div class="edit-form-actions">
                        <button type="submit" class="btn-primary">Save changes</button>
                        <a href="subtasks.php?habit_id=<?php echo $habitId; ?>&page=<?php echo $page; ?>" class="btn-cancel">Cancel</a>
                      </div>
                    </form>
                  </td>
                </tr>

              <?php elseif ($isLoggingRow): ?>
                <tr class="edit-row" id="subtask-<?php echo $s['subtask_id']; ?>">
                  <td colspan="5">
                    <form method="POST" action="subtasks.php?habit_id=<?php echo $habitId; ?>&page=<?php echo $page; ?>">
                      <input type="hidden" name="action" value="log_completion">
                      <input type="hidden" name="habit_id" value="<?php echo $habitId; ?>">
                      <input type="hidden" name="subtask_id" value="<?php echo $s['subtask_id']; ?>">

                      <div class="field"><input type="number" name="log_value" placeholder="Value (optional)" value="<?php echo $isLoggedToday && $todayLog['value'] !== null ? (int) $todayLog['value'] : ''; ?>"></div>
                      <div class="field"><input type="text" name="log_unit" placeholder="Unit (e.g. kg, minutes, reps)" value="<?php echo $isLoggedToday ? htmlspecialchars((string) ($todayLog['unit'] ?? '')) : ''; ?>"></div>

                      <div class="edit-form-actions">
                        <button type="submit" class="btn-primary">Save log</button>
                        <a href="subtasks.php?habit_id=<?php echo $habitId; ?>&page=<?php echo $page; ?>" class="btn-cancel">Cancel</a>
                      </div>
                    </form>
                    <?php if ($isLoggedToday): ?>
                    <form method="POST" action="subtasks.php?habit_id=<?php echo $habitId; ?>&page=<?php echo $page; ?>" class="clear-log-form">
                      <input type="hidden" name="action" value="clear_log">
                      <input type="hidden" name="habit_id" value="<?php echo $habitId; ?>">
                      <input type="hidden" name="subtask_id" value="<?php echo $s['subtask_id']; ?>">
                      <button type="submit" class="btn-delete">Clear today's log</button>
                    </form>
                    <?php endif; ?>
                  </td>
                </tr>

              <?php else: ?>
                <tr id="subtask-<?php echo $s['subtask_id']; ?>">
                  <td><?php echo htmlspecialchars($s['subtask_name']); ?></td>
                  <td><?php echo $s['description'] ? htmlspecialchars($s['description']) : '—'; ?></td>
                  <td><?php echo $s['is_optional'] ? '<span class="badge-optional">Optional</span>' : '—'; ?></td>
                  <td>
                    <?php if ($isLoggedToday): ?>
                      <span class="badge-logged">✓<?php echo $todayLog['value'] !== null ? ' ' . htmlspecialchars((string) (int) $todayLog['value']) : ''; ?><?php echo $todayLog['unit'] ? ' ' . htmlspecialchars($todayLog['unit']) : ''; ?></span>
                    <?php elseif ($todayLog): ?>
                      <span class="today-other-note">Today: <?php echo htmlspecialchars((string) $todayLog['logged_subtask_name']); ?></span>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </td>
                  <td class="actions-cell">
                    <a href="subtasks.php?habit_id=<?php echo $habitId; ?>&page=<?php echo $page; ?>&log_subtask_id=<?php echo $s['subtask_id']; ?>#subtask-<?php echo $s['subtask_id']; ?>" class="btn-log">Log</a>
                    <a href="subtasks.php?habit_id=<?php echo $habitId; ?>&page=<?php echo $page; ?>&edit_subtask_id=<?php echo $s['subtask_id']; ?>#subtask-<?php echo $s['subtask_id']; ?>" class="btn-edit">Edit</a>
                    <form method="POST" action="subtasks.php?habit_id=<?php echo $habitId; ?>&page=<?php echo $page; ?>" class="reorder-form">
                      <input type="hidden" name="action" value="move_up">
                      <input type="hidden" name="habit_id" value="<?php echo $habitId; ?>">
                      <input type="hidden" name="subtask_id" value="<?php echo $s['subtask_id']; ?>">
                      <button type="submit" class="btn-reorder" aria-label="Move up">▲</button>
                    </form>
                    <form method="POST" action="subtasks.php?habit_id=<?php echo $habitId; ?>&page=<?php echo $page; ?>" class="reorder-form">
                      <input type="hidden" name="action" value="move_down">
                      <input type="hidden" name="habit_id" value="<?php echo $habitId; ?>">
                      <input type="hidden" name="subtask_id" value="<?php echo $s['subtask_id']; ?>">
                      <button type="submit" class="btn-reorder" aria-label="Move down">▼</button>
                    </form>
                    <form method="POST" action="subtasks.php?habit_id=<?php echo $habitId; ?>&page=<?php echo $page; ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="habit_id" value="<?php echo $habitId; ?>">
                      <input type="hidden" name="subtask_id" value="<?php echo $s['subtask_id']; ?>">
                      <button type="submit" class="btn-delete">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endif; ?>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
          <div class="list-pagination">
            <?php if ($page > 1): ?>
              <a class="pagination-link" href="subtasks.php?habit_id=<?php echo $habitId; ?>&page=<?php echo $page - 1; ?>">Previous</a>
            <?php endif; ?>

            <span class="pagination-status">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>

            <?php if ($page < $totalPages): ?>
              <a class="pagination-link" href="subtasks.php?habit_id=<?php echo $habitId; ?>&page=<?php echo $page + 1; ?>">Next</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>