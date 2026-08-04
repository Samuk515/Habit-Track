<?php
declare(strict_types=1);
require_once '../../includes/auth.php';

// Wipe session data
$_SESSION = [];

// Expire the session cookie itself, not just the server-side data
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: login.php');
exit;
