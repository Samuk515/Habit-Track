<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/email_verification.php';

$email = trim($_POST['email'] ?? $_GET['email'] ?? '');
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $stmt = mysqli_prepare($conn, 'SELECT user_id, name, email_verified_at FROM USER WHERE email = ?');
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($user && $user['email_verified_at'] === null) {
            $verification = createEmailVerificationToken($conn, (int) $user['user_id']);
            sendVerificationEmail($email, $user['name'], $verification['token'], $verification['code']);
        }

        $message = 'If that email belongs to an unverified account, a new verification email has been sent.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Resend verification — Habit Track</title>
<link rel="stylesheet" href="auth.css">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <?php require_once '../../includes/logo.php'; ?>
        <h1 class="auth-title">Resend verification</h1>

        <?php if ($message !== ''): ?>
        <div class="auth-success"><?= htmlspecialchars($message) ?></div>
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

        <form method="POST" action="resend-verification.php" novalidate>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>

            <button type="submit" class="btn-submit">Send verification email</button>
        </form>

        <p class="auth-switch"><a href="login.php">Back to login</a></p>
    </div>
</div>
</body>
</html>
