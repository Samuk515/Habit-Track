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

$failedEditValues = null;
$failedLogValues = null;

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

                header('Location: subtasks.php?habit_id=' . $habitId . '&success=add');
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
                    header('Location: subtasks.php?habit_id=' . $habitId . '&success=update');
                    exit;
                }
            }

            if (!empty($errors)) {
                $failedEditValues = [
                    'id' => $subtaskId ?? null,
                    'name' => $subtaskName,
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

            header('Location: subtasks.php?habit_id=' . $habitId . '&success=delete');
            exit;
        }
    }

    if ($action === 'log_completion') {
        $logSubtaskId = $_POST['subtask_id'] ?? '';

        if (filter_var($logSubtaskId, FILTER_VALIDATE_INT) === false) {
            $errors[] = 'Invalid subtask.';
        } else {
            $logSubtaskId = (int) $logSubtaskId;

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

                    calculateAndSaveStreak($conn, $habitId);

                    header('Location: subtasks.php?habit_id=' . $habitId . '&success=log');
                    exit;
                }
            }

            if (!empty($errors)) {
                $failedLogValues = [
                    'id' => $logSubtaskId,
                    'value' => $_POST['log_value'] ?? '',
                    'unit' => $_POST['log_unit'] ?? '',
                ];
            }
        }
    }

    if ($action === 'clear_log') {
        $logSubtaskId = $_POST['subtask_id'] ?? '';

        if (filter_var($logSubtaskId, FILTER_VALIDATE_INT) !== false) {
            $logSubtaskId = (int) $logSubtaskId;

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

            $stmt = mysqli_prepare($conn, 'DELETE FROM HABIT_LOG WHERE habit_id = ? AND log_date = CURDATE() AND subhabit_id = ?');
            mysqli_stmt_bind_param($stmt, 'ii', $habitId, $logSubtaskId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            calculateAndSaveStreak($conn, $habitId);

            header('Location: subtasks.php?habit_id=' . $habitId . '&success=clear_log');
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

            header('Location: subtasks.php?habit_id=' . $habitId);
            exit;
        }
    }
}

$todayLogStmt = mysqli_prepare($conn, 'SELECT HABIT_LOG.subhabit_id, HABIT_LOG.value, HABIT_LOG.unit, SUBTASK.subtask_name AS logged_subtask_name
    FROM HABIT_LOG
    LEFT JOIN SUBTASK ON HABIT_LOG.subhabit_id = SUBTASK.subtask_id
    WHERE HABIT_LOG.habit_id = ? AND HABIT_LOG.log_date = CURDATE()');
mysqli_stmt_bind_param($todayLogStmt, 'i', $habitId);
mysqli_stmt_execute($todayLogStmt);
$todayLogResult = mysqli_stmt_get_result($todayLogStmt);
$todayLog = mysqli_fetch_assoc($todayLogResult);
mysqli_stmt_close($todayLogStmt);

// DataTables handles pagination client-side now — fetch everything.
$subtaskStmt = mysqli_prepare($conn, 'SELECT * FROM SUBTASK WHERE habit_id = ? ORDER BY order_no ASC');
mysqli_stmt_bind_param($subtaskStmt, 'i', $habitId);
mysqli_stmt_execute($subtaskStmt);
$subtaskResult = mysqli_stmt_get_result($subtaskStmt);
$subtasks = mysqli_fetch_all($subtaskResult, MYSQLI_ASSOC);
mysqli_stmt_close($subtaskStmt);

$subtasksForJs = array_map(function ($s) use ($todayLog) {
    $isLoggedToday = $todayLog && (int) $todayLog['subhabit_id'] === (int) $s['subtask_id'];
    return [
        'id' => (int) $s['subtask_id'],
        'name' => $s['subtask_name'],
        'description' => $s['description'],
        'is_optional' => (int) $s['is_optional'],
        'logged_today' => $isLoggedToday,
        'today_value' => $isLoggedToday && $todayLog['value'] !== null ? (int) $todayLog['value'] : null,
        'today_unit' => $isLoggedToday ? $todayLog['unit'] : null,
    ];
}, $subtasks);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Subtasks — Habit Track</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
  <link href="https://cdn.datatables.net/v/dt/dt-3.0.2/datatables.min.css" rel="stylesheet">
  <link rel="stylesheet" href="subtasks.css?v=20260801-6">
</head>
<body>
  <script>window.SERVER_ERRORS = <?php echo json_encode($errors, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
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
        <form method="POST" action="subtasks.php?habit_id=<?php echo $habitId; ?>">
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
        <table id="subtasks-table" class="data-table">
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
              <?php $isLoggedToday = ($todayLog && (int) $todayLog['subhabit_id'] === (int) $s['subtask_id']); ?>
              <tr>
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
                  <button type="button" class="btn-log" onclick="openLogSubtaskDialog(<?php echo (int) $s['subtask_id']; ?>)">Log</button>
                  <button type="button" class="btn-edit" onclick="openEditSubtaskDialog(<?php echo (int) $s['subtask_id']; ?>)">Edit</button>
                  <form method="POST" action="subtasks.php?habit_id=<?php echo $habitId; ?>" class="reorder-form">
                    <input type="hidden" name="action" value="move_up">
                    <input type="hidden" name="habit_id" value="<?php echo $habitId; ?>">
                    <input type="hidden" name="subtask_id" value="<?php echo $s['subtask_id']; ?>">
                    <button type="submit" class="btn-reorder" aria-label="Move up">▲</button>
                  </form>
                  <form method="POST" action="subtasks.php?habit_id=<?php echo $habitId; ?>" class="reorder-form">
                    <input type="hidden" name="action" value="move_down">
                    <input type="hidden" name="habit_id" value="<?php echo $habitId; ?>">
                    <input type="hidden" name="subtask_id" value="<?php echo $s['subtask_id']; ?>">
                    <button type="submit" class="btn-reorder" aria-label="Move down">▼</button>
                  </form>
                  <form method="POST" action="subtasks.php?habit_id=<?php echo $habitId; ?>" style="display:inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="habit_id" value="<?php echo $habitId; ?>">
                    <input type="hidden" name="subtask_id" value="<?php echo $s['subtask_id']; ?>">
                    <button type="button" class="btn-delete" data-confirm-message="<?php echo htmlspecialchars('Delete "' . $s['subtask_name'] . '"? Any reminders tied to it will also be removed. This cannot be undone.'); ?>">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <!-- Edit dialog -->
      <div id="edit-subtask-dialog" title="Edit Subtask" style="display:none;">
        <form method="POST" action="subtasks.php?habit_id=<?php echo $habitId; ?>" id="edit-subtask-form">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="habit_id" value="<?php echo $habitId; ?>">
          <input type="hidden" name="subtask_id" id="edit-subtask-id">

          <div class="field"><input type="text" name="subtask_name" id="edit-subtask-name" required></div>
          <div class="field"><input type="text" name="description" id="edit-subtask-description" placeholder="Description (optional)"></div>
          <div class="field field-checkbox">
            <input type="checkbox" name="is_optional" id="edit-subtask-optional" class="checkbox-input">
            <label for="edit-subtask-optional" class="checkbox-label">This subtask is optional</label>
          </div>

          <button type="submit" class="btn-primary">Save changes</button>
        </form>
      </div>

      <!-- Log dialog -->
      <div id="log-subtask-dialog" title="Log Today" style="display:none;">
        <form method="POST" action="subtasks.php?habit_id=<?php echo $habitId; ?>" id="log-subtask-form">
          <input type="hidden" name="action" value="log_completion">
          <input type="hidden" name="habit_id" value="<?php echo $habitId; ?>">
          <input type="hidden" name="subtask_id" id="log-subtask-id">

          <div class="field"><input type="number" name="log_value" id="log-subtask-value" placeholder="Value (optional)"></div>
          <div class="field"><input type="text" name="log_unit" id="log-subtask-unit" placeholder="Unit (e.g. kg, minutes, reps)"></div>

          <button type="submit" class="btn-primary">Save log</button>
        </form>
        <form method="POST" action="subtasks.php?habit_id=<?php echo $habitId; ?>" id="clear-log-form" style="display:none;">
          <input type="hidden" name="action" value="clear_log">
          <input type="hidden" name="habit_id" value="<?php echo $habitId; ?>">
          <input type="hidden" name="subtask_id" id="clear-log-subtask-id">
          <button type="submit" class="btn-delete">Clear today's log</button>
        </form>
      </div>

    </div>
  </div>

  <script>
    window.SUBTASKS_DATA = <?php echo json_encode($subtasksForJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.FAILED_EDIT = <?php echo $failedEditValues ? json_encode($failedEditValues, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : 'null'; ?>;
    window.FAILED_LOG = <?php echo $failedLogValues ? json_encode($failedLogValues, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : 'null'; ?>;
  </script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" integrity="sha512-bLT0Qm9VnAYZDflyKcBaQ2gg0hSYNQrJ8RilYldYQ1FxQYoCLtUjuuRuZo+fjqhx/qtq/1itJ0C2ejDxltZVFg==" crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
  <script src="https://cdn.datatables.net/v/dt/dt-3.0.2/datatables.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="/assets/js/toast.js"></script>
  <script src="/assets/js/confirm-delete.js"></script>
  <script src="subtasks-datatable.js"></script>
</body>
</html>