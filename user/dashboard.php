<?php
require_once '../includes/config.php';

if (!isLoggedIn() || isAdmin()) {
    redirect('../index.php');
}

$user_id = $_SESSION['user_id'];

// Get user's tasks
$query = "
    SELECT t.*, 
           (SELECT COUNT(*) FROM orders WHERE task_id = t.id) as order_count,
           (SELECT COUNT(*) FROM orders WHERE task_id = t.id AND step4_status = 'approved') as completed_count
    FROM tasks t
    WHERE t.user_id = :user_id
    ORDER BY t.assigned_date DESC
";

$stmt = $db->prepare($query);
$stmt->execute([':user_id' => $user_id]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending orders
$query = "
    SELECT o.* 
    FROM orders o
    JOIN tasks t ON o.task_id = t.id
    WHERE t.user_id = :user_id 
    AND o.refund_status != 'completed'
    ORDER BY o.submitted_at DESC
    LIMIT 5
";

$orders_stmt = $db->prepare($query);
$orders_stmt->execute([':user_id' => $user_id]);
$pending_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - ReviewFlow</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="light-mode">
    <?php include '../includes/header.php'; ?>
    
    <div class="sidebar">
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="tasks.php"><i class="fas fa-tasks"></i> My Tasks</a></li>
            <li><a href="submit_order.php"><i class="fas fa-shopping-cart"></i> Submit Order</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-tachometer-alt"></i> User Dashboard</h2>
                <span>Welcome, <?php echo $_SESSION['user_name']; ?></span>
            </div>
            
            <!-- Quick Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #4361ee;">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($tasks); ?></h3>
                        <p>Total Tasks</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #f39c12;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3>
                            <?php 
                            $pending = 0;
                            foreach($tasks as $task) {
                                if($task['status'] == 'pending' || $task['status'] == 'in_progress') {
                                    $pending++;
                                }
                            }
                            echo $pending;
                            ?>
                        </h3>
                        <p>Pending Tasks</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #2ecc71;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>
                            <?php 
                            $completed = 0;
                            foreach($tasks as $task) {
                                $completed += $task['completed_count'];
                            }
                            echo $completed;
                            ?>
                        </h3>
                        <p>Completed Orders</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #e74c3c;">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($pending_orders); ?></h3>
                        <p>Pending Refunds</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pending Orders -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-clock"></i> Pending Actions</h3>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Product</th>
                            <th>Current Step</th>
                            <th>Next Action</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pending_orders as $order): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                            <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                            <td>
                                <?php 
                                if($order['step3_status'] == 'pending' && $order['step2_status'] == 'approved') {
                                    echo 'Step 3 - Review Submitted';
                                } elseif($order['step2_status'] == 'pending' && $order['step1_status'] == 'approved') {
                                    echo 'Step 2 - Delivery Proof';
                                } elseif($order['step1_status'] == 'pending') {
                                    echo 'Step 1 - Order Details';
                                } elseif($order['step3_status'] == 'approved') {
                                    echo 'Step 4 - Refund Request';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if($order['step3_status'] == 'pending' && $order['step2_status'] == 'approved') {
                                    echo 'Submit Review Screenshot';
                                } elseif($order['step2_status'] == 'pending' && $order['step1_status'] == 'approved') {
                                    echo 'Submit Delivery Screenshot';
                                } elseif($order['step1_status'] == 'pending') {
                                    echo 'Submit Order Details';
                                } elseif($order['step3_status'] == 'approved') {
                                    echo 'Request Refund';
                                }
                                ?>
                            </td>
                            <td>
                                <span class="status-badge status-pending">Pending</span>
                            </td>
                            <td>
                                <a href="update_order.php?id=<?php echo $order['id']; ?>" class="btn btn-primary btn-small">
                                    <i class="fas fa-edit"></i> Update
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Available Tasks -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bullhorn"></i> Available Tasks</h3>
            </div>
            <?php if(count($tasks) > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Task ID</th>
                            <th>Product Link</th>
                            <th>Assigned Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($tasks as $task): ?>
                        <tr>
                            <td>#<?php echo $task['id']; ?></td>
                            <td>
                                <a href="<?php echo htmlspecialchars($task['product_link']); ?>" target="_blank">
                                    <i class="fas fa-external-link-alt"></i> View Product
                                </a>
                            </td>
                            <td><?php echo date('d M Y', strtotime($task['assigned_date'])); ?></td>
                            <td>
                                <?php 
                                $status_class = $task['status'] == 'completed' ? 'status-completed' : 
                                              ($task['status'] == 'in_progress' ? 'status-approved' : 'status-pending');
                                echo '<span class="status-badge ' . $status_class . '">' . ucfirst($task['status']) . '</span>';
                                ?>
                            </td>
                            <td>
                                <a href="submit_order.php?task_id=<?php echo $task['id']; ?>" class="btn btn-primary btn-small">
                                    <i class="fas fa-plus"></i> Start Order
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No tasks assigned yet. Please wait for admin to assign tasks.
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Version Display -->
    <?php require_once __DIR__ . '/../includes/version-display.php'; ?>
    
    <!-- Include Theme CSS and JS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/themes.css">
    <script src="<?= APP_URL ?>/assets/js/theme.js"></script>
    
    <!-- Include Chatbot Widget -->
    <?php require_once __DIR__ . '/../includes/chatbot-widget.php'; ?>
    
    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/theme-toggle.js"></script>
    <script src="../assets/js/chatbot.js"></script>
</body>
</html>
