<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$task_id = intval($_GET['task_id'] ?? 0);
$step = intval($_GET['step'] ?? 0);
$errors = [];
$success = false;

try {
    // Fetch task and user details
    $stmt = $pdo->prepare("
        SELECT t.*, u.name, u.email, u.mobile 
        FROM tasks t
        JOIN users u ON t.user_id = u.id
        WHERE t.id = :task_id
    ");
    $stmt->execute([':task_id' => $task_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        die('Task not found');
    }
    
    // Fetch all steps for this task
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

// Handle step approval
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('CSRF error');
    }
    
    $approve_step = intval($_POST['approve_step'] ?? 0);
    
    if ($approve_step < 1 || $approve_step > 4) {
        $errors[] = 'Invalid step number';
    }
    
    if (empty($errors)) {
        try {
            if ($approve_step == 4) {
                // Refund step - requires payment screenshot
                $payment_screenshot = '';
                
                if (isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] === UPLOAD_ERR_OK) {
                    $upload = uploadFile($_FILES['payment_screenshot'], 'refund_screenshots');
                    if ($upload['success']) {
                        $payment_screenshot = $upload['url'];
                    } else {
                        $errors[] = $upload['message'];
                    }
                } else {
                    $errors[] = 'Payment screenshot is required for refund approval';
                }
                
                if (empty($errors)) {
                    $stmt = $pdo->prepare("
                        UPDATE task_steps 
                        SET step_status = 'completed', payment_screenshot = :screenshot, 
                            submitted_by_admin = true
                        WHERE task_id = :task_id AND step_number = 4
                    ");
                    
                    $stmt->execute([
                        ':task_id' => $task_id,
                        ':screenshot' => $payment_screenshot
                    ]);
                    
                    // Mark task as completed
                    $stmt = $pdo->prepare("UPDATE tasks SET task_status = 'completed' WHERE id = :task_id");
                    $stmt->execute([':task_id' => $task_id]);
                    
                    logActivity('Admin approved Refund/Step 4', $task_id, $task['user_id']);
                    $success = true;
                }
            } else {
                // Steps 1-3
                $stmt = $pdo->prepare("
                    UPDATE task_steps 
                    SET step_status = 'completed', submitted_by_admin = true
                    WHERE task_id = :task_id AND step_number = :step
                ");
                
                $stmt->execute([
                    ':task_id' => $task_id,
                    ':step' => $approve_step
                ]);
                
                logActivity('Admin approved Step ' . $approve_step, $task_id, $task['user_id']);
                $success = true;
                
                // Refresh steps
                $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :task_id ORDER BY step_number");
                $stmt->execute([':task_id' => $task_id]);
                $steps = $stmt->fetchAll();
                $steps_by_number = [];
                foreach ($steps as $s) {
                    $steps_by_number[$s['step_number']] = $s;
                }
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors[] = 'Approval failed';
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
    <title>Task Detail - Admin</title>
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
        .detail-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .step-section {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .step-section.completed {
            border-color: #27ae60;
            background: #f0fdf4;
        }
        .step-title {
            font-weight: 600;
            color: #2c3e50;
            font-size: 16px;
            margin-bottom: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            font-weight: 600;
            display: block;
            margin-bottom: 5px;
            color: #333;
        }
        .screenshot-preview {
            max-width: 300px;
            max-height: 200px;
            border-radius: 8px;
            margin: 10px 0;
            cursor: pointer;
            border: 1px solid #ddd;
            transition: transform 0.3s;
        }
        .screenshot-preview:hover {
            transform: scale(1.05);
        }
        .btn-approve {
            padding: 10px 20px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-approve:hover {
            background: #229954;
        }
        .btn-back {
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
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
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-completed {
            background: #27ae60;
            color: white;
        }
        .badge-pending {
            background: #e74c3c;
            color: white;
        }
        .image-url {
            font-size: 12px;
            color: #3498db;
            word-break: break-all;
            margin-top: 5px;
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
            <h1 style="color: #2c3e50; margin-bottom: 30px;">📋 Task #<?php echo $task_id; ?> Details</h1>
            
            <div class="detail-card">
                <h3>👤 Reviewer Information</h3>
                <p><strong>Name:</strong> <?php echo escape($task['name']); ?></p>
                <p><strong>Email:</strong> <?php echo escape($task['email']); ?></p>
                <p><strong>Mobile:</strong> <?php echo escape($task['mobile']); ?></p>
                <p><strong>Product Link:</strong> <a href="<?php echo escape($task['product_link']); ?>" target="_blank">Visit</a></p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✓ Step approved successfully!</div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger">✗ <?php echo escape($error); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- DEBUG: Check what data we have -->
            <?php if (isset($steps_by_number[1])): ?>
                <!-- <pre style="display:none;"><?php print_r($steps_by_number[1]); ?></pre> -->
            <?php endif; ?>
            
            <!-- STEP 1 -->
            <div class="step-section <?php echo isset($steps_by_number[1]) && $steps_by_number[1]['step_status'] === 'completed' ? 'completed' : ''; ?>">
                <div class="step-title">
                    Step 1: Order Placed 
                    <?php if (isset($steps_by_number[1]) && $steps_by_number[1]['step_status'] === 'completed'): ?>
                        <span class="badge badge-completed">✓ DONE</span>
                    <?php else: ?>
                        <span class="badge badge-pending">PENDING</span>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($steps_by_number[1])): ?>
                    <div class="form-group">
                        <label>Order Date:</label>
                        <strong><?php echo escape($steps_by_number[1]['order_date']); ?></strong>
                    </div>
                    <div class="form-group">
                        <label>Order Name:</label>
                        <strong><?php echo escape($steps_by_number[1]['order_name']); ?></strong>
                    </div>
                    <div class="form-group">
                        <label>Product Name:</label>
                        <strong><?php echo escape($steps_by_number[1]['product_name']); ?></strong>
                    </div>
                    <div class="form-group">
                        <label>Order Number:</label>
                        <strong><?php echo escape($steps_by_number[1]['order_number']); ?></strong>
                    </div>
                    <div class="form-group">
                        <label>Order Amount:</label>
                        <strong>₹<?php echo number_format($steps_by_number[1]['order_amount'], 2); ?></strong>
                    </div>
                    <div class="form-group">
                        <label>Order Screenshot:</label>
                        <?php if (!empty($steps_by_number[1]['order_screenshot'])): ?>
                            <br>
                            <?php 
                            $image_url = $steps_by_number[1]['order_screenshot'];
                            // Check if URL is valid
                            if (filter_var($image_url, FILTER_VALIDATE_URL)) {
                                // Display image with link
                                echo '<a href="' . escape($image_url) . '" target="_blank">';
                                echo '<img src="' . escape($image_url) . '" alt="Order Screenshot" class="screenshot-preview">';
                                echo '</a>';
                                echo '<div class="image-url">';
                                echo '<a href="' . escape($image_url) . '" target="_blank">' . escape($image_url) . '</a>';
                                echo '</div>';
                            } elseif (strpos($image_url, 'https://') === 0 || strpos($image_url, 'http://') === 0) {
                                // URL-like but not valid, still show as link
                                echo '<a href="' . escape($image_url) . '" target="_blank" style="color: #e74c3c;">';
                                echo 'View Image (URL might be invalid)';
                                echo '</a>';
                                echo '<div class="image-url">' . escape($image_url) . '</div>';
                            } else {
                                echo '<p style="color: #e74c3c;">Invalid image URL stored in database</p>';
                                echo '<div class="image-url">Database value: ' . escape($image_url) . '</div>';
                            }
                            ?>
                        <?php else: ?>
                            <p style="color: #999;">No screenshot uploaded</p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($steps_by_number[1]['step_status'] !== 'completed'): ?>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="approve_step" value="1">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <button type="submit" class="btn-approve">✓ Approve Step 1</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color: #999;">User has not submitted this step yet</p>
                <?php endif; ?>
            </div>
            
            <!-- STEP 2 -->
            <div class="step-section <?php echo isset($steps_by_number[2]) && $steps_by_number[2]['step_status'] === 'completed' ? 'completed' : ''; ?>">
                <div class="step-title">
                    Step 2: Order Delivered
                    <?php if (isset($steps_by_number[2]) && $steps_by_number[2]['step_status'] === 'completed'): ?>
                        <span class="badge badge-completed">✓ DONE</span>
                    <?php else: ?>
                        <span class="badge badge-pending">PENDING</span>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($steps_by_number[2])): ?>
                    <div class="form-group">
                        <label>Delivery Screenshot:</label>
                        <?php if (!empty($steps_by_number[2]['delivered_screenshot'])): ?>
                            <br>
                            <?php 
                            $image_url = $steps_by_number[2]['delivered_screenshot'];
                            if (filter_var($image_url, FILTER_VALIDATE_URL)) {
                                echo '<a href="' . escape($image_url) . '" target="_blank">';
                                echo '<img src="' . escape($image_url) . '" alt="Delivery Screenshot" class="screenshot-preview">';
                                echo '</a>';
                                echo '<div class="image-url">';
                                echo '<a href="' . escape($image_url) . '" target="_blank">' . escape($image_url) . '</a>';
                                echo '</div>';
                            } elseif (!empty($image_url)) {
                                echo '<a href="' . escape($image_url) . '" target="_blank">View Image</a>';
                                echo '<div class="image-url">' . escape($image_url) . '</div>';
                            }
                            ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($steps_by_number[2]['step_status'] !== 'completed'): ?>
                        <form method="POST">
                            <input type="hidden" name="approve_step" value="2">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <button type="submit" class="btn-approve">✓ Approve Step 2</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color: #999;">User has not submitted this step yet</p>
                <?php endif; ?>
            </div>
            
            <!-- STEP 3 -->
            <div class="step-section <?php echo isset($steps_by_number[3]) && $steps_by_number[3]['step_status'] === 'completed' ? 'completed' : ''; ?>">
                <div class="step-title">
                    Step 3: Review Submitted
                    <?php if (isset($steps_by_number[3]) && $steps_by_number[3]['step_status'] === 'completed'): ?>
                        <span class="badge badge-completed">✓ DONE</span>
                    <?php else: ?>
                        <span class="badge badge-pending">PENDING</span>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($steps_by_number[3])): ?>
                    <div class="form-group">
                        <label>Review Submitted Screenshot:</label>
                        <?php if (!empty($steps_by_number[3]['review_submitted_screenshot'])): ?>
                            <br>
                            <?php 
                            $image_url = $steps_by_number[3]['review_submitted_screenshot'];
                            if (filter_var($image_url, FILTER_VALIDATE_URL)) {
                                echo '<a href="' . escape($image_url) . '" target="_blank">';
                                echo '<img src="' . escape($image_url) . '" alt="Review Screenshot" class="screenshot-preview">';
                                echo '</a>';
                                echo '<div class="image-url">';
                                echo '<a href="' . escape($image_url) . '" target="_blank">' . escape($image_url) . '</a>';
                                echo '</div>';
                            } elseif (!empty($image_url)) {
                                echo '<a href="' . escape($image_url) . '" target="_blank">View Image</a>';
                                echo '<div class="image-url">' . escape($image_url) . '</div>';
                            }
                            ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($steps_by_number[3]['step_status'] !== 'completed'): ?>
                        <form method="POST">
                            <input type="hidden" name="approve_step" value="3">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <button type="submit" class="btn-approve">✓ Approve Step 3</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color: #999;">User has not submitted this step yet</p>
                <?php endif; ?>
            </div>
            
            <!-- STEP 4 - REFUND -->
            <div class="step-section <?php echo $task['refund_requested'] ? 'completed' : ''; ?>">
                <div class="step-title">
                    Step 4: Review Live & Refund Request
                    <?php if ($task['refund_requested']): ?>
                        <span class="badge badge-completed">✓ DONE</span>
                    <?php else: ?>
                        <span class="badge badge-pending">PENDING</span>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($steps_by_number[4])): ?>
                    <div class="form-group">
                        <label>Review Live Screenshot:</label>
                        <?php if (!empty($steps_by_number[4]['review_live_screenshot'])): ?>
                            <br>
                            <?php 
                            $image_url = $steps_by_number[4]['review_live_screenshot'];
                            if (filter_var($image_url, FILTER_VALIDATE_URL)) {
                                echo '<a href="' . escape($image_url) . '" target="_blank">';
                                echo '<img src="' . escape($image_url) . '" alt="Review Live Screenshot" class="screenshot-preview">';
                                echo '</a>';
                                echo '<div class="image-url">';
                                echo '<a href="' . escape($image_url) . '" target="_blank">' . escape($image_url) . '</a>';
                                echo '</div>';
                            } elseif (!empty($image_url)) {
                                echo '<a href="' . escape($image_url) . '" target="_blank">View Image</a>';
                                echo '<div class="image-url">' . escape($image_url) . '</div>';
                            }
                            ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!$task['refund_requested'] || (isset($steps_by_number[4]) && $steps_by_number[4]['step_status'] !== 'completed')): ?>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="payment_screenshot">Payment/Refund Screenshot *</label>
                                <input type="file" id="payment_screenshot" name="payment_screenshot" accept="image/*" required>
                                <small>Upload proof of payment to user</small>
                            </div>
                            
                            <input type="hidden" name="approve_step" value="4">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <button type="submit" class="btn-approve">✓ Approve & Send Refund</button>
                        </form>
                    <?php else: ?>
                        <div class="form-group">
                            <label>Refund/Payment Screenshot:</label>
                            <?php if (!empty($steps_by_number[4]['payment_screenshot'])): ?>
                                <br>
                                <?php 
                                $image_url = $steps_by_number[4]['payment_screenshot'];
                                if (filter_var($image_url, FILTER_VALIDATE_URL)) {
                                    echo '<a href="' . escape($image_url) . '" target="_blank">';
                                    echo '<img src="' . escape($image_url) . '" alt="Payment Screenshot" class="screenshot-preview">';
                                    echo '</a>';
                                    echo '<div class="image-url">';
                                    echo '<a href="' . escape($image_url) . '" target="_blank">' . escape($image_url) . '</a>';
                                    echo '</div>';
                                } elseif (!empty($image_url)) {
                                    echo '<a href="' . escape($image_url) . '" target="_blank">View Image</a>';
                                    echo '<div class="image-url">' . escape($image_url) . '</div>';
                                }
                                ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color: #999;">User has not requested refund yet</p>
                <?php endif; ?>
            </div>
            
            <a href="<?php echo ADMIN_URL; ?>/task-pending.php" class="btn-back">← Back to Pending Tasks</a>
        </div>
    </div>
    
    <script>
        // Image preview click to open in new tab
        document.addEventListener('DOMContentLoaded', function() {
            const images = document.querySelectorAll('.screenshot-preview');
            images.forEach(img => {
                img.addEventListener('click', function() {
                    window.open(this.src, '_blank');
                });
            });
        });
    </script>
</body>
</html>
