<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

// Admin authentication using environment variables
$admin_user = env('ADMIN_EMAIL', 'admin@reviewflow.com');
$admin_pass = env('ADMIN_PASSWORD', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // For security, check against hashed password if stored
    // For initial setup, allow direct comparison but log warning
    $isValidPassword = false;
    
    // Check if admin_pass is a hash
    if (strpos($admin_pass, '$2y$') === 0) {
        // It's a bcrypt hash
        $isValidPassword = password_verify($password, $admin_pass);
    } else {
        // Plain text password (only for development/initial setup)
        $isValidPassword = ($password === $admin_pass);
        error_log("WARNING: Admin password is not hashed. Please hash the password in .env file.");
    }
    
    if ($username === $admin_user && $isValidPassword) {
        $_SESSION['admin_name'] = $username;
        $_SESSION['admin_login_time'] = time();
        header('Location: ' . ADMIN_URL . '/dashboard.php');
        exit;
    } else {
        $error = 'Invalid admin credentials';
    }
}

// Check if already logged in
if (isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL . '/dashboard.php');
    exit;
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #2c3e50 0%, #000000 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            width: 100%;
            max-width: 450px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 50px 40px;
        }
        .admin-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .admin-logo h1 {
            font-size: 32px;
            color: #2c3e50;
            margin: 0;
        }
        .admin-logo p {
            color: #666;
            font-size: 12px;
            margin: 5px 0 0 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        .form-control:focus {
            border-color: #2c3e50;
            outline: none;
            box-shadow: 0 0 0 3px rgba(44, 62, 80, 0.1);
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.3s;
            margin-top: 10px;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(44, 62, 80, 0.3);
        }
        .alert {
            margin-bottom: 20px;
            padding: 12px;
            border-radius: 8px;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="admin-logo">
            <h1>🔐 Admin Panel</h1>
            <p><?php echo APP_NAME; ?></p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo escape($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Admin Username</label>
                <input type="text" id="username" name="username" class="form-control" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Admin Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <button type="submit" class="btn-login">Login</button>
        </form>
    </div>
</body>
</html>
