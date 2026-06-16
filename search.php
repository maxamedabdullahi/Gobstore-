<?php
// ============================================================
// search.php — GobStore Live Search AJAX Endpoint
// ============================================================

require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

// ---------- Input ----------
$q = trim($_GET['q'] ?? ($_POST['q'] ?? ''));
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// ---------- Delete all history ----------
if ($action === 'delete_history' && isLoggedIn()) {
    $stmt = $pdo->prepare("DELETE FROM search_history WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode(['ok' => true]);
    exit;
}

// ---------- Delete single history item ----------
if ($action === 'delete_history_item' && isLoggedIn()) {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM search_history WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

// ---------- Trending ----------
if ($action === 'trending') {
    $stmt = $pdo->query("SELECT query, count FROM trending_searches ORDER BY count DESC, last_searched DESC LIMIT 10");
    echo json_encode(['trending' => $stmt->fetchAll()]);
    exit;
}

// ---------- History ----------
if ($action === 'history' && isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT id, query FROM search_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode(['history' => $stmt->fetchAll()]);
    exit;
}

// ---------- Live Search ----------
if (mb_strlen($q) < 1) {
    echo json_encode(['products' => [], 'query' => '']);
    exit;
}

$q = mb_substr($q, 0, 100);

// ---------- Build search terms ----------
$terms = preg_split('/\s+/', $q);
$terms = array_filter($terms, fn($t) => mb_strlen(trim($t)) > 0);
$terms = array_values($terms);

// For FULLTEXT boolean mode: +word* for each term >= 2 chars
$boolParts = [];
foreach ($terms as $t) {
    $t = trim($t);
    if (mb_strlen($t) >= 2) {
        $safe = preg_replace('/[+\-*()~<>"@]/', ' ', $t);
        $safe = trim(preg_replace('/\s+/', ' ', $safe));
        if (mb_strlen($safe) >= 2) {
            $boolParts[] = '+' . $safe . '*';
        }
    }
}
$boolQuery = implode(' ', $boolParts);
$useFulltext = count($boolParts) > 0;
$likeQ = '%' . $q . '%';

// ---------- SQL with YouTube-style ranking ----------
$sql = "
SELECT
    p.id, p.name, p.price, p.is_featured, p.is_vip, p.views, p.main_image,
    c.id AS category_id, c.name AS category_name,
    (
        CASE WHEN LOWER(p.name) = LOWER(:exact1) THEN 10000 ELSE 0 END +
        CASE WHEN LOWER(p.name) LIKE LOWER(:prefix1) THEN 5000 ELSE 0 END
";

if ($useFulltext) {
    $sql .= " + CASE WHEN MATCH(p.name, p.description) AGAINST(:bool1 IN BOOLEAN MODE) THEN 2000 ELSE 0 END";
}

$sql .= "
        + CASE WHEN p.is_featured = 1 THEN 500 ELSE 0 END
        + CASE WHEN p.is_vip = 1 THEN 300 ELSE 0 END
        + p.views * 0.1
    ) AS score
FROM products p
JOIN categories c ON c.id = p.category_id
WHERE p.status = 'active' AND p.stock_quantity > 0
  AND (
        LOWER(p.name) LIKE LOWER(:like1)
        OR LOWER(c.name) LIKE LOWER(:cat1)
";

if ($useFulltext) {
    $sql .= " OR MATCH(p.name, p.description) AGAINST(:bool2 IN BOOLEAN MODE)";
}

$sql .= "
    )
ORDER BY score DESC, p.views DESC, p.created_at DESC
LIMIT 8
";

$params = [
    ':exact1'  => $q,
    ':prefix1' => $q . '%',
    ':like1'   => $likeQ,
    ':cat1'    => $likeQ,
];

if ($useFulltext) {
    $params[':bool1'] = $boolQuery;
    $params[':bool2'] = $boolQuery;
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    // Fallback: FULLTEXT may fail for very short words
    $fallbackSql = "
        SELECT
            p.id, p.name, p.price, p.is_featured, p.is_vip, p.views, p.main_image,
            c.id AS category_id, c.name AS category_name,
            (CASE WHEN LOWER(p.name) = LOWER(:exact2) THEN 10000 ELSE 0 END +
             CASE WHEN LOWER(p.name) LIKE LOWER(:prefix2) THEN 5000 ELSE 0 END +
             CASE WHEN p.is_featured = 1 THEN 500 ELSE 0 END +
             CASE WHEN p.is_vip = 1 THEN 300 ELSE 0 END +
             p.views * 0.1) AS score
        FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE p.status = 'active' AND p.stock_quantity > 0
          AND (LOWER(p.name) LIKE LOWER(:like2) OR LOWER(c.name) LIKE LOWER(:cat2))
        ORDER BY score DESC, p.views DESC, p.created_at DESC
        LIMIT 8
    ";
    $fallbackParams = [
        ':exact2'  => $q,
        ':prefix2' => $q . '%',
        ':like2'   => $likeQ,
        ':cat2'    => $likeQ,
    ];
    $stmt = $pdo->prepare($fallbackSql);
    $stmt->execute($fallbackParams);
    $products = $stmt->fetchAll();
}

// ---------- Format results ----------
$baseUrl = BASE_URL;
$results = [];

foreach ($products as $p) {
    $img = $p['main_image'] ?: 'assets/images/placeholder.png';
    if (!str_starts_with($img, 'http') && !str_starts_with($img, '/')) {
        $img = $baseUrl . '/' . $img;
    }

    $price = number_format((float)$p['price'], 2);
    $nameHighlighted = highlightMatch($p['name'], $terms);
    $descHighlighted = highlightMatch(mb_substr($p['description'] ?? '', 0, 120), $terms);

    $results[] = [
        'id'               => (int)$p['id'],
        'name'             => $p['name'],
        'name_highlighted' => $nameHighlighted,
        'price'            => $price,
        'image'            => $img,
        'category_id'      => (int)$p['category_id'],
        'category_name'    => $p['category_name'],
        'is_featured'      => (int)$p['is_featured'],
        'is_vip'           => (int)$p['is_vip'],
        'views'            => (int)$p['views'],
        'score'            => round((float)$p['score'], 1),
        'description'      => $descHighlighted,
        'url'              => $baseUrl . '/product-details.php?id=' . $p['id'],
    ];
}

// ---------- Record search ----------
if (count($results) > 0) {
    if (isLoggedIn()) {
        $histStmt = $pdo->prepare("INSERT INTO search_history (user_id, query) VALUES (?, ?)");
        $histStmt->execute([$_SESSION['user_id'], $q]);
    }

    try {
        $trendStmt = $pdo->prepare("INSERT INTO trending_searches (query, count) VALUES (?, 1) ON DUPLICATE KEY UPDATE count = count + 1, last_searched = NOW()");
        $trendStmt->execute([mb_strtolower($q)]);
    } catch (Exception $e) {
        // Non-critical
    }
}

// ---------- Autocomplete suggestions ----------
$suggestions = [];
try {
    $likePattern = '%' . $q . '%';
    $stmt = $pdo->prepare("
        SELECT DISTINCT name FROM products
        WHERE status = 'active' AND stock_quantity > 0
          AND LOWER(name) LIKE LOWER(?)
        ORDER BY
          CASE WHEN LOWER(name) LIKE LOWER(?) THEN 0 ELSE 1 END,
          is_featured DESC, views DESC, name ASC
        LIMIT 6
    ");
    $stmt->execute([$likePattern, $q . '%']);
    $suggestions = $stmt->fetchAll();
} catch (Exception $e) {
    // Non-critical
}

echo json_encode([
    'products'    => $results,
    'suggestions' => array_column($suggestions, 'name'),
    'query'       => $q,
    'total'       => count($results),
], JSON_UNESCAPED_UNICODE);

// ============================================================
// Helper: highlight matching terms
// ============================================================
function highlightMatch(string $text, array $terms): string
{
    if (empty($text) || empty($terms)) {
        return escape($text);
    }
    $escaped = escape($text);
    foreach ($terms as $t) {
        $t = trim($t);
        if (mb_strlen($t) < 1) continue;
        $pattern = '/' . preg_quote($t, '/') . '/iu';
        $escaped = preg_replace($pattern, '<mark>$0</mark>', $escaped);
    }
    return $escaped;
}
