<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    redirectTo(APP_URL . '/user/');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Token Verification
    $csrf_token_input = $_POST['csrf_token'] ?? '';
    
    if (empty($csrf_token_input)) {
        $errors[] = 'CSRF token missing. Please refresh and try again.';
    } elseif (!verifyCSRFToken($csrf_token_input)) {
        $errors[] = 'CSRF token validation failed. Please refresh and try again.';
    } else {
        // Token verified
        $login_identifier = $_POST['login_field'] ?? 'unknown';
        if (!checkRateLimit('login_attempt_' . $login_identifier, limit: 5, window: 300)) {
            $errors[] = 'Too many login attempts. Please try again in 5 minutes.';
        }
        
        if (empty($errors)) {
            $login_field = sanitizeInput($_POST['login_field'] ?? '');
            $password = $_POST['password'] ?? '';
            
            // Validate inputs
            if (empty($login_field)) {
                $errors[] = 'Email or Mobile number is required';
            }
            
            if (empty($password)) {
                $errors[] = 'Password is required';
            }
            
            // Verify credentials
            if (empty($errors)) {
                try {
                    // Check if login field is email or mobile
                    $is_email = str_contains($login_field, '@');
                    $field = $is_email ? 'email' : 'mobile';
                    
                    // FIX: Match actual database schema
                    $stmt = $pdo->prepare("
                        SELECT id, name, email, mobile, password, status, user_type
                        FROM users 
                        WHERE $field = :login_field 
                        AND user_type = 'user'
                        AND status = 'active'
                        LIMIT 1
                    ");
                    
                    $stmt->execute([':login_field' => $login_field]);
                    $user = $stmt->fetch();
                    
                    if ($user && verifyPassword($password, $user['password'])) {
                        // Check if password needs rehash
                        if (needsRehash($user['password'])) {
                            $new_hash = hashPassword($password);
                            $update_stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
                            $update_stmt->execute([
                                ':password' => $new_hash,
                                ':id' => (int)$user['id']
                            ]);
                        }
                        
                        // Set session
                        $_SESSION['user_id'] = (int)$user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_mobile'] = $user['mobile'];
                        $_SESSION['login_time'] = time();
                        
                        // Log activity
                        logActivity('User Login', null, (int)$user['id']);
                        
                        // Redirect to dashboard
                        header('Location: ' . APP_URL . '/user/');
                        exit;
                        
                    } else {
                        $errors[] = 'Invalid credentials or account inactive';
                        error_log("Failed login attempt for: $login_field");
                    }
                } catch (PDOException $e) {
                    error_log('Login error: ' . $e->getMessage());
                    $errors[] = 'Login failed. Please try again.';
                }
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>User Login - <?php echo escape(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
        }
        .login-card h2 {
            margin-bottom: 30px;
            color: #333;
            font-weight: 600;
            text-align: center;
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
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
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
            transition: all 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        .signup-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        .signup-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            border-left: 4px solid;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-color: #e74c3c;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <h2>🔐 User Login</h2>
            
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger">
                        ✗ <?php echo escape($error); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="login_field">Email or Mobile *</label>
                    <input type="text" class="form-control" id="login_field" name="login_field" 
                           value="<?php echo escape($_POST['login_field'] ?? ''); ?>" 
                           placeholder="Enter email or 10-digit mobile" required>
                    <small style="color: #666;">Example: 8604261683 or aqidulm@gmail.com</small>
                </div>
                
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Enter your password" required>
                </div>
                
                <input type="hidden" name="csrf_token" value="<?php echo escape($csrf_token); ?>">
                
                <button type="submit" class="btn-login">🔓 Login</button>
            </form>
            
            <div class="signup-link">
                New user? <a href="<?php echo APP_URL; ?>/user/signup.php">Create account here</a>
            </div>
        </div>
    </div>
</body>
</html>
