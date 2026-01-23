<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/db_connection.php';

if (!isLoggedIn() || isAdmin()) {
    redirect('../index.php');
}

$user_id = $_SESSION['user_id'];
$filter = isset($_GET['filter']) ? sanitizeInput($_GET['filter']) : '';

// Build query based on filter
$query = "SELECT * FROM tasks WHERE user_id = ?";
$params = [$user_id];

switch ($filter) {
    case 'pending':
        $query .= " AND status NOT IN ('completed')";
        break;
    case 'completed':
        $query .= " AND status = 'completed'";
        break;
    case 'active':
        $query .= " AND status IN ('assigned', 'step1_completed', 'step2_completed', 'step3_completed', 'refund_requested')";
        break;
}

$query .= " ORDER BY FIELD(status, 'assigned', 'step1_completed', 'step2_completed', 'step3_completed', 'refund_requested', 'completed'), deadline ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Count tasks by status
$counts = [
    'total' => 0,
    'pending' => 0,
    'completed' => 0,
    'active' => 0
];

foreach ($tasks as $task) {
    $counts['total']++;
    if ($task['status'] == 'completed') {
        $counts['completed']++;
    } else {
        $counts['pending']++;
    }
    if ($task['status'] != 'completed') {
        $counts['active']++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks - User Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .task-card { transition: all 0.3s; }
        .task-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .status-badge { font-size: 0.8rem; }
        .progress-step { width: 25px; height: 25px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-right: 5px; }
        .step-active { background-color: #0d6efd; color: white; }
        .step-completed { background-color: #198754; color: white; }
        .step-pending { background-color: #6c757d; color: white; }
        .deadline-warning { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container mt-4">
        <h2 class="mb-4">📋 My Tasks</h2>
        
        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Total</h5>
                        <h3><?php echo $counts['total']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body text-center">
                        <h5 class="card-title">Active</h5>
                        <h3><?php echo $counts['active']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Pending</h5>
                        <h3><?php echo $counts['pending']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Completed</h5>
                        <h3><?php echo $counts['completed']; ?></h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="btn-group" role="group">
                    <a href="?filter=" class="btn btn-outline-primary <?php echo empty($filter) ? 'active' : ''; ?>">All Tasks</a>
                    <a href="?filter=active" class="btn btn-outline-warning <?php echo $filter == 'active' ? 'active' : ''; ?>">Active</a>
                    <a href="?filter=pending" class="btn btn-outline-danger <?php echo $filter == 'pending' ? 'active' : ''; ?>">Pending</a>
                    <a href="?filter=completed" class="btn btn-outline-success <?php echo $filter == 'completed' ? 'active' : ''; ?>">Completed</a>
                </div>
            </div>
        </div>
        
        <!-- Tasks Grid -->
        <div class="row">
            <?php if (empty($tasks)): ?>
                <div class="col-12">
                    <div class="alert alert-info">No tasks found.</div>
                </div>
            <?php else: ?>
                <?php foreach ($tasks as $task): ?>
                    <?php
                    // Determine status color
                    $status_color = 'secondary';
                    $status_text = ucfirst(str_replace('_', ' ', $task['status']));
                    
                    switch ($task['status']) {
                        case 'assigned': $status_color = 'danger'; break;
                        case 'step1_completed': $status_color = 'warning'; break;
                        case 'step2_completed': $status_color = 'info'; break;
                        case 'step3_completed': $status_color = 'primary'; break;
                        case 'refund_requested': $status_color = 'success'; break;
                        case 'completed': $status_color = 'success'; $status_text = 'Refunded'; break;
                    }
                    
                    // Check deadline
                    $deadline_class = '';
                    $deadline = strtotime($task['deadline']);
                    $today = strtotime(date('Y-m-d'));
                    $days_left = round(($deadline - $today) / (60 * 60 * 24));
                    
                    if ($days_left < 0) {
                        $deadline_class = 'deadline-warning';
                        $deadline_text = 'Overdue!';
                    } elseif ($days_left == 0) {
                        $deadline_class = 'text-warning';
                        $deadline_text = 'Today';
                    } elseif ($days_left <= 3) {
                        $deadline_class = 'text-warning';
                        $deadline_text = $days_left . ' days left';
                    } else {
                        $deadline_text = $days_left . ' days left';
                    }
                    
                    // Determine step completion
                    $step1_completed = $task['status'] != 'assigned';
                    $step2_completed = in_array($task['status'], ['step2_completed', 'step3_completed', 'refund_requested', 'completed']);
                    $step3_completed = in_array($task['status'], ['step3_completed', 'refund_requested', 'completed']);
                    $refund_requested = in_array($task['status'], ['refund_requested', 'completed']);
                    ?>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card task-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title">Order: <?php echo htmlspecialchars($task['order_id'] ?? 'Not Started'); ?></h5>
                                    <span class="badge bg-<?php echo $status_color; ?> status-badge">
                                        <?php echo $status_text; ?>
                                    </span>
                                </div>
                                
                                <p class="card-text">
                                    <strong>Platform:</strong> <?php echo htmlspecialchars($task['platform']); ?><br>
                                    <strong>Product:</strong> 
                                    <a href="<?php echo htmlspecialchars($task['product_link']); ?>" target="_blank" class="text-decoration-none">
                                        View Link
                                    </a><br>
                                    <strong>Task Value:</strong> ₹<?php echo number_format($task['task_value'], 2); ?><br>
                                    <strong>Deadline:</strong> 
                                    <span class="<?php echo $deadline_class; ?>">
                                        <?php echo date('d-m-Y', strtotime($task['deadline'])); ?> (<?php echo $deadline_text; ?>)
                                    </span>
                                </p>
                                
                                <!-- Progress Steps -->
                                <div class="mb-3">
                                    <small class="text-muted d-block mb-2">Progress:</small>
                                    <div>
                                        <span class="progress-step <?php echo $step1_completed ? 'step-completed' : 'step-active'; ?>" title="Step 1: Order">
                                            ①
                                        </span>
                                        <span class="progress-step <?php echo $step2_completed ? 'step-completed' : ($step1_completed ? 'step-active' : 'step-pending'); ?>" title="Step 2: Review">
                                            ②
                                        </span>
                                        <span class="progress-step <?php echo $step3_completed ? 'step-completed' : ($step2_completed ? 'step-active' : 'step-pending'); ?>" title="Step 3: Screenshots">
                                            ③
                                        </span>
                                        <span class="progress-step <?php echo $refund_requested ? 'step-completed' : ($step3_completed ? 'step-active' : 'step-pending'); ?>" title="Refund">
                                            ₹
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="mt-3">
                                    <?php if ($task['status'] == 'assigned'): ?>
                                        <a href="submit_order.php?task_id=<?php echo $task['id']; ?>" 
                                           class="btn btn-primary btn-sm">Start Step 1</a>
                                    <?php elseif ($task['status'] == 'step1_completed'): ?>
                                        <a href="update_order.php?order_id=<?php echo urlencode($task['order_id']); ?>&step=2" 
                                           class="btn btn-warning btn-sm">Continue Step 2</a>
                                    <?php elseif ($task['status'] == 'step2_completed'): ?>
                                        <a href="update_order.php?order_id=<?php echo urlencode($task['order_id']); ?>&step=3" 
                                           class="btn btn-info btn-sm">Continue Step 3</a>
                                    <?php elseif ($task['status'] == 'step3_completed'): ?>
                                        <a href="update_order.php?order_id=<?php echo urlencode($task['order_id']); ?>&step=4" 
                                           class="btn btn-success btn-sm">Request Refund</a>
                                    <?php elseif ($task['status'] == 'refund_requested'): ?>
                                        <span class="badge bg-success">Refund Pending</span>
                                        <a href="view_entry.php?order_id=<?php echo urlencode($task['order_id']); ?>" 
                                           class="btn btn-outline-primary btn-sm">View</a>
                                    <?php elseif ($task['status'] == 'completed'): ?>
                                        <a href="view_entry.php?order_id=<?php echo urlencode($task['order_id']); ?>" 
                                           class="btn btn-outline-success btn-sm">View Details</a>
                                    <?php endif; ?>
                                    
                                    <?php if ($task['order_id'] && $task['status'] != 'completed'): ?>
                                        <a href="update_order.php?order_id=<?php echo urlencode($task['order_id']); ?>" 
                                           class="btn btn-outline-secondary btn-sm">Update</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
