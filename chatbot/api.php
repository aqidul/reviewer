<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

header('Content-Type: application/json');

$user_message = trim($_POST['message'] ?? '');
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'Guest';

if (empty($user_message)) {
    echo json_encode(['success' => false, 'message' => 'Message is required']);
    exit;
}

try {
    // Clean and prepare search
    $clean_message = strtolower(trim($user_message));
    $words = explode(' ', $clean_message);
    
    // Remove common words for better matching
    $stopwords = ['a', 'an', 'the', 'is', 'are', 'was', 'were', 'what', 'how', 'why', 'when', 'where', 'who', 'which', 'do', 'does', 'did', 'can', 'could', 'will', 'would', 'should', 'may', 'might', 'must', 'to', 'for', 'of', 'in', 'on', 'at', 'by', 'with', 'about', 'i', 'me', 'my', 'we', 'our', 'you', 'your', 'it', 'its', 'this', 'that', 'these', 'those', 'and', 'or', 'but', 'if', 'then', 'so', 'because', 'please', 'help', 'want', 'need', 'know'];
    
    $keywords = array_filter($words, function($word) use ($stopwords) {
        return strlen($word) > 2 && !in_array($word, $stopwords);
    });
    
    // Try to find matching FAQ
    $faq = null;
    $best_match_score = 0;
    
    // Get all active FAQs
    $stmt = $pdo->query("SELECT id, question, answer, category FROM chatbot_faq WHERE is_active = 1");
    $all_faqs = $stmt->fetchAll();
    
    foreach ($all_faqs as $f) {
        $faq_question = strtolower($f['question']);
        $faq_answer = strtolower($f['answer']);
        $score = 0;
        
        // Check keyword matches in question
        foreach ($keywords as $word) {
            if (strpos($faq_question, $word) !== false) {
                $score += 15;
            }
            // Also check answer for context
            if (strpos($faq_answer, $word) !== false) {
                $score += 5;
            }
        }
        
        // Check if question contains user message or vice versa
        if (strpos($faq_question, $clean_message) !== false) {
            $score += 50;
        }
        if (strpos($clean_message, $faq_question) !== false) {
            $score += 40;
        }
        
        // Similar text check
        similar_text($clean_message, $faq_question, $percent);
        if ($percent > 50) {
            $score += $percent;
        }
        
        // Levenshtein distance for typo tolerance
        $distance = levenshtein($clean_message, $faq_question);
        $max_len = max(strlen($clean_message), strlen($faq_question));
        if ($max_len > 0) {
            $similarity = (1 - ($distance / $max_len)) * 100;
            if ($similarity > 60) {
                $score += $similarity / 2;
            }
        }
        
        if ($score > $best_match_score && $score >= 25) {
            $best_match_score = $score;
            $faq = $f;
        }
    }
    
    if ($faq) {
        // Found a matching FAQ
        $bot_response = $faq['answer'];
        $faq_id = $faq['id'];
        $faq_found = true;
    } else {
        // No match found - Log as unanswered question
        $bot_response = "I'm sorry, I don't have a specific answer for that question yet. 🤖\n\nOur team will review this and add an answer soon!\n\nIn the meantime, you can:\n• Try rephrasing your question\n• Contact admin through WhatsApp\n• Check the Help section for common topics";
        $faq_id = null;
        $faq_found = false;
        
        // Log unanswered question for admin review
        logUnansweredQuestion($pdo, $user_message, $user_id, $user_name);
    }
    
    // Log conversation
    try {
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
    } catch (PDOException $e) {
        // Silently fail - don't break the chat
        error_log("Conversation Log Error: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'response' => $bot_response,
        'faq_found' => $faq_found
    ]);
    
} catch (PDOException $e) {
    error_log("Chatbot Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'response' => 'Sorry, something went wrong. Please try again.',
        'faq_found' => false
    ]);
}

/**
 * Log unanswered question for admin review
 */
function logUnansweredQuestion($pdo, $question, $user_id, $user_name) {
    try {
        $clean_q = strtolower(trim($question));
        
        // Check if similar question already exists (not resolved)
        $stmt = $pdo->prepare("
            SELECT id, asked_count FROM chatbot_unanswered 
            WHERE LOWER(TRIM(question)) = :q AND is_resolved = 0
        ");
        $stmt->execute([':q' => $clean_q]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update count and last asked time
            $stmt = $pdo->prepare("
                UPDATE chatbot_unanswered 
                SET asked_count = asked_count + 1, 
                    last_asked_at = NOW() 
                WHERE id = :id
            ");
            $stmt->execute([':id' => $existing['id']]);
        } else {
            // Insert new unanswered question
            $stmt = $pdo->prepare("
                INSERT INTO chatbot_unanswered (user_id, user_name, question, asked_count, first_asked_at, last_asked_at, is_resolved)
                VALUES (:user_id, :user_name, :question, 1, NOW(), NOW(), 0)
            ");
            $stmt->execute([
                ':user_id' => $user_id,
                ':user_name' => $user_name,
                ':question' => $question
            ]);
        }
    } catch (PDOException $e) {
        error_log("Log Unanswered Error: " . $e->getMessage());
        // Don't throw - this is a background operation
    }
}
