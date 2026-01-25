<?php
declare(strict_types=1);

session_start();

// Store redirect path before destroying session
$is_admin = isset($_SESSION['admin_name']);

// Destroy session securely
$_SESSION = [];

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        name: session_name(),
        value: '',
        expires_or_options: time() - 42000,
        path: $params["path"] ?? '/',
        domain: $params["domain"] ?? '',
        secure: true,
        httponly: true
    );
}

session_destroy();

// Redirect to home/login
require_once __DIR__ . '/includes/config.php';
redirectTo(APP_URL);
?>
