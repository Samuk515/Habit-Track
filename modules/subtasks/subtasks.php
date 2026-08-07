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
}

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
  <link rel="stylesheet" href="subtasks.css?v=20260801-4">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <div class="app-layout">
    <div class="sidebar">
      <?php require __DIR__ . '/../../includes/logo.php'; ?>
      <a href="../dashboard/dashboard.php" class="nav-item">Dashboard</a>
      <a href="../habits/habits.php" class="nav-item active">Habits</a>
      <a href="../categories/categories.php" class="nav-item">Categories</a>
      <a href="../reminders/reminders.php" class="nav-item">Reminders</a>
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
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($subtasks as $s): ?>
              <?php $isEditing = ($editSubtaskId !== null && (int) $s['subtask_id'] === (int) $editSubtaskId); ?>

              <?php if ($isEditing): ?>
                <?php $ev = $editValues ?? $s; ?>
                <tr class="edit-row" id="subtask-<?php echo $s['subtask_id']; ?>">
                  <td colspan="4">
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

              <?php else: ?>
                <tr id="subtask-<?php echo $s['subtask_id']; ?>">
                  <td><?php echo htmlspecialchars($s['subtask_name']); ?></td>
                  <td><?php echo $s['description'] ? htmlspecialchars($s['description']) : '—'; ?></td>
                  <td><?php echo $s['is_optional'] ? '<span class="badge-optional">Optional</span>' : '—'; ?></td>
                  <td class="actions-cell">
                    <a href="subtasks.php?habit_id=<?php echo $habitId; ?>&page=<?php echo $page; ?>&edit_subtask_id=<?php echo $s['subtask_id']; ?>#subtask-<?php echo $s['subtask_id']; ?>" class="btn-edit">Edit</a>
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