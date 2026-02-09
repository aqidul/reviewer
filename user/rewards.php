<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/gamification-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Update login streak
try {
    updateLoginStreak($pdo, $user_id);
    $dashboard_data = getGamificationDashboard($pdo, $user_id);
    $streak_calendar = getStreakCalendar($pdo, $user_id);
    $streak_milestones = getStreakMilestones($pdo, $user_id);
    $next_milestone = getNextStreakMilestone($dashboard_data['user_points']['streak_days']);
} catch (PDOException $e) {
    $dashboard_data = ['user_points' => ['level' => 'Bronze', 'points' => 0, 'total_earned' => 0, 'streak_days' => 0], 'next_level' => null, 'earned_badges' => 0, 'total_badges' => 0, 'rank' => 0, 'badges' => [], 'recent_transactions' => []];
    $streak_calendar = [];
    $streak_milestones = [];
    $next_milestone = null;
}

// Get level benefits
$level_benefits = [
    'Bronze' => ['tasks' => 5, 'commission' => '1x', 'withdrawal' => '₹100'],
    'Silver' => ['tasks' => 10, 'commission' => '1.2x', 'withdrawal' => '₹100'],
    'Gold' => ['tasks' => 20, 'commission' => '1.5x', 'withdrawal' => '₹50'],
    'Platinum' => ['tasks' => 50, 'commission' => '1.8x', 'withdrawal' => '₹50'],
    'Diamond' => ['tasks' => 'Unlimited', 'commission' => '2x', 'withdrawal' => '₹25']
];

// Set current page for sidebar
$current_page = 'rewards';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rewards & Gamification - User Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
    /* Base Styles */
    * { box-sizing: border-box; }
    
    body {
        background: #f5f7fa;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    .admin-layout {
        margin-left: 260px;
        padding: 30px 20px;
        min-height: calc(100vh - 60px);
    }
    
    /* Enhanced Card Styles */
    .card {
        border-radius: 16px;
        border: none;
        box-shadow: 0 8px 32px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        overflow: hidden;
    }
    
    .card:hover {
        box-shadow: 0 12px 40px rgba(0,0,0,0.12);
        transform: translateY(-2px);
    }
    
    /* Level Card with animated gradient */
    .level-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 20px;
        padding: 35px;
        margin-bottom: 30px;
        position: relative;
        overflow: hidden;
    }
    
    .level-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 100%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        animation: pulse 3s ease-in-out infinite;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); opacity: 0.5; }
        50% { transform: scale(1.1); opacity: 0.8; }
    }
    
    .level-icon {
        font-size: 4rem;
        margin-bottom: 10px;
        text-shadow: 0 4px 15px rgba(0,0,0,0.3);
        animation: bounce 2s ease-in-out infinite;
    }
    
    @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
    
    /* Circular Progress Ring */
    .progress-ring {
        width: 200px;
        height: 200px;
        position: relative;
        margin: 20px auto;
    }
    
    .progress-ring-circle {
        transform: rotate(-90deg);
        transform-origin: 50% 50%;
    }
    
    .progress-ring-bg {
        fill: none;
        stroke: rgba(255,255,255,0.2);
        stroke-width: 12;
    }
    
    .progress-ring-progress {
        fill: none;
        stroke: #4ade80;
        stroke-width: 12;
        stroke-linecap: round;
        transition: stroke-dashoffset 1s ease;
    }
    
    .progress-ring-text {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
    }
    
    .progress-percentage {
        font-size: 2.5rem;
        font-weight: 800;
        display: block;
    }
    
    .progress-label {
        font-size: 0.9rem;
        opacity: 0.9;
    }
    
    /* Streak Calendar */
    .streak-calendar {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 8px;
        margin: 20px 0;
    }
    
    .calendar-day {
        aspect-ratio: 1;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.3s;
        position: relative;
    }
    
    .calendar-day.inactive {
        background: #f0f0f0;
        color: #999;
    }
    
    .calendar-day.active {
        background: linear-gradient(135deg, #27ae60, #2ecc71);
        color: white;
        box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
    }
    
    .calendar-day.today {
        background: linear-gradient(135deg, #f39c12, #e67e22);
        color: white;
        animation: todayPulse 2s ease-in-out infinite;
        box-shadow: 0 0 0 4px rgba(243, 156, 18, 0.3);
    }
    
    @keyframes todayPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    .calendar-day.active::after {
        content: '✓';
        position: absolute;
        font-size: 1.2rem;
        color: white;
    }
    
    /* Streak Fire Animation */
    .streak-fire {
        font-size: 3rem;
        display: inline-block;
        animation: fireFlicker 1.5s ease-in-out infinite;
    }
    
    @keyframes fireFlicker {
        0%, 100% { transform: scale(1) rotate(-5deg); }
        50% { transform: scale(1.1) rotate(5deg); }
    }
    
    /* Stats Cards */
    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 25px;
        text-align: center;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        transition: all 0.3s;
        border: 2px solid transparent;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        border-color: #667eea;
    }
    
    .stat-icon {
        font-size: 2.5rem;
        margin-bottom: 10px;
        display: inline-block;
        animation: float 3s ease-in-out infinite;
    }
    
    @keyframes float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 800;
        color: #333;
        margin: 10px 0 5px;
    }
    
    .stat-label {
        color: #888;
        font-size: 0.95rem;
    }
    
    /* Badge Gallery */
    .badge-gallery {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .badge-item {
        background: white;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s;
        border: 2px solid #f0f0f0;
    }
    
    .badge-item.earned {
        border-color: #27ae60;
        box-shadow: 0 4px 20px rgba(39, 174, 96, 0.2);
    }
    
    .badge-item:not(.earned) {
        opacity: 0.5;
        filter: grayscale(1);
    }
    
    .badge-item:hover {
        transform: scale(1.05);
    }
    
    .badge-icon {
        font-size: 3rem;
        margin-bottom: 10px;
    }
    
    .badge-earned .badge-icon {
        animation: badgeShine 2s ease-in-out infinite;
    }
    
    @keyframes badgeShine {
        0%, 100% { filter: brightness(1); }
        50% { filter: brightness(1.3); }
    }
    
    .badge-name {
        font-weight: 600;
        font-size: 0.9rem;
        color: #333;
        margin-bottom: 5px;
    }
    
    .badge-desc {
        font-size: 0.8rem;
        color: #666;
        margin-bottom: 10px;
    }
    
    /* Level Benefits Panel */
    .benefits-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 20px;
    }
    
    .benefit-item {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        padding: 20px;
        border-radius: 12px;
        text-align: center;
        border-left: 4px solid #667eea;
    }
    
    .benefit-icon {
        font-size: 2rem;
        margin-bottom: 10px;
        color: #667eea;
    }
    
    .benefit-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #333;
        margin-bottom: 5px;
    }
    
    .benefit-label {
        font-size: 0.85rem;
        color: #666;
    }
    
    /* Milestone Progress */
    .milestone-progress {
        background: linear-gradient(135deg, #fff5e6, #ffe8cc);
        border-radius: 16px;
        padding: 25px;
        margin: 20px 0;
        border-left: 4px solid #f39c12;
    }
    
    .milestone-progress h4 {
        color: #333;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .progress-bar-custom {
        height: 30px;
        border-radius: 15px;
        background: rgba(255,255,255,0.5);
        overflow: hidden;
        margin: 15px 0;
    }
    
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #f39c12, #e67e22);
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        transition: width 1s ease;
        box-shadow: 0 2px 10px rgba(243, 156, 18, 0.3);
    }
    
    /* Recent Activity */
    .activity-item {
        padding: 15px;
        border-left: 3px solid #e0e0e0;
        margin-bottom: 10px;
        background: white;
        border-radius: 0 8px 8px 0;
        transition: all 0.3s;
    }
    
    .activity-item:hover {
        border-left-color: #667eea;
        background: #f8f9fa;
        transform: translateX(5px);
    }
    
    .activity-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        font-size: 1.2rem;
    }
    
    /* Motivational Messages */
    .motivation-box {
        background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
        border-radius: 16px;
        padding: 20px 25px;
        margin: 20px 0;
        border-left: 4px solid #27ae60;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .motivation-icon {
        font-size: 2.5rem;
    }
    
    .motivation-text {
        flex: 1;
    }
    
    .motivation-title {
        font-weight: 700;
        color: #1e8449;
        margin-bottom: 5px;
    }
    
    .motivation-message {
        color: #27ae60;
        margin: 0;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .admin-layout {
            margin-left: 0;
            padding: 20px 10px;
        }
        .streak-calendar {
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
        }
        .calendar-day {
            font-size: 0.7rem;
        }
        .badge-gallery {
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        }
        .stat-card {
            padding: 15px;
        }
        .stat-icon {
            font-size: 2rem;
        }
        .stat-value {
            font-size: 1.5rem;
        }
    }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="admin-layout">
    <div class="container-fluid">
        <h2 class="mb-4"><i class="bi bi-trophy-fill"></i> Rewards & Gamification</h2>

        <!-- User Level Card with Circular Progress -->
        <div class="level-card">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="level-icon">
                        <?php
                        $level_icons = [
                            'Bronze' => '🥉',
                            'Silver' => '🥈',
                            'Gold' => '🥇',
                            'Platinum' => '💎',
                            'Diamond' => '👑'
                        ];
                        echo $level_icons[$dashboard_data['user_points']['level']] ?? '⭐';
                        ?>
                    </div>
                    <h3 class="mb-2" style="font-size: 2rem; font-weight: 800;">
                        <?php echo htmlspecialchars($dashboard_data['user_points']['level']); ?> Level
                    </h3>
                    <h1 class="display-4 mb-2" style="font-weight: 900;">
                        <?php echo number_format($dashboard_data['user_points']['points']); ?> Points
                    </h1>
                    <p class="mb-0" style="font-size: 1.1rem; opacity: 0.9;">
                        Total Earned: <?php echo number_format($dashboard_data['user_points']['total_earned']); ?> points
                    </p>
                </div>
                <div class="col-md-4">
                    <?php if ($dashboard_data['next_level']): ?>
                    <div class="progress-ring">
                        <?php 
                        $current = $dashboard_data['user_points']['points'];
                        $next_required = $dashboard_data['next_level']['min_points'];
                        $progress = min(($current / $next_required) * 100, 100);
                        $radius = 85;
                        $circumference = 2 * M_PI * $radius;
                        $offset = $circumference - ($progress / 100) * $circumference;
                        ?>
                        <svg class="progress-ring-circle" width="200" height="200">
                            <circle class="progress-ring-bg" cx="100" cy="100" r="<?php echo $radius; ?>"></circle>
                            <circle class="progress-ring-progress" cx="100" cy="100" r="<?php echo $radius; ?>"
                                    style="stroke-dasharray: <?php echo $circumference; ?>; stroke-dashoffset: <?php echo $offset; ?>;"></circle>
                        </svg>
                        <div class="progress-ring-text">
                            <span class="progress-percentage"><?php echo number_format($progress, 1); ?>%</span>
                            <span class="progress-label">to <?php echo $dashboard_data['next_level']['level_name']; ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Streak Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h4 class="mb-3">
                            <span class="streak-fire">🔥</span> 
                            Daily Login Streak: <?php echo $dashboard_data['user_points']['streak_days']; ?> Days
                        </h4>
                        
                        <?php
                        // Motivational messages based on streak
                        $streak_messages = [
                            0 => ['icon' => '👋', 'title' => 'Start Your Journey!', 'message' => 'Login daily to build your streak and earn bonus points!'],
                            1 => ['icon' => '🌟', 'title' => 'Great Start!', 'message' => 'You\'ve started your streak! Come back tomorrow to keep it going!'],
                            3 => ['icon' => '🎯', 'title' => 'On Fire!', 'message' => 'You\'ve hit a 3-day streak! Keep the momentum going!'],
                            7 => ['icon' => '🚀', 'title' => 'Amazing!', 'message' => 'One week streak! You\'re unstoppable!'],
                            14 => ['icon' => '💪', 'title' => 'Incredible!', 'message' => 'Two weeks of consistency! You\'re a champion!'],
                            30 => ['icon' => '👑', 'title' => 'Legendary!', 'message' => 'One month streak! You\'re truly dedicated!'],
                        ];
                        
                        $current_streak = $dashboard_data['user_points']['streak_days'];
                        $message = $streak_messages[0];
                        
                        foreach (array_reverse($streak_messages, true) as $days => $msg) {
                            if ($current_streak >= $days) {
                                $message = $msg;
                                break;
                            }
                        }
                        ?>
                        
                        <div class="motivation-box">
                            <div class="motivation-icon"><?php echo $message['icon']; ?></div>
                            <div class="motivation-text">
                                <div class="motivation-title"><?php echo $message['title']; ?></div>
                                <p class="motivation-message"><?php echo $message['message']; ?></p>
                            </div>
                        </div>
                        
                        <!-- Streak Calendar -->
                        <h5 class="mt-4 mb-3">📅 Last 30 Days Activity</h5>
                        <div class="streak-calendar">
                            <?php foreach ($streak_calendar as $day): ?>
                            <div class="calendar-day <?php echo $day['active'] ? 'active' : 'inactive'; ?> <?php echo $day['is_today'] ? 'today' : ''; ?>"
                                 title="<?php echo $day['date']; ?>">
                                <span><?php echo $day['day_num']; ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($next_milestone): ?>
                        <div class="milestone-progress">
                            <h4>🎯 Next Milestone: <?php echo $next_milestone['days']; ?> Days</h4>
                            <p>Only <?php echo $next_milestone['days_remaining']; ?> more day(s) to earn <?php echo $next_milestone['points_reward']; ?> bonus points!</p>
                            <?php 
                            $milestone_progress = (($current_streak / $next_milestone['days']) * 100);
                            ?>
                            <div class="progress-bar-custom">
                                <div class="progress-fill" style="width: <?php echo $milestone_progress; ?>%">
                                    <?php echo number_format($milestone_progress, 1); ?>%
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Achieved Milestones -->
                        <?php if (!empty($streak_milestones)): ?>
                        <h5 class="mt-4 mb-3">🏆 Streak Milestones Achieved</h5>
                        <div class="row">
                            <?php foreach ($streak_milestones as $milestone): ?>
                            <div class="col-md-3 mb-3">
                                <div class="stat-card">
                                    <div class="stat-icon">🏅</div>
                                    <div class="stat-value"><?php echo $milestone['milestone_days']; ?></div>
                                    <div class="stat-label">Day Streak</div>
                                    <small class="text-success">+<?php echo $milestone['points_awarded']; ?> points</small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="row mb-4">
            <div class="col-md-3 col-6 mb-3">
                <div class="stat-card">
                    <div class="stat-icon">🔥</div>
                    <div class="stat-value"><?php echo $dashboard_data['user_points']['streak_days']; ?></div>
                    <div class="stat-label">Day Streak</div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="stat-card">
                    <div class="stat-icon">🏅</div>
                    <div class="stat-value"><?php echo $dashboard_data['earned_badges']; ?>/<?php echo $dashboard_data['total_badges']; ?></div>
                    <div class="stat-label">Badges Earned</div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-value">#<?php echo $dashboard_data['rank']; ?></div>
                    <div class="stat-label">Your Rank</div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="stat-card">
                    <div class="stat-icon">⚡</div>
                    <div class="stat-value"><?php echo number_format($dashboard_data['user_points']['total_earned']); ?></div>
                    <div class="stat-label">Total Points</div>
                </div>
            </div>
        </div>

        <!-- Level Benefits -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3"><i class="bi bi-gift-fill"></i> Your Level Benefits (<?php echo $dashboard_data['user_points']['level']; ?>)</h5>
                <?php $benefits = $level_benefits[$dashboard_data['user_points']['level']] ?? $level_benefits['Bronze']; ?>
                <div class="benefits-grid">
                    <div class="benefit-item">
                        <div class="benefit-icon">📋</div>
                        <div class="benefit-value"><?php echo $benefits['tasks']; ?></div>
                        <div class="benefit-label">Daily Tasks</div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon">💰</div>
                        <div class="benefit-value"><?php echo $benefits['commission']; ?></div>
                        <div class="benefit-label">Commission Multiplier</div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon">💸</div>
                        <div class="benefit-value"><?php echo $benefits['withdrawal']; ?></div>
                        <div class="benefit-label">Min Withdrawal</div>
                    </div>
                </div>
                
                <?php if ($dashboard_data['next_level']): ?>
                <div class="milestone-progress mt-4">
                    <h5>🎁 Next Level Preview: <?php echo $dashboard_data['next_level']['level_name']; ?></h5>
                    <?php $next_benefits = $level_benefits[$dashboard_data['next_level']['level_name']] ?? []; ?>
                    <?php if (!empty($next_benefits)): ?>
                    <p class="mb-2">Unlock these benefits:</p>
                    <ul style="margin: 0; padding-left: 20px; color: #666;">
                        <li>📋 Daily Tasks: <strong><?php echo $next_benefits['tasks']; ?></strong></li>
                        <li>💰 Commission: <strong><?php echo $next_benefits['commission']; ?></strong></li>
                        <li>💸 Min Withdrawal: <strong><?php echo $next_benefits['withdrawal']; ?></strong></li>
                    </ul>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Badge Gallery -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3"><i class="bi bi-award-fill"></i> Badge Collection</h5>
                <div class="badge-gallery">
                    <?php 
                    try {
                        $all_badges = getAllBadges($pdo);
                    } catch (PDOException $e) {
                        $all_badges = [];
                    }
                    $earned_badge_ids = array_column($dashboard_data['badges'], 'badge_id');
                    foreach ($all_badges as $badge): 
                        $is_earned = in_array($badge['id'], $earned_badge_ids);
                    ?>
                    <div class="badge-item <?php echo $is_earned ? 'earned' : ''; ?>">
                        <div class="badge-icon <?php echo $is_earned ? 'badge-earned' : ''; ?>">
                            <?php echo htmlspecialchars($badge['icon'] ?? '🏆'); ?>
                        </div>
                        <div class="badge-name"><?php echo htmlspecialchars($badge['name']); ?></div>
                        <div class="badge-desc"><?php echo htmlspecialchars($badge['description']); ?></div>
                        <?php if ($is_earned): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Earned</span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><?php echo $badge['points_required']; ?> pts</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <div class="card-body">
                <h5 class="mb-3"><i class="bi bi-clock-history"></i> Recent Point Activity</h5>
                <?php if (count($dashboard_data['recent_transactions']) > 0): ?>
                    <?php foreach ($dashboard_data['recent_transactions'] as $transaction): ?>
                    <div class="activity-item">
                        <div style="display: flex; align-items: center;">
                            <div class="activity-icon">
                                <?php
                                $type_icons = [
                                    'daily_login' => '🚪',
                                    'task_completion' => '✅',
                                    'referral' => '🤝',
                                    'streak_milestone' => '🔥',
                                    'level_up' => '⬆️',
                                    'daily_spin' => '🎰'
                                ];
                                echo $type_icons[$transaction['type']] ?? '⭐';
                                ?>
                            </div>
                            <div style="flex: 1;">
                                <strong><?php echo ucfirst(str_replace('_', ' ', $transaction['type'])); ?></strong>
                                <p style="margin: 0; color: #666; font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($transaction['description']); ?>
                                </p>
                                <small style="color: #999;">
                                    <?php echo date('M d, Y H:i', strtotime($transaction['created_at'])); ?>
                                </small>
                            </div>
                            <div>
                                <strong class="<?php echo $transaction['points'] > 0 ? 'text-success' : 'text-danger'; ?>" 
                                        style="font-size: 1.3rem;">
                                    <?php echo $transaction['points'] > 0 ? '+' : ''; ?><?php echo $transaction['points']; ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <div style="font-size: 3rem; opacity: 0.3;">⭐</div>
                        <p class="text-muted">No activity yet. Start earning points!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Animate numbers on page load
document.addEventListener('DOMContentLoaded', function() {
    const statValues = document.querySelectorAll('.stat-value');
    statValues.forEach(element => {
        const target = parseInt(element.textContent.replace(/,/g, ''));
        if (!isNaN(target)) {
            let current = 0;
            const increment = target / 30;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    element.textContent = target.toLocaleString();
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(current).toLocaleString();
                }
            }, 30);
        }
    });
});
</script>
</body>
</html>
