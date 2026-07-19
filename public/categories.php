<?php
require '../includes/auth.php';
requireLogin();
require '../includes/csrf.php';
require __DIR__ . '/../config/db.php';

$errors = [];
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Verify CSRF token 
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { die('Invalid request.'); }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        // 2. Grab and trim category_name and description from $_POST
        $name = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        // 3. Validate: category_name is not empty
        if ($name === '') {
            $errors[] = 'Category name is required.';
        }

        // 4. If valid, insert:
        //    INSERT INTO CATEGORY (user_id, category_name, description) 
        if (empty($errors)) {
            $stmt = $pdo->prepare('INSERT INTO CATEGORY (user_id, category_name, description) VALUES (?, ?, ?)');
            $stmt->execute([$userId, $name, $description]);
        }
    }

    if ($action === 'delete') {
        // 5. Grab category_id from $_POST
        $categoryId = $_POST['category_id'] ?? '';

        // 6. Delete with BOTH conditions in the WHERE clause:
        //    DELETE FROM CATEGORY WHERE category_id = ? AND user_id = ?
        if (filter_var($categoryId, FILTER_VALIDATE_INT)) {
            $stmt = $pdo->prepare('DELETE FROM CATEGORY WHERE category_id = ? AND user_id = ?');
            $stmt->execute([$categoryId, $userId]);
        }
    }
}

// 7. Fetch this user's categories to display:
$stmt = $pdo->prepare('SELECT * FROM CATEGORY WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$userId]);
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Categories — Habit Track</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="app-layout">
    <div class="sidebar">
      <?php require '../includes/logo.php'; ?>
      <a href="dashboard.php" class="nav-item">Dashboard</a>
      <a href="habits.php" class="nav-item">Habits</a>
      <a href="categories.php" class="nav-item active">Categories</a>
      <div style="margin-top:auto;">
        <a href="logout.php" class="nav-item">Logout</a>
      </div>
    </div>
    <div class="main-content">
      <div class="page-header"><h1>Categories</h1></div>

      <?php foreach ($errors as $err): ?>
        <div class="error-box"><?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>

      <div class="auth-card" style="max-width:500px;margin-bottom:24px;">
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
        <div style="display:flex;flex-direction:column;gap:10px;">
        <?php foreach ($categories as $cat): ?>
          <div class="auth-card" style="max-width:500px;padding:16px 20px;display:flex;justify-content:space-between;align-items:center;">
            <div>
              <div style="font-weight:600;"><?php echo htmlspecialchars($cat['category_name']); ?></div>
              <?php if (!empty($cat['description'])): ?>
                <div style="font-size:12px;color:var(--muted);margin-top:2px;"><?php echo htmlspecialchars($cat['description']); ?></div>
              <?php endif; ?>
            </div>
            <form method="POST" action="categories.php">
              <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="category_id" value="<?php echo $cat['category_id']; ?>">
              <button type="submit" style="background:none;border:1px solid var(--border);color:var(--coral);border-radius:8px;padding:6px 12px;font-size:13px;cursor:pointer;">Delete</button>
            </form>
          </div>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
