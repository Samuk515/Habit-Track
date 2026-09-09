<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ../auth/login.php');
        exit;
    }
}

function isAdminLoggedIn(): bool
{
    return !empty($_SESSION['admin_authenticated']);
}

function requireAdmin(): void
{
    if (!isAdminLoggedIn()) {
        header('Location: /modules/admin/login.php');
        exit;
    }
}

function getAdminEmail(): string
{
    return getenv('ADMIN_EMAIL') ?: 'Singhsamir515@gmail.com';
}

function verifyAdminCredentials(string $email, string $password): bool
{
    $configuredPassword = getenv('ADMIN_PASSWORD');

    if ($configuredPassword === false || $configuredPassword === '') {
        return false;
    }

    return hash_equals(getAdminEmail(), $email) && hash_equals($configuredPassword, $password);
}
