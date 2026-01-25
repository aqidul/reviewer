<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$task_id = intval($_GET['task_id'] ?? 0);
$errors = [];
$success = false;

try {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :task_id AND user_id = :user_id");
    $stmt->execute([':task_id' => $task_id, ':user_id' => $user_id]);
    $task = $stmt->fetch();
    
    if (!$task || $task['refund_requested']) {
        die('Invalid task or read-only mode');
    }
    
    // Check if Step 2 is completed
    $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :task_id AND step_number = 2 AND step_status = 'completed'");
    $stmt->execute([':task_id' => $task_id]);
    if ($stmt->rowCount() === 0) {
        die('Complete Step 2 first');
    }
    
    $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :task_id AND step_number = 3");
    $stmt->execute([':task_id' => $task_id]);
    $step_data = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    die('Database error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('CSRF error');
    }
    
    $review_submitted_screenshot = $step_data['review_submitted_screenshot'] ?? '';
    
    if (isset($_FILES['review_submitted_screenshot']) && $_FILES['review_submitted_screenshot']['error'] === UPLOAD_ERR_OK) {
        // Validate file size
        $max_size = 5 * 1024 * 1024; // 5MB
        if ($_FILES['review_submitted_screenshot']['size'] > $max_size) {
            $errors[] = 'File size must be less than 5MB';
        }
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $_FILES['review_submitted_screenshot']['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            $errors[] = 'Only JPG, PNG, GIF, and WebP images are allowed';
        }
        
        if (empty($errors)) {
            // Prepare file for upload to palians.com image host
            $cfile = new CURLFile(
                $_FILES['review_submitted_screenshot']['tmp_name'],
                $_FILES['review_submitted_screenshot']['type'],
                $_FILES['review_submitted_screenshot']['name']
            );
            
            $postData = ['image' => $cfile];
            
            // Upload to your image hosting website
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://palians.com/image-host/upload.php');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            // Add user agent
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            
            // Execute upload
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            // Debug logging
            error_log("Image Host Response (Step 3): HTTP $httpCode - " . $response);
            
            if ($httpCode === 200 && !empty($response)) {
                $lines = explode("\n", trim($response));
                if (!empty($lines[0])) {
                    $review_submitted_screenshot = $lines[0];
                } else {
                    $errors[] = 'Image uploaded but no URL returned';
                }
            } else {
                if ($curlError) {
                    $errors[] = 'Upload failed: ' . $curlError;
                } else {
                    $errors[] = 'Upload failed. HTTP Code: ' . $httpCode . ' - Response: ' . substr($response, 0, 100);
                }
            }
        }
    } elseif (isset($_FILES['review_submitted_screenshot']) && $_FILES['review_submitted_screenshot']['error'] !== UPLOAD_ERR_NO_FILE) {
        // File upload error
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive.',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
        ];
        
        $error_code = $_FILES['review_submitted_screenshot']['error'];
        $errors[] = $upload_errors[$error_code] ?? 'Unknown upload error (Code: ' . $error_code . ')';
    } elseif (empty($review_submitted_screenshot)) {
        // Only require if no existing screenshot
        $errors[] = 'Review screenshot is required';
    }
    
    if (empty($errors) && !empty($review_submitted_screenshot)) {
        try {
            if ($step_data) {
                $stmt = $pdo->prepare("
                    UPDATE task_steps SET review_submitted_screenshot = :screenshot, 
                    step_status = 'completed', submitted_by_user = true, updated_at = NOW()
                    WHERE task_id = :task_id AND step_number = 3
                ");
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO task_steps (task_id, step_number, step_name, review_submitted_screenshot, 
                    step_status, submitted_by_user) 
                    VALUES (:task_id, 3, 'Review Submitted', :screenshot, 'completed', true)
                ");
            }
            
            $stmt->execute([
                ':task_id' => $task_id,
                ':screenshot' => $review_submitted_screenshot
            ]);
            
            logActivity('User submitted Step 3 - Review Submitted', $task_id, $user_id);
            $success = true;
            
            $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :task_id AND step_number = 3");
            $stmt->execute([':task_id' => $task_id]);
            $step_data = $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors[] = 'Save failed: ' . $e->getMessage();
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
    <title>Step 3: Review Submitted - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .form-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .form-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 30px;
            color: #333;
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
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }
        .btn-submit {
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
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .alert {
            margin-bottom: 20px;
            padding: 12px;
            border-radius: 8px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .step-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        .file-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .current-image {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            border: 1px solid #e9ecef;
        }
        .current-image img {
            max-width: 200px;
            max-height: 150px;
            border-radius: 5px;
            margin-top: 5px;
            border: 1px solid #dee2e6;
        }
        .next-step-link {
            display: block;
            margin-top: 15px;
            padding: 12px;
            background: #27ae60;
            color: white;
            text-align: center;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }
        .next-step-link:hover {
            background: #219a52;
            color: white;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2 class="form-title">⭐ Step 3: Review Submitted</h2>
        
        <div class="step-info">
            <strong>📌 Task #<?php echo $task_id; ?></strong><br>
            <small>Upload the screenshot showing your review has been submitted</small>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                ✓ Review submission recorded successfully!
                <?php if (!empty($review_submitted_screenshot)): ?>
                    <br><small>Image uploaded: <a href="<?php echo escape($review_submitted_screenshot); ?>" target="_blank">View Screenshot</a></small>
                <?php endif; ?>
            </div>
            <a href="<?php echo APP_URL; ?>/user/submit-refund.php?task_id=<?php echo $task_id; ?>" class="next-step-link">
                Continue to Step 4 (Final Step) →
            </a>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger">✗ <?php echo escape($error); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="review_submitted_screenshot">Review Submitted Screenshot *</label>
                <input type="file" id="review_submitted_screenshot" name="review_submitted_screenshot" 
                       class="form-control" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" <?php echo empty($step_data['review_submitted_screenshot']) ? 'required' : ''; ?>>
                <div class="file-info">Max size: 5MB | Allowed: JPG, PNG, GIF, WebP</div>
                <small>Proof that your review has been posted</small>
                
                <?php if ($step_data && $step_data['review_submitted_screenshot']): ?>
                    <div class="current-image">
                        <strong>Current Screenshot:</strong><br>
                        <a href="<?php echo escape($step_data['review_submitted_screenshot']); ?>" target="_blank">
                            <img src="<?php echo escape($step_data['review_submitted_screenshot']); ?>" alt="Current Screenshot" onerror="this.style.display='none'">
                        </a>
                        <br>
                        <small>
                            <a href="<?php echo escape($step_data['review_submitted_screenshot']); ?>" target="_blank">
                                <?php echo escape($step_data['review_submitted_screenshot']); ?>
                            </a>
                        </small>
                    </div>
                <?php endif; ?>
            </div>
            
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <button type="submit" class="btn-submit">Submit Review Proof</button>
            
            <a href="<?php echo APP_URL; ?>/user/" class="back-link">← Back to Dashboard</a>
            <a href="<?php echo APP_URL; ?>/user/task-detail.php?task_id=<?php echo $task_id; ?>" class="back-link" style="margin-left: 15px;">View Task Details</a>
        </form>
    </div>
    
    <script>
        // File size validation client-side
        document.getElementById('review_submitted_screenshot').addEventListener('change', function(e) {
            var file = e.target.files[0];
            if (file) {
                var maxSize = 5 * 1024 * 1024; // 5MB
                if (file.size > maxSize) {
                    alert('File size must be less than 5MB');
                    e.target.value = '';
                }
            }
        });
    </script>
</body>
</html>
