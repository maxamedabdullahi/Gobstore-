<?php require_once 'includes/functions.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Gob Store</title>
    <script>try{let t=localStorage.getItem('gob_theme')||'dark';document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=9">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-4">
        <h2 class="page-title"><i class="bi bi-box-seam me-2" style="color: var(--accent);"></i>Products</h2>

        <form method="GET" class="row g-2 mb-4 p-3 rounded filter-bar">
            <div class="col-md-10">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?= escape($_GET['search'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Filter</button>
            </div>
        </form>

        <div class="row">
            <?php
            $query = "SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.status = 'active' AND p.stock_quantity > 0";
            $params = [];

            if (!empty($_GET['search'])) {
                $query .= " AND p.name LIKE ?";
                $params[] = '%' . $_GET['search'] . '%';
            }
            if (!empty($_GET['category'])) {
                $catId = (int)$_GET['category'];
                $check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE id = ? AND parent_id IS NULL");
                $check->execute([$catId]);
                if ($check->fetchColumn() > 0) {
                    $query .= " AND p.category_id IN (SELECT id FROM categories WHERE parent_id = ? OR id = ?)";
                    $params[] = $catId;
                    $params[] = $catId;
                } else {
                    $query .= " AND p.category_id = ?";
                    $params[] = $catId;
                }
            }
            if (!empty($_GET['subcategory_id'])) {
                $subId = (int)$_GET['subcategory_id'];
                $query .= " AND p.category_id = ?";
                $params[] = $subId;
            }
            $query .= " ORDER BY p.created_at DESC";

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $products = $stmt->fetchAll();

            if ($products):
                foreach ($products as $product):
            ?>
                <div class="col-md-3 col-6 mb-4">
                    <div class="card product-card h-100">
                        <img src="<?= $product['main_image'] ? BASE_URL . '/uploads/' . $product['main_image'] : 'https://placehold.co/300x300/1a3a1a/ffd700?text=Gob' ?>" class="card-img-top" alt="<?= escape($product['name']) ?>">
                        <div class="card-body d-flex flex-column">
                            <h6 class="card-title"><?= escape($product['name']) ?></h6>
                            <div class="mt-auto">
                                <p class="price-tag mb-2">$<?= number_format($product['price'], 2) ?></p>
                                <a href="product-details.php?id=<?= $product['id'] ?>" class="btn btn-outline-accent btn-sm w-100">View Details</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php
                endforeach;
            else:
            ?>
                <div class="text-center py-5 empty-state">
                    <i class="bi bi-emoji-frown empty-state-icon" style="font-size: 3rem;"></i>
                    <p class="mt-2 text-muted">No products found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
