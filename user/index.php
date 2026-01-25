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
</body>
</html>
