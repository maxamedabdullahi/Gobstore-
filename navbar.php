<?php
// Fetch all categories organized hierarchically
$navCats = $pdo->query("SELECT id, name, parent_id FROM categories ORDER BY parent_id IS NULL DESC, parent_id, sort_order, id")->fetchAll();

$parents = [];
$children = [];
foreach ($navCats as $c) {
    if ($c['parent_id'] === null) {
        $parents[] = $c;
    } else {
        $children[$c['parent_id']][] = $c;
    }
}
$cartCount = isLoggedIn() ? getCartCount($pdo, $_SESSION['user_id']) : 0;
?>
<div class="gob-progress" id="gobProgress"></div>
<nav class="gob-navbar" id="gobNavbar">
    <div class="container-fluid">
        <div class="gob-navbar-inner">

            <button class="gob-hamburger" id="gobHamburger" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>

            <a class="gob-brand" href="<?= BASE_URL ?>/index.php">
                <img src="<?= BASE_URL ?>/assets/images/logo1.png" alt="GOBSTORE">
            </a>

            <ul class="gob-menu" id="gobMenu">
                <li class="gob-menu-item"><a class="gob-menu-link" href="<?= BASE_URL ?>/index.php">Home</a></li>
                <?php foreach ($parents as $p):
                    $hasKids = !empty($children[$p['id']]);
                ?>
                <li class="gob-menu-item <?= $hasKids ? 'gob-has-drop' : '' ?>">
                    <a class="gob-menu-link" href="<?= BASE_URL . '/category.php?id=' . $p['id'] ?>">
                        <?= escape($p['name']) ?>
                        <?php if ($hasKids): ?><i class="bi bi-chevron-down gob-arrow"></i><?php endif; ?>
                    </a>
                    <?php if ($hasKids): ?>
                    <div class="gob-drop">
                        <div class="gob-drop-inner">
                            <h6 class="gob-drop-title"><?= escape($p['name']) ?></h6>
                            <?php foreach ($children[$p['id']] as $ch): ?>
                            <a class="gob-drop-link" href="<?= BASE_URL ?>/products.php?subcategory_id=<?= $ch['id'] ?>"><?= escape($ch['name']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>

            <div class="gob-actions">
                <div class="gob-search-box" id="gobSearchBox">
                    <form action="<?= BASE_URL ?>/products.php" method="GET" class="gob-search-form">
                        <input type="text" name="search" class="gob-search-input" placeholder="Search..." autocomplete="off">
                        <button type="submit" class="gob-search-submit" aria-label="Search"><i class="bi bi-search"></i></button>
                    </form>
                </div>

                <button class="gob-icon-btn gob-theme-btn" id="themeToggle" aria-label="Theme">
                    <i class="bi bi-sun-fill"></i>
                </button>

                <div class="gob-user-menu">
                    <button class="gob-icon-btn" id="gobUserBtn" aria-label="Account" aria-haspopup="true" aria-expanded="false">
                        <i class="bi bi-person-fill"></i>
                        <i class="bi bi-chevron-down gob-user-arrow"></i>
                    </button>
                    <div class="gob-user-drop" id="gobUserDrop">
                        <?php if (isLoggedIn()): ?>
                        <div class="gob-drop-userinfo"><?= escape($_SESSION['user_name'] ?? 'Account') ?></div>
                        <div class="gob-drop-divider"></div>
                        <a class="gob-drop-link" href="<?= BASE_URL ?>/profile.php"><i class="bi bi-person me-2"></i>Profile</a>
                        <?php if (!isAdmin()): ?>
                        <a class="gob-drop-link" href="<?= BASE_URL ?>/my-orders.php"><i class="bi bi-box me-2"></i>My Orders</a>
                        <?php endif; ?>
                        <?php if (isAdmin()): ?>
                        <div class="gob-drop-divider"></div>
                        <a class="gob-drop-link" href="<?= BASE_URL ?>/admin/index.php"><i class="bi bi-shield-fill me-2"></i>Admin Panel</a>
                        <?php endif; ?>
                        <div class="gob-drop-divider"></div>
                        <a class="gob-drop-link" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
                        <?php else: ?>
                        <div class="gob-drop-userinfo">Account</div>
                        <div class="gob-drop-divider"></div>
                        <a class="gob-drop-link" href="<?= BASE_URL ?>/register.php"><i class="bi bi-person-plus me-2"></i>Register</a>
                        <a class="gob-drop-link" href="<?= BASE_URL ?>/login.php"><i class="bi bi-person me-2"></i>Login</a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!isAdmin()): ?>
                <a class="gob-icon-btn gob-cart" href="<?= BASE_URL ?>/cart.php" aria-label="Cart">
                    <i class="bi bi-cart3"></i>
                    <?php if ($cartCount > 0): ?>
                    <span class="gob-cart-count"><?= $cartCount ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
            </div>

        </div>
    </div>
</nav>
<div class="gob-overlay" id="gobOverlay"></div>
<script>
document.getElementById('gobUserBtn')?.addEventListener('click', function(e){
    e.stopPropagation();
    var p = this.closest('.gob-user-menu');
    p.classList.toggle('active');
    this.setAttribute('aria-expanded', p.classList.contains('active'));
});
document.addEventListener('click', function(e){
    var btn = document.getElementById('gobUserBtn');
    if(!btn) return;
    var p = btn.closest('.gob-user-menu');
    if(!p.contains(e.target) && p.classList.contains('active')){
        p.classList.remove('active');
        btn.setAttribute('aria-expanded','false');
    }
});
document.addEventListener('keydown', function(e){
    if(e.key!=='Escape') return;
    var btn = document.getElementById('gobUserBtn');
    if(!btn) return;
    var p = btn.closest('.gob-user-menu');
    if(p.classList.contains('active')){
        p.classList.remove('active');
        btn.setAttribute('aria-expanded','false');
    }
});

// Search logic moved to assets/js/search.js
</script>
