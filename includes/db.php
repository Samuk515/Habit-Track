<?php
// Database connection 
$host = '127.0.0.1';
$db   = 'habit_track_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4'; //UTF-8 support in MySQL (handles emojis, special characters, etc.)

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
	PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
	PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Fetch results as associative arrays
	PDO::ATTR_EMULATE_PREPARES => false, // Use native prepared statements if possible
];

try {
	$pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
	die('Database connection failed: ' . $e->getMessage());
}