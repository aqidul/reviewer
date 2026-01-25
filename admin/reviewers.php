<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = escape($_SESSION['admin_name'] ?? 'Admin');

// Channel Links
$whatsapp_channel = 'https://whatsapp.com/channel/0029VbC3kV4A89MjSMOyFX1o';
$telegram_channel = 'https://t.me/palimall';

// Pre-defined message for WhatsApp
$whatsapp_message = "🎉 Welcome to Review Task Management!

📢 Join our official channels for important updates:

✅ WhatsApp Channel: {$whatsapp_channel}

✅ Telegram Channel: {$telegram_channel}

Stay connected for task updates, announcements & support!";

// Fetch all USERS (not admins)
$users = [];
$error_message = null;

try {
    // FIX: Match actual database schema and filter by user_type = 'user'
    $stmt = $pdo->prepare("
        SELECT 
            u.id, 
            u.name, 
            u.email, 
            u.mobile, 
            u.created_at,
            COUNT(DISTINCT t.id) as assigned_tasks,
            SUM(CASE WHEN t.refund_requested = true THEN 1 ELSE 0 END) as completed_tasks
        FROM users u
        LEFT JOIN tasks t ON u.id = t.user_id
        WHERE u.user_type = 'user'
        AND u.status = 'active'
        GROUP BY u.id, u.name, u.email, u.mobile, u.created_at
        ORDER BY u.created_at DESC
    ");
    
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    error_log("Reviewers found: " . count($users));
    
} catch (PDOException $e) {
    error_log('Database error in reviewers.php: ' . $e->getMessage());
    $error_message = 'Database error: ' . $e->getMessage();
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Reviewers - Admin Panel</title>
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
            font-size: 20px;
            margin-bottom: 5px;
            color: white;
        }
        .sidebar-brand p {
            font-size: 11px;
            color: #bbb;
        }
        .sidebar-menu {
            list-style: none;
        }
        .sidebar-menu li {
            margin-bottom: 10px;
        }
        .sidebar-menu a {
            color: #bbb;
            text-decoration: none;
            padding: 12px 15px;
            display: block;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .admin-content {
            padding: 30px;
        }
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .content-header h1 {
            color: #2c3e50;
            font-size: 28px;
        }
        .table-wrapper {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        thead {
            background: #f8f9fa;
        }
        th {
            border-bottom: 2px solid #ddd;
            font-weight: 600;
            color: #2c3e50;
            padding: 15px;
            text-align: left;
        }
        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        tbody tr:hover {
            background: #f9f9f9;
        }
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-primary {
            background: #3498db;
            color: white;
        }
        .badge-success {
            background: #27ae60;
            color: white;
        }
        .action-btn {
            padding: 8px 15px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .action-btn:hover {
            background: #2980b9;
            color: white;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .empty-state h3 {
            color: #333;
            margin-bottom: 10px;
        }
        
        /* Mobile & Contact Buttons */
        .mobile-cell {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .mobile-number {
            font-weight: 600;
            color: #2c3e50;
        }
        .contact-btns {
            display: flex;
            gap: 5px;
        }
        .wa-btn, .tg-btn {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.3s;
        }
        .wa-btn {
            background: #25D366;
            color: white;
        }
        .wa-btn:hover {
            background: #1fb855;
            color: white;
            transform: scale(1.05);
        }
        .tg-btn {
            background: #0088cc;
            color: white;
        }
        .tg-btn:hover {
            background: #0077b5;
            color: white;
            transform: scale(1.05);
        }
        
        /* Channel Info Box */
        .channel-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .channel-info h4 {
            margin: 0 0 10px 0;
            font-size: 16px;
        }
        .channel-info p {
            margin: 0;
            font-size: 13px;
            opacity: 0.9;
        }
        .channel-links {
            display: flex;
            gap: 10px;
        }
        .channel-link {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .channel-link.whatsapp {
            background: #25D366;
            color: white;
        }
        .channel-link.telegram {
            background: #0088cc;
            color: white;
        }
        .channel-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: white;
        }
        
        /* Action buttons column */
        .action-cell {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <div class="admin-sidebar">
            <div class="sidebar-brand">
                <h3>⚙️ Admin</h3>
                <p><?php echo escape(APP_NAME); ?></p>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="<?php echo ADMIN_URL; ?>/dashboard.php">📊 Dashboard</a></li>
                <li><a href="<?php echo ADMIN_URL; ?>/reviewers.php" class="active">👥 Reviewers</a></li>
                <li><a href="<?php echo ADMIN_URL; ?>/task-pending.php">📋 Pending Tasks</a></li>
                <li><a href="<?php echo ADMIN_URL; ?>/task-completed.php">✓ Completed Tasks</a></li>
                <li><a href="<?php echo ADMIN_URL; ?>/faq-manager.php">🤖 Chatbot FAQ</a></li>
                <li style="margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 30px;">
                    <a href="<?php echo APP_URL; ?>/logout.php" style="color: #e74c3c;">🚪 Logout</a>
                </li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="admin-content">
            <div class="content-header">
                <h1>👥 All Reviewers (<?php echo count($users); ?>)</h1>
            </div>
            
            <!-- Channel Info Box -->
            <div class="channel-info">
                <div>
                    <h4>📢 Official Channels</h4>
                    <p>Share these channels with reviewers for task updates & announcements</p>
                </div>
                <div class="channel-links">
                    <a href="<?php echo $whatsapp_channel; ?>" target="_blank" class="channel-link whatsapp">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        WhatsApp Channel
                    </a>
                    <a href="<?php echo $telegram_channel; ?>" target="_blank" class="channel-link telegram">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                        </svg>
                        Telegram Channel
                    </a>
                </div>
            </div>
            
            <?php if (isset($error_message)): ?>
                <div style="padding: 15px; background: #f8d7da; color: #721c24; border-radius: 8px; margin-bottom: 20px;">
                    ❌ <?php echo escape($error_message); ?>
                </div>
            <?php endif; ?>
            
            <div class="table-wrapper">
                <?php if (empty($users)): ?>
                    <div class="empty-state">
                        <h3>📭 No reviewers registered yet</h3>
                        <p>Users with 'user' type will appear here</p>
                        <p style="margin-top: 15px; font-size: 13px;">
                            Current test users: 
                            <strong>aqidulm@gmail.com</strong> (Mobile: 8604261683)
                            <br>
                            <strong>gopalashukla18@gmail.com</strong> (Mobile: 7379162377)
                        </p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Mobile</th>
                                <th>Tasks Assigned</th>
                                <th>Completed</th>
                                <th>Joined</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): 
                                $mobile = $user['mobile'] ?? '';
                                // Format mobile for WhatsApp (add country code if not present)
                                $wa_mobile = $mobile;
                                if (!empty($mobile) && strpos($mobile, '+') !== 0) {
                                    $wa_mobile = '91' . ltrim($mobile, '0'); // India country code
                                }
                                // URL encode the message
                                $wa_msg_encoded = urlencode($whatsapp_message);
                            ?>
                                <tr>
                                    <td><strong><?php echo escape($user['name'] ?? 'N/A'); ?></strong></td>
                                    <td><?php echo escape($user['email'] ?? 'N/A'); ?></td>
                                    <td>
                                        <div class="mobile-cell">
                                            <span class="mobile-number"><?php echo escape($mobile ?: 'N/A'); ?></span>
                                            <?php if (!empty($mobile)): ?>
                                                <div class="contact-btns">
                                                    <a href="https://wa.me/<?php echo $wa_mobile; ?>?text=<?php echo $wa_msg_encoded; ?>" 
                                                       target="_blank" 
                                                       class="wa-btn" 
                                                       title="Send WhatsApp message with channel links">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                                        </svg>
                                                        WA
                                                    </a>
                                                    <a href="https://t.me/share/url?url=<?php echo urlencode($telegram_channel); ?>&text=<?php echo urlencode("Join our official Telegram channel for task updates: "); ?>" 
                                                       target="_blank" 
                                                       class="tg-btn" 
                                                       title="Share Telegram channel link">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                                            <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                                                        </svg>
                                                        TG
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary">
                                            <?php echo (int)($user['assigned_tasks'] ?? 0); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-success">
                                            <?php echo (int)($user['completed_tasks'] ?? 0); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($user['created_at'] ?? 'now')); ?></td>
                                    <td>
                                        <a href="<?php echo ADMIN_URL; ?>/assign-task.php?user_id=<?php echo (int)$user['id']; ?>" 
                                           class="action-btn">
                                            ➕ Assign Task
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
