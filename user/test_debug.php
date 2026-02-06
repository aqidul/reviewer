<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Step 1: PHP is working<br>";

echo "Step 2: Loading config...<br>";
require_once __DIR__ . '/../includes/config.php';
echo "Step 3: Config loaded<br>";

echo "Step 4: Loading security...<br>";
require_once __DIR__ . '/../includes/security.php';
echo "Step 5: Security loaded<br>";

echo "Step 6: Loading functions...<br>";
require_once __DIR__ . '/../includes/functions.php';
echo "Step 7: Functions loaded<br>";

echo "Step 8: Loading gamification...<br>";
require_once __DIR__ . '/../includes/gamification-functions.php';
echo "Step 9: Gamification loaded<br>";

echo "Step 10: Checking session...<br>";
echo "Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";

echo "All includes loaded successfully!";
?>
