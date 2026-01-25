<?php
/**
 * Common Helper Functions - SINGLE SOURCE OF TRUTH
 * All reusable functions are defined here
 *
 * NOTE: This file must be included after includes/config.php so that
 * INCLUDES_PATH and BASE_URL and $pdo are available.
 */

// Prevent direct access
if (!defined('INCLUDES_PATH')) {
    die('Direct access not permitted');
}

/**
 * Sanitize user input
 */
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map('sanitizeInput', $input);
        }

        $input = trim($input);
        // Remove slashes added by magic quotes if enabled (rare)
        if (get_magic_quotes_gpc()) {
            $input = stripslashes($input);
        }
        // Convert special characters to HTML entities to avoid XSS
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

/**
 * Redirect to another page.
 * Accepts absolute URLs, root-relative paths (/path), or app-relative paths (path.php).
 * Converts app-relative paths to BASE_URL-based absolute URLs.
 */
if (!function_exists('redirect')) {
    function redirect($url) {
        // If already an absolute URL, use it directly
        if (preg_match('#^https?://#i', $url)) {
            $target = $url;
        } elseif (strpos($url, '/') === 0) {
            // root-relative
            $target = rtrim(BASE_URL, '/') . $url;
        } else {
            // app relative
            $target = rtrim(BASE_URL, '/') . '/' . ltrim($url, '/');
        }

        if (!headers_sent()) {
            header("Location: $target");
            exit();
        } else {
            echo '<script>window.location.href="' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '";</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '"></noscript>';
            exit();
        }
    }
}

/**
 * Check if user is logged in
 */
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

/**
 * Check if user is admin
 */
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
    }
}

/**
 * Check if user is regular user
 */
if (!function_exists('isUser')) {
    function isUser() {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'user';
    }
}

/**
 * Require user to be logged in (regular user)
 */
if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (!isLoggedIn() || isAdmin()) {
            redirect('index.php');
        }
    }
}

/**
 * Require admin privileges
 */
if (!function_exists('requireAdmin')) {
    function requireAdmin() {
        if (!isLoggedIn() || !isAdmin()) {
            redirect('admin/index.php');
        }
    }
}

/**
 * Format date/time string
 */
if (!function_exists('formatDate')) {
    function formatDate($datetime, $format = 'd-m-Y') {
        if (empty($datetime)) {
            return '';
        }
        $ts = strtotime($datetime);
        if ($ts === false) {
            return $datetime;
        }
        return date($format, $ts);
    }
}

/**
 * Return a bootstrap badge for a task/status
 */
if (!function_exists('statusBadge')) {
    function statusBadge($status) {
        $status_text = ucfirst(str_replace('_', ' ', $status));
        $color = 'secondary';

        switch ($status) {
            case 'assigned': $color = 'danger'; break;
            case 'step1_completed': $color = 'warning'; break;
            case 'step2_completed': $color = 'info'; break;
            case 'step3_completed': $color = 'primary'; break;
            case 'refund_requested': $color = 'success'; $status_text = 'Refund Requested'; break;
            case 'completed': $color = 'success'; $status_text = 'Refunded'; break;
        }

        return '<span class="badge bg-' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($status_text, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

/**
 * Simple email validator wrapper
 */
if (!function_exists('validateEmail')) {
    function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

/**
 * Simple URL validator wrapper
 */
if (!function_exists('validateURL')) {
    function validateURL($url) {
        return filter_var($url, FILTER_VALIDATE_URL);
    }
}

/**
 * Get current full URL
 */
if (!function_exists('getCurrentURL')) {
    function getCurrentURL() {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $request = $_SERVER['REQUEST_URI'] ?? '/';
        return $scheme . '://' . $host . $request;
    }
}

/**
 * Log activity into activity_logs table if $pdo is available.
 */
if (!function_exists('logActivity')) {
    function logActivity($action, $details = '') {
        global $pdo;
        try {
            if (isset($pdo) && $pdo instanceof PDO) {
                $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) VALUES (:user_id, :action, :details, :ip, :ua, NOW())");
                $stmt->execute([
                    ':user_id' => $_SESSION['user_id'] ?? null,
                    ':action' => $action,
                    ':details' => $details,
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                    ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? null
                ]);
            }
        } catch (Exception $e) {
            // Don't break the app due to logging failures
            error_log("logActivity error: " . $e->getMessage());
        }
    }
}
?>
