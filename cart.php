<?php require_once 'includes/functions.php'; ?>
<?php requireLogin(); ?>

<?php
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_qty'])) {
    $itemId = (int)($_POST['item_id'] ?? 0);
    $qty = max(1, (int)($_POST['quantity'] ?? 1));
    $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND cart_id IN (SELECT id FROM cart WHERE user_id = ?)");
    $stmt->execute([$qty, $itemId, $userId]);
    redirect(BASE_URL . '/cart.php');
}

if (isset($_GET['remove'])) {
    $itemId = (int)$_GET['remove'];
    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND cart_id IN (SELECT id FROM cart WHERE user_id = ?)");
    $stmt->execute([$itemId, $userId]);
    redirect(BASE_URL . '/cart.php');
}

$stmt = $pdo->prepare("SELECT ci.id, ci.product_id, ci.quantity, ci.price, p.name, p.main_image, p.stock_quantity
                       FROM cart c
                       JOIN cart_items ci ON ci.cart_id = c.id
                       JOIN products p ON p.id = ci.product_id
                       WHERE c.user_id = ?
                       ORDER BY ci.id");
$stmt->execute([$userId]);
$items = $stmt->fetchAll();

$total = 0;
foreach ($items as $item) {
    $total += $item['quantity'] * $item['price'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Gob Store</title>
    <script>try{let t=localStorage.getItem('gob_theme')||'dark';document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=9">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container py-4">
        <h2 class="page-title"><i class="bi bi-cart4 me-2 text-accent"></i>Shopping Cart</h2>
        <?php if (empty($items)): ?>
            <div class="text-center py-5">
                <i class="bi bi-cart-x empty-state-icon" style="font-size: 4rem; color: var(--text-muted);"></i>
                <p class="mt-3 fs-5" style="color: var(--text-muted);">Your cart is empty.</p>
                <a href="products.php" class="btn btn-primary"><i class="bi bi-arrow-left me-1"></i>Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?= $item['main_image'] ? BASE_URL . '/uploads/' . $item['main_image'] : 'https://placehold.co/80x80/1a3a1a/ffd700?text=G' ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;" class="me-3">
                                        <a href="product-details.php?id=<?= $item['product_id'] ?>" class="text-decoration-none"><?= escape($item['name']) ?></a>
                                    </div>
                                </td>
                                <td class="price-tag" style="font-size: 1rem;">$<?= number_format($item['price'], 2) ?></td>
                                <td>
                                    <form method="POST" class="d-flex align-items-center gap-1">
                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                        <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['stock_quantity'] ?>" class="form-control" style="width: 70px;">
                                        <button type="submit" name="update_qty" class="btn btn-sm btn-outline-accent">Update</button>
                                    </form>
                                </td>
                                <td class="fw-bold">$<?= number_format($item['quantity'] * $item['price'], 2) ?></td>
                                <td>
                                    <a href="cart.php?remove=<?= $item['id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Remove this item?"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end fw-bold text-secondary">Total:</td>
                            <td class="fw-bold text-accent" style="font-size: 1.3rem;">$<?= number_format($total, 2) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="d-flex justify-content-between mt-3">
                <a href="products.php" class="btn btn-outline-green"><i class="bi bi-arrow-left me-1"></i>Continue Shopping</a>
                <a href="checkout.php" class="btn btn-primary"><i class="bi bi-credit-card me-1"></i>Proceed to Checkout</a>
            </div>
        <?php endif; ?>
    </div>
    <?php include 'includes/footer.php'; ?>
