<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

if (isAdminLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (verifyAdminCredentials($email, $password)) {
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_email'] = $email;
        header('Location: dashboard.php');
        exit;
    }

    $error = 'Invalid admin email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin login — Habit Track</title>
<link rel="stylesheet" href="../auth/auth.css">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <?php require_once __DIR__ . '/../../includes/logo.php'; ?>
        <h1 class="auth-title">Admin login</h1>

        <?php if ($error !== ''): ?>
        <div class="auth-errors"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" novalidate>
            <label for="email">Admin email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required autofocus>

            <label for="password">Admin password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit" class="btn-submit">Log in to admin</button>
        </form>

        <p class="auth-switch"><a href="../auth/login.php">Back to user login</a></p>
    </div>
</div>
</body>
</html>