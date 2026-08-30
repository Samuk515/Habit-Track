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

$ddlStatements = [
    "CREATE TABLE IF NOT EXISTS USER (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS CATEGORY (
        category_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        category_name VARCHAR(100) NOT NULL,
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES USER(user_id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS HABIT (
        habit_id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        habit_name VARCHAR(100) NOT NULL,
        habit_nature ENUM('good', 'bad') NOT NULL DEFAULT 'good',
        measurement_type ENUM('boolean', 'count', 'duration', 'weight', 'distance', 'rating', 'percentage', 'steps', 'custom', 'money', 'time_of_day', 'score', 'volume', 'partial') NOT NULL DEFAULT 'boolean',
        target_value INT NULL,
        target_type ENUM('daily', 'twice a week', 'weekly') NOT NULL DEFAULT 'daily',
        description TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES CATEGORY(category_id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS SUBTASK (
        subtask_id INT AUTO_INCREMENT PRIMARY KEY,
        habit_id INT NOT NULL,
        subtask_name VARCHAR(150) NOT NULL,
        description VARCHAR(255),
        is_optional TINYINT(1) NOT NULL DEFAULT 0,
        order_no INT NOT NULL DEFAULT 0,
        FOREIGN KEY (habit_id) REFERENCES HABIT(habit_id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS HABIT_LOG (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        habit_id INT NOT NULL,
        subtask_id INT NULL,
        log_date DATE NOT NULL,
        value INT NULL,
        unit VARCHAR(30) NULL,
        status ENUM('done','skipped','partial') NOT NULL DEFAULT 'done',
        notes VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (habit_id) REFERENCES HABIT(habit_id) ON DELETE CASCADE,
        FOREIGN KEY (subtask_id) REFERENCES SUBTASK(subtask_id) ON DELETE SET NULL,
        UNIQUE KEY unique_habit_per_day (habit_id, log_date)
    )",
    "CREATE TABLE IF NOT EXISTS STREAK (
        streak_id INT AUTO_INCREMENT PRIMARY KEY,
        habit_id INT NOT NULL UNIQUE,
        current_streak INT NOT NULL DEFAULT 0,
        longest_streak INT NOT NULL DEFAULT 0,
        FOREIGN KEY (habit_id) REFERENCES HABIT(habit_id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS Bad_Habit_Progress (
        progress_id INT AUTO_INCREMENT PRIMARY KEY,
        log_id INT NOT NULL,
        log_date DATE NOT NULL,
        value INT NULL,
        notes VARCHAR(255),
        FOREIGN KEY (log_id) REFERENCES HABIT_LOG(log_id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS REMINDER (
        reminder_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        subtask_id INT NULL,
        label VARCHAR(255) NULL,
        reminder_time TIME NOT NULL,
        reminder_type ENUM('once', 'daily', 'weekly') NOT NULL DEFAULT 'daily',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        FOREIGN KEY (user_id) REFERENCES USER(user_id) ON DELETE CASCADE,
        FOREIGN KEY (subtask_id) REFERENCES SUBTASK(subtask_id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS CALENDAR_EVENT (
        event_id INT AUTO_INCREMENT PRIMARY KEY,
        subtask_id INT NOT NULL,
        label VARCHAR(255) NOT NULL,
        event_date DATE NOT NULL,
        event_type VARCHAR(50) NOT NULL,
        ref_id INT NULL,
        FOREIGN KEY (subtask_id) REFERENCES SUBTASK(subtask_id) ON DELETE CASCADE,
        FOREIGN KEY (ref_id) REFERENCES HABIT_LOG(log_id) ON DELETE CASCADE
    )",
];

foreach ($ddlStatements as $sql) {
    $result = mysqli_query($conn, $sql);
    if ($result === false) {
        error_log('DB schema creation failed: ' . mysqli_error($conn) . ' :: ' . $sql);
        die('A system error occurred while initializing the database.');
    }
}
;