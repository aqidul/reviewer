<?php
declare(strict_types=1);

// PHP 8.2 strict mode
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Database Configuration
const DB_HOST = 'localhost';
const DB_USER = 'reviewflow_user';
const DB_PASS = 'Malik@241123';
const DB_NAME = 'reviewflow';
const DB_CHARSET = 'utf8mb4';

// Application Settings
const APP_NAME = 'Reviewer Task Management';
const APP_URL = 'https://palians.com/reviewer';
const ADMIN_URL = 'https://palians.com/reviewer/admin';

// Security Settings
const SESSION_TIMEOUT = 3600; // 1 hour
const PASSWORD_HASH_ALGO = PASSWORD_BCRYPT;
const PASSWORD_HASH_OPTIONS = ['cost' => 12];

// File Upload Settings
const UPLOAD_DIR = __DIR__ . '/../uploads/';
const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

// Create uploads directory if not exists
if (!is_dir(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0755, true);
}

// Create logs directory
$logs_dir = __DIR__ . '/../logs';
if (!is_dir($logs_dir)) {
    @mkdir($logs_dir, 0755, true);
}

ini_set('error_log', $logs_dir . '/error.log');

// PDO Connection with Security Settings (PHP 8.2 compatible)
try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );
    
    $pdo = new PDO(
        dsn: $dsn,
        username: DB_USER,
        password: DB_PASS,
        options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,  // Critical for security
            PDO::ATTR_PERSISTENT => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            PDO::ATTR_STRINGIFY_FETCHES => false,  // PHP 8.2: prevent string conversion
        ]
    );
    
    // Enable exception handling
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (PDOException $e) {
    error_log('Database Connection Failed: ' . $e->getMessage());
    http_response_code(500);
    die('Database connection error. Please try again later.');
}

// PHP 8.2 Null Safe Operator (?->)
// Consistent Error Handler
set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline): bool {
    error_log("[$errno] $errstr in $errfile:$errline");
    return true;
});

// Session Configuration
session_set_cookie_params([
    'lifetime' => SESSION_TIMEOUT,
    'path' => '/',
    'domain' => 'palians.com',
    'secure' => true,  // HTTPS only
    'httponly' => true,  // No JavaScript access
    'samesite' => 'Strict'  // CSRF protection (PHP 8.2)
]);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check session timeout
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > SESSION_TIMEOUT)) {
    session_destroy();
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

// Update session timestamp
if (isset($_SESSION['user_id']) || isset($_SESSION['admin_name'])) {
    $_SESSION['login_time'] = time();
}
?>
