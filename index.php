<?php
/**
 * Home Page / Landing Page with Login/Register Routing
 */

require_once 'includes/config.php';
require_once 'includes/functions.php';

// Handle login/register actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = sanitizeInput($_POST['action']);
        
        if ($action === 'admin_login') {
            // Admin login (hardcoded credentials)
            $username = sanitizeInput($_POST['username']);
            $password = sanitizeInput($_POST['password']);
            
            if ($username === 'aqidulmumtaz' && $password === 'Malik@241123') {
                $_SESSION['user_id'] = 1;
                $_SESSION['username'] = 'admin';
                $_SESSION['user_type'] = 'admin';
                $_SESSION['admin_logged_in'] = true;
                
                redirect('admin/dashboard.php');
            } else {
                $error = "Invalid admin credentials!";
            }
        } elseif ($action === 'user_login') {
            // User login - will be redirected to actual login page
            redirect('login.php');
        }
    }
}

// If already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('user/dashboard.php');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Reviewer Task Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 100px 0;
        }
        .feature-icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 1rem;
        }
        .login-box {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="<?php echo BASE_URL; ?>/">
                <i class="bi bi-check-circle-fill"></i> ReviewFlow
            </a>
            <div class="navbar-nav ms-auto">
                <a href="#user-login" class="btn btn-outline-primary me-2" onclick="showUserLogin()">User Login</a>
                <a href="#user-register" class="btn btn-primary" onclick="showUserRegister()">Register</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Review Products, Get Refunded!</h1>
                    <p class="lead mb-4">Join our community of reviewers. Purchase products, submit honest reviews, and get 100% refund on successful completion.</p>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                        <a href="#user-register" class="btn btn-light btn-lg px-4 me-md-2" onclick="showUserRegister()">Get Started</a>
                        <a href="#how-it-works" class="btn btn-outline-light btn-lg px-4">Learn More</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="login-box">
                        <h3 class="mb-4 text-center">Admin Login</h3>
                        
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <form action="" method="POST">
                            <input type="hidden" name="action" value="admin_login">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Admin Login</button>
                        </form>
                        
                        <div class="mt-3 text-center">
                            <small class="text-muted">
                                Admin Credentials: aqidulmumtaz / Malik@241123
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">How It Works</h2>
            <div class="row text-center">
                <div class="col-md-3 mb-4">
                    <div class="feature-icon">
                        <i class="bi bi-cart-plus"></i>
                    </div>
                    <h4>1. Get Task</h4>
                    <p>Admin assigns you a product to review with detailed instructions.</p>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="feature-icon">
                        <i class="bi bi-bag-check"></i>
                    </div>
                    <h4>2. Purchase</h4>
                    <p>Buy the product from Amazon/Flipkart using provided link.</p>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="feature-icon">
                        <i class="bi bi-star-fill"></i>
                    </div>
                    <h4>3. Review</h4>
                    <p>Submit honest review with rating and screenshots as proof.</p>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="feature-icon">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                    <h4>4. Get Refund</h4>
                    <p>Receive 100% refund after admin verifies your submission.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- User Login/Register Modal -->
    <div class="modal fade" id="userAuthModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">User Login</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="loginForm" style="display: none;">
                        <div class="alert alert-info">
                            <strong>Note:</strong> User login system is under development. For now, contact admin to create your account.
                        </div>
                        <form id="userLoginForm" action="<?php echo BASE_URL; ?>/admin/index.php" method="GET">
                            <p>Please visit the admin panel to manage user accounts.</p>
                            <button type="submit" class="btn btn-primary w-100">Go to Admin Panel</button>
                        </form>
                    </div>
                    <div id="registerForm" style="display: none;">
                        <div class="alert alert-info">
                            <strong>Note:</strong> User registration is currently managed by admin. Please contact administrator to create an account.
                        </div>
                        <div class="text-center">
                            <p>Contact Admin: admin@reviewflow.com</p>
                            <a href="mailto:admin@reviewflow.com" class="btn btn-primary">Email Admin</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container text-center">
            <p>&copy; <?php echo date('Y'); ?> ReviewFlow - Reviewer Task Management System</p>
            <p class="mb-0">
                <a href="<?php echo BASE_URL; ?>/admin/index.php" class="text-white text-decoration-none me-3">Admin Panel</a> | 
                Contact: admin@reviewflow.com | Support: +91-XXXXXXXXXX
            </p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showUserLogin() {
            document.getElementById('loginForm').style.display = 'block';
            document.getElementById('registerForm').style.display = 'none';
            document.getElementById('modalTitle').innerText = 'User Login';
            var modal = new bootstrap.Modal(document.getElementById('userAuthModal'));
            modal.show();
        }
        
        function showUserRegister() {
            document.getElementById('loginForm').style.display = 'none';
            document.getElementById('registerForm').style.display = 'block';
            document.getElementById('modalTitle').innerText = 'User Registration';
            var modal = new bootstrap.Modal(document.getElementById('userAuthModal'));
            modal.show();
        }
    </script>
</body>
</html>
