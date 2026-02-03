<?php
require_once '../includes/config.php';
require_once '../includes/referral-functions.php';

if (!isLoggedIn() || isAdmin()) {
    redirect('../index.php');
}

$user_id = $_SESSION['user_id'];

// Get referral code
$referral_code = getUserReferralCode($db, $user_id);

// Get referral stats
$stats = getReferralStats($db, $user_id);

// Get referral tree
$referral_tree = getReferralTree($db, $user_id);

// Get recent earnings
$recent_earnings = getRecentReferralEarnings($db, $user_id, 10);

// Get referral settings
$referral_settings = getReferralSettings($db);

// Generate share links
$referral_link = generateReferralLink($referral_code);
$whatsapp_link = getWhatsAppShareLink($referral_code);
$facebook_link = getFacebookShareLink($referral_code);
$twitter_link = getTwitterShareLink($referral_code);

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2">
            <div class="list-group">
                <a href="dashboard.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="tasks.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-list-task"></i> My Tasks
                </a>
                <a href="referrals.php" class="list-group-item list-group-item-action active">
                    <i class="bi bi-people"></i> Referrals
                </a>
                <a href="rewards.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-trophy"></i> Rewards
                </a>
                <a href="chat.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-chat-dots"></i> Chat Support
                </a>
                <a href="wallet.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-wallet2"></i> Wallet
                </a>
                <a href="profile.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-person"></i> Profile
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-10">
            <h2 class="mb-4"><i class="bi bi-people-fill"></i> Referral & Affiliate Program</h2>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h3 class="mb-0"><?php echo $stats['total_referrals']; ?></h3>
                            <p class="mb-0">Total Referrals</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h3 class="mb-0"><?php echo $stats['active_referrals']; ?></h3>
                            <p class="mb-0">Active Referrals</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h3 class="mb-0">₹<?php echo number_format($stats['total_earnings'], 2); ?></h3>
                            <p class="mb-0">Total Earnings</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h3 class="mb-0">₹<?php echo number_format($stats['pending_earnings'], 2); ?></h3>
                            <p class="mb-0">Pending Earnings</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Referral Link Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-link-45deg"></i> Your Referral Link</h5>
                </div>
                <div class="card-body">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="referralLink" value="<?php echo htmlspecialchars($referral_link); ?>" readonly>
                        <button class="btn btn-primary" onclick="copyReferralLink()">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </div>
                    <div class="mb-3">
                        <strong>Your Referral Code:</strong> 
                        <span class="badge bg-primary fs-6"><?php echo htmlspecialchars($referral_code); ?></span>
                    </div>
                    <div>
                        <h6>Share on Social Media:</h6>
                        <a href="<?php echo htmlspecialchars($whatsapp_link); ?>" target="_blank" class="btn btn-success me-2">
                            <i class="bi bi-whatsapp"></i> WhatsApp
                        </a>
                        <a href="<?php echo htmlspecialchars($facebook_link); ?>" target="_blank" class="btn btn-primary me-2">
                            <i class="bi bi-facebook"></i> Facebook
                        </a>
                        <a href="<?php echo htmlspecialchars($twitter_link); ?>" target="_blank" class="btn btn-info">
                            <i class="bi bi-twitter"></i> Twitter
                        </a>
                    </div>
                </div>
            </div>

            <!-- Commission Structure -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-cash-stack"></i> Commission Structure</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Level</th>
                                    <th>Commission Rate</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($referral_settings as $setting): ?>
                                <tr>
                                    <td><strong>Level <?php echo $setting['level']; ?></strong></td>
                                    <td><span class="badge bg-success"><?php echo $setting['commission_percent']; ?>%</span></td>
                                    <td>
                                        <?php if ($setting['level'] == 1): ?>
                                            Direct referrals - Your immediate referrals
                                        <?php elseif ($setting['level'] == 2): ?>
                                            2nd level - Referrals from your direct referrals
                                        <?php else: ?>
                                            3rd level - Referrals from 2nd level referrals
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> <strong>How it works:</strong> 
                        You earn commission when your referrals complete tasks. The commission is automatically credited to your wallet!
                    </div>
                </div>
            </div>

            <!-- Referral Tree -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-diagram-3"></i> Your Referral Network</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($referral_tree as $level => $referrals): ?>
                        <?php if (count($referrals) > 0): ?>
                        <h6 class="mt-3">Level <?php echo $level; ?> (<?php echo count($referrals); ?> referrals)</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Completed Tasks</th>
                                        <th>Joined Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($referrals as $ref): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($ref['username']); ?></td>
                                        <td><?php echo htmlspecialchars($ref['email']); ?></td>
                                        <td>
                                            <?php if ($ref['status'] == 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php elseif ($ref['status'] == 'pending'): ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $ref['completed_tasks']; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($ref['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <?php if (array_sum(array_map('count', $referral_tree)) == 0): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-people" style="font-size: 4rem; color: #ccc;"></i>
                            <h4 class="mt-3">No referrals yet</h4>
                            <p class="text-muted">Start sharing your referral link to earn commissions!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Earnings -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-clock-history"></i> Recent Earnings</h5>
                </div>
                <div class="card-body">
                    <?php if (count($recent_earnings) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>From User</th>
                                    <th>Task</th>
                                    <th>Level</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_earnings as $earning): ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($earning['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($earning['from_username']); ?></td>
                                    <td><?php echo htmlspecialchars($earning['task_title'] ?? 'N/A'); ?></td>
                                    <td><span class="badge bg-info">Level <?php echo $earning['level']; ?></span></td>
                                    <td><strong>₹<?php echo number_format($earning['amount'], 2); ?></strong></td>
                                    <td>
                                        <?php if ($earning['status'] == 'credited'): ?>
                                            <span class="badge bg-success">Credited</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-muted">No earnings yet. Start referring to earn commissions!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyReferralLink() {
    const input = document.getElementById('referralLink');
    input.select();
    input.setSelectionRange(0, 99999); // For mobile devices
    
    navigator.clipboard.writeText(input.value).then(() => {
        alert('Referral link copied to clipboard!');
    }).catch(err => {
        console.error('Failed to copy:', err);
    });
}
</script>

<?php include '../includes/footer.php'; ?>
