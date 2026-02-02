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
        
        $sql = "SELECT answer FROM chatbot_faq WHERE is_active = 1 AND (" . implode(' OR ', $conditions) . ") ORDER BY id DESC LIMIT 1";
        
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
        // Review requests
        if (strpos($message, 'review') !== false || strpos($message, 'request') !== false) {
            return "**How to Request Reviews:**\n\n" .
                   "1. Click 'New Request' in the sidebar\n" .
                   "2. Enter product details:\n" .
                   "   • Product link (Amazon/Flipkart)\n" .
                   "   • Product name and brand\n" .
                   "   • Product price\n" .
                   "   • Number of reviews needed\n" .
                   "3. Review the cost calculation\n" .
                   "4. Make payment securely\n" .
                   "5. Wait for admin approval\n\n" .
                   "Once approved, reviewers will be assigned to your product automatically!";
        }
        
        // Wallet and recharge
        if (strpos($message, 'wallet') !== false || strpos($message, 'recharge') !== false || strpos($message, 'balance') !== false) {
            return "**Wallet & Recharge Guide:**\n\n" .
                   "To recharge your wallet:\n" .
                   "1. Go to 'Wallet' in the sidebar\n" .
                   "2. Click 'Recharge Wallet' button\n" .
                   "3. Enter the amount you want to add\n" .
                   "4. Choose payment method\n" .
                   "5. Complete the payment\n\n" .
                   "Your wallet balance will be updated instantly!\n\n" .
                   "You can also add money during checkout when creating a new review request.";
        }
        
        // Invoices
        if (strpos($message, 'invoice') !== false || strpos($message, 'bill') !== false || strpos($message, 'receipt') !== false) {
            return "**View & Download Invoices:**\n\n" .
                   "1. Go to 'Invoices' in the sidebar\n" .
                   "2. You'll see all your invoices listed\n" .
                   "3. Click 'View' to see invoice details\n" .
                   "4. Click 'Download' to save PDF\n\n" .
                   "Invoices include:\n" .
                   "• Order details\n" .
                   "• GST breakdown (18%)\n" .
                   "• Payment information\n" .
                   "• SAC code for services\n\n" .
                   "Invoices are generated automatically after payment.";
        }
        
        // Payment and pricing
        if (strpos($message, 'payment') !== false || strpos($message, 'pay') !== false || strpos($message, 'cost') !== false || strpos($message, 'price') !== false) {
            return "**Payment & Pricing:**\n\n" .
                   "Review pricing:\n" .
                   "• ₹50 per review (base commission)\n" .
                   "• Plus 18% GST\n" .
                   "• Example: 10 reviews = ₹500 + ₹90 GST = ₹590\n\n" .
                   "Payment methods:\n" .
                   "• Razorpay (UPI, Cards, Net Banking)\n" .
                   "• Wallet balance\n\n" .
                   "All payments are secure and encrypted!";
        }
        
        // Order status and tracking
        if (strpos($message, 'order') !== false || strpos($message, 'status') !== false || strpos($message, 'track') !== false) {
            return "**Track Your Orders:**\n\n" .
                   "1. Go to 'Orders' in the sidebar\n" .
                   "2. See all your review requests\n" .
                   "3. Filter by status:\n" .
                   "   • Pending - Awaiting admin approval\n" .
                   "   • Approved - In progress\n" .
                   "   • Completed - All reviews done\n" .
                   "   • Rejected - See reason in details\n\n" .
                   "Click 'View' on any order to see:\n" .
                   "• Product details\n" .
                   "• Review progress\n" .
                   "• Payment status\n" .
                   "• Timeline";
        }
        
        // Getting started / help
        if (strpos($message, 'start') !== false || strpos($message, 'begin') !== false || strpos($message, 'first') !== false || strpos($message, 'new') !== false) {
            return "**Getting Started as a Seller:**\n\n" .
                   "Welcome! Here's how to get reviews for your products:\n\n" .
                   "1. **Create a Review Request**\n" .
                   "   • Click 'New Request'\n" .
                   "   • Enter your product details\n" .
                   "   • Choose number of reviews\n\n" .
                   "2. **Make Payment**\n" .
                   "   • Review the cost\n" .
                   "   • Pay securely via Razorpay\n\n" .
                   "3. **Wait for Approval**\n" .
                   "   • Admin reviews your request (usually within 24 hours)\n\n" .
                   "4. **Track Progress**\n" .
                   "   • Monitor reviews in 'Orders'\n" .
                   "   • Get notifications on completion\n\n" .
                   "Need help? Contact support anytime!";
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
    
    // Generic helpful response - dynamic based on user type
    $topics = [];
    if ($userType === 'seller') {
        $topics = [
            '• How to request reviews',
            '• Wallet and recharges',
            '• Order status and tracking',
            '• Payment and pricing',
            '• Invoices and receipts',
            '• Getting started guide'
        ];
    } elseif ($userType === 'admin') {
        $topics = [
            '• How to approve requests',
            '• Assign tasks to users',
            '• Export data',
            '• Manage settings',
            '• View reports'
        ];
    } elseif ($userType === 'user') {
        $topics = [
            '• How to complete tasks',
            '• Withdrawals and payments',
            '• Referral program',
            '• Account management'
        ];
    } else {
        $topics = [
            '• How to register',
            '• Platform features',
            '• Getting started'
        ];
    }
    
    return "Thank you for your message! I'm here to help. Could you please be more specific about what you need assistance with?\n\n" .
           "**Common topics I can help with:**\n" .
           implode("\n", $topics) . "\n\n" .
           "Just ask me anything about these topics, or contact our support team for personalized assistance!";
}
?>
