<?php
/**
 * register.php
 * DriveEasy Car Rentals — User Registration
 *
 * Security:
 *  - Password hashed with PASSWORD_BCRYPT via password_hash()
 *  - Email uniqueness checked before insert
 *  - CSRF token validated
 *  - All inputs sanitized server-side
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect already logged-in users
if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$pageTitle = 'Create Account – DriveEasy Car Rentals';
$csrf      = generateCsrfToken();
$errors    = [];
$formData  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    // Collect and sanitize inputs
    $formData['name']  = trim(filter_input(INPUT_POST, 'name',  FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $formData['email'] = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
    $password          = $_POST['password']         ?? '';
    $confirmPassword   = $_POST['confirm_password'] ?? '';

    // Validate name
    if (empty($formData['name']))                $errors[] = 'Full name is required.';
    elseif (strlen($formData['name']) < 2)       $errors[] = 'Name must be at least 2 characters.';

    // Validate email
    if (empty($formData['email']))               $errors[] = 'Email address is required.';
    elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL))
                                                 $errors[] = 'Please enter a valid email address.';

    // Validate password
    if (empty($password))                        $errors[] = 'Password is required.';
    elseif (strlen($password) < 8)               $errors[] = 'Password must be at least 8 characters.';
    elseif (!preg_match('/[A-Z]/', $password))   $errors[] = 'Password must contain at least one uppercase letter.';
    elseif (!preg_match('/[0-9]/', $password))   $errors[] = 'Password must contain at least one number.';

    if ($password !== $confirmPassword)          $errors[] = 'Passwords do not match.';

    // Check email uniqueness (prepared statement)
    if (empty($errors)) {
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $checkStmt->execute([':email' => $formData['email']]);
        if ($checkStmt->fetch()) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    if (empty($errors)) {
        // Hash the password using BCRYPT
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $insertStmt = $pdo->prepare(
            "INSERT INTO users (name, email, password_hash, role)
             VALUES (:name, :email, :hash, 'user')"
        );
        $insertStmt->execute([
            ':name'  => $formData['name'],
            ':email' => $formData['email'],
            ':hash'  => $passwordHash,
        ]);

        $newUserId = (int)$pdo->lastInsertId();

        // Auto-login after registration
        session_regenerate_id(true);
        $_SESSION['user_id']   = $newUserId;
        $_SESSION['user_name'] = $formData['name'];
        $_SESSION['user_role'] = 'user';

        setFlash('success', 'Welcome to DriveEasy, ' . htmlspecialchars($formData['name']) . '! Your account has been created.');
        header('Location: /index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Create your DriveEasy Car Rentals account to start booking today.">
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
        <div class="col-12 col-sm-10 col-md-8 col-lg-6">

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">

                    <div class="text-center mb-4">
                        <h1 class="h4 fw-bold">
                            <span class="text-warning">Drive</span>Easy
                        </h1>
                        <p class="text-muted small">Create your free account</p>
                    </div>

                    <?= renderFlash() ?>

                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger small" role="alert">
                        <strong>Please fix the following:</strong>
                        <ul class="mb-0 mt-1">
                            <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <form action="/register.php" method="POST"
                          class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token"
                               value="<?= htmlspecialchars($csrf) ?>">

                        <!-- Full Name -->
                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold">
                                Full Name <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <input type="text" class="form-control" id="name" name="name"
                                   value="<?= htmlspecialchars($formData['name'] ?? '') ?>"
                                   autocomplete="name"
                                   maxlength="100" placeholder="e.g. Ahmad Razif"
                                   required minlength="2">
                            <div class="invalid-feedback">Please enter your full name.</div>
                        </div>

                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">
                                Email Address <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= htmlspecialchars($formData['email'] ?? '') ?>"
                                   autocomplete="username email"
                                   maxlength="150" placeholder="you@example.com"
                                   required>
                            <div class="invalid-feedback">Please enter a valid email address.</div>
                        </div>

                        <!-- Password -->
                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">
                                Password <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password"
                                       name="password"
                                       autocomplete="new-password"
                                       minlength="8" required
                                       placeholder="Min. 8 chars, 1 uppercase, 1 number">
                                <button class="btn btn-outline-secondary" type="button"
                                        id="togglePassword1" aria-label="Toggle password visibility">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Password must be at least 8 characters.</div>
                            <!-- Strength indicator -->
                            <div class="mt-2">
                                <div class="progress" style="height:4px;" aria-hidden="true">
                                    <div class="progress-bar" id="strengthBar" style="width:0%;transition:width 0.3s;"></div>
                                </div>
                                <small class="text-muted" id="strengthLabel"></small>
                            </div>
                        </div>

                        <!-- Confirm Password -->
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label fw-semibold">
                                Confirm Password <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password"
                                       name="confirm_password"
                                       autocomplete="new-password"
                                       required placeholder="Repeat password">
                                <button class="btn btn-outline-secondary" type="button"
                                        id="togglePassword2" aria-label="Toggle confirm password visibility">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Passwords must match.</div>
                        </div>

                        <!-- T&C agreement -->
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="agreeTerms"
                                   name="agree_terms" required>
                            <label class="form-check-label small" for="agreeTerms">
                                I agree to the <a href="#" class="text-warning">Terms &amp; Conditions</a>
                                and <a href="#" class="text-warning">Privacy Policy</a>
                            </label>
                            <div class="invalid-feedback">You must agree to the terms.</div>
                        </div>

                        <button type="submit" class="btn btn-warning w-100 fw-bold py-2">
                            <i class="bi bi-person-plus me-2" aria-hidden="true"></i>
                            Create Account
                        </button>
                    </form>

                    <hr class="my-4">

                    <p class="text-center text-muted small mb-0">
                        Already have an account?
                        <a href="/login.php" class="text-warning fw-semibold">Sign in here</a>
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
// Password toggle helpers
function makeToggle(btnId, inputId) {
    document.getElementById(btnId)?.addEventListener('click', function() {
        const inp  = document.getElementById(inputId);
        const icon = this.querySelector('i');
        inp.type = inp.type === 'password' ? 'text' : 'password';
        icon.className = inp.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
}
makeToggle('togglePassword1', 'password');
makeToggle('togglePassword2', 'confirm_password');

// Password strength indicator
document.getElementById('password')?.addEventListener('input', function() {
    const val = this.value;
    let score = 0;
    if (val.length >= 8)             score++;
    if (/[A-Z]/.test(val))           score++;
    if (/[0-9]/.test(val))           score++;
    if (/[^A-Za-z0-9]/.test(val))   score++;

    const bar   = document.getElementById('strengthBar');
    const label = document.getElementById('strengthLabel');
    const levels = [
        { pct: 0,   cls: '',         txt: '' },
        { pct: 25,  cls: 'bg-danger',  txt: 'Weak' },
        { pct: 50,  cls: 'bg-warning', txt: 'Fair' },
        { pct: 75,  cls: 'bg-info',    txt: 'Good' },
        { pct: 100, cls: 'bg-success', txt: 'Strong' },
    ];
    const lvl = levels[score] || levels[0];
    bar.style.width    = lvl.pct + '%';
    bar.className      = 'progress-bar ' + lvl.cls;
    label.textContent  = lvl.txt;
});
</script>

</body>
</html>
