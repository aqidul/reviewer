<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$errors = [];
$success = false;

// Handle FAQ CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');
    
    // ADD FAQ
    if ($action === 'add') {
        $question = sanitizeInput($_POST['question'] ?? '');
        $answer = sanitizeInput($_POST['answer'] ?? '');
        $category = sanitizeInput($_POST['category'] ?? '');
        
        if (empty($question)) $errors[] = 'Question is required';
        if (empty($answer)) $errors[] = 'Answer is required';
        if (empty($category)) $errors[] = 'Category is required';
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO chatbot_faq (question, answer, category, is_active)
                    VALUES (:question, :answer, :category, true)
                ");
                
                $stmt->execute([
                    ':question' => $question,
                    ':answer' => $answer,
                    ':category' => $category
                ]);
                
                logActivity('Admin added FAQ', null, null);
                $success = true;
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $errors[] = 'Failed to add FAQ';
            }
        }
    }
    
    // EDIT FAQ
    if ($action === 'edit') {
        $faq_id = intval($_POST['faq_id'] ?? 0);
        $question = sanitizeInput($_POST['question'] ?? '');
        $answer = sanitizeInput($_POST['answer'] ?? '');
        $category = sanitizeInput($_POST['category'] ?? '');
        
        if ($faq_id > 0 && !empty($question) && !empty($answer) && !empty($category)) {
            try {
                $stmt = $pdo->prepare("UPDATE chatbot_faq SET question = :q, answer = :a, category = :c WHERE id = :id");
                $stmt->execute([':q' => $question, ':a' => $answer, ':c' => $category, ':id' => $faq_id]);
                $success = true;
            } catch (PDOException $e) {
                $errors[] = 'Failed to update FAQ';
            }
        }
    }
    
    // DELETE FAQ
    if ($action === 'delete') {
        $faq_id = intval($_POST['faq_id'] ?? 0);
        
        if ($faq_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM chatbot_faq WHERE id = :id");
                $stmt->execute([':id' => $faq_id]);
                
                logActivity('Admin deleted FAQ', null, null);
                $success = true;
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $errors[] = 'Failed to delete FAQ';
            }
        }
    }
}

// Fetch all FAQs
try {
    $stmt = $pdo->query("SELECT * FROM chatbot_faq ORDER BY category, id");
    $faqs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $faqs = [];
}

// Get unanswered count for badge
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM chatbot_unanswered WHERE is_resolved = 0");
    $unanswered_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    $unanswered_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot FAQ Manager - Admin</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:#f5f5f5;font-family:-apple-system,sans-serif}
        .wrapper{display:grid;grid-template-columns:220px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(135deg,#2c3e50,#1a252f);color:#fff;padding:20px}
        .sidebar h3{text-align:center;margin-bottom:30px;padding-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1)}
        .sidebar ul{list-style:none}
        .sidebar a{color:#bbb;text-decoration:none;padding:12px 15px;display:flex;align-items:center;justify-content:space-between;border-radius:8px;margin-bottom:8px}
        .sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,0.1);color:#fff}
        .nav-badge{background:#e74c3c;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600}
        .content{padding:25px}
        .page-title{font-size:24px;color:#2c3e50;margin-bottom:25px}
        .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:25px}
        .card{background:#fff;border-radius:12px;padding:25px;box-shadow:0 2px 10px rgba(0,0,0,0.1)}
        .card-title{font-size:18px;font-weight:600;color:#2c3e50;margin-bottom:20px;padding-bottom:15px;border-bottom:1px solid #eee}
        .form-group{margin-bottom:15px}
        .form-group label{display:block;font-weight:600;margin-bottom:6px;color:#333;font-size:14px}
        .form-control{width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px}
        .form-control:focus{border-color:#3498db;outline:none}
        textarea.form-control{min-height:100px;resize:vertical}
        .btn{padding:12px 20px;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px}
        .btn-add{width:100%;background:#27ae60;color:#fff}
        .btn-add:hover{background:#219a52}
        .btn-delete{padding:6px 12px;background:#e74c3c;color:#fff;font-size:12px}
        .btn-delete:hover{background:#c0392b}
        .btn-edit{padding:6px 12px;background:#3498db;color:#fff;font-size:12px;margin-right:5px}
        .alert{padding:12px 15px;border-radius:8px;margin-bottom:20px;font-size:14px}
        .alert-success{background:#d4edda;color:#155724}
        .alert-danger{background:#f8d7da;color:#721c24}
        .faq-list{max-height:600px;overflow-y:auto}
        .faq-card{background:#fff;border:1px solid #eee;border-radius:8px;padding:15px;margin-bottom:12px;border-left:4px solid #3498db}
        .faq-question{font-weight:600;color:#2c3e50;margin-bottom:8px;font-size:14px}
        .faq-answer{color:#666;font-size:13px;line-height:1.5;margin-bottom:10px}
        .faq-footer{display:flex;justify-content:space-between;align-items:center}
        .faq-category{background:#ecf0f1;padding:3px 10px;border-radius:15px;font-size:11px;color:#2c3e50;font-weight:600}
        .empty{text-align:center;padding:40px;color:#999}
        
        /* Modal */
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;justify-content:center;align-items:center}
        .modal.show{display:flex}
        .modal-content{background:#fff;border-radius:12px;padding:25px;width:90%;max-width:500px;max-height:90vh;overflow-y:auto}
        .modal-title{font-size:18px;font-weight:600;margin-bottom:20px;color:#2c3e50}
        .modal-close{float:right;font-size:24px;cursor:pointer;color:#999}
        .modal-close:hover{color:#333}
        .btn-row{display:flex;gap:10px;margin-top:20px}
        .btn-secondary{background:#95a5a6;color:#fff}
        
        @media(max-width:900px){.grid-2{grid-template-columns:1fr}.wrapper{grid-template-columns:1fr}.sidebar{display:none}}
    </style>
</head>
<body>
<div class="wrapper">
    <div class="sidebar">
        <h3>⚙️ Admin</h3>
        <ul>
            <li><a href="<?php echo ADMIN_URL; ?>/dashboard.php">📊 Dashboard</a></li>
            <li><a href="<?php echo ADMIN_URL; ?>/reviewers.php">👥 Reviewers</a></li>
            <li><a href="<?php echo ADMIN_URL; ?>/task-pending.php">📋 Pending Tasks</a></li>
            <li><a href="<?php echo ADMIN_URL; ?>/task-completed.php">✓ Completed Tasks</a></li>
            <li><a href="<?php echo ADMIN_URL; ?>/faq-manager.php" class="active">🤖 Chatbot FAQ</a></li>
            <li>
                <a href="<?php echo ADMIN_URL; ?>/chatbot-unanswered.php">
                    ❓ Unanswered Q's
                    <?php if ($unanswered_count > 0): ?>
                        <span class="nav-badge"><?php echo $unanswered_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li style="margin-top:30px;border-top:1px solid rgba(255,255,255,0.1);padding-top:20px">
                <a href="<?php echo APP_URL; ?>/logout.php" style="color:#e74c3c">🚪 Logout</a>
            </li>
        </ul>
    </div>
    
    <div class="content">
        <h1 class="page-title">🤖 Chatbot FAQ Manager</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✓ Operation successful!</div>
        <?php endif; ?>
        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger">✗ <?php echo escape($e); ?></div>
        <?php endforeach; ?>
        
        <?php if ($unanswered_count > 0): ?>
            <div class="alert" style="background:#fff3cd;color:#856404;display:flex;justify-content:space-between;align-items:center">
                <span>⚠️ You have <strong><?php echo $unanswered_count; ?></strong> unanswered questions waiting for your response!</span>
                <a href="<?php echo ADMIN_URL; ?>/chatbot-unanswered.php" style="color:#856404;font-weight:600">View Now →</a>
            </div>
        <?php endif; ?>
        
        <div class="grid-2">
            <!-- Add FAQ Form -->
            <div class="card">
                <h3 class="card-title">➕ Add New FAQ</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Question *</label>
                        <input type="text" name="question" class="form-control" placeholder="Enter the question..." required>
                    </div>
                    <div class="form-group">
                        <label>Answer *</label>
                        <textarea name="answer" class="form-control" placeholder="Enter the answer..." required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <option value="Getting Started">Getting Started</option>
                            <option value="Tasks">Tasks</option>
                            <option value="Payment">Payment & Refund</option>
                            <option value="Account">Account</option>
                            <option value="Technical">Technical Support</option>
                        </select>
                    </div>
                    <input type="hidden" name="action" value="add">
                    <button type="submit" class="btn btn-add">➕ Add FAQ</button>
                </form>
            </div>
            
            <!-- FAQ List -->
            <div class="card">
                <h3 class="card-title">📚 All FAQs (<?php echo count($faqs); ?>)</h3>
                <div class="faq-list">
                    <?php if (empty($faqs)): ?>
                        <div class="empty">
                            <p>No FAQs yet. Add some!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($faqs as $faq): ?>
                            <div class="faq-card">
                                <div class="faq-question"><?php echo escape($faq['question']); ?></div>
                                <div class="faq-answer"><?php echo nl2br(escape($faq['answer'])); ?></div>
                                <div class="faq-footer">
                                    <span class="faq-category"><?php echo escape($faq['category']); ?></span>
                                    <div>
                                        <button class="btn btn-edit" onclick="openEditModal(<?php echo $faq['id']; ?>, '<?php echo addslashes(escape($faq['question'])); ?>', '<?php echo addslashes(escape($faq['answer'])); ?>', '<?php echo escape($faq['category']); ?>')">Edit</button>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this FAQ?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="faq_id" value="<?php echo $faq['id']; ?>">
                                            <button type="submit" class="btn btn-delete">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeEditModal()">&times;</span>
        <h3 class="modal-title">✏️ Edit FAQ</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="faq_id" id="edit_faq_id">
            <div class="form-group">
                <label>Question *</label>
                <input type="text" name="question" id="edit_question" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Answer *</label>
                <textarea name="answer" id="edit_answer" class="form-control" required></textarea>
            </div>
            <div class="form-group">
                <label>Category *</label>
                <select name="category" id="edit_category" class="form-control" required>
                    <option value="Getting Started">Getting Started</option>
                    <option value="Tasks">Tasks</option>
                    <option value="Payment">Payment & Refund</option>
                    <option value="Account">Account</option>
                    <option value="Technical">Technical Support</option>
                </select>
            </div>
            <div class="btn-row">
                <button type="submit" class="btn btn-add">💾 Save Changes</button>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, question, answer, category) {
    document.getElementById('edit_faq_id').value = id;
    document.getElementById('edit_question').value = question;
    document.getElementById('edit_answer').value = answer.replace(/<br\s*\/?>/gi, '\n');
    document.getElementById('edit_category').value = category;
    document.getElementById('editModal').classList.add('show');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('show');
}

// Close modal on outside click
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
</script>
</body>
</html>
