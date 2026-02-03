<?php
require_once '../includes/config.php';
require_once '../includes/chat-functions.php';

if (!isLoggedIn() || isAdmin()) {
    redirect('../index.php');
}

$user_id = $_SESSION['user_id'];

// Get or create conversation
$conversation_id = getOrCreateConversation($db, $user_id);

// Get messages
$messages = getChatMessages($db, $conversation_id);

// Mark messages as read
markMessagesAsRead($db, $conversation_id, 'user');

// Get conversation details
$conversation = getConversationDetails($db, $conversation_id);

include '../includes/header.php';
?>

<style>
.chat-container {
    height: calc(100vh - 200px);
    display: flex;
    flex-direction: column;
}
.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    background-color: #f8f9fa;
}
.message {
    margin-bottom: 15px;
    display: flex;
}
.message.user {
    justify-content: flex-end;
}
.message.admin {
    justify-content: flex-start;
}
.message-content {
    max-width: 70%;
    padding: 10px 15px;
    border-radius: 15px;
    word-wrap: break-word;
}
.message.user .message-content {
    background-color: #007bff;
    color: white;
}
.message.admin .message-content {
    background-color: white;
    border: 1px solid #dee2e6;
}
.message-time {
    font-size: 0.75rem;
    opacity: 0.7;
    margin-top: 5px;
}
.chat-input {
    padding: 15px;
    background-color: white;
    border-top: 1px solid #dee2e6;
}
</style>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2">
            <div class="list-group">
                <a href="dashboard.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="tasks.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-list-task"></i> My Tasks
                </a>
                <a href="submit-proof.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-file-earmark-check"></i> Submit Proof
                </a>
                <a href="referrals.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-people"></i> Referrals
                </a>
                <a href="rewards.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-trophy"></i> Rewards
                </a>
                <a href="leaderboard.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-bar-chart"></i> Leaderboard
                </a>
                <a href="chat.php" class="list-group-item list-group-item-action active">
                    <i class="bi bi-chat-dots"></i> Chat Support
                </a>
                <a href="wallet.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-wallet2"></i> Wallet
                </a>
                <a href="profile.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-person"></i> Profile
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-10">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-chat-dots-fill"></i> Support Chat
                        <?php if ($conversation['admin_name']): ?>
                            <small class="text-muted">with <?php echo htmlspecialchars($conversation['admin_name']); ?></small>
                        <?php endif; ?>
                    </h5>
                    <span class="badge bg-<?php echo $conversation['status'] == 'open' ? 'success' : 'secondary'; ?>">
                        <?php echo ucfirst($conversation['status']); ?>
                    </span>
                </div>

                <div class="chat-container">
                    <!-- Messages -->
                    <div class="chat-messages" id="chatMessages">
                        <?php if (count($messages) > 0): ?>
                            <?php foreach ($messages as $message): ?>
                                <div class="message <?php echo $message['sender_type']; ?>">
                                    <div class="message-content">
                                        <div><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                        <?php if ($message['attachment']): ?>
                                            <div class="mt-2">
                                                <a href="../<?php echo htmlspecialchars($message['attachment']); ?>" target="_blank">
                                                    <i class="bi bi-paperclip"></i> View Attachment
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        <div class="message-time">
                                            <?php echo date('M d, Y H:i', strtotime($message['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-chat-dots" style="font-size: 4rem; color: #ccc;"></i>
                                <h5 class="mt-3">No messages yet</h5>
                                <p class="text-muted">Start a conversation with our support team!</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Input -->
                    <div class="chat-input">
                        <form id="chatForm" method="POST" enctype="multipart/form-data">
                            <div class="input-group">
                                <input type="file" class="d-none" id="attachmentInput" name="attachment" accept="image/*,.pdf">
                                <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('attachmentInput').click()">
                                    <i class="bi bi-paperclip"></i>
                                </button>
                                <input type="text" class="form-control" id="messageInput" name="message" 
                                       placeholder="Type your message..." required>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send"></i> Send
                                </button>
                            </div>
                            <div id="attachmentPreview" class="mt-2" style="display: none;">
                                <small class="text-muted">
                                    <i class="bi bi-paperclip"></i> <span id="attachmentName"></span>
                                    <button type="button" class="btn btn-sm btn-link text-danger" onclick="clearAttachment()">Remove</button>
                                </small>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Scroll to bottom of messages
function scrollToBottom() {
    const chatMessages = document.getElementById('chatMessages');
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Submit chat form
document.getElementById('chatForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('conversation_id', <?php echo $conversation_id; ?>);
    formData.append('send_message', '1');
    
    fetch('../api/chat.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Add message to chat
            loadMessages();
            // Clear form
            document.getElementById('messageInput').value = '';
            clearAttachment();
        } else {
            alert('Error sending message: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error sending message');
    });
});

// Load messages periodically
function loadMessages() {
    fetch('../api/chat.php?action=get_messages&conversation_id=<?php echo $conversation_id; ?>')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateMessages(data.messages);
        }
    })
    .catch(error => console.error('Error loading messages:', error));
}

function updateMessages(messages) {
    const container = document.getElementById('chatMessages');
    if (!messages || messages.length === 0) return;
    
    let html = '';
    messages.forEach(msg => {
        const senderClass = msg.sender_type === 'user' ? 'user' : 'admin';
        const attachment = msg.attachment ? 
            `<div class="mt-2"><a href="../${msg.attachment}" target="_blank"><i class="bi bi-paperclip"></i> View Attachment</a></div>` : '';
        
        html += `
            <div class="message ${senderClass}">
                <div class="message-content">
                    <div>${msg.message.replace(/\n/g, '<br>')}</div>
                    ${attachment}
                    <div class="message-time">${formatDate(msg.created_at)}</div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html || '<div class="text-center py-5"><i class="bi bi-chat-dots" style="font-size: 4rem; color: #ccc;"></i><h5 class="mt-3">No messages yet</h5></div>';
    scrollToBottom();
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

// Attachment handling
document.getElementById('attachmentInput').addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        document.getElementById('attachmentName').textContent = file.name;
        document.getElementById('attachmentPreview').style.display = 'block';
    }
});

function clearAttachment() {
    document.getElementById('attachmentInput').value = '';
    document.getElementById('attachmentPreview').style.display = 'none';
}

// Auto-refresh messages every 5 seconds
setInterval(loadMessages, 5000);

// Initial scroll
scrollToBottom();
</script>

<?php include '../includes/footer.php'; ?>
