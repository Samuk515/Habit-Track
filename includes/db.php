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

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS REMINDER (
    reminder_id INT AUTO_INCREMENT PRIMARY KEY,
    subtask_id INT NOT NULL,
    reminder_time TIME NOT NULL,
    reminder_type ENUM('once', 'daily', 'weekly') NOT NULL DEFAULT 'daily',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (subtask_id) REFERENCES SUBTASK(subtask_id) ON DELETE CASCADE
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS CALENDAR_EVENT (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    subtask_id INT NOT NULL,
    label VARCHAR(255) NOT NULL,
    event_date DATE NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    ref_id INT NULL,
    FOREIGN KEY (subtask_id) REFERENCES SUBTASK(subtask_id) ON DELETE CASCADE
)");