<?php
require_once '../includes/config.php';
require_once '../includes/payment-functions.php';

if (!isLoggedIn() || isAdmin()) {
    redirect('../index.php');
}

$user_id = $_SESSION['user_id'];

// Get user's payment history
$payments = getUserPayments($db, $user_id, 100);

// Get payment stats
$stats = getPaymentStats($db, $user_id);

// Set current page for sidebar
$current_page = 'payment-history';

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
    .admin-layout {
        margin-left: 260px;
        padding: 20px;
        min-height: calc(100vh - 60px);
    }
</style>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="admin-layout">
    <div class="container-fluid mt-4">
        <h2 class="mb-4"><i class="bi bi-clock-history"></i> Payment History</h2>

        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h3><?php echo $stats['total_payments']; ?></h3>
                        <p class="mb-0">Total Payments</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h3>₹<?php echo number_format($stats['total_amount'], 2); ?></h3>
                        <p class="mb-0">Total Amount</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h3><?php echo $stats['pending_count']; ?></h3>
                        <p class="mb-0">Pending Payments</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment History Table -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-list"></i> All Payments</h5>
            </div>
            <div class="card-body">
                <?php if (count($payments) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Transaction ID</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i', strtotime($payment['created_at'])); ?></td>
                                <td><code><?php echo htmlspecialchars($payment['transaction_id'] ?? $payment['razorpay_payment_id'] ?? '-'); ?></code></td>
                                <td><strong>₹<?php echo number_format($payment['amount'], 2); ?></strong></td>
                                <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                <td>
                                    <?php
                                    $badge_class = 'secondary';
                                    if ($payment['status'] == 'success') $badge_class = 'success';
                                    elseif ($payment['status'] == 'pending') $badge_class = 'warning';
                                    elseif ($payment['status'] == 'failed') $badge_class = 'danger';
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($payment['receipt_url']): ?>
                                        <a href="<?php echo htmlspecialchars($payment['receipt_url']); ?>" 
                                           target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-receipt"></i> Receipt
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
                    <h4 class="mt-3">No Payment History</h4>
                    <p class="text-muted">You haven't made any payments yet.</p>
                    <a href="recharge-wallet.php" class="btn btn-primary">
                        <i class="bi bi-wallet2"></i> Recharge Wallet
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
