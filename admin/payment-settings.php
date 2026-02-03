<?php
require_once '../includes/config.php';
require_once '../includes/payment-functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../index.php');
}

$message = '';
$error = '';

// Handle config update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_config'])) {
    try {
        foreach ($_POST['config'] as $key => $value) {
            $stmt = $db->prepare("
                INSERT INTO payment_config (config_key, config_value, is_active) 
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE config_value = ?
            ");
            $stmt->execute([$key, $value, $value]);
        }
        $message = 'Payment settings updated successfully!';
    } catch (Exception $e) {
        $error = 'Failed to update settings: ' . $e->getMessage();
    }
}

// Get all payments
$status_filter = $_GET['status'] ?? '';
$payments = getAllPayments($db, $status_filter, 100);

// Get payment stats
$stats = getPaymentStats($db);

// Get current config
$config_stmt = $db->query("SELECT * FROM payment_config WHERE is_active = 1");
$configs = $config_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

include '../includes/header.php';
$current_page = 'payment-settings';
?>

<style>
.admin-layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
.sidebar{background:linear-gradient(180deg,#2c3e50 0%,#1a252f 100%);color:#fff;padding:0;position:sticky;top:0;height:100vh;overflow-y:auto}
.main-content{padding:25px;overflow-x:hidden}
</style>

<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="main-content">
        <h2 class="mb-4"><i class="bi bi-credit-card"></i> Payment Gateway Settings</h2>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h3><?php echo $stats['total_payments']; ?></h3>
                        <p class="mb-0">Total Payments</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h3>₹<?php echo number_format($stats['total_amount'], 2); ?></h3>
                        <p class="mb-0">Total Amount</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h3><?php echo $stats['pending_count']; ?></h3>
                        <p class="mb-0">Pending</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h3><?php echo $stats['failed_count']; ?></h3>
                        <p class="mb-0">Failed</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Configuration -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-gear"></i> Razorpay Configuration</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Test Mode</label>
                            <select class="form-select" name="config[razorpay_test_mode]">
                                <option value="1" <?php echo ($configs['razorpay_test_mode'] ?? '1') == '1' ? 'selected' : ''; ?>>Enabled</option>
                                <option value="0" <?php echo ($configs['razorpay_test_mode'] ?? '1') == '0' ? 'selected' : ''; ?>>Disabled</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gateway Enabled</label>
                            <select class="form-select" name="config[razorpay_enabled]">
                                <option value="1" <?php echo ($configs['razorpay_enabled'] ?? '1') == '1' ? 'selected' : ''; ?>>Yes</option>
                                <option value="0" <?php echo ($configs['razorpay_enabled'] ?? '1') == '0' ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Test Key ID</label>
                            <input type="text" class="form-control" name="config[razorpay_test_key_id]" 
                                   value="<?php echo htmlspecialchars($configs['razorpay_test_key_id'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Test Key Secret</label>
                            <input type="password" class="form-control" name="config[razorpay_test_key_secret]" 
                                   value="<?php echo htmlspecialchars($configs['razorpay_test_key_secret'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Min Recharge Amount (₹)</label>
                            <input type="number" class="form-control" name="config[min_recharge_amount]" 
                                   value="<?php echo htmlspecialchars($configs['min_recharge_amount'] ?? '100'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Max Recharge Amount (₹)</label>
                            <input type="number" class="form-control" name="config[max_recharge_amount]" 
                                   value="<?php echo htmlspecialchars($configs['max_recharge_amount'] ?? '50000'); ?>">
                        </div>
                    </div>

                    <button type="submit" name="update_config" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Settings
                    </button>
                </form>
            </div>
        </div>

        <!-- Recent Payments -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-clock-history"></i> Recent Payments</h5>
                <div class="btn-group btn-group-sm">
                    <a href="?status=" class="btn btn-<?php echo $status_filter == '' ? 'primary' : 'outline-primary'; ?>">All</a>
                    <a href="?status=pending" class="btn btn-<?php echo $status_filter == 'pending' ? 'primary' : 'outline-primary'; ?>">Pending</a>
                    <a href="?status=success" class="btn btn-<?php echo $status_filter == 'success' ? 'primary' : 'outline-primary'; ?>">Success</a>
                    <a href="?status=failed" class="btn btn-<?php echo $status_filter == 'failed' ? 'primary' : 'outline-primary'; ?>">Failed</a>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($payments) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Transaction ID</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo $payment['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($payment['username']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($payment['email']); ?></small>
                                </td>
                                <td><strong>₹<?php echo number_format($payment['amount'], 2); ?></strong></td>
                                <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                <td><code><?php echo htmlspecialchars($payment['razorpay_payment_id'] ?? '-'); ?></code></td>
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
                                <td><?php echo date('M d, Y H:i', strtotime($payment['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-muted">No payments found</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
