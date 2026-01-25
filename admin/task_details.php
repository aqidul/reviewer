<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/db_connection.php';

if (!isAdmin()) {
    redirect('../index.php');
}

if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    redirect('pending_tasks.php');
}

$order_id = sanitizeInput($_GET['order_id']);
$error = '';
$success = '';

// Fetch task details with user info
try {
    $stmt = $pdo->prepare("SELECT t.*, u.username, u.email, u.phone, u.payment_method 
                           FROM tasks t 
                           JOIN users u ON t.user_id = u.id 
                           WHERE t.order_id = ?");
    $stmt->execute([$order_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        die("Task not found!");
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle step completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_step'])) {
    $step = sanitizeInput($_POST['step']);
    $new_status = '';
    
    switch ($step) {
        case 'step1':
            $new_status = 'step1_completed';
            break;
        case 'step2':
            $new_status = 'step2_completed';
            break;
        case 'step3':
            $new_status = 'step3_completed';
            break;
        case 'refund':
            $new_status = 'completed';
            // Update refund date
            try {
                $stmt = $pdo->prepare("UPDATE tasks SET refund_date = NOW() WHERE order_id = ?");
                $stmt->execute([$order_id]);
            } catch (PDOException $e) {
                $error = "Failed to update refund date: " . $e->getMessage();
            }
            break;
    }
    
    if (!empty($new_status)) {
        try {
            $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE order_id = ?");
            $stmt->execute([$new_status, $order_id]);
            $success = "Step marked as completed!";
            
            // Refresh task data
            $stmt = $pdo->prepare("SELECT t.*, u.username, u.email, u.phone, u.payment_method 
                                   FROM tasks t 
                                   JOIN users u ON t.user_id = u.id 
                                   WHERE t.order_id = ?");
            $stmt->execute([$order_id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Failed to update status: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Details - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .detail-card { border-left: 4px solid #0d6efd; }
        .step-card { transition: all 0.3s; }
        .step-card.completed { border-color: #198754; background-color: #f8fff9; }
        .step-card.pending { border-color: #dc3545; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container mt-4">
        <h2 class="mb-4">📋 Task Details: <?php echo htmlspecialchars($order_id); ?></h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <!-- Task Info -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card detail-card">
                    <div class="card-body">
                        <h5 class="card-title">Task Information</h5>
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">Order ID:</th>
                                <td><strong><?php echo htmlspecialchars($task['order_id']); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Platform:</th>
                                <td><?php echo htmlspecialchars($task['platform']); ?></td>
                            </tr>
                            <tr>
                                <th>Product Link:</th>
                                <td><a href="<?php echo htmlspecialchars($task['product_link']); ?>" target="_blank">View Product</a></td>
                            </tr>
                            <tr>
                                <th>Task Value:</th>
                                <td>₹<?php echo number_format($task['task_value'], 2); ?></td>
                            </tr>
                            <tr>
                                <th>Deadline:</th>
                                <td><?php echo date('d-m-Y', strtotime($task['deadline'])); ?></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $task['status'] == 'completed' ? 'success' : 
                                             ($task['status'] == 'assigned' ? 'danger' : 'warning'); ?>">
                                        <?php echo htmlspecialchars(str_replace('_', ' ', $task['status'])); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card detail-card">
                    <div class="card-body">
                        <h5 class="card-title">User Information</h5>
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">Username:</th>
                                <td><?php echo htmlspecialchars($task['username']); ?></td>
                            </tr>
                            <tr>
                                <th>Email:</th>
                                <td><?php echo htmlspecialchars($task['email']); ?></td>
                            </tr>
                            <tr>
                                <th>Phone:</th>
                                <td><?php echo htmlspecialchars($task['phone'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th>Payment Method:</th>
                                <td><?php echo htmlspecialchars($task['payment_method'] ?? 'N/A'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Step Completion -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">✅ Step Completion</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Step 1 -->
                    <div class="col-md-3 mb-3">
                        <div class="card step-card <?php echo $task['status'] != 'assigned' ? 'completed' : 'pending'; ?>">
                            <div class="card-body text-center">
                                <h5>Step 1</h5>
                                <p class="text-muted">Order Placed</p>
                                <p><strong>Order Date:</strong><br>
                                <?php echo $task['order_date'] ? date('d-m-Y', strtotime($task['order_date'])) : 'Not submitted'; ?></p>
                                
                                <?php if ($task['status'] == 'assigned'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="step" value="step1">
                                        <button type="submit" name="complete_step" class="btn btn-sm btn-success">
                                            Mark Complete
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge bg-success">Completed</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 2 -->
                    <div class="col-md-3 mb-3">
                        <div class="card step-card <?php echo in_array($task['status'], ['step2_completed', 'step3_completed', 'refund_requested', 'completed']) ? 'completed' : 'pending'; ?>">
                            <div class="card-body text-center">
                                <h5>Step 2</h5>
                                <p class="text-muted">Review Submitted</p>
                                <p><strong>Review Date:</strong><br>
                                <?php echo $task['review_date'] ? date('d-m-Y', strtotime($task['review_date'])) : 'Not submitted'; ?></p>
                                
                                <?php if ($task['status'] == 'step1_completed'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="step" value="step2">
                                        <button type="submit" name="complete_step" class="btn btn-sm btn-success">
                                            Mark Complete
                                        </button>
                                    </form>
                                <?php elseif ($task['status'] == 'assigned'): ?>
                                    <span class="badge bg-secondary">Not Started</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Completed</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 3 -->
                    <div class="col-md-3 mb-3">
                        <div class="card step-card <?php echo in_array($task['status'], ['step3_completed', 'refund_requested', 'completed']) ? 'completed' : 'pending'; ?>">
                            <div class="card-body text-center">
                                <h5>Step 3</h5>
                                <p class="text-muted">Screenshots Uploaded</p>
                                <p><strong>Screenshots:</strong><br>
                                <?php echo $task['screenshot1'] ? 'Uploaded' : 'Not uploaded'; ?></p>
                                
                                <?php if ($task['status'] == 'step2_completed'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="step" value="step3">
                                        <button type="submit" name="complete_step" class="btn btn-sm btn-success">
                                            Mark Complete
                                        </button>
                                    </form>
                                <?php elseif (in_array($task['status'], ['assigned', 'step1_completed'])): ?>
                                    <span class="badge bg-secondary">Not Started</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Completed</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Refund -->
                    <div class="col-md-3 mb-3">
                        <div class="card step-card <?php echo $task['status'] == 'completed' ? 'completed' : 'pending'; ?>">
                            <div class="card-body text-center">
                                <h5>Refund</h5>
                                <p class="text-muted">Process Payment</p>
                                <p><strong>Refund Date:</strong><br>
                                <?php echo $task['refund_date'] ? date('d-m-Y', strtotime($task['refund_date'])) : 'Pending'; ?></p>
                                
                                <?php if ($task['status'] == 'refund_requested'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="step" value="refund">
                                        <button type="submit" name="complete_step" class="btn btn-sm btn-success">
                                            Process Refund
                                        </button>
                                    </form>
                                <?php elseif ($task['status'] == 'completed'): ?>
                                    <span class="badge bg-success">Refunded</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Not Ready</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- User Submitted Details -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">📄 User Submitted Details</h5>
            </div>
            <div class="card-body">
                <table class="table">
                    <tr>
                        <th width="30%">Order Amount:</th>
                        <td><?php echo $task['order_amount'] ? '₹' . number_format($task['order_amount'], 2) : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <th>Order Date:</th>
                        <td><?php echo $task['order_date'] ? date('d-m-Y', strtotime($task['order_date'])) : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <th>Review Text:</th>
                        <td><?php echo htmlspecialchars($task['review_text'] ?? 'Not submitted'); ?></td>
                    </tr>
                    <tr>
                        <th>Review Rating:</th>
                        <td>
                            <?php if ($task['review_rating']): ?>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= $task['review_rating']): ?>
                                        ⭐
                                    <?php endif; ?>
                                <?php endfor; ?>
                                (<?php echo $task['review_rating']; ?>/5)
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Screenshots:</th>
                        <td>
                            <?php if ($task['screenshot1']): ?>
                                <a href="../uploads/<?php echo htmlspecialchars($task['screenshot1']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">Screenshot 1</a>
                            <?php endif; ?>
                            <?php if ($task['screenshot2']): ?>
                                <a href="../uploads/<?php echo htmlspecialchars($task['screenshot2']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">Screenshot 2</a>
                            <?php endif; ?>
                            <?php if (!$task['screenshot1'] && !$task['screenshot2']): ?>
                                No screenshots uploaded
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Refund Request Date:</th>
                        <td><?php echo $task['refund_request_date'] ? date('d-m-Y H:i', strtotime($task['refund_request_date'])) : 'N/A'; ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="mt-3">
            <a href="pending_tasks.php" class="btn btn-secondary">Back to List</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
