<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$task_id = intval($_GET['task_id'] ?? 0);
$errors = [];
$success_message = '';

if ($task_id <= 0) {
    header('Location: ' . ADMIN_URL . '/task-pending.php');
    exit;
}

try {
    // Fetch task with user details
    $stmt = $pdo->prepare("
        SELECT t.*, u.name as user_name, u.email, u.mobile 
        FROM tasks t
        JOIN users u ON t.user_id = u.id
        WHERE t.id = :task_id
    ");
    $stmt->execute([':task_id' => $task_id]);
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
    die('Database error');
}

// Function to upload image to palians.com
function uploadToImageHost($file) {
    $cfile = new CURLFile(
        $file['tmp_name'],
        $file['type'],
        $file['name']
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://palians.com/image-host/upload.php');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['image' => $cfile]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && !empty($response)) {
        $lines = explode("\n", trim($response));
        if (!empty($lines[0])) {
            return ['success' => true, 'url' => $lines[0]];
        }
    }
    return ['success' => false];
}

// Handle Step Approval
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Handle Step 4 Refund Processing
    if ($action === 'process_refund') {
        $refund_amount = floatval($_POST['refund_amount'] ?? 0);
        $admin_payment_screenshot = '';
        
        if ($refund_amount <= 0) {
            $errors[] = 'Please enter valid refund amount';
        }
        
        // Upload admin payment screenshot
        if (isset($_FILES['admin_payment_screenshot']) && $_FILES['admin_payment_screenshot']['error'] === UPLOAD_ERR_OK) {
            $upload_result = uploadToImageHost($_FILES['admin_payment_screenshot']);
            if ($upload_result['success']) {
                $admin_payment_screenshot = $upload_result['url'];
            } else {
                $errors[] = 'Failed to upload payment screenshot';
            }
        } else {
            $errors[] = 'Payment screenshot is required';
        }
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Update step 4
                $stmt = $pdo->prepare("
                    UPDATE task_steps SET 
                        refund_amount = :amount,
                        admin_payment_screenshot = :screenshot,
                        step_status = 'completed',
                        refund_processed_at = NOW(),
                        refund_processed_by = :admin
                    WHERE task_id = :task_id AND step_number = 4
                ");
                $stmt->execute([
                    ':amount' => $refund_amount,
                    ':screenshot' => $admin_payment_screenshot,
                    ':admin' => $admin_name,
                    ':task_id' => $task_id
                ]);
                
                // Mark task as completed
                $stmt = $pdo->prepare("UPDATE tasks SET task_status = 'completed' WHERE id = :task_id");
                $stmt->execute([':task_id' => $task_id]);
                
                $pdo->commit();
                
                logActivity("Processed refund ₹$refund_amount for Task #$task_id", $task_id, null);
                $success_message = "Refund of ₹$refund_amount processed successfully!";
                
                // Refresh data
                $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :task_id ORDER BY step_number");
                $stmt->execute([':task_id' => $task_id]);
                $steps = $stmt->fetchAll();
                $steps_by_number = [];
                foreach ($steps as $s) {
                    $steps_by_number[$s['step_number']] = $s;
                }
                
                // Refresh task
                $stmt = $pdo->prepare("SELECT t.*, u.name as user_name, u.email, u.mobile FROM tasks t JOIN users u ON t.user_id = u.id WHERE t.id = :task_id");
                $stmt->execute([':task_id' => $task_id]);
                $task = $stmt->fetch();
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
    
    // Handle Step Approval (1, 2, 3)
    if ($action === 'approve_step') {
        $step_number = intval($_POST['step_number'] ?? 0);
        if ($step_number >= 1 && $step_number <= 3) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE task_steps SET step_status = 'completed' 
                    WHERE task_id = :task_id AND step_number = :step
                ");
                $stmt->execute([':task_id' => $task_id, ':step' => $step_number]);
                
                logActivity("Approved Step $step_number for Task #$task_id", $task_id, null);
                $success_message = "Step $step_number approved!";
                
                // Refresh steps
                $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :task_id ORDER BY step_number");
                $stmt->execute([':task_id' => $task_id]);
                $steps = $stmt->fetchAll();
                $steps_by_number = [];
                foreach ($steps as $s) {
                    $steps_by_number[$s['step_number']] = $s;
                }
            } catch (PDOException $e) {
                $errors[] = 'Failed to approve step';
            }
        }
    }
}

// Helper functions
function getStepStatus($step_data) {
    if (!$step_data) return ['class' => 'pending', 'text' => 'Not Submitted'];
    $status = $step_data['step_status'] ?? 'pending';
    switch ($status) {
        case 'completed': return ['class' => 'completed', 'text' => '✓ Completed'];
        case 'pending_admin': return ['class' => 'pending-admin', 'text' => '⏳ Pending Admin'];
        default: return ['class' => 'pending', 'text' => '○ Pending'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task #<?php echo $task_id; ?> Details - Admin</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/bootstrap.min.css">
    <style>
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
        }
        .sidebar-brand {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
        }
        .sidebar-menu a {
            color: #bbb;
            text-decoration: none;
            padding: 12px 15px;
            display: block;
            border-radius: 8px;
            margin-bottom: 8px;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .admin-content {
            padding: 30px;
        }
        
        /* Task Header */
        .task-header-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .task-title {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        .user-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .info-item {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
        }
        .info-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 3px;
        }
        .info-value {
            font-weight: 600;
            color: #2c3e50;
        }
        
        /* Step Cards */
        .step-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 5px solid #e74c3c;
        }
        .step-card.completed {
            border-left-color: #27ae60;
        }
        .step-card.pending-admin {
            border-left-color: #f39c12;
        }
        .step-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .step-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }
        .step-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .step-badge.completed {
            background: #d4edda;
            color: #155724;
        }
        .step-badge.pending-admin {
            background: #fff3cd;
            color: #856404;
        }
        .step-badge.pending {
            background: #f8d7da;
            color: #721c24;
        }
        .step-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .field-group {
            margin-bottom: 10px;
        }
        .field-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        .field-value {
            font-weight: 500;
            color: #333;
        }
        .screenshot-link {
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
        }
        .screenshot-link:hover {
            text-decoration: underline;
        }
        
        /* QR Code Display */
        .qr-display-section {
            background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 25px;
            margin: 20px 0;
            text-align: center;
        }
        .qr-display-section h4 {
            color: #ff8f00;
            margin-bottom: 15px;
        }
        .qr-image {
            max-width: 280px;
            max-height: 280px;
            border: 4px solid #27ae60;
            border-radius: 12px;
            padding: 10px;
            background: white;
        }
        
        /* Refund Form */
        .refund-form-section {
            background: #e8f5e9;
            border: 2px solid #4caf50;
            border-radius: 12px;
            padding: 25px;
            margin-top: 20px;
        }
        .refund-form-section h4 {
            color: #2e7d32;
            margin-bottom: 20px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-control:focus {
            border-color: #4caf50;
            outline: none;
        }
        .btn-process {
            padding: 12px 30px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-process:hover {
            background: #219a52;
        }
        .btn-approve {
            padding: 8px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-approve:hover {
            background: #2980b9;
        }
        
        /* Alerts */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Refund Completed Section */
        .refund-completed {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            border: 2px solid #4caf50;
            border-radius: 12px;
            padding: 25px;
            margin-top: 20px;
        }
        .refund-completed h4 {
            color: #2e7d32;
            margin-bottom: 15px;
        }
        .refund-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .refund-amount-display {
            font-size: 28px;
            font-weight: 700;
            color: #27ae60;
        }
        
        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .back-btn:hover {
            background: #5a6268;
            color: white;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <div class="admin-sidebar">
            <div class="sidebar-brand">
                <h3>⚙️ Admin</h3>
            </div>
            <ul class="sidebar-menu">
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
        
        <!-- Main Content -->
        <div class="admin-content">
            <a href="<?php echo ADMIN_URL; ?>/task-pending.php" class="back-btn">← Back to Pending Tasks</a>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">✓ <?php echo escape($success_message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger">✗ <?php echo escape($error); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Task Header -->
            <div class="task-header-card">
                <div class="task-title">📋 Task #<?php echo $task_id; ?></div>
                <div class="user-info-grid">
                    <div class="info-item">
                        <div class="info-label">Reviewer Name</div>
                        <div class="info-value"><?php echo escape($task['user_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo escape($task['email']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Mobile</div>
                        <div class="info-value"><?php echo escape($task['mobile']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Task Status</div>
                        <div class="info-value"><?php echo strtoupper(str_replace('_', ' ', $task['task_status'])); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- STEP 1 -->
            <?php $step1 = $steps_by_number[1] ?? null; $step1_status = getStepStatus($step1); ?>
            <div class="step-card <?php echo $step1_status['class']; ?>">
                <div class="step-header">
                    <div class="step-title">📦 Step 1: Order Placed</div>
                    <span class="step-badge <?php echo $step1_status['class']; ?>"><?php echo $step1_status['text']; ?></span>
                </div>
                
                <?php if ($step1): ?>
                    <div class="step-content">
                        <div class="field-group">
                            <div class="field-label">Order Number</div>
                            <div class="field-value"><strong><?php echo escape($step1['order_number'] ?? 'N/A'); ?></strong></div>
                        </div>
                        <div class="field-group">
                            <div class="field-label">Order Name</div>
                            <div class="field-value"><?php echo escape($step1['order_name'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="field-group">
                            <div class="field-label">Product Name</div>
                            <div class="field-value"><?php echo escape($step1['product_name'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="field-group">
                            <div class="field-label">Order Amount</div>
                            <div class="field-value">₹<?php echo number_format($step1['order_amount'] ?? 0, 2); ?></div>
                        </div>
                        <div class="field-group">
                            <div class="field-label">Order Date</div>
                            <div class="field-value"><?php echo $step1['order_date'] ? date('d M Y', strtotime($step1['order_date'])) : 'N/A'; ?></div>
                        </div>
                        <div class="field-group">
                            <div class="field-label">Screenshot</div>
                            <div class="field-value">
                                <?php if (!empty($step1['order_screenshot'])): ?>
                                    <a href="<?php echo escape($step1['order_screenshot']); ?>" target="_blank" class="screenshot-link">View Screenshot →</a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($step1['step_status'] !== 'completed'): ?>
                        <form method="POST" style="margin-top: 15px;">
                            <input type="hidden" name="action" value="approve_step">
                            <input type="hidden" name="step_number" value="1">
                            <button type="submit" class="btn-approve">✓ Approve Step 1</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color: #999;">User has not submitted this step yet.</p>
                <?php endif; ?>
            </div>
            
            <!-- STEP 2 -->
            <?php $step2 = $steps_by_number[2] ?? null; $step2_status = getStepStatus($step2); ?>
            <div class="step-card <?php echo $step2_status['class']; ?>">
                <div class="step-header">
                    <div class="step-title">🚚 Step 2: Order Delivered</div>
                    <span class="step-badge <?php echo $step2_status['class']; ?>"><?php echo $step2_status['text']; ?></span>
                </div>
                
                <?php if ($step2): ?>
                    <div class="field-group">
                        <div class="field-label">Delivery Screenshot</div>
                        <div class="field-value">
                            <?php if (!empty($step2['delivered_screenshot'])): ?>
                                <a href="<?php echo escape($step2['delivered_screenshot']); ?>" target="_blank" class="screenshot-link">View Screenshot →</a>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($step2['step_status'] !== 'completed'): ?>
                        <form method="POST" style="margin-top: 15px;">
                            <input type="hidden" name="action" value="approve_step">
                            <input type="hidden" name="step_number" value="2">
                            <button type="submit" class="btn-approve">✓ Approve Step 2</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color: #999;">User has not submitted this step yet.</p>
                <?php endif; ?>
            </div>
            
            <!-- STEP 3 -->
            <?php $step3 = $steps_by_number[3] ?? null; $step3_status = getStepStatus($step3); ?>
            <div class="step-card <?php echo $step3_status['class']; ?>">
                <div class="step-header">
                    <div class="step-title">⭐ Step 3: Review Submitted</div>
                    <span class="step-badge <?php echo $step3_status['class']; ?>"><?php echo $step3_status['text']; ?></span>
                </div>
                
                <?php if ($step3): ?>
                    <div class="field-group">
                        <div class="field-label">Review Screenshot</div>
                        <div class="field-value">
                            <?php if (!empty($step3['review_submitted_screenshot'])): ?>
                                <a href="<?php echo escape($step3['review_submitted_screenshot']); ?>" target="_blank" class="screenshot-link">View Screenshot →</a>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($step3['step_status'] !== 'completed'): ?>
                        <form method="POST" style="margin-top: 15px;">
                            <input type="hidden" name="action" value="approve_step">
                            <input type="hidden" name="step_number" value="3">
                            <button type="submit" class="btn-approve">✓ Approve Step 3</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color: #999;">User has not submitted this step yet.</p>
                <?php endif; ?>
            </div>
            
            <!-- STEP 4 -->
            <?php $step4 = $steps_by_number[4] ?? null; $step4_status = getStepStatus($step4); ?>
            <div class="step-card <?php echo $step4_status['class']; ?>">
                <div class="step-header">
                    <div class="step-title">💰 Step 4: Refund Request</div>
                    <span class="step-badge <?php echo $step4_status['class']; ?>"><?php echo $step4_status['text']; ?></span>
                </div>
                
                <?php if ($step4): ?>
                    <!-- Review Live Screenshot -->
                    <div class="field-group">
                        <div class="field-label">Review Live Screenshot</div>
                        <div class="field-value">
                            <?php if (!empty($step4['review_live_screenshot'])): ?>
                                <a href="<?php echo escape($step4['review_live_screenshot']); ?>" target="_blank" class="screenshot-link">View Screenshot →</a>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- QR Code Display - Direct Image -->
                    <?php if (!empty($step4['payment_qr_code'])): ?>
                        <div class="qr-display-section">
                            <h4>📱 User's Payment QR Code (Scan to Send Refund)</h4>
                            <img src="<?php echo escape($step4['payment_qr_code']); ?>" alt="Payment QR Code" class="qr-image">
                            <p style="margin-top: 10px; font-size: 13px; color: #666;">
                                <a href="<?php echo escape($step4['payment_qr_code']); ?>" target="_blank">Open Full Size →</a>
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- If Refund Already Processed -->
                    <?php if ($step4['step_status'] === 'completed' && !empty($step4['refund_amount'])): ?>
                        <div class="refund-completed">
                            <h4>✅ Refund Processed Successfully</h4>
                            <div class="refund-details">
                                <div class="field-group">
                                    <div class="field-label">Refund Amount</div>
                                    <div class="refund-amount-display">₹<?php echo number_format($step4['refund_amount'], 2); ?></div>
                                </div>
                                <div class="field-group">
                                    <div class="field-label">Payment Screenshot</div>
                                    <div class="field-value">
                                        <a href="<?php echo escape($step4['admin_payment_screenshot']); ?>" target="_blank" class="screenshot-link">View Payment Proof →</a>
                                    </div>
                                </div>
                                <div class="field-group">
                                    <div class="field-label">Processed By</div>
                                    <div class="field-value"><?php echo escape($step4['refund_processed_by'] ?? 'Admin'); ?></div>
                                </div>
                                <div class="field-group">
                                    <div class="field-label">Processed At</div>
                                    <div class="field-value"><?php echo $step4['refund_processed_at'] ? date('d M Y, h:i A', strtotime($step4['refund_processed_at'])) : 'N/A'; ?></div>
                                </div>
                            </div>
                        </div>
                    
                    <!-- Refund Processing Form -->
                    <?php elseif ($step4['step_status'] === 'pending_admin'): ?>
                        <div class="refund-form-section">
                            <h4>💳 Process Refund Payment</h4>
                            <p style="color: #666; margin-bottom: 20px;">Scan the QR code above, send the refund amount, then fill the details below.</p>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="process_refund">
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="refund_amount">Refund Amount (₹) *</label>
                                        <input type="number" id="refund_amount" name="refund_amount" class="form-control" 
                                               step="0.01" min="1" placeholder="Enter refund amount" required
                                               value="<?php echo $step1['order_amount'] ?? ''; ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="admin_payment_screenshot">Payment Screenshot (Proof) *</label>
                                        <input type="file" id="admin_payment_screenshot" name="admin_payment_screenshot" 
                                               class="form-control" accept="image/*" required>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn-process">
                                    ✓ Mark Refund as Completed
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <p style="color: #999;">User has not submitted refund request yet.</p>
                <?php endif; ?>
            </div>
            
        </div>
    </div>
</body>
</html>
