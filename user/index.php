<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    redirectTo(APP_URL . '/index.php');
}

$user_id = (int)$_SESSION['user_id'];
$user_name = escape($_SESSION['user_name'] ?? '');

// Fetch all tasks assigned to user with arrow function (PHP 8.2)
try {
    $stmt = $pdo->prepare("
        SELECT 
            t.id, t.product_link, t.task_status, t.refund_requested, t.created_at,
            COUNT(CASE WHEN ts.step_status = 'completed' THEN 1 END) as completed_steps
        FROM tasks t
        LEFT JOIN task_steps ts ON t.id = ts.task_id
        WHERE t.user_id = :user_id
        GROUP BY t.id
        ORDER BY t.created_at DESC
    ");
    
    $stmt->execute([':user_id' => $user_id]);
    $tasks = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Dashboard error: ' . $e->getMessage());
    $tasks = [];
}

// PHP 8.2: Arrow functions
$getTaskProgress = fn(int $completed, int $total): int => 
    $total == 0 ? 0 : (int)round(($completed / $total) * 100);

$getStepColor = fn(string $status): string => 
    $status === 'completed' ? 'btn-success' : 'btn-danger';

$getStepStatus = fn(string $status): string => 
    $status === 'completed' ? '✓ Done' : '✗ Pending';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>User Dashboard - <?php echo escape(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .dashboard-container {
            padding: 20px;
        }
        .header-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .welcome-text {
            font-size: 28px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        .user-info {
            color: #666;
            font-size: 14px;
        }
        .logout-btn {
            float: right;
            padding: 10px 20px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .task-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .task-card:hover {
            transform: translateY(-5px);
        }
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
        }
        .task-id {
            font-weight: 600;
            color: #333;
            font-size: 18px;
        }
        .task-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending {
            background: #ffeaa7;
            color: #d63031;
        }
        .status-completed {
            background: #55efc4;
            color: #00b894;
        }
        .progress-container {
            margin-bottom: 15px;
        }
        .progress-bar-custom {
            height: 25px;
            background: #f0f0f0;
            border-radius: 12px;
            overflow: hidden;
            margin-top: 8px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: 600;
        }
        .steps-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }
        .step-btn {
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s;
        }
        .step-btn:hover {
            opacity: 0.9;
            transform: scale(1.05);
        }
        .btn-success {
            background: #27ae60;
        }
        .btn-danger {
            background: #e74c3c;
        }
        .task-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn-view {
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .no-tasks {
            background: white;
            padding: 40px;
            border-radius: 15px;
            text-align: center;
            color: #666;
        }
        .no-tasks h3 {
            color: #333;
            margin-bottom: 10px;
        }
        
        /* Chatbot Styles */
        .chat-toggle {
            position: fixed;
            bottom: 25px;
            right: 25px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 5px 25px rgba(102, 126, 234, 0.5);
            z-index: 1000;
            transition: all 0.3s;
            border: none;
        }
        .chat-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.6);
        }
        .chat-toggle svg {
            width: 28px;
            height: 28px;
            fill: white;
        }
        .chat-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #e74c3c;
            color: white;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            font-size: 11px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .chat-window {
            position: fixed;
            bottom: 100px;
            right: 25px;
            width: 380px;
            max-width: calc(100vw - 40px);
            height: 500px;
            max-height: calc(100vh - 150px);
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            z-index: 1001;
            display: none;
            flex-direction: column;
            overflow: hidden;
        }
        .chat-window.show {
            display: flex;
        }
        
        .chat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 18px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .chat-header h4 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }
        .chat-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            line-height: 1;
        }
        
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f8f9fa;
        }
        .message {
            margin-bottom: 15px;
            display: flex;
        }
        .message.bot {
            justify-content: flex-start;
        }
        .message.user {
            justify-content: flex-end;
        }
        .message-content {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.5;
        }
        .message.bot .message-content {
            background: white;
            color: #333;
            border-bottom-left-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .message.user .message-content {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom-right-radius: 5px;
        }
        
        .chat-input {
            padding: 15px;
            background: white;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
        }
        .chat-input input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 25px;
            font-size: 14px;
            outline: none;
        }
        .chat-input input:focus {
            border-color: #667eea;
        }
        .chat-input button {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .chat-input button:hover {
            opacity: 0.9;
        }
        
        .typing-indicator {
            display: none;
            padding: 12px 16px;
            background: white;
            border-radius: 18px;
            border-bottom-left-radius: 5px;
            width: fit-content;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .typing-indicator.show {
            display: block;
        }
        .typing-indicator span {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #667eea;
            border-radius: 50%;
            margin-right: 5px;
            animation: typing 1s infinite;
        }
        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; margin-right: 0; }
        @keyframes typing {
            0%, 100% { opacity: 0.3; transform: scale(0.8); }
            50% { opacity: 1; transform: scale(1); }
        }
        
        @media (max-width: 480px) {
            .chat-window {
                right: 10px;
                bottom: 80px;
                width: calc(100vw - 20px);
                height: calc(100vh - 100px);
            }
            .chat-toggle {
                right: 15px;
                bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="header-section">
            <div class="welcome-text">👋 Welcome, <?php echo $user_name; ?>!</div>
            <div class="user-info">
                Email: <?php echo escape($_SESSION['user_email']); ?> | Mobile: <?php echo escape($_SESSION['user_mobile']); ?>
                <a href="<?php echo APP_URL; ?>/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
        
        <div class="container-fluid">
            <?php if (empty($tasks)): ?>
                <div class="no-tasks">
                    <h3>📋 No Tasks Assigned</h3>
                    <p>Admin will assign tasks to you. Come back later!</p>
                </div>
            <?php else: ?>
                <?php foreach ($tasks as $task): 
                    $completed_steps = (int)($task['completed_steps'] ?? 0);
                    $total_steps = 4;
                    $progress = $getTaskProgress($completed_steps, $total_steps);
                    ?>
                    <div class="task-card">
                        <div class="task-header">
                            <div>
                                <div class="task-id">Task #<?php echo (int)$task['id']; ?></div>
                                <small style="color: #999;">Created: <?php echo date('d M Y', strtotime($task['created_at'])); ?></small>
                            </div>
                            <span class="task-status status-<?php echo $task['task_status']; ?>">
                                <?php echo strtoupper(str_replace('_', ' ', $task['task_status'])); ?>
                            </span>
                        </div>
                        
                        <div class="progress-container">
                            <strong>Progress: <?php echo $completed_steps; ?>/4 Steps</strong>
                            <div class="progress-bar-custom">
                                <div class="progress-fill" style="width: <?php echo $progress; ?>%;">
                                    <?php echo $progress; ?>%
                                </div>
                            </div>
                        </div>
                        
                        <div class="steps-container">
                            <a href="<?php echo APP_URL; ?>/user/submit-order.php?task_id=<?php echo (int)$task['id']; ?>" 
                               class="step-btn btn-<?php echo $getStepColor($completed_steps >= 1 ? 'completed' : 'pending'); ?>">
                                Step 1
                            </a>
                            <a href="<?php echo APP_URL; ?>/user/submit-delivery.php?task_id=<?php echo (int)$task['id']; ?>" 
                               class="step-btn btn-<?php echo $getStepColor($completed_steps >= 2 ? 'completed' : 'pending'); ?>">
                                Step 2
                            </a>
                            <a href="<?php echo APP_URL; ?>/user/submit-review.php?task_id=<?php echo (int)$task['id']; ?>" 
                               class="step-btn btn-<?php echo $getStepColor($completed_steps >= 3 ? 'completed' : 'pending'); ?>">
                                Step 3
                            </a>
                            <a href="<?php echo APP_URL; ?>/user/submit-refund.php?task_id=<?php echo (int)$task['id']; ?>" 
                               class="step-btn btn-<?php echo $getStepColor($task['refund_requested'] ? 'completed' : 'pending'); ?>">
                                Step 4
                            </a>
                        </div>
                        
                        <div class="task-actions">
                            <a href="<?php echo APP_URL; ?>/user/task-detail.php?task_id=<?php echo (int)$task['id']; ?>" 
                               class="btn-view">View Full Details</a>
                            <?php if (!$task['refund_requested']): ?>
                                <a href="<?php echo APP_URL; ?>/user/submit-order.php?task_id=<?php echo (int)$task['id']; ?>" 
                                   class="btn-view" style="background: #27ae60;">Edit Task</a>
                            <?php else: ?>
                                <span style="color: #666; padding: 10px;">✓ Task Completed - View Only Mode</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- AI Chat Toggle Button -->
    <button class="chat-toggle" id="chatToggle" title="AI Support Chat">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
            <circle cx="8" cy="10" r="1.5"/>
            <circle cx="12" cy="10" r="1.5"/>
            <circle cx="16" cy="10" r="1.5"/>
        </svg>
    </button>
    
    <!-- Chat Window -->
    <div class="chat-window" id="chatWindow">
        <div class="chat-header">
            <h4>🤖 AI Support</h4>
            <button class="chat-close" id="chatClose">&times;</button>
        </div>
        <div class="chat-messages" id="chatMessages">
            <div class="message bot">
                <div class="message-content">
                    Hi <?php echo $user_name; ?>! 👋 I'm your AI assistant. How can I help you today?
                </div>
            </div>
            <div class="typing-indicator" id="typingIndicator">
                <span></span><span></span><span></span>
            </div>
        </div>
        <div class="chat-input">
            <input type="text" id="chatInput" placeholder="Type your message..." autocomplete="off">
            <button id="chatSend">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="white">
                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                </svg>
            </button>
        </div>
    </div>
    
    <script>
        // Chat Elements
        const chatToggle = document.getElementById('chatToggle');
        const chatWindow = document.getElementById('chatWindow');
        const chatClose = document.getElementById('chatClose');
        const chatMessages = document.getElementById('chatMessages');
        const chatInput = document.getElementById('chatInput');
        const chatSend = document.getElementById('chatSend');
        const typingIndicator = document.getElementById('typingIndicator');
        
        // Toggle chat window
        chatToggle.addEventListener('click', () => {
            chatWindow.classList.toggle('show');
            if (chatWindow.classList.contains('show')) {
                chatInput.focus();
            }
        });
        
        chatClose.addEventListener('click', () => {
            chatWindow.classList.remove('show');
        });
        
        // Send message function
        function sendMessage() {
            const message = chatInput.value.trim();
            if (!message) return;
            
            // Add user message
            addMessage(message, 'user');
            chatInput.value = '';
            
            // Show typing indicator
            typingIndicator.classList.add('show');
            scrollToBottom();
            
            // Send to API
            fetch('<?php echo APP_URL; ?>/chatbot/api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'message=' + encodeURIComponent(message)
            })
            .then(response => response.json())
            .then(data => {
                typingIndicator.classList.remove('show');
                if (data.success) {
                    addMessage(data.response, 'bot');
                } else {
                    addMessage('Sorry, something went wrong. Please try again.', 'bot');
                }
            })
            .catch(error => {
                typingIndicator.classList.remove('show');
                addMessage('Connection error. Please try again.', 'bot');
            });
        }
        
        // Add message to chat
        function addMessage(text, type) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message ' + type;
            messageDiv.innerHTML = '<div class="message-content">' + text.replace(/\n/g, '<br>') + '</div>';
            chatMessages.insertBefore(messageDiv, typingIndicator);
            scrollToBottom();
        }
        
        // Scroll to bottom
        function scrollToBottom() {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Event listeners
        chatSend.addEventListener('click', sendMessage);
        chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendMessage();
        });
    </script>
</body>
</html>
