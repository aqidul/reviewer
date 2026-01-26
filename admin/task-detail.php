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
$success = '';

if ($task_id <= 0) {
    header('Location: ' . ADMIN_URL . '/task-pending.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT t.*, u.name as user_name, u.email, u.mobile FROM tasks t JOIN users u ON t.user_id = u.id WHERE t.id = :id");
    $stmt->execute([':id' => $task_id]);
    $task = $stmt->fetch();
    
    if (!$task) { header('Location: ' . ADMIN_URL . '/task-pending.php'); exit; }
    
    $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :id ORDER BY step_number");
    $stmt->execute([':id' => $task_id]);
    $steps = $stmt->fetchAll();
    $step = [];
    foreach ($steps as $s) $step[$s['step_number']] = $s;
} catch (PDOException $e) {
    die('Error');
}

// Handle refund processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_refund'])) {
    $refund_amount = floatval($_POST['refund_amount'] ?? 0);
    $payment_ss = '';
    
    if ($refund_amount <= 0) {
        $errors[] = 'Enter valid refund amount';
    }
    
    if (isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] === UPLOAD_ERR_OK) {
        $cfile = new CURLFile($_FILES['payment_screenshot']['tmp_name'], $_FILES['payment_screenshot']['type'], $_FILES['payment_screenshot']['name']);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://palians.com/image-host/upload.php',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['image' => $cfile],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 120
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && !empty($response)) {
            $lines = explode("\n", trim($response));
            if (!empty($lines[0]) && strpos($lines[0], 'http') === 0) {
                $payment_ss = $lines[0];
            }
        }
        if (empty($payment_ss)) $errors[] = 'Payment screenshot upload failed';
    } else {
        $errors[] = 'Payment screenshot required';
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                UPDATE task_steps SET 
                    refund_amount = :amount,
                    admin_payment_screenshot = :screenshot,
                    step_status = 'completed',
                    refund_processed_at = NOW(),
                    refund_processed_by = :admin_id
                WHERE task_id = :task_id AND step_number = 4
            ");
            $stmt->execute([
                ':amount' => $refund_amount,
                ':screenshot' => $payment_ss,
                ':admin_id' => $_SESSION['admin_id'],
                ':task_id' => $task_id
            ]);
            
            $stmt = $pdo->prepare("UPDATE tasks SET task_status = 'completed' WHERE id = :id");
            $stmt->execute([':id' => $task_id]);
            
            $pdo->commit();
            $success = 'Refund processed successfully!';
            
            // Refresh data
            $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :id ORDER BY step_number");
            $stmt->execute([':id' => $task_id]);
            $steps = $stmt->fetchAll();
            $step = [];
            foreach ($steps as $s) $step[$s['step_number']] = $s;
            
            $stmt = $pdo->prepare("SELECT t.*, u.name as user_name, u.email, u.mobile FROM tasks t JOIN users u ON t.user_id = u.id WHERE t.id = :id");
            $stmt->execute([':id' => $task_id]);
            $task = $stmt->fetch();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$step4 = $step[4] ?? null;
$refund_done = $step4 && $step4['step_status'] === 'completed';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task #<?php echo $task_id; ?> - Admin</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:#f5f5f5;font-family:-apple-system,sans-serif}
        .wrapper{display:grid;grid-template-columns:220px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(135deg,#2c3e50,#1a252f);color:#fff;padding:20px}
        .sidebar h3{text-align:center;margin-bottom:30px;padding-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1)}
        .sidebar ul{list-style:none}
        .sidebar a{color:#bbb;text-decoration:none;padding:12px 15px;display:block;border-radius:8px;margin-bottom:8px}
        .sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,0.1);color:#fff}
        .content{padding:25px}
        .back-btn{display:inline-block;padding:10px 20px;background:#6c757d;color:#fff;text-decoration:none;border-radius:8px;margin-bottom:20px}
        .card{background:#fff;border-radius:12px;padding:20px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,0.1)}
        .card-title{font-size:18px;font-weight:600;color:#2c3e50;margin-bottom:15px;padding-bottom:10px;border-bottom:1px solid #eee}
        .info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px}
        .info-item{padding:10px;background:#f8f9fa;border-radius:8px}
        .info-label{font-size:11px;color:#888;text-transform:uppercase}
        .info-value{font-weight:600;color:#2c3e50;margin-top:3px}
        .step-card{border-left:4px solid #e74c3c}
        .step-card.done{border-left-color:#27ae60}
        .step-card.pending{border-left-color:#f39c12}
        .status-badge{padding:5px 12px;border-radius:15px;font-size:12px;font-weight:600}
        .status-done{background:#d4edda;color:#155724}
        .status-pending{background:#fff3cd;color:#856404}
        .screenshot-link{color:#3498db;text-decoration:none;font-weight:600}
        .qr-section{background:linear-gradient(135deg,#fff8e1,#ffecb3);border:2px solid #ffc107;border-radius:12px;padding:25px;text-align:center;margin:20px 0}
        .qr-section h4{color:#ff8f00;margin-bottom:15px}
        .qr-section img{max-width:280px;border:4px solid #27ae60;border-radius:12px;padding:10px;background:#fff}
        .refund-form{background:#e8f5e9;border:2px solid #4caf50;border-radius:12px;padding:25px;margin-top:20px}
        .refund-form h4{color:#2e7d32;margin-bottom:20px}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}
        .form-group label{display:block;font-weight:600;margin-bottom:8px;color:#333}
        .form-group input{width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:14px}
        .btn-submit{padding:15px 30px;background:#27ae60;color:#fff;border:none;border-radius:8px;font-weight:600;font-size:16px;cursor:pointer}
        .btn-submit:hover{background:#219a52}
        .alert{padding:15px;border-radius:8px;margin-bottom:20px}
        .alert-success{background:#d4edda;color:#155724}
        .alert-danger{background:#f8d7da;color:#721c24}
        .refund-done{background:linear-gradient(135deg,#e8f5e9,#c8e6c9);border:2px solid #4caf50;border-radius:12px;padding:25px;margin-top:20px}
        .refund-done h4{color:#2e7d32;margin-bottom:15px}
        .refund-amount{font-size:32px;font-weight:700;color:#27ae60}
        @media(max-width:768px){.wrapper{grid-template-columns:1fr}.sidebar{display:none}.form-row{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrapper">
    <div class="sidebar">
        <h3>⚙️ Admin</h3>
        <ul>
            <li><a href="<?php echo ADMIN_URL; ?>/dashboard.php">📊 Dashboard</a></li>
            <li><a href="<?php echo ADMIN_URL; ?>/reviewers.php">👥 Reviewers</a></li>
            <li><a href="<?php echo ADMIN_URL; ?>/task-pending.php" class="active">📋 Pending Tasks</a></li>
            <li><a href="<?php echo ADMIN_URL; ?>/task-completed.php">✓ Completed Tasks</a></li>
            <li><a href="<?php echo ADMIN_URL; ?>/faq-manager.php">🤖 Chatbot FAQ</a></li>
            <li style="margin-top:30px;border-top:1px solid rgba(255,255,255,0.1);padding-top:20px">
                <a href="<?php echo APP_URL; ?>/logout.php" style="color:#e74c3c">🚪 Logout</a>
            </li>
        </ul>
    </div>
    <div class="content">
        <a href="<?php echo ADMIN_URL; ?>/task-pending.php" class="back-btn">← Back</a>
        
        <?php if ($success): ?><div class="alert alert-success">✓ <?php echo $success; ?></div><?php endif; ?>
        <?php foreach ($errors as $e): ?><div class="alert alert-danger">✗ <?php echo escape($e); ?></div><?php endforeach; ?>
        
        <!-- Task Header -->
        <div class="card">
            <div class="card-title">📋 Task #<?php echo $task_id; ?></div>
            <div class="info-grid">
                <div class="info-item"><div class="info-label">Reviewer</div><div class="info-value"><?php echo escape($task['user_name']); ?></div></div>
                <div class="info-item"><div class="info-label">Email</div><div class="info-value"><?php echo escape($task['email']); ?></div></div>
                <div class="info-item"><div class="info-label">Mobile</div><div class="info-value"><?php echo escape($task['mobile']); ?></div></div>
                <div class="info-item"><div class="info-label">Status</div><div class="info-value"><?php echo $refund_done ? '✓ Completed' : '⏳ Pending'; ?></div></div>
            </div>
        </div>
        
        <!-- Step 1 -->
        <?php $s1 = $step[1] ?? null; ?>
        <div class="card step-card <?php echo ($s1 && $s1['step_status'] === 'completed') ? 'done' : ''; ?>">
            <div class="card-title" style="display:flex;justify-content:space-between">
                <span>📦 Step 1: Order Placed</span>
                <span class="status-badge <?php echo ($s1 && $s1['step_status'] === 'completed') ? 'status-done' : 'status-pending'; ?>">
                    <?php echo ($s1 && $s1['step_status'] === 'completed') ? '✓ Completed' : 'Pending'; ?>
                </span>
            </div>
            <?php if ($s1): ?>
            <div class="info-grid">
                <div class="info-item"><div class="info-label">Order Number</div><div class="info-value"><?php echo escape($s1['order_number'] ?? '-'); ?></div></div>
                <div class="info-item"><div class="info-label">Order Name</div><div class="info-value"><?php echo escape($s1['order_name'] ?? '-'); ?></div></div>
                <div class="info-item"><div class="info-label">Product</div><div class="info-value"><?php echo escape($s1['product_name'] ?? '-'); ?></div></div>
                <div class="info-item"><div class="info-label">Amount</div><div class="info-value">₹<?php echo number_format($s1['order_amount'] ?? 0, 2); ?></div></div>
                <div class="info-item"><div class="info-label">Date</div><div class="info-value"><?php echo $s1['order_date'] ? date('d M Y', strtotime($s1['order_date'])) : '-'; ?></div></div>
                <div class="info-item"><div class="info-label">Screenshot</div><div class="info-value"><?php echo !empty($s1['order_screenshot']) ? '<a href="'.escape($s1['order_screenshot']).'" target="_blank" class="screenshot-link">View →</a>' : '-'; ?></div></div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Step 2 -->
        <?php $s2 = $step[2] ?? null; ?>
        <div class="card step-card <?php echo ($s2 && $s2['step_status'] === 'completed') ? 'done' : ''; ?>">
            <div class="card-title" style="display:flex;justify-content:space-between">
                <span>🚚 Step 2: Order Delivered</span>
                <span class="status-badge <?php echo ($s2 && $s2['step_status'] === 'completed') ? 'status-done' : 'status-pending'; ?>">
                    <?php echo ($s2 && $s2['step_status'] === 'completed') ? '✓ Completed' : 'Pending'; ?>
                </span>
            </div>
            <?php if ($s2 && !empty($s2['delivered_screenshot'])): ?>
                <div class="info-item"><div class="info-label">Screenshot</div><div class="info-value"><a href="<?php echo escape($s2['delivered_screenshot']); ?>" target="_blank" class="screenshot-link">View Screenshot →</a></div></div>
            <?php endif; ?>
        </div>
        
        <!-- Step 3 -->
        <?php $s3 = $step[3] ?? null; ?>
        <div class="card step-card <?php echo ($s3 && $s3['step_status'] === 'completed') ? 'done' : ''; ?>">
            <div class="card-title" style="display:flex;justify-content:space-between">
                <span>⭐ Step 3: Review Submitted</span>
                <span class="status-badge <?php echo ($s3 && $s3['step_status'] === 'completed') ? 'status-done' : 'status-pending'; ?>">
                    <?php echo ($s3 && $s3['step_status'] === 'completed') ? '✓ Completed' : 'Pending'; ?>
                </span>
            </div>
            <?php if ($s3 && !empty($s3['review_submitted_screenshot'])): ?>
                <div class="info-item"><div class="info-label">Screenshot</div><div class="info-value"><a href="<?php echo escape($s3['review_submitted_screenshot']); ?>" target="_blank" class="screenshot-link">View Screenshot →</a></div></div>
            <?php endif; ?>
        </div>
        
        <!-- Step 4 -->
        <div class="card step-card <?php echo $refund_done ? 'done' : 'pending'; ?>">
            <div class="card-title" style="display:flex;justify-content:space-between">
                <span>💰 Step 4: Refund Request</span>
                <span class="status-badge <?php echo $refund_done ? 'status-done' : 'status-pending'; ?>">
                    <?php echo $refund_done ? '✓ Refund Sent' : '⏳ Pending'; ?>
                </span>
            </div>
            
            <?php if ($step4 && !empty($step4['review_live_screenshot'])): ?>
                <div class="info-item" style="margin-bottom:15px">
                    <div class="info-label">Review Live Screenshot</div>
                    <div class="info-value"><a href="<?php echo escape($step4['review_live_screenshot']); ?>" target="_blank" class="screenshot-link">View Screenshot →</a></div>
                </div>
            <?php endif; ?>
            
            <!-- QR Code Display -->
            <?php if ($step4 && !empty($step4['payment_qr_code'])): ?>
                <div class="qr-section">
                    <h4>📱 User's Payment QR Code (Scan to Send Refund)</h4>
                    <img src="<?php echo escape($step4['payment_qr_code']); ?>" alt="Payment QR Code">
                    <p style="margin-top:10px"><a href="<?php echo escape($step4['payment_qr_code']); ?>" target="_blank" class="screenshot-link">Open Full Size →</a></p>
                </div>
            <?php endif; ?>
            
            <?php if ($refund_done): ?>
                <!-- Refund Already Done -->
                <div class="refund-done">
                    <h4>✅ Refund Processed</h4>
                    <div class="info-grid">
                        <div class="info-item"><div class="info-label">Refund Amount</div><div class="refund-amount">₹<?php echo number_format($step4['refund_amount'], 2); ?></div></div>
                        <div class="info-item"><div class="info-label">Payment Proof</div><div class="info-value"><a href="<?php echo escape($step4['admin_payment_screenshot']); ?>" target="_blank" class="screenshot-link">View Screenshot →</a></div></div>
                        <div class="info-item"><div class="info-label">Processed By</div><div class="info-value"><?php echo escape($step4['refund_processed_by']); ?></div></div>
                        <div class="info-item"><div class="info-label">Processed On</div><div class="info-value"><?php echo date('d M Y, h:i A', strtotime($step4['refund_processed_at'])); ?></div></div>
                    </div>
                </div>
            <?php elseif ($step4): ?>
                <!-- Refund Form -->
                <div class="refund-form">
                    <h4>💳 Process Refund Payment</h4>
                    <p style="color:#666;margin-bottom:20px">Scan the QR code above, send the refund, then fill details below.</p>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Refund Amount (₹) *</label>
                                <input type="number" name="refund_amount" step="0.01" min="1" required value="<?php echo $step[1]['order_amount'] ?? ''; ?>" placeholder="Enter amount">
                            </div>
                            <div class="form-group">
                                <label>Payment Screenshot (Proof) *</label>
                                <input type="file" name="payment_screenshot" accept="image/*" required>
                            </div>
                        </div>
                        <button type="submit" name="process_refund" class="btn-submit">✓ Mark Refund as Completed</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
