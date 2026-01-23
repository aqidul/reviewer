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

// Check if task_id is provided
if (!isset($_GET['task_id']) || empty($_GET['task_id'])) {
    redirect('tasks.php');
}

$task_id = intval($_GET['task_id']);

// Fetch task details
try {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ? AND status = 'assigned'");
    $stmt->execute([$task_id, $user_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        die("Task not found or already started!");
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = sanitizeInput($_POST['order_id']);
    $order_date = sanitizeInput($_POST['order_date']);
    $order_amount = floatval($_POST['order_amount']);
    $payment_method = sanitizeInput($_POST['payment_method']);
    $transaction_id = sanitizeInput($_POST['transaction_id']);
    
    if (empty($order_id) || empty($order_date) || empty($order_amount) || empty($payment_method)) {
        $error = "Please fill all required fields!";
    } elseif ($order_amount <= 0) {
        $error = "Order amount must be greater than 0!";
    } else {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Update task with order details
            $stmt = $pdo->prepare("UPDATE tasks SET 
                                  order_id = ?, 
                                  order_date = ?, 
                                  order_amount = ?, 
                                  payment_method = ?, 
                                  transaction_id = ?, 
                                  status = 'step1_completed', 
                                  step1_completed_at = NOW() 
                                  WHERE id = ? AND user_id = ?");
            $stmt->execute([$order_id, $order_date, $order_amount, $payment_method, $transaction_id, $task_id, $user_id]);
            
            // Insert into order_history
            $stmt = $pdo->prepare("INSERT INTO order_history 
                                  (task_id, order_id, step, details, created_at) 
                                  VALUES (?, ?, 'step1', ?, NOW())");
            $details = json_encode([
                'order_date' => $order_date,
                'order_amount' => $order_amount,
                'payment_method' => $payment_method,
                'transaction_id' => $transaction_id
            ]);
            $stmt->execute([$task_id, $order_id, $details]);
            
            $pdo->commit();
            $success = "Order details submitted successfully! You can now proceed to Step 2.";
            
            // Refresh task data
            $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ?");
            $stmt->execute([$task_id, $user_id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Failed to submit order: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Step 1: Submit Order - User Panel</title>
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
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container mt-4">
        <h2 class="mb-4">🛒 Step 1: Submit Order Details</h2>
        
        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step active">
                <div class="step-number">1</div>
                <div>Order Details</div>
                <div class="step-line"></div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div>Submit Review</div>
                <div class="step-line"></div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div>Upload Screenshots</div>
                <div class="step-line"></div>
            </div>
            <div class="step">
                <div class="step-number">₹</div>
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
                    <a href="update_order.php?order_id=<?php echo urlencode($task['order_id']); ?>&step=2" class="btn btn-success">
                        Proceed to Step 2 →
                    </a>
                    <a href="tasks.php" class="btn btn-secondary">Back to Tasks</a>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!$success): ?>
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Order Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="order_id" class="form-label">Order ID *</label>
                                        <input type="text" class="form-control" id="order_id" name="order_id" 
                                               placeholder="e.g., OD123456789" required
                                               value="<?php echo isset($_POST['order_id']) ? htmlspecialchars($_POST['order_id']) : ''; ?>">
                                        <small class="text-muted">Your order ID from <?php echo htmlspecialchars($task['platform']); ?></small>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="order_date" class="form-label">Order Date *</label>
                                        <input type="date" class="form-control" id="order_date" name="order_date" 
                                               max="<?php echo date('Y-m-d'); ?>" required
                                               value="<?php echo isset($_POST['order_date']) ? htmlspecialchars($_POST['order_date']) : date('Y-m-d'); ?>">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="order_amount" class="form-label">Order Amount (₹) *</label>
                                        <input type="number" class="form-control" id="order_amount" name="order_amount" 
                                               step="0.01" min="0.01" required
                                               placeholder="e.g., 499.99"
                                               value="<?php echo isset($_POST['order_amount']) ? htmlspecialchars($_POST['order_amount']) : ''; ?>">
                                        <small class="text-muted">Total amount paid including taxes</small>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="payment_method" class="form-label">Payment Method *</label>
                                        <select class="form-select" id="payment_method" name="payment_method" required>
                                            <option value="">-- Select Method --</option>
                                            <option value="Credit Card" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'Credit Card') ? 'selected' : ''; ?>>Credit Card</option>
                                            <option value="Debit Card" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'Debit Card') ? 'selected' : ''; ?>>Debit Card</option>
                                            <option value="UPI" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'UPI') ? 'selected' : ''; ?>>UPI</option>
                                            <option value="Net Banking" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'Net Banking') ? 'selected' : ''; ?>>Net Banking</option>
                                            <option value="Cash on Delivery" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'Cash on Delivery') ? 'selected' : ''; ?>>Cash on Delivery</option>
                                            <option value="Wallet" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'Wallet') ? 'selected' : ''; ?>>Wallet</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="transaction_id" class="form-label">Transaction ID / Reference Number</label>
                                    <input type="text" class="form-control" id="transaction_id" name="transaction_id" 
                                           placeholder="e.g., TXN123456789"
                                           value="<?php echo isset($_POST['transaction_id']) ? htmlspecialchars($_POST['transaction_id']) : ''; ?>">
                                    <small class="text-muted">Required for online payments (optional for COD)</small>
                                </div>
                                
                                <div class="alert alert-info">
                                    <h6><strong>Task Details:</strong></h6>
                                    <p><strong>Platform:</strong> <?php echo htmlspecialchars($task['platform']); ?></p>
                                    <p><strong>Product Link:</strong> <a href="<?php echo htmlspecialchars($task['product_link']); ?>" target="_blank">View Product</a></p>
                                    <p><strong>Deadline:</strong> <?php echo date('d-m-Y', strtotime($task['deadline'])); ?></p>
                                    <?php if (!empty($task['instructions'])): ?>
                                        <p><strong>Instructions:</strong> <?php echo htmlspecialchars($task['instructions']); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Submit Order Details</button>
                                <a href="tasks.php" class="btn btn-secondary">Cancel</a>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">💡 Instructions</h5>
                        </div>
                        <div class="card-body">
                            <ol>
                                <li>Place order on <?php echo htmlspecialchars($task['platform']); ?> using the provided link</li>
                                <li>Wait for order confirmation</li>
                                <li>Fill in the exact order details as shown in your order confirmation</li>
                                <li>Double-check all information before submitting</li>
                                <li>Click "Submit Order Details" to proceed to Step 2</li>
                            </ol>
                            <div class="alert alert-warning">
                                <strong>Note:</strong> Do not proceed to next step until you have received the product.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
