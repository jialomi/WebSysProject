<?php
/**
 * admin/manage-bookings.php
 * DriveEasy Car Rentals — Admin Booking Management
 *
 * Features:
 *  - View all bookings with filters (status, search)
 *  - Update booking status (pending → confirmed → cancelled)
 *  - View booking details inline
 */
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

requireAdmin();

$pageTitle = 'Manage Bookings – DriveEasy Admin';
$csrf      = generateCsrfToken();
$errors    = [];

// ── HANDLE STATUS UPDATE (POST) ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    $bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    $newStatus = filter_input(INPUT_POST, 'new_status', FILTER_SANITIZE_SPECIAL_CHARS);

    if ($bookingId && in_array($newStatus, ['pending','confirmed','cancelled'])) {
        $pdo->prepare("UPDATE bookings SET status = :status WHERE id = :id")
            ->execute([':status' => $newStatus, ':id' => $bookingId]);
        setFlash('success', 'Booking #' . $bookingId . ' updated to "' . $newStatus . '".');
    } else {
        setFlash('danger', 'Invalid update request.');
    }
    header('Location: /admin/manage-bookings.php');
    exit;
}

// ── Filter parameters ───────────────────────────────────────
$filterStatus = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'all';
$searchQuery  = trim(filter_input(INPUT_GET, 'q', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$editId       = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);

// ── Build query with optional filters ──────────────────────
$where  = ['1=1'];
$params = [];

if ($filterStatus !== 'all' && in_array($filterStatus, ['pending','confirmed','cancelled'])) {
    $where[]          = 'b.status = :status';
    $params[':status'] = $filterStatus;
}

if ($searchQuery) {
    $where[]  = '(u.name LIKE :q OR u.email LIKE :q OR c.brand LIKE :q OR c.model LIKE :q OR b.id LIKE :q)';
    $params[':q'] = '%' . $searchQuery . '%';
}

$whereClause = implode(' AND ', $where);

$bookingsStmt = $pdo->prepare(
    "SELECT b.id, b.status, b.total_cost, b.discount_amount, b.promo_code,
            b.start_date, b.end_date, b.pickup_location, b.created_at, b.notes,
            u.name AS customer_name, u.email AS customer_email,
            c.brand, c.model, c.year, c.daily_rate, c.image_path
     FROM bookings b
     JOIN users u ON u.id = b.user_id
     JOIN cars  c ON c.id = b.car_id
     WHERE {$whereClause}
     ORDER BY b.created_at DESC"
);
$bookingsStmt->execute($params);
$bookings = $bookingsStmt->fetchAll();

// Counts for the filter tabs
$countStmt = $pdo->query(
    "SELECT status, COUNT(*) as cnt FROM bookings GROUP BY status"
);
$counts = ['all' => 0, 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0];
foreach ($countStmt->fetchAll() as $row) {
    $counts[$row['status']] = (int)$row['cnt'];
    $counts['all'] += (int)$row['cnt'];
}

// Fetch booking for edit detail view
$editBooking = null;
if ($editId) {
    $estmt = $pdo->prepare(
        "SELECT b.*, u.name AS customer_name, u.email AS customer_email,
                c.brand, c.model
         FROM bookings b JOIN users u ON u.id=b.user_id JOIN cars c ON c.id=b.car_id
         WHERE b.id = :id LIMIT 1"
    );
    $estmt->execute([':id' => $editId]);
    $editBooking = $estmt->fetch();
}
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

    <!-- Sidebar -->
    <nav class="admin-sidebar d-none d-lg-flex flex-column" style="width:240px;flex-shrink:0;"
         aria-label="Admin navigation">
        <a href="/admin/dashboard.php" class="d-flex align-items-center gap-2 px-4 py-3 text-decoration-none mb-2">
            <span class="fw-bold fs-5">
                <span class="text-warning">Drive</span><span class="text-white">Easy</span>
            </span>
            <span class="badge bg-warning text-dark" style="font-size:0.6rem;">ADMIN</span>
        </a>
        <div class="px-3">
            <ul class="nav flex-column">
                <li><a class="nav-link" href="/admin/dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                <li><a class="nav-link" href="/admin/manage-cars.php"><i class="bi bi-car-front me-2"></i>Manage Cars</a></li>
                <li><a class="nav-link active" href="/admin/manage-bookings.php"><i class="bi bi-calendar-check me-2"></i>Manage Bookings</a></li>
                <li><a class="nav-link" href="/fleet.php"><i class="bi bi-grid me-2"></i>View Site</a></li>
            </ul>
            <hr class="border-secondary my-3">
            <ul class="nav flex-column">
                <li><a class="nav-link text-danger" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
        </div>
    </nav>

    <!-- Main content -->
    <div class="flex-grow-1 bg-light">
        <header class="bg-white shadow-sm px-4 py-3 d-flex justify-content-between align-items-center sticky-top">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#adminSidebar">
                    <i class="bi bi-list"></i>
                </button>
                <h1 class="h5 mb-0 fw-bold">Manage Bookings</h1>
            </div>
            <span class="badge bg-secondary"><?= count($bookings) ?> result(s)</span>
        </header>

        <main class="p-4">
            <?= renderFlash() ?>

            <!-- Filter Tabs + Search -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

                <!-- Status tabs -->
                <ul class="nav nav-pills gap-1" role="tablist">
                    <?php
                    $tabLabels = ['all' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled'];
                    foreach ($tabLabels as $val => $label):
                        $active = $filterStatus === $val ? 'btn-dark' : 'btn-outline-secondary';
                    ?>
                    <li class="nav-item">
                        <a href="/admin/manage-bookings.php?status=<?= $val ?><?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>"
                           class="btn btn-sm <?= $active ?>">
                            <?= htmlspecialchars($label) ?>
                            <span class="badge <?= $filterStatus === $val ? 'bg-warning text-dark' : 'bg-secondary' ?> ms-1">
                                <?= $counts[$val] ?>
                            </span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Search -->
                <form action="/admin/manage-bookings.php" method="GET" class="d-flex gap-2">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
                    <input type="search" class="form-control form-control-sm"
                           name="q" placeholder="Search customer, car…"
                           value="<?= htmlspecialchars($searchQuery) ?>">
                    <button type="submit" class="btn btn-sm btn-warning">
                        <i class="bi bi-search" aria-hidden="true"></i>
                    </button>
                </form>
            </div>

            <!-- Bookings Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Customer</th>
                                    <th scope="col">Car</th>
                                    <th scope="col">Dates</th>
                                    <th scope="col">Location</th>
                                    <th scope="col">Cost</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $b): ?>
                                <tr>
                                    <td class="fw-semibold text-nowrap">
                                        <a href="/admin/manage-bookings.php?edit=<?= (int)$b['id'] ?>"
                                           class="text-dark text-decoration-none">
                                            #<?= (int)$b['id'] ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-semibold small"><?= htmlspecialchars($b['customer_name']) ?></div>
                                        <div class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($b['customer_email']) ?></div>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold">
                                            <?= htmlspecialchars($b['brand'] . ' ' . $b['model']) ?>
                                        </div>
                                        <div class="text-muted" style="font-size:0.75rem;">
                                            SGD <?= number_format((float)$b['daily_rate'],2) ?>/day
                                        </div>
                                    </td>
                                    <td class="small">
                                        <div><?= htmlspecialchars(date('d M Y', strtotime($b['start_date']))) ?></div>
                                        <div class="text-muted">→ <?= htmlspecialchars(date('d M Y', strtotime($b['end_date']))) ?></div>
                                    </td>
                                    <td class="small text-muted">
                                        <?= htmlspecialchars($b['pickup_location']) ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">SGD <?= number_format((float)$b['total_cost'],2) ?></div>
                                        <?php if ($b['promo_code']): ?>
                                        <div class="text-success" style="font-size:0.72rem;">
                                            <?= htmlspecialchars($b['promo_code']) ?> applied
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge status-<?= htmlspecialchars($b['status']) ?> px-2 py-1 text-capitalize">
                                            <?= htmlspecialchars($b['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <!-- Quick status update buttons -->
                                        <div class="d-flex gap-1 flex-wrap">
                                            <?php if ($b['status'] !== 'confirmed'): ?>
                                            <form method="POST" action="/admin/manage-bookings.php" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                                <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                                <input type="hidden" name="new_status"  value="confirmed">
                                                <button type="submit" class="btn btn-xs btn-success btn-sm"
                                                        title="Confirm booking">
                                                    <i class="bi bi-check-lg" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <?php if ($b['status'] !== 'cancelled'): ?>
                                            <form method="POST" action="/admin/manage-bookings.php" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                                <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                                <input type="hidden" name="new_status"  value="cancelled">
                                                <button type="submit" class="btn btn-xs btn-danger btn-sm btn-cancel-booking"
                                                        title="Cancel booking">
                                                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <button class="btn btn-xs btn-outline-secondary btn-sm"
                                                    title="View details"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#detailModal<?= (int)$b['id'] ?>">
                                                <i class="bi bi-eye" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Detail Modal for this booking -->
                                <div class="modal fade" id="detailModal<?= (int)$b['id'] ?>"
                                     tabindex="-1"
                                     aria-labelledby="detailModalLabel<?= (int)$b['id'] ?>"
                                     aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="detailModalLabel<?= (int)$b['id'] ?>">
                                                    Booking #<?= (int)$b['id'] ?> Details
                                                </h5>
                                                <button type="button" class="btn-close"
                                                        data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <dl class="row small mb-0">
                                                    <dt class="col-sm-4">Customer</dt>
                                                    <dd class="col-sm-8"><?= htmlspecialchars($b['customer_name']) ?><br><span class="text-muted"><?= htmlspecialchars($b['customer_email']) ?></span></dd>
                                                    <dt class="col-sm-4">Car</dt>
                                                    <dd class="col-sm-8"><?= htmlspecialchars($b['brand'] . ' ' . $b['model']) ?></dd>
                                                    <dt class="col-sm-4">Pickup</dt>
                                                    <dd class="col-sm-8"><?= htmlspecialchars($b['pickup_location']) ?></dd>
                                                    <dt class="col-sm-4">Dates</dt>
                                                    <dd class="col-sm-8"><?= htmlspecialchars(date('d M Y', strtotime($b['start_date']))) ?> → <?= htmlspecialchars(date('d M Y', strtotime($b['end_date']))) ?></dd>
                                                    <dt class="col-sm-4">Total</dt>
                                                    <dd class="col-sm-8 fw-bold">SGD <?= number_format((float)$b['total_cost'],2) ?></dd>
                                                    <?php if ($b['discount_amount'] > 0): ?>
                                                    <dt class="col-sm-4">Discount</dt>
                                                    <dd class="col-sm-8 text-success">- SGD <?= number_format((float)$b['discount_amount'],2) ?> (<?= htmlspecialchars($b['promo_code']) ?>)</dd>
                                                    <?php endif; ?>
                                                    <dt class="col-sm-4">Status</dt>
                                                    <dd class="col-sm-8 text-capitalize"><?= htmlspecialchars($b['status']) ?></dd>
                                                    <dt class="col-sm-4">Created</dt>
                                                    <dd class="col-sm-8"><?= htmlspecialchars(date('d M Y H:i', strtotime($b['created_at']))) ?></dd>
                                                    <?php if ($b['notes']): ?>
                                                    <dt class="col-sm-4">Notes</dt>
                                                    <dd class="col-sm-8"><?= htmlspecialchars($b['notes']) ?></dd>
                                                    <?php endif; ?>
                                                </dl>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary"
                                                        data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>

                                <?php if (empty($bookings)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        <i class="bi bi-calendar-x fs-2 d-block mb-2" aria-hidden="true"></i>
                                        No bookings found.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/main.js"></script>

</body>
</html>
