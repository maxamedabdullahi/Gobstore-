<?php require_once 'includes/functions.php'; ?>
<?php requireLogin(); ?>

<?php
$sessionId = $_GET['session_id'] ?? '';
$orderId = $_GET['order_id'] ?? '';

$paymentVerified = false;
$paymentStatus = 'unknown';
$verificationError = '';
$order = null;

if ($sessionId && $orderId) {
    // Call payment service to verify payment
    $verifyUrl = PAYMENT_SERVICE_URL . '/api/payment/verify/' . urlencode($sessionId);
    $ch = curl_init($verifyUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        $verificationError = 'Could not verify payment. Please check your orders page for status.';
    } elseif ($httpCode === 200) {
        $result = json_decode($response, true);
        $paymentVerified = ($result['isPaid'] ?? false);
        $paymentStatus = $result['paymentStatus'] ?? 'unknown';
    } else {
        $verificationError = 'Payment verification service unavailable.';
    }

    // Load order details from DB
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([(int)$orderId, $_SESSION['user_id']]);
    $order = $stmt->fetch();

    // Load order items
    if ($order) {
        $stmt = $pdo->prepare("
            SELECT oi.*, p.name, p.main_image
            FROM order_items oi
            JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([(int)$orderId]);
        $orderItems = $stmt->fetchAll();
    }
} else {
    $verificationError = 'Missing payment information.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order <?= $paymentVerified ? 'Confirmed' : 'Pending' ?> - Gob Store</title>
    <script>try{let t=localStorage.getItem('gob_theme')||'dark';document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=9">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <?php if ($paymentVerified): ?>
                    <div class="text-center mb-4">
                        <div class="display-1 text-success mb-3"><i class="bi bi-check-circle-fill"></i></div>
                        <h2 class="fw-bold">Payment Successful!</h2>
                        <p class="text-muted">Your order has been placed and payment confirmed.</p>
                    </div>
                <?php elseif ($paymentStatus === 'pending'): ?>
                    <div class="text-center mb-4">
                        <div class="display-1 text-warning mb-3"><i class="bi bi-hourglass-split"></i></div>
                        <h2 class="fw-bold">Payment Pending</h2>
                        <p class="text-muted">Your payment is being processed. This may take a few moments.</p>
                    </div>
                <?php elseif ($paymentStatus === 'failed'): ?>
                    <div class="text-center mb-4">
                        <div class="display-1 text-danger mb-3"><i class="bi bi-x-circle-fill"></i></div>
                        <h2 class="fw-bold">Payment Failed</h2>
                        <p class="text-muted">The payment was not successful. Please try again.</p>
                    </div>
                <?php else: ?>
                    <div class="text-center mb-4">
                        <div class="display-1 text-secondary mb-3"><i class="bi bi-question-circle-fill"></i></div>
                        <h2 class="fw-bold">Order Status Unknown</h2>
                        <?php if ($verificationError): ?>
                            <p class="text-danger"><?= escape($verificationError) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($order): ?>
                    <div class="card mb-3">
                        <div class="card-header"><i class="bi bi-receipt me-1"></i>Order #<?= escape($order['order_number']) ?></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <span class="badge fs-6 <?= $paymentVerified ? 'bg-success' : ($paymentStatus === 'failed' ? 'bg-danger' : 'bg-warning text-dark') ?>">
                                    <?= $paymentVerified ? 'Paid' : escape(ucfirst($paymentStatus)) ?>
                                </span>
                            </div>
                            <table class="table table-sm">
                                <thead>
                                    <tr><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderItems ?? [] as $item): ?>
                                        <tr>
                                            <td><?= escape($item['name']) ?></td>
                                            <td><?= (int)$item['quantity'] ?></td>
                                            <td>$<?= number_format((float)$item['price'], 2) ?></td>
                                            <td>$<?= number_format((float)$item['total'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="text-end fw-bold text-secondary">Total:</td>
                                        <td class="fw-bold text-accent">$<?= number_format((float)$order['grand_total'], 2) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="d-flex gap-2 justify-content-center">
                    <a href="my-orders.php" class="btn btn-primary"><i class="bi bi-box me-1"></i>View My Orders</a>
                    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-house me-1"></i>Continue Shopping</a>
                </div>
            </div>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
    <script>
    // Poll payment status if not yet confirmed (webhook may be delayed)
    <?php if ($sessionId && !$paymentVerified && !$verificationError): ?>
    (function() {
        var sessionId = '<?= escape($sessionId) ?>';
        var interval = setInterval(function() {
            fetch('<?= PAYMENT_SERVICE_URL ?>/api/payment/verify/' + encodeURIComponent(sessionId))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.isPaid) {
                        clearInterval(interval);
                        location.reload();
                    }
                })
                .catch(function() { /* retry on next tick */ });
        }, 5000);
    })();
    <?php endif; ?>
    </script>
</body>
</html>
