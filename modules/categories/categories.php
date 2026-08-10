<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/auth.php';
requireLogin();
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/db.php';

$errors = [];
$userId = (int) $_SESSION['user_id'];
$perPage = 10;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$editCategoryId = filter_var($_GET['edit_category_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$editValues = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'update') {
        $name = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($description === '') {
            $description = null;
        }

        if ($name === '') {
            $errors[] = 'Category name is required.';
        }

        if ($action === 'add') {
            if (empty($errors)) {
                $stmt = mysqli_prepare($conn, 'INSERT INTO CATEGORY (user_id, category_name, description) VALUES (?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'iss', $userId, $name, $description);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                header('Location: categories.php?page=' . $page);
                exit;
            }
        }

        if ($action === 'update') {
            $categoryId = $_POST['category_id'] ?? '';
            if (filter_var($categoryId, FILTER_VALIDATE_INT) === false) {
                $errors[] = 'Invalid category.';
            } else {
                $categoryId = (int) $categoryId;
            }

            if (empty($errors)) {
                $stmt = mysqli_prepare($conn, 'UPDATE CATEGORY SET category_name = ?, description = ? WHERE category_id = ? AND user_id = ?');
                mysqli_stmt_bind_param($stmt, 'ssii', $name, $description, $categoryId, $userId);
                mysqli_stmt_execute($stmt);
                $affected = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);

                if ($affected === 0) {
                    $errors[] = 'Category not found.';
                } else {
                    header('Location: categories.php?page=' . $page);
                    exit;
                }
            }

            if (!empty($errors)) {
                $editCategoryId = $categoryId ?? null;
                $editValues = ['category_name' => $name, 'description' => $description];
            }
        }
    }

    if ($action === 'delete') {
        $categoryId = $_POST['category_id'] ?? '';

        if (filter_var($categoryId, FILTER_VALIDATE_INT) !== false) {
            $categoryId = (int) $categoryId;

            // Ownership verified ONCE, here — every cascading delete
            // below trusts this category_id without re-checking, since
            // they're all scoped to a category already confirmed to
            // belong to this user.
            $ownStmt = mysqli_prepare($conn, 'SELECT category_id FROM CATEGORY WHERE category_id = ? AND user_id = ?');
            mysqli_stmt_bind_param($ownStmt, 'ii', $categoryId, $userId);
            mysqli_stmt_execute($ownStmt);
            $ownResult = mysqli_stmt_get_result($ownStmt);
            $owns = mysqli_fetch_assoc($ownResult);
            mysqli_stmt_close($ownStmt);

            if ($owns) {
                // Deleting a category cascades through everything
                // beneath it: habits, their subtasks, logs, streaks,
                // bad-habit progress, and reminders — 6 tables. This
                // runs as one transaction: either the whole cascade
                // commits, or none of it does. Without this, a
                // mid-cascade failure would leave orphaned rows (e.g.
                // a SUBTASK pointing at a habit_id that no longer exists).
                mysqli_begin_transaction($conn);
                $cascadeOk = true;

                $steps = [
                    'DELETE FROM Bad_Habit_Progress WHERE log_id IN (
                        SELECT log_id FROM HABIT_LOG WHERE habit_id IN (
                            SELECT habit_id FROM HABIT WHERE category_id = ?
                        )
                    )',
                    'DELETE FROM CALENDAR_EVENT WHERE subtask_id IN (
                        SELECT subtask_id FROM SUBTASK WHERE habit_id IN (
                            SELECT habit_id FROM HABIT WHERE category_id = ?
                        )
                    )',
                    'DELETE FROM REMINDER WHERE subtask_id IN (
                        SELECT subtask_id FROM SUBTASK WHERE habit_id IN (
                            SELECT habit_id FROM HABIT WHERE category_id = ?
                        )
                    )',
                    'DELETE FROM SUBTASK WHERE habit_id IN (
                        SELECT habit_id FROM HABIT WHERE category_id = ?
                    )',
                    'DELETE FROM HABIT_LOG WHERE habit_id IN (
                        SELECT habit_id FROM HABIT WHERE category_id = ?
                    )',
                    'DELETE FROM STREAK WHERE habit_id IN (
                        SELECT habit_id FROM HABIT WHERE category_id = ?
                    )',
                    'DELETE FROM HABIT WHERE category_id = ?',
                ];

                foreach ($steps as $sql) {
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, 'i', $categoryId);
                    if (!mysqli_stmt_execute($stmt)) {
                        $cascadeOk = false;
                    }
                    mysqli_stmt_close($stmt);
                    if (!$cascadeOk) {
                        break;
                    }
                }

                if ($cascadeOk) {
                    $finalStmt = mysqli_prepare($conn, 'DELETE FROM CATEGORY WHERE category_id = ? AND user_id = ?');
                    mysqli_stmt_bind_param($finalStmt, 'ii', $categoryId, $userId);
                    $cascadeOk = mysqli_stmt_execute($finalStmt);
                    mysqli_stmt_close($finalStmt);
                }

                if ($cascadeOk) {
                    mysqli_commit($conn);
                    header('Location: categories.php?page=' . $page);
                    exit;
                }

                mysqli_rollback($conn);
                $errors[] = 'Could not delete this category. Please try again.';
            }
        }
    }
}

$countStmt = mysqli_prepare($conn, 'SELECT COUNT(*) FROM CATEGORY WHERE user_id = ?');
mysqli_stmt_bind_param($countStmt, 'i', $userId);
mysqli_stmt_execute($countStmt);
mysqli_stmt_bind_result($countStmt, $totalCategories);
mysqli_stmt_fetch($countStmt);
mysqli_stmt_close($countStmt);
$totalPages = (int) ceil($totalCategories / $perPage);

if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// LEFT JOIN + COUNT gives a live, always-accurate habit count per
// category — replaces the old hand-typed "Gym, Running, walking"
// description text, which had no connection to the real HABIT rows
// and would silently go stale the moment a habit was renamed or added.
$catStmt = mysqli_prepare($conn, 'SELECT CATEGORY.*, COUNT(HABIT.habit_id) AS habit_count
    FROM CATEGORY
    LEFT JOIN HABIT ON HABIT.category_id = CATEGORY.category_id
    WHERE CATEGORY.user_id = ?
    GROUP BY CATEGORY.category_id
    ORDER BY CATEGORY.created_at DESC
    LIMIT ? OFFSET ?');
mysqli_stmt_bind_param($catStmt, 'iii', $userId, $perPage, $offset);
mysqli_stmt_execute($catStmt);
$catResult = mysqli_stmt_get_result($catStmt);
$categories = mysqli_fetch_all($catResult, MYSQLI_ASSOC);
mysqli_stmt_close($catStmt);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Categories — Habit Track</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="categories.css?v=20260801-3">
</head>
<body>
  <div class="app-layout">
    <div class="sidebar">
      <?php require __DIR__ . '/../../includes/logo.php'; ?>
      <a href="../dashboard/dashboard.php" class="nav-item">Dashboard</a>
      <a href="../habits/habits.php" class="nav-item">Habits</a>
      <a href="categories.php" class="nav-item active">Categories</a>
      <a href="../reminders/reminders.php" class="nav-item">Reminders</a>
      <a href="../calendar/calendar.php" class="nav-item">Calendar</a>
      <div class="sidebar-footer">
        <a href="../auth/logout.php" class="nav-item">Logout</a>
      </div>
    </div>
    <div class="main-content">
      <div class="page-header"><h1>Categories</h1></div>

      <?php foreach ($errors as $err): ?>
        <div class="error-box"><?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>

      <div class="auth-card category-form-card">
        <form method="POST" action="categories.php?page=<?php echo $page; ?>">
          <input type="hidden" name="action" value="add">
          <div class="field"><input type="text" name="category_name" placeholder="Category name" required></div>
          <div class="field"><input type="text" name="description" placeholder="Description (optional)"></div>
          <button type="submit" class="btn-primary">Add Category</button>
        </form>
      </div>

      <?php if (empty($categories)): ?>
        <div class="empty-state"><p>No categories yet.</p></div>
      <?php else: ?>
        <table class="data-table">
          <thead>
            <tr>
              <th>Category</th>
              <th>Description</th>
              <th>Habits</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($categories as $cat): ?>
              <?php $isEditing = ($editCategoryId !== null && (int) $cat['category_id'] === (int) $editCategoryId); ?>

              <?php if ($isEditing): ?>
                <?php $ev = $editValues ?? $cat; ?>
                <tr class="edit-row" id="category-<?php echo $cat['category_id']; ?>">
                  <td colspan="4">
                    <form method="POST" action="categories.php?page=<?php echo $page; ?>">
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="category_id" value="<?php echo $cat['category_id']; ?>">

                      <div class="field"><input type="text" name="category_name" value="<?php echo htmlspecialchars((string) $ev['category_name']); ?>" required></div>
                      <div class="field"><input type="text" name="description" value="<?php echo htmlspecialchars((string) ($ev['description'] ?? '')); ?>" placeholder="Description (optional)"></div>

                      <div class="edit-form-actions">
                        <button type="submit" class="btn-primary">Save changes</button>
                        <a href="categories.php?page=<?php echo $page; ?>" class="btn-cancel">Cancel</a>
                      </div>
                    </form>
                  </td>
                </tr>

              <?php else: ?>
                <?php
                  $habitCount = (int) $cat['habit_count'];
                  $confirmMsg = 'Delete "' . $cat['category_name'] . '"? This will also permanently delete '
                      . $habitCount . ' habit(s) and everything under them (subtasks, logs, reminders). This cannot be undone.';
                ?>
                <tr id="category-<?php echo $cat['category_id']; ?>">
                  <td><?php echo htmlspecialchars($cat['category_name']); ?></td>
                  <td><?php echo $cat['description'] ? htmlspecialchars($cat['description']) : '—'; ?></td>
                  <td><span class="badge-optional"><?php echo $habitCount; ?> habit<?php echo $habitCount === 1 ? '' : 's'; ?></span></td>
                  <td class="actions-cell">
                    <a href="categories.php?page=<?php echo $page; ?>&edit_category_id=<?php echo $cat['category_id']; ?>#category-<?php echo $cat['category_id']; ?>" class="btn-edit">Edit</a>
                    <form method="POST" action="categories.php?page=<?php echo $page; ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="category_id" value="<?php echo $cat['category_id']; ?>">
                      <button type="submit" class="btn-delete" onclick="return confirm(<?php echo htmlspecialchars(json_encode($confirmMsg)); ?>)">Delete</button>
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
              <a class="pagination-link" href="categories.php?page=<?php echo $page - 1; ?>">Previous</a>
            <?php endif; ?>
            <span class="pagination-status">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
            <?php if ($page < $totalPages): ?>
              <a class="pagination-link" href="categories.php?page=<?php echo $page + 1; ?>">Next</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>