<?php
require_once '../includes/config.php';
require_once '../includes/gamification-functions.php';

if (!isLoggedIn() || isAdmin()) {
    redirect('../index.php');
}

$user_id = $_SESSION['user_id'];

// Update login streak
updateLoginStreak($db, $user_id);

// Get dashboard data
$dashboard_data = getGamificationDashboard($db, $user_id);

// Set current page for sidebar
$current_page = 'rewards';

include '../includes/header.php';
?>

<style>
    /* Sidebar Styles */
    .sidebar {
        width: 260px;
        position: fixed;
        left: 0;
        top: 60px;
        height: calc(100vh - 60px);
        background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%);
        box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        overflow-y: auto;
        transition: all 0.3s ease;
        z-index: 999;
    }
    .sidebar-header {
        padding: 20px;
        background: rgba(255,255,255,0.05);
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .sidebar-header h2 {
        color: #fff;
        font-size: 18px;
        margin: 0;
    }
    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .sidebar-menu li {
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .sidebar-menu li a {
        display: block;
        padding: 15px 20px;
        color: rgba(255,255,255,0.8);
        text-decoration: none;
        transition: all 0.3s;
        font-size: 14px;
    }
    .sidebar-menu li a:hover {
        background: rgba(255,255,255,0.1);
        color: #fff;
        padding-left: 25px;
    }
    .sidebar-menu li a.active {
        background: linear-gradient(90deg, rgba(66,153,225,0.2) 0%, transparent 100%);
        color: #4299e1;
        border-left: 3px solid #4299e1;
    }
    .sidebar-menu li a.logout {
        color: #fc8181;
    }
    .sidebar-divider {
        height: 1px;
        background: rgba(255,255,255,0.1);
        margin: 10px 0;
    }
    .menu-section-label {
        padding: 15px 20px 5px;
        color: rgba(255,255,255,0.5);
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .badge {
        background: #e53e3e;
        color: white;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 11px;
        margin-left: 8px;
    }
    .admin-layout {
        margin-left: 260px;
        padding: 20px;
        min-height: calc(100vh - 60px);
    }
    @media (max-width: 768px) {
        .sidebar {
            left: -260px;
        }
        .sidebar.active {
            left: 0;
        }
        .admin-layout {
            margin-left: 0;
        }
    }
</style>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="admin-layout">
    <div class="container-fluid mt-4">
            <h2 class="mb-4"><i class="bi bi-trophy-fill"></i> Rewards & Gamification</h2>

            <!-- User Level Card -->
            <div class="card mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="mb-3">
                                <i class="bi bi-star-fill"></i> 
                                <?php echo htmlspecialchars($dashboard_data['user_points']['level']); ?> Level
                            </h3>
                            <h1 class="display-4 mb-0"><?php echo number_format($dashboard_data['user_points']['points']); ?> Points</h1>
                            <p class="mb-3">Total Earned: <?php echo number_format($dashboard_data['user_points']['total_earned']); ?> points</p>
                            
                            <?php if ($dashboard_data['next_level']): ?>
                            <div class="mb-2">
                                <strong>Next Level: <?php echo $dashboard_data['next_level']['level_name']; ?></strong>
                                <div class="progress mt-2" style="height: 25px; background-color: rgba(255,255,255,0.3);">
                                    <?php 
                                    $current = $dashboard_data['user_points']['points'];
                                    $next_required = $dashboard_data['next_level']['min_points'];
                                    $progress = min(($current / $next_required) * 100, 100);
                                    ?>
                                    <div class="progress-bar" role="progressbar" style="width: <?php echo $progress; ?>%; background-color: #4ade80;">
                                        <?php echo number_format($progress, 1); ?>%
                                    </div>
                                </div>
                                <small><?php echo ($next_required - $current); ?> points to next level</small>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-center">
                            <div style="font-size: 8rem; opacity: 0.3;">
                                <i class="bi bi-trophy-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-calendar-check" style="font-size: 2rem; color: #f59e0b;"></i>
                            <h3 class="mt-2"><?php echo $dashboard_data['user_points']['streak_days']; ?></h3>
                            <p class="mb-0 text-muted">Day Streak</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-award" style="font-size: 2rem; color: #8b5cf6;"></i>
                            <h3 class="mt-2"><?php echo $dashboard_data['earned_badges']; ?>/<?php echo $dashboard_data['total_badges']; ?></h3>
                            <p class="mb-0 text-muted">Badges Earned</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-graph-up" style="font-size: 2rem; color: #10b981;"></i>
                            <h3 class="mt-2">#<?php echo $dashboard_data['rank']; ?></h3>
                            <p class="mb-0 text-muted">Your Rank</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-lightning-charge" style="font-size: 2rem; color: #ef4444;"></i>
                            <h3 class="mt-2"><?php echo number_format($dashboard_data['user_points']['total_earned']); ?></h3>
                            <p class="mb-0 text-muted">Total Points</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Earned Badges -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="bi bi-award-fill"></i> Your Badges</h5>
                    <span class="badge bg-primary"><?php echo count($dashboard_data['badges']); ?> Earned</span>
                </div>
                <div class="card-body">
                    <?php if (count($dashboard_data['badges']) > 0): ?>
                    <div class="row">
                        <?php foreach ($dashboard_data['badges'] as $badge): ?>
                        <div class="col-md-3 mb-3">
                            <div class="card text-center h-100">
                                <div class="card-body">
                                    <i class="<?php echo htmlspecialchars($badge['icon']); ?>" 
                                       style="font-size: 3rem; color: #fbbf24;"></i>
                                    <h6 class="mt-2"><?php echo htmlspecialchars($badge['name']); ?></h6>
                                    <p class="text-muted small mb-2"><?php echo htmlspecialchars($badge['description']); ?></p>
                                    <small class="text-success">
                                        <i class="bi bi-check-circle"></i> 
                                        Earned <?php echo date('M d, Y', strtotime($badge['earned_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-award" style="font-size: 4rem; color: #ccc;"></i>
                        <h5 class="mt-3">No Badges Earned Yet</h5>
                        <p class="text-muted">Complete tasks and achievements to earn badges!</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- All Available Badges -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-collection"></i> All Available Badges</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php 
                        $all_badges = getAllBadges($db);
                        $earned_badge_ids = array_column($dashboard_data['badges'], 'badge_id');
                        foreach ($all_badges as $badge): 
                            $is_earned = in_array($badge['id'], $earned_badge_ids);
                        ?>
                        <div class="col-md-3 mb-3">
                            <div class="card text-center h-100 <?php echo $is_earned ? 'border-success' : 'opacity-50'; ?>">
                                <div class="card-body">
                                    <i class="<?php echo htmlspecialchars($badge['icon']); ?>" 
                                       style="font-size: 3rem; color: <?php echo $is_earned ? '#fbbf24' : '#d1d5db'; ?>;"></i>
                                    <h6 class="mt-2"><?php echo htmlspecialchars($badge['name']); ?></h6>
                                    <p class="text-muted small"><?php echo htmlspecialchars($badge['description']); ?></p>
                                    <?php if ($is_earned): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle"></i> Earned</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?php echo $badge['points_required']; ?> points required</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-clock-history"></i> Recent Point Activity</h5>
                </div>
                <div class="card-body">
                    <?php if (count($dashboard_data['recent_transactions']) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Activity</th>
                                    <th>Description</th>
                                    <th>Points</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dashboard_data['recent_transactions'] as $transaction): ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($transaction['created_at'])); ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo ucfirst(str_replace('_', ' ', $transaction['type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                    <td>
                                        <strong class="<?php echo $transaction['points'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $transaction['points'] > 0 ? '+' : ''; ?><?php echo $transaction['points']; ?>
                                        </strong>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <p class="text-muted">No activity yet. Start earning points!</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
