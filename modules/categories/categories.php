<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/auth.php';
requireLogin();
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/db.php';

$errors = [];
$userId = (int) $_SESSION['user_id'];

// Failed-edit state, if a POST update below fails validation — used
// to reopen the dialog with the attempted values instead of losing them.
$failedEditCategoryId = null;
$failedEditValues = null;

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

                header('Location: categories.php');
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
                    header('Location: categories.php');
                    exit;
                }
            }

            if (!empty($errors)) {
                $failedEditCategoryId = $categoryId ?? null;
                $failedEditValues = [
                    'id' => $failedEditCategoryId,
                    'name' => $name,
                    'description' => $description,
                ];
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
                    header('Location: categories.php');
                    exit;
                }

                mysqli_rollback($conn);
                $errors[] = 'Could not delete this category. Please try again.';
            }
        }
    }
}

// DataTables handles pagination client-side now — fetch everything,
// no LIMIT/OFFSET or page math needed server-side anymore.
$catStmt = mysqli_prepare($conn, 'SELECT CATEGORY.*, COUNT(HABIT.habit_id) AS habit_count
    FROM CATEGORY
    LEFT JOIN HABIT ON HABIT.category_id = CATEGORY.category_id
    WHERE CATEGORY.user_id = ?
    GROUP BY CATEGORY.category_id
    ORDER BY CATEGORY.created_at DESC');
mysqli_stmt_bind_param($catStmt, 'i', $userId);
mysqli_stmt_execute($catStmt);
$catResult = mysqli_stmt_get_result($catStmt);
$categories = mysqli_fetch_all($catResult, MYSQLI_ASSOC);
mysqli_stmt_close($catStmt);

// Every category's data, for the edit dialog to look up client-side.
$categoriesForJs = array_map(function ($c) {
    return [
        'id' => (int) $c['category_id'],
        'name' => $c['category_name'],
        'description' => $c['description'],
    ];
}, $categories);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Categories — Habit Track</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
  <link href="https://cdn.datatables.net/v/dt/dt-3.0.2/datatables.min.css" rel="stylesheet">
  <link rel="stylesheet" href="categories.css?v=20260801-4">
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
        <form method="POST" action="categories.php">
          <input type="hidden" name="action" value="add">
          <div class="field"><input type="text" name="category_name" placeholder="Category name" required></div>
          <div class="field"><input type="text" name="description" placeholder="Description (optional)"></div>
          <button type="submit" class="btn-primary">Add Category</button>
        </form>
      </div>

      <?php if (empty($categories)): ?>
        <div class="empty-state"><p>No categories yet.</p></div>
      <?php else: ?>
        <table id="categories-table" class="data-table">
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
              <?php
                $habitCount = (int) $cat['habit_count'];
                $confirmMsg = 'Delete "' . $cat['category_name'] . '"? This will also permanently delete '
                    . $habitCount . ' habit(s) and everything under them (subtasks, logs, reminders). This cannot be undone.';
              ?>
              <tr>
                <td><?php echo htmlspecialchars($cat['category_name']); ?></td>
                <td><?php echo $cat['description'] ? htmlspecialchars($cat['description']) : '—'; ?></td>
                <td><span class="badge-optional"><?php echo $habitCount; ?> habit<?php echo $habitCount === 1 ? '' : 's'; ?></span></td>
                <td class="actions-cell">
                  <button type="button" class="btn-edit" onclick="openEditCategoryDialog(<?php echo (int) $cat['category_id']; ?>)">Edit</button>
                  <form method="POST" action="categories.php" style="display:inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="category_id" value="<?php echo $cat['category_id']; ?>">
                    <button type="submit" class="btn-delete" onclick="return confirm(<?php echo htmlspecialchars(json_encode($confirmMsg)); ?>)">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <!-- Edit dialog — lives outside the table entirely so DataTables
           never has to reason about an irregular row. -->
      <div id="edit-category-dialog" title="Edit Category" style="display:none;">
        <form method="POST" action="categories.php" id="edit-category-form">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="category_id" id="edit-category-id">

          <div class="field"><input type="text" name="category_name" id="edit-category-name" required></div>
          <div class="field"><input type="text" name="description" id="edit-category-description" placeholder="Description (optional)"></div>

          <button type="submit" class="btn-primary">Save changes</button>
        </form>
      </div>

    </div>
  </div>

  <script>
    window.CATEGORIES_DATA = <?php echo json_encode($categoriesForJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.FAILED_EDIT = <?php echo $failedEditValues ? json_encode($failedEditValues, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : 'null'; ?>;
  </script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" integrity="sha512-bLT0Qm9VnAYZDflyKcBaQ2gg0hSYNQrJ8RilYldYQ1FxQYoCLtUjuuRuZo+fjqhx/qtq/1itJ0C2ejDxltZVFg==" crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
  <script src="https://cdn.datatables.net/v/dt/dt-3.0.2/datatables.min.js"></script>
  <script src="categories-datatable.js"></script>
</body>
</html>