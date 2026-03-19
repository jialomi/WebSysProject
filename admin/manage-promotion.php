<?php
/**
 * admin/manage-promotion.php
 * DriveEasy Car Rentals — Admin Promo Code Management
 *
 * Features:
 * - View all promo codes with status
 * - Create new promo codes
 * - Edit existing promo codes (inline modal)
 * - Toggle active/inactive
 * - Delete promo codes
 * - Requires admin role
 */
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

requireAdmin();

$pageTitle = 'Manage Promotions – DriveEasy Admin';
$csrf      = generateCsrfToken();
$errors    = [];

// ── HANDLE POST ACTIONS ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // ── Create new promo code ────────────────────────────────
    if ($action === 'create') {
        $code     = strtoupper(trim($_POST['code'] ?? ''));
        $discount = floatval($_POST['discount_percent'] ?? 0);
        $expiry   = trim($_POST['expiry_date'] ?? '');
        $active   = isset($_POST['is_active']) ? 1 : 0;

        // Validate
        if (empty($code)) $errors[] = 'Promo code is required.';
        elseif (strlen($code) > 30) $errors[] = 'Promo code must be 30 characters or less.';
        elseif (!preg_match('/^[A-Z0-9]+$/', $code)) $errors[] = 'Promo code must contain only letters and numbers.';

        if ($discount <= 0 || $discount > 100) $errors[] = 'Discount must be between 0.01% and 100%.';
        if (empty($expiry)) $errors[] = 'Expiry date is required.';

        // Check for duplicate code
        if (empty($errors)) {
            $dupStmt = $pdo->prepare("SELECT id FROM promo_codes WHERE code = :code LIMIT 1");
            $dupStmt->execute([':code' => $code]);
            if ($dupStmt->fetch()) $errors[] = 'A promo code with this name already exists.';
        }

        if (empty($errors)) {
            $pdo->prepare(
                "INSERT INTO promo_codes (code, discount_percent, expiry_date, is_active) VALUES (:code, :discount, :expiry, :active)"
            )->execute([
                ':code'     => $code,
                ':discount' => $discount,
                ':expiry'   => $expiry,
                ':active'   => $active,
            ]);
            setFlash('success', 'Promo code "' . htmlspecialchars($code) . '" created successfully.');
            header('Location: /admin/manage-promotion.php');
            exit;
        }
    }

    // ── Update existing promo code ───────────────────────────
    if ($action === 'update') {
        $promoId  = intval($_POST['promo_id'] ?? 0);
        $code     = strtoupper(trim($_POST['code'] ?? ''));
        $discount = floatval($_POST['discount_percent'] ?? 0);
        $expiry   = trim($_POST['expiry_date'] ?? '');
        $active   = isset($_POST['is_active']) ? 1 : 0;

        if (empty($code)) $errors[] = 'Promo code is required.';
        elseif (strlen($code) > 30) $errors[] = 'Promo code must be 30 characters or less.';
        elseif (!preg_match('/^[A-Z0-9]+$/', $code)) $errors[] = 'Promo code must contain only letters and numbers.';

        if ($discount <= 0 || $discount > 100) $errors[] = 'Discount must be between 0.01% and 100%.';
        if (empty($expiry)) $errors[] = 'Expiry date is required.';

        // Check for duplicate code (excluding this promo)
        if (empty($errors)) {
            $dupStmt = $pdo->prepare("SELECT id FROM promo_codes WHERE code = :code AND id != :id LIMIT 1");
            $dupStmt->execute([':code' => $code, ':id' => $promoId]);
            if ($dupStmt->fetch()) $errors[] = 'Another promo code with this name already exists.';
        }

        if (empty($errors)) {
            $pdo->prepare(
                "UPDATE promo_codes SET code = :code, discount_percent = :discount, expiry_date = :expiry, is_active = :active WHERE id = :id"
            )->execute([
                ':code'     => $code,
                ':discount' => $discount,
                ':expiry'   => $expiry,
                ':active'   => $active,
                ':id'       => $promoId,
            ]);
            setFlash('success', 'Promo code "' . htmlspecialchars($code) . '" updated.');
            header('Location: /admin/manage-promotion.php');
            exit;
        }
    }

    // ── Toggle active/inactive ───────────────────────────────
    if ($action === 'toggle') {
        $promoId = intval($_POST['promo_id'] ?? 0);
        if ($promoId) {
            $pdo->prepare("UPDATE promo_codes SET is_active = NOT is_active WHERE id = :id")
                ->execute([':id' => $promoId]);
            setFlash('success', 'Promo code status toggled.');
        }
        header('Location: /admin/manage-promotion.php');
        exit;
    }

    // ── Delete promo code ────────────────────────────────────
    if ($action === 'delete') {
        $promoId = intval($_POST['promo_id'] ?? 0);
        if ($promoId) {
            $pdo->prepare("DELETE FROM promo_codes WHERE id = :id")
                ->execute([':id' => $promoId]);
            setFlash('success', 'Promo code deleted.');
        }
        header('Location: /admin/manage-promotion.php');
        exit;
    }
}

// ── Fetch all promo codes ───────────────────────────────────
$promos = $pdo->query("SELECT * FROM promo_codes ORDER BY is_active DESC, expiry_date ASC")->fetchAll();

// ── Counts for summary ──────────────────────────────────────
$totalCount  = count($promos);
$activeCount = 0;
foreach ($promos as $p) { if ($p['is_active']) $activeCount++; }

// ── Unread messages count for sidebar badge ─────────────────
$unreadMsgCount = (int) $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
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

<div class="d-flex" style="min-height:100vh;">
    <nav class="admin-sidebar d-none d-lg-flex flex-column" style="width:240px;flex-shrink:0;" aria-label="Admin navigation">
        <a href="/admin/dashboard.php" class="d-flex align-items-center gap-2 px-4 py-3 text-decoration-none mb-2">
            <span class="fw-bold fs-5"><span class="text-warning">Drive</span><span class="text-white">Easy</span></span>
            <span class="badge bg-warning text-dark" style="font-size:0.6rem;">ADMIN</span>
        </a>
        <div class="px-3">
            <ul class="nav flex-column">
                <li><a class="nav-link" href="/admin/dashboard.php"><i class="bi bi-speedometer2 me-2" aria-hidden="true"></i>Dashboard</a></li>
                <li><a class="nav-link" href="/admin/manage-cars.php"><i class="bi bi-car-front me-2" aria-hidden="true"></i>Manage Cars</a></li>
                <li><a class="nav-link" href="/admin/manage-bookings.php"><i class="bi bi-calendar-check me-2" aria-hidden="true"></i>Manage Bookings</a></li>
                <li>
                    <a class="nav-link" href="/admin/manage-messages.php">
                        <i class="bi bi-envelope me-2" aria-hidden="true"></i>Messages
                        <?php if ($unreadMsgCount > 0): ?>
                        <span class="badge bg-danger ms-auto"><?= $unreadMsgCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a class="nav-link active" href="/admin/manage-promotion.php">
                        <i class="bi bi-tag me-2" aria-hidden="true"></i>Promotions
                    </a>
                </li>
                <li><a class="nav-link" href="/fleet.php"><i class="bi bi-grid me-2" aria-hidden="true"></i>View Site</a></li>
            </ul>
            <hr class="border-secondary my-3">
            <ul class="nav flex-column">
                <li><a class="nav-link text-danger" href="/logout.php"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="flex-grow-1 bg-light">
        <header class="bg-white shadow-sm px-4 py-3 d-flex justify-content-between align-items-center sticky-top">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-label="Open menu">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <h1 class="h5 mb-0 fw-bold">Promotions</h1>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-success"><?= $activeCount ?> active</span>
                <span class="badge bg-secondary"><?= $totalCount ?> total</span>
                <span class="small text-muted">Welcome, <?= currentUserName() ?></span>
            </div>
        </header>

        <main class="p-4">
            <?= renderFlash() ?>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Please fix the following:</strong>
                <ul class="mb-0 mt-1">
                    <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <!-- Create new promo button -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <p class="text-muted mb-0 small"><?= $totalCount ?> promo code<?= $totalCount !== 1 ? 's' : '' ?></p>
                <button type="button" class="btn btn-warning fw-semibold" data-bs-toggle="modal" data-bs-target="#createPromoModal">
                    <i class="bi bi-plus-circle me-1" aria-hidden="true"></i> New Promo Code
                </button>
            </div>

            <!-- Promo codes table -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Code</th>
                                    <th scope="col">Discount</th>
                                    <th scope="col">Expiry Date</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($promos)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <i class="bi bi-tag fs-2 d-block mb-2" aria-hidden="true"></i>
                                        No promo codes found. Create one to get started.
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($promos as $p):
                                    $isActive  = ($p['is_active'] == 1);
                                    $isExpired = ($p['expiry_date'] < date('Y-m-d'));
                                ?>
                                <tr class="<?= !$isActive ? 'text-muted' : '' ?>">
                                    <td class="fw-semibold">#<?= (int)$p['id'] ?></td>
                                    <td>
                                        <span class="fw-bold font-monospace <?= $isActive ? '' : 'text-decoration-line-through' ?>">
                                            <?= htmlspecialchars($p['code']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success px-2 py-1 fs-6">
                                            <?= number_format((float)$p['discount_percent'], 0) ?>% OFF
                                        </span>
                                    </td>
                                    <td class="small text-nowrap">
                                        <?= htmlspecialchars(date('d M Y', strtotime($p['expiry_date']))) ?>
                                        <?php if ($isExpired): ?>
                                            <span class="badge bg-danger ms-1">Expired</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($isActive && !$isExpired): ?>
                                            <span class="badge bg-success px-2 py-1">Active</span>
                                        <?php elseif ($isActive && $isExpired): ?>
                                            <span class="badge bg-warning text-dark px-2 py-1">Expired</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary px-2 py-1">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <!-- Edit -->
                                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                                    title="Edit promo code"
                                                    aria-label="Edit promo #<?= (int)$p['id'] ?>"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editPromoModal<?= (int)$p['id'] ?>">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                            </button>

                                            <!-- Toggle active -->
                                            <form method="POST" action="/admin/manage-promotion.php" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                                <input type="hidden" name="promo_id" value="<?= (int)$p['id'] ?>">
                                                <input type="hidden" name="action" value="toggle">
                                                <?php if ($isActive): ?>
                                                <button type="submit" class="btn btn-warning btn-sm"
                                                        title="Deactivate"
                                                        aria-label="Deactivate promo #<?= (int)$p['id'] ?>">
                                                    <i class="bi bi-pause-circle" aria-hidden="true"></i>
                                                </button>
                                                <?php else: ?>
                                                <button type="submit" class="btn btn-success btn-sm"
                                                        title="Activate"
                                                        aria-label="Activate promo #<?= (int)$p['id'] ?>">
                                                    <i class="bi bi-play-circle" aria-hidden="true"></i>
                                                </button>
                                                <?php endif; ?>
                                            </form>

                                            <!-- Delete -->
                                            <form method="POST" action="/admin/manage-promotion.php" class="d-inline"
                                                  onsubmit="return confirm('Delete promo code <?= htmlspecialchars($p['code']) ?>?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                                <input type="hidden" name="promo_id" value="<?= (int)$p['id'] ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-danger btn-sm"
                                                        title="Delete promo code"
                                                        aria-label="Delete promo #<?= (int)$p['id'] ?>">
                                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<!-- ── Create Promo Modal ─────────────────────────────────────── -->
<div class="modal fade" id="createPromoModal" tabindex="-1" aria-labelledby="createPromoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="/admin/manage-promotion.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h2 class="h5 modal-title" id="createPromoLabel">
                        <i class="bi bi-plus-circle text-warning me-2" aria-hidden="true"></i>New Promo Code
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="createCode" class="form-label fw-semibold">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-uppercase font-monospace" id="createCode"
                               name="code" required maxlength="30" placeholder="e.g. SUMMER25"
                               pattern="[A-Za-z0-9]+" title="Letters and numbers only">
                        <div class="form-text">Letters and numbers only, max 30 characters.</div>
                    </div>
                    <div class="mb-3">
                        <label for="createDiscount" class="form-label fw-semibold">Discount (%) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="createDiscount"
                               name="discount_percent" required min="0.01" max="100" step="0.01" placeholder="e.g. 15">
                    </div>
                    <div class="mb-3">
                        <label for="createExpiry" class="form-label fw-semibold">Expiry Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="createExpiry"
                               name="expiry_date" required>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="createActive" name="is_active" checked>
                        <label class="form-check-label" for="createActive">Active immediately</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-semibold btn-sm">
                        <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Create Code
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Edit Promo Modals ──────────────────────────────────────── -->
<?php foreach ($promos as $p): ?>
<div class="modal fade" id="editPromoModal<?= (int)$p['id'] ?>" tabindex="-1"
     aria-labelledby="editPromoLabel<?= (int)$p['id'] ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="/admin/manage-promotion.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="promo_id" value="<?= (int)$p['id'] ?>">
                <div class="modal-header">
                    <h2 class="h5 modal-title" id="editPromoLabel<?= (int)$p['id'] ?>">
                        <i class="bi bi-pencil text-warning me-2" aria-hidden="true"></i>Edit: <?= htmlspecialchars($p['code']) ?>
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editCode<?= (int)$p['id'] ?>" class="form-label fw-semibold">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-uppercase font-monospace"
                               id="editCode<?= (int)$p['id'] ?>"
                               name="code" required maxlength="30"
                               value="<?= htmlspecialchars($p['code']) ?>"
                               pattern="[A-Za-z0-9]+" title="Letters and numbers only">
                    </div>
                    <div class="mb-3">
                        <label for="editDiscount<?= (int)$p['id'] ?>" class="form-label fw-semibold">Discount (%) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control"
                               id="editDiscount<?= (int)$p['id'] ?>"
                               name="discount_percent" required min="0.01" max="100" step="0.01"
                               value="<?= htmlspecialchars($p['discount_percent']) ?>">
                    </div>
                    <div class="mb-3">
                        <label for="editExpiry<?= (int)$p['id'] ?>" class="form-label fw-semibold">Expiry Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control"
                               id="editExpiry<?= (int)$p['id'] ?>"
                               name="expiry_date" required
                               value="<?= htmlspecialchars($p['expiry_date']) ?>">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox"
                               id="editActive<?= (int)$p['id'] ?>"
                               name="is_active" <?= $p['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="editActive<?= (int)$p['id'] ?>">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-semibold btn-sm">
                        <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Mobile offcanvas sidebar -->
<div class="offcanvas offcanvas-start bg-dark text-white" id="adminSidebar"
     tabindex="-1" aria-labelledby="adminSidebarLabel">
    <div class="offcanvas-header">
        <div class="offcanvas-title h2 fw-bold" id="adminSidebarLabel">
            <span class="text-warning">Drive</span>Easy Admin
        </div>
        <button type="button" class="btn-close btn-close-white"
                data-bs-dismiss="offcanvas" aria-label="Close admin menu"></button>
    </div>
    <div class="offcanvas-body p-3">
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link text-white" href="/admin/dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="/admin/manage-cars.php">Manage Cars</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="/admin/manage-bookings.php">Manage Bookings</a></li>
            <li class="nav-item">
                <a class="nav-link text-white" href="/admin/manage-messages.php">
                    Messages
                    <?php if ($unreadMsgCount > 0): ?>
                    <span class="badge bg-danger ms-1"><?= $unreadMsgCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item"><a class="nav-link text-white" href="/admin/manage-promotion.php">Promotions</a></li>
            <li class="nav-item"><a class="nav-link text-danger" href="/logout.php">Logout</a></li>
        </ul>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/main.js"></script>

</body>
</html>
