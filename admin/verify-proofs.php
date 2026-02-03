<?php
require_once '../includes/config.php';
require_once '../includes/proof-functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../index.php');
}

$admin_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle proof approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_proof'])) {
        $proof_id = filter_input(INPUT_POST, 'proof_id', FILTER_SANITIZE_NUMBER_INT);
        $result = approveProof($db, $proof_id, $admin_id);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
    
    if (isset($_POST['reject_proof'])) {
        $proof_id = filter_input(INPUT_POST, 'proof_id', FILTER_SANITIZE_NUMBER_INT);
        $reason = filter_input(INPUT_POST, 'rejection_reason', FILTER_SANITIZE_STRING);
        $result = rejectProof($db, $proof_id, $admin_id, $reason);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// Get pending proofs
$pending_proofs = getPendingProofs($db, 100);

// Get statistics
$stats = getProofStats($db);

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Admin Sidebar -->
        <div class="col-md-2">
            <div class="list-group">
                <a href="dashboard.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="referral-settings.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-people"></i> Referral Settings
                </a>
                <a href="verify-proofs.php" class="list-group-item list-group-item-action active">
                    <i class="bi bi-file-earmark-check"></i> Verify Proofs
                </a>
                <a href="gamification-settings.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-trophy"></i> Gamification
                </a>
                <a href="support-chat.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-chat-dots"></i> Support Chat
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-10">
            <h2 class="mb-4"><i class="bi bi-file-earmark-check-fill"></i> Proof Verification</h2>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3><?php echo $stats['total']; ?></h3>
                            <p class="mb-0 small">Total Proofs</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-warning text-white text-center">
                        <div class="card-body">
                            <h3><?php echo $stats['pending']; ?></h3>
                            <p class="mb-0 small">Pending</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-info text-white text-center">
                        <div class="card-body">
                            <h3><?php echo $stats['manual_review']; ?></h3>
                            <p class="mb-0 small">Manual Review</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-success text-white text-center">
                        <div class="card-body">
                            <h3><?php echo $stats['approved']; ?></h3>
                            <p class="mb-0 small">Approved</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-primary text-white text-center">
                        <div class="card-body">
                            <h3><?php echo $stats['auto_approved']; ?></h3>
                            <p class="mb-0 small">Auto Approved</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-danger text-white text-center">
                        <div class="card-body">
                            <h3><?php echo $stats['rejected']; ?></h3>
                            <p class="mb-0 small">Rejected</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Proofs -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-hourglass-split"></i> Proofs Awaiting Verification</h5>
                </div>
                <div class="card-body">
                    <?php if (count($pending_proofs) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Task</th>
                                    <th>Type</th>
                                    <th>Submitted</th>
                                    <th>AI Score</th>
                                    <th>Proof</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_proofs as $proof): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($proof['username']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($proof['email']); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($proof['task_title']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo ucfirst($proof['proof_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($proof['created_at'])); ?></td>
                                    <td>
                                        <?php if ($proof['ai_score']): ?>
                                            <div class="progress" style="width: 80px;">
                                                <div class="progress-bar <?php 
                                                    echo $proof['ai_score'] >= 80 ? 'bg-success' : 
                                                        ($proof['ai_score'] >= 50 ? 'bg-warning' : 'bg-danger'); 
                                                ?>" role="progressbar" 
                                                    style="width: <?php echo $proof['ai_score']; ?>%">
                                                    <?php echo number_format($proof['ai_score'], 0); ?>%
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($proof['proof_file']): ?>
                                            <a href="../<?php echo htmlspecialchars($proof['proof_file']); ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        <?php elseif ($proof['proof_text']): ?>
                                            <button class="btn btn-sm btn-outline-info" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#proofTextModal<?php echo $proof['id']; ?>">
                                                <i class="bi bi-file-text"></i> View Text
                                            </button>
                                            
                                            <!-- Modal for text proof -->
                                            <div class="modal fade" id="proofTextModal<?php echo $proof['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Proof Text</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <pre><?php echo htmlspecialchars($proof['proof_text']); ?></pre>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="proof_id" value="<?php echo $proof['id']; ?>">
                                            <button type="submit" name="approve_proof" class="btn btn-sm btn-success" 
                                                    onclick="return confirm('Approve this proof?')">
                                                <i class="bi bi-check-circle"></i> Approve
                                            </button>
                                        </form>
                                        
                                        <button class="btn btn-sm btn-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#rejectModal<?php echo $proof['id']; ?>">
                                            <i class="bi bi-x-circle"></i> Reject
                                        </button>
                                        
                                        <!-- Reject Modal -->
                                        <div class="modal fade" id="rejectModal<?php echo $proof['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Reject Proof</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="proof_id" value="<?php echo $proof['id']; ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label">Rejection Reason *</label>
                                                                <textarea class="form-control" name="rejection_reason" 
                                                                          rows="3" required 
                                                                          placeholder="Provide a clear reason for rejection..."></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="reject_proof" class="btn btn-danger">
                                                                Reject Proof
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-check-circle" style="font-size: 4rem; color: #28a745;"></i>
                        <h4 class="mt-3">All Caught Up!</h4>
                        <p class="text-muted">No proofs pending verification</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
