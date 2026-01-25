<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

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
    
    if (empty($product_link)) {
        $errors[] = 'Product link is required';
    }
    
    if (!filter_var($product_link, FILTER_VALIDATE_URL)) {
        $errors[] = 'Invalid product link URL';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO tasks (user_id, product_link, task_status)
                VALUES (:user_id, :product_link, 'pending')
            ");
            
            $stmt->execute([
                ':user_id' => $user_id,
                ':product_link' => $product_link
            ]);
            
            $task_id = $pdo->lastInsertId();
            logActivity('Admin assigned task', $task_id, $user_id);
            
            $success = true;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors[] = 'Failed to assign task';
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
        .sidebar-menu {
            list-style: none;
        }
        .sidebar-menu a {
            color: #bbb;
            text-decoration: none;
            padding: 12px 15px;
            display: block;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .admin-content {
            padding: 30px;
        }
        .form-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 600px;
        }
        .user-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #3498db;
        }
        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .btn-assign {
            width: 100%;
            padding: 12px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }
        .alert {
            margin-bottom: 20px;
            padding: 12px 15px;
            border-radius: 8px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <div class="admin-sidebar">
            <div class="sidebar-brand">
                <h3>⚙️ Admin</h3>
                <p><?php echo APP_NAME; ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="<?php echo ADMIN_URL; ?>/dashboard.php">📊 Dashboard</a></li>
                <li><a href="<?php echo ADMIN_URL; ?>/reviewers.php" class="active">👥 Reviewers</a></li>
                <li><a href="<?php echo ADMIN_URL; ?>/task-pending.php">📋 Pending Tasks</a></li>
                <li><a href="<?php echo ADMIN_URL; ?>/task-completed.php">✓ Completed Tasks</a></li>
                <li><a href="<?php echo ADMIN_URL; ?>/faq-manager.php">🤖 Chatbot FAQ</a></li>
                <li style="margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 30px;">
                    <a href="<?php echo APP_URL; ?>/logout.php" style="color: #e74c3c;">🚪 Logout</a>
                </li>
            </ul>
        </div>
        
        <div class="admin-content">
            <h1 style="color: #2c3e50; margin-bottom: 30px;">📌 Assign New Task</h1>
            
            <div class="form-card">
                <div class="user-info">
                    <p><strong>Reviewer:</strong> <?php echo escape($user['name']); ?></p>
                    <p><strong>Email:</strong> <?php echo escape($user['email']); ?></p>
                    <p><strong>Mobile:</strong> <?php echo escape($user['mobile']); ?></p>
                </div>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        ✓ Task assigned successfully! Task ID: #<?php echo intval($task_id); ?><br>
                        <small>User will see this task in their dashboard.</small>
                    </div>
                    <a href="<?php echo ADMIN_URL; ?>/reviewers.php" style="color: #3498db;">← Back to Reviewers</a>
                <?php else: ?>
                    <?php if (!empty($errors)): ?>
                        <?php foreach ($errors as $error): ?>
                            <div class="alert alert-danger">✗ <?php echo escape($error); ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label for="product_link">Product Link (Amazon/Flipkart) *</label>
                            <input type="url" id="product_link" name="product_link" class="form-control" 
                                   placeholder="https://amazon.com/product/..." required>
                            <small>Paste the product link where user should submit review</small>
                        </div>
                        
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <button type="submit" class="btn-assign">Assign Task to Reviewer</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
