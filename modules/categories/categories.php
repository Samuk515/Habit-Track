<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/auth.php';
requireLogin();
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/db.php';

$errors = [];
$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('Invalid request.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '') {
            $errors[] = 'Category name is required.';
        }

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, 'INSERT INTO CATEGORY (user_id, category_name, description) VALUES (?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'iss', $userId, $name, $description);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // Post/Redirect/Get — the original fell through to re-render
            // instead of redirecting, which means a page refresh after
            // adding a category would silently resubmit the same insert.
            redirect('categories.php');
        }
    }

    if ($action === 'delete') {
        $categoryId = $_POST['category_id'] ?? '';

        // !== false, not a truthy check — FILTER_VALIDATE_INT returns
        // 0 for a valid "0", and 0 is falsy in PHP. A plain `if (filter_var(...))`
        // would silently reject a real category_id of 0.
        if (filter_var($categoryId, FILTER_VALIDATE_INT) !== false) {
            $categoryId = (int) $categoryId;

            // Ownership enforced directly in the WHERE clause — CATEGORY
            // has user_id as a native column, no JOIN needed here (unlike
            // HABIT and everything below it).
            $stmt = mysqli_prepare($conn, 'DELETE FROM CATEGORY WHERE category_id = ? AND user_id = ?');
            mysqli_stmt_bind_param($stmt, 'ii', $categoryId, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect('categories.php');
        }
    }
}

$stmt = mysqli_prepare($conn, 'SELECT * FROM CATEGORY WHERE user_id = ? ORDER BY created_at DESC');
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$categories = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Categories — Habit Track</title>
  <link rel="stylesheet" href="categories.css?v=20260801-2">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <div class="app-layout">
    <div class="sidebar">
      <?php require __DIR__ . '/../../includes/logo.php'; ?>
      <a href="../dashboard/dashboard.php" class="nav-item">Dashboard</a>
      <a href="../habits/habits.php" class="nav-item">Habits</a>
      <a href="categories.php" class="nav-item active">Categories</a>
      <a href="../reminders/reminders.php" class="nav-item">Reminders</a>
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
          <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
          <input type="hidden" name="action" value="add">
          <div class="field"><input type="text" name="category_name" placeholder="Category name" required></div>
          <div class="field"><input type="text" name="description" placeholder="Description (optional)"></div>
          <button type="submit" class="btn-primary">Add Category</button>
        </form>
      </div>

      <?php if (empty($categories)): ?>
        <div class="empty-state"><p>No categories yet.</p></div>
      <?php else: ?>
        <div class="category-list">
        <?php foreach ($categories as $cat): ?>
          <div class="auth-card category-card">
            <div>
              <div class="category-name"><?php echo htmlspecialchars($cat['category_name']); ?></div>
              <?php if (!empty($cat['description'])): ?>
                <div class="category-desc"><?php echo htmlspecialchars($cat['description']); ?></div>
              <?php endif; ?>
            </div>
            <form method="POST" action="categories.php">
              <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="category_id" value="<?php echo $cat['category_id']; ?>">
              <button type="submit" class="btn-delete">Delete</button>
            </form>
          </div>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>