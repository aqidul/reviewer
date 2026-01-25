<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/security.php';

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/user/');
    exit;
}

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
    <title><?php echo APP_NAME; ?> - Reviewer Task Management</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/bootstrap.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-size: 24px;
            font-weight: 600;
            color: #667eea;
        }
        .hero-section {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        .hero-content {
            max-width: 1200px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
        }
        .hero-text h1 {
            font-size: 48px;
            color: white;
            margin-bottom: 20px;
            font-weight: 700;
        }
        .hero-text p {
            font-size: 18px;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .features {
            list-style: none;
            margin-bottom: 30px;
        }
        .features li {
            color: white;
            margin-bottom: 15px;
            font-size: 16px;
            display: flex;
            align-items: center;
        }
        .features li:before {
            content: "✓";
            display: inline-block;
            width: 30px;
            height: 30px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            margin-right: 15px;
            font-weight: bold;
        }
        .btn-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .btn-primary-custom {
            padding: 15px 40px;
            background: white;
            color: #667eea;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        .btn-secondary-custom {
            padding: 15px 40px;
            background: transparent;
            color: white;
            border: 2px solid white;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-secondary-custom:hover {
            background: white;
            color: #667eea;
        }
        .cards-section {
            background: white;
            border-radius: 15px;
            padding: 40px 30px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .card-item {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            border-radius: 12px;
            color: white;
            text-align: center;
            transition: transform 0.3s;
        }
        .card-item:hover {
            transform: translateY(-5px);
        }
        .card-item h3 {
            font-size: 20px;
            margin-bottom: 10px;
        }
        .card-item p {
            font-size: 14px;
            opacity: 0.9;
        }
        .card-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        .login-form {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-top: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-control:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-login:hover {
            transform: translateY(-2px);
        }
        .signup-link {
            text-align: center;
            margin-top: 15px;
            color: #666;
        }
        .signup-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .footer {
            text-align: center;
            color: white;
            padding: 30px;
            margin-top: 50px;
        }
        @media (max-width: 768px) {
            .hero-content {
                grid-template-columns: 1fr;
            }
            .hero-text h1 {
                font-size: 32px;
            }
            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">🚀 <?php echo APP_NAME; ?></div>
    </nav>
    
    <section class="hero-section">
        <div class="hero-content">
            <div class="hero-text">
                <h1>Start Earning Today! 💰</h1>
                <p>Complete simple review tasks and get paid instantly. No experience needed!</p>
                
                <ul class="features">
                    <li>Simple 4-step review process</li>
                    <li>Instant payment after completion</li>
                    <li>Work from anywhere, anytime</li>
                    <li>24/7 AI chat support</li>
                </ul>
                
                <div class="btn-group">
                    <a href="<?php echo APP_URL; ?>/user/signup.php" class="btn-primary-custom">Sign Up Now</a>
                    <a href="<?php echo APP_URL; ?>/chatbot/" class="btn-secondary-custom">💬 Need Help?</a>
                </div>
            </div>
            
            <div class="cards-section">
                <div class="card-item">
                    <div class="card-icon">📦</div>
                    <h3>Step 1</h3>
                    <p>Receive product & place order</p>
                </div>
                <div class="card-item">
                    <div class="card-icon">🚚</div>
                    <h3>Step 2</h3>
                    <p>Confirm delivery</p>
                </div>
                <div class="card-item">
                    <div class="card-icon">⭐</div>
                    <h3>Step 3</h3>
                    <p>Submit your review</p>
                </div>
                <div class="card-item">
                    <div class="card-icon">💸</div>
                    <h3>Step 4</h3>
                    <p>Get paid instantly</p>
                </div>
            </div>
        </div>
    </section>
    
    <div style="max-width: 400px; margin: 0 auto; padding: 40px 20px;">
        <div class="login-form">
            <h3 style="text-align: center; margin-bottom: 25px; color: #333;">Already registered?</h3>
            
            <form method="GET" action="<?php echo APP_URL; ?>/index.php">
                <div class="form-group">
                    <label for="login_field">Email or Mobile</label>
                    <input type="text" id="login_field" name="login_field" class="form-control" placeholder="Enter email or mobile">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter password">
                </div>
                
                <button type="submit" formmethod="POST" formaction="<?php echo APP_URL; ?>/user/login.php" class="btn-login">Login</button>
            </form>
            
            <div class="signup-link">
                New user? <a href="<?php echo APP_URL; ?>/user/signup.php">Create account</a>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <p>&copy; 2026 <?php echo APP_NAME; ?>. All rights reserved. | Secure & Fast Payment</p>
    </div>
</body>
</html>
