<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';

$message = 'The verification link is invalid or has expired.';
$isSuccess = false;
$token = $_GET['token'] ?? '';
$email = trim($_GET['email'] ?? $_POST['email'] ?? '');
$code = trim($_POST['code'] ?? '');
$verification = null;

if (is_string($token) && preg_match('/\A[a-f0-9]{64}\z/', $token) === 1) {
    $tokenHash = hash('sha256', $token);
    $stmt = mysqli_prepare(
        $conn,
        'SELECT verification_id, user_id FROM EMAIL_VERIFICATION WHERE token_hash = ? AND expires_at > NOW()'
    );
    mysqli_stmt_bind_param($stmt, 's', $tokenHash);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $verification = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST'
    && filter_var($email, FILTER_VALIDATE_EMAIL)
    && preg_match('/\A\d{6}\z/', $code) === 1
) {
    $codeHash = hash('sha256', $code);
    $stmt = mysqli_prepare(
        $conn,
        'SELECT verification_id, user_id, otp_hash FROM EMAIL_VERIFICATION ev
         INNER JOIN USER u ON u.user_id = ev.user_id
         WHERE u.email = ? AND ev.expires_at > NOW()'
    );
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $candidate = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($candidate && is_string($candidate['otp_hash']) && hash_equals($candidate['otp_hash'], $codeHash)) {
        $verification = $candidate;
    }
}

    if ($verification) {
        $userId = (int) $verification['user_id'];

        $updateStmt = mysqli_prepare($conn, 'UPDATE USER SET email_verified_at = NOW() WHERE user_id = ?');
        mysqli_stmt_bind_param($updateStmt, 'i', $userId);
        mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);

        $deleteStmt = mysqli_prepare($conn, 'DELETE FROM EMAIL_VERIFICATION WHERE user_id = ?');
        mysqli_stmt_bind_param($deleteStmt, 'i', $userId);
        mysqli_stmt_execute($deleteStmt);
        mysqli_stmt_close($deleteStmt);

        $message = 'Your email has been verified. You can log in now.';
        $isSuccess = true;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Email verification — Habit Track</title>
<link rel="stylesheet" href="auth.css">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <?php require_once '../../includes/logo.php'; ?>
        <h1 class="auth-title">Email verification</h1>

        <div class="<?= $isSuccess ? 'auth-success' : 'auth-errors' ?>">
            <?= htmlspecialchars($message) ?>
        </div>

        <?php if (!$isSuccess): ?>
        <form method="POST" action="verify-email.php" novalidate>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>

            <label for="code">Verification code</label>
            <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>

            <button type="submit" class="btn-submit">Verify email</button>
        </form>
        <?php endif; ?>

        <p class="auth-switch"><a href="login.php">Back to login</a></p>
    </div>
</div>
</body>
</html>
