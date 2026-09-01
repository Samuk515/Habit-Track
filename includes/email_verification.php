<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../vendor/autoload.php';

function getAppBaseUrl(): string
{
    return rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/');
}

function createEmailVerificationToken(mysqli $conn, int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    $deleteStmt = mysqli_prepare($conn, 'DELETE FROM EMAIL_VERIFICATION WHERE user_id = ?');
    mysqli_stmt_bind_param($deleteStmt, 'i', $userId);
    mysqli_stmt_execute($deleteStmt);
    mysqli_stmt_close($deleteStmt);

    $insertStmt = mysqli_prepare(
        $conn,
        'INSERT INTO EMAIL_VERIFICATION (user_id, token_hash, expires_at, created_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())'
    );
    mysqli_stmt_bind_param($insertStmt, 'is', $userId, $tokenHash);
    mysqli_stmt_execute($insertStmt);
    mysqli_stmt_close($insertStmt);

    return $token;
}

function sendVerificationEmail(string $email, string $name, string $token): bool
{
    $verificationUrl = getAppBaseUrl() . '/modules/auth/verify-email.php?token=' . urlencode($token);
    $mail = new PHPMailer(true);

    try {
        $smtpHost = getenv('SMTP_HOST') ?: '';

        if ($smtpHost !== '') {
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->Port = (int) (getenv('SMTP_PORT') ?: 587);
            $mail->SMTPSecure = getenv('SMTP_SECURE') ?: PHPMailer::ENCRYPTION_STARTTLS;

            $smtpUsername = getenv('SMTP_USERNAME') ?: '';
            if ($smtpUsername !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = $smtpUsername;
                $mail->Password = getenv('SMTP_PASSWORD') ?: '';
            }
        }

        $fromEmail = getenv('MAIL_FROM') ?: 'no-reply@habit-track.local';
        $fromName = getenv('MAIL_FROM_NAME') ?: 'Habit Track';

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($email, $name);
        $mail->Subject = 'Verify your Habit Track email';
        $mail->isHTML(true);
        $mail->Body = '<p>Hi ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>Please verify your email address to finish setting up your Habit Track account.</p>'
            . '<p><a href="' . htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8') . '">Verify email address</a></p>'
            . '<p>This link expires in 24 hours.</p>';
        $mail->AltBody = "Hi {$name},\n\nVerify your email address with this link:\n{$verificationUrl}\n\nThis link expires in 24 hours.";

        $mail->send();
        return true;
    } catch (Exception $exception) {
        error_log('Verification email failed: ' . $exception->getMessage());
        error_log('Verification link for ' . $email . ': ' . $verificationUrl);
        return false;
    }
}
