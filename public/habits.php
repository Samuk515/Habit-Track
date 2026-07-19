<?php
require '../includes/auth.php';
requireLogin();
require '../includes/csrf.php';
require __DIR__ . '/../config/db.php';

$errors = [];
$userId = $_SESSION['user_id'];

$catStmt = $pdo->prepare('SELECT category_id, category_name FROM CATEGORY WHERE user_id = ? ORDER BY category_name');
$catStmt->execute([$userId]);
$categories = $catStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Verify CSRF token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { die('Invalid request.'); }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        
        $habitName = trim($_POST['habit_name'] ?? '');
        $categoryId = $_POST['category_id'] ?? '';
        $habitNature = $_POST['habit_nature'] ?? '';
        $measurementType = $_POST['measurement_type'] ?? '';
        $targetValue = trim($_POST['target_value'] ?? '');
        $targetType = $_POST['target_type'] ?? '';
        $description = trim($_POST['description'] ?? '');

        // 3. Validate: and not to keep habit name empty
        if ($habitName === '') {
            $errors[] = 'Habit name is required.';
        }

    
        $ownsCategory = false;
        foreach ($categories as $cat) {
            if ((string) $cat['category_id'] === (string) $categoryId) {
                $ownsCategory = true;
                break;
            }
        }

        if (!$ownsCategory) {
            $errors[] = 'Please select a valid category.';
        }

        //    keeping habit_nature is 'good' or 'bad'
        if (!in_array($habitNature, ['good', 'bad'], true)) {
            $errors[] = 'Please select a valid habit type.';
        }

        if (!in_array($measurementType, ['boolean', 'count', 'duration'], true)) {
            $errors[] = 'Please select a valid measurement type.';
        }

        if (!in_array($targetType, ['daily', 'weekly'], true)) {
            $errors[] = 'Please select a valid target type.';
        }

        if ($targetValue === '') {
            $targetValue = null;
        } elseif (filter_var($targetValue, FILTER_VALIDATE_INT) === false) {
            $errors[] = 'Target value must be a whole number.';
        }
        if (empty($errors)) {
            $stmt = $pdo->prepare('INSERT INTO HABIT (category_id, habit_name, habit_nature, measurement_type, target_value, target_type, description) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$categoryId, $habitName, $habitNature, $measurementType, $targetValue, $targetType, $description]);
        }
    }

    if ($action === 'delete') {
        $habitId = $_POST['habit_id'] ?? '';

       
        if (filter_var($habitId, FILTER_VALIDATE_INT) !== false) {
            $stmt = $pdo->prepare('DELETE FROM HABIT WHERE habit_id = ? AND 
            category_id IN (SELECT category_id FROM CATEGORY WHERE user_id = ?)');
            $stmt->execute([$habitId, $userId]);
        }
    }
}

  $habitStmt = $pdo->prepare('SELECT HABIT.*, CATEGORY.category_name 
  FROM HABIT INNER JOIN CATEGORY ON HABIT.category_id = CATEGORY.category_id WHERE CATEGORY.user_id = ?
   ORDER BY HABIT.created_at DESC');
  $habitStmt->execute([$userId]);
  $habits = $habitStmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Habits — Habit Track</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="app-layout">
    <div class="sidebar">
      <?php require '../includes/logo.php'; ?>
      <a href="dashboard.php" class="nav-item">Dashboard</a>
      <a href="habits.php" class="nav-item active">Habits</a>
      <a href="categories.php" class="nav-item">Categories</a>
      <div style="margin-top:auto;">
        <a href="logout.php" class="nav-item">Logout</a>
      </div>
    </div>
    <div class="main-content">
      <div class="page-header"><h1>Habits</h1></div>

      <?php foreach ($errors as $err): ?>
        <div class="error-box"><?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>

      <?php if (empty($categories)): ?>
        <div class="empty-state">
          <p>You need at least one category before adding a habit.</p>
          <a href="categories.php">Create a category →</a>
        </div>
      <?php else: ?>
        <div class="auth-card" style="max-width:500px;margin-bottom:24px;">
          <form method="POST" action="habits.php">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="action" value="add">

            <div class="field"><input type="text" name="habit_name" placeholder="Habit name" required></div>

            <div class="field">
              <select name="category_id" required style="width:100%;padding:11px 13px;border:1px solid var(--border);border-radius:9px;font-size:14px;">
                <option value="">Select category</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="field">
              <select name="habit_nature" style="width:100%;padding:11px 13px;border:1px solid var(--border);border-radius:9px;font-size:14px;">
                <option value="good">Good habit</option>
                <option value="bad">Bad habit</option>
              </select>
            </div>

            <div class="field">
              <select name="measurement_type" style="width:100%;padding:11px 13px;border:1px solid var(--border);border-radius:9px;font-size:14px;">
                <option value="boolean">Simple (did / didn't)</option>
                <option value="count">Count (e.g. glasses of water)</option>
                <option value="duration">Duration (minutes)</option>
              </select>
            </div>

            <div class="field"><input type="number" name="target_value" placeholder="Target value (if count/duration)"></div>

            <div class="field">
              <select name="target_type" style="width:100%;padding:11px 13px;border:1px solid var(--border);border-radius:9px;font-size:14px;">
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
              </select>
            </div>

            <div class="field"><input type="text" name="description" placeholder="Description (optional)"></div>

            <button type="submit" class="btn-primary">Add Habit</button>
          </form>
        </div>
      <?php endif; ?>

      <?php if (empty($habits)): ?>
        <div class="empty-state"><p>No habits yet.</p></div>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:10px;">
        <?php foreach ($habits as $h): ?>
            <div class="auth-card" style="max-width:500px;padding:16px 20px;display:flex;justify-content:space-between;align-items:center;">
              <div>
                <div style="font-weight:600;"><?php echo htmlspecialchars($h['habit_name']); ?></div>
                <div style="font-size:12px;color:var(--muted);margin-top:2px;"><?php echo htmlspecialchars($h['category_name']); ?></div>
              </div>
              <form method="POST" action="habits.php">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="habit_id" value="<?php echo $h['habit_id']; ?>">
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
