<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

$admin_url = rtrim(ADMIN_URL, '/');

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . $admin_url . '/index.php');
    exit;
}

header('Location: ' . $admin_url . '/kyc-verification.php');
exit;
