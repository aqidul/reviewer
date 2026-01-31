<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/header.php';

$error = '';
$success = '';

// Get wallet details
try {
    $stmt = $pdo->prepare("SELECT * FROM seller_wallet WHERE seller_id = ?");
    $stmt->execute([$seller_id]);
    $wallet = $stmt->fetch();
    
    $balance = $wallet['balance'] ?? 0;
    $total_spent = $wallet['total_spent'] ?? 0;
    
    // Get transaction history
    $stmt = $pdo->prepare("
        SELECT pt.*, rr.product_name
        FROM payment_transactions pt
        LEFT JOIN review_requests rr ON pt.review_request_id = rr.id
        WHERE pt.seller_id = ?
        ORDER BY pt.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$seller_id]);
    $transactions = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Wallet error: ' . $e->getMessage());
    $balance = 0;
    $total_spent = 0;
    $transactions = [];
}

// Handle add money request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_money'])) {
    $amount = (float) ($_POST['amount'] ?? 0);
    
    if ($amount < 100) {
        $error = 'Minimum amount to add is ₹100';
    } elseif ($amount > 100000) {
        $error = 'Maximum amount to add is ₹1,00,000';
    } else {
        // Store amount in session to prevent parameter tampering
        $_SESSION['wallet_add_amount'] = $amount;
        
        // Redirect to payment gateway
        // In production, create payment order and redirect
        header('Location: payment-callback.php?action=add_money&amount=' . $amount);
        exit;
    }
}
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Wallet</li>
                </ol>
            </nav>
            <h3 class="mb-0">Seller Wallet</h3>
            <p class="text-muted">Manage your wallet balance and transactions</p>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Wallet Overview -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-muted small mb-1">Available Balance</div>
                            <h2 class="mb-0 text-primary">₹<?= number_format($balance, 2) ?></h2>
                        </div>
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-wallet2"></i>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#addMoneyModal">
                        <i class="bi bi-plus-circle"></i> Add Money
                    </button>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-1">Total Spent</div>
                            <h3 class="mb-0">₹<?= number_format($total_spent, 2) ?></h3>
                        </div>
                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-graph-down-arrow"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-1">Total Transactions</div>
                            <h3 class="mb-0"><?= count($transactions) ?></h3>
                        </div>
                        <div class="stat-icon bg-secondary bg-opacity-10 text-secondary">
                            <i class="bi bi-receipt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Transaction History -->
    <div class="card">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Transaction History</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($transactions)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-clock-history" style="font-size: 3rem; color: #cbd5e1;"></i>
                    <p class="text-muted mt-3 mb-0">No transactions yet</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Transaction ID</th>
                                <th>Date & Time</th>
                                <th>Description</th>
                                <th>Payment Gateway</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $txn): ?>
                                <tr>
                                    <td>
                                        <strong>#<?= $txn['id'] ?></strong>
                                        <?php if ($txn['gateway_payment_id']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($txn['gateway_payment_id']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= date('d M Y', strtotime($txn['created_at'])) ?><br>
                                        <small class="text-muted"><?= date('H:i:s', strtotime($txn['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($txn['review_request_id']): ?>
                                            Review Request #<?= $txn['review_request_id'] ?>
                                            <?php if ($txn['product_name']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($txn['product_name']) ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            Wallet Credit
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= strtoupper($txn['payment_gateway']) ?></span>
                                    </td>
                                    <td>
                                        <strong>₹<?= number_format($txn['total_amount'], 2) ?></strong>
                                        <?php if ($txn['gst_amount'] > 0): ?>
                                            <br><small class="text-muted">(Inc. GST ₹<?= number_format($txn['gst_amount'], 2) ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_badges = [
                                            'pending' => 'warning',
                                            'success' => 'success',
                                            'failed' => 'danger',
                                            'refunded' => 'info'
                                        ];
                                        $badge = $status_badges[$txn['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $badge ?>"><?= ucfirst($txn['status']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Money Modal -->
<div class="modal fade" id="addMoneyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">Add Money to Wallet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Enter Amount (₹)</label>
                        <input type="number" name="amount" class="form-control form-control-lg" 
                               placeholder="1000" min="100" max="100000" step="1" required>
                        <small class="text-muted">Minimum: ₹100 | Maximum: ₹1,00,000</small>
                    </div>
                    
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle"></i>
                        <strong>Note:</strong> GST charges apply on payment gateway fees.
                    </div>
                    
                    <div class="mt-3">
                        <h6>Quick Select:</h6>
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn btn-outline-primary" onclick="setAmount(500)">₹500</button>
                            <button type="button" class="btn btn-outline-primary" onclick="setAmount(1000)">₹1000</button>
                            <button type="button" class="btn btn-outline-primary" onclick="setAmount(2000)">₹2000</button>
                            <button type="button" class="btn btn-outline-primary" onclick="setAmount(5000)">₹5000</button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_money" class="btn btn-primary">
                        <i class="bi bi-arrow-right-circle"></i> Proceed to Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function setAmount(amount) {
    document.querySelector('input[name="amount"]').value = amount;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
