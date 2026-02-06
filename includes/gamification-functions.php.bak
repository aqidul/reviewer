<?php
/**
 * Gamification & Rewards System Helper Functions
 * Phase 2: Gamification System
 */

if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

/**
 * Initialize user points record
 */
function initializeUserPoints($db, $user_id) {
    $stmt = $db->prepare("
        INSERT IGNORE INTO user_points (user_id, points, level, total_earned, streak_days)
        VALUES (?, 0, 'Bronze', 0, 0)
    ");
    return $stmt->execute([$user_id]);
}

/**
 * Award points to user
 */
function awardPoints($db, $user_id, $points, $type, $description, $reference_id = null, $reference_type = null) {
    try {
        $db->beginTransaction();
        
        // Ensure user points record exists
        initializeUserPoints($db, $user_id);
        
        // Insert transaction
        $stmt = $db->prepare("
            INSERT INTO point_transactions (user_id, points, type, description, reference_id, reference_type)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $points, $type, $description, $reference_id, $reference_type]);
        
        // Update user points
        $update = $db->prepare("
            UPDATE user_points 
            SET points = points + ?, total_earned = total_earned + ?
            WHERE user_id = ?
        ");
        $update->execute([$points, $points, $user_id]);
        
        // Check and update level
        updateUserLevel($db, $user_id);
        
        // Check for badge achievements
        checkBadgeAchievements($db, $user_id);
        
        $db->commit();
        
        // Send notification
        createNotification($db, $user_id, 'points_earned', 
            "You earned {$points} points! {$description}");
        
        return ['success' => true, 'points' => $points];
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Award points error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error awarding points'];
    }
}

/**
 * Get user points and level
 */
function getUserPoints($db, $user_id) {
    $stmt = $db->prepare("SELECT * FROM user_points WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        initializeUserPoints($db, $user_id);
        return getUserPoints($db, $user_id);
    }
    
    return $result;
}

/**
 * Update user level based on points
 */
function updateUserLevel($db, $user_id) {
    $points_data = getUserPoints($db, $user_id);
    $current_points = $points_data['points'];
    
    // Get appropriate level
    $stmt = $db->prepare("
        SELECT level_name 
        FROM level_settings 
        WHERE min_points <= ? AND max_points >= ?
        ORDER BY level_order DESC
        LIMIT 1
    ");
    $stmt->execute([$current_points, $current_points]);
    $new_level = $stmt->fetchColumn();
    
    if ($new_level && $new_level !== $points_data['level']) {
        // Update level
        $update = $db->prepare("UPDATE user_points SET level = ? WHERE user_id = ?");
        $update->execute([$new_level, $user_id]);
        
        // Send level-up notification
        createNotification($db, $user_id, 'level_up', 
            "Congratulations! You've reached {$new_level} level!");
        
        // Award level-up bonus points
        $bonus_points = getLevelUpBonus($new_level);
        if ($bonus_points > 0) {
            awardPoints($db, $user_id, $bonus_points, 'level_up', "Level up bonus: {$new_level}");
        }
        
        return true;
    }
    
    return false;
}

/**
 * Get level up bonus points
 */
function getLevelUpBonus($level) {
    $bonuses = [
        'Bronze' => 0,
        'Silver' => 50,
        'Gold' => 100,
        'Platinum' => 200,
        'Diamond' => 500
    ];
    return $bonuses[$level] ?? 0;
}

/**
 * Update daily login streak
 */
function updateLoginStreak($db, $user_id) {
    $points_data = getUserPoints($db, $user_id);
    $today = date('Y-m-d');
    
    if ($points_data['last_login_date'] === $today) {
        return false; // Already logged in today
    }
    
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $new_streak = 1;
    
    if ($points_data['last_login_date'] === $yesterday) {
        // Continuing streak
        $new_streak = $points_data['streak_days'] + 1;
    }
    
    // Update streak
    $stmt = $db->prepare("
        UPDATE user_points 
        SET streak_days = ?, last_login_date = ?
        WHERE user_id = ?
    ");
    $stmt->execute([$new_streak, $today, $user_id]);
    
    // Award daily login points
    $streak_bonus = min(floor($new_streak / 7) * 5, 20); // Max 20 bonus points
    $total_points = 5 + $streak_bonus;
    
    awardPoints($db, $user_id, $total_points, 'daily_login', 
        "Daily login (Streak: {$new_streak} days)");
    
    // Check for streak badges
    if ($new_streak >= 30) {
        awardBadge($db, $user_id, 'Streak Master');
    }
    
    return true;
}

/**
 * Award badge to user
 */
function awardBadge($db, $user_id, $badge_name) {
    try {
        // Get badge ID
        $stmt = $db->prepare("SELECT id FROM badges WHERE name = ? AND is_active = 1");
        $stmt->execute([$badge_name]);
        $badge_id = $stmt->fetchColumn();
        
        if (!$badge_id) {
            return false;
        }
        
        // Check if user already has badge
        $check = $db->prepare("SELECT id FROM user_badges WHERE user_id = ? AND badge_id = ?");
        $check->execute([$user_id, $badge_id]);
        if ($check->fetch()) {
            return false; // Already has badge
        }
        
        // Award badge
        $insert = $db->prepare("
            INSERT INTO user_badges (user_id, badge_id)
            VALUES (?, ?)
        ");
        $insert->execute([$user_id, $badge_id]);
        
        // Send notification
        createNotification($db, $user_id, 'badge_earned', 
            "You earned a new badge: {$badge_name}!");
        
        return true;
    } catch (Exception $e) {
        error_log("Award badge error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check and award badges based on achievements
 */
function checkBadgeAchievements($db, $user_id) {
    // Get user stats
    $stats = getUserAchievementStats($db, $user_id);
    
    // Task completion badges
    if ($stats['completed_tasks'] >= 1) {
        awardBadge($db, $user_id, 'First Task');
    }
    if ($stats['completed_tasks'] >= 10) {
        awardBadge($db, $user_id, 'Task Master 10');
    }
    if ($stats['completed_tasks'] >= 50) {
        awardBadge($db, $user_id, 'Task Master 50');
    }
    if ($stats['completed_tasks'] >= 100) {
        awardBadge($db, $user_id, 'Task Master 100');
    }
    
    // Referral badges
    if ($stats['total_referrals'] >= 1) {
        awardBadge($db, $user_id, 'First Referral');
    }
    if ($stats['total_referrals'] >= 10) {
        awardBadge($db, $user_id, 'Referral Pro');
    }
    
    // KYC badge
    if ($stats['kyc_verified']) {
        awardBadge($db, $user_id, 'Verified User');
    }
}

/**
 * Get user achievement statistics
 */
function getUserAchievementStats($db, $user_id) {
    // Completed tasks
    $tasks_stmt = $db->prepare("
        SELECT COUNT(*) FROM orders o
        JOIN tasks t ON o.task_id = t.id
        WHERE t.user_id = ? AND o.step4_status = 'approved'
    ");
    $tasks_stmt->execute([$user_id]);
    $completed_tasks = $tasks_stmt->fetchColumn();
    
    // Total referrals
    $ref_stmt = $db->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ? AND level = 1");
    $ref_stmt->execute([$user_id]);
    $total_referrals = $ref_stmt->fetchColumn();
    
    // KYC verification
    $kyc_stmt = $db->prepare("SELECT kyc_verified FROM users WHERE id = ?");
    $kyc_stmt->execute([$user_id]);
    $kyc_verified = $kyc_stmt->fetchColumn();
    
    return [
        'completed_tasks' => $completed_tasks,
        'total_referrals' => $total_referrals,
        'kyc_verified' => $kyc_verified
    ];
}

/**
 * Get user badges
 */
function getUserBadges($db, $user_id) {
    $stmt = $db->prepare("
        SELECT b.*, ub.earned_at
        FROM user_badges ub
        JOIN badges b ON ub.badge_id = b.id
        WHERE ub.user_id = ?
        ORDER BY ub.earned_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get leaderboard
 */
function getLeaderboard($db, $period = 'all_time', $limit = 100) {
    $where_clause = "";
    
    if ($period === 'daily') {
        $where_clause = "AND pt.created_at >= CURDATE()";
    } elseif ($period === 'weekly') {
        $where_clause = "AND pt.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    } elseif ($period === 'monthly') {
        $where_clause = "AND pt.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    }
    
    $query = "
        SELECT 
            u.id,
            u.username,
            up.points,
            up.level,
            up.streak_days,
            COALESCE(SUM(CASE WHEN pt.created_at >= CURDATE() THEN pt.points ELSE 0 END), 0) as points_today,
            COUNT(DISTINCT ub.badge_id) as badge_count
        FROM users u
        JOIN user_points up ON u.id = up.user_id
        LEFT JOIN point_transactions pt ON u.id = pt.user_id $where_clause
        LEFT JOIN user_badges ub ON u.id = ub.user_id
        WHERE u.user_type = 'user'
        GROUP BY u.id, u.username, up.points, up.level, up.streak_days
        ORDER BY up.points DESC
        LIMIT ?
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get user rank
 */
function getUserRank($db, $user_id) {
    $stmt = $db->prepare("
        SELECT COUNT(*) + 1 as rank
        FROM user_points up1
        JOIN user_points up2 ON up2.user_id = ?
        WHERE up1.points > up2.points
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

/**
 * Get point transaction history
 */
function getPointTransactions($db, $user_id, $limit = 50, $offset = 0) {
    $stmt = $db->prepare("
        SELECT * FROM point_transactions
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$user_id, $limit, $offset]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all level settings
 */
function getLevelSettings($db) {
    $stmt = $db->query("SELECT * FROM level_settings ORDER BY level_order ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all badges
 */
function getAllBadges($db) {
    $stmt = $db->query("SELECT * FROM badges WHERE is_active = 1 ORDER BY points_required ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Award task completion points
 */
function awardTaskCompletionPoints($db, $user_id, $task_id) {
    return awardPoints($db, $user_id, 10, 'task_completion', 
        'Task completed successfully', $task_id, 'task');
}

/**
 * Award referral points
 */
function awardReferralPoints($db, $user_id, $referee_id) {
    return awardPoints($db, $user_id, 50, 'referral', 
        'New referral', $referee_id, 'user');
}

/**
 * Award profile completion points
 */
function awardProfileCompletionPoints($db, $user_id) {
    // Check if already awarded
    $check = $db->prepare("
        SELECT id FROM point_transactions 
        WHERE user_id = ? AND type = 'profile_completion'
    ");
    $check->execute([$user_id]);
    if ($check->fetch()) {
        return false; // Already awarded
    }
    
    return awardPoints($db, $user_id, 20, 'profile_completion', 
        'Profile completed');
}

/**
 * Get gamification dashboard data
 */
function getGamificationDashboard($db, $user_id) {
    $user_points = getUserPoints($db, $user_id);
    $badges = getUserBadges($db, $user_id);
    $rank = getUserRank($db, $user_id);
    $recent_transactions = getPointTransactions($db, $user_id, 10);
    
    // Get next level info
    $next_level_stmt = $db->prepare("
        SELECT * FROM level_settings 
        WHERE level_order > (
            SELECT level_order FROM level_settings WHERE level_name = ?
        )
        ORDER BY level_order ASC
        LIMIT 1
    ");
    $next_level_stmt->execute([$user_points['level']]);
    $next_level = $next_level_stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'user_points' => $user_points,
        'badges' => $badges,
        'rank' => $rank,
        'recent_transactions' => $recent_transactions,
        'next_level' => $next_level,
        'total_badges' => count(getAllBadges($db)),
        'earned_badges' => count($badges)
    ];
}
