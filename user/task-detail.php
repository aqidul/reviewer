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

try {
    $stmt = $pdo->prepare("
        SELECT t.*, u.name
        FROM tasks t
        JOIN users u ON t.user_id = u.id
        WHERE t.id = :task_id AND t.user_id = :user_id
    ");
    
    $stmt->execute([':task_id' => $task_id, ':user_id' => $user_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        die('Task not found');
    }
    
    // Fetch all steps
    $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :task_id ORDER BY step_number");
    $stmt->execute([':task_id' => $task_id]);
    $steps = $stmt->fetchAll();
    $steps_by_number = [];
    foreach ($steps as $s) {
        $steps_by_number[$s['step_number']] = $s;
    }
    
    // Calculate step completion status
    $step1_completed = isset($steps_by_number[1]) && $steps_by_number[1]['step_status'] === 'completed';
    $step2_completed = isset($steps_by_number[2]) && $steps_by_number[2]['step_status'] === 'completed';
    $step3_completed = isset($steps_by_number[3]) && $steps_by_number[3]['step_status'] === 'completed';
    $step4_data = $steps_by_number[4] ?? null;
    $step4_submitted = $step4_data && ($step4_data['step_status'] === 'pending_admin' || $step4_data['step_status'] === 'completed');
    $step4_completed = $step4_data && $step4_data['step_status'] === 'completed';
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    die('Error');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task #<?php echo $task_id; ?> Details</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 850px;
        }
        .task-header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .task-title {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 20px;
        }
        .task-meta {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .meta-item {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .meta-label {
            font-weight: 600;
            color: #666;
            font-size: 12px;
        }
        .meta-value {
            color: #2c3e50;
            font-weight: 600;
            margin-top: 5px;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
        }
        .status-pending { background: #ffeaa7; color: #d63031; }
