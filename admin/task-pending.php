<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

// Get filter parameters
$filter_order_id = isset($_GET['order_id']) ? trim($_GET['order_id']) : '';
$filter_user = isset($_GET['user']) ? trim($_GET['user']) : '';

// Fetch pending tasks (where at least step 1 is submitted)
try {
    $query = "
        SELECT 
            t.id, t.created_at, u.name as user_name, u.email, u.mobile,
            ts1.step_status as step1_status,
            ts2.step_status as step2_status,
            ts3.step_status as step3_status,
            ts4.step_status as step4_status,
            ts1.order_number, ts1.order_amount, ts1.order_date, ts1.order_name, ts1.product_name
        FROM tasks t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN task_steps ts1 ON t.id = ts1.task_id AND ts1.step_number = 1
        LEFT JOIN task_steps ts2 ON t.id = ts2.task_id AND ts2.step_number = 2
        LEFT JOIN task_steps ts3 ON t.id = ts3.task_id AND ts3.step_number = 3
        LEFT JOIN task_steps ts4 ON t.id = ts4.task_id AND ts4.step_number = 4
        WHERE t.refund_requested = false AND ts1.id IS NOT NULL
    ";
    
    $params = [];
    
    // Apply Order ID filter
    if (!empty($filter_order_id)) {
        $query .= " AND ts1.order_number LIKE :order_id";
        $params[':order_id'] = '%' . $filter_order_id . '%';
    }
    
    // Apply User filter (name or email)
    if (!empty($filter_user)) {
        $query .= " AND (u.name LIKE :user OR u.email LIKE :user_email)";
        $params[':user'] = '%' . $filter_user . '%';
        $params[':user_email'] = '%' . $filter_user . '%';
    }
    
    $query .= " ORDER BY t.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    $tasks = [];
}

// Get unique order IDs for autocomplete
try {
    $orderStmt = $pdo->query("SELECT DISTINCT order_number FROM task_steps WHERE step_number = 1 AND order_number IS NOT NULL ORDER BY order_number");
    $all_order_ids = $orderStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $all_order_ids = [];
}

$csrf_token = generateCSRFToken();

// Helper function to get step button color
function getStepButtonClass($status) {
    return $status === 'completed' ? 'success' : 'danger';
}

// Helper function to get step status text
function getStepStatusText($status) {
    return $status === 'completed' ? '✓' : '✗';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Tasks - Admin</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/bootstrap.min.css">
    <style>
        body {
            background: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
        .sidebar-brand {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-brand h3 {
            margin: 0;
            font-size: 20px;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-menu a {
            color: #bbb;
            text-decoration: none;
            padding: 12px 15px;
            display: block;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .admin-content {
            padding: 30px;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .page-title {
            color: #2c3e50;
            font-size: 28px;
            margin: 0;
        }
        
        /* Filter Section */
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .filter-title {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        .filter-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        .filter-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #666;
            margin-bottom: 5px;
        }
        .filter-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .filter-input:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        .filter-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        .btn-filter {
            background: #3498db;
            color: white;
        }
        .btn-filter:hover {
            background: #2980b9;
        }
        .btn-clear {
            background: #95a5a6;
            color: white;
        }
        .btn-clear:hover {
            background: #7f8c8d;
        }
        .filter-results {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-size: 14px;
            color: #666;
        }
        
        /* Task Card */
        .task-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .task-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
        }
        .task-id {
            font-weight: 600;
            color: #2c3e50;
            font-size: 18px;
        }
        .user-details {
            color: #666;
            font-size: 13px;
            margin-top: 5px;
        }
        .order-info {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #3498db;
        }
        .order-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
        }
        .order-info-item {
            font-size: 13px;
        }
        .order-info-label {
            color: #666;
            font-weight: 600;
        }
        .order-info-value {
            color: #2c3e50;
            font-weight: 700;
        }
        .order-id-highlight {
            background: #3498db;
            color: white;
            padding: 3px 10px;
            border-radius: 5px;
            font-weight: 700;
            font-size: 14px;
        }
        .steps-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        .step-btn {
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            color: white;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.3s;
        }
        .step-btn:hover {
            transform: scale(1.02);
            color: white;
        }
        .btn-success {
            background: #27ae60;
        }
        .btn-danger {
            background: #e74c3c;
        }
        .btn-view {
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-view:hover {
            background: #2980b9;
            color: white;
        }
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            background: white;
            border-radius: 12px;
            color: #666;
        }
        .empty-state h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .task-count {
            background: #3498db;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <div class="admin-sidebar">
            <div class="sidebar-brand">
                <h3>⚙️ Admin</h3>
            </div>
            <ul class="sidebar-menu">
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
            <div class="page-header">
                <h1 class="page-title">📋 Pending Tasks</h1>
                <span class="task-count"><?php echo count($tasks); ?> Task(s)</span>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-title">🔍 Filter Tasks</div>
                <form method="GET" action="" class="filter-form">
                    <div class="filter-group">
                        <label for="order_id">Order ID</label>
                        <input type="text" id="order_id" name="order_id" class="filter-input" 
                               placeholder="Search by Order ID..." 
                               value="<?php echo escape($filter_order_id); ?>"
                               list="order_ids_list">
                        <datalist id="order_ids_list">
                            <?php foreach ($all_order_ids as $oid): ?>
                                <option value="<?php echo escape($oid); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="filter-group">
                        <label for="user">Reviewer (Name/Email)</label>
                        <input type="text" id="user" name="user" class="filter-input" 
                               placeholder="Search by name or email..."
                               value="<?php echo escape($filter_user); ?>">
                    </div>
                    <button type="submit" class="filter-btn btn-filter">🔍 Search</button>
                    <?php if (!empty($filter_order_id) || !empty($filter_user)): ?>
                        <a href="<?php echo ADMIN_URL; ?>/task-pending.php" class="filter-btn btn-clear">✕ Clear</a>
                    <?php endif; ?>
                </form>
                
                <?php if (!empty($filter_order_id) || !empty($filter_user)): ?>
                    <div class="filter-results">
                        <strong>Showing results for:</strong>
                        <?php if (!empty($filter_order_id)): ?>
                            Order ID: "<strong><?php echo escape($filter_order_id); ?></strong>"
                        <?php endif; ?>
                        <?php if (!empty($filter_order_id) && !empty($filter_user)): ?> | <?php endif; ?>
                        <?php if (!empty($filter_user)): ?>
                            Reviewer: "<strong><?php echo escape($filter_user); ?></strong>"
                        <?php endif; ?>
                        — Found <strong><?php echo count($tasks); ?></strong> task(s)
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (empty($tasks)): ?>
                <div class="empty-state">
                    <h3>📭 No pending tasks found</h3>
                    <?php if (!empty($filter_order_id) || !empty($filter_user)): ?>
                        <p>No tasks match your filter criteria. <a href="<?php echo ADMIN_URL; ?>/task-pending.php">Clear filters</a></p>
                    <?php else: ?>
                        <p>All tasks are completed or no tasks assigned yet</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($tasks as $task): ?>
                    <div class="task-card">
                        <div class="task-header">
                            <div>
                                <div class="task-id">Task #<?php echo $task['id']; ?></div>
                                <div class="user-details">
                                    👤 <?php echo escape($task['user_name']); ?> | 📧 <?php echo escape($task['email']); ?> | 📱 <?php echo escape($task['mobile'] ?? 'N/A'); ?>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <small style="color: #999;">Created: <?php echo date('d M Y, h:i A', strtotime($task['created_at'])); ?></small>
                            </div>
                        </div>
                        
                        <!-- Order Information -->
                        <?php if (!empty($task['order_number'])): ?>
                            <div class="order-info">
                                <div class="order-info-grid">
                                    <div class="order-info-item">
                                        <div class="order-info-label">Order ID</div>
                                        <div class="order-info-value">
                                            <span class="order-id-highlight"><?php echo escape($task['order_number']); ?></span>
                                        </div>
                                    </div>
                                    <?php if (!empty($task['order_name'])): ?>
                                        <div class="order-info-item">
                                            <div class="order-info-label">Order Name</div>
                                            <div class="order-info-value"><?php echo escape($task['order_name']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($task['product_name'])): ?>
                                        <div class="order-info-item">
                                            <div class="order-info-label">Product</div>
                                            <div class="order-info-value"><?php echo escape($task['product_name']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($task['order_amount'])): ?>
                                        <div class="order-info-item">
                                            <div class="order-info-label">Amount</div>
                                            <div class="order-info-value">₹<?php echo number_format($task['order_amount'], 2); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($task['order_date'])): ?>
                                        <div class="order-info-item">
                                            <div class="order-info-label">Order Date</div>
                                            <div class="order-info-value"><?php echo date('d M Y', strtotime($task['order_date'])); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="steps-container">
                            <a href="<?php echo ADMIN_URL; ?>/task-detail.php?task_id=<?php echo $task['id']; ?>&step=1" 
                               class="step-btn btn-<?php echo getStepButtonClass($task['step1_status']); ?>">
                                <?php echo getStepStatusText($task['step1_status']); ?> Step 1
                            </a>
                            <a href="<?php echo ADMIN_URL; ?>/task-detail.php?task_id=<?php echo $task['id']; ?>&step=2" 
                               class="step-btn btn-<?php echo getStepButtonClass($task['step2_status']); ?>">
                                <?php echo getStepStatusText($task['step2_status']); ?> Step 2
                            </a>
                            <a href="<?php echo ADMIN_URL; ?>/task-detail.php?task_id=<?php echo $task['id']; ?>&step=3" 
                               class="step-btn btn-<?php echo getStepButtonClass($task['step3_status']); ?>">
                                <?php echo getStepStatusText($task['step3_status']); ?> Step 3
                            </a>
                            <a href="<?php echo ADMIN_URL; ?>/task-detail.php?task_id=<?php echo $task['id']; ?>&step=4" 
                               class="step-btn btn-<?php echo getStepButtonClass($task['step4_status']); ?>">
                                <?php echo getStepStatusText($task['step4_status']); ?> Refund
                            </a>
                        </div>
                        
                        <a href="<?php echo ADMIN_URL; ?>/task-detail.php?task_id=<?php echo $task['id']; ?>" class="btn-view">
                            View Full Details
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Auto-submit on Enter key in filter inputs
        document.querySelectorAll('.filter-input').forEach(input => {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    this.form.submit();
                }
            });
        });
    </script>
</body>
</html>
