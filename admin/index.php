<?php
/**
 * Admin Login Page
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

// If already logged in as admin, redirect to dashboard
if (isLoggedIn() && isAdmin()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = sanitizeInput($_POST['password']);
    
    // Hardcoded admin credentials (as per requirements)
    if ($username === 'aqidulmumtaz' && $password === 'Malik@241123') {
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'admin';
        $_SESSION['user_type'] = 'admin';
        $_SESSION['admin_logged_in'] = true;
        
        // Log activity
        logActivity('Admin Login', 'Admin logged in from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
        redirect('dashboard.php');
    } else {
        $error = "Invalid admin credentials!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Reviewer Task Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header i {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 15px;
        }
        .back-home {
            margin-top: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="bi bi-shield-lock-fill"></i>
            <h2>Admin Login</h2>
            <p class="text-muted">Enter admin credentials to continue</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required autofocus>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember">
                <label class="form-check-label" for="remember">Remember me</label>
            </div>
            
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-box-arrow-in-right"></i> Login
            </button>
        </form>
        
        <div class="mt-4 text-center">
            <small class="text-muted">
                <strong>Default Credentials:</strong><br>
                Username: aqidulmumtaz<br>
                Password: Malik@241123
            </small>
        </div>
        
        <div class="back-home">
            <a href="<?php echo BASE_URL; ?>/" class="text-decoration-none">
                <i class="bi bi-arrow-left"></i> Back to Homepage
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
