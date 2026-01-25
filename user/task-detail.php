<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$task_id = intval($_GET['task_id'] ?? 0);

try {
    $stmt = $pdo->prepare("
        SELECT t.*, u.name
        FROM tasks t
        JOIN users u ON t.user_id = u.id
        WHERE t.id = :task_id AND t.user_id = :user_id
    ");
    
    $stmt->execute([':task_id' => $task_id, ':user_id' => $user_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        die('Task not found');
    }
    
    // Fetch all steps
    $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :task_id ORDER BY step_number");
    $stmt->execute([':task_id' => $task_id]);
    $steps = $stmt->fetchAll();
    $steps_by_number = [];
    foreach ($steps as $s) {
        $steps_by_number[$s['step_number']] = $s;
    }
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    die('Error');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task #<?php echo $task_id; ?> Details</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
        }
        .task-header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .task-title {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        .task-meta {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .meta-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .meta-label {
            font-weight: 600;
            color: #666;
            font-size: 12px;
        }
        .meta-value {
            color: #2c3e50;
            font-weight: 600;
            margin-top: 5px;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
        }
        .status-pending {
            background: #ffeaa7;
            color: #d63031;
        }
        .status-completed {
            background: #55efc4;
            color: #00b894;
        }
        .step-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 5px solid #e74c3c;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .step-card.completed {
            border-left-color: #27ae60;
            background: #f0fdf4;
        }
        .step-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .step-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .field {
            margin-bottom: 15px;
        }
        .field-label {
            font-weight: 600;
            color: #666;
            font-size: 13px;
            margin-bottom: 5px;
        }
        .field-value {
            color: #333;
            word-break: break-word;
        }
        .screenshot-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .screenshot-link:hover {
            text-decoration: underline;
        }
        .btn-back {
            padding: 10px 20px;
            background: white;
            color: #667eea;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-edit {
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .empty-state {
            color: #999;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="task-header">
            <div class="task-title">📋 Task #<?php echo $task_id; ?></div>
            
            <div class="task-meta">
                <div class="meta-item">
                    <div class="meta-label">Status</div>
                    <div class="meta-value">
                        <span class="status-badge status-<?php echo $task['task_status']; ?>">
                            <?php echo strtoupper(str_replace('_', ' ', $task['task_status'])); ?>
                        </span>
                    </div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Product Link</div>
                    <div class="meta-value">
                        <a href="<?php echo escape($task['product_link']); ?>" target="_blank" style="color: #667eea;">Visit →</a>
                    </div>
                </div>
            </div>
            
            <div class="btn-group">
                <a href="<?php echo APP_URL; ?>/user/" class="btn-back">← Back to Dashboard</a>
                <?php if (!$task['refund_requested']): ?>
                    <a href="<?php echo APP_URL; ?>/user/submit-order.php?task_id=<?php echo $task_id; ?>" class="btn-edit">✎ Edit Task</a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- STEP 1 -->
        <div class="step-card <?php echo isset($steps_by_number[1]) && $steps_by_number[1]['step_status'] === 'completed' ? 'completed' : ''; ?>">
            <div class="step-title">
                📦 Step 1: Order Placed
                <?php if (isset($steps_by_number[1]) && $steps_by_number[1]['step_status'] === 'completed'): ?>
                    <span class="status-badge status-completed">✓ Done</span>
                <?php else: ?>
                    <span class="status-badge status-pending">Pending</span>
                <?php endif; ?>
            </div>
            
            <?php if (isset($steps_by_number[1])): ?>
                <div class="step-content">
                    <div class="field">
                        <div class="field-label">Order Date</div>
                        <div class="field-value"><?php echo escape($steps_by_number[1]['order_date']); ?></div>
                    </div>
                    <div class="field">
                        <div class="field-label">Order Name</div>
                        <div class="field-value"><?php echo escape($steps_by_number[1]['order_name']); ?></div>
                    </div>
                    <div class="field">
                        <div class="field-label">Product Name</div>
                        <div class="field-value"><?php echo escape($steps_by_number[1]['product_name']); ?></div>
                    </div>
                    <div class="field">
                        <div class="field-label">Order Number</div>
                        <div class="field-value"><?php echo escape($steps_by_number[1]['order_number']); ?></div>
                    </div>
                    <div class="field">
                        <div class="field-label">Order Amount</div>
                        <div class="field-value">₹<?php echo number_format($steps_by_number[1]['order_amount'], 2); ?></div>
                    </div>
                    <div class="field">
                        <div class="field-label">Screenshot</div>
                        <div class="field-value">
                            <?php if ($steps_by_number[1]['order_screenshot']): ?>
                                <a href="<?php echo escape($steps_by_number[1]['order_screenshot']); ?>" target="_blank" class="screenshot-link">View Screenshot →</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">Not submitted yet</div>
            <?php endif; ?>
        </div>
        
        <!-- STEP 2 -->
        <div class="step-card <?php echo isset($steps_by_number[2]) && $steps_by_number[2]['step_status'] === 'completed' ? 'completed' : ''; ?>">
            <div class="step-title">
                🚚 Step 2: Order Delivered
                <?php if (isset($steps_by_number[2]) && $steps_by_number[2]['step_status'] === 'completed'): ?>
                    <span class="status-badge status-completed">✓ Done</span>
                <?php else: ?>
                    <span class="status-badge status-pending">Pending</span>
                <?php endif; ?>
            </div>
            
            <?php if (isset($steps_by_number[2])): ?>
                <div class="field">
                    <div class="field-label">Delivery Screenshot</div>
                    <div class="field-value">
                        <?php if ($steps_by_number[2]['delivered_screenshot']): ?>
                            <a href="<?php echo escape($steps_by_number[2]['delivered_screenshot']); ?>" target="_blank" class="screenshot-link">View Screenshot →</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">Not submitted yet</div>
            <?php endif; ?>
        </div>
        
        <!-- STEP 3 -->
        <div class="step-card <?php echo isset($steps_by_number[3]) && $steps_by_number[3]['step_status'] === 'completed' ? 'completed' : ''; ?>">
            <div class="step-title">
                ⭐ Step 3: Review Submitted
                <?php if (isset($steps_by_number[3]) && $steps_by_number[3]['step_status'] === 'completed'): ?>
                    <span class="status-badge status-completed">✓ Done</span>
                <?php else: ?>
                    <span class="status-badge status-pending">Pending</span>
                <?php endif; ?>
            </div>
            
            <?php if (isset($steps_by_number[3])): ?>
                <div class="field">
                    <div class="field-label">Review Screenshot</div>
                    <div class="field-value">
                        <?php if ($steps_by_number[3]['review_submitted_screenshot']): ?>
                            <a href="<?php echo escape($steps_by_number[3]['review_submitted_screenshot']); ?>" target="_blank" class="screenshot-link">View Screenshot →</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">Not submitted yet</div>
            <?php endif; ?>
        </div>
        
        <!-- STEP 4 -->
        <div class="step-card <?php echo $task['refund_requested'] ? 'completed' : ''; ?>">
            <div class="step-title">
                💰 Step 4: Review Live & Refund
                <?php if ($task['refund_requested']): ?>
                    <span class="status-badge status-completed">✓ Done</span>
                <?php else: ?>
                    <span class="status-badge status-pending">Pending</span>
                <?php endif; ?>
            </div>
            
            <?php if (isset($steps_by_number[4])): ?>
                <div class="step-content">
                    <div class="field">
                        <div class="field-label">Review Live Screenshot</div>
                        <div class="field-value">
                            <?php if ($steps_by_number[4]['review_live_screenshot']): ?>
                                <a href="<?php echo escape($steps_by_number[4]['review_live_screenshot']); ?>" target="_blank" class="screenshot-link">View Screenshot →</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($steps_by_number[4]['payment_screenshot']): ?>
                        <div class="field">
                            <div class="field-label">Payment Screenshot</div>
                            <div class="field-value">
                                <a href="<?php echo escape($steps_by_number[4]['payment_screenshot']); ?>" target="_blank" class="screenshot-link">View Payment Proof →</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">Not submitted yet</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
