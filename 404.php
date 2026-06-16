<?php require_once 'includes/functions.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found - Gob Store</title>
    <script>try{let t=localStorage.getItem('gob_theme')||'dark';document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=9">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container py-5 text-center">
        <h1 class="display-1 text-muted">404</h1>
        <p class="lead">Page not found.</p>
        <a href="index.php" class="btn btn-primary">Go Home</a>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
