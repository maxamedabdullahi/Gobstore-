<?php
require_once 'includes/functions.php';
requireLogin();

$stmt = $pdo->prepare("SELECT fullname, email, created_at FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$orders = $pdo->prepare("SELECT id, total_amount, order_status, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$orders->execute([$_SESSION['user_id']]);
$recentOrders = $orders->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gob Store</title>
    <script>try{let t=localStorage.getItem('gob_theme')||'dark';document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=9">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container py-4">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="card border-0 shadow-sm" style="background: var(--card-bg); border-radius: 16px;">
                    <div class="card-body text-center p-4">
                        <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; margin: 0 auto 16px;">
                            <?= strtoupper(substr($user['fullname'], 0, 1)) ?>
                        </div>
                        <h5 style="color: var(--text-heading); font-weight: 700;"><?= escape($user['fullname']) ?></h5>
                        <p style="color: var(--text-muted);"><i class="bi bi-envelope me-1"></i><?= escape($user['email']) ?></p>
                        <p style="color: var(--text-muted); font-size: 0.85rem;"><i class="bi bi-calendar me-1"></i>Member since <?= date('M Y', strtotime($user['created_at'])) ?></p>
                        <hr style="border-color: var(--border-color);">
                        <a href="profile.php" class="btn btn-outline-primary w-100 mb-2"><i class="bi bi-person-gear me-2"></i>Profile</a>
                        <a href="my-orders.php" class="btn btn-outline-primary w-100 mb-2"><i class="bi bi-box me-2"></i>My Orders</a>
                        <a href="logout.php" class="btn btn-outline-danger w-100"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-8 mb-4">
                <div class="card border-0 shadow-sm" style="background: var(--card-bg); border-radius: 16px;">
                    <div class="card-header" style="background: transparent; border-bottom: 1px solid var(--border-color); padding: 20px 24px;">
                        <h5 style="color: var(--text-heading); font-weight: 700; margin: 0;"><i class="bi bi-clock-history me-2" style="color: var(--accent);"></i>Recent Orders</h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if (empty($recentOrders)): ?>
                            <div class="text-center py-4" style="color: var(--text-muted);">
                                <i class="bi bi-inbox" style="font-size: 3rem; display: block; margin-bottom: 12px;"></i>
                                <p>No orders yet.</p>
                                <a href="products.php" class="btn btn-primary"><i class="bi bi-bag me-2"></i>Start Shopping</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle" style="margin: 0;">
                                    <thead>
                                        <tr>
                                            <th style="color: var(--text-muted); font-size: 0.85rem;">Order #</th>
                                            <th style="color: var(--text-muted); font-size: 0.85rem;">Date</th>
                                            <th style="color: var(--text-muted); font-size: 0.85rem;">Total</th>
                                            <th style="color: var(--text-muted); font-size: 0.85rem;">Status</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentOrders as $o): ?>
                                        <tr>
                                            <td style="color: var(--text-primary); font-weight: 600;">#<?= $o['id'] ?></td>
                                            <td style="color: var(--text-secondary);"><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
                                            <td style="color: var(--text-primary); font-weight: 600;">$<?= number_format($o['total_amount'], 2) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $o['order_status'] === 'delivered' ? 'success' : ($o['order_status'] === 'cancelled' ? 'secondary' : ($o['order_status'] === 'pending' ? 'warning' : 'info')) ?>">
                                                    <?= ucfirst($o['order_status']) ?>
                                                </span>
                                            </td>
                                            <td><a href="my-orders.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
