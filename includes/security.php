<?php
declare(strict_types=1);

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access not allowed');
}

/**
 * XSS Protection: Escape HTML output
 * PHP 8.2: Type declarations
 */
function escape(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Input Sanitization
 * PHP 8.2: Type declarations and null coalescing
 */
function sanitizeInput(string|array $input): string|array
{
    if (is_array($input)) {
        return array_map(fn($item) => sanitizeInput($item), $input);
    }
    return trim(strip_tags($input));
}

/**
 * Validate Email
 */
function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate Mobile Number (Indian format)
 */
function validateMobile(string $mobile): bool
{
    return preg_match('/^[6-9]\d{9}$/', $mobile) === 1;
}

/**
 * Hash Password with PHP 8.2 best practices
 */
function hashPassword(string $password): string
{
    return password_hash(
        password: $password,
        algo: PASSWORD_HASH_ALGO,
        options: PASSWORD_HASH_OPTIONS
    );
}

/**
 * Verify Password
 */
function verifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/**
 * Check if password needs rehash (PHP 8.2)
 */
function needsRehash(string $hash): bool
{
    return password_needs_rehash(
        hash: $hash,
        algo: PASSWORD_HASH_ALGO,
        options: PASSWORD_HASH_OPTIONS
    );
}

/**
 * Generate CSRF Token using random_bytes (secure)
 */
function generateCSRFToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token with timing attack prevention
 */
function verifyCSRFToken(string $token): bool
{
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate Random String (PHP 8.2)
 */
function generateRandomString(int $length = 32): string
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Validate File Upload with enhanced security
 */
function validateFileUpload(array $file): array
{
    // Check if file was uploaded
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return [
            'success' => false,
            'message' => 'File upload error: ' . ($file['error'] ?? 'Unknown')
        ];
    }
    
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return [
            'success' => false,
            'message' => sprintf('File size exceeds limit of %.2f MB', MAX_FILE_SIZE / (1024 * 1024))
        ];
    }
    
    // Check file extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
        return [
            'success' => false,
            'message' => 'Invalid file type. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS)
        ];
    }
    
    // Check MIME type with finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']) ?: 'unknown';
    finfo_close($finfo);
    
    $allowed_mimes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf'
    ];
    
    if (!in_array($mime, $allowed_mimes, true)) {
        return [
            'success' => false,
            'message' => "Invalid MIME type: $mime"
        ];
    }
    
    return ['success' => true];
}

/**
 * Upload File Securely with PHP 8.2
 */
function uploadFile(array $file, string $folder = 'general'): array
{
    global $pdo;
    
    // Validate upload
    $validation = validateFileUpload($file);
    if (!$validation['success']) {
        return $validation;
    }
    
    // Generate unique filename with timestamp
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = sprintf(
        'file_%s_%s.%s',
        time(),
        uniqid(),
        $ext
    );
    
    $filepath = UPLOAD_DIR . $folder . '/';
    
    // Create folder if not exists
    if (!is_dir($filepath)) {
        @mkdir($filepath, 0755, true);
    }
    
    $fullpath = $filepath . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $fullpath)) {
        error_log("File upload failed: $filename");
        return [
            'success' => false,
            'message' => 'Failed to save uploaded file'
        ];
    }
    
    // Set proper permissions
    chmod($fullpath, 0644);
    
    // Return relative URL
    $fileurl = '/reviewer/uploads/' . $folder . '/' . $filename;
    
    return [
        'success' => true,
        'filename' => $filename,
        'url' => $fileurl
    ];
}

/**
 * Log Activity with prepared statements
 */
function logActivity(
    string $action,
    ?int $task_id = null,
    ?int $user_id = null,
    ?string $oldValue = null,
    ?string $newValue = null
): bool {
    global $pdo;
    
    $admin_name = $_SESSION['admin_name'] ?? 'System';
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_logs (admin_name, action, task_id, user_id, old_value, new_value)
            VALUES (:admin_name, :action, :task_id, :user_id, :old_value, :new_value)
        ");
        
        $stmt->execute([
            ':admin_name' => $admin_name,
            ':action' => $action,
            ':task_id' => $task_id,
            ':user_id' => $user_id,
            ':old_value' => $oldValue,
            ':new_value' => $newValue
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log('Activity log error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Send JSON Response with proper headers (PHP 8.2)
 */
function sendJSON(array $data, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    
    echo json_encode(
        value: $data,
        flags: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
    
    exit;
}

/**
 * Redirect with safe headers
 */
function redirectTo(string $url): never
{
    // Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        exit('Invalid redirect URL');
    }
    
    header('Location: ' . $url);
    exit;
}

/**
 * Rate Limiting Helper
 */
function checkRateLimit(string $identifier, int $limit = 5, int $window = 60): bool
{
    $key = "rate_limit:$identifier";
    $current = $_SESSION[$key] ?? 0;
    
    if ($current >= $limit) {
        return false;
    }
    
    $_SESSION[$key] = $current + 1;
    
    // Reset counter after window
    if (!isset($_SESSION["{$key}_time"])) {
        $_SESSION["{$key}_time"] = time();
    } elseif (time() - $_SESSION["{$key}_time"] > $window) {
        $_SESSION[$key] = 1;
        $_SESSION["{$key}_time"] = time();
    }
    
    return true;
}
?>
