<?php
/**
 * Admin Dashboard
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require admin authentication
if (!isLoggedIn() || !isAdmin()) {
    redirect('../admin/index.php');
}

// Get dashboard statistics
try {
    // Total tasks
    $stmt = $pdo->query("SELECT COUNT(*) as total_tasks FROM tasks");
    $total_tasks = $stmt->fetch(PDO::FETCH_ASSOC)['total_tasks'];
    
    // Pending tasks
    $stmt = $pdo->query("SELECT COUNT(*) as pending_tasks FROM tasks WHERE status != 'completed'");
    $pending_tasks = $stmt->fetch(PDO::FETCH_ASSOC)['pending_tasks'];
    
    // Completed tasks
    $stmt = $pdo->query("SELECT COUNT(*) as completed_tasks FROM tasks WHERE status = 'completed'");
    $completed_tasks = $stmt->fetch(PDO::FETCH_ASSOC)['completed_tasks'];
    
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users WHERE user_type = 'user'");
    $total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];
    
    // Pending refund requests
    $stmt = $pdo->query("SELECT COUNT(*) as refund_requests FROM tasks WHERE status = 'refund_requested'");
    $refund_requests = $stmt->fetch(PDO::FETCH_ASSOC)['refund_requests'];
    
    // Total refunded amount
    $stmt = $pdo->query("SELECT SUM(task_value) as total_refunded FROM tasks WHERE status = 'completed'");
    $total_refunded = $stmt->fetch(PDO::FETCH_ASSOC)['total_refunded'] ?? 0;
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Reviewer Task Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container mt-4">
        <h1 class="mb-4">Admin Dashboard</h1>
        
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Tasks</h5>
                        <h2><?php echo $total_tasks; ?></h2>
                        <p class="card-text">All assigned tasks</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body">
                        <h5 class="card-title">Pending Tasks</h5>
                        <h2><?php echo $pending_tasks; ?></h2>
                        <p class="card-text">Awaiting completion</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Completed Tasks</h5>
                        <h2><?php echo $completed_tasks; ?></h2>
                        <p class="card-text">Fully refunded</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Users</h5>
                        <h2><?php echo $total_users; ?></h2>
                        <p class="card-text">Active reviewers</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="row mb-5">
            <div class="col-12">
                <h3 class="mb-3">Quick Actions</h3>
                <div class="d-flex flex-wrap gap-3">
                    <a href="<?php echo BASE_URL; ?>/admin/assign_task.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Assign New Task
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/pending_tasks.php" class="btn btn-warning">
                        <i class="bi bi-clock-history"></i> View Pending Tasks
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/completed_tasks.php" class="btn btn-success">
                        <i class="bi bi-check2-circle"></i> View Completed Tasks
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/users.php" class="btn btn-info">
                        <i class="bi bi-people"></i> Manage Users
                    </a>
                    <?php if ($refund_requests > 0): ?>
                        <a href="<?php echo BASE_URL; ?>/admin/pending_tasks.php?status=refund_requested" class="btn btn-danger">
                            <i class="bi bi-cash-coin"></i> Process Refunds (<?php echo $refund_requests; ?>)
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            $stmt = $pdo->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 10");
                            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (empty($activities)) {
                                echo '<p class="text-muted">No recent activity.</p>';
                            } else {
                                echo '<div class="list-group">';
                                foreach ($activities as $activity) {
                                    echo '<div class="list-group-item">';
                                    echo '<small class="text-muted">' . formatDate($activity['created_at'], 'd-m-Y H:i') . '</small><br>';
                                    echo '<strong>' . htmlspecialchars($activity['action']) . '</strong>';
                                    if (!empty($activity['details'])) {
                                        echo '<br><small>' . htmlspecialchars($activity['details']) . '</small>';
                                    }
                                    echo '</div>';
                                }
                                echo '</div>';
                            }
                        } catch (PDOException $e) {
                            echo '<p class="text-danger">Unable to load activity log.</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">System Info</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Total Refunded:</strong> ₹<?php echo number_format($total_refunded, 2); ?></p>
                        <p><strong>Pending Refunds:</strong> <?php echo $refund_requests; ?></p>
                        <p><strong>Server Time:</strong> <?php echo date('d-m-Y H:i:s'); ?></p>
                        <p><strong>Admin:</strong> <?php echo htmlspecialchars($_SESSION['username'] ?? 'N/A'); ?></p>
                        <hr>
                        <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
