<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

// Fetch completed tasks
try {
    $stmt = $pdo->query("
        SELECT 
            t.id, u.name as user_name, u.email, u.mobile,
            t.task_status, t.refund_requested, t.created_at,
            COUNT(ts.id) as total_steps,
            SUM(CASE WHEN ts.step_status = 'completed' THEN 1 ELSE 0 END) as completed_steps
        FROM tasks t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN task_steps ts ON t.id = ts.task_id
        WHERE t.refund_requested = true
        GROUP BY t.id
        ORDER BY t.created_at DESC
    ");
    
    $tasks = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $tasks = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Tasks - Admin</title>
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
            border-left: 5px solid #27ae60;
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
        .badge-completed {
            background: #27ae60;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
        }
        .user-details {
            color: #666;
            font-size: 13px;
        }
        .btn-view {
            padding: 10px 15px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
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
                <li><a href="<?php echo ADMIN_URL; ?>/task-pending.php">📋 Pending Tasks</a></li>
                <li><a href="<?php echo ADMIN_URL; ?>/task-completed.php" class="active">✓ Completed Tasks</a></li>
                <li><a href="<?php echo ADMIN_URL; ?>/faq-manager.php">🤖 Chatbot FAQ</a></li>
                <li style="margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 30px;">
                    <a href="<?php echo APP_URL; ?>/logout.php" style="color: #e74c3c;">🚪 Logout</a>
                </li>
            </ul>
        </div>
        
        <div class="admin-content">
            <h1 style="color: #2c3e50; margin-bottom: 30px;">✓ Completed Tasks (Refunds Sent)</h1>
            
            <?php if (empty($tasks)): ?>
                <div class="empty-state">
                    <h3>No completed tasks yet</h3>
                    <p>Tasks will appear here once refunds are processed</p>
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
                            <span class="badge-completed">✓ COMPLETED</span>
                        </div>
                        
                        <p><strong>All 4 Steps Completed:</strong> <?php echo $task['completed_steps']; ?>/4</p>
                        <p><strong>Completed on:</strong> <?php echo date('d M Y', strtotime($task['created_at'])); ?></p>
                        
                        <a href="<?php echo ADMIN_URL; ?>/task-detail.php?task_id=<?php echo $task['id']; ?>" class="btn-view">
                            View Details
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
