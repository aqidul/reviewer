<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/db_connection.php';

if (!isAdmin()) {
    redirect('../index.php');
}

$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

// Build query
$query = "SELECT t.*, u.username, u.email FROM tasks t 
          JOIN users u ON t.user_id = u.id 
          WHERE t.status != 'completed' AND t.order_id IS NOT NULL";
$params = [];

if (!empty($search)) {
    $query .= " AND (t.order_id LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    $params = [$search_term, $search_term, $search_term];
}

if (!empty($status_filter)) {
    $query .= " AND t.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY t.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Tasks - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .badge-pending { background-color: #dc3545; }
        .badge-completed { background-color: #198754; }
        .badge-in-progress { background-color: #ffc107; color: #000; }
        .step-icon { font-size: 1.2rem; margin-right: 5px; }
        .step-completed { color: #198754; }
        .step-pending { color: #dc3545; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container mt-4">
        <h2 class="mb-4">⏳ Pending Tasks</h2>
        
        <!-- Filter and Search -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-6">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search by Order ID, Username, or Email" 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="assigned" <?php echo $status_filter == 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                            <option value="step1_completed" <?php echo $status_filter == 'step1_completed' ? 'selected' : ''; ?>>Step 1 Completed</option>
                            <option value="step2_completed" <?php echo $status_filter == 'step2_completed' ? 'selected' : ''; ?>>Step 2 Completed</option>
                            <option value="step3_completed" <?php echo $status_filter == 'step3_completed' ? 'selected' : ''; ?>>Step 3 Completed</option>
                            <option value="refund_requested" <?php echo $status_filter == 'refund_requested' ? 'selected' : ''; ?>>Refund Requested</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Tasks Table -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($tasks)): ?>
                    <div class="alert alert-info">No pending tasks found.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Task ID</th>
                                    <th>Order ID</th>
                                    <th>User</th>
                                    <th>Platform</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                    <th>Deadline</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tasks as $task): ?>
                                    <?php
                                    // Determine status badge
                                    $status_class = 'badge-pending';
                                    if ($task['status'] == 'completed') {
                                        $status_class = 'badge-completed';
                                    } elseif ($task['status'] == 'step1_completed' || $task['status'] == 'step2_completed' || $task['status'] == 'step3_completed') {
                                        $status_class = 'badge-in-progress';
                                    }
                                    
                                    // Determine step completion
                                    $step1_completed = $task['status'] == 'step1_completed' || $task['status'] == 'step2_completed' || $task['status'] == 'step3_completed' || $task['status'] == 'refund_requested';
                                    $step2_completed = $task['status'] == 'step2_completed' || $task['status'] == 'step3_completed' || $task['status'] == 'refund_requested';
                                    $step3_completed = $task['status'] == 'step3_completed' || $task['status'] == 'refund_requested';
                                    $refund_requested = $task['status'] == 'refund_requested';
                                    ?>
                                    <tr>
                                        <td>#<?php echo htmlspecialchars($task['id']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($task['order_id']); ?></strong></td>
                                        <td>
                                            <div><?php echo htmlspecialchars($task['username']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($task['email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($task['platform']); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="step-icon <?php echo $step1_completed ? 'step-completed' : 'step-pending'; ?>">
                                                    <?php echo $step1_completed ? '✓' : '①'; ?>
                                                </span>
                                                <span class="step-icon <?php echo $step2_completed ? 'step-completed' : 'step-pending'; ?>">
                                                    <?php echo $step2_completed ? '✓' : '②'; ?>
                                                </span>
                                                <span class="step-icon <?php echo $step3_completed ? 'step-completed' : 'step-pending'; ?>">
                                                    <?php echo $step3_completed ? '✓' : '③'; ?>
                                                </span>
                                                <span class="step-icon <?php echo $refund_requested ? 'step-completed' : 'step-pending'; ?>">
                                                    <?php echo $refund_requested ? '✓' : '₹'; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge rounded-pill <?php echo $status_class; ?>">
                                                <?php echo htmlspecialchars(str_replace('_', ' ', $task['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d-m-Y', strtotime($task['deadline'])); ?></td>
                                        <td>
                                            <a href="task_details.php?order_id=<?php echo urlencode($task['order_id']); ?>" 
                                               class="btn btn-sm btn-primary">View</a>
                                            <?php if ($task['status'] == 'refund_requested'): ?>
                                                <button class="btn btn-sm btn-success" 
                                                        onclick="processRefund('<?php echo htmlspecialchars($task['order_id']); ?>')">
                                                    Refund
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function processRefund(orderId) {
        if (confirm('Are you sure you want to process refund for order ' + orderId + '?')) {
            window.location.href = 'process_refund.php?order_id=' + encodeURIComponent(orderId);
        }
    }
    </script>
</body>
</html>
