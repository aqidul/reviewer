<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/db_connection.php';

if (!isLoggedIn() || isAdmin()) {
    redirect('../index.php');
}

$user_id = $_SESSION['user_id'];

// Check if order_id is provided
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    redirect('tasks.php');
}

$order_id = sanitizeInput($_GET['order_id']);

// Fetch task details
try {
    $stmt = $pdo->prepare("SELECT t.*, u.username, u.email, u.account_name, u.account_number, u.bank_name, u.ifsc_code, u.upi_id 
                           FROM tasks t 
                           JOIN users u ON t.user_id = u.id 
                           WHERE t.order_id = ? AND t.user_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        die("Task not found!");
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Entry - User Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .detail-row {
            border-bottom: 1px solid #dee2e6;
            padding: 10px 0;
        }
        .detail-label {
            font-weight: bold;
            color: #495057;
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 5px 10px;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container mt-4">
        <h2 class="mb-4">📄 Entry Details: <?php echo htmlspecialchars($order_id); ?></h2>
        
        <!-- Status Badge -->
        <div class="mb-4">
            <?php
            $status_color = 'secondary';
            $status_text = ucfirst(str_replace('_', ' ', $task['status']));
            
            switch ($task['status']) {
                case 'assigned': $status_color = 'danger'; break;
                case 'step1_completed': $status_color = 'warning'; break;
                case 'step2_completed': $status_color = 'info'; break;
                case 'step3_completed': $status_color = 'primary'; break;
                case 'refund_requested': $status_color = 'success'; $status_text = 'Refund Requested'; break;
                case 'completed': $status_color = 'success'; $status_text = 'Refunded'; break;
            }
            ?>
            <span class="badge bg-<?php echo $status_color; ?> status-badge">Status: <?php echo $status_text; ?></span>
            
            <?php if ($task['status'] == 'completed'): ?>
                <span class="badge bg-success status-badge">Refunded on: <?php echo date('d-m-Y', strtotime($task['refund_date'])); ?></span>
            <?php endif; ?>
        </div>
        
        <div class="row">
            <!-- Task Information -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">📋 Task Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="detail-row">
                            <div class="detail-label">Platform</div>
                            <div><?php echo htmlspecialchars($task['platform']); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Product Link</div>
                            <div><a href="<?php echo htmlspecialchars($task['product_link']); ?>" target="_blank">View Product</a></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Task Value</div>
                            <div>₹<?php echo number_format($task['task_value'], 2); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Deadline</div>
                            <div><?php echo date('d-m-Y', strtotime($task['deadline'])); ?></div>
                        </div>
                        <?php if (!empty($task['instructions'])): ?>
                        <div class="detail-row">
                            <div class="detail-label">Special Instructions</div>
                            <div><?php echo htmlspecialchars($task['instructions']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Step 2: Review -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">📝 Review Details</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($task['review_text']): ?>
                            <div class="detail-row">
                                <div class="detail-label">Rating</div>
                                <div>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $task['review_rating']): ?>⭐<?php endif; ?>
                                    <?php endfor; ?>
                                    (<?php echo $task['review_rating']; ?>/5)
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Review Date</div>
                                <div><?php echo date('d-m-Y', strtotime($task['review_date'])); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Review Text</div>
                                <div class="border p-3 bg-light rounded"><?php echo nl2br(htmlspecialchars($task['review_text'])); ?></div>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">Review not submitted yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Step 1: Order & Step 3: Screenshots -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">🛒 Order Details</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($task['order_id']): ?>
                            <div class="detail-row">
                                <div class="detail-label">Order ID</div>
                                <div><?php echo htmlspecialchars($task['order_id']); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Order Date</div>
                                <div><?php echo date('d-m-Y', strtotime($task['order_date'])); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Order Amount</div>
                                <div>₹<?php echo number_format($task['order_amount'], 2); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Payment Method</div>
                                <div><?php echo htmlspecialchars($task['payment_method']); ?></div>
                            </div>
                            <?php if (!empty($task['transaction_id'])): ?>
                            <div class="detail-row">
                                <div class="detail-label">Transaction ID</div>
                                <div><?php echo htmlspecialchars($task['transaction_id']); ?></div>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="text-muted">Order details not submitted yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Step 3: Screenshots -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">🖼️ Screenshots</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php if ($task['screenshot1']): ?>
                                <div class="col-md-6 mb-3">
                                    <p class="detail-label">Screenshot 1</p>
                                    <a href="../uploads/<?php echo htmlspecialchars($task['screenshot1']); ?>" target="_blank">
                                        <img src="../uploads/<?php echo htmlspecialchars($task['screenshot1']); ?>" 
                                             class="img-thumbnail" style="max-height: 150px;">
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($task['screenshot2']): ?>
                                <div class="col-md-6 mb-3">
                                    <p class="detail-label">Screenshot 2</p>
                                    <a href="../uploads/<?php echo htmlspecialchars($task['screenshot2']); ?>" target="_blank">
                                        <img src="../uploads/<?php echo htmlspecialchars($task['screenshot2']); ?>" 
                                             class="img-thumbnail" style="max-height: 150px;">
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!$task['screenshot1'] && !$task['screenshot2']): ?>
                                <p class="text-muted">No screenshots uploaded.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Details -->
                <?php if ($task['status'] == 'refund_requested' || $task['status'] == 'completed'): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">💰 Payment Details</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($task['account_number'])): ?>
                            <div class="detail-row">
                                <div class="detail-label">Account Holder</div>
                                <div><?php echo htmlspecialchars($task['account_name']); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Account Number</div>
                                <div><?php echo htmlspecialchars($task['account_number']); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Bank Name</div>
                                <div><?php echo htmlspecialchars($task['bank_name']); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">IFSC Code</div>
                                <div><?php echo htmlspecialchars($task['ifsc_code']); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($task['upi_id'])): ?>
                            <div class="detail-row">
                                <div class="detail-label">UPI ID</div>
                                <div><?php echo htmlspecialchars($task['upi_id']); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (empty($task['account_number']) && empty($task['upi_id'])): ?>
                            <p class="text-muted">Payment details not provided.</p>
                        <?php endif; ?>
                        
                        <?php if ($task['status'] == 'completed'): ?>
                            <div class="alert alert-success mt-3">
                                <strong>✅ Refund Processed</strong><br>
                                Amount: ₹<?php echo number_format($task['task_value'], 2); ?><br>
                                Date: <?php echo date('d-m-Y H:i', strtotime($task['refund_date'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Timeline -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">⏱️ Timeline</h5>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <?php
                    $timeline_items = [];
                    
                    if ($task['created_at']) {
                        $timeline_items[] = [
                            'date' => $task['created_at'],
                            'title' => 'Task Assigned',
                            'description' => 'Task was assigned to you'
                        ];
                    }
                    
                    if ($task['order_date']) {
                        $timeline_items[] = [
                            'date' => $task['order_date'],
                            'title' => 'Order Placed',
                            'description' => 'Order ID: ' . $task['order_id']
                        ];
                    }
                    
                    if ($task['step1_completed_at']) {
                        $timeline_items[] = [
                            'date' => $task['step1_completed_at'],
                            'title' => 'Step 1 Completed',
                            'description' => 'Order details submitted'
                        ];
                    }
                    
                    if ($task['review_date']) {
                        $timeline_items[] = [
                            'date' => $task['review_date'],
                            'title' => 'Review Submitted',
                            'description' => 'Rating: ' . $task['review_rating'] . '/5 stars'
                        ];
                    }
                    
                    if ($task['step2_completed_at']) {
                        $timeline_items[] = [
                            'date' => $task['step2_completed_at'],
                            'title' => 'Step 2 Completed',
                            'description' => 'Review submitted'
                        ];
                    }
                    
                    if ($task['step3_completed_at']) {
                        $timeline_items[] = [
                            'date' => $task['step3_completed_at'],
                            'title' => 'Step 3 Completed',
                            'description' => 'Screenshots uploaded'
                        ];
                    }
                    
                    if ($task['refund_request_date']) {
                        $timeline_items[] = [
                            'date' => $task['refund_request_date'],
                            'title' => 'Refund Requested',
                            'description' => 'Refund request submitted'
                        ];
                    }
                    
                    if ($task['refund_date']) {
                        $timeline_items[] = [
                            'date' => $task['refund_date'],
                            'title' => 'Refund Processed',
                            'description' => 'Amount: ₹' . number_format($task['task_value'], 2)
                        ];
                    }
                    
                    // Sort by date
                    usort($timeline_items, function($a, $b) {
                        return strtotime($a['date']) - strtotime($b['date']);
                    });
                    ?>
                    
                    <?php foreach ($timeline_items as $item): ?>
                        <div class="row mb-3">
                            <div class="col-md-2">
                                <small class="text-muted"><?php echo date('d-m-Y', strtotime($item['date'])); ?></small>
                            </div>
                            <div class="col-md-10">
                                <strong><?php echo htmlspecialchars($item['title']); ?></strong><br>
                                <small><?php echo htmlspecialchars($item['description']); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="tasks.php" class="btn btn-secondary">Back to Tasks</a>
            <?php if ($task['status'] != 'completed' && $task['status'] != 'refund_requested'): ?>
                <a href="update_order.php?order_id=<?php echo urlencode($order_id); ?>" class="btn btn-primary">Update Entry</a>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
