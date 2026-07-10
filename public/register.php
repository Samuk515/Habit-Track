<?php
require '../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // 1. Verify CSRF token first — reject immediately if it fails
  if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { die('Invalid request.'); }

  // 2. Grab and trim inputs from $_POST
  $name = trim($_POST['name'] ?? '');
  $email = strtolower(trim($_POST['email'] ?? ''));
  $password = $_POST['password'] ?? '';
  $confirm_password = $_POST['confirm_password'] ?? '';

  // 3. Validate inputs and collect all errors
  if ($name === '') {
    $errors[] = 'Name is required.';
  }

  if ($email === '') {
    $errors[] = 'Email is required.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email is not valid.';
  }

  if (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters.';
  }

  if ($password !== $confirm_password) {
    $errors[] = 'Passwords do not match.';
  }

  // 4. If no validation errors so far, check for duplicate email
  if (empty($errors)) {
    require __DIR__ . '/../config/db.php';
    $stmt = $pdo->prepare('SELECT user_id FROM USER WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
      $errors[] = 'Email already registered.';
    }
  }

  // 5. If still no errors, create the user and redirect to login
    if (empty($errors)) {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO USER (name, email, password) VALUES (?, ?, ?)');
    $stmt->execute([$name, $email, $hashedPassword]);
    redirect('login.php');
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Register — Habit Track</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="auth-wrap">
    <div class="auth-card">
      <?php require '../includes/logo.php'; ?>
      <h1>Create your account</h1>
      <p class="subtitle">Start tracking your habits today.</p>

      <?php if (!empty($errors)): ?>
        <div class="error-box">
          <?php foreach ($errors as $err): ?>
            <div><?php echo htmlspecialchars($err); ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="register.php">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        <div class="field"><input type="text" name="name" placeholder="Full name" required></div>
        <div class="field"><input type="email" name="email" placeholder="Email" required></div>
        <div class="field"><input type="password" name="password" placeholder="Password" required></div>
        <div class="field"><input type="password" name="confirm_password" placeholder="Confirm password" required></div>
        <button type="submit" class="btn-primary">Register</button>
      </form>

      <div class="form-footer">Already have an account? <a href="login.php">Login</a></div>
    </div>
  </div>
</body>
</html>