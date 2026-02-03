<?php
// This file should be included after setting $current_page variable
// Make sure to have fetched badge counts before including this

// Get badge counts if not already set
if (!isset($pending_tasks_count)) {
    try {
        $user_id = $_SESSION['user_id'] ?? 0;
        $stmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND task_status = 'pending'");
        $stmt->execute([$user_id]);
        $pending_tasks_count = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $pending_tasks_count = 0;
    }
}

if (!isset($unread_messages)) {
    try {
        $user_id = $_SESSION['user_id'] ?? 0;
        $stmt = $db->prepare("SELECT COUNT(*) FROM chat_messages WHERE user_id = ? AND is_read = 0 AND sender = 'admin'");
        $stmt->execute([$user_id]);
        $unread_messages = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $unread_messages = 0;
    }
}

// Set current page if not set
if (!isset($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
}
?>
<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <h2>🏠 <?php echo htmlspecialchars(APP_NAME); ?></h2>
    </div>
    <ul class="sidebar-menu">
        <!-- Dashboard -->
        <li><a href="<?php echo APP_URL; ?>/user/dashboard.php" class="<?= $current_page === 'dashboard' ? 'active' : '' ?>">🏠 Dashboard</a></li>
        
        <!-- Tasks Section -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>📋 Tasks</span></li>
        <li><a href="<?php echo APP_URL; ?>/user/tasks.php" class="<?= $current_page === 'tasks' ? 'active' : '' ?>">📋 My Tasks <?php if($pending_tasks_count > 0): ?><span class="badge"><?php echo $pending_tasks_count; ?></span><?php endif; ?></a></li>
        
        <!-- Wallet -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>💰 Finance</span></li>
        <li><a href="<?php echo APP_URL; ?>/user/wallet.php" class="<?= $current_page === 'wallet' ? 'active' : '' ?>">💰 Wallet</a></li>
        <li><a href="<?php echo APP_URL; ?>/user/transactions.php" class="<?= $current_page === 'transactions' ? 'active' : '' ?>">💳 Transactions</a></li>
        
        <!-- Referrals (Phase 2) -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>🔗 Referrals</span></li>
        <li><a href="<?php echo APP_URL; ?>/user/referrals.php" class="<?= $current_page === 'referrals' ? 'active' : '' ?>">🔗 My Referrals</a></li>
        
        <!-- Rewards & Gamification (Phase 2) -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>🎮 Gamification</span></li>
        <li><a href="<?php echo APP_URL; ?>/user/rewards.php" class="<?= $current_page === 'rewards' ? 'active' : '' ?>">🎮 Rewards & Points</a></li>
        <li><a href="<?php echo APP_URL; ?>/user/leaderboard.php" class="<?= $current_page === 'leaderboard' ? 'active' : '' ?>">🏆 Leaderboard</a></li>
        
        <!-- Submit Proof (Phase 2) -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>📸 Proofs</span></li>
        <li><a href="<?php echo APP_URL; ?>/user/submit-proof.php" class="<?= $current_page === 'submit-proof' ? 'active' : '' ?>">📸 Submit Proof</a></li>
        
        <!-- Support (Phase 2) -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>💬 Support</span></li>
        <li><a href="<?php echo APP_URL; ?>/user/chat.php" class="<?= $current_page === 'chat' ? 'active' : '' ?>">💬 Support Chat <?php if($unread_messages > 0): ?><span class="badge"><?php echo $unread_messages; ?></span><?php endif; ?></a></li>
        
        <!-- KYC & Analytics (Phase 1) -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>🔐 Account</span></li>
        <li><a href="<?php echo APP_URL; ?>/user/kyc.php" class="<?= $current_page === 'kyc' ? 'active' : '' ?>">🆔 KYC Verification</a></li>
        <li><a href="<?php echo APP_URL; ?>/user/analytics.php" class="<?= $current_page === 'analytics' ? 'active' : '' ?>">📊 My Analytics</a></li>
        
        <!-- Profile & Settings -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>⚙️ Settings</span></li>
        <li><a href="<?php echo APP_URL; ?>/user/profile.php" class="<?= $current_page === 'profile' ? 'active' : '' ?>">👤 Profile</a></li>
        <li><a href="<?php echo APP_URL; ?>/user/notifications.php" class="<?= $current_page === 'notifications' ? 'active' : '' ?>">🔔 Notifications</a></li>
        
        <!-- Logout -->
        <div class="sidebar-divider"></div>
        <li><a href="<?php echo APP_URL; ?>/logout.php" class="logout">🚪 Logout</a></li>
    </ul>
</div>
