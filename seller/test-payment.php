<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

echo "Step 1: Starting...<br>";

session_start();
echo "Step 2: Session started<br>";

$_SESSION["seller_id"] = 1;
echo "Step 3: Seller ID set<br>";

echo "Step 4: Including config...<br>";
require_once __DIR__ . "/../includes/config.php";
echo "Step 5: Config loaded<br>";

echo "Step 6: Testing database...<br>";
try {
    $test = $pdo->query("SELECT 1");
    echo "Step 7: Database OK<br>";
} catch (Exception $e) {
    echo "Database Error: " . $e->getMessage() . "<br>";
}

echo "Step 8: Testing autoload...<br>";
if (file_exists(__DIR__ . "/../vendor/autoload.php")) {
    require_once __DIR__ . "/../vendor/autoload.php";
    echo "Step 9: Autoload loaded<br>";
} else {
    echo "Step 9: No autoload file<br>";
}

echo "Step 10: Testing Razorpay...<br>";
try {
    $api = new Razorpay\Api\Api("test_key", "test_secret");
    echo "Step 11: Razorpay class loaded<br>";
} catch (Exception $e) {
    echo "Razorpay Error: " . $e->getMessage() . "<br>";
}

echo "<br>All tests completed!";
