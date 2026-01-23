<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/db_connection.php';

if (!isLoggedIn() || isAdmin()) {
    redirect('../index.php');
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Check if order_id is provided
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    redirect('tasks.php');
}

$order_id = sanitizeInput($_GET['order_id']);
$step = isset($_GET['step']) ? intval($_GET['step']) : 0;

// Fetch task details
try {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE order_id = ? AND user_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        die("Task not found!");
    }
    
    // Determine which step to show
    if ($step == 0) {
        // Auto-determine current step based on status
        switch ($task['status']) {
            case 'step1_completed': $step = 2; break;
            case 'step2_completed': $step = 3; break;
            case 'step3_completed': $step = 4; break;
            default: $step = 2; break;
        }
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle form submission based on step
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_step = intval($_POST['current_step']);
    
    switch ($current_step) {
        case 2: // Step 2: Review
            $review_text = sanitizeInput($_POST['review_text']);
            $review_rating = intval($_POST['review_rating']);
            $review_date = sanitizeInput($_POST['review_date']);
            
            if (empty($review_text) || empty($review_rating) || empty($review_date)) {
                $error = "Please fill all required fields for review!";
            } elseif ($review_rating < 1 || $review_rating > 5) {
                $error = "Rating must be between 1 and 5 stars!";
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    $stmt = $pdo->prepare("UPDATE tasks SET 
                                          review_text = ?, 
                                          review_rating = ?, 
                                          review_date = ?, 
                                          status = 'step2_completed', 
                                          step2_completed_at = NOW() 
                                          WHERE order_id = ? AND user_id = ?");
                    $stmt->execute([$review_text, $review_rating, $review_date, $order_id, $user_id]);
                    
                    // Log in history
                    $stmt = $pdo->prepare("INSERT INTO order_history 
                                          (task_id, order_id, step, details, created_at) 
                                          VALUES (?, ?, 'step2', ?, NOW())");
                    $details = json_encode([
                        'review_rating' => $review_rating,
                        'review_date' => $review_date
                    ]);
                    $stmt->execute([$task['id'], $order_id, $details]);
                    
                    $pdo->commit();
                    $success = "Review submitted successfully! You can now proceed to Step 3.";
                    $step = 3;
                    
                    // Refresh task data
                    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE order_id = ? AND user_id = ?");
                    $stmt->execute([$order_id, $user_id]);
                    $task = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "Failed to submit review: " . $e->getMessage();
                }
            }
            break;
            
        case 3: // Step 3: Screenshots
            // Handle file uploads
            $upload_dir = '../uploads/';
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            $screenshot1 = $task['screenshot1'];
            $screenshot2 = $task['screenshot2'];
            
            // Upload screenshot 1
            if (isset($_FILES['screenshot1']) && $_FILES['screenshot1']['error'] == 0) {
                $file = $_FILES['screenshot1'];
                
                if (!in_array($file['type'], $allowed_types)) {
                    $error = "Only JPG, PNG, GIF, and WebP images are allowed!";
                } elseif ($file['size'] > $max_size) {
                    $error = "File size must be less than 5MB!";
                } else {
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'screenshot_' . $order_id . '_1_' . time() . '.' . $ext;
                    $destination = $upload_dir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        $screenshot1 = $filename;
                    } else {
                        $error = "Failed to upload screenshot 1!";
                    }
                }
            }
            
            // Upload screenshot 2
            if (empty($error) && isset($_FILES['screenshot2']) && $_FILES['screenshot2']['error'] == 0) {
                $file = $_FILES['screenshot2'];
                
                if (!in_array($file['type'], $allowed_types)) {
                    $error = "Only JPG, PNG, GIF, and WebP images are allowed!";
                } elseif ($file['size'] > $max_size) {
                    $error = "File size must be less than 5MB!";
                } else {
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'screenshot_' . $order_id . '_2_' . time() . '.' . $ext;
                    $destination = $upload_dir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        $screenshot2 = $filename;
                    } else {
                        $error = "Failed to upload screenshot 2!";
                    }
                }
            }
            
            if (empty($error)) {
                try {
                    $pdo->beginTransaction();
                    
                    $stmt = $pdo->prepare("UPDATE tasks SET 
                                          screenshot1 = ?, 
                                          screenshot2 = ?, 
                                          status = 'step3_completed', 
                                          step3_completed_at = NOW() 
                                          WHERE order_id = ? AND user_id = ?");
                    $stmt->execute([$screenshot1, $screenshot2, $order_id, $user_id]);
                    
                    // Log in history
                    $stmt = $pdo->prepare("INSERT INTO order_history 
                                          (task_id, order_id, step, details, created_at) 
                                          VALUES (?, ?, 'step3', ?, NOW())");
                    $details = json_encode(['screenshots_uploaded' => date('Y-m-d H:i:s')]);
                    $stmt->execute([$task['id'], $order_id, $details]);
                    
                    $pdo->commit();
                    $success = "Screenshots uploaded successfully! You can now request refund.";
                    $step = 4;
                    
                    // Refresh task data
                    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE order_id = ? AND user_id = ?");
                    $stmt->execute([$order_id, $user_id]);
                    $task = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "Failed to save screenshots: " . $e->getMessage();
                }
            }
            break;
            
        case 4: // Step 4: Refund Request
            $account_name = sanitizeInput($_POST['account_name']);
            $account_number = sanitizeInput($_POST['account_number']);
            $bank_name = sanitizeInput($_POST['bank_name']);
            $ifsc_code = sanitizeInput($_POST['ifsc_code']);
            $upi_id = sanitizeInput($_POST['upi_id']);
            
            // Validate at least one payment method
            if (empty($account_number) && empty($upi_id)) {
                $error = "Please provide either bank account details or UPI ID!";
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // Update user's payment info
                    $stmt = $pdo->prepare("UPDATE users SET 
                                          account_name = ?, 
                                          account_number = ?, 
                                          bank_name = ?, 
                                          ifsc_code = ?, 
                                          upi_id = ? 
                                          WHERE id = ?");
                    $stmt->execute([$account_name, $account_number, $bank_name, $ifsc_code, $upi_id, $user_id]);
                    
                    // Update task status
                    $stmt = $pdo->prepare("UPDATE tasks SET 
                                          status = 'refund_requested', 
                                          refund_request_date = NOW() 
                                          WHERE order_id = ? AND user_id = ?");
                    $stmt->execute([$order_id, $user_id]);
                    
                    // Log in history
                    $stmt = $pdo->prepare("INSERT INTO order_history 
                                          (task_id, order_id, step, details, created_at) 
                                          VALUES (?, ?, 'refund_request', ?, NOW())");
                    $details = json_encode([
                        'refund_request_date' => date('Y-m-d H:i:s'),
                        'payment_method' => !empty($upi_id) ? 'UPI' : 'Bank Transfer'
                    ]);
                    $stmt->execute([$task['id'], $order_id, $details]);
                    
                    $pdo->commit();
                    $success = "Refund request submitted successfully! Admin will process your refund soon.";
                    
                    // Refresh task data
                    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE order_id = ? AND user_id = ?");
                    $stmt->execute([$order_id, $user_id]);
                    $task = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "Failed to submit refund request: " . $e->getMessage();
                }
            }
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Order - User Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .step {
            text-align: center;
            flex: 1;
            position: relative;
        }
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #6c757d;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
        }
        .step.active .step-number {
            background-color: #0d6efd;
        }
        .step.completed .step-number {
            background-color: #198754;
        }
        .step.pending .step-number {
            background-color: #6c757d;
        }
        .step-line {
            position: absolute;
            top: 20px;
            left: 50%;
            width: 100%;
            height: 2px;
            background-color: #dee2e6;
            z-index: -1;
        }
        .step:last-child .step-line {
            display: none;
        }
        .rating-stars {
            font-size: 2rem;
            cursor: pointer;
        }
        .rating-stars .star {
            color: #ddd;
            transition: color 0.2s;
        }
        .rating-stars .star.active {
            color: #ffc107;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container mt-4">
        <h2 class="mb-4">
            <?php 
            echo $step == 2 ? '📝 Step 2: Submit Review' : 
                 ($step == 3 ? '🖼️ Step 3: Upload Screenshots' : 
                 '💰 Step 4: Request Refund');
            ?>
        </h2>
        
        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step <?php echo $step >= 1 ? 'completed' : ''; ?>">
                <div class="step-number">✓</div>
                <div>Order Details</div>
                <div class="step-line"></div>
            </div>
            <div class="step <?php echo $step == 2 ? 'active' : ($step > 2 ? 'completed' : 'pending'); ?>">
                <div class="step-number"><?php echo $step > 2 ? '✓' : '2'; ?></div>
                <div>Submit Review</div>
                <div class="step-line"></div>
            </div>
            <div class="step <?php echo $step == 3 ? 'active' : ($step > 3 ? 'completed' : 'pending'); ?>">
                <div class="step-number"><?php echo $step > 3 ? '✓' : '3'; ?></div>
                <div>Upload Screenshots</div>
                <div class="step-line"></div>
            </div>
            <div class="step <?php echo $step == 4 ? 'active' : ($task['status'] == 'completed' ? 'completed' : 'pending'); ?>">
                <div class="step-number"><?php echo $task['status'] == 'completed' ? '✓' : '₹'; ?></div>
                <div>Request Refund</div>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
                <div class="mt-2">
                    <?php if ($step == 3 && $task['status'] == 'step2_completed'): ?>
                        <a href="?order_id=<?php echo urlencode($order_id); ?>&step=3" class="btn btn-success">
                            Proceed to Step 3 →
                        </a>
                    <?php elseif ($step == 4 && $task['status'] == 'step3_completed'): ?>
                        <a href="?order_id=<?php echo urlencode($order_id); ?>&step=4" class="btn btn-success">
                            Request Refund →
                        </a>
                    <?php endif; ?>
                    <a href="tasks.php" class="btn btn-secondary">Back to Tasks</a>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Step 2: Review Form -->
        <?php if ($step == 2 && $task['status'] == 'step1_completed' && !$success): ?>
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Submit Your Review</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="current_step" value="2">
                                
                                <div class="mb-3">
                                    <label for="review_rating" class="form-label">Rating *</label>
                                    <div class="rating-stars mb-2" id="ratingStars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="star" data-value="<?php echo $i; ?>">★</span>
                                        <?php endfor; ?>
                                    </div>
                                    <input type="hidden" name="review_rating" id="review_rating" value="<?php echo isset($_POST['review_rating']) ? $_POST['review_rating'] : '5'; ?>" required>
                                    <small class="text-muted">Click on stars to rate (1-5 stars)</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="review_text" class="form-label">Review Text *</label>
                                    <textarea class="form-control" id="review_text" name="review_text" 
                                              rows="6" placeholder="Write your detailed review here..." required><?php echo isset($_POST['review_text']) ? htmlspecialchars($_POST['review_text']) : ''; ?></textarea>
                                    <small class="text-muted">Minimum 50 characters. Write about product quality, delivery experience, etc.</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="review_date" class="form-label">Review Date *</label>
                                    <input type="date" class="form-control" id="review_date" name="review_date" 
                                           max="<?php echo date('Y-m-d'); ?>" required
                                           value="<?php echo isset($_POST['review_date']) ? htmlspecialchars($_POST['review_date']) : date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="alert alert-info">
                                    <h6><strong>Review Guidelines:</strong></h6>
                                    <ul>
                                        <li>Review must be genuine and detailed</li>
                                        <li>Minimum 50 characters required</li>
                                        <li>Include your experience with product quality</li>
                                        <li>Mention delivery and packaging experience</li>
                                        <li>Do not include personal information</li>
                                    </ul>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Submit Review</button>
                                <a href="tasks.php" class="btn btn-secondary">Cancel</a>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">📋 Order Summary</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Order ID:</strong> <?php echo htmlspecialchars($task['order_id']); ?></p>
                            <p><strong>Order Date:</strong> <?php echo date('d-m-Y', strtotime($task['order_date'])); ?></p>
                            <p><strong>Amount:</strong> ₹<?php echo number_format($task['order_amount'], 2); ?></p>
                            <p><strong>Platform:</strong> <?php echo htmlspecialchars($task['platform']); ?></p>
                            <p><strong>Deadline:</strong> <?php echo date('d-m-Y', strtotime($task['deadline'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
            // Star rating functionality
            document.querySelectorAll('.star').forEach(star => {
                star.addEventListener('click', function() {
                    const value = this.getAttribute('data-value');
                    document.getElementById('review_rating').value = value;
                    
                    // Update star display
                    document.querySelectorAll('.star').forEach(s => {
                        if (s.getAttribute('data-value') <= value) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                });
            });
            
            // Initialize stars
            document.querySelectorAll('.star').forEach(s => {
                if (s.getAttribute('data-value') <= document.getElementById('review_rating').value) {
                    s.classList.add('active');
                }
            });
            </script>
        
        <!-- Step 3: Screenshots Form -->
        <?php elseif ($step == 3 && $task['status'] == 'step2_completed' && !$success): ?>
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Upload Screenshots</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <input type="hidden" name="current_step" value="3">
                                
                                <div class="mb-4">
                                    <label for="screenshot1" class="form-label">Screenshot 1: Order Review *</label>
                                    <input type="file" class="form-control" id="screenshot1" name="screenshot1" accept="image/*" required>
                                    <small class="text-muted">Screenshot of your published review on <?php echo htmlspecialchars($task['platform']); ?></small>
                                    
                                    <?php if ($task['screenshot1']): ?>
                                        <div class="mt-2">
                                            <p class="text-success">✓ Already uploaded</p>
                                            <a href="../uploads/<?php echo htmlspecialchars($task['screenshot1']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">View Current</a>
                                            <small class="text-muted">Upload new file to replace</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="screenshot2" class="form-label">Screenshot 2: Order Details (Optional)</label>
                                    <input type="file" class="form-control" id="screenshot2" name="screenshot2" accept="image/*">
                                    <small class="text-muted">Screenshot of your order details page (optional but recommended)</small>
                                    
                                    <?php if ($task['screenshot2']): ?>
                                        <div class="mt-2">
                                            <p class="text-success">✓ Already uploaded</p>
                                            <a href="../uploads/<?php echo htmlspecialchars($task['screenshot2']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">View Current</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <h6><strong>Screenshot Requirements:</strong></h6>
                                    <ul>
                                        <li>Images must be clear and readable</li>
                                        <li>Maximum file size: 5MB per image</li>
                                        <li>Allowed formats: JPG, PNG, GIF, WebP</li>
                                        <li>Screenshot 1 must show your review with rating</li>
                                        <li>Make sure order ID is visible in screenshot</li>
                                    </ul>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Upload Screenshots</button>
                                <a href="tasks.php" class="btn btn-secondary">Cancel</a>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">📝 Review Summary</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Rating:</strong> 
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= $task['review_rating']): ?>⭐<?php endif; ?>
                                <?php endfor; ?>
                                (<?php echo $task['review_rating']; ?>/5)
                            </p>
                            <p><strong>Review Date:</strong> <?php echo date('d-m-Y', strtotime($task['review_date'])); ?></p>
                            <p><strong>Review Preview:</strong></p>
                            <div class="border p-2 bg-light rounded">
                                <small><?php echo htmlspecialchars(substr($task['review_text'], 0, 100)); ?>...</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        
        <!-- Step 4: Refund Request Form -->
        <?php elseif ($step == 4 && $task['status'] == 'step3_completed' && !$success): ?>
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Request Refund</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="current_step" value="4">
                                
                                <div class="alert alert-success mb-4">
                                    <h6><strong>Congratulations! 🎉</strong></h6>
                                    <p>You have completed all steps. Now provide your payment details to receive refund of <strong>₹<?php echo number_format($task['task_value'], 2); ?></strong>.</p>
                                </div>
                                
                                <h6 class="mb-3">Bank Account Details</h6>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="account_name" class="form-label">Account Holder Name</label>
                                        <input type="text" class="form-control" id="account_name" name="account_name" 
                                               placeholder="John Doe"
                                               value="<?php echo isset($_POST['account_name']) ? htmlspecialchars($_POST['account_name']) : ''; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="account_number" class="form-label">Account Number</label>
                                        <input type="text" class="form-control" id="account_number" name="account_number" 
                                               placeholder="1234567890"
                                               value="<?php echo isset($_POST['account_number']) ? htmlspecialchars($_POST['account_number']) : ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <label for="bank_name" class="form-label">Bank Name</label>
                                        <input type="text" class="form-control" id="bank_name" name="bank_name" 
                                               placeholder="State Bank of India"
                                               value="<?php echo isset($_POST['bank_name']) ? htmlspecialchars($_POST['bank_name']) : ''; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="ifsc_code" class="form-label">IFSC Code</label>
                                        <input type="text" class="form-control" id="ifsc_code" name="ifsc_code" 
                                               placeholder="SBIN0001234"
                                               value="<?php echo isset($_POST['ifsc_code']) ? htmlspecialchars($_POST['ifsc_code']) : ''; ?>">
                                    </div>
                                </div>
                                
                                <h6 class="mb-3">OR</h6>
                                
                                <div class="mb-4">
                                    <label for="upi_id" class="form-label">UPI ID</label>
                                    <input type="text" class="form-control" id="upi_id" name="upi_id" 
                                           placeholder="username@upi"
                                           value="<?php echo isset($_POST['upi_id']) ? htmlspecialchars($_POST['upi_id']) : ''; ?>">
                                    <small class="text-muted">Provide either bank details or UPI ID</small>
                                </div>
                                
                                <div class="alert alert-info">
                                    <h6><strong>Refund Information:</strong></h6>
                                    <ul>
                                        <li>Refund amount: ₹<?php echo number_format($task['task_value'], 2); ?></li>
                                        <li>Refunds are processed within 3-5 business days</li>
                                        <li>You will receive confirmation email after refund</li>
                                        <li>Ensure payment details are correct</li>
                                    </ul>
                                </div>
                                
                                <button type="submit" class="btn btn-success">Submit Refund Request</button>
                                <a href="tasks.php" class="btn btn-secondary">Cancel</a>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">✅ Completion Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <p class="text-success"><strong>✓ Step 1: Order Details</strong></p>
                                <small>Completed on <?php echo date('d-m-Y', strtotime($task['step1_completed_at'])); ?></small>
                            </div>
                            
                            <div class="mb-3">
                                <p class="text-success"><strong>✓ Step 2: Review Submitted</strong></p>
                                <small>Completed on <?php echo date('d-m-Y', strtotime($task['step2_completed_at'])); ?></small>
                            </div>
                            
                            <div class="mb-3">
                                <p class="text-success"><strong>✓ Step 3: Screenshots Uploaded</strong></p>
                                <small>Completed on <?php echo date('d-m-Y', strtotime($task['step3_completed_at'])); ?></small>
                            </div>
                            
                            <div class="mt-4">
                                <p><strong>Total Task Value:</strong></p>
                                <h4 class="text-success">₹<?php echo number_format($task['task_value'], 2); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        
        <?php else: ?>
            <div class="alert alert-warning">
                <p>You cannot access this step yet. Please complete previous steps first.</p>
                <a href="tasks.php" class="btn btn-primary">Go to Tasks</a>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
