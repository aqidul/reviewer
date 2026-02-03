<?php
require_once '../includes/config.php';
require_once '../includes/gamification-functions.php';

if (!isLoggedIn() || isAdmin()) {
    redirect('../index.php');
}

$user_id = $_SESSION['user_id'];

// Get period filter
$period = isset($_GET['period']) ? $_GET['period'] : 'all_time';
$valid_periods = ['daily', 'weekly', 'monthly', 'all_time'];
if (!in_array($period, $valid_periods)) {
    $period = 'all_time';
}

// Get leaderboard
$leaderboard = getLeaderboard($db, $period, 100);
$user_rank = getUserRank($db, $user_id);

// Set current page for sidebar
$current_page = 'leaderboard';

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
            <h2 class="mb-4"><i class="bi bi-bar-chart-fill"></i> Leaderboard</h2>

            <!-- User Rank Card -->
            <div class="card mb-4" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3>Your Current Rank</h3>
                            <h1 class="display-3 mb-0">#<?php echo $user_rank; ?></h1>
                            <p class="mb-0">Keep completing tasks to climb the leaderboard!</p>
                        </div>
                        <div class="col-md-4 text-center">
                            <i class="bi bi-trophy-fill" style="font-size: 6rem; opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Period Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="btn-group w-100" role="group">
                        <a href="?period=daily" class="btn btn-<?php echo $period == 'daily' ? 'primary' : 'outline-primary'; ?>">
                            <i class="bi bi-calendar-day"></i> Daily
                        </a>
                        <a href="?period=weekly" class="btn btn-<?php echo $period == 'weekly' ? 'primary' : 'outline-primary'; ?>">
                            <i class="bi bi-calendar-week"></i> Weekly
                        </a>
                        <a href="?period=monthly" class="btn btn-<?php echo $period == 'monthly' ? 'primary' : 'outline-primary'; ?>">
                            <i class="bi bi-calendar-month"></i> Monthly
                        </a>
                        <a href="?period=all_time" class="btn btn-<?php echo $period == 'all_time' ? 'primary' : 'outline-primary'; ?>">
                            <i class="bi bi-clock-history"></i> All Time
                        </a>
                    </div>
                </div>
            </div>

            <!-- Top 3 Podium -->
            <?php if (count($leaderboard) >= 3): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="text-center mb-4">Top Performers</h5>
                    <div class="row align-items-end text-center">
                        <!-- 2nd Place -->
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <div style="font-size: 4rem; color: #c0c0c0;">
                                        <i class="bi bi-trophy-fill"></i>
                                    </div>
                                    <h4 class="mb-0">#2</h4>
                                    <h5><?php echo htmlspecialchars($leaderboard[1]['username']); ?></h5>
                                    <p class="mb-0">
                                        <strong><?php echo number_format($leaderboard[1]['points']); ?></strong> points
                                    </p>
                                    <span class="badge bg-secondary"><?php echo $leaderboard[1]['level']; ?></span>
                                    <div class="mt-2">
                                        <i class="bi bi-award text-warning"></i> <?php echo $leaderboard[1]['badge_count']; ?> badges
                                        <i class="bi bi-fire text-danger ms-2"></i> <?php echo $leaderboard[1]['streak_days']; ?> days
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 1st Place -->
                        <div class="col-md-4">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <div style="font-size: 5rem;">
                                        <i class="bi bi-trophy-fill"></i>
                                    </div>
                                    <h4 class="mb-0">#1</h4>
                                    <h4><?php echo htmlspecialchars($leaderboard[0]['username']); ?></h4>
                                    <h5 class="mb-0">
                                        <strong><?php echo number_format($leaderboard[0]['points']); ?></strong> points
                                    </h5>
                                    <span class="badge bg-dark"><?php echo $leaderboard[0]['level']; ?></span>
                                    <div class="mt-2">
                                        <i class="bi bi-award"></i> <?php echo $leaderboard[0]['badge_count']; ?> badges
                                        <i class="bi bi-fire ms-2"></i> <?php echo $leaderboard[0]['streak_days']; ?> days
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3rd Place -->
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <div style="font-size: 4rem; color: #cd7f32;">
                                        <i class="bi bi-trophy-fill"></i>
                                    </div>
                                    <h4 class="mb-0">#3</h4>
                                    <h5><?php echo htmlspecialchars($leaderboard[2]['username']); ?></h5>
                                    <p class="mb-0">
                                        <strong><?php echo number_format($leaderboard[2]['points']); ?></strong> points
                                    </p>
                                    <span class="badge bg-secondary"><?php echo $leaderboard[2]['level']; ?></span>
                                    <div class="mt-2">
                                        <i class="bi bi-award text-warning"></i> <?php echo $leaderboard[2]['badge_count']; ?> badges
                                        <i class="bi bi-fire text-danger ms-2"></i> <?php echo $leaderboard[2]['streak_days']; ?> days
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Full Leaderboard -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-list-ol"></i> Complete Leaderboard</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Rank</th>
                                    <th>User</th>
                                    <th>Level</th>
                                    <th>Points</th>
                                    <th>Badges</th>
                                    <th>Streak</th>
                                    <?php if ($period == 'daily'): ?>
                                    <th>Today's Points</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rank = 1;
                                foreach ($leaderboard as $user): 
                                    $is_current_user = ($user['id'] == $user_id);
                                    $row_class = $is_current_user ? 'table-primary' : '';
                                    
                                    // Medal icons for top 3
                                    $medal = '';
                                    if ($rank == 1) $medal = '<i class="bi bi-trophy-fill text-warning"></i>';
                                    elseif ($rank == 2) $medal = '<i class="bi bi-trophy-fill" style="color: #c0c0c0;"></i>';
                                    elseif ($rank == 3) $medal = '<i class="bi bi-trophy-fill" style="color: #cd7f32;"></i>';
                                ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td><strong><?php echo $medal; ?> #<?php echo $rank; ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($user['username']); ?>
                                        <?php if ($is_current_user): ?>
                                            <span class="badge bg-primary">You</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge" style="background-color: 
                                            <?php 
                                            $colors = [
                                                'Bronze' => '#CD7F32',
                                                'Silver' => '#C0C0C0',
                                                'Gold' => '#FFD700',
                                                'Platinum' => '#E5E4E2',
                                                'Diamond' => '#B9F2FF'
                                            ];
                                            echo $colors[$user['level']] ?? '#6c757d';
                                            ?>">
                                            <?php echo $user['level']; ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo number_format($user['points']); ?></strong></td>
                                    <td>
                                        <i class="bi bi-award text-warning"></i> 
                                        <?php echo $user['badge_count']; ?>
                                    </td>
                                    <td>
                                        <i class="bi bi-fire text-danger"></i> 
                                        <?php echo $user['streak_days']; ?> days
                                    </td>
                                    <?php if ($period == 'daily'): ?>
                                    <td>
                                        <span class="badge bg-success">
                                            +<?php echo number_format($user['points_today']); ?>
                                        </span>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php 
                                $rank++;
                                endforeach; 
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (count($leaderboard) == 0): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-people" style="font-size: 4rem; color: #ccc;"></i>
                        <h4 class="mt-3">No Rankings Yet</h4>
                        <p class="text-muted">Be the first to appear on the leaderboard!</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
