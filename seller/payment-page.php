<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['seller_id'])) {
    header('Location: index.php');
    exit;
}

$seller_id = $_SESSION['seller_id'];
$order_id = $_GET['order_id'] ?? '';

if (empty($order_id) || !isset($_SESSION['payment_order'])) {
    header('Location: orders.php?error=invalid_session');
    exit;
}

$payment_order = $_SESSION['payment_order'];
$request_id = $payment_order['request_id'];

$stmt = $pdo->prepare("SELECT rr.*, s.name, s.email, s.mobile FROM review_requests rr JOIN sellers s ON rr.seller_id = s.id WHERE rr.id = ? AND rr.seller_id = ?");
$stmt->execute([$request_id, $seller_id]);
$request = $stmt->fetch();

if (!$request) {
    header('Location: orders.php?error=request_not_found');
    exit;
}

$razorpay_key = getSetting('razorpay_key_id', '');
$site_name = getSetting('site_name', 'ReviewFlow');

require_once __DIR__ . '/includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Complete Payment</h4>
                </div>
                <div class="card-body">
                    <h5 class="text-center"><?= htmlspecialchars($request['product_name']) ?></h5>
                    <table class="table mt-3">
                        <tr><td>Product Price</td><td class="text-end">₹<?= number_format($request['product_price'], 2) ?></td></tr>
                        <tr><td>Commission</td><td class="text-end">₹<?= number_format($request['admin_commission'] * $request['reviews_needed'], 2) ?></td></tr>
                        <tr><td>GST (18%)</td><td class="text-end">₹<?= number_format($request['gst_amount'], 2) ?></td></tr>
                        <tr class="table-primary"><th>Total</th><th class="text-end">₹<?= number_format($request['grand_total'], 2) ?></th></tr>
                    </table>
                    <button id="pay-btn" class="btn btn-success btn-lg w-100">Pay ₹<?= number_format($request['grand_total'], 2) ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.getElementById('pay-btn').onclick = function(e) {
    var options = {
        "key": "<?= $razorpay_key ?>",
        "amount": "<?= (int)($request['grand_total'] * 100) ?>",
        "currency": "INR",
        "name": "<?= $site_name ?>",
        "description": "Review Request #<?= $request_id ?>",
        "order_id": "<?= $order_id ?>",
        "handler": function (response) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'payment-callback.php?action=callback';
            ['razorpay_payment_id','razorpay_order_id','razorpay_signature'].forEach(function(k) {
                var inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = k; inp.value = response[k];
                form.appendChild(inp);
            });
            var g = document.createElement('input');
            g.type='hidden'; g.name='gateway'; g.value='razorpay';
            form.appendChild(g);
            document.body.appendChild(form);
            form.submit();
        },
        "prefill": {
            "name": "<?= htmlspecialchars($request['name']) ?>",
            "email": "<?= htmlspecialchars($request['email']) ?>",
            "contact": "<?= htmlspecialchars($request['mobile']) ?>"
        },
        "theme": {"color": "#6366f1"}
    };
    var rzp = new Razorpay(options);
    rzp.open();
};
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
