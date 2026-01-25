<?php
require_once '../includes/config.php';

if (!isLoggedIn() || isAdmin()) {
    redirect('../index.php');
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Get user details
try {
    $query = "SELECT * FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        redirect('../index.php');
    }
} catch(PDOException $e) {
    $user = [];
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $mobile = trim($_POST['mobile']);
        
        if (empty($name) || empty($email) || empty($mobile)) {
            $message = 'All fields are required!';
            $message_type = 'error';
        } else {
            try {
                // Check if email exists for another user
                $checkQuery = "SELECT id FROM users WHERE email = :email AND id != :user_id";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->execute([':email' => $email, ':user_id' => $user_id]);
                
                if ($checkStmt->rowCount() > 0) {
                    $message = 'Email already exists for another user!';
                    $message_type = 'error';
                } else {
                    // Update profile
                    $updateQuery = "UPDATE users SET name = :name, email = :email, mobile = :mobile, updated_at = NOW() WHERE id = :id";
                    $updateStmt = $db->prepare($updateQuery);
                    $updateStmt->execute([
                        ':name' => $name,
                        ':email' => $email,
                        ':mobile' => $mobile,
                        ':id' => $user_id
                    ]);
                    
                    // Update session
                    $_SESSION['user_name'] = $name;
                    
                    $message = 'Profile updated successfully!';
                    $message_type = 'success';
                    
                    // Refresh user data
                    $stmt->execute([':user_id' => $user_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            } catch(PDOException $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $message = 'All password fields are required!';
            $message_type = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = 'New passwords do not match!';
            $message_type = 'error';
        } elseif (strlen($new_password) < 6) {
            $message = 'New password must be at least 6 characters!';
            $message_type = 'error';
        } else {
            try {
                // Verify current password
                $checkQuery = "SELECT password FROM users WHERE id = :user_id";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->execute([':user_id' => $user_id]);
                $db_user = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($db_user && password_verify($current_password, $db_user['password'])) {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $updateQuery = "UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id";
                    $updateStmt = $db->prepare($updateQuery);
                    $updateStmt->execute([
                        ':password' => $hashed_password,
                        ':id' => $user_id
                    ]);
                    
                    $message = 'Password changed successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Current password is incorrect!';
                    $message_type = 'error';
                }
            } catch(PDOException $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// Get user statistics
try {
    $statsQuery = "
        SELECT 
            COUNT(DISTINCT t.id) as total_tasks,
            COUNT(DISTINCT o.id) as total_orders,
            SUM(CASE WHEN o.step4_status = 'approved' THEN 1 ELSE 0 END) as completed_orders,
            COUNT(DISTINCT CASE WHEN t.status = 'pending' OR t.status = 'in_progress' THEN t.id END) as pending_tasks
        FROM tasks t
        LEFT JOIN orders o ON t.id = o.task_id
        WHERE t.user_id = :user_id
    ";
    
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->execute([':user_id' => $user_id]);
    $user_stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_stats) {
        $user_stats = [
            'total_tasks' => 0,
            'total_orders' => 0,
            'completed_orders' => 0,
            'pending_tasks' => 0
        ];
    }
} catch(PDOException $e) {
    $user_stats = [
        'total_tasks' => 0,
        'total_orders' => 0,
        'completed_orders' => 0,
        'pending_tasks' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - ReviewFlow</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 20px;
            border-radius: 5px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .container {
            display: flex;
            min-height: calc(100vh - 70px);
        }
        
        .sidebar {
            width: 250px;
            background: white;
            border-right: 1px solid #e0e0e0;
            padding: 20px 0;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 25px;
            color: #555;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover {
            background: #f0f4ff;
            color: #4361ee;
        }
        
        .sidebar-menu a.active {
            background: #4361ee;
            color: white;
        }
        
        .main-content {
            flex: 1;
            padding: 25px;
            background: #f5f5f5;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .card-header h2 {
            font-size: 1.3rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn {
            background: #4361ee;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn:hover {
            background: #3a0ca3;
            transform: translateY(-2px);
        }
        
        .btn.green { background: #2ecc71; }
        .btn.green:hover { background: #27ae60; }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #4361ee;
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.2);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            font-weight: bold;
        }
        
        .profile-info h2 {
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .profile-info p {
            margin: 0 0 10px 0;
            color: #666;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            text-align: center;
            border-top: 5px solid #4361ee;
        }
        
        .stat-card:nth-child(2) { border-top-color: #f39c12; }
        .stat-card:nth-child(3) { border-top-color: #2ecc71; }
        .stat-card:nth-child(4) { border-top-color: #e74c3c; }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #4361ee;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 20px;
        }
        
        .stat-card:nth-child(2) .stat-icon { background: #f39c12; }
        .stat-card:nth-child(3) .stat-icon { background: #2ecc71; }
        .stat-card:nth-child(4) .stat-icon { background: #e74c3c; }
        
        .stat-info h3 {
            font-size: 28px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-info p {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }
            
            .sidebar {
                position: fixed;
                left: -250px;
                top: 70px;
                height: calc(100vh - 70px);
                transition: left 0.3s;
                z-index: 1000;
            }
            
            .sidebar.open {
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="light-mode">
    <!-- Header -->
    <div class="header">
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>
        <h1>
            <i class="fas fa-user-circle"></i>
            ReviewFlow - User Panel
        </h1>
        <div class="user-info">
            <span>
                <i class="fas fa-user"></i>
                <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            </span>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>
    
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="tasks.php"><i class="fas fa-tasks"></i> My Tasks</a></li>
                <li><a href="submit_order.php"><i class="fas fa-shopping-cart"></i> Submit Order</a></li>
                <li><a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($user['name']); ?></h2>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['mobile']); ?></p>
                    <p><i class="fas fa-calendar"></i> Member since: <?php echo date('d M Y', strtotime($user['created_at'])); ?></p>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $user_stats['total_tasks']; ?></h3>
                        <p>Total Tasks</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $user_stats['pending_tasks']; ?></h3>
                        <p>Pending Tasks</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $user_stats['completed_orders']; ?></h3>
                        <p>Completed Orders</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $user_stats['total_orders']; ?></h3>
                        <p>Total Orders</p>
                    </div>
                </div>
            </div>
            
            <!-- Update Profile Form -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-edit"></i> Update Profile</h2>
                </div>
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name"><i class="fas fa-user"></i> Full Name</label>
                            <input type="text" id="name" name="name" class="form-control" required 
                                   value="<?php echo htmlspecialchars($user['name']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" required 
                                   value="<?php echo htmlspecialchars($user['email']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="mobile"><i class="fas fa-phone"></i> Mobile Number</label>
                            <input type="text" id="mobile" name="mobile" class="form-control" required 
                                   value="<?php echo htmlspecialchars($user['mobile']); ?>"
                                   pattern="[0-9]{10}" maxlength="10">
                        </div>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn green">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>
            
            <!-- Change Password Form -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-key"></i> Change Password</h2>
                </div>
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="current_password"><i class="fas fa-lock"></i> Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password"><i class="fas fa-lock"></i> New Password</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password"><i class="fas fa-lock"></i> Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="6">
                        </div>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>
            
            <!-- Account Information -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-info-circle"></i> Account Information</h2>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> User ID</label>
                        <input type="text" class="form-control" value="#<?php echo $user['id']; ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-user-tag"></i> Account Type</label>
                        <input type="text" class="form-control" value="<?php echo ucfirst($user['user_type']); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-calendar-plus"></i> Registration Date</label>
                        <input type="text" class="form-control" value="<?php echo date('d M Y, h:i A', strtotime($user['created_at'])); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-calendar-check"></i> Last Updated</label>
                        <input type="text" class="form-control" value="<?php echo $user['updated_at'] ? date('d M Y, h:i A', strtotime($user['updated_at'])) : 'Never'; ?>" readonly>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Mobile menu toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');
        
        if (mobileMenuBtn && sidebar) {
            mobileMenuBtn.addEventListener('click', function() {
                sidebar.classList.toggle('open');
            });
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768 && 
                sidebar.classList.contains('open') && 
                !sidebar.contains(e.target) && 
                e.target !== mobileMenuBtn) {
                sidebar.classList.remove('open');
            }
        });
        
        // Password validation
        document.querySelector('form[name="change_password"]').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>
