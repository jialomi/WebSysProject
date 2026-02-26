<?php
/**
 * login.php
 * DriveEasy Car Rentals — Login Page
 *
 * Security:
 *  - Password verified with password_verify() (bcrypt)
 *  - Session regenerated after login (session fixation prevention)
 *  - CSRF token on form
 *  - Generic error message (no user enumeration)
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect already logged-in users
if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$pageTitle = 'Login – DriveEasy Car Rentals';
$csrf      = generateCsrfToken();
$errors    = [];
$emailVal  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    $email    = trim(filter_input(INPUT_POST, 'email',    FILTER_SANITIZE_EMAIL));
    $password = $_POST['password'] ?? '';  // Raw; will be verified, not echoed
    $emailVal = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

    if (empty($email))    $errors[] = 'Email is required.';
    if (empty($password)) $errors[] = 'Password is required.';

    if (empty($errors)) {
        // Fetch user by email using prepared statement
        $stmt = $pdo->prepare(
            "SELECT id, name, email, password_hash, role FROM users
             WHERE email = :email LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        // Verify password; use time-constant comparison to prevent timing attacks
        if ($user && password_verify($password, $user['password_hash'])) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            // Store user data in session
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            // Check if we need to rehash (e.g. algorithm upgraded)
            if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT)) {
                $newHash = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET password_hash = :h WHERE id = :id")
                    ->execute([':h' => $newHash, ':id' => $user['id']]);
            }

            // Redirect to originally attempted page, or role-specific default
            $redirect = $_SESSION['login_redirect'] ?? '';
            unset($_SESSION['login_redirect']);

            if ($user['role'] === 'admin') {
                header('Location: ' . ($redirect ?: '/admin/dashboard.php'));
            } else {
                header('Location: ' . ($redirect ?: '/index.php'));
            }
            exit;
        } else {
            // Generic message — don't reveal whether email exists
            $errors[] = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Log in to your DriveEasy Car Rentals account.">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light">

<?php include __DIR__ . '/includes/navbar.php'; ?>

<main>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-9 col-md-7 col-lg-5">

            <!-- Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">

                    <!-- Brand -->
                    <div class="text-center mb-4">
                        <h1 class="h4 fw-bold">
                            <span class="text-warning">Drive</span>Easy
                        </h1>
                        <p class="text-muted small">Sign in to your account</p>
                    </div>

                    <!-- Flash message from redirect -->
                    <?php
                    $msg = filter_input(INPUT_GET, 'msg', FILTER_SANITIZE_SPECIAL_CHARS);
                    if ($msg === 'please_login'): ?>
                    <div class="alert alert-info small" role="alert">
                        <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                        Please log in to continue.
                    </div>
                    <?php endif; ?>

                    <?= renderFlash() ?>

                    <!-- Validation errors -->
                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger small" role="alert">
                        <?php foreach ($errors as $e): ?>
                        <div><?= htmlspecialchars($e) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Login Form -->
                    <form action="/login.php" method="POST"
                          class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token"
                               value="<?= htmlspecialchars($csrf) ?>">

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= $emailVal ?>"
                                   autocomplete="username email"
                                   placeholder="you@example.com"
                                   required>
                            <div class="invalid-feedback">Please enter your email address.</div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <label for="password" class="form-label fw-semibold">Password</label>
                                <a href="#" class="small text-warning">Forgot password?</a>
                            </div>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password"
                                       name="password"
                                       autocomplete="current-password"
                                       placeholder="Your password"
                                       required>
                                <button class="btn btn-outline-secondary" type="button"
                                        id="togglePassword"
                                        aria-label="Toggle password visibility">
                                    <i class="bi bi-eye" id="toggleIcon" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Please enter your password.</div>
                        </div>

                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       id="rememberMe" name="remember_me">
                                <label class="form-check-label small" for="rememberMe">
                                    Remember me on this device
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-warning w-100 fw-bold py-2">
                            <i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i>
                            Sign In
                        </button>
                    </form>

                    <hr class="my-4">

                    <p class="text-center text-muted small mb-0">
                        Don't have an account?
                        <a href="/register.php" class="text-warning fw-semibold">Register here</a>
                    </p>

                </div>
            </div>

            <!-- Demo credentials for testing -->
            <div class="card border-warning mt-3 bg-warning bg-opacity-10">
                <div class="card-body py-2 px-3">
                    <p class="small mb-1 fw-semibold text-warning">Demo Credentials</p>
                    <p class="small mb-1 text-muted">
                        <strong>Admin:</strong> admin@driveeasy.com / admin123
                    </p>
                    <p class="small mb-0 text-muted">
                        <strong>User:</strong> john@example.com / user123
                    </p>
                </div>
            </div>

        </div>
    </div>
</div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/main.js"></script>

<script>
// Toggle password visibility
document.getElementById('togglePassword')?.addEventListener('click', function() {
    const pwInput = document.getElementById('password');
    const icon    = document.getElementById('toggleIcon');
    if (pwInput.type === 'password') {
        pwInput.type  = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        pwInput.type  = 'password';
        icon.className = 'bi bi-eye';
    }
});
</script>

</body>
</html>
