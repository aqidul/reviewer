<?php
/**
 * Simple test script for chatbot functionality
 * Usage: php test_chatbot.php
 */

// Simulate the chatbot environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [];

// Create a mock input
$testMessage = json_encode([
    'message' => 'How do I request reviews?',
    'userType' => 'seller',
    'userId' => 1
]);

// Capture the output
ob_start();

// Set php://input for testing
$GLOBALS['HTTP_RAW_POST_DATA'] = $testMessage;
stream_wrapper_unregister("php");
stream_wrapper_register("php", "MockPhpStream");

class MockPhpStream {
    public $position;
    public $data;

    public function stream_open($path, $mode, $options, &$opened_path) {
        $this->position = 0;
        $this->data = $GLOBALS['HTTP_RAW_POST_DATA'];
        return true;
    }

    public function stream_read($count) {
        $ret = substr($this->data, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }

    public function stream_eof() {
        return $this->position >= strlen($this->data);
    }

    public function stream_stat() {
        return [];
    }
}

// Start a session
session_start();
$_SESSION['seller_id'] = 1;
$_SESSION['seller_name'] = 'Test Seller';

// Include the chatbot processor
require_once __DIR__ . '/chatbot/process.php';

// Get the output
$output = ob_get_clean();

// Restore php stream wrapper
stream_wrapper_restore("php");

// Display results
echo "=== Chatbot Test Results ===\n\n";
echo "Input:\n";
echo $testMessage . "\n\n";
echo "Output:\n";
echo $output . "\n\n";

// Parse and validate JSON
$result = json_decode($output, true);
if ($result) {
    echo "JSON Valid: YES\n";
    echo "Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
    if (isset($result['response'])) {
        echo "Response: " . substr($result['response'], 0, 100) . "...\n";
    } else if (isset($result['error'])) {
        echo "Error: " . $result['error'] . "\n";
    }
} else {
    echo "JSON Valid: NO\n";
    echo "JSON Error: " . json_last_error_msg() . "\n";
}

echo "\n=== Test Complete ===\n";
?>
