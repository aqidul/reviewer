<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/competition-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';

// Handle join competition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_competition'])) {
    $competition_id = intval($_POST['competition_id']);
    $result = joinCompetition($pdo, $competition_id, $user_id);
    $message = $result['message'];
}

// Get active competitions
$active_competitions = getActiveCompetitions($pdo);

// Get user's competitions
$user_competitions = getUserCompetitionHistory($pdo, $user_id);

$current_page = 'competitions';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Competitions - ReviewFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .competition-card {
            border-left: 4px solid #007bff;
            transition: transform 0.2s;
        }
        .competition-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="container my-5">
    <h2 class="mb-4"><i class="bi bi-trophy"></i> Competitions & Leaderboards</h2>

    <?php if (isset($message)): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Active Competitions -->
    <h4 class="mb-3">🔥 Active Competitions</h4>
    <div class="row mb-5">
        <?php foreach ($active_competitions as $comp): ?>
        <div class="col-md-6 mb-3">
            <div class="card competition-card h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <?php echo htmlspecialchars($comp['name']); ?>
                        <span class="badge bg-<?php echo $comp['status'] == 'active' ? 'success' : 'info'; ?>">
                            <?php echo ucfirst($comp['status']); ?>
                        </span>
                    </h5>
                    <p class="card-text"><?php echo htmlspecialchars($comp['description']); ?></p>
                    
                    <div class="mb-3">
                        <strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $comp['competition_type'])); ?><br>
                        <strong>Duration:</strong> 
                        <?php echo date('M d', strtotime($comp['start_date'])); ?> - 
                        <?php echo date('M d, Y', strtotime($comp['end_date'])); ?><br>
                        <strong>Prize Pool:</strong> ₹<?php echo number_format($comp['prize_pool'], 2); ?>
                    </div>

                    <?php
                    // Check if user has joined
                    $joined_stmt = $pdo->prepare("
                        SELECT * FROM competition_participants 
                        WHERE competition_id = ? AND user_id = ?
                    ");
                    $joined_stmt->execute([$comp['id'], $user_id]);
                    $has_joined = $joined_stmt->fetch();
                    ?>

                    <?php if ($has_joined): ?>
                        <div class="alert alert-success mb-2">
                            <i class="bi bi-check-circle"></i> You're participating!
                            <?php if ($has_joined['rank']): ?>
                                Current Rank: #<?php echo $has_joined['rank']; ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="competition_id" value="<?php echo $comp['id']; ?>">
                            <button type="submit" name="join_competition" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Join Competition
                            </button>
                        </form>
                    <?php endif; ?>

                    <a href="?view=<?php echo $comp['id']; ?>" class="btn btn-outline-primary mt-2">
                        <i class="bi bi-list-ol"></i> View Leaderboard
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($active_competitions)): ?>
        <div class="col-12">
            <div class="alert alert-info">
                No active competitions at the moment. Check back later!
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- User's Competition History -->
    <?php if (!empty($user_competitions)): ?>
    <h4 class="mb-3">📊 Your Competition History</h4>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Competition</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Your Rank</th>
                            <th>Score</th>
                            <th>Prize Won</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($user_competitions as $comp): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($comp['name']); ?></td>
                            <td><?php echo ucfirst($comp['competition_type']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $comp['status'] == 'ended' ? 'secondary' : 'success'; ?>">
                                    <?php echo ucfirst($comp['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($comp['rank']): ?>
                                    <strong>#<?php echo $comp['rank']; ?></strong>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($comp['score'], 2); ?></td>
                            <td>
                                <?php if ($comp['prize_won'] > 0): ?>
                                    <strong class="text-success">₹<?php echo number_format($comp['prize_won'], 2); ?></strong>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- View specific leaderboard -->
    <?php if (isset($_GET['view'])): ?>
    <?php
    $comp_id = intval($_GET['view']);
    $leaderboard = getCompetitionLeaderboard($pdo, $comp_id, 100);
    
    if (!empty($leaderboard)):
    ?>
    <div class="mt-5">
        <h4 class="mb-3">🏆 Leaderboard</h4>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>User</th>
                                <th>Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaderboard as $entry): ?>
                            <tr class="<?php echo $entry['user_id'] == $user_id ? 'table-success' : ''; ?>">
                                <td>
                                    <strong>#<?php echo $entry['rank']; ?></strong>
                                    <?php if ($entry['rank'] <= 3): ?>
                                        <?php
                                        $medals = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
                                        echo $medals[$entry['rank']];
                                        ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($entry['name']); ?></td>
                                <td><?php echo number_format($entry['metric_value'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
