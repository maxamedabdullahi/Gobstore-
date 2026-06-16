<?php require_once 'includes/functions.php'; ?>
<?php
$id = (int)($_GET['id'] ?? 0);

// ---------- JSON endpoint for Quick View ----------
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.id = ? AND p.status = 'active'");
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    if (!$p) {
        header('HTTP/1.0 404 Not Found');
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    $img = $p['main_image'] ? BASE_URL . '/uploads/' . $p['main_image'] : 'https://placehold.co/500x500/1a3a1a/ffd700?text=Gob';

    // Review stats
    $revStmt = $pdo->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_reviews FROM reviews WHERE product_id = ? AND is_approved = 1");
    $revStmt->execute([$id]);
    $revData = $revStmt->fetch();

    // User review status
    $hasReviewed = false;
    $isLoggedIn = isLoggedIn();
    $isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    if ($isLoggedIn && !$isAdmin) {
        $chk = $pdo->prepare("SELECT 1 FROM reviews WHERE product_id = ? AND user_id = ?");
        $chk->execute([$id, $_SESSION['user_id']]);
        $hasReviewed = (bool)$chk->fetchColumn();
    }

    header('Content-Type: application/json');
    echo json_encode([
        'id'            => (int)$p['id'],
        'name'          => $p['name'],
        'price'         => number_format($p['price'], 2),
        'description'   => nl2br(escape($p['description'])),
        'image'         => $img,
        'sizes'         => $p['sizes'] ? explode(',', $p['sizes']) : [],
        'stock'         => (int)$p['stock_quantity'],
        'is_vip'        => (int)$p['is_vip'],
        'category'      => $p['category_name'],
        'url'           => BASE_URL . '/product-details.php?id=' . $p['id'],
        'avg_rating'    => $revData['avg_rating'] ? round((float)$revData['avg_rating'], 1) : 0,
        'total_reviews' => (int)$revData['total_reviews'],
        'is_logged_in'  => $isLoggedIn,
        'is_admin'      => $isAdmin,
        'has_reviewed'  => $hasReviewed,
    ]);
    exit;
}

$stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.id = ? AND p.status = 'active'");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    header('HTTP/1.0 404 Not Found');
    echo '<h1 class="text-center mt-5">Product not found</h1>';
    exit;
}

$message = '';
$reviewMessage = '';

// ---------- Handle review submission ----------
if (isset($_POST['submit_review'])) {
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/login.php');
    }
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        $reviewMessage = 'Admins cannot submit reviews.';
    } else {
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    if ($rating >= 1 && $rating <= 5 && $comment !== '') {
        $stmt = $pdo->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO reviews (product_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$id, $_SESSION['user_id'], $rating, $comment]);
            $reviewMessage = 'Review submitted! It will appear after approval.';
        } else {
            $reviewMessage = 'You have already reviewed this product.';
        }
    } else {
        $reviewMessage = 'Please select a rating and write a comment.';
    }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['submit_review'])) {
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/login.php');
    }
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        redirect(BASE_URL . '/admin/index.php');
    }
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));

    $stmt = $pdo->prepare("SELECT id FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cart = $stmt->fetch();

    if (!$cart) {
        $stmt = $pdo->prepare("INSERT INTO cart (user_id) VALUES (?)");
        $stmt->execute([$_SESSION['user_id']]);
        $cartId = $pdo->lastInsertId();
    } else {
        $cartId = $cart['id'];
    }

    $stmt = $pdo->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?");
    $stmt->execute([$cartId, $product['id']]);
    $existing = $stmt->fetch();

    if ($existing) {
        $newQty = $existing['quantity'] + $quantity;
        $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
        $stmt->execute([$newQty, $existing['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        $stmt->execute([$cartId, $product['id'], $quantity, $product['price']]);
    }

    $message = 'Product added to cart!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($product['name']) ?> - Gob Store</title>
    <script>try{let t=localStorage.getItem('gob_theme')||'dark';document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=13">
<style>
.size-options{display:inline-flex;gap:6px;flex-wrap:wrap}
.size-pill{border:2px solid var(--border-color,#333);background:transparent;color:var(--text-color,#ddd);padding:4px 14px;border-radius:20px;font-size:0.85rem;cursor:pointer;transition:all .2s;outline:none}
.size-pill:hover{border-color:var(--accent,#ffc107)}
.size-pill.selected{background:var(--accent,#ffc107);border-color:var(--accent,#ffc107);color:#000;font-weight:600}
</style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container py-4">
        <?php if ($message): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i><?= escape($message) ?></div>
        <?php endif; ?>
        <div class="row g-4">
            <div class="col-md-5">
                <img src="<?= $product['main_image'] ? BASE_URL . '/uploads/' . $product['main_image'] : 'https://placehold.co/500x500/1a3a1a/ffd700?text=Gob' ?>" class="img-fluid product-img-lg" alt="<?= escape($product['name']) ?>">
            </div>
            <div class="col-md-7">
                <h2 class="fw-bold"><?= escape($product['name']) ?></h2>
                <p class="price-tag" style="font-size: 2rem;">$<?= number_format($product['price'], 2) ?></p>
                <p class="mt-3"><?= nl2br(escape($product['description'])) ?></p>
                <?php if (!empty($product['sizes'])): ?>
                <p>
                    <strong class="text-secondary">Sizes:</strong>
                    <span class="size-options">
                        <?php foreach (explode(',', $product['sizes']) as $size): $s = trim($size); ?>
                            <button type="button" class="size-pill" data-value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></button>
                        <?php endforeach; ?>
                    </span>
                </p>
                <?php endif; ?>
                <p>
                    <strong class="text-secondary">Stock:</strong>
                    <?php if ($product['stock_quantity'] > 0): ?>
                        <span class="badge bg-green"><?= $product['stock_quantity'] ?> available</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Out of Stock</span>
                    <?php endif; ?>
                </p>
                <?php if ($product['stock_quantity'] > 0): ?>
                    <form method="POST" class="row g-2 mt-4">
                        <?php if (!empty($product['sizes'])): ?>
                        <input type="hidden" name="size" id="selectedSize" value="">
                        <?php endif; ?>
                        <div class="col-auto">
                            <input type="number" name="quantity" value="1" min="1" max="<?= $product['stock_quantity'] ?>" class="form-control" style="width: 90px; text-align: center;">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-cart-plus me-1"></i>Add to Cart</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============================================================
         CUSTOMER REVIEWS
         ============================================================ -->
    <?php
    $stmt = $pdo->prepare("SELECT r.*, u.fullname FROM reviews r JOIN users u ON u.id = r.user_id WHERE r.product_id = ? AND r.is_approved = 1 ORDER BY r.created_at DESC");
    $stmt->execute([$id]);
    $reviews = $stmt->fetchAll();

    $avgRating = 0;
    $ratingCounts = [1=>0,2=>0,3=>0,4=>0,5=>0];
    foreach ($reviews as $rv) {
        $avgRating += $rv['rating'];
        $ratingCounts[(int)$rv['rating']]++;
    }
    $totalReviews = count($reviews);
    if ($totalReviews > 0) $avgRating /= $totalReviews;

    $userReview = null;
    if (isLoggedIn()) {
        $stmt = $pdo->prepare("SELECT * FROM reviews WHERE product_id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        $userReview = $stmt->fetch();
    }
    ?>
    <?php if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin'): ?>
    <div class="gob-reviews mt-5">
        <div class="gob-reviews-header">
            <h3><i class="bi bi-star-fill"></i> Customer Reviews</h3>
            <?php if ($totalReviews > 0): ?>
            <div class="gob-reviews-summary">
                <div class="gob-reviews-avg">
                    <span class="gob-reviews-avg-num"><?= number_format($avgRating, 1) ?></span>
                    <div class="gob-stars" data-rating="<?= round($avgRating) ?>">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="bi bi-star<?= $i <= round($avgRating) ? '-fill' : '' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <span class="gob-reviews-count"><?= $totalReviews ?> review<?= $totalReviews !== 1 ? 's' : '' ?></span>
                </div>
                <div class="gob-reviews-bars">
                    <?php for ($i = 5; $i >= 1; $i--):
                        $pct = $totalReviews > 0 ? round($ratingCounts[$i] / $totalReviews * 100) : 0;
                    ?>
                    <div class="gob-reviews-bar-row">
                        <span class="gob-reviews-bar-label"><?= $i ?> <i class="bi bi-star-fill"></i></span>
                        <div class="gob-reviews-bar-track">
                            <div class="gob-reviews-bar-fill" style="width:<?= $pct ?>%"></div>
                        </div>
                        <span class="gob-reviews-bar-pct"><?= $pct ?>%</span>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($reviewMessage): ?>
        <div class="alert alert-info mt-3"><?= escape($reviewMessage) ?></div>
        <?php endif; ?>

        <!-- Review Form -->
        <?php if (isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
        <div class="gob-review-form-wrap gob-review-admin">
            <p><i class="bi bi-shield-lock"></i> Admins cannot submit reviews. <a href="<?= BASE_URL ?>/index.php" style="color:var(--accent)">Browse as customer</a> to leave a review.</p>
        </div>
        <?php elseif (isLoggedIn() && !$userReview): ?>
        <div class="gob-review-form-wrap">
            <h5>Write a Review</h5>
            <form method="POST" class="gob-review-form">
                <div class="gob-star-input" id="gobStarInput">
                    <i class="bi bi-star" data-val="1"></i>
                    <i class="bi bi-star" data-val="2"></i>
                    <i class="bi bi-star" data-val="3"></i>
                    <i class="bi bi-star" data-val="4"></i>
                    <i class="bi bi-star" data-val="5"></i>
                </div>
                <input type="hidden" name="rating" id="gobRatingVal" value="0">
                <textarea name="comment" class="form-control gob-review-textarea" placeholder="Share your thoughts about this product..." rows="3" required></textarea>
                <button type="submit" name="submit_review" class="btn btn-primary mt-2"><i class="bi bi-send me-1"></i>Submit Review</button>
            </form>
        </div>
        <?php elseif (isLoggedIn() && $userReview): ?>
        <div class="gob-review-form-wrap">
            <div class="gob-review-thanks">
                <i class="bi bi-check2-circle"></i> You reviewed this product
                <div class="gob-stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="bi bi-star<?= $i <= (int)$userReview['rating'] ? '-fill' : '' ?>"></i>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="gob-review-form-wrap">
            <p class="gob-review-login"><a href="<?= BASE_URL ?>/login.php">Login</a> to write a review.</p>
        </div>
        <?php endif; ?>

        <!-- Reviews List -->
        <?php if (!empty($reviews)): ?>
        <div class="gob-reviews-list">
            <?php foreach ($reviews as $rv): ?>
            <div class="gob-review-card">
                <div class="gob-review-card-top">
                    <div class="gob-review-avatar"><?= mb_strtoupper(mb_substr($rv['fullname'], 0, 1)) ?></div>
                    <div>
                        <div class="gob-review-author"><?= escape($rv['fullname']) ?></div>
                        <div class="gob-stars gob-stars-sm">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi bi-star<?= $i <= (int)$rv['rating'] ? '-fill' : '' ?>"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <span class="gob-review-date"><?= date('M d, Y', strtotime($rv['created_at'])) ?></span>
                </div>
                <p class="gob-review-comment"><?= nl2br(escape($rv['comment'])) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ============================================================
         RELATED PRODUCTS
         ============================================================ -->
    <?php
    // ---------- Fallback: same-category latest ----------
    $fallbackStmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name
        FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE p.category_id = ?
          AND p.id != ?
          AND p.status = 'active'
          AND p.stock_quantity > 0
        ORDER BY p.is_featured DESC, p.is_vip DESC, p.created_at DESC
        LIMIT 8
    ");
    $fallbackStmt->execute([$product['category_id'], $product['id']]);
    $related = $fallbackStmt->fetchAll();

    // ---------- Primary: scored related products ----------
    $nameWords = preg_split('/\s+/', $product['name']);
    $stopWords = ['the', 'a', 'an', 'and', 'or', 'in', 'on', 'at', 'to', 'for',
                  'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been',
                  'has', 'have', 'had', 'not', 'but', 'its', 'all', 'each',
                  'that', 'this', 'from', 'they', 'you', 'we', 'our', 'new',
                  'men', 'women', 'unisex'];
    $keywords = [];
    foreach ($nameWords as $w) {
        $w = trim(preg_replace('/[^a-zA-Z0-9]/', '', $w));
        if (mb_strlen($w) >= 3 && !in_array(mb_strtolower($w), $stopWords)) {
            $keywords[] = '+' . $w . '*';
        }
    }

    if (!empty($keywords)) {
        $boolKeyword = implode(' ', $keywords);
        $minPrice = $product['price'] * 0.8;
        $maxPrice = $product['price'] * 1.2;

        try {
            $scoredStmt = $pdo->prepare("
                SELECT p.*, c.name AS category_name,
                    (CASE WHEN p.category_id = :cat1 THEN 50 ELSE 0 END
                     + CASE WHEN MATCH(p.name) AGAINST(:kw1 IN BOOLEAN MODE) THEN 20 ELSE 0 END
                     + CASE WHEN p.price BETWEEN :min1 AND :max1 THEN 10 ELSE 0 END
                     + CASE WHEN p.is_featured = 1 THEN 5 ELSE 0 END
                     + CASE WHEN p.is_vip = 1 THEN 5 ELSE 0 END
                    ) AS score
                FROM products p
                JOIN categories c ON c.id = p.category_id
                WHERE p.id != :cur1
                  AND p.status = 'active'
                  AND p.stock_quantity > 0
                  AND (p.category_id = :cat2 OR MATCH(p.name) AGAINST(:kw2 IN BOOLEAN MODE) OR p.price BETWEEN :min2 AND :max2)
                ORDER BY score DESC, p.views DESC, p.created_at DESC
                LIMIT 8
            ");
            $scoredStmt->execute([
                ':cat1' => $product['category_id'],
                ':kw1'  => $boolKeyword,
                ':min1' => $minPrice,
                ':max1' => $maxPrice,
                ':cur1' => $product['id'],
                ':cat2' => $product['category_id'],
                ':kw2'  => $boolKeyword,
                ':min2' => $minPrice,
                ':max2' => $maxPrice,
            ]);
            $scored = $scoredStmt->fetchAll();

            if (count($scored) > 0) {
                $related = $scored;
            }
        } catch (PDOException $e) {
            // FULLTEXT may fail for short/empty keywords; fallback is kept
        }
    }
    ?>

    <?php if (!empty($related)): ?>
    <div class="related-products mt-5">
        <div class="related-title-wrap">
            <h3 class="related-title"><i class="bi bi-arrow-repeat"></i> You May Also Like</h3>
        </div>
        <div class="row g-3">
            <?php foreach ($related as $rp):
                $rpImg = $rp['main_image'] ? BASE_URL . '/uploads/' . $rp['main_image'] : 'https://placehold.co/400x400/1a3a1a/ffd700?text=Gob';
            ?>
            <div class="col-6 col-md-3">
                <div class="related-card" data-product-id="<?= $rp['id'] ?>">
                    <a href="product-details.php?id=<?= $rp['id'] ?>" class="related-card-img-wrap">
                        <img src="<?= $rpImg ?>" alt="<?= escape($rp['name']) ?>" loading="lazy">
                        <?php if (!empty($rp['is_vip'])): ?>
                        <span class="related-badge related-badge-vip">VIP</span>
                        <?php endif; ?>
                        <?php if (!empty($rp['is_sale']) || !empty($rp['sale_percentage'])): ?>
                        <span class="related-badge related-badge-sale">-<?= (int)$rp['sale_percentage'] ?>%</span>
                        <?php endif; ?>
                        <div class="related-card-actions">
                            <button type="button" class="related-quick-view" data-id="<?= $rp['id'] ?>" title="View Details">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </a>
                    <div class="related-card-body">
                        <a href="product-details.php?id=<?= $rp['id'] ?>" class="related-card-name"><?= escape($rp['name']) ?></a>
                        <div class="related-card-price">$<?= number_format($rp['price'], 2) ?></div>
                        <div class="related-card-buttons">
                            <?php if ($rp['stock_quantity'] > 0): ?>
                            <form method="POST" action="product-details.php?id=<?= $rp['id'] ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="related-add-btn"><i class="bi bi-cart-plus"></i> Add to Cart</button>
                            </form>
                            <?php else: ?>
                            <span class="related-out-of-stock">Out of Stock</span>
                            <?php endif; ?>
                            <button type="button" class="related-quick-view-btn" data-id="<?= $rp['id'] ?>">
                                <i class="bi bi-eye"></i> View Details
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <script src="<?= BASE_URL ?>/assets/js/reviews.js?v=1"></script>
    <script>
    (function(){
        var pills = document.querySelectorAll('.size-pill');
        var hidden = document.getElementById('selectedSize');
        if (!pills.length || !hidden) return;
        for (var i = 0; i < pills.length; i++) {
            pills[i].addEventListener('click', function(){
                if (this.classList.contains('selected')) {
                    this.classList.remove('selected');
                    hidden.value = '';
                } else {
                    for (var j = 0; j < pills.length; j++) pills[j].classList.remove('selected');
                    this.classList.add('selected');
                    hidden.value = this.getAttribute('data-value');
                }
            });
        }
    })();
    </script>
    <?php include 'includes/footer.php'; ?>
