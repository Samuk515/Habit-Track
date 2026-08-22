<?php
declare(strict_types=1);
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

$errors = [];
$email = '';

if (isLoggedIn()) {
    header('Location: /modules/dashboard/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

        // Generic message either way — never reveal whether the
        // email exists or the password was wrong specifically.
        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Invalid email or password.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name'] = $user['name'];

            header('Location: /modules/dashboard/dashboard.php');
            exit;
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" integrity="sha512-bLT0Qm9VnAYZDflyKcBaQ2gg0hSYNQrJ8RilYldYQ1FxQYoCLtUjuuRuZo+fjqhx/qtq/1itJ0C2ejDxltZVFg==" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.5/jquery.validate.js"></script>
<script src="login-validation.js"></script>
<script src="auth.js"></script>
</body>
</html>