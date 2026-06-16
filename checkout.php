<?php require_once 'includes/functions.php'; ?>
<?php requireLogin(); ?>
<?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') redirect(BASE_URL . '/admin/index.php'); ?>

<?php
$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT ci.id, ci.product_id, ci.quantity, ci.price, p.name, p.stock_quantity
                       FROM cart c
                       JOIN cart_items ci ON ci.cart_id = c.id
                       JOIN products p ON p.id = ci.product_id
                       WHERE c.user_id = ?");
$stmt->execute([$userId]);
$items = $stmt->fetchAll();

if (empty($items)) {
    redirect(BASE_URL . '/cart.php');
}

$total = 0;
foreach ($items as $item) {
    $total += $item['quantity'] * $item['price'];
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        foreach ($items as $item) {
            if ($item['quantity'] > $item['stock_quantity']) {
                throw new Exception("Insufficient stock for {$item['name']}.");
            }
        }

        $date = date('Ymd');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        $orderNumber = 'GOB-' . $date . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

        // Get or create address for the user
        $stmt = $pdo->prepare("SELECT id FROM addresses WHERE user_id = ? AND is_default = 1 LIMIT 1");
        $stmt->execute([$userId]);
        $address = $stmt->fetch();
        if ($address) {
            $addressId = $address['id'];
        } else {
            $stmt = $pdo->prepare("SELECT fullname, phone, address, country FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $u = $stmt->fetch();
            if (!$u) throw new Exception('User record not found.');
            $stmt = $pdo->prepare("INSERT INTO addresses (user_id, fullname, phone, address_line1, city, country, is_default) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$userId, $u['fullname'], $u['phone'], $u['address'], 'Hargeisa', $u['country']]);
            $addressId = $pdo->lastInsertId();
        }

        $stmt = $pdo->prepare("INSERT INTO orders (order_number, user_id, address_id, total_amount, grand_total, payment_method, order_status) VALUES (?, ?, ?, ?, ?, 'stripe', 'pending')");
        $stmt->execute([$orderNumber, $userId, $addressId, $total, $total]);
        $orderId = $pdo->lastInsertId();

        foreach ($items as $item) {
            $total_item = $item['quantity'] * $item['price'];
            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, total) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['price'], $total_item]);

            $stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
            $stmt->execute([$item['quantity'], $item['product_id']]);
            $pdo->prepare("UPDATE products SET status = IF(stock_quantity > 0, 'active', 'inactive') WHERE id = ?")->execute([$item['product_id']]);
        }

        $stmt = $pdo->prepare("INSERT INTO payments (order_id, user_id, payment_method, amount, status) VALUES (?, ?, 'stripe', ?, 'pending')");
        $stmt->execute([$orderId, $userId, $total]);

        // Commit the order BEFORE calling payment service — otherwise the service
        // (which has its own DB connection) won't see the uncommitted order.
        $pdo->commit();

        // Call payment service to create Stripe Checkout Session
        $stripeUrl = PAYMENT_SERVICE_URL . '/api/payment/create-checkout-session';
        $itemsPayload = [];
        foreach ($items as $it) {
            $imageUrl = '';
            $stmtImg = $pdo->prepare("SELECT main_image FROM products WHERE id = ?");
            $stmtImg->execute([$it['product_id']]);
            $prod = $stmtImg->fetch();
            if ($prod && $prod['main_image']) {
                $imageUrl = BASE_URL . '/uploads/' . $prod['main_image'];
            }
            $itemsPayload[] = [
                'id' => (int) $it['product_id'],
                'name' => $it['name'],
                'price' => (float) $it['price'],
                'quantity' => (int) $it['quantity'],
                'image' => $imageUrl,
            ];
        }
        $payload = json_encode(['items' => $itemsPayload, 'orderId' => (int) $orderId]);

        $ch = curl_init($stripeUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            logMessage('ERROR', "Stripe session creation failed for order {$orderId}: {$curlError} HTTP {$httpCode}");
            // Order is committed but Stripe failed — restore stock and delete the order
            $pdo->beginTransaction();
            foreach ($items as $it) {
                $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?")->execute([$it['quantity'], $it['product_id']]);
                $pdo->prepare("UPDATE products SET status = 'active' WHERE id = ?")->execute([$it['product_id']]);
            }
            $pdo->prepare("DELETE FROM payments WHERE order_id = ?")->execute([$orderId]);
            $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
            $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$orderId]);
            $pdo->commit();
            $error = 'Payment service unavailable. Please try again.';
        } else {
            $result = json_decode($response, true);
            if ($result['IsSuccess'] ?? false) {
                // Clear cart and redirect to Stripe
                $pdo->prepare("DELETE ci FROM cart_items ci JOIN cart c ON c.id = ci.cart_id WHERE c.user_id = ?")->execute([$userId]);
                logMessage('INFO', "Order placed: $orderNumber by user {$userId} via Stripe");
                redirect($result['url']);
            } else {
                // Order exists but Stripe rejected — restore stock and delete
                $pdo->beginTransaction();
                foreach ($items as $it) {
                    $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?")->execute([$it['quantity'], $it['product_id']]);
                    $pdo->prepare("UPDATE products SET status = 'active' WHERE id = ?")->execute([$it['product_id']]);
                }
                $pdo->prepare("DELETE FROM payments WHERE order_id = ?")->execute([$orderId]);
                $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
                $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$orderId]);
                $pdo->commit();
                logMessage('ERROR', "Stripe session error for order {$orderId}: " . ($result['error'] ?? 'unknown'));
                $error = 'Payment service error. Please try again.';
            }
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Gob Store</title>
    <script>try{let t=localStorage.getItem('gob_theme')||'dark';document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=9">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container py-4">
        <h2 class="page-title"><i class="bi bi-credit-card me-2 text-accent"></i>Checkout</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= escape($error) ?></div>
        <?php endif; ?>
        <div class="row">
            <div class="col-md-7">
                <form method="POST" id="checkoutForm">
                    <div class="card mb-3">
                        <div class="card-header"><i class="bi bi-receipt me-1"></i>Order Summary</div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <thead>
                                    <tr><th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><?= escape($item['name']) ?></td>
                                            <td><?= $item['quantity'] ?></td>
                                            <td>$<?= number_format($item['price'], 2) ?></td>
                                            <td>$<?= number_format($item['quantity'] * $item['price'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr><td colspan="3" class="text-end fw-bold text-secondary">Total:</td><td class="fw-bold text-accent" style="font-size: 1.2rem;">$<?= number_format($total, 2) ?></td></tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header"><i class="bi bi-wallet2 me-1"></i>Payment Method</div>
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3 p-3 rounded" style="background: rgba(99,91,255,0.08); border: 1px solid rgba(99,91,255,0.25);">
                                <i class="bi bi-credit-card-2-back fs-2" style="color:#635bff;"></i>
                                <div>
                                    <strong style="color:#635bff;">Pay with Card (Stripe)</strong>
                                    <p class="mb-0 text-muted" style="font-size:0.85rem;">Visa, Mastercard, and other cards accepted. You will be redirected to Stripe to complete payment securely.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert d-flex align-items-center gap-2 mb-3 rounded-3" data-no-dismiss style="background: rgba(255,215,0,0.08); border: 1px solid rgba(255,215,0,0.3); padding: 14px 18px;">
                        <i class="bi bi-exclamation-triangle-fill" style="color: var(--accent); font-size: 1.3rem; flex-shrink: 0;"></i>
                        <div style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.5;">Fadlan ogow: Marka dalabkaaga la dhigo, <strong style="color: var(--accent);">lama joojin karo lamana beddeli karo</strong>. Hubi in alaabtaada iyo faahfaahintaadu ay sax yihiin ka hor intaadan dalban.</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-3"><i class="bi bi-check2-circle me-2"></i>Place Order</button>
                </form>
            </div>
        </div>
    </div>

    <script src="<?= BASE_URL ?>/assets/js/checkout.js?v=2"></script>
    <?php include 'includes/footer.php'; ?>
