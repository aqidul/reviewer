<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

// Check admin session
if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];

// Fetch statistics
try {
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $total_users = $stmt->fetch()['count'];
    
    // Total tasks
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tasks");
    $total_tasks = $stmt->fetch()['count'];
    
    // Pending tasks
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM task_steps WHERE step_status = 'pending'");
    $pending_tasks = $stmt->fetch()['count'];
    
    // Completed tasks
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM task_steps WHERE step_status = 'completed'");
    $completed_tasks = $stmt->fetch()['count'];
    
} catch (PDOException $e) {
    error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/bootstrap.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
            font-size: 20px;
            margin-bottom: 5px;
        }
        .sidebar-brand p {
            font-size: 11px;
            color: #bbb;
        }
        .sidebar-menu {
            list-style: none;
        }
        .sidebar-menu li {
            margin-bottom: 10px;
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
        .sidebar-menu a i {
            margin-right: 10px;
            width: 20px;
        }
        .admin-content {
            padding: 30px;
        }
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .content-header h1 {
            color: #2c3e50;
            font-size: 28px;
        }
        .logout-btn {
            padding: 10px 20px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            font-weight: 600;
        }
        .logout-btn:hover {
            background: #c0392b;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 5px solid #3498db;
        }
        .stat-card.success {
            border-left-color: #27ae60;
        }
        .stat-card.warning {
            border-left-color: #f39c12;
        }
        .stat-card.danger {
            border-left-color: #e74c3c;
        }
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .stat-card .number {
            font-size: 36px;
            font-weight: 700;
            color: #2c3e50;
        }
        .welcome-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        .welcome-box h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .action-btn {
            padding: 15px 20px;
            background: white;
            color: #2c3e50;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <div class="admin-sidebar">
            <div class="sidebar-brand">
                <h3>⚙️ Admin</h3>
                <p><?php echo APP_NAME; ?></p>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="<?php echo ADMIN_URL; ?>/dashboard.php" class="active">📊 Dashboard</a></li>
                <li><a href="<?php echo ADMIN_URL; ?>/reviewers.php">👥 Reviewers</a></li>
                <li><a href="<?php echo ADMIN_URL; ?>/task-pending.php">📋 Pending Tasks</a></li>
                <li><a href="<?php echo ADMIN_URL; ?>/task-completed.php">✓ Completed Tasks</a></li>
                <li><a href="<?php echo ADMIN_URL; ?>/faq-manager.php">🤖 Chatbot FAQ</a></li>
                <li style="margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 30px;">
                    <a href="<?php echo APP_URL; ?>/logout.php" style="color: #e74c3c;">🚪 Logout</a>
                </li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="admin-content">
            <div class="content-header">
                <h1>📊 Dashboard</h1>
            </div>
            
            <div class="welcome-box">
                <h2>Welcome back, <?php echo escape($admin_name); ?>! 👋</h2>
                <p>Manage all reviewers and tasks from here. Everything is ready!</p>
                
                <div class="quick-actions">
                    <a href="<?php echo ADMIN_URL; ?>/reviewers.php" class="action-btn">Assign New Task</a>
                    <a href="<?php echo ADMIN_URL; ?>/task-pending.php" class="action-btn">Review Pending</a>
                    <a href="<?php echo ADMIN_URL; ?>/task-completed.php" class="action-btn">View Completed</a>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Reviewers</h3>
                    <div class="number"><?php echo $total_users; ?></div>
                </div>
                
                <div class="stat-card success">
                    <h3>Total Tasks</h3>
                    <div class="number"><?php echo $total_tasks; ?></div>
                </div>
                
                <div class="stat-card warning">
                    <h3>Pending Steps</h3>
                    <div class="number"><?php echo $pending_tasks; ?></div>
                </div>
                
                <div class="stat-card danger">
                    <h3>Completed Steps</h3>
                    <div class="number"><?php echo $completed_tasks; ?></div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
