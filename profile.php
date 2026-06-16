<?php require_once 'includes/functions.php'; ?>
<?php require_once 'includes/countries.php'; ?>
<?php requireLogin(); ?>
<?php generateCsrfToken(); ?>

<?php
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) {
        $error = 'Security token invalid. Please refresh and try again.';
    } else {
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $country = $_POST['country'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $address = trim($_POST['address'] ?? '');
        $age = (int)($_POST['age'] ?? 0);

        if (empty($fullname) || empty($email) || empty($phone) || empty($country) || empty($gender) || empty($address) || empty($age)) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } elseif (!preg_match('/^\+?\d{7,15}$/', preg_replace('/[\s\-\(\)]/', '', $phone))) {
            $error = 'Invalid phone number format.';
        } elseif ($age < 1 || $age > 120) {
            $error = 'Please enter a valid age.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $error = 'Email already in use.';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, phone = ?, country = ?, gender = ?, address = ?, age = ? WHERE id = ?");
                $stmt->execute([$fullname, $email, $phone, $country, $gender, $address, $age, $_SESSION['user_id']]);
                $_SESSION['user_name'] = $fullname;
                $_SESSION['user_email'] = $email;
                $success = 'Profile updated successfully.';
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Gob Store</title>
    <script>try{let t=localStorage.getItem('gob_theme')||'dark';document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=9">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?= strtoupper(substr($user['fullname'], 0, 1)) ?>
                        </div>
                        <h4 class="mt-3" style="color: var(--text-heading); font-weight: 700;"><?= escape($user['fullname']) ?></h4>
                        <small style="color: var(--text-muted);"><i class="bi bi-envelope me-1"></i><?= escape($user['email']) ?></small>
                    </div>
                    <div class="profile-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i><?= escape($success) ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= escape($error) ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <?= csrfField() ?>
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-person-fill me-1 text-accent"></i>Full Name</label>
                                <input type="text" name="fullname" class="form-control" value="<?= escape($user['fullname']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-envelope-fill me-1 text-accent"></i>Email</label>
                                <input type="email" name="email" class="form-control" value="<?= escape($user['email']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-globe me-1 text-accent"></i>Country</label>
                                <select name="country" class="form-control" required>
                                    <?php foreach ($countries as $c): ?>
                                    <option value="<?= escape($c) ?>" <?= $user['country'] === $c ? 'selected' : '' ?>><?= escape($c) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-telephone-fill me-1 text-accent"></i>Phone</label>
                                <input type="tel" name="phone" class="form-control" value="<?= escape($user['phone']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-gender-ambiguous me-1 text-accent"></i>Gender</label>
                                <select name="gender" class="form-control" required>
                                    <option value="male" <?= $user['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                                    <option value="female" <?= $user['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-geo-alt-fill me-1 text-accent"></i>Address</label>
                                <textarea name="address" class="form-control" required style="min-height: 80px;"><?= escape($user['address']) ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-calendar me-1 text-accent"></i>Age</label>
                                <input type="number" name="age" class="form-control" value="<?= escape($user['age']) ?>" required min="1" max="120">
                            </div>
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check2-circle me-2"></i>Update Profile</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    const codes = <?= json_encode($countryCodes) ?>;
    document.querySelector('[name="country"]')?.addEventListener('change', function() {
        const phone = document.querySelector('[name="phone"]');
        if (phone) {
            let num = phone.value.replace(/^\+\d+\s*/, '');
            phone.value = '+' + (codes[this.value] || '') + num;
        }
    });
    </script>
    <?php include 'includes/footer.php'; ?>
