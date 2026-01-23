<?php
/**
 * Logout Handler
 */

require_once 'includes/config.php';
require_once 'includes/functions.php';

// Destroy session
session_destroy();

// Clear session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Clear any other custom cookies
setcookie('dark_mode', '', time() - 3600, '/');

// Redirect to home page
redirect('index.php');
