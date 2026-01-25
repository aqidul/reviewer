<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/upload-helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$task_id = intval($_GET['task_id'] ?? 0);
$errors = [];
$success = false;

try {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :task_id AND user_id = :user_id");
    $stmt->execute([':task_id' => $task_id, ':user_id' => $user_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        die('Invalid task');
    }
    
    if ($task['refund_requested']) {
        header('Location: ' . APP_URL . '/user/task-detail.php?task_id=' . $task_id);
        exit;
    }
    
    // Check Step 3 completed
    $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :task_id AND step_number = 3 AND step_status = 'completed'");
    $stmt->execute([':task_id' => $task_id]);
    if ($stmt->rowCount() === 0) {
        header('Location: ' . APP_URL . '/user/task-detail.php?task_id=' . $task_id . '&error=complete_step3');
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :task_id AND step_number = 4");
    $stmt->execute([':task_id' => $task_id]);
    $step_data = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    die('Database error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $review_live_screenshot = $step_data['review_live_screenshot'] ?? '';
    $payment_qr_code = $step_data['payment_qr_code'] ?? '';
    
    // Upload Review Screenshot
    if (isset($_FILES['review_live_screenshot']) && $_FILES['review_live_screenshot']['error'] === UPLOAD_ERR_OK) {
        $result = uploadImageFast($_FILES['review_live_screenshot']);
        if ($result['success']) {
            $review_live_screenshot = $result['url'];
        } else {
            $errors[] = 'Review Screenshot: ' . $result['error'];
        }
    } elseif (empty($review_live_screenshot)) {
        $errors[] = 'Review Live Screenshot is required';
    }
    
    // Upload QR Code
    if (isset($_FILES['payment_qr_code']) && $_FILES['payment_qr_code']['error'] === UPLOAD_ERR_OK) {
        $result = uploadImageFast($_FILES['payment_qr_code'], 800, 90); // QR needs higher quality
        if ($result['success']) {
            $payment_qr_code = $result['url'];
        } else {
            $errors[] = 'QR Code: ' . $result['error'];
        }
    } elseif (empty($payment_qr_code)) {
        $errors[] = 'Payment QR Code is required';
    }
    
    // Save to database
    if (empty($errors) && !empty($review_live_screenshot) && !empty($payment_qr_code)) {
        try {
            $pdo->beginTransaction();
            
            if ($step_data) {
                $stmt = $pdo->prepare("
                    UPDATE task_steps SET 
                        review_live_screenshot = :review,
                        payment_qr_code = :qr,
                        step_status = 'pending_admin',
                        submitted_by_user = true,
                        updated_at = NOW()
                    WHERE task_id = :task_id AND step_number = 4
                ");
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO task_steps (task_id, step_number, step_name, review_live_screenshot, payment_qr_code, step_status, submitted_by_user)
                    VALUES (:task_id, 4, 'Refund Request', :review, :qr, 'pending_admin', true)
                ");
            }
            
            $stmt->execute([
                ':task_id' => $task_id,
                ':review' => $review_live_screenshot,
                ':qr' => $payment_qr_code
            ]);
            
            $stmt = $pdo->prepare("UPDATE tasks SET refund_requested = true WHERE id = :task_id");
            $stmt->execute([':task_id' => $task_id]);
            
            $pdo->commit();
            $success = true;
            
            // Refresh
            $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :task_id AND step_number = 4");
            $stmt->execute([':task_id' => $task_id]);
            $step_data = $stmt->fetch();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Save failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#667eea">
    <title>Step 4: Refund Request - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/bootstrap.min.css">
    <style>
        * { box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 15px;
            margin: 0;
        }
        .form-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .form-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #333;
            text-align: center;
        }
        .step-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
        }
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #856404;
        }
        .alert {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        
        /* Upload Section */
        .upload-section {
            background: #f8f9fa;
            border: 2px dashed #ddd;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .upload-section:hover, .upload-section:focus-within {
            border-color: #667eea;
            background: #f0f4ff;
        }
        .upload-section.qr-section {
            background: #fffbeb;
            border-color: #f59e0b;
        }
        .upload-section.qr-section:hover {
            border-color: #d97706;
        }
        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .section-icon { font-size: 24px; }
        .section-title {
            font-weight: 600;
            color: #333;
            font-size: 15px;
        }
        .section-desc {
            font-size: 12px;
            color: #666;
            margin-bottom: 12px;
            line-height: 1.5;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            background: white;
        }
        .form-control:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
        }
        .file-info {
            font-size: 11px;
            color: #888;
            margin-top: 8px;
        }
        
        /* Preview */
        .preview-box {
            margin-top: 12px;
            padding: 10px;
            background: white;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            text-align: center;
        }
        .preview-box img {
            max-width: 100%;
            max-height: 150px;
            border-radius: 8px;
            border: 2px solid #27ae60;
        }
        .preview-box.qr-preview img {
            max-width: 180px;
            padding: 5px;
            background: white;
        }
        
        /* Submit Button */
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(39,174,96,0.4);
        }
        .btn-submit:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Back Links */
        .back-links {
            margin-top: 20px;
            text-align: center;
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .back-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }
        
        /* Success Box */
        .success-box {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 2px solid #27ae60;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 20px;
        }
        .success-box .icon { font-size: 45px; margin-bottom: 10px; }
        .success-box h3 { color: #155724; font-size: 20px; margin-bottom: 8px; }
        .success-box p { color: #155724; font-size: 14px; margin: 5px 0; }
        
        .dashboard-btn {
            display: block;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 12px;
        }
        .dashboard-btn.green {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
        }
        
        /* Loading Overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        .loading-overlay.show { display: flex; }
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        .loading-text {
            color: white;
            margin-top: 15px;
            font-size: 16px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Mobile */
        @media (max-width: 480px) {
            body { padding: 10px; }
            .form-container { padding: 18px; border-radius: 15px; }
            .form-title { font-size: 19px; }
            .upload-section { padding: 15px; }
            .section-title { font-size: 14px; }
            .preview-box img { max-height: 120px; }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Uploading images...</div>
    </div>

    <div class="form-container">
        <h2 class="form-title">💰 Step 4: Request Refund</h2>
        
        <?php if ($success): ?>
            <div class="success-box">
                <div class="icon">🎉</div>
                <h3>Request Submitted!</h3>
                <p>Admin will verify and send refund to your QR.</p>
            </div>
            <a href="<?php echo APP_URL; ?>/user/task-detail.php?task_id=<?php echo $task_id; ?>" class="dashboard-btn">
                📋 View Task
            </a>
            <a href="<?php echo APP_URL; ?>/user/" class="dashboard-btn green">
                ← Dashboard
            </a>
        <?php else: ?>
            <div class="step-info">
                <strong>📌 Task #<?php echo $task_id; ?></strong><br>
                <small>Upload both images to complete</small>
            </div>
            
            <div class="warning-box">
                ⚠️ <strong>Important:</strong> After submitting, admin will review and send refund to your QR Code.
            </div>
            
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger">✗ <?php echo escape($error); ?></div>
            <?php endforeach; ?>
            
            <form method="POST" enctype="multipart/form-data" id="refundForm">
                <!-- Review Screenshot -->
                <div class="upload-section">
                    <div class="section-header">
                        <span class="section-icon">📸</span>
                        <span class="section-title">Review Live Screenshot *</span>
                    </div>
                    <div class="section-desc">
                        Screenshot showing your review is live on the product page
                    </div>
                    <input type="file" name="review_live_screenshot" class="form-control" 
                           accept="image/*" capture="environment"
                           <?php echo empty($step_data['review_live_screenshot']) ? 'required' : ''; ?>>
                    <div class="file-info">📎 Max 5MB • JPG, PNG, WebP</div>
                    
                    <?php if (!empty($step_data['review_live_screenshot'])): ?>
                        <div class="preview-box">
                            <small>✓ Uploaded</small><br>
                            <img src="<?php echo escape($step_data['review_live_screenshot']); ?>" alt="Review">
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- QR Code -->
                <div class="upload-section qr-section">
                    <div class="section-header">
                        <span class="section-icon">📱</span>
                        <span class="section-title">Your Payment QR Code *</span>
                    </div>
                    <div class="section-desc">
                        Upload your <strong>UPI/GPay/PhonePe/Paytm QR</strong><br>
                        <span style="color:#d97706">⚡ Make sure QR is clear!</span>
                    </div>
                    <input type="file" name="payment_qr_code" class="form-control" 
                           accept="image/*" capture="environment"
                           <?php echo empty($step_data['payment_qr_code']) ? 'required' : ''; ?>>
                    <div class="file-info">📎 Max 5MB • JPG, PNG, WebP</div>
                    
                    <?php if (!empty($step_data['payment_qr_code'])): ?>
                        <div class="preview-box qr-preview">
                            <small>✓ Your QR</small><br>
                            <img src="<?php echo escape($step_data['payment_qr_code']); ?>" alt="QR Code">
                        </div>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="btn-submit" id="submitBtn">
                    🔒 Submit & Request Refund
                </button>
                
                <div class="back-links">
                    <a href="<?php echo APP_URL; ?>/user/" class="back-link">← Dashboard</a>
                    <a href="<?php echo APP_URL; ?>/user/task-detail.php?task_id=<?php echo $task_id; ?>" class="back-link">View Task →</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
    
    <script>
        // Show loading on form submit
        document.getElementById('refundForm')?.addEventListener('submit', function(e) {
            const files = this.querySelectorAll('input[type="file"]');
            let hasFile = false;
            files.forEach(f => { if(f.files.length > 0) hasFile = true; });
            
            if (hasFile) {
                document.getElementById('loadingOverlay').classList.add('show');
                document.getElementById('submitBtn').disabled = true;
            }
        });
        
        // File size validation
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file && file.size > 5 * 1024 * 1024) {
                    alert('File too large! Max 5MB allowed.');
                    e.target.value = '';
                }
            });
        });
    </script>
</body>
</html>
