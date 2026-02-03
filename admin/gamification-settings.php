<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/gamification-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = escape($_SESSION['admin_name'] ?? 'Admin');

$message = '';

// Get all statistics
try {
    $total_users_stmt = $pdo->query("SELECT COUNT(*) FROM user_points");
    $total_users = $total_users_stmt->fetchColumn();

    $total_points_stmt = $pdo->query("SELECT SUM(total_earned) FROM user_points");
    $total_points = $total_points_stmt->fetchColumn();

    $total_badges_stmt = $pdo->query("SELECT COUNT(*) FROM user_badges");
    $total_badges = $total_badges_stmt->fetchColumn();

    // Get level distribution
    $level_dist = $pdo->query("
    SELECT level, COUNT(*) as count 
    FROM user_points 
    GROUP BY level 
    ORDER BY 
        CASE level
            WHEN 'Diamond' THEN 5
            WHEN 'Platinum' THEN 4
            WHEN 'Gold' THEN 3
            WHEN 'Silver' THEN 2
            ELSE 1
        END DESC
")->fetchAll(PDO::FETCH_ASSOC);

    // Get level settings
    $level_settings = getLevelSettings($pdo);

    // Get all badges
    $all_badges = getAllBadges($pdo);

    // Get recent point transactions
    $recent_transactions = $pdo->query("
    SELECT pt.*, u.username
    FROM point_transactions pt
    JOIN users u ON pt.user_id = u.id
    ORDER BY pt.created_at DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = 'Database error';
    $total_users = $total_points = $total_badges = 0;
    $level_dist = $level_settings = $all_badges = $recent_transactions = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gamification Settings - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
/* Admin Layout */
.admin-layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}

/* Sidebar styles */
.sidebar{background:linear-gradient(180deg,#2c3e50 0%,#1a252f 100%);color:#fff;padding:0;position:sticky;top:0;height:100vh;overflow-y:auto}
.sidebar-header{padding:25px 20px;border-bottom:1px solid rgba(255,255,255,0.1)}
.sidebar-header h2{font-size:20px;display:flex;align-items:center;gap:10px}
.sidebar-menu{list-style:none;padding:15px 0}
.sidebar-menu li{margin-bottom:5px}
.sidebar-menu a{display:flex;align-items:center;gap:12px;padding:12px 20px;color:#94a3b8;text-decoration:none;transition:all 0.2s;border-left:3px solid transparent}
.sidebar-menu a:hover,.sidebar-menu a.active{background:rgba(255,255,255,0.05);color:#fff;border-left-color:#667eea}
.sidebar-menu .badge{background:#e74c3c;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:auto}
.sidebar-divider{height:1px;background:rgba(255,255,255,0.1);margin:15px 20px}
.menu-section-label{padding:8px 20px;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px}
.sidebar-menu a.logout{color:#e74c3c}

/* Main Content */
.main-content{padding:25px;overflow-x:hidden}
</style>
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="main-content">
        <h2 class="mb-4"><i class="bi bi-trophy-fill"></i> Gamification System</h2>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h3><?php echo number_format($total_users); ?></h3>
                            <p class="mb-0">Total Users in System</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h3><?php echo number_format($total_points); ?></h3>
                            <p class="mb-0">Total Points Earned</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h3><?php echo number_format($total_badges); ?></h3>
                            <p class="mb-0">Total Badges Awarded</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Level Distribution -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-bar-chart"></i> User Level Distribution</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Level</th>
                                    <th>Users</th>
                                    <th>Point Range</th>
                                    <th>Perks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($level_settings as $level): ?>
                                <tr>
                                    <td>
                                        <strong style="color: <?php echo $level['badge_color']; ?>">
                                            <?php echo $level['level_name']; ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php
                                        $count = 0;
                                        foreach ($level_dist as $dist) {
                                            if ($dist['level'] == $level['level_name']) {
                                                $count = $dist['count'];
                                                break;
                                            }
                                        }
                                        echo $count;
                                        ?>
                                    </td>
                                    <td><?php echo number_format($level['min_points']); ?> - <?php echo number_format($level['max_points']); ?></td>
                                    <td><?php echo htmlspecialchars($level['perks']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Badges Overview -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-award"></i> Badge System</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($all_badges as $badge): ?>
                        <div class="col-md-3 mb-3">
                            <div class="card text-center h-100">
                                <div class="card-body">
                                    <i class="<?php echo htmlspecialchars($badge['icon']); ?>" 
                                       style="font-size: 2.5rem; color: #fbbf24;"></i>
                                    <h6 class="mt-2"><?php echo htmlspecialchars($badge['name']); ?></h6>
                                    <p class="text-muted small mb-0"><?php echo htmlspecialchars($badge['description']); ?></p>
                                    <div class="mt-2">
                                        <?php
                                        $earned_count = $pdo->prepare("SELECT COUNT(*) FROM user_badges WHERE badge_id = ?");
                                        $earned_count->execute([$badge['id']]);
                                        $count = $earned_count->fetchColumn();
                                        ?>
                                        <span class="badge bg-success"><?php echo $count; ?> users</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-clock-history"></i> Recent Point Transactions</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Points</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_transactions as $trans): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($trans['username']); ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo ucfirst(str_replace('_', ' ', $trans['type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($trans['description']); ?></td>
                                    <td>
                                        <strong class="<?php echo $trans['points'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $trans['points'] > 0 ? '+' : ''; ?><?php echo $trans['points']; ?>
                                        </strong>
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($trans['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
