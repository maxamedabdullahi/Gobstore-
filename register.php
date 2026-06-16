<?php require_once 'includes/functions.php'; ?>
<?php require_once 'includes/countries.php'; ?>
<?php
if (isLoggedIn()) {
    redirect(BASE_URL . '/dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) {
        $error = 'Security token invalid. Please try again.';
    } else {
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $country = $_POST['country'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $address = trim($_POST['address'] ?? '');
        $age = isset($_POST['age']) ? (int)$_POST['age'] : 0;
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($fullname) || empty($email) || empty($phone) || empty($country) || empty($gender) || empty($address) || empty($age) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } elseif (!preg_match('/^\+?\d{7,15}$/', preg_replace('/[\s\-\(\)]/', '', $phone))) {
            $error = 'Invalid phone number format.';
        } elseif ($age < 1 || $age > 120) {
            $error = 'Please enter a valid age.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email already registered.';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (fullname, email, phone, country, gender, address, age, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'customer', 'active')");
                $stmt->execute([$fullname, $email, $phone, $country, $gender, $address, $age, $hashed]);
                logMessage('INFO', "New user registered: $email");
                $_SESSION['register_success'] = 'Registration successful! You can now login.';
                redirect(BASE_URL . '/login.php');
            }
        }
    }
}
generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Gob Store</title>
    <script>try{let t=localStorage.getItem('gob_theme')||'dark';document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=9">
    <style>
        body {
            background: var(--body-bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .auth-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: 0 0 40px var(--shadow-green);
            overflow: hidden;
        }
        .auth-header {
            background: linear-gradient(135deg, var(--hero-to), rgba(13, 38, 13, 0.8));
            padding: 25px 30px;
            text-align: center;
            border-bottom: 2px solid var(--accent);
        }
        .auth-header .brand-icon {
            font-size: 2.5rem;
            color: var(--accent);
        }
        .auth-header h2 {
            color: var(--text-heading);
            font-weight: 700;
            margin-top: 8px;
            letter-spacing: 1px;
        }
        .auth-header p {
            color: var(--text-muted);
            margin: 0;
        }
        .auth-body {
            padding: 30px;
        }
        .form-control {
            background: var(--input-bg);
            border: 1px solid var(--border-light);
            color: var(--text-primary);
            border-radius: 10px;
            padding: 12px 16px;
            transition: all 0.3s;
        }
        .form-control:focus {
            background: var(--input-bg);
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.15);
            color: var(--text-primary);
        }
        .form-control::placeholder {
            color: var(--text-muted);
        }
        .form-label {
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.9rem;
        }
        .btn-register {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            color: #fff;
            transition: all 0.3s;
        }
        .btn-register:hover {
            background: linear-gradient(135deg, var(--primary-hover), var(--primary));
            transform: translateY(-1px);
            box-shadow: 0 8px 20px var(--btn-primary-shadow);
        }
        .auth-footer {
            text-align: center;
            padding: 20px 30px;
            border-top: 1px solid var(--border-color);
            background: var(--card-header-bg);
        }
        .auth-footer a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }
        .auth-footer a:hover {
            text-decoration: underline;
        }
        .input-icon {
            position: relative;
        }
        .input-icon .bi {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            z-index: 10;
        }
        .input-icon .form-control {
            padding-left: 40px;
            padding-right: 40px;
        }
        .input-icon .input-icon-toggle {
            z-index: 11;
        }
        .alert-custom {
            background: var(--alert-info-bg);
            border: 1px solid var(--alert-info-border);
            color: var(--alert-info-text);
            border-radius: 10px;
            padding: 12px 16px;
        }
        .alert-success-custom {
            background: var(--alert-success-bg);
            border: 1px solid var(--alert-success-border);
            color: var(--alert-success-text);
            border-radius: 10px;
            padding: 12px 16px;
        }
        [data-theme="light"] .auth-header {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
        }
    </style>
</head>
<body>
    <button type="button" class="theme-toggle position-fixed top-0 end-0 m-3" id="themeToggle" title="Toggle theme" style="z-index: 9999;">
        <i class="bi bi-sun-fill"></i>
    </button>
    <div style="position: fixed; top: 14px; left: 50%; transform: translateX(-50%); z-index: 9999;">
        <a href="<?= BASE_URL ?>/index.php" style="color: var(--accent); font-size: 1.5rem; text-decoration: none;">
            <i class="bi bi-house-door-fill"></i>
        </a>
    </div>
    <div class="container" style="padding-top: 40px; padding-bottom: 40px;">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="auth-card">
                    <div class="auth-header">
                        <i class="bi bi-person-plus-fill brand-icon"></i>
                        <h2>Create Account</h2>
                        <p>Join Gob Store today</p>
                    </div>
                    <div class="auth-body">
                        <?php if ($error): ?>
                            <div class="alert-custom mb-4">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= escape($error) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert-success-custom mb-4">
                                <i class="bi bi-check-circle-fill me-2"></i><?= escape($success) ?>
                            </div>
                        <?php endif; ?>
                        <form method="POST">
                            <?= csrfField() ?>
                            <div class="mb-3">
                                <label class="form-label">Full Name <span style="color:#ffd700">*</span></label>
                                <div class="input-icon">
                                    <i class="bi bi-person-fill"></i>
                                    <input type="text" name="fullname" class="form-control" placeholder="Enter your full name" required value="<?= escape($_POST['fullname'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email <span style="color:#ffd700">*</span></label>
                                <div class="input-icon">
                                    <i class="bi bi-envelope-fill"></i>
                                    <input type="email" name="email" class="form-control" placeholder="Enter your email" required value="<?= escape($_POST['email'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Country <span style="color:#ffd700">*</span></label>
                                <div class="input-icon">
                                    <i class="bi bi-globe"></i>
                                    <select name="country" class="form-control" required style="padding-left: 40px;">
                                        <option value="" disabled <?= empty($_POST['country']) ? 'selected' : '' ?>>Select your country</option>
                                        <?php foreach ($countries as $c): ?>
                                        <option value="<?= escape($c) ?>" <?= ($_POST['country'] ?? '') === $c ? 'selected' : '' ?>><?= escape($c) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone <span style="color:#ffd700">*</span></label>
                                <div class="input-icon">
                                    <i class="bi bi-telephone-fill"></i>
                                    <input type="tel" name="phone" class="form-control" placeholder="e.g. 612345678" required value="<?= escape($_POST['phone'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Gender <span style="color:#ffd700">*</span></label>
                                <div class="input-icon">
                                    <i class="bi bi-gender-ambiguous"></i>
                                    <select name="gender" class="form-control" required style="padding-left: 40px;">
                                        <option value="" disabled <?= empty($_POST['gender']) ? 'selected' : '' ?>>Select your gender</option>
                                        <option value="male" <?= ($_POST['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                                        <option value="female" <?= ($_POST['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address <span style="color:#ffd700">*</span></label>
                                <div class="input-icon">
                                    <i class="bi bi-geo-alt-fill" style="top: 16px; transform: none;"></i>
                                    <textarea name="address" class="form-control" placeholder="Enter your address" required style="padding-left: 40px; min-height: 80px;"><?= escape($_POST['address'] ?? '') ?></textarea>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Age <span style="color:#ffd700">*</span></label>
                                <div class="input-icon">
                                    <i class="bi bi-calendar"></i>
                                    <input type="number" name="age" class="form-control" placeholder="Enter your age" required min="1" max="120" value="<?= escape($_POST['age'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password <span style="color:#ffd700">*</span></label>
                                <div class="input-icon">
                                    <i class="bi bi-lock-fill"></i>
                                    <input type="password" name="password" id="regPassword" class="form-control" placeholder="At least 8 characters" required minlength="8">
                                    <button type="button" class="input-icon-toggle" onclick="togglePassword('regPassword', this)" tabindex="-1" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 4px; line-height: 1;">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Confirm Password <span style="color:#ffd700">*</span></label>
                                <div class="input-icon">
                                    <i class="bi bi-shield-lock-fill"></i>
                                    <input type="password" name="confirm_password" id="regConfirmPassword" class="form-control" placeholder="Re-enter your password" required>
                                    <button type="button" class="input-icon-toggle" onclick="togglePassword('regConfirmPassword', this)" tabindex="-1" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 4px; line-height: 1;">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-register flex-grow-1">
                                    <i class="bi bi-person-check me-2"></i>Create Account
                                </button>
                                <button type="button" class="btn btn-outline-secondary" style="border-radius: 12px; padding: 12px 20px; font-weight: 600;" onclick="document.querySelector('form').querySelectorAll('input, textarea, select').forEach(function(el) { if (el.type !== 'hidden') { if (el.tagName === 'SELECT') { el.selectedIndex = 0; } else if (el.type !== 'button') { el.value = ''; } } });">
                                    <i class="bi bi-x-circle me-1"></i>Clear
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="auth-footer">
                        <span class="text-muted">Already have an account?</span>
                        <a href="login.php"><i class="bi bi-box-arrow-in-right me-1"></i>Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/main.js?v=2.3"></script>
    <script>
    const codes = <?= json_encode($countryCodes) ?>;
    document.querySelector('[name="country"]')?.addEventListener('change', function() {
        const phone = document.querySelector('[name="phone"]');
        if (phone) {
            let num = phone.value.replace(/^\+\d+\s*/, '');
            phone.value = '+' + (codes[this.value] || '') + num;
        }
    });

    // Client-side validation
    (function() {
        var form = document.querySelector('form');
        if (!form) return;

        var password = document.getElementById('regPassword');
        var confirm = document.getElementById('regConfirmPassword');
        var phone = document.querySelector('[name="phone"]');

        function validatePassword() {
            if (!password) return true;
            var val = password.value;
            var hint = password.closest('.mb-3').querySelector('.password-hint');
            if (!hint) {
                hint = document.createElement('div');
                hint.className = 'password-hint';
                hint.style.cssText = 'font-size:0.8rem;margin-top:4px;color:var(--text-muted);';
                password.parentElement.appendChild(hint);
            }
            if (val.length >= 8) {
                hint.innerHTML = '<span style="color:#2e7d32;">✓ Minimum 8 characters</span>';
                return true;
            }
            hint.innerHTML = '<span style="color:#c62828;">Must be at least 8 characters</span>';
            return false;
        }
            if (errors.length === 0) {
                hint.innerHTML = '<span style="color:#2e7d32;">✓ Strong password</span>';
                return true;
            }
            hint.innerHTML = '✗ ' + errors.join(', ');
            return false;
        }

        function validateConfirm() {
            if (!password || !confirm) return true;
            var hint = confirm.closest('.mb-3').querySelector('.confirm-hint');
            if (!hint) {
                hint = document.createElement('div');
                hint.className = 'confirm-hint';
                hint.style.cssText = 'font-size:0.8rem;margin-top:4px;color:var(--text-muted);';
                confirm.parentElement.appendChild(hint);
            }
            if (confirm.value === '') {
                hint.textContent = '';
                return true;
            }
            if (password.value === confirm.value) {
                hint.innerHTML = '<span style="color:#2e7d32;">✓ Passwords match</span>';
                return true;
            }
            hint.innerHTML = '<span style="color:#c62828;">✗ Passwords do not match</span>';
            return false;
        }

        if (password) {
            password.addEventListener('input', function() {
                validatePassword();
                if (confirm && confirm.value) validateConfirm();
            });
        }
        if (confirm) {
            confirm.addEventListener('input', validateConfirm);
        }

        // Phone format hint
        if (phone) {
            phone.addEventListener('input', function() {
                var hint = this.closest('.mb-3').querySelector('.phone-hint');
                if (!hint) {
                    hint = document.createElement('div');
                    hint.className = 'phone-hint';
                    hint.style.cssText = 'font-size:0.8rem;margin-top:4px;color:var(--text-muted);';
                    this.parentElement.appendChild(hint);
                }
                var cleaned = this.value.replace(/[\s\-\(\)]/g, '');
                if (/^\+?\d{7,15}$/.test(cleaned)) {
                    hint.innerHTML = '<span style="color:#2e7d32;">✓ Valid phone number</span>';
                } else if (this.value.length > 0) {
                    hint.innerHTML = '<span style="color:#c62828;">Invalid format (digits only, 7-15, optional +)</span>';
                } else {
                    hint.textContent = '';
                }
            });
        }

        // Form submit validation
        form.addEventListener('submit', function(e) {
            if (!validatePassword() || !validateConfirm()) {
                e.preventDefault();
                var firstError = form.querySelector('.password-hint, .confirm-hint');
                if (firstError && firstError.textContent.includes('✗')) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    })();
    </script>
</body>
</html>
