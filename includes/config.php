<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Debug mode (set to false in production)
const DEBUG = false;

// Database Configuration
const DB_HOST = 'localhost';
const DB_USER = 'reviewflow_user';
const DB_PASS = 'Malik@241123';
const DB_NAME = 'reviewflow';
const DB_CHARSET = 'utf8mb4';

// Application Settings
const APP_NAME = 'ReviewFlow';
const APP_URL = 'https://palians.com/reviewer';
const ADMIN_URL = 'https://palians.com/reviewer/admin';
const SELLER_URL = 'https://palians.com/reviewer/seller';
const APP_VERSION = '2.0.0';

// Security Settings
const SESSION_TIMEOUT = 3600;
const PASSWORD_HASH_ALGO = PASSWORD_BCRYPT;
const PASSWORD_HASH_OPTIONS = ['cost' => 12];

// File Upload Settings
const UPLOAD_DIR = __DIR__ . '/../uploads/';
const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
const MAX_FILE_SIZE = 5 * 1024 * 1024;

// Wallet Settings
const MIN_WITHDRAWAL = 100;
const REFERRAL_BONUS = 50;
const FIRST_TASK_BONUS = 25;
const DEFAULT_ADMIN_COMMISSION_PER_REVIEW = 5;

// Task Steps Configuration
const TASK_STEPS = ['Order Placed', 'Delivery Received', 'Review Submitted', 'Refund Requested'];

// WhatsApp Settings
const WHATSAPP_API_URL = 'https://api.whatsapp.com/send';
const WHATSAPP_SUPPORT = '919876543210';

// Email Settings
const SMTP_HOST = 'smtp.gmail.com';
const SMTP_PORT = 587;
const SMTP_USER = 'your-email@gmail.com';
const SMTP_PASS = 'your-app-password';
const SMTP_FROM = 'noreply@palians.com';
const SMTP_FROM_NAME = 'ReviewFlow';

// Payment Gateway Settings (Override with database settings)
const RAZORPAY_KEY_ID = '';
const RAZORPAY_KEY_SECRET = '';
const PAYUMONEY_MERCHANT_KEY = '';
const PAYUMONEY_MERCHANT_SALT = '';

// GST Settings
const GST_RATE = 18;
const SAC_CODE = '998371';

// Create directories
$dirs = [
    UPLOAD_DIR, 
    UPLOAD_DIR . 'qr/', 
    UPLOAD_DIR . 'profiles/', 
    UPLOAD_DIR . 'invoices/',
    __DIR__ . '/../logs'
];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
}

ini_set('error_log', __DIR__ . '/../logs/error.log');

// PDO Connection
try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]);
} catch (PDOException $e) {
    error_log('Database Connection Failed: ' . $e->getMessage());
    http_response_code(500);
    die('Database connection error.');
}

// Error Handler
set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline): bool {
    error_log("[$errno] $errstr in $errfile:$errline");
    return true;
});

// Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path' => '/',
        'domain' => 'palians.com',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Session timeout check
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > SESSION_TIMEOUT)) {
    session_destroy();
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

if (isset($_SESSION['user_id']) || isset($_SESSION['admin_name'])) {
    $_SESSION['login_time'] = time();
}

// Helper function to get settings
function getSetting(string $key, $default = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : $default;
    } catch (PDOException $e) {
        return $default;
    }
}
?>
