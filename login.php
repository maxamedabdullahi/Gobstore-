<?php require_once 'includes/functions.php'; ?>
<?php
if (isLoggedIn()) {
    redirect(BASE_URL . '/index.php');
}

$error = '';
$success = $_SESSION['register_success'] ?? '';
unset($_SESSION['register_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) {
        $error = 'Security token invalid. Please refresh and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter email and password.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Check account status
                if (($user['status'] ?? 'active') !== 'active') {
                    $error = 'Your account has been deactivated. Contact support.';
                } else {
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['fullname'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];

                    logMessage('INFO', "User logged in: $email");

                    if ($user['role'] === 'admin') {
                        redirect(BASE_URL . '/admin/index.php');
                    } else {
                        redirect(BASE_URL . '/index.php');
                    }
                }
            } else {
                $error = 'Invalid email or password.';
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
    <title>Login - Gob Store</title>
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
            padding: 30px;
            text-align: center;
            border-bottom: 2px solid var(--accent);
        }
        .auth-header .brand-icon {
            font-size: 3rem;
            color: var(--accent);
        }
        .auth-header h2 {
            color: var(--text-heading);
            font-weight: 700;
            margin-top: 10px;
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
        .btn-login {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            color: #fff;
            transition: all 0.3s;
        }
        .btn-login:hover {
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
                        <i class="bi bi-bag-check-fill brand-icon"></i>
                        <h2>Welcome Back</h2>
                        <p>Sign in to your Gob Store account</p>
                    </div>
                    <div class="auth-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success mb-4">
                                <i class="bi bi-check-circle-fill me-2"></i><?= escape($success) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert-custom mb-4">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= escape($error) ?>
                            </div>
                        <?php endif; ?>
                        <form method="POST">
                            <?= csrfField() ?>
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <div class="input-icon">
                                    <i class="bi bi-envelope-fill"></i>
                                    <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Password</label>
                                <div class="input-icon">
                                    <i class="bi bi-lock-fill"></i>
                                    <input type="password" name="password" id="loginPassword" class="form-control" placeholder="Enter your password" required>
                                    <button type="button" class="input-icon-toggle" onclick="togglePassword('loginPassword', this)" tabindex="-1" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 4px; line-height: 1;">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-login w-100">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login
                            </button>
                        </form>
                    </div>
                    <div class="auth-footer">
                        <span class="text-muted">Don't have an account?</span>
                        <a href="register.php"><i class="bi bi-person-plus me-1"></i>Register</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/main.js?v=2.3"></script>
    <script>
    (function() {
        var form = document.querySelector('form');
        if (!form) return;

        var email = form.querySelector('[name="email"]');
        var password = form.querySelector('[name="password"]');

        function validateEmail() {
            if (!email || !email.value) return true;
            var hint = email.closest('.mb-3').querySelector('.email-hint');
            if (!hint) {
                hint = document.createElement('div');
                hint.className = 'email-hint';
                hint.style.cssText = 'font-size:0.8rem;margin-top:4px;color:var(--text-muted);';
                email.parentElement.appendChild(hint);
            }
            if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
                hint.innerHTML = '';
                return true;
            }
            hint.innerHTML = '<span style="color:#c62828;">Please enter a valid email address</span>';
            return false;
        }

        if (email) {
            email.addEventListener('input', validateEmail);
        }

        form.addEventListener('submit', function(e) {
            if (email && !email.value) {
                e.preventDefault();
                email.focus();
                return;
            }
            if (password && !password.value) {
                e.preventDefault();
                password.focus();
                return;
            }
        });
    })();
    </script>
</body>
</html>
