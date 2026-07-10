<?php
require '../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // 1. Verify CSRF token
  if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { die('Invalid request.'); }

  // 2. Grab and normalize inputs
  $email = strtolower(trim($_POST['email'] ?? ''));
  $password = $_POST['password'] ?? '';

  // 3. Basic presence check
  if ($email === '' || $password === '') {
    $errors[] = 'Email and password are required.';
  }

  // 4 & 5. Lookup user and verify password
  if (empty($errors)) {
    require __DIR__ . '/../config/db.php';
    $stmt = $pdo->prepare('SELECT user_id, name, password FROM USER WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
      $errors[] = 'Invalid email or password.';
    } else {
      // 6. Success — establish session and redirect
      session_regenerate_id(true);
      $_SESSION['user_id'] = $user['user_id'];
      $_SESSION['name'] = $user['name'];
      redirect('dashboard.php');
    }
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Login — Habit Track</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="auth-wrap">
    <div class="auth-card">
      <?php require '../includes/logo.php'; ?>
      <h1>Welcome back</h1>
      <p class="subtitle">Log in to continue your streaks.</p>

      <?php if (!empty($errors)): ?>
        <div class="error-box">
          <?php foreach ($errors as $err): ?>
            <div><?php echo htmlspecialchars($err); ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        <div class="field"><input type="email" name="email" placeholder="Email" required></div>
        <div class="field"><input type="password" name="password" placeholder="Password" required></div>
        <button type="submit" class="btn-primary">Login</button>
      </form>

      <div class="form-footer">Don't have an account? <a href="register.php">Register</a></div>
    </div>
  </div>
</body>
</html>