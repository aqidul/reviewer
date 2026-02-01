<?php
/**
 * Chatbot Message Processing Endpoint - Version 2.0
 * Handles chatbot messages from the widget
 */

session_start();
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

// Get input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['message'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$message = trim($data['message']);
$userType = $data['userType'] ?? 'guest';
$userId = intval($data['userId'] ?? 0);

if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
    exit;
}

try {
    // Log the question to chatbot_unanswered table
    $stmt = $pdo->prepare("
        INSERT INTO chatbot_unanswered (question, user_type, user_id, is_resolved, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ");
    $stmt->execute([$message, $userType, $userId > 0 ? $userId : null]);
    
    // Try to find answer in FAQ
    $response = findFAQAnswer($message, $pdo);
    
    if ($response) {
        // Mark as resolved if answer found
        $lastId = $pdo->lastInsertId();
        $stmt = $pdo->prepare("UPDATE chatbot_unanswered SET is_resolved = 1 WHERE id = ?");
        $stmt->execute([$lastId]);
    } else {
        // Generate contextual response based on user type
        $response = generateContextualResponse($message, $userType);
    }
    
    echo json_encode([
        'success' => true,
        'response' => $response
    ]);
    
} catch (PDOException $e) {
    error_log('Chatbot error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to process message'
    ]);
}

/**
 * Find answer in FAQ database
 */
function findFAQAnswer($question, $pdo) {
    try {
        // Simple keyword matching
        $keywords = extractKeywords($question);
        
        if (empty($keywords)) {
            return null;
        }
        
        // Build LIKE query for each keyword
        $conditions = [];
        $params = [];
        foreach ($keywords as $keyword) {
            $conditions[] = "(question LIKE ? OR answer LIKE ?)";
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
        }
        
        $sql = "SELECT answer FROM faq WHERE is_active = 1 AND (" . implode(' OR ', $conditions) . ") ORDER BY id DESC LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchColumn();
        
        return $result ?: null;
        
    } catch (PDOException $e) {
        error_log('FAQ search error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Extract keywords from question
 */
function extractKeywords($text) {
    $text = strtolower($text);
    $stopWords = ['how', 'do', 'i', 'the', 'a', 'an', 'to', 'is', 'can', 'what', 'where', 'when'];
    
    $words = preg_split('/\s+/', $text);
    $keywords = array_filter($words, function($word) use ($stopWords) {
        return strlen($word) > 3 && !in_array($word, $stopWords);
    });
    
    return array_values($keywords);
}

/**
 * Generate contextual response based on user type
 */
function generateContextualResponse($message, $userType) {
    $message = strtolower($message);
    
    // Admin responses
    if ($userType === 'admin') {
        if (strpos($message, 'approve') !== false || strpos($message, 'request') !== false) {
            return "To approve review requests:\n1. Go to 'Review Requests' in the sidebar\n2. Click on a pending request\n3. Review the details\n4. Click 'Approve' button\n\nYou can also approve wallet recharge requests from the 'Wallet Requests' page.";
        }
        if (strpos($message, 'assign') !== false || strpos($message, 'task') !== false) {
            return "To assign tasks:\n1. Go to 'Assign Task' in the sidebar\n2. Select users from the list\n3. Enter product link and commission\n4. Click 'Assign Task'\n\nYou can assign tasks to multiple users at once.";
        }
        if (strpos($message, 'export') !== false || strpos($message, 'data') !== false) {
            return "To export data:\n1. Go to 'Export Data' in the sidebar\n2. Select a brand from the dropdown\n3. Choose date range (optional)\n4. Click 'Export to CSV'\n\nThe file will download automatically with all review data.";
        }
    }
    
    // Seller responses
    if ($userType === 'seller') {
        if (strpos($message, 'review') !== false || strpos($message, 'request') !== false) {
            return "To request reviews:\n1. Go to 'New Request' in the sidebar\n2. Fill in product details (name, link, platform)\n3. Enter number of reviews needed\n4. Make payment\n5. Admin will approve your request\n\nOnce approved, reviewers will be assigned to your product.";
        }
        if (strpos($message, 'wallet') !== false || strpos($message, 'recharge') !== false) {
            return "To recharge your wallet:\n1. Go to 'Wallet' in the sidebar\n2. Click 'Recharge Wallet'\n3. Enter amount and transfer details\n4. Upload bank transfer screenshot\n5. Submit request\n\nAdmin will verify and credit your wallet within 24 hours.";
        }
        if (strpos($message, 'invoice') !== false) {
            return "To view invoices:\n1. Go to 'Invoices' in the sidebar\n2. All your invoices are listed there\n3. Click 'View' to see invoice details\n4. Click 'Download' to get PDF\n\nInvoices are generated after each successful payment.";
        }
    }
    
    // User/Reviewer responses
    if ($userType === 'user') {
        if (strpos($message, 'task') !== false || strpos($message, 'complete') !== false) {
            return "To complete a task:\n1. Check 'My Tasks' on dashboard\n2. Click on a pending task\n3. Follow the 4 steps:\n   - Place order\n   - Confirm delivery\n   - Submit review\n   - Request refund\n4. Upload required screenshots\n\nYou'll earn commission after admin approval.";
        }
        if (strpos($message, 'withdraw') !== false || strpos($message, 'money') !== false) {
            return "To withdraw money:\n1. Go to 'Wallet' page\n2. Click 'Withdraw'\n3. Enter amount (min ₹100)\n4. Provide bank/UPI details\n5. Submit request\n\nAdmin will process within 24-48 hours. Check your tier limits.";
        }
        if (strpos($message, 'refer') !== false || strpos($message, 'friend') !== false) {
            return "To refer friends:\n1. Go to 'Referrals' page\n2. Copy your unique referral link\n3. Share with friends\n4. Earn ₹50 when they complete their first task\n\nYou can track all your referrals on the referrals page.";
        }
    }
    
    // Generic helpful response
    return "Thank you for your message! I'm here to help. Could you please be more specific about what you need assistance with?\n\nCommon topics:\n• How to complete tasks\n• Payment and withdrawals\n• Account issues\n• Technical problems\n\nYou can also contact our support team for personalized assistance.";
}
?>
