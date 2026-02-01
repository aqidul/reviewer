<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

echo "Step 1: Starting...<br>";

session_start();
echo "Step 2: Session started<br>";

echo "Step 3: Including config...<br>";
require_once __DIR__ . "/../includes/config.php";
echo "Step 4: Config loaded<br>";

echo "Step 5: Checking database variables...<br>";
echo "conn exists: " . (isset($pdo) ? "YES" : "NO") . "<br>";
echo "db exists: " . (isset($db) ? "YES" : "NO") . "<br>";
echo "pdo exists: " . (isset($pdo) ? "YES" : "NO") . "<br>";
echo "mysqli exists: " . (isset($mysqli) ? "YES" : "NO") . "<br>";

echo "Step 6: All defined variables:<br>";
$vars = get_defined_vars();
foreach ($vars as $name => $value) {
    if (is_object($value)) {
        echo "- \$$name (object: " . get_class($value) . ")<br>";
    }
}

echo "<br>Test completed!";
