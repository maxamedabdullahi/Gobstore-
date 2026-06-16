<?php require_once 'includes/functions.php'; ?>
<?php requireLogin(); ?>

<?php
$success = isset($_GET['success']) ? 'Order placed successfully!' : '';

$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? AND order_status != 'cancelled' ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Gob Store</title>
    <script>try{let t=localStorage.getItem('gob_theme')||'dark';document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=9">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container py-4">
        <h2 class="page-title"><i class="bi bi-box-seam me-2 text-accent"></i>My Orders</h2>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i><?= escape($success) ?>
                <a href="products.php" class="float-end" style="color: var(--alert-success-text);">Continue Shopping &rarr;</a>
            </div>
        <?php endif; ?>
        <?php if (empty($orders)): ?>
            <div class="text-center py-5">
                <i class="bi bi-box empty-state-icon" style="font-size: 4rem;"></i>
                <p class="mt-3 fs-5" style="color: var(--text-muted);">No orders yet.</p>
                <a href="products.php" class="btn btn-primary"><i class="bi bi-shop me-1"></i>Start Shopping</a>
            </div>
        <?php else: ?>
            <div class="orders-list">
                <?php foreach ($orders as $order):
                    $stmt = $pdo->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?");
                    $stmt->execute([$order['id']]);
                    $orderItems = $stmt->fetchAll();
                    $itemCount = array_sum(array_column($orderItems, 'quantity'));
                ?>
                <div class="order-card">
                    <div class="order-card-top">
                        <div class="order-card-left">
                            <div class="order-number"><?= escape($order['order_number']) ?></div>
                            <div class="order-date"><i class="bi bi-calendar3 me-1"></i><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></div>
                        </div>
                        <div class="order-card-center">
                            <div class="order-total">$<?= number_format($order['total_amount'], 2) ?></div>
                            <span class="order-badge payment-badge"><?= strtoupper($order['payment_method']) ?></span>
                            <?php if (isset($order['payment_status'])): ?>
                                <span class="order-badge status-badge payment-status-<?= $order['payment_status'] ?>"><?= ucfirst($order['payment_status']) ?></span>
                            <?php endif; ?>
                            <span class="order-badge status-badge status-<?= $order['order_status'] ?>"><?= ucfirst($order['order_status']) ?></span>
                        </div>
                        <div class="order-card-right">
                            <button class="btn-view-toggle" onclick="toggleOrderDetails(this)"><i class="bi bi-chevron-down"></i> View Details</button>
                        </div>
                    </div>
                    <div class="order-card-items">
                        <div class="order-items-header">
                            <span class="items-count"><?= count($orderItems) ?> item<?= count($orderItems) !== 1 ? 's' : '' ?> &middot; <?= $itemCount ?> unit<?= $itemCount !== 1 ? 's' : '' ?></span>
                        </div>
                        <div class="order-items-grid">
                            <div class="order-item order-item-head">
                                <span class="item-col product">Product</span>
                                <span class="item-col qty">Qty</span>
                                <span class="item-col price">Price</span>
                                <span class="item-col subtotal">Subtotal</span>
                            </div>
                            <?php foreach ($orderItems as $oi): ?>
                            <div class="order-item">
                                <span class="item-col product"><?= escape($oi['name']) ?></span>
                                <span class="item-col qty"><?= $oi['quantity'] ?></span>
                                <span class="item-col price">$<?= number_format($oi['price'], 2) ?></span>
                                <span class="item-col subtotal">$<?= number_format($oi['total'], 2) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="order-card-footer">
                            <span class="order-footer-total">Total <span>$<?= number_format($order['total_amount'], 2) ?></span></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php include 'includes/footer.php'; ?>
