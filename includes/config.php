<?php
/**
 * Configuration File - Database, Constants, Session
 * NO FUNCTION DECLARATIONS HERE
 */

// Start session only if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'reviewflow');
define('DB_USER', 'reviewflow_user');
define('DB_PASS', 'Malik@241123');

// Base URL Configuration
define('BASE_URL', 'http://localhost/palians/reviewer');
define('SITE_NAME', 'Reviewer Task Management System');

// Path Constants
define('ROOT_PATH', dirname(dirname(__FILE__)));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('ADMIN_PATH', ROOT_PATH . '/admin');
define('USER_PATH', ROOT_PATH . '/user');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');

// Application Settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB in bytes
define('ALLOWED_FILE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('SESSION_TIMEOUT', 3600 * 2); // 2 hours

// Database Connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    // Log error but don't expose details in production
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection error. Please try again later.");
}

// Check if admin credentials exist in session, if not set default
if (!isset($_SESSION['admin_logged_in'])) {
    $_SESSION['admin_logged_in'] = false;
}

// Timezone setting
date_default_timezone_set('Asia/Kolkata');
?>
