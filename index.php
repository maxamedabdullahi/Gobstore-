<?php require_once 'includes/functions.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gob Store - Online Shopping</title>
    <script>try{let t=localStorage.getItem('gob_theme')||'dark';document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=11">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <?php
    $heroStmt = $pdo->prepare("SELECT p.* FROM products p WHERE p.status = 'active' AND p.stock_quantity > 0 ORDER BY p.created_at DESC LIMIT 4");
    $heroStmt->execute();
    $heroProducts = $heroStmt->fetchAll();
    $heroSlides = [
        ['title' => 'New Arrivals', 'tag' => 'Fresh Styles', 'text' => 'Discover the latest trends and elevate your wardrobe with our newest collection.', 'btn' => 'Shop New Arrivals'],
        ['title' => 'Premium Quality', 'tag' => 'Top Picks', 'text' => 'Handpicked pieces designed for comfort, style, and durability.', 'btn' => 'Explore Now'],
        ['title' => 'Season Favorites', 'tag' => 'Trending', 'text' => 'From casual to formal — find the perfect look for every moment.', 'btn' => 'Shop Trending'],
    ];
    ?>
    <section id="heroCarousel" class="carousel slide hero-banner" data-bs-ride="true" data-bs-interval="5000" data-bs-pause="false">
        <div class="carousel-indicators">
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active"></button>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1"></button>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2"></button>
        </div>
        <div class="carousel-inner">
            <?php foreach ($heroSlides as $i => $slide): ?>
            <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                <div class="hero-banner-slide">
                    <div class="container">
                        <div class="row align-items-center g-4">
                            <div class="col-lg-6">
                                <span class="hero-badge"><?= $slide['tag'] ?></span>
                                <h1 class="hero-heading"><?= $slide['title'] ?></h1>
                                <p class="hero-text"><?= $slide['text'] ?></p>
                                <a href="products.php" class="btn btn-hero"><?= $slide['btn'] ?> <i class="bi bi-arrow-right ms-2"></i></a>
                            </div>
                            <div class="col-lg-6">
                                <div class="hero-images">
                                    <?php for ($j = 0; $j < 2; $j++):
                                        $hp = $heroProducts[$i * 2 + $j] ?? $heroProducts[$j] ?? null;
                                        if ($hp):
                                    ?>
                                    <a href="product-details.php?id=<?= $hp['id'] ?>" class="hero-img-link">
                                        <img src="<?= $hp['main_image'] ? BASE_URL . '/uploads/' . $hp['main_image'] : 'https://placehold.co/300x300/1a3a1a/ffd700?text=G' ?>" alt="<?= escape($hp['name']) ?>">
                                        <span class="hero-img-label"><?= escape($hp['name']) ?></span>
                                    </a>
                                    <?php endif; endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
        </button>
    </section>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var el = document.getElementById('heroCarousel');
        if (el) { new bootstrap.Carousel(el, { interval: 5000, ride: 'carousel', pause: false, wrap: true }); }
    });
    </script>

    <section class="hero-search-section">
        <div class="container">
            <div class="hero-search-wrap">
                <h2 class="hero-search-heading"><i class="bi bi-search me-2"></i>What are you looking for?</h2>
                <div class="gob-search-box hero-search-box" id="gobHomeSearchBox">
                    <form action="<?= BASE_URL ?>/products.php" method="GET" class="gob-search-form">
                        <input type="text" name="search" class="gob-search-input" placeholder="Search products..." autocomplete="off">
                        <button type="submit" class="gob-search-submit" aria-label="Search"><i class="bi bi-search"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <div class="container py-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h2 class="section-title mb-0"><i class="bi bi-lightning-fill me-2" style="color: var(--accent);"></i>Featured Products</h2>
            <a href="products.php" class="btn btn-outline-accent btn-sm">View All <i class="bi bi-arrow-right ms-1"></i></a>
        </div>
        <div class="row g-3">
            <?php
            $prodStmt = $pdo->query("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.status = 'active' AND p.stock_quantity > 0 ORDER BY p.created_at DESC LIMIT 8");
            $featured = $prodStmt->fetchAll();
            foreach ($featured as $product):
            ?>
            <div class="col-md-3 col-6">
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
            <?php endforeach; ?>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
