<?php
/**
 * profile.php
 * DriveEasy Car Rentals — User Profile Settings
 *
 * Allows logged-in users to update their name and email,
 * and change their password.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin('/profile.php');

$pageTitle = 'My Profile – DriveEasy Car Rentals';
$userId    = currentUserId();
$csrf      = generateCsrfToken();
$errors    = [];

// Fetch current user data
$stmt = $pdo->prepare("SELECT id, name, email, created_at FROM users WHERE id = :id");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

// ── Handle profile update ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS);

    // ── Update name/email ───────────────────────────────────
    if ($action === 'update_profile') {
        $name  = trim(filter_input(INPUT_POST, 'name',  FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');

        if (empty($name))                              $errors[] = 'Name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';

        // Check email not taken by another user
        if (empty($errors)) {
            $checkStmt = $pdo->prepare(
                "SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1"
            );
            $checkStmt->execute([':email' => $email, ':id' => $userId]);
            if ($checkStmt->fetch()) $errors[] = 'That email is already in use.';
        }

        if (empty($errors)) {
            $pdo->prepare("UPDATE users SET name = :name, email = :email WHERE id = :id")
                ->execute([':name' => $name, ':email' => $email, ':id' => $userId]);
            $_SESSION['user_name'] = $name; // Keep session in sync
            setFlash('success', 'Profile updated successfully.');
            header('Location: /profile.php');
            exit;
        }
    }

    // ── Change password ─────────────────────────────────────
    if ($action === 'change_password') {
        $current  = $_POST['current_password']  ?? '';
        $newPw    = $_POST['new_password']       ?? '';
        $confirm  = $_POST['confirm_password']   ?? '';

        // Fetch current hash
        $hashStmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
        $hashStmt->execute([':id' => $userId]);
        $hashRow  = $hashStmt->fetch();

        if (!password_verify($current, $hashRow['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        }
        if (strlen($newPw) < 8)               $errors[] = 'New password must be at least 8 characters.';
        if ($newPw !== $confirm)              $errors[] = 'New passwords do not match.';

        if (empty($errors)) {
            $newHash = password_hash($newPw, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password_hash = :h WHERE id = :id")
                ->execute([':h' => $newHash, ':id' => $userId]);
            setFlash('success', 'Password changed successfully.');
            header('Location: /profile.php');
            exit;
        }
    }

    // Re-fetch user after failed update to keep form values
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();
}

// Booking stats
$bookingCount = (int)$pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = :id")
    ->execute([':id' => $userId]) ?: 0;
$bookingCountStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = :id");
$bookingCountStmt->execute([':id' => $userId]);
$bookingCount = (int)$bookingCountStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<main>

<div class="page-hero">
    <div class="container">
        <h1 class="fw-bold text-white mb-1">My Profile</h1>
        <p class="text-secondary mb-0">Manage your account details</p>
    </div>
</div>

<div class="container py-5">

    <?= renderFlash() ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- Profile sidebar -->
        <div class="col-12 col-md-4 col-lg-3">
            <div class="card border-0 shadow-sm text-center p-4">
                <div class="bg-warning rounded-circle mx-auto mb-3 fw-bold fs-2 d-flex align-items-center justify-content-center"
                     style="width:80px;height:80px;" aria-hidden="true">
                    <?= mb_strtoupper(mb_substr(htmlspecialchars($user['name']), 0, 1)) ?>
                </div>
                <h2 class="h6 fw-bold mb-0"><?= htmlspecialchars($user['name']) ?></h2>
                <p class="text-muted small mb-3"><?= htmlspecialchars($user['email']) ?></p>
                <div class="badge bg-light text-dark border mb-3">
                    <i class="bi bi-person me-1" aria-hidden="true"></i>Customer
                </div>
                <div class="text-muted small">
                    Member since <?= htmlspecialchars(date('M Y', strtotime($user['created_at']))) ?>
                </div>
                <hr>
                <div>
                    <div class="fw-bold"><?= $bookingCount ?></div>
                    <div class="text-muted small">Total Bookings</div>
                </div>
            </div>
        </div>

        <!-- Settings panels -->
        <div class="col-12 col-md-8 col-lg-9">

            <!-- Update Profile -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold py-3">
                    <i class="bi bi-person-fill me-2 text-warning" aria-hidden="true"></i>
                    Personal Information
                </div>
                <div class="card-body p-4">
                    <form action="/profile.php" method="POST"
                          class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token"
                               value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label for="name" class="form-label fw-semibold">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name"
                                       value="<?= htmlspecialchars($user['name']) ?>"
                                       required maxlength="100">
                                <div class="invalid-feedback">Name is required.</div>
                            </div>
                            <div class="col-sm-6">
                                <label for="email" class="form-label fw-semibold">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?= htmlspecialchars($user['email']) ?>"
                                       required maxlength="150">
                                <div class="invalid-feedback">Valid email is required.</div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-warning fw-semibold">
                                    Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold py-3">
                    <i class="bi bi-shield-lock me-2 text-warning" aria-hidden="true"></i>
                    Change Password
                </div>
                <div class="card-body p-4">
                    <form action="/profile.php" method="POST"
                          class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token"
                               value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="action" value="change_password">

                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label for="current_password" class="form-label fw-semibold">
                                    Current Password
                                </label>
                                <input type="password" class="form-control"
                                       id="current_password" name="current_password"
                                       required autocomplete="current-password">
                                <div class="invalid-feedback">Required.</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label for="new_password" class="form-label fw-semibold">
                                    New Password
                                </label>
                                <input type="password" class="form-control"
                                       id="new_password" name="new_password"
                                       required minlength="8"
                                       autocomplete="new-password"
                                       placeholder="Min. 8 characters">
                                <div class="invalid-feedback">Min. 8 characters.</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label for="confirm_password" class="form-label fw-semibold">
                                    Confirm New Password
                                </label>
                                <input type="password" class="form-control"
                                       id="confirm_password" name="confirm_password"
                                       required autocomplete="new-password">
                                <div class="invalid-feedback">Passwords must match.</div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-outline-dark fw-semibold">
                                    <i class="bi bi-lock me-2" aria-hidden="true"></i>
                                    Update Password
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/main.js"></script>

</body>
</html>
