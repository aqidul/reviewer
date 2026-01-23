<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/db_connection.php';

if (!isAdmin()) {
    redirect('../index.php');
}

$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

// Build query
$query = "SELECT t.*, u.username, u.email FROM tasks t 
          JOIN users u ON t.user_id = u.id 
          WHERE t.status = 'completed'";
$params = [];

if (!empty($search)) {
    $query .= " AND (t.order_id LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

if (!empty($date_from)) {
    $query .= " AND DATE(t.refund_date) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(t.refund_date) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY t.refund_date DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Calculate statistics
$total_refunded = 0;
$total_tasks = count($tasks);
foreach ($tasks as $task) {
    $total_refunded += floatval($task['task_value']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Tasks - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container mt-4">
        <h2 class="mb-4">✅ Completed Tasks</h2>
        
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Completed</h5>
                        <h2><?php echo $total_tasks; ?></h2>
                        <p class="card-text">Tasks fully refunded</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Refunded</h5>
                        <h2>₹<?php echo number_format($total_refunded, 2); ?></h2>
                        <p class="card-text">Total amount refunded</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Average per Task</h5>
                        <h2>₹<?php echo $total_tasks > 0 ? number_format($total_refunded / $total_tasks, 2) : '0.00'; ?></h2>
                        <p class="card-text">Average refund amount</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search Order ID or User" 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <input type="date" class="form-control" name="date_from" 
                               value="<?php echo htmlspecialchars($date_from); ?>"
                               max="<?php echo date('Y-m-d'); ?>">
                        <small class="text-muted">From Date</small>
                    </div>
                    <div class="col-md-3">
                        <input type="date" class="form-control" name="date_to" 
                               value="<?php echo htmlspecialchars($date_to); ?>"
                               max="<?php echo date('Y-m-d'); ?>">
                        <small class="text-muted">To Date</small>
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
                    <div class="alert alert-info">No completed tasks found.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Task ID</th>
                                    <th>Order ID</th>
                                    <th>User</th>
                                    <th>Platform</th>
                                    <th>Task Value</th>
                                    <th>Order Date</th>
                                    <th>Refund Date</th>
                                    <th>Time Taken</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tasks as $task): ?>
                                    <?php
                                    $order_date = strtotime($task['order_date']);
                                    $refund_date = strtotime($task['refund_date']);
                                    $days_taken = $order_date ? round(($refund_date - $order_date) / (60 * 60 * 24)) : 'N/A';
                                    ?>
                                    <tr>
                                        <td>#<?php echo htmlspecialchars($task['id']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($task['order_id']); ?></strong></td>
                                        <td>
                                            <div><?php echo htmlspecialchars($task['username']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($task['email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($task['platform']); ?></td>
                                        <td>₹<?php echo number_format($task['task_value'], 2); ?></td>
                                        <td><?php echo $task['order_date'] ? date('d-m-Y', strtotime($task['order_date'])) : 'N/A'; ?></td>
                                        <td><?php echo date('d-m-Y', strtotime($task['refund_date'])); ?></td>
                                        <td>
                                            <?php if (is_numeric($days_taken)): ?>
                                                <span class="badge bg-info"><?php echo $days_taken; ?> days</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?php echo $days_taken; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="task_details.php?order_id=<?php echo urlencode($task['order_id']); ?>" 
                                               class="btn btn-sm btn-outline-primary">View Details</a>
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
</body>
</html>
