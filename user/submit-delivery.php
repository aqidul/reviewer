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
$errors = [];
$success = false;

try {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :task_id AND user_id = :user_id");
    $stmt->execute([':task_id' => $task_id, ':user_id' => $user_id]);
    $task = $stmt->fetch();
    
    if (!$task || $task['refund_requested']) {
        die('Invalid task or read-only mode');
    }
    
    // Check if Step 1 is completed
    $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :task_id AND step_number = 1 AND step_status = 'completed'");
    $stmt->execute([':task_id' => $task_id]);
    if ($stmt->rowCount() === 0) {
        die('Complete Step 1 first');
    }
    
    $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :task_id AND step_number = 2");
    $stmt->execute([':task_id' => $task_id]);
    $step_data = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    die('Database error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('CSRF error');
    }
    
    $delivered_screenshot = $step_data['delivered_screenshot'] ?? '';
    
    if (isset($_FILES['delivered_screenshot']) && $_FILES['delivered_screenshot']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES['delivered_screenshot'], 'delivery_screenshots');
        if ($upload['success']) {
            $delivered_screenshot = $upload['url'];
        } else {
            $errors[] = $upload['message'];
        }
    } else {
        $errors[] = 'Screenshot is required';
    }
    
    if (empty($errors)) {
        try {
            if ($step_data) {
                $stmt = $pdo->prepare("
                    UPDATE task_steps SET delivered_screenshot = :screenshot, step_status = 'completed',
                    submitted_by_user = true, updated_at = NOW() 
                    WHERE task_id = :task_id AND step_number = 2
                ");
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO task_steps (task_id, step_number, step_name, delivered_screenshot, 
                    step_status, submitted_by_user) 
                    VALUES (:task_id, 2, 'Order Delivered', :screenshot, 'completed', true)
                ");
            }
            
            $stmt->execute([
                ':task_id' => $task_id,
                ':screenshot' => $delivered_screenshot
            ]);
            
            logActivity('User submitted Step 2 - Order Delivered', $task_id, $user_id);
            $success = true;
            
            $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :task_id AND step_number = 2");
            $stmt->execute([':task_id' => $task_id]);
            $step_data = $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors[] = 'Save failed';
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
    <title>Step 2: Order Delivered</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .form-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .form-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 30px;
            color: #333;
        }
        .form-group {
            margin-bottom: 20px;
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
        .form-control:focus {
            border-color: #667eea;
            outline: none;
        }
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
        }
        .alert {
            margin-bottom: 20px;
            padding: 12px;
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
        .step-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2 class="form-title">🚚 Step 2: Order Delivered</h2>
        
        <div class="step-info">
            <strong>📌 Task #<?php echo $task_id; ?></strong><br>
            <small>Upload the screenshot proving that your order has been delivered</small>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✓ Delivery details saved! Move to Step 3.</div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger">✗ <?php echo escape($error); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="delivered_screenshot">Delivery Screenshot (Proof of Delivery) *</label>
                <input type="file" id="delivered_screenshot" name="delivered_screenshot" 
                       class="form-control" accept="image/*" required>
                <small>JPG, PNG formats accepted. Max 5MB</small>
                <?php if ($step_data && $step_data['delivered_screenshot']): ?>
                    <br><small>✓ Current: <a href="<?php echo escape($step_data['delivered_screenshot']); ?>" target="_blank">View Screenshot</a></small>
                <?php endif; ?>
            </div>
            
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <button type="submit" class="btn-submit">Submit Delivery Proof</button>
            
            <a href="<?php echo APP_URL; ?>/user/" class="back-link">← Back to Dashboard</a>
        </form>
    </div>
</body>
</html>
