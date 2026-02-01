<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$errors = [];
$success = '';

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $request_id = intval($_POST['request_id'] ?? 0);
    $admin_remarks = trim($_POST['admin_remarks'] ?? '');
    
    if ($request_id > 0) {
        try {
            // Get request details
            $stmt = $pdo->prepare("
                SELECT wrr.*, s.name as seller_name, s.email as seller_email
                FROM wallet_recharge_requests wrr
                JOIN sellers s ON wrr.seller_id = s.id
                WHERE wrr.id = ?
            ");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch();
            
            if (!$request) {
                $errors[] = "Recharge request not found";
            } elseif ($request['status'] !== 'pending') {
                $errors[] = "This request has already been processed";
            } else {
                $pdo->beginTransaction();
                
                if ($action === 'approve') {
                    // Approve the request
                    $stmt = $pdo->prepare("
                        UPDATE wallet_recharge_requests 
                        SET status = 'approved', admin_remarks = ?, approved_by = ?, approved_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$admin_remarks, $_SESSION['admin_id'] ?? 1, $request_id]);
                    
                    // Update seller wallet balance
                    $stmt = $pdo->prepare("
                        INSERT INTO seller_wallet (seller_id, balance, total_spent)
                        VALUES (?, ?, 0)
                        ON DUPLICATE KEY UPDATE balance = balance + ?
                    ");
                    $stmt->execute([$request['seller_id'], $request['amount'], $request['amount']]);
                    
                    // Insert payment transaction record
                    $stmt = $pdo->prepare("
                        INSERT INTO payment_transactions 
                        (seller_id, review_request_id, amount, gst_amount, total_amount, payment_gateway, gateway_payment_id, status, created_at)
                        VALUES (?, NULL, ?, 0, ?, 'bank_transfer', ?, 'success', NOW())
                    ");
                    $stmt->execute([
                        $request['seller_id'], 
                        $request['amount'], 
                        $request['amount'],
                        'UTR:' . $request['utr_number']
                    ]);
                    
                    $pdo->commit();
                    $success = "Recharge request #$request_id approved successfully! ₹" . number_format($request['amount'], 2) . " added to seller wallet.";
                    
                } elseif ($action === 'reject') {
                    // Reject the request
                    if (empty($admin_remarks)) {
                        $errors[] = "Please provide a reason for rejection";
                        $pdo->rollBack();
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE wallet_recharge_requests 
                            SET status = 'rejected', admin_remarks = ?, approved_by = ?, approved_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$admin_remarks, $_SESSION['admin_id'] ?? 1, $request_id]);
                        
                        $pdo->commit();
                        $success = "Recharge request #$request_id rejected.";
                    }
                }
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Wallet request processing error: ' . $e->getMessage());
            $errors[] = 'Failed to process request. Please try again.';
        }
    }
}

// Get filter parameter
$filter = $_GET['filter'] ?? 'pending';
$allowed_filters = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($filter, $allowed_filters)) {
    $filter = 'pending';
}

// Fetch wallet recharge requests
try {
    $sql = "
        SELECT wrr.*, s.name as seller_name, s.email as seller_email, s.mobile as seller_mobile
        FROM wallet_recharge_requests wrr
        JOIN sellers s ON wrr.seller_id = s.id
    ";
    
    if ($filter !== 'all') {
        $sql .= " WHERE wrr.status = ?";
    }
    
    $sql .= " ORDER BY wrr.created_at DESC LIMIT 100";
    
    $stmt = $pdo->prepare($sql);
    
    if ($filter !== 'all') {
        $stmt->execute([$filter]);
    } else {
        $stmt->execute();
    }
    
    $requests = $stmt->fetchAll();
    
    // Get counts for badges
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM wallet_recharge_requests GROUP BY status");
    $status_counts = [];
    while ($row = $stmt->fetch()) {
        $status_counts[$row['status']] = $row['count'];
    }
    
} catch (PDOException $e) {
    error_log('Failed to fetch wallet requests: ' . $e->getMessage());
    $requests = [];
    $status_counts = [];
}

$pending_count = $status_counts['pending'] ?? 0;
$approved_count = $status_counts['approved'] ?? 0;
$rejected_count = $status_counts['rejected'] ?? 0;
$total_count = array_sum($status_counts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet Recharge Requests - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #f8fafc;
        }
        .navbar {
            background: #1e293b;
        }
        .badge-count {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-speedometer2"></i> <?= APP_NAME ?> Admin
            </a>
            <div class="d-flex align-items-center text-white">
                <span class="me-3"><?= htmlspecialchars($admin_name) ?></span>
                <a href="logout.php" class="btn btn-sm btn-outline-light">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Wallet Recharge Requests</li>
                    </ol>
                </nav>
                <h3 class="mb-0">Wallet Recharge Requests</h3>
                <p class="text-muted">Manage seller wallet recharge requests via bank transfer</p>
            </div>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Filter Tabs -->
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link <?= $filter === 'all' ? 'active' : '' ?>" href="?filter=all">
                    All <span class="badge bg-secondary badge-count"><?= $total_count ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $filter === 'pending' ? 'active' : '' ?>" href="?filter=pending">
                    Pending <span class="badge bg-warning badge-count"><?= $pending_count ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $filter === 'approved' ? 'active' : '' ?>" href="?filter=approved">
                    Approved <span class="badge bg-success badge-count"><?= $approved_count ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $filter === 'rejected' ? 'active' : '' ?>" href="?filter=rejected">
                    Rejected <span class="badge bg-danger badge-count"><?= $rejected_count ?></span>
                </a>
            </li>
        </ul>
        
        <!-- Requests Table -->
        <div class="card">
            <div class="card-body p-0">
                <?php if (empty($requests)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 3rem; color: #cbd5e1;"></i>
                        <p class="text-muted mt-3 mb-0">No recharge requests found</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Seller Details</th>
                                    <th>Amount</th>
                                    <th>UTR Number</th>
                                    <th>Transfer Date</th>
                                    <th>Screenshot</th>
                                    <th>Submitted</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $req): ?>
                                    <tr>
                                        <td><strong>#<?= $req['id'] ?></strong></td>
                                        <td>
                                            <strong><?= htmlspecialchars($req['seller_name']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($req['seller_email']) ?></small><br>
                                            <small class="text-muted"><?= htmlspecialchars($req['seller_mobile']) ?></small>
                                        </td>
                                        <td>
                                            <strong class="text-primary">₹<?= number_format($req['amount'], 2) ?></strong>
                                        </td>
                                        <td>
                                            <code><?= htmlspecialchars($req['utr_number']) ?></code>
                                        </td>
                                        <td><?= date('d M Y', strtotime($req['transfer_date'])) ?></td>
                                        <td>
                                            <a href="../uploads/wallet_screenshots/<?= htmlspecialchars($req['screenshot_path']) ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-image"></i> View
                                            </a>
                                        </td>
                                        <td>
                                            <?= date('d M Y', strtotime($req['created_at'])) ?><br>
                                            <small class="text-muted"><?= date('H:i', strtotime($req['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $status_badges = [
                                                'pending' => 'warning',
                                                'approved' => 'success',
                                                'rejected' => 'danger'
                                            ];
                                            $badge = $status_badges[$req['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $badge ?>">
                                                <?= ucfirst($req['status']) ?>
                                            </span>
                                            <?php if ($req['admin_remarks']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($req['admin_remarks']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($req['status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-sm btn-success mb-1" 
                                                        onclick="showActionModal(<?= $req['id'] ?>, 'approve', '<?= htmlspecialchars($req['seller_name'], ENT_QUOTES) ?>', <?= $req['amount'] ?>)">
                                                    <i class="bi bi-check-circle"></i> Approve
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        onclick="showActionModal(<?= $req['id'] ?>, 'reject', '<?= htmlspecialchars($req['seller_name'], ENT_QUOTES) ?>', <?= $req['amount'] ?>)">
                                                    <i class="bi bi-x-circle"></i> Reject
                                                </button>
                                            <?php else: ?>
                                                <small class="text-muted">
                                                    <?php if ($req['approved_at']): ?>
                                                        <?= date('d M Y H:i', strtotime($req['approved_at'])) ?>
                                                    <?php endif; ?>
                                                </small>
                                            <?php endif; ?>
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

    <!-- Action Modal -->
    <div class="modal fade" id="actionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="actionForm">
                    <input type="hidden" name="request_id" id="modal_request_id">
                    <input type="hidden" name="action" id="modal_action">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="modal_title">Confirm Action</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div id="modal_message"></div>
                        
                        <div class="mb-3">
                            <label class="form-label">Admin Remarks</label>
                            <textarea name="admin_remarks" class="form-control" rows="3" 
                                      id="admin_remarks_field" placeholder="Enter remarks (optional for approval, required for rejection)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="modal_submit_btn">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showActionModal(requestId, action, sellerName, amount) {
            document.getElementById('modal_request_id').value = requestId;
            document.getElementById('modal_action').value = action;
            
            const modal = new bootstrap.Modal(document.getElementById('actionModal'));
            const title = document.getElementById('modal_title');
            const message = document.getElementById('modal_message');
            const submitBtn = document.getElementById('modal_submit_btn');
            const remarksField = document.getElementById('admin_remarks_field');
            
            if (action === 'approve') {
                title.textContent = 'Approve Recharge Request';
                message.innerHTML = `<div class="alert alert-success">
                    <strong>Confirm Approval</strong><br>
                    Seller: <strong>${sellerName}</strong><br>
                    Amount: <strong>₹${amount.toFixed(2)}</strong><br>
                    This amount will be added to the seller's wallet.
                </div>`;
                submitBtn.className = 'btn btn-success';
                submitBtn.textContent = 'Approve';
                remarksField.required = false;
            } else if (action === 'reject') {
                title.textContent = 'Reject Recharge Request';
                message.innerHTML = `<div class="alert alert-danger">
                    <strong>Confirm Rejection</strong><br>
                    Seller: <strong>${sellerName}</strong><br>
                    Amount: <strong>₹${amount.toFixed(2)}</strong><br>
                    Please provide a reason for rejection.
                </div>`;
                submitBtn.className = 'btn btn-danger';
                submitBtn.textContent = 'Reject';
                remarksField.required = true;
            }
            
            modal.show();
        }
    </script>
</body>
</html>
