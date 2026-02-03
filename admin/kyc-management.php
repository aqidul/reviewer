<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

$admin_path = rtrim(parse_url(ADMIN_URL, PHP_URL_PATH) ?: '/reviewer/admin', '/');

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . $admin_path . '/index.php');
    exit;
}

header('Location: ' . $admin_path . '/kyc-verification.php');
exit;
