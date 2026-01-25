<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

// Fetch pending tasks (where at least step 1 is submitted)
try {
    $stmt = $pdo->query("
        SELECT 
            t.id, u.name as user_name, u.email, u.mobile,
            ts1.step_status as step1_status,
            ts2.step_status as step2_status,
            ts3.step_status as step3_status,
            ts4.step_status as step4_status,
            ts1.order_number, ts1.order_amount
        FROM tasks t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN task_steps ts1 ON t.id = ts1.task_id AND ts1.step_number = 1
        LEFT JOIN task_steps ts2 ON t.id = ts2.task_id AND ts2.step_number = 2
        LEFT JOIN task_steps ts3 ON t.id = ts3.task_id AND ts3.step_number = 3
        LEFT JOIN task_steps ts4 ON t.id = ts4.task_id AND ts4.step_number = 4
        WHERE t.refund_requested = false AND ts1.id IS NOT NULL
        ORDER BY t.created_at DESC
    ");
    
    $tasks = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $tasks = [];
}

$csrf_token = generateCSRFToken();

// Helper function to get step button color
function getStepButtonClass($status) {
    return $status === 'completed' ? 'btn-success' : 'btn-danger';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Tasks - Admin</title>
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
        .sidebar-menu a {
            color: #bbb;
            text-decoration: none;
            padding: 12px 15px;
            display: block;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .admin-content {
            padding: 30px;
        }
        .task-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
            color: #2c3e50;
            font-size: 18px;
        }
        .user-details {
            color: #666;
            font-size: 13px;
            margin-bottom: 15px;
        }
        .steps-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        .step-btn {
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            color: white;
        }
        .btn-success {
            background: #27ae60;
        }
        .btn-danger {
            background: #e74c3c;
        }
        .btn-view {
            padding: 10px 15px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-view:hover {
            background: #2980b9;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <div class="admin-sidebar">
            <div class="sidebar-brand" style="text-align: center; margin-bottom: 30px;">
                <h3>⚙️ Admin</h3>
            </div>
            <ul class="sidebar-menu" style="list-style: none;">
                <li><a href="<?php echo ADMIN_URL; ?>/dashboard.php">📊 Dashboard</a></li>
                <li><a href="<?php echo ADMIN_URL; ?>/reviewers.php">👥 Reviewers</a></li>
                <li><a href="<?php echo ADMIN_URL; ?>/task-pending.php" class="active">📋 Pending Tasks</a></li>
                <li><a href="<?php echo ADMIN_URL; ?>/task-completed.php">✓ Completed Tasks</a></li>
                <li><a href="<?php echo ADMIN_URL; ?>/faq-manager.php">🤖 Chatbot FAQ</a></li>
                <li style="margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 30px;">
                    <a href="<?php echo APP_URL; ?>/logout.php" style="color: #e74c3c;">🚪 Logout</a>
                </li>
            </ul>
        </div>
        
        <div class="admin-content">
            <h1 style="color: #2c3e50; margin-bottom: 30px;">📋 Pending Tasks</h1>
            
            <?php if (empty($tasks)): ?>
                <div class="empty-state">
                    <h3>No pending tasks</h3>
                    <p>All tasks are completed or no tasks assigned yet</p>
                </div>
            <?php else: ?>
                <?php foreach ($tasks as $task): ?>
                    <div class="task-card">
                        <div class="task-header">
                            <div>
                                <div class="task-id">Task #<?php echo $task['id']; ?></div>
                                <div class="user-details">
                                    👤 <?php echo escape($task['user_name']); ?> | 📧 <?php echo escape($task['email']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="steps-container">
                            <a href="<?php echo ADMIN_URL; ?>/task-detail.php?task_id=<?php echo $task['id']; ?>&step=1" 
                               class="step-btn btn-<?php echo getStepButtonClass($task['step1_status']); ?>">
                                Step 1
                            </a>
                            <a href="<?php echo ADMIN_URL; ?>/task-detail.php?task_id=<?php echo $task['id']; ?>&step=2" 
                               class="step-btn btn-<?php echo getStepButtonClass($task['step2_status']); ?>">
                                Step 2
                            </a>
                            <a href="<?php echo ADMIN_URL; ?>/task-detail.php?task_id=<?php echo $task['id']; ?>&step=3" 
                               class="step-btn btn-<?php echo getStepButtonClass($task['step3_status']); ?>">
                                Step 3
                            </a>
                            <a href="<?php echo ADMIN_URL; ?>/task-detail.php?task_id=<?php echo $task['id']; ?>&step=4" 
                               class="step-btn btn-<?php echo getStepButtonClass($task['step4_status']); ?>">
                                Refund
                            </a>
                        </div>
                        
                        <a href="<?php echo ADMIN_URL; ?>/task-detail.php?task_id=<?php echo $task['id']; ?>" class="btn-view">
                            View Full Details
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
