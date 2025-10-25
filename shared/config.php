<?php
// shared/config.php - Unified database configuration

class DatabaseConfig
{
    // Development environment (local XAMPP)
    const DEV_HOST = 'localhost';
    const DEV_NAME = 'university_canteen';
    const DEV_USER = 'root';
    const DEV_PASS = '';

    // Production environment (Docker/Render)
    const PROD_HOST = 'localhost'; // Or your Render DB host
    const PROD_NAME = 'university_canteen';
    const PROD_USER = 'admin';
    const PROD_PASS = 'password';

    public static function getConnection()
    {
        // Auto-detect environment
        if (isset($_ENV['RENDER']) || getenv('RENDER')) {
            // Production environment (Render)
            $host = self::PROD_HOST;
            $dbname = self::PROD_NAME;
            $username = self::PROD_USER;
            $password = self::PROD_PASS;
        } else {
            // Development environment (local)
            $host = self::DEV_HOST;
            $dbname = self::DEV_NAME;
            $username = self::DEV_USER;
            $password = self::DEV_PASS;
        }

        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $pdo;
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection error. Please try again later.");
        }
    }
}

// Global database connection
$db = DatabaseConfig::getConnection();
?>