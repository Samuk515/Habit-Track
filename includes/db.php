<?php
declare(strict_types=1);

$host = getenv('DB_HOST') ?: '127.0.0.1';
$db = getenv('DB_NAME') ?: 'habit_track_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$charset = 'utf8mb4';

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    error_log('DB connection failed: ' . mysqli_connect_error());
    die('A system error occurred. Please try again later.');
}

mysqli_set_charset($conn, $charset);