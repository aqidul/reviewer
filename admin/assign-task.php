<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$user_id = intval($_GET['user_id'] ?? 0);
$errors = [];
$success = false;
$user = null;
$users = [];

// If user_id is provided, fetch that specific user
if ($user_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND user_type = 'user' AND status = 'active'");
        $stmt->execute([':id' => $user_id]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}

// Fetch all active users for dropdown/selection
try {
    $stmt = $pdo->prepare("
        SELECT id, name, email, mobile, created_at,
            (SELECT COUNT(*) FROM tasks WHERE tasks.user_id = users.id) as total_tasks,
            (SELECT COUNT(*) FROM tasks WHERE tasks.user_id = users.id AND task_status = 'completed') as completed_tasks
        FROM users 
        WHERE user_type = 'user' AND status = 'active'
        ORDER BY name ASC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch users error: " . $e->getMessage());
}

// Handle task assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('CSRF error');
    }
    
    // Get user_id from POST if not in GET
    $post_user_id = intval($_POST['user_id'] ?? 0);
    if ($post_user_id > 0) {
        $user_id = $post_user_id;
        // Fetch user details for the selected user
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND user_type = 'user' AND status = 'active'");
            $stmt->execute([':id' => $user_id]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            error_log($e->getMessage());
        }
    }
    
    if (!$user) {
        $errors[] = 'Please select a valid user';
    }
    
    $product_link = sanitizeInput($_POST['product_link'] ?? '');
    $commission = floatval($_POST['commission'] ?? 0);
    $deadline = $_POST['deadline'] ?? null;
    $priority = $_POST['priority'] ?? 'medium';
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    if (empty($product_link)) {
        $errors[] = 'Product link is required';
    }
    
    if (!filter_var($product_link, FILTER_VALIDATE_URL)) {
        $errors[] = 'Invalid product link URL';
    }
    
    if ($commission < 0) {
        $errors[] = 'Commission cannot be negative';
    }
    
    if (empty($errors) && $user) {
        try {
            $pdo->beginTransaction();
            
            // Insert task
            $stmt = $pdo->prepare("
                INSERT INTO tasks (user_id, product_link, task_status, commission, deadline, priority, admin_notes, assigned_by, created_at)
                VALUES (:user_id, :product_link, 'pending', :commission, :deadline, :priority, :notes, :admin, NOW())
            ");
            
            $stmt->execute([
                ':user_id' => $user_id,
                ':product_link' => $product_link,
                ':commission' => $commission,
                ':deadline' => !empty($deadline) ? $deadline : null,
                ':priority' => $priority,
                ':notes' => $notes,
                ':admin' => $admin_name
            ]);
            
            $task_id = $pdo->lastInsertId();
            
            // Create task steps
            $steps = ['Order Placed', 'Delivery Received', 'Review Submitted', 'Refund Requested'];
            foreach ($steps as $index => $step) {
                $stmt = $pdo->prepare("
                    INSERT INTO task_steps (task_id, step_number, step_name, step_status, created_at)
                    VALUES (?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([$task_id, $index + 1, $step]);
            }
            
            $pdo->commit();
            
            // Log activity
            logActivity('Admin assigned task #' . $task_id . ' to user #' . $user_id, $task_id, $user_id);
            
            // Send notification to user
            createNotification(
                $user_id, 
                'task', 
                '📋 New Task Assigned', 
                'A new review task has been assigned to you. Commission: ₹' . number_format($commission, 2) . '. Check your dashboard!', 
                APP_URL . '/user/task-detail.php?task_id=' . $task_id
            );
            
            // Send email notification
            sendTaskNotification($user_id, 'task_assigned', ['task_id' => $task_id, 'commission' => $commission]);
            
            $success = true;
            $success_task_id = $task_id;
            $success_user_name = $user['name'];
            
            // Reset form
            $_POST = [];
            $user = null;
            $user_id = 0;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Assign Task Error: " . $e->getMessage());
            $errors[] = 'Failed to assign task. Please try again.';
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Task - Admin Panel</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/bootstrap.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
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
            color: #fff;
        }
        .sidebar-brand small {
            color: #888;
            font-size: 11px;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        .sidebar-menu a {
            color: #bbb;
            text-decoration: none;
            padding: 12px 15px;
            display: block;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 14px;
        }
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .sidebar-menu a.active {
            background: rgba(102, 126, 234, 0.3);
            border-left: 3px solid #667eea;
        }
        .admin-content {
            padding: 30px;
            background: #f5f5f5;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .page-title {
            font-size: 26px;
            color: #2c3e50;
            font-weight: 600;
        }
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            padding: 25px;
            margin-bottom: 20px;
        }
        .card-title {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        /* User Selection Grid */
        .user-select-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .user-card {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .user-card:hover {
            border-color: #667eea;
            background: #f0f3ff;
        }
        .user-card.selected {
            border-color: #27ae60;
            background: #e8f8f0;
        }
        .user-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }
        .user-card-info h4 {
            margin: 0;
            font-size: 15px;
            color: #333;
        }
        .user-card-info p {
            margin: 3px 0 0 0;
            font-size: 12px;
            color: #666;
        }
        .user-card-stats {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: #888;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e0e0e0;
        }
        .user-card-stats span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Selected User Info */
        .selected-user-info {
            background: linear-gradient(135deg, #e8f8f0 0%, #d4edda 100%);
            border: 2px solid #27ae60;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        .selected-user-info h4 {
            margin: 0 0 10px 0;
            color: #155724;
            font-size: 16px;
        }
        .selected-user-info p {
            margin: 5px 0;
            color: #1e7e34;
            font-size: 14px;
        }
        .change-user-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            margin-top: 10px;
        }
        .change-user-btn:hover {
            background: #218838;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-control:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .form-text {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        /* Search Box */
        .search-box {
            position: relative;
            margin-bottom: 20px;
        }
        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        .search-box input:focus {
            border-color: #667eea;
            outline: none;
        }
        .search-box::before {
            content: "🔍";
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        /* Buttons */
        .btn-submit {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(39, 174, 96, 0.3);
        }
        .btn-submit:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-danger {
            background: #ffe6e6;
            color: #c0392b;
            border: 1px solid #f5c6cb;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-success a {
            color: #155724;
            font-weight: 600;
        }
        
        /* Back Link */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        
        /* No Users Message */
        .no-users {
            text-align: center;
            padding: 40px;
            color: #888;
        }
        .no-users h3 {
            margin-bottom: 10px;
            color: #666;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 768px) {
            .admin-wrapper {
                grid-template-columns: 1fr;
            }
            .admin-sidebar {
                display: none;
            }
            .admin-content {
                padding: 20px;
            }
            .user-select-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <!-- Sidebar -->
    <div class="admin-sidebar">
        <div class="sidebar-brand">
            <h3>⚙️ ReviewFlow</h3>
            <small>Admin Panel</small>
        </div>
        <ul class="sidebar-menu">
            <li><a href="<?php echo ADMIN_URL; ?>/dashboard.php">📊 Dashboard</a></li>
            <li><a href="<?php echo ADMIN_URL; ?>/reviewers.php">👥 Users</a></li>
            <li><a href="<?php echo ADMIN_URL; ?>/assign-task.php" class="active">➕ Assign Task</a></li>
            <li><a href="<?php echo ADMIN_URL; ?>/task-pending.php">📋 Pending Tasks</a></li>
            <li><a href="<?php echo ADMIN_URL; ?>/task-completed.php">✅ Completed Tasks</a></li>
            <li><a href="<?php echo ADMIN_URL; ?>/withdrawals.php">💸 Withdrawals</a></li>
            <li><a href="<?php echo ADMIN_URL; ?>/messages.php">💬 Messages</a></li>
            <li><a href="<?php echo ADMIN_URL; ?>/chatbot-faq.php">🤖 Chatbot FAQ</a></li>
            <li><a href="<?php echo ADMIN_URL; ?>/chatbot-unanswered.php">❓ Unanswered Q's</a></li>
            <li><a href="<?php echo ADMIN_URL; ?>/reports.php">📈 Reports</a></li>
            <li><a href="<?php echo ADMIN_URL; ?>/settings.php">⚙️ Settings</a></li>
            <li style="margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                <a href="<?php echo ADMIN_URL; ?>/logout.php" style="color: #e74c3c;">🚪 Logout</a>
            </li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="admin-content">
        <a href="<?php echo ADMIN_URL; ?>/dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <div class="page-header">
            <h1 class="page-title">➕ Assign New Task</h1>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ <strong>Task assigned successfully!</strong> 
                Task #<?php echo $success_task_id; ?> has been assigned to <?php echo escape($success_user_name); ?>.
                <a href="<?php echo ADMIN_URL; ?>/task-detail.php?task_id=<?php echo $success_task_id; ?>">View Task</a> | 
                <a href="<?php echo ADMIN_URL; ?>/assign-task.php">Assign Another</a>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <span>❌ <?php echo escape($error); ?></span><br>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="assignTaskForm">
            <input type="hidden" name="csrf_token" value="<?php echo escape($csrf_token); ?>">
            <input type="hidden" name="user_id" id="selected_user_id" value="<?php echo $user_id; ?>">
            
            <!-- Step 1: Select User -->
            <div class="card" id="userSelectionCard">
                <h3 class="card-title">👤 Step 1: Select User</h3>
                
                <?php if ($user): ?>
                    <!-- User is already selected -->
                    <div class="selected-user-info">
                        <h4>✅ Selected User</h4>
                        <p><strong>Name:</strong> <?php echo escape($user['name']); ?></p>
                        <p><strong>Email:</strong> <?php echo escape($user['email']); ?></p>
                        <p><strong>Mobile:</strong> <?php echo escape($user['mobile']); ?></p>
                        <button type="button" class="change-user-btn" onclick="showUserSelection()">Change User</button>
                    </div>
                <?php endif; ?>
                
                <div id="userSelectionArea" style="<?php echo $user ? 'display:none;' : ''; ?>">
                    <?php if (empty($users)): ?>
                        <div class="no-users">
                            <h3>😔 No Active Users Found</h3>
                            <p>Please add users first before assigning tasks.</p>
                            <a href="<?php echo ADMIN_URL; ?>/reviewers.php" class="btn-submit" style="width:auto; display:inline-block; margin-top:15px;">Go to Users</a>
                        </div>
                    <?php else: ?>
                        <div class="search-box">
                            <input type="text" id="userSearch" placeholder="Search users by name, email or mobile..." onkeyup="filterUsers()">
                        </div>
                        
                        <div class="user-select-grid" id="userGrid">
                            <?php foreach ($users as $u): ?>
                                <div class="user-card <?php echo ($user_id == $u['id']) ? 'selected' : ''; ?>" 
                                     data-user-id="<?php echo $u['id']; ?>"
                                     data-name="<?php echo escape(strtolower($u['name'])); ?>"
                                     data-email="<?php echo escape(strtolower($u['email'])); ?>"
                                     data-mobile="<?php echo escape($u['mobile']); ?>"
                                     onclick="selectUser(<?php echo $u['id']; ?>, '<?php echo escape(addslashes($u['name'])); ?>', '<?php echo escape($u['email']); ?>', '<?php echo escape($u['mobile']); ?>')">
                                    <div class="user-card-header">
                                        <div class="user-avatar"><?php echo strtoupper(substr($u['name'], 0, 1)); ?></div>
                                        <div class="user-card-info">
                                            <h4><?php echo escape($u['name']); ?></h4>
                                            <p><?php echo escape($u['email']); ?></p>
                                        </div>
                                    </div>
                                    <div class="user-card-stats">
                                        <span>📱 <?php echo escape($u['mobile']); ?></span>
                                        <span>📋 <?php echo $u['total_tasks']; ?> tasks</span>
                                        <span>✅ <?php echo $u['completed_tasks']; ?> done</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Step 2: Task Details -->
            <div class="card">
                <h3 class="card-title">📝 Step 2: Task Details</h3>
                
                <div class="form-group">
                    <label for="product_link">Product Link (Amazon/Flipkart) *</label>
                    <input type="url" id="product_link" name="product_link" class="form-control" 
                           placeholder="https://www.amazon.in/dp/XXXXXXXXXX or https://www.flipkart.com/..." required
                           value="<?php echo escape($_POST['product_link'] ?? ''); ?>">
                    <p class="form-text">Paste the full product URL where user should purchase and submit review</p>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="commission">Commission Amount (₹)</label>
                        <input type="number" id="commission" name="commission" class="form-control" 
                               placeholder="0.00" step="0.01" min="0"
                               value="<?php echo escape($_POST['commission'] ?? '50'); ?>">
                        <p class="form-text">Amount user will earn after completing this task</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="deadline">Deadline</label>
                        <input type="date" id="deadline" name="deadline" class="form-control"
                               min="<?php echo date('Y-m-d'); ?>"
                               value="<?php echo escape($_POST['deadline'] ?? date('Y-m-d', strtotime('+7 days'))); ?>">
                        <p class="form-text">Set a deadline for task completion</p>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="priority">Priority Level</label>
                        <select id="priority" name="priority" class="form-control">
                            <option value="low" <?php echo (($_POST['priority'] ?? '') == 'low') ? 'selected' : ''; ?>>🟢 Low</option>
                            <option value="medium" <?php echo (($_POST['priority'] ?? 'medium') == 'medium') ? 'selected' : ''; ?>>🟡 Medium</option>
                            <option value="high" <?php echo (($_POST['priority'] ?? '') == 'high') ? 'selected' : ''; ?>>🟠 High</option>
                            <option value="urgent" <?php echo (($_POST['priority'] ?? '') == 'urgent') ? 'selected' : ''; ?>>🔴 Urgent</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Admin Notes (Optional)</label>
                        <input type="text" id="notes" name="notes" class="form-control" 
                               placeholder="Any special instructions..."
                               value="<?php echo escape($_POST['notes'] ?? ''); ?>">
                    </div>
                </div>
                
                <button type="submit" class="btn-submit" id="submitBtn" <?php echo !$user && empty($users) ? 'disabled' : ''; ?>>
                    ✨ Assign Task to User
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// User Selection Functions
function selectUser(userId, name, email, mobile) {
    // Remove previous selection
    document.querySelectorAll('.user-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Add selection to clicked card
    document.querySelector(`.user-card[data-user-id="${userId}"]`).classList.add('selected');
    
    // Update hidden input
    document.getElementById('selected_user_id').value = userId;
    
    // Create selected user info
    const selectedInfo = `
        <div class="selected-user-info">
            <h4>✅ Selected User</h4>
            <p><strong>Name:</strong> ${name}</p>
            <p><strong>Email:</strong> ${email}</p>
            <p><strong>Mobile:</strong> ${mobile}</p>
            <button type="button" class="change-user-btn" onclick="showUserSelection()">Change User</button>
        </div>
    `;
    
    // Hide user selection, show selected info
    document.getElementById('userSelectionArea').style.display = 'none';
    
    // Check if selected info already exists
    const existingInfo = document.querySelector('.selected-user-info');
    if (existingInfo) {
        existingInfo.outerHTML = selectedInfo;
    } else {
        document.querySelector('#userSelectionCard .card-title').insertAdjacentHTML('afterend', selectedInfo);
    }
    
    // Enable submit button
    document.getElementById('submitBtn').disabled = false;
}

function showUserSelection() {
    // Remove selected user info
    const selectedInfo = document.querySelector('.selected-user-info');
    if (selectedInfo) {
        selectedInfo.remove();
    }
    
    // Show user selection grid
    document.getElementById('userSelectionArea').style.display = 'block';
    
    // Clear hidden input
    document.getElementById('selected_user_id').value = '';
}

function filterUsers() {
    const searchValue = document.getElementById('userSearch').value.toLowerCase();
    const userCards = document.querySelectorAll('.user-card');
    
    userCards.forEach(card => {
        const name = card.dataset.name;
        const email = card.dataset.email;
        const mobile = card.dataset.mobile;
        
        if (name.includes(searchValue) || email.includes(searchValue) || mobile.includes(searchValue)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Form validation before submit
document.getElementById('assignTaskForm').addEventListener('submit', function(e) {
    const userId = document.getElementById('selected_user_id').value;
    const productLink = document.getElementById('product_link').value;
    
    if (!userId || userId === '0') {
        e.preventDefault();
        alert('Please select a user first!');
        return false;
    }
    
    if (!productLink) {
        e.preventDefault();
        alert('Please enter a product link!');
        return false;
    }
    
    return true;
});
</script>
</body>
</html>
