<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

$admin_base = parse_url(ADMIN_URL, PHP_URL_PATH);
if (!$admin_base) {
    $app_base = rtrim(parse_url(APP_URL, PHP_URL_PATH) ?: '', '/');
    $admin_base = $app_base . '/admin';
}
$admin_path = rtrim($admin_base, '/');

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . $admin_path . '/index.php');
    exit;
}

header('Location: ' . $admin_path . '/kyc-verification.php');
exit;
