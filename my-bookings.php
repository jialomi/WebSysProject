<?php
/**
 * my-bookings.php
 * DriveEasy Car Rentals — User Booking History
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin('/my-bookings.php');

$pageTitle = 'My Bookings – DriveEasy Car Rentals';
$userId    = currentUserId();
$csrf      = generateCsrfToken();
$errors    = [];

// ── Handle POST actions ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    $action    = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS);
    $bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);

    if ($action === 'cancel' && $bookingId) {
        $checkStmt = $pdo->prepare("SELECT id FROM bookings WHERE id = :id AND user_id = :uid AND status IN ('pending','confirmed') LIMIT 1");
        $checkStmt->execute([':id' => $bookingId, ':uid' => $userId]);
        if ($checkStmt->fetch()) {
            $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id = :id")->execute([':id' => $bookingId]);
            setFlash('warning', 'Booking #' . $bookingId . ' has been cancelled.');
        } else {
            setFlash('danger', 'Unable to cancel that booking.');
        }
        header('Location: /my-bookings.php');
        exit;
    }


}

$bookingsStmt = $pdo->prepare(
    "SELECT b.*, c.brand, c.model, c.year, c.type, c.image_path, c.daily_rate
     FROM bookings b
     JOIN cars c ON c.id = b.car_id
     WHERE b.user_id = :uid
     ORDER BY b.created_at DESC"
);
$bookingsStmt->execute([':uid' => $userId]);
$bookings = $bookingsStmt->fetchAll();
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
        <h1 class="fw-bold text-white mb-1">My Bookings</h1>
        <p class="text-secondary mb-0">Welcome back, <?= currentUserName() ?>!</p>
    </div>
</div>

<div class="container py-5">

    <?= renderFlash() ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($bookings)): ?>
    <div class="text-center py-5">
        <i class="bi bi-calendar-x fs-1 text-muted" aria-hidden="true"></i>
        <h2 class="h4 mt-3">No Bookings Yet</h2>
        <p class="text-muted">Looks like you haven't booked anything yet. Browse our fleet and hit the road!</p>
        <a href="/fleet.php" class="btn btn-warning fw-bold mt-2">Browse Fleet</a>
    </div>
    <?php else: ?>

    <div class="row g-3 mb-5">
        <?php
        $counts = ['pending' => 0, 'confirmed' => 0, 'cancelled' => 0];
        foreach ($bookings as $b) $counts[$b['status']] = ($counts[$b['status']] ?? 0) + 1;
        $totalSpent = array_sum(array_column(array_filter($bookings, fn($b) => $b['status'] !== 'cancelled'), 'total_cost'));
        $statCards = [
            ['Total Bookings', count($bookings),        'bi-calendar-check', 'primary'],
            ['Confirmed',      $counts['confirmed'],    'bi-check-circle',   'success'],
            ['Pending',        $counts['pending'],      'bi-hourglass-split','warning'],
            ['Total Spent',    'SGD '.number_format($totalSpent,2), 'bi-wallet2', 'info'],
        ];
        foreach ($statCards as $sc): ?>
        <div class="col-6 col-md-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <i class="bi <?= $sc[3] ?> fs-3 text-<?= $sc[3] ?> mb-2" aria-hidden="true"></i>
                    <div class="fw-bold fs-5"><?= htmlspecialchars((string)$sc[1]) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($sc[0]) ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php foreach ($bookings as $booking): ?>
    <div class="card border-0 shadow-sm mb-4 overflow-hidden">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
            <div>
                <span class="fw-bold">Booking #<?= (int)$booking['id'] ?></span>
                <span class="text-muted small ms-2"><?= htmlspecialchars(date('d M Y', strtotime($booking['created_at']))) ?></span>
            </div>
            <span class="badge status-<?= htmlspecialchars($booking['status']) ?> px-3 py-2 text-capitalize"><?= htmlspecialchars($booking['status']) ?></span>
        </div>

        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-12 col-sm-3 col-md-2 text-center">
                    <img src="/<?= htmlspecialchars($booking['image_path']) ?>"
                        alt="" class="img-fluid rounded"
                        style="max-height:70px; object-fit:cover;"
                        onerror="this.src='/assets/images/placeholder.jpg'">
                </div>

                <div class="col-12 col-sm-9 col-md-4">
                    <div class="fw-bold">
                        <?= htmlspecialchars($booking['brand'] . ' ' . $booking['model']) ?> <?= htmlspecialchars($booking['year']) ?>
                    </div>
                    <div class="text-muted small">
                        <i class="bi bi-geo-alt me-1" aria-hidden="true"></i>
                        <?= htmlspecialchars($booking['pickup_location']) ?>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="small text-muted">Pick-up</div>
                    <div class="fw-semibold small"><?= htmlspecialchars(date('d M Y', strtotime($booking['start_date']))) ?></div>
                    <div class="small text-muted mt-1">Return</div>
                    <div class="fw-semibold small"><?= htmlspecialchars(date('d M Y', strtotime($booking['end_date']))) ?></div>
                </div>

                <div class="col-6 col-md-3 text-end">
                    <div class="fs-5 fw-bold text-dark">SGD <?= number_format((float)$booking['total_cost'], 2) ?></div>
                    <?php if ($booking['discount_amount'] > 0): ?>
                    <div class="text-success small">
                        <i class="bi bi-tag me-1" aria-hidden="true"></i> Saved SGD <?= number_format((float)$booking['discount_amount'], 2) ?>
                        <?php if ($booking['promo_code']): ?>(<?= htmlspecialchars($booking['promo_code']) ?>)<?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card-footer bg-white border-top d-flex flex-wrap gap-2 align-items-center">
            <a href="/car-details.php?id=<?= (int)$booking['car_id'] ?>" class="btn btn-outline-dark btn-sm">
                <i class="bi bi-eye me-1" aria-hidden="true"></i>View Car
            </a>

            <?php if (in_array($booking['status'], ['pending', 'confirmed'])): ?>
            <form method="POST" action="/my-bookings.php" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm btn-cancel-booking">
                    <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancel
                </button>
            </form>
            <?php endif; ?>


        </div>
    </div>


    <?php endforeach; ?>
    <?php endif; ?>

</div></main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/main.js"></script>

</body>
</html>