<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: /reviewer/admin/index.php');
    exit;
}

header('Location: kyc-verification.php');
exit;
