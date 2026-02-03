<?php
require_once '../includes/config.php';
require_once '../includes/activity-logger.php';

if (!isLoggedIn() || isAdmin()) {
    redirect('../index.php');
}

$user_id = $_SESSION['user_id'];

// Get user activity
$activities = getUserActivity($db, $user_id, 100);

// Get login history
$logins = getUserLoginHistory($db, $user_id, 20);

// Get activity stats
$stats = getActivityStats($db, $user_id);

// Set current page for sidebar
$current_page = 'my-activity';

include '../includes/header.php';
?>

<style>
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
        <h2 class="mb-4"><i class="bi bi-activity"></i> My Activity</h2>

        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h3><?php echo $stats['total_activities']; ?></h3>
                        <p class="mb-0">Total Activities</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h3><?php echo $stats['active_days']; ?></h3>
                        <p class="mb-0">Active Days</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h3><?php echo $stats['unique_actions']; ?></h3>
                        <p class="mb-0">Unique Actions</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-clock-history"></i> Recent Activity</h5>
            </div>
            <div class="card-body">
                <?php if (count($activities) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities as $activity): ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i:s', strtotime($activity['created_at'])); ?></td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo ucfirst(str_replace('_', ' ', $activity['action'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                <td><code><?php echo htmlspecialchars($activity['ip_address']); ?></code></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-muted">No activity recorded yet</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Login History -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-shield-lock"></i> Login History</h5>
            </div>
            <div class="card-body">
                <?php if (count($logins) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Device</th>
                                <th>Browser</th>
                                <th>IP Address</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logins as $login): ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i:s', strtotime($login['created_at'])); ?></td>
                                <td>
                                    <i class="bi bi-<?php echo $login['device_type'] == 'mobile' ? 'phone' : 'laptop'; ?>"></i>
                                    <?php echo ucfirst($login['device_type']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($login['browser']); ?></td>
                                <td><code><?php echo htmlspecialchars($login['ip_address']); ?></code></td>
                                <td>
                                    <span class="badge bg-<?php echo $login['status'] == 'success' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($login['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-muted">No login history available</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
