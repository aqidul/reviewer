<?php
session_start();
require_once __DIR__ . "/includes/config.php";

if (isset($_SESSION["admin_logged_in_as_user"]) && $_SESSION["admin_logged_in_as_user"] === true) {
    $admin_name = $_SESSION["original_admin_name"] ?? null;
    $admin_login_time = $_SESSION["original_admin_login_time"] ?? time();
    
    unset($_SESSION["user_id"], $_SESSION["user_name"], $_SESSION["user_email"], $_SESSION["user_mobile"]);
    unset($_SESSION["admin_logged_in_as_user"], $_SESSION["original_admin_name"], $_SESSION["original_admin_login_time"]);
    
    if ($admin_name) {
        $_SESSION["admin_name"] = $admin_name;
        $_SESSION["admin_login_time"] = $admin_login_time;
        header("Location: " . ADMIN_URL . "/dashboard.php");
        exit;
    }
}
header("Location: " . APP_URL . "/index.php");
exit;
