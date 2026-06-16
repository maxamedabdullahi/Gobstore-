<?php require_once 'includes/functions.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to submit a review.', 'login_url' => BASE_URL . '/login.php']);
    exit;
}

if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    echo json_encode(['success' => false, 'message' => 'Admins cannot submit reviews.']);
    exit;
}

$productId = (int)($_POST['product_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if ($productId < 1 || $rating < 1 || $rating > 5 || $comment === '') {
    echo json_encode(['success' => false, 'message' => 'Please select a rating and write a comment.']);
    exit;
}

// Check product exists
$stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND status = 'active'");
$stmt->execute([$productId]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Product not found.']);
    exit;
}

// Check duplicate
$stmt = $pdo->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_id = ?");
$stmt->execute([$productId, $_SESSION['user_id']]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'You have already reviewed this product.']);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO reviews (product_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->execute([$productId, $_SESSION['user_id'], $rating, $comment]);

echo json_encode(['success' => true, 'message' => 'Review submitted! It will appear after approval.']);
