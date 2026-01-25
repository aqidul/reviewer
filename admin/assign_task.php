<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/db_connection.php';

if (!isAdmin()) {
    redirect('../index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = sanitizeInput($_POST['user_id']);
    $platform = sanitizeInput($_POST['platform']);
    $product_link = sanitizeInput($_POST['product_link']);
    $instructions = sanitizeInput($_POST['instructions']);
    $deadline = sanitizeInput($_POST['deadline']);
    $task_value = floatval($_POST['task_value']);
    
    if (empty($user_id) || empty($product_link) || empty($platform) || empty($deadline)) {
        $error = "All required fields must be filled!";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO tasks (user_id, platform, product_link, instructions, deadline, task_value, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'assigned', NOW())");
            $stmt->execute([$user_id, $platform, $product_link, $instructions, $deadline, $task_value]);
            $success = "Task successfully assigned!";
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch users for dropdown
$users = [];
try {
    $stmt = $pdo->query("SELECT id, username, email FROM users WHERE role = 'user'");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Failed to load users: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Task - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container mt-4">
        <h2 class="mb-4">📝 Assign New Task</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="user_id" class="form-label">Select User *</label>
                            <select class="form-select" id="user_id" name="user_id" required>
                                <option value="">-- Choose User --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo htmlspecialchars($user['id']); ?>">
                                        <?php echo htmlspecialchars($user['username'] . ' (' . $user['email'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="platform" class="form-label">Platform *</label>
                            <select class="form-select" id="platform" name="platform" required>
                                <option value="">-- Select Platform --</option>
                                <option value="Amazon">Amazon</option>
                                <option value="Flipkart">Flipkart</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="product_link" class="form-label">Product Link *</label>
                        <input type="url" class="form-control" id="product_link" name="product_link" 
                               placeholder="https://www.amazon.in/..." required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="instructions" class="form-label">Special Instructions</label>
                        <textarea class="form-control" id="instructions" name="instructions" 
                                  rows="3" placeholder="Any specific instructions for the reviewer..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="deadline" class="form-label">Deadline *</label>
                            <input type="date" class="form-control" id="deadline" name="deadline" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="task_value" class="form-label">Task Value (₹)</label>
                            <input type="number" class="form-control" id="task_value" name="task_value" 
                                   step="0.01" min="0" placeholder="e.g., 499.99">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Assign Task</button>
                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
