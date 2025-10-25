<?php
// shared/db.php - Simple database connection

$host = 'localhost';
$dbname = 'university_canteen';
$username = 'root';
$password = '';

// For Render production - use environment variables
if (isset($_ENV['RENDER']) || getenv('RENDER')) {
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $dbname = $_ENV['DB_NAME'] ?? 'university_canteen';
    $username = $_ENV['DB_USER'] ?? 'admin';
    $password = $_ENV['DB_PASS'] ?? 'password';
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection error. Please try again later.");
}
?>