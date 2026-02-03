<?php
require_once '../includes/config.php';
require_once '../includes/gamification-functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../index.php');
}

$message = '';

// Get all statistics
$total_users_stmt = $db->query("SELECT COUNT(*) FROM user_points");
$total_users = $total_users_stmt->fetchColumn();

$total_points_stmt = $db->query("SELECT SUM(total_earned) FROM user_points");
$total_points = $total_points_stmt->fetchColumn();

$total_badges_stmt = $db->query("SELECT COUNT(*) FROM user_badges");
$total_badges = $total_badges_stmt->fetchColumn();

// Get level distribution
$level_dist = $db->query("
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
$level_settings = getLevelSettings($db);

// Get all badges
$all_badges = getAllBadges($db);

// Get recent point transactions
$recent_transactions = $db->query("
    SELECT pt.*, u.username
    FROM point_transactions pt
    JOIN users u ON pt.user_id = u.id
    ORDER BY pt.created_at DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Admin Sidebar -->
        <div class="col-md-2">
            <div class="list-group">
                <a href="dashboard.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="referral-settings.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-people"></i> Referral Settings
                </a>
                <a href="verify-proofs.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-file-earmark-check"></i> Verify Proofs
                </a>
                <a href="gamification-settings.php" class="list-group-item list-group-item-action active">
                    <i class="bi bi-trophy"></i> Gamification
                </a>
                <a href="support-chat.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-chat-dots"></i> Support Chat
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-10">
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
                                        $earned_count = $db->prepare("SELECT COUNT(*) FROM user_badges WHERE badge_id = ?");
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

<?php include '../includes/footer.php'; ?>
