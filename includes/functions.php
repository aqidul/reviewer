<?php
/**
 * Common Helper Functions - SINGLE SOURCE OF TRUTH
 * All reusable functions are defined here
 */

// Prevent direct access
if (!defined('INCLUDES_PATH')) {
    die('Direct access not permitted');
}

/**
 * Check if function already exists before declaring
 */
if (!function_exists('sanitizeInput')) {
    /**
     * Sanitize user input
     */
    function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map('sanitizeInput', $input);
        }
        
        $input = trim($input);
        $input = stripslashes($input);
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $input;
    }
}

if (!function_exists('redirect')) {
    /**
     * Redirect to another page
     */
    function redirect($url) {
        if (!headers_sent()) {
            header("Location: $url");
            exit();
        } else {
            echo '<script>window.location.href="' . $url . '";</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . $url . '"></noscript>';
            exit();
        }
    }
}

if (!function_exists('isLoggedIn')) {
    /**
     * Check if user is logged in
     */
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

if (!function_exists('isAdmin')) {
    /**
     * Check if user is admin
     */
    function isAdmin() {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
    }
}

if (!function_exists('isUser')) {
    /**
     * Check if user is regular user
     */
    function isUser() {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'user';
    }
}

if (!function_exists('requireLogin')) {
    /**
     * Require user to be logged in
     */
    function requireLogin() {
        if (!isLoggedIn()) {
            redirect('/login.php');
        }
    }
}

if (!function_exists('requireAdmin')) {
    /**
     * Require user to be admin
     */
    function requireAdmin() {
        requireLogin();
        if (!isAdmin()) {
            redirect('/index.php');
        }
    }
}

if (!function_exists('requireUser')) {
    /**
     * Require user to be regular user
     */
    function requireUser() {
        requireLogin();
        if (!isUser()) {
            redirect('/index.php');
        }
    }
}

if (!function_exists('formatDate')) {
    /**
     * Format date for display
     */
    function formatDate($date, $format = 'd-m-Y') {
        if (empty($date) || $date == '0000-00-00') {
            return '';
        }
        return date($format, strtotime($date));
    }
}

if (!function_exists('getStatusBadge')) {
    /**
     * Get task status badge HTML
     */
    function getStatusBadge($status) {
        $status_text = ucfirst(str_replace('_', ' ', $status));
        $color = 'secondary';
        
        switch ($status) {
            case 'assigned': $color = 'danger'; break;
            case 'step1_completed': $color = 'warning'; break;
            case 'step2_completed': $color = 'info'; break;
            case 'step3_completed': $color = 'primary'; break;
            case 'refund_requested': $color = 'success'; break;
            case 'completed': $color = 'success'; $status_text = 'Refunded'; break;
        }
        
        return '<span class="badge bg-' . $color . '">' . $status_text . '</span>';
    }
}

if (!function_exists('validateEmail')) {
    /**
     * Validate email
     */
    function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('validateURL')) {
    /**
     * Validate URL
     */
    function validateURL($url) {
        return filter_var($url, FILTER_VALIDATE_URL);
    }
}

if (!function_exists('getCurrentURL')) {
    /**
     * Get current URL
     */
    function getCurrentURL() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        return $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
}

if (!function_exists('logActivity')) {
    /**
     * Log activity
     */
    function logActivity($action, $details = '') {
        global $pdo;
        
        if (!isset($pdo)) {
            return false;
        }
        
        try {
            $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) 
                                  VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$user_id, $action, $details, $ip_address]);
            return true;
        } catch (PDOException $e) {
            error_log("Failed to log activity: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('checkDeadline')) {
    /**
     * Check if deadline is approaching or passed
     */
    function checkDeadline($deadline) {
        $deadline_time = strtotime($deadline);
        $current_time = time();
        $days_left = floor(($deadline_time - $current_time) / (60 * 60 * 24));
        
        if ($days_left < 0) {
            return ['status' => 'overdue', 'days' => abs($days_left)];
        } elseif ($days_left == 0) {
            return ['status' => 'today', 'days' => 0];
        } elseif ($days_left <= 3) {
            return ['status' => 'urgent', 'days' => $days_left];
        } else {
            return ['status' => 'normal', 'days' => $days_left];
        }
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Generate CSRF token
     */
    function csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verify_csrf')) {
    /**
     * Verify CSRF token
     */
    function verify_csrf($token) {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
?>
