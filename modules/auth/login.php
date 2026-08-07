<?php
declare(strict_types=1);
require_once '../../includes/csrf.php';
require_once '../../includes/db.php';

$errors = [];
$email = '';

if (!empty($_SESSION['user_id'])) {
    header('Location: /modules/dashboard/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid or expired form submission. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $errors[] = 'Please enter your email and password.';
        }

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, 'SELECT user_id, name, password FROM USER WHERE email = ?');
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            // Same generic message whether the email doesn't exist or
            // the password is wrong — never reveal which one it was.
            if (!$user || !password_verify($password, $user['password'])) {
                $errors[] = 'Invalid email or password.';
            } else {
                // Regenerate the session ID on every successful login
                // to prevent session fixation attacks.
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name'] = $user['name'];

                header('Location: /modules/dashboard/dashboard.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log in — Habit Track</title>
<link rel="stylesheet" href="auth.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <?php require_once '../../includes/logo.php'; ?>
        <h1 class="auth-title">Welcome back</h1>

        <?php if (isset($_GET['registered'])): ?>
        <div class="auth-success">Account created — please log in.</div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <div class="auth-errors">
            <ul>
                <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" action="login.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">

            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>

            <label for="password">Password</label>
            <div class="password-field">
                <input type="password" id="password" name="password" required>
                <button type="button" class="toggle-password" data-target="password" aria-label="Show password">👁</button>
            </div>

            <button type="submit" id="submit-btn" class="btn-submit">Log in</button>
        </form>

        <p class="auth-switch">Don't have an account? <a href="register.php">Register</a></p>
    </div>
</div>
<script src="auth.js"></script>
</body>
</html>