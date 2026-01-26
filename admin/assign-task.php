<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$user_id = intval($_GET['user_id'] ?? 0);
$errors = [];
$success = false;

// Fetch user details
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        die('User not found');
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    die('Error');
}

// Handle task assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('CSRF error');
    }
    
    $product_link = sanitizeInput($_POST['product_link'] ?? '');
    $commission = floatval($_POST['commission'] ?? 0);
    $deadline = $_POST['deadline'] ?? null;
    $priority = $_POST['priority'] ?? 'medium';
    
    if (empty($product_link)) {
        $errors[] = 'Product link is required';
    }
    
    if (!filter_var($product_link, FILTER_VALIDATE_URL)) {
        $errors[] = 'Invalid product link URL';
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Insert task
            $stmt = $pdo->prepare("
                INSERT INTO tasks (user_id, product_link, task_status, commission, deadline, priority, created_at)
                VALUES (:user_id, :product_link, 'pending', :commission, :deadline, :priority, NOW())
            ");
            
            $stmt->execute([
                ':user_id' => $user_id,
                ':product_link' => $product_link,
                ':commission' => $commission,
                ':deadline' => $deadline ?: null,
                ':priority' => $priority
            ]);
            
            $task_id = $pdo->lastInsertId();
            
            // Create task steps
            $steps = ['Order Placed', 'Delivery Received', 'Review Submitted', 'Refund Requested'];
            foreach ($steps as $index => $step) {
                $stmt = $pdo->prepare("
                    INSERT INTO task_steps (task_id, step_number, step_status, created_at)
                    VALUES (?, ?, 'pending', NOW())
                ");
                $stmt->execute([$task_id, $index + 1]);
            }
            
            $pdo->commit();
            
            // Log activity
            logActivity('Admin assigned task #' . $task_id, $task_id, $user_id);
            
            // Send notification to user
            createNotification($user_id, 'task', '📋 New Task Assigned', 'A new review task has been assigned to you. Check your dashboard!', APP_URL . '/user/');
            
            // Send email notification
            sendTaskNotification($user_id, 'task_assigned', ['task_id' => $task_id]);
            
            $success = true;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Assign Task Error: " . $e->getMessage());
            $errors[] = 'Failed to assign task: ' . $e->getMessage();
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Task - Admin</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/bootstrap.min.css">
    <style>
        body {
            background: #f5f5f5;
            font-family: 'Segoe UI', sans-serif;
        }
        .admin-wrapper {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }
        .admin-sidebar {
            background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);
            color: white;
            padding: 20px;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar-brand {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-brand h3 {
            margin: 0;
            font-size: 18px;
        }
        .sidebar-brand small {
            color: #888;
            font-size: 11px;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-menu a {
            color: #bbb;
            text-decoration: none;
            padding: 12px 15px;
            display: block;
            border-radius: 8px;
            transition: all 0.3s;
            margin-bottom: 5px;
        }
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .admin-content {
            padding: 30px;
        }
        .page-title {
            font-size: 24px;
            margin-bottom: 25px;
            color: #333;
        }
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            padding: 25px;
            max-width: 600px;
        }
        .user-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .user-info p {
            margin: 5px 0;
            color: #555;
        }
        .user-info strong {
            color: #333;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        .form-control:focus {
            border-color: #27ae60;
            outline: none;
        }
        .form-text {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }
        .btn-submit {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-danger {
            background: #ffe6e6;
            color: #c0392b;
            border: 1px solid #f5c6cb;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        @media (max-width: 768px) {
            .admin-wrapper {
                grid-template-columns: 1fr;
            }
            .admin-sidebar {
                display: none;
            }
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <div class="admin-sidebar">
        <div class="sidebar-brand">
            <h3>⚙️ Admin</h3>
            <small>ReviewFlow</small>
        </div>
        <ul class="sidebar-menu">
            <li><a href="<?php echo ADMIN_URL; ?>/dashboard.php">📊 Dashboard</a></li>
            <li><a href="<?php echo ADMIN_URL; ?>/reviewers.php" class="active">👥 Reviewers</a></li>
            <li><a href="<?php echo ADMIN_URL; ?>/pending-tasks.php">📋 Pending Tasks</a></li>
            <li><a href="<?php echo ADMIN_URL; ?>/completed-tasks.php">✅ Completed Tasks</a></li>
            <li><a href="<?php echo ADMIN_URL; ?>/chatbot-faq.php">🤖 Chatbot FAQ</a></li>
            <li style="margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                <a href="<?php echo ADMIN_URL; ?>/logout.php" style="color: #e74c3c;">🚪 Logout</a>
            </li>
        </ul>
    </div>
    
    <div class="admin-content">
        <a href="<?php echo ADMIN_URL; ?>/reviewers.php" class="back-link">← Back to Reviewers</a>
        <h1 class="page-title">📝 Assign New Task</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ Task assigned successfully! 
                <a href="<?php echo ADMIN_URL; ?>/reviewers.php">Go back to Reviewers</a>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p style="margin:5px 0">❌ <?php echo escape($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="user-info">
                <p><strong>Reviewer:</strong> <?php echo escape($user['name']); ?></p>
                <p><strong>Email:</strong> <?php echo escape($user['email']); ?></p>
                <p><strong>Mobile:</strong> <?php echo escape($user['mobile']); ?></p>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label for="product_link">Product Link (Amazon/Flipkart) *</label>
                    <input type="url" id="product_link" name="product_link" class="form-control" 
                           placeholder="https://amazon.com/product/..." required
                           value="<?php echo escape($_POST['product_link'] ?? ''); ?>">
                    <p class="form-text">Paste the product link where user should submit review</p>
                </div>
                
                <div class="form-group">
                    <label for="commission">Commission (₹)</label>
                    <input type="number" id="commission" name="commission" class="form-control" 
                           placeholder="0.00" step="0.01" min="0"
                           value="<?php echo escape($_POST['commission'] ?? ''); ?>">
                    <p class="form-text">Amount user will earn after completing this task</p>
                </div>
                
                <div class="form-group">
                    <label for="deadline">Deadline</label>
                    <input type="date" id="deadline" name="deadline" class="form-control"
                           min="<?php echo date('Y-m-d'); ?>"
                           value="<?php echo escape($_POST['deadline'] ?? ''); ?>">
                    <p class="form-text">Optional: Set a deadline for task completion</p>
                </div>
                
                <div class="form-group">
                    <label for="priority">Priority</label>
                    <select id="priority" name="priority" class="form-control">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-submit">Assign Task to Reviewer</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
