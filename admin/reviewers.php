<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = escape($_SESSION['admin_name'] ?? 'Admin');

// Fetch all USERS (not admins)
$users = [];
$error_message = null;

try {
    // FIX: Match actual database schema and filter by user_type = 'user'
    $stmt = $pdo->prepare("
        SELECT 
            u.id, 
            u.name, 
            u.email, 
            u.mobile, 
            u.created_at,
            COUNT(DISTINCT t.id) as assigned_tasks,
            SUM(CASE WHEN t.refund_requested = true THEN 1 ELSE 0 END) as completed_tasks
        FROM users u
        LEFT JOIN tasks t ON u.id = t.user_id
        WHERE u.user_type = 'user'
        AND u.status = 'active'
        GROUP BY u.id, u.name, u.email, u.mobile, u.created_at
        ORDER BY u.created_at DESC
    ");
    
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    error_log("Reviewers found: " . count($users));
    
} catch (PDOException $e) {
    error_log('Database error in reviewers.php: ' . $e->getMessage());
    $error_message = 'Database error: ' . $e->getMessage();
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Reviewers - Admin Panel</title>
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
            color: white;
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
        .table-wrapper {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        thead {
            background: #f8f9fa;
        }
        th {
            border-bottom: 2px solid #ddd;
            font-weight: 600;
            color: #2c3e50;
            padding: 15px;
            text-align: left;
        }
        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        tbody tr:hover {
            background: #f9f9f9;
        }
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-primary {
            background: #3498db;
            color: white;
        }
        .badge-success {
            background: #27ae60;
            color: white;
        }
        .action-btn {
            padding: 8px 15px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .action-btn:hover {
            background: #2980b9;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .empty-state h3 {
            color: #333;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <div class="admin-sidebar">
            <div class="sidebar-brand">
                <h3>⚙️ Admin</h3>
                <p><?php echo escape(APP_NAME); ?></p>
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
        
        <!-- Main Content -->
        <div class="admin-content">
            <div class="content-header">
                <h1>👥 All Reviewers (<?php echo count($users); ?>)</h1>
            </div>
            
            <?php if (isset($error_message)): ?>
                <div style="padding: 15px; background: #f8d7da; color: #721c24; border-radius: 8px; margin-bottom: 20px;">
                    ❌ <?php echo escape($error_message); ?>
                </div>
            <?php endif; ?>
            
            <div class="table-wrapper">
                <?php if (empty($users)): ?>
                    <div class="empty-state">
                        <h3>📭 No reviewers registered yet</h3>
                        <p>Users with 'user' type will appear here</p>
                        <p style="margin-top: 15px; font-size: 13px;">
                            Current test users: 
                            <strong>aqidulm@gmail.com</strong> (Mobile: 8604261683)
                            <br>
                            <strong>gopalashukla18@gmail.com</strong> (Mobile: 7379162377)
                        </p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Mobile</th>
                                <th>Tasks Assigned</th>
                                <th>Completed</th>
                                <th>Joined</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><strong><?php echo escape($user['name'] ?? 'N/A'); ?></strong></td>
                                    <td><?php echo escape($user['email'] ?? 'N/A'); ?></td>
                                    <td><?php echo escape($user['mobile'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge badge-primary">
                                            <?php echo (int)($user['assigned_tasks'] ?? 0); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-success">
                                            <?php echo (int)($user['completed_tasks'] ?? 0); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($user['created_at'] ?? 'now')); ?></td>
                                    <td>
                                        <a href="<?php echo ADMIN_URL; ?>/assign-task.php?user_id=<?php echo (int)$user['id']; ?>" 
                                           class="action-btn">
                                            ➕ Assign Task
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
