<?php
/**
 * Referral System Helper Functions
 * Phase 2: Referral & Affiliate System
 */

if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

/**
 * Generate unique referral code for user
 */
function generateReferralCode($user_id) {
    return 'REF' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
}

/**
 * Get or create referral code for user
 */
function getUserReferralCode($db, $user_id) {
    $stmt = $db->prepare("SELECT referral_code FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $code = $stmt->fetchColumn();
    
    if (empty($code)) {
        $code = generateReferralCode($user_id);
        $update = $db->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
        $update->execute([$code, $user_id]);
    }
    
    return $code;
}

/**
 * Get user by referral code
 */
function getUserByReferralCode($db, $code) {
    $stmt = $db->prepare("SELECT id, username, email FROM users WHERE referral_code = ?");
    $stmt->execute([$code]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Create referral relationship
 */
function createReferral($db, $referrer_id, $referee_id) {
    try {
        // Check if referee already has a referrer
        $check = $db->prepare("SELECT referred_by FROM users WHERE id = ?");
        $check->execute([$referee_id]);
        $existing = $check->fetchColumn();
        
        if (!empty($existing)) {
            return ['success' => false, 'message' => 'User already referred by someone'];
        }
        
        // Update referee's referred_by
        $update = $db->prepare("UPDATE users SET referred_by = ? WHERE id = ?");
        $update->execute([$referrer_id, $referee_id]);
        
        // Create referral record
        $stmt = $db->prepare("
            INSERT INTO referrals (referrer_id, referee_id, level, status)
            VALUES (?, ?, 1, 'pending')
        ");
        $stmt->execute([$referrer_id, $referee_id]);
        
        // Create multi-level referrals
        createMultiLevelReferrals($db, $referrer_id, $referee_id);
        
        return ['success' => true, 'message' => 'Referral created successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Create multi-level referral relationships
 */
function createMultiLevelReferrals($db, $referrer_id, $referee_id) {
    $level = 2;
    $current_referrer = $referrer_id;
    
    // Get referral settings to check max levels
    $settings_stmt = $db->query("SELECT MAX(level) as max_level FROM referral_settings WHERE is_active = 1");
    $max_level = $settings_stmt->fetchColumn();
    
    while ($level <= $max_level && $current_referrer) {
        // Get next level referrer
        $stmt = $db->prepare("SELECT referred_by FROM users WHERE id = ?");
        $stmt->execute([$current_referrer]);
        $next_referrer = $stmt->fetchColumn();
        
        if ($next_referrer) {
            // Create referral record for this level
            $insert = $db->prepare("
                INSERT IGNORE INTO referrals (referrer_id, referee_id, level, status)
                VALUES (?, ?, ?, 'pending')
            ");
            $insert->execute([$next_referrer, $referee_id, $level]);
            
            $current_referrer = $next_referrer;
            $level++;
        } else {
            break;
        }
    }
}

/**
 * Activate referral when referee completes first task
 */
function activateReferral($db, $referee_id) {
    $stmt = $db->prepare("UPDATE referrals SET status = 'active' WHERE referee_id = ? AND status = 'pending'");
    return $stmt->execute([$referee_id]);
}

/**
 * Calculate and credit referral commission
 */
function creditReferralCommission($db, $referee_id, $task_id, $task_amount) {
    try {
        // Get all referrers for this referee
        $stmt = $db->prepare("
            SELECT r.referrer_id, r.level, rs.commission_percent
            FROM referrals r
            JOIN referral_settings rs ON r.level = rs.level
            WHERE r.referee_id = ? AND r.status = 'active' AND rs.is_active = 1
        ");
        $stmt->execute([$referee_id]);
        $referrers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($referrers as $ref) {
            $commission = ($task_amount * $ref['commission_percent']) / 100;
            
            // Insert earning record
            $insert = $db->prepare("
                INSERT INTO referral_earnings (user_id, from_user_id, task_id, amount, level, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $insert->execute([
                $ref['referrer_id'],
                $referee_id,
                $task_id,
                $commission,
                $ref['level']
            ]);
            
            // Credit to wallet
            $update_wallet = $db->prepare("
                UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?
            ");
            $update_wallet->execute([$commission, $ref['referrer_id']]);
            
            // Mark as credited
            $mark_credited = $db->prepare("
                UPDATE referral_earnings 
                SET status = 'credited', credited_at = NOW() 
                WHERE user_id = ? AND from_user_id = ? AND task_id = ?
            ");
            $mark_credited->execute([$ref['referrer_id'], $referee_id, $task_id]);
            
            // Send notification
            createNotification($db, $ref['referrer_id'], 'referral_commission', 
                "You earned ₹{$commission} commission from your Level {$ref['level']} referral!");
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Referral commission error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get referral statistics for user
 */
function getReferralStats($db, $user_id) {
    // Total referrals
    $total_stmt = $db->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ? AND level = 1");
    $total_stmt->execute([$user_id]);
    $total_referrals = $total_stmt->fetchColumn();
    
    // Active referrals
    $active_stmt = $db->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ? AND level = 1 AND status = 'active'");
    $active_stmt->execute([$user_id]);
    $active_referrals = $active_stmt->fetchColumn();
    
    // Total earnings
    $earnings_stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM referral_earnings WHERE user_id = ? AND status = 'credited'");
    $earnings_stmt->execute([$user_id]);
    $total_earnings = $earnings_stmt->fetchColumn();
    
    // Pending earnings
    $pending_stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM referral_earnings WHERE user_id = ? AND status = 'pending'");
    $pending_stmt->execute([$user_id]);
    $pending_earnings = $pending_stmt->fetchColumn();
    
    return [
        'total_referrals' => $total_referrals,
        'active_referrals' => $active_referrals,
        'total_earnings' => $total_earnings,
        'pending_earnings' => $pending_earnings
    ];
}

/**
 * Get referral tree for user
 */
function getReferralTree($db, $user_id, $max_levels = 3) {
    $tree = [];
    
    for ($level = 1; $level <= $max_levels; $level++) {
        $stmt = $db->prepare("
            SELECT 
                u.id,
                u.username,
                u.email,
                r.status,
                r.created_at,
                (SELECT COUNT(*) FROM orders o 
                 JOIN tasks t ON o.task_id = t.id 
                 WHERE t.user_id = u.id AND o.step4_status = 'approved') as completed_tasks
            FROM referrals r
            JOIN users u ON r.referee_id = u.id
            WHERE r.referrer_id = ? AND r.level = ?
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$user_id, $level]);
        $tree[$level] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    return $tree;
}

/**
 * Get recent referral earnings
 */
function getRecentReferralEarnings($db, $user_id, $limit = 10) {
    $stmt = $db->prepare("
        SELECT 
            re.*,
            u.username as from_username,
            t.title as task_title
        FROM referral_earnings re
        LEFT JOIN users u ON re.from_user_id = u.id
        LEFT JOIN tasks t ON re.task_id = t.id
        WHERE re.user_id = ?
        ORDER BY re.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get referral commission settings
 */
function getReferralSettings($db) {
    $stmt = $db->query("
        SELECT * FROM referral_settings 
        WHERE is_active = 1 
        ORDER BY level ASC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Update referral commission settings (Admin)
 */
function updateReferralSettings($db, $level, $commission_percent, $is_active = 1) {
    $stmt = $db->prepare("
        INSERT INTO referral_settings (level, commission_percent, is_active)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            commission_percent = VALUES(commission_percent),
            is_active = VALUES(is_active)
    ");
    return $stmt->execute([$level, $commission_percent, $is_active]);
}

/**
 * Generate shareable referral link
 */
function generateReferralLink($referral_code) {
    $base_url = rtrim(APP_URL, '/');
    return $base_url . '/user/signup.php?ref=' . urlencode($referral_code);
}

/**
 * Get WhatsApp share link for referral
 */
function getWhatsAppShareLink($referral_code) {
    $link = generateReferralLink($referral_code);
    $message = "Join ReviewFlow and earn money by completing tasks! Use my referral code: {$referral_code}\n\n{$link}";
    return 'https://api.whatsapp.com/send?text=' . urlencode($message);
}

/**
 * Get Facebook share link for referral
 */
function getFacebookShareLink($referral_code) {
    $link = generateReferralLink($referral_code);
    return 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($link);
}

/**
 * Get Twitter share link for referral
 */
function getTwitterShareLink($referral_code) {
    $link = generateReferralLink($referral_code);
    $message = "Join ReviewFlow and earn money! Use my code: {$referral_code}";
    return 'https://twitter.com/intent/tweet?text=' . urlencode($message) . '&url=' . urlencode($link);
}
