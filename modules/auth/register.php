<?php
declare(strict_types=1);
require_once '../../includes/csrf.php';
require_once '../../includes/db.php';

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. CSRF check happens before we touch anything else
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid or expired form submission. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // 2. Validate everything BEFORE any database write
        if ($name === '' || $email === '' || $password === '') {
            $errors[] = 'All fields are required.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        // 3. Uniqueness check using MySQLi
        if (empty($errors)) {
            $checkStmt = mysqli_prepare($conn, 'SELECT user_id FROM USER WHERE email = ?');
            mysqli_stmt_bind_param($checkStmt, 's', $email);
            mysqli_stmt_execute($checkStmt);
            $result = mysqli_stmt_get_result($checkStmt);
            
            if (mysqli_fetch_assoc($result)) {
                $errors[] = 'An account with this email already exists.';
            }
            mysqli_stmt_close($checkStmt);
        }

        // 4. Write only after every validation branch is clean
        if (empty($errors)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $insertStmt = mysqli_prepare(
                $conn,
                'INSERT INTO USER (name, email, password, created_at) VALUES (?, ?, ?, NOW())'
            );
            mysqli_stmt_bind_param($insertStmt, 'sss', $name, $email, $hashedPassword);

            if (mysqli_stmt_execute($insertStmt)) {
                // 5. Post/Redirect/Get
                header('Location: login.php?registered=1');
                exit;
            }
            mysqli_stmt_close($insertStmt);
            
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — Habit Track</title>
<link rel="stylesheet" href="auth.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <?php require_once '../../includes/logo.php'; ?>
        <h1 class="auth-title">Create your account</h1>

        <?php if (!empty($errors)): ?>
        <div class="auth-errors">
            <ul>
                <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" action="register.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">

            <label for="name">Full name</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>

            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>

            <label for="password">Password</label>
            <div class="password-field">
                <input type="password" id="password" name="password" required minlength="8">
                <button type="button" class="toggle-password" data-target="password" aria-label="Show password">👁</button>
            </div>

            <label for="confirm_password">Confirm password</label>
            <div class="password-field">
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                <button type="button" class="toggle-password" data-target="confirm_password" aria-label="Show password">👁</button>
            </div>
            <p id="match-feedback" class="match-feedback"></p>

            <button type="submit" id="submit-btn" class="btn-submit">Create account</button>
        </form>

        <p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
    </div>
</div>
<script src="auth.js"></script>
</body>
</html>