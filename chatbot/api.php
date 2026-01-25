<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

header('Content-Type: application/json');

$user_message = sanitizeInput($_POST['message'] ?? '');
$user_id = $_SESSION['user_id'] ?? null;

if (empty($user_message)) {
    sendJSON(['success' => false, 'message' => 'Message is required'], 400);
}

try {
    // Search FAQs for matching answer
    $stmt = $pdo->prepare("
        SELECT id, question, answer FROM chatbot_faq 
        WHERE is_active = true 
        AND (question LIKE :search OR answer LIKE :search)
        LIMIT 1
    ");
    
    $search_term = '%' . $user_message . '%';
    $stmt->execute([':search' => $search_term]);
    $faq = $stmt->fetch();
    
    if ($faq) {
        $bot_response = $faq['answer'];
        $faq_id = $faq['id'];
    } else {
        $bot_response = "I understand your question, but I don't have a specific answer in my database. Please try asking about our task system, payment, account, or technical support. Or contact our admin for more help!";
        $faq_id = null;
    }
    
    // Log conversation
    if ($user_id) {
        $stmt = $pdo->prepare("
            INSERT INTO chatbot_conversations (user_id, user_message, bot_response, faq_id)
            VALUES (:user_id, :user_message, :bot_response, :faq_id)
        ");
        
        $stmt->execute([
            ':user_id' => $user_id,
            ':user_message' => $user_message,
            ':bot_response' => $bot_response,
            ':faq_id' => $faq_id
        ]);
    }
    
    sendJSON([
        'success' => true,
        'response' => $bot_response,
        'faq_found' => $faq ? true : false
    ]);
    
} catch (PDOException $e)
