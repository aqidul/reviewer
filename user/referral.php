<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get or generate referral code
$referral_code = getReferralCode($user_id);
$referral_bonus = (float)getSetting('referral_bonus', 50);

// Get referral stats
try {
    // Total referrals
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ?");
    $stmt->execute([$user_id]);
    $total_referrals = (int)$stmt->fetchColumn();
    
    // Completed referrals (bonus received)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $completed_referrals = (int)$stmt->fetchColumn();
    
    // Pending referrals
    $pending_referrals = $total_referrals - $completed_referrals;
    
    // Total earnings from referrals
    $stmt = $pdo->prepare("SELECT referral_earnings FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $total_earnings = (float)$stmt->fetchColumn();
    
    // Get referred users list
    $stmt = $pdo->prepare("
        SELECT r.*, u.name as referred_name, u.email as referred_email, u.created_at as joined_at
        FROM referrals r
        JOIN users u ON r.referred_id = u.id
        WHERE r.referrer_id = ?
        ORDER BY r.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $referred_users = $stmt->fetchAll();
    
    // Get referral transactions
    $stmt = $pdo->prepare("
        SELECT * FROM wallet_transactions 
        WHERE user_id = ? AND type = 'referral' 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $referral_transactions = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Referral Error: " . $e->getMessage());
    $total_referrals = 0;
    $completed_referrals = 0;
    $pending_referrals = 0;
    $total_earnings = 0;
    $referred_users = [];
    $referral_transactions = [];
}

// Referral link
$referral_link = APP_URL . "/index.php?ref=" . $referral_code;

// Share messages
$whatsapp_message = "🎁 Join " . APP_NAME . " and start earning money!\n\n✅ Complete simple tasks\n✅ Earn rewards\n✅ Instant withdrawals\n\n👉 Use my referral code: " . $referral_code . "\n\n🔗 " . $referral_link;
$telegram_message = "Join " . APP_NAME . "! Use code: " . $referral_code . " " . $referral_link;
$twitter_message = "I'm earning money on " . APP_NAME . "! Join using my code: " . $referral_code . " " . $referral_link;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refer & Earn - <?php echo APP_NAME; ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;padding:20px}
        
        .container{max-width:900px;margin:0 auto}
        
        .back-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:#fff;color:#333;text-decoration:none;border-radius:10px;margin-bottom:20px;font-weight:600;font-size:14px;transition:transform 0.2s;box-shadow:0 3px 10px rgba(0,0,0,0.1)}
        .back-btn:hover{transform:translateY(-2px)}
        
        /* Hero Card */
        .hero-card{background:linear-gradient(135deg,#f39c12 0%,#e67e22 100%);border-radius:20px;padding:35px;color:#fff;margin-bottom:25px;text-align:center;position:relative;overflow:hidden;box-shadow:0 10px 40px rgba(243,156,18,0.4)}
        .hero-card::before{content:'';position:absolute;top:-50%;right:-50%;width:100%;height:100%;background:radial-gradient(circle,rgba(255,255,255,0.2) 0%,transparent 70%);pointer-events:none}
        .hero-card::after{content:'🎁';position:absolute;top:20px;right:30px;font-size:60px;opacity:0.2}
        .hero-title{font-size:28px;font-weight:700;margin-bottom:10px}
        .hero-subtitle{font-size:16px;opacity:0.9;margin-bottom:25px}
        .hero-amount{font-size:52px;font-weight:800;margin-bottom:5px;text-shadow:0 3px 15px rgba(0,0,0,0.2)}
        .hero-label{font-size:14px;opacity:0.9}
        
        /* Referral Code Box */
        .code-box{background:#fff;border-radius:15px;padding:25px;margin-bottom:25px;box-shadow:0 5px 20px rgba(0,0,0,0.1)}
        .code-title{font-size:16px;font-weight:600;color:#333;margin-bottom:15px;text-align:center}
        .code-display{background:linear-gradient(135deg,#f8f9fa,#e9ecef);border:2px dashed #f39c12;border-radius:12px;padding:20px;text-align:center;margin-bottom:20px}
        .code-value{font-size:32px;font-weight:800;letter-spacing:4px;color:#333;margin-bottom:10px}
        .code-copy{display:inline-flex;align-items:center;gap:8px;padding:10px 25px;background:linear-gradient(135deg,#f39c12,#e67e22);color:#fff;border:none;border-radius:25px;font-weight:600;cursor:pointer;font-size:14px;transition:all 0.2s}
        .code-copy:hover{transform:scale(1.05);box-shadow:0 5px 20px rgba(243,156,18,0.4)}
        
        .link-box{background:#f8f9fa;border-radius:10px;padding:12px 15px;display:flex;align-items:center;gap:10px;margin-bottom:20px}
        .link-box input{flex:1;background:none;border:none;font-size:13px;color:#666;outline:none}
        .link-copy{padding:8px 15px;background:#667eea;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer}
        
        .share-section{text-align:center}
        .share-title{font-size:14px;color:#666;margin-bottom:15px}
        .share-buttons{display:flex;justify-content:center;gap:12px;flex-wrap:wrap}
        .share-btn{display:inline-flex;align-items:center;gap:8px;padding:12px 20px;border-radius:25px;text-decoration:none;color:#fff;font-weight:600;font-size:13px;transition:all 0.2s}
        .share-btn:hover{transform:translateY(-3px);box-shadow:0 5px 20px rgba(0,0,0,0.2)}
        .share-btn.whatsapp{background:#25d366}
        .share-btn.telegram{background:#0088cc}
        .share-btn.twitter{background:#1da1f2}
        .share-btn.facebook{background:#1877f2}
        .share-btn.copy{background:#333}
        
        /* Stats Grid */
        .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:25px}
        .stat-card{background:#fff;border-radius:12px;padding:20px;text-align:center;box-shadow:0 3px 15px rgba(0,0,0,0.08)}
        .stat-icon{font-size:28px;margin-bottom:8px}
        .stat-value{font-size:26px;font-weight:700;color:#333}
        .stat-label{font-size:12px;color:#888;margin-top:3px}
        
        /* How It Works */
        .how-it-works{background:#fff;border-radius:15px;padding:25px;margin-bottom:25px;box-shadow:0 5px 20px rgba(0,0,0,0.1)}
        .section-title{font-size:18px;font-weight:600;color:#333;margin-bottom:20px;display:flex;align-items:center;gap:10px}
        .steps{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
        .step{text-align:center;padding:20px}
        .step-number{width:50px;height:50px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;margin:0 auto 15px}
        .step-title{font-weight:600;color:#333;margin-bottom:8px}
        .step-desc{font-size:13px;color:#666;line-height:1.5}
        
        /* Referred Users */
        .card{background:#fff;border-radius:15px;padding:25px;box-shadow:0 5px 20px rgba(0,0,0,0.1);margin-bottom:25px}
        .card-title{font-size:18px;font-weight:600;color:#333;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center}
        
        .user-list{max-height:400px;overflow-y:auto}
        .user-item{display:flex;align-items:center;padding:15px 0;border-bottom:1px solid #f5f5f5}
        .user-item:last-child{border-bottom:none}
        .user-avatar{width:45px;height:45px;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:16px;margin-right:15px}
        .user-info{flex:1}
        .user-name{font-weight:600;color:#333;font-size:14px}
        .user-date{font-size:12px;color:#888;margin-top:2px}
        .user-status{padding:5px 12px;border-radius:15px;font-size:11px;font-weight:600}
        .user-status.completed{background:#d4edda;color:#155724}
        .user-status.pending{background:#fff3cd;color:#856404}
        .user-bonus{font-weight:600;color:#27ae60;font-size:14px;margin-left:10px}
        
        /* Transactions */
        .transaction-item{display:flex;align-items:center;padding:12px 0;border-bottom:1px solid #f5f5f5}
        .transaction-item:last-child{border-bottom:none}
        .txn-icon{width:40px;height:40px;background:#e8f5e9;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;margin-right:12px}
        .txn-info{flex:1}
        .txn-desc{font-weight:600;color:#333;font-size:14px}
        .txn-date{font-size:12px;color:#888;margin-top:2px}
        .txn-amount{font-weight:700;color:#27ae60;font-size:15px}
        
        /* Empty State */
        .empty-state{text-align:center;padding:40px 20px;color:#999}
        .empty-state .icon{font-size:50px;margin-bottom:15px;opacity:0.5}
        .empty-state h4{color:#666;margin-bottom:8px}
        .empty-state p{font-size:13px}
        
        /* Responsive */
        @media(max-width:768px){
            .stats-grid{grid-template-columns:repeat(2,1fr)}
            .steps{grid-template-columns:1fr}
            .hero-amount{font-size:40px}
            .code-value{font-size:24px}
            .share-buttons{flex-direction:column}
            .share-btn{justify-content:center}
        }
    </style>
</head>
<body>
<div class="container">
    <a href="<?php echo APP_URL; ?>/user/" class="back-btn">← Back to Dashboard</a>
    
    <!-- Hero Card -->
    <div class="hero-card">
        <div class="hero-title">🎁 Refer Friends & Earn Rewards!</div>
        <div class="hero-subtitle">Share your referral code and earn for every friend who joins</div>
        <div class="hero-amount">₹<?php echo number_format($referral_bonus, 0); ?></div>
        <div class="hero-label">Per Successful Referral</div>
    </div>
    
    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-value"><?php echo $total_referrals; ?></div>
            <div class="stat-label">Total Referrals</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?php echo $completed_referrals; ?></div>
            <div class="stat-label">Completed</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏳</div>
            <div class="stat-value"><?php echo $pending_referrals; ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-value">₹<?php echo number_format($total_earnings, 0); ?></div>
            <div class="stat-label">Total Earned</div>
        </div>
    </div>
    
    <!-- Referral Code Box -->
    <div class="code-box">
        <div class="code-title">Your Referral Code</div>
        <div class="code-display">
            <div class="code-value" id="referralCode"><?php echo $referral_code; ?></div>
            <button class="code-copy" onclick="copyCode()">📋 Copy Code</button>
        </div>
        
        <div class="link-box">
            <input type="text" value="<?php echo $referral_link; ?>" id="referralLink" readonly>
            <button class="link-copy" onclick="copyLink()">Copy Link</button>
        </div>
        
        <div class="share-section">
            <div class="share-title">Share via</div>
            <div class="share-buttons">
                <a href="https://wa.me/?text=<?php echo urlencode($whatsapp_message); ?>" target="_blank" class="share-btn whatsapp">
                    📱 WhatsApp
                </a>
                <a href="https://t.me/share/url?url=<?php echo urlencode($referral_link); ?>&text=<?php echo urlencode($telegram_message); ?>" target="_blank" class="share-btn telegram">
                    ✈️ Telegram
                </a>
                <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode($twitter_message); ?>" target="_blank" class="share-btn twitter">
                    🐦 Twitter
                </a>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($referral_link); ?>" target="_blank" class="share-btn facebook">
                    📘 Facebook
                </a>
                <button class="share-btn copy" onclick="copyLink()">📋 Copy Link</button>
            </div>
        </div>
    </div>
    
    <!-- How It Works -->
    <div class="how-it-works">
        <div class="section-title">📖 How It Works</div>
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-title">Share Your Code</div>
                <div class="step-desc">Share your unique referral code with friends via WhatsApp, Telegram, or any social media</div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-title">Friend Joins & Works</div>
                <div class="step-desc">Your friend signs up using your code and completes their first task successfully</div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-title">You Earn Bonus!</div>
                <div class="step-desc">Once your friend completes their first task, you get ₹<?php echo number_format($referral_bonus, 0); ?> bonus in your wallet</div>
            </div>
        </div>
    </div>
    
    <!-- Referred Users -->
    <div class="card">
        <div class="card-title">
            <span>👥 Your Referrals</span>
            <span style="font-size:14px;color:#888"><?php echo $total_referrals; ?> total</span>
        </div>
        
        <?php if (empty($referred_users)): ?>
            <div class="empty-state">
                <div class="icon">👥</div>
                <h4>No Referrals Yet</h4>
                <p>Share your referral code to start earning bonus rewards!</p>
            </div>
        <?php else: ?>
            <div class="user-list">
                <?php foreach ($referred_users as $user): ?>
                    <div class="user-item">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user['referred_name'], 0, 1)); ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name"><?php echo escape($user['referred_name']); ?></div>
                            <div class="user-date">Joined: <?php echo date('d M Y', strtotime($user['joined_at'])); ?></div>
                        </div>
                        <?php if ($user['status'] === 'completed'): ?>
                            <span class="user-status completed">✓ Completed</span>
                            <span class="user-bonus">+₹<?php echo number_format($user['bonus_amount'], 0); ?></span>
                        <?php else: ?>
                            <span class="user-status pending">⏳ Pending</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Referral Earnings History -->
    <div class="card">
        <div class="card-title">
            <span>💰 Referral Earnings</span>
        </div>
        
        <?php if (empty($referral_transactions)): ?>
            <div class="empty-state">
                <div class="icon">💰</div>
                <h4>No Earnings Yet</h4>
                <p>Referral bonuses will appear here once your friends complete tasks</p>
            </div>
        <?php else: ?>
            <?php foreach ($referral_transactions as $txn): ?>
                <div class="transaction-item">
                    <div class="txn-icon">🎁</div>
                    <div class="txn-info">
                        <div class="txn-desc"><?php echo escape($txn['description']); ?></div>
                        <div class="txn-date"><?php echo date('d M Y, H:i', strtotime($txn['created_at'])); ?></div>
                    </div>
                    <div class="txn-amount">+₹<?php echo number_format($txn['amount'], 2); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Terms -->
    <div class="card" style="background:#f8f9fa">
        <div class="section-title">📋 Referral Terms & Conditions</div>
        <ul style="padding-left:20px;color:#666;font-size:13px;line-height:1.8">
            <li>Referral bonus of ₹<?php echo number_format($referral_bonus, 0); ?> is credited when your referred friend completes their first task</li>
            <li>The referred friend must use your referral code during registration</li>
            <li>Self-referrals or fake accounts are not allowed and will result in account suspension</li>
            <li>Bonus amount is subject to change without prior notice</li>
            <li>Admin reserves the right to modify or terminate the referral program at any time</li>
            <li>Referral earnings can be withdrawn as per normal withdrawal rules</li>
        </ul>
    </div>
</div>

<script>
// Copy referral code
function copyCode() {
    const code = document.getElementById('referralCode').innerText;
    copyToClipboard(code);
    showToast('✓ Referral code copied: ' + code);
}

// Copy referral link
function copyLink() {
    const link = document.getElementById('referralLink').value;
    copyToClipboard(link);
    showToast('✓ Referral link copied!');
}

// Copy to clipboard function
function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text);
    } else {
        // Fallback
        const input = document.createElement('textarea');
        input.value = text;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
    }
}

// Toast notification
function showToast(message) {
    // Remove existing toast
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();
    
    // Create toast
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.innerHTML = message;
    toast.style.cssText = `
        position: fixed;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%);
        background: #333;
        color: #fff;
        padding: 15px 30px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        z-index: 9999;
        animation: fadeInUp 0.3s ease;
    `;
    document.body.appendChild(toast);
    
    // Remove after 3 seconds
    setTimeout(() => {
        toast.style.animation = 'fadeOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Add animations
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeInUp {
        from { opacity: 0; transform: translate(-50%, 20px); }
        to { opacity: 1; transform: translate(-50%, 0); }
    }
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
`;
document.head.appendChild(style);

// Web Share API (for mobile)
if (navigator.share) {
    document.querySelectorAll('.share-btn.copy').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            try {
                await navigator.share({
                    title: '<?php echo APP_NAME; ?> Referral',
                    text: 'Join <?php echo APP_NAME; ?> using my referral code: <?php echo $referral_code; ?>',
                    url: '<?php echo $referral_link; ?>'
                });
            } catch (err) {
                copyLink();
            }
        });
    });
}
</script>
</body>
</html>
