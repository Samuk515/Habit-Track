<?php
function requireLogin() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        require_once __DIR__ . '/functions.php';
        redirect('login.php');
    }
}