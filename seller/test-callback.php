<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

echo "Step 1: Starting...<br>";

session_start();
$_SESSION["seller_id"] = 1;
echo "Step 2: Session set<br>";

require_once __DIR__ . "/../includes/config.php";
echo "Step 3: Config loaded, pdo exists: " . (isset($pdo) ? "YES" : "NO") . "<br>";

// Test the actual query from payment-callback
echo "Step 4: Testing review_requests query...<br>";
try {
    global $pdo;
    $request_id = 3;
    $seller_id = 1;
    
    $stmt = $pdo->prepare("SELECT * FROM review_requests WHERE id = ? AND seller_id = ?");
    $stmt->execute([$request_id, $seller_id]);
    $request = $stmt->fetch();
    
    if ($request) {
        echo "Step 5: Request found - ID: " . $request["id"] . "<br>";
        print_r($request);
    } else {
        echo "Step 5: No request found for ID=$request_id, seller_id=$seller_id<br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

echo "<br>Test completed!";
