<?php
session_start();

// 1. Clear all session data
$_SESSION = [];

// 2. Expire the session cookie
if (ini_get("session.use_cookies")) { // Check if session cookies are being used ini_get reads a setting
    $params = session_get_cookie_params(); // Get current session cookie parameters
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destroy the session
session_destroy();

// 4. Redirect to login
require_once __DIR__ . '/../includes/functions.php'; // require once stope php with an error if the file is not found
redirect('login.php');