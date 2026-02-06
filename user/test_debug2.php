<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Loading config...<br>";
require_once __DIR__ . '/../includes/config.php';
echo "Config loaded<br>";

echo "Loading security...<br>";
require_once __DIR__ . '/../includes/security.php';
echo "Security loaded<br>";

echo "Loading functions...<br>";
require_once __DIR__ . '/../includes/functions.php';
echo "Functions loaded<br>";

echo "Checking if createNotification function exists: " . (function_exists('createNotification') ? 'YES' : 'NO') . "<br>";

echo "Loading gamification line by line...<br>";
$file = __DIR__ . '/../includes/gamification-functions.php';
echo "File exists: " . (file_exists($file) ? 'YES' : 'NO') . "<br>";

try {
    require_once $file;
    echo "Gamification loaded successfully!<br>";
} catch (Error $e) {
    echo "Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}
?>
