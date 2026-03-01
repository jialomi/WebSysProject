<?php
/**
 * my-bookings.php
 * DriveEasy Car Rentals — User Booking History
 *
 * Shows the logged-in user's bookings with status badges.
 * Allows cancellation of pending/confirmed bookings.
 * After a confirmed booking, user can submit a testimonial review.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Must be logged in
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

    // ── Cancel Booking ──────────────────────────────────────
    if ($action === 'cancel' && $bookingId) {
        // Verify the booking belongs to this user and is cancellable
        $checkStmt = $pdo->prepare(
            "SELECT id FROM bookings
             WHERE id = :id AND user_id = :uid
               AND status IN ('pending','confirmed')
             LIMIT 1"
        );
        $checkStmt->execute([':id' => $bookingId, ':uid' => $userId]);
        if ($checkStmt->fetch()) {
            $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id = :id")
                ->execute([':id' => $bookingId]);
            setFlash('warning', 'Booking #' . $bookingId . ' has been cancelled.');
        } else {
            setFlash('danger', 'Unable to cancel that booking.');
        }
        header('Location: /my-bookings.php');
        exit;
    }

    // ── Submit Review / Testimonial ─────────────────────────
    if ($action === 'review' && $bookingId) {
        $rating  = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
        $message = trim(filter_input(INPUT_POST, 'message', FILTER_SANITIZE_SPECIAL_CHARS));

        if ($rating < 1 || $rating > 5) $errors[] = 'Please select a star rating.';
        if (strlen($message) < 10)      $errors[] = 'Review must be at least 10 characters.';

        // Verify booking belongs to user and is confirmed
        $checkStmt = $pdo->prepare(
            "SELECT id FROM bookings WHERE id = :id AND user_id = :uid AND status = 'confirmed'"
        );
        $checkStmt->execute([':id' => $bookingId, ':uid' => $userId]);
        $validBooking = $checkStmt->fetch();

        if (empty($errors) && $validBooking) {
            // Check if review already submitted for this booking
            $existsStmt = $pdo->prepare(
                "SELECT id FROM testimonials WHERE booking_id = :bid LIMIT 1"
            );
            $existsStmt->execute([':bid' => $bookingId]);
            if ($existsStmt->fetch()) {
                setFlash('warning', 'You have already submitted a review for this booking.');
            } else {
                $pdo->prepare(
                    "INSERT INTO testimonials (user_id, booking_id, rating, message, is_active)
                     VALUES (:uid, :bid, :rating, :msg, 0)"
                )->execute([
                    ':uid'    => $userId,
                    ':bid'    => $bookingId,
                    ':rating' => $rating,
                    ':msg'    => $message,
                ]);
                setFlash('success', 'Thank you for your review! It will appear once approved.');
            }
        } elseif (empty($errors)) {
            setFlash('danger', 'Review could not be submitted for this booking.');
        }

        if (empty($errors)) {
            header('Location: /my-bookings.php');
            exit;
        }
    }
}

// ── Fetch user's bookings (with car info) ───────────────────
$bookingsStmt = $pdo->prepare(
    "SELECT b.*, c.brand, c.model, c.year, c.type, c.image_path, c.daily_rate,
            (SELECT id FROM testimonials WHERE booking_id = b.id LIMIT 1) AS reviewed
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
    <!-- Empty state -->
    <div class="text-center py-5">
        <i class="bi bi-calendar-x fs-1 text-muted" aria-hidden="true"></i>
        <h2 class="h4 mt-3">No Bookings Yet</h2>
        <p class="text-muted">Looks like you haven't booked anything yet. Browse our fleet and hit the road!</p>
        <a href="/fleet.php" class="btn btn-warning fw-bold mt-2">Browse Fleet</a>
    </div>

    <?php else: ?>

    <!-- Stats row -->
    <div class="row g-3 mb-5">
        <?php
        $counts = ['pending' => 0, 'confirmed' => 0, 'cancelled' => 0];
        foreach ($bookings as $b) $counts[$b['status']] = ($counts[$b['status']] ?? 0) + 1;
        $totalSpent = array_sum(array_column(
            array_filter($bookings, fn($b) => $b['status'] !== 'cancelled'),
            'total_cost'
        ));
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

    <!-- Bookings Table / Cards -->
    <?php foreach ($bookings as $booking): ?>
    <div class="card border-0 shadow-sm mb-4 overflow-hidden">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
            <div>
                <span class="fw-bold">Booking #<?= (int)$booking['id'] ?></span>
                <span class="text-muted small ms-2">
                    <?= htmlspecialchars(date('d M Y', strtotime($booking['created_at']))) ?>
                </span>
            </div>
            <span class="badge status-<?= htmlspecialchars($booking['status']) ?> px-3 py-2 text-capitalize">
                <?= htmlspecialchars($booking['status']) ?>
            </span>
        </div>

        <div class="card-body">
            <div class="row g-3 align-items-center">
                <!-- Car image & name -->
                <div class="col-12 col-sm-3 col-md-2 text-center">
                    <img src="<?= htmlspecialchars($booking['image_path']) ?>"
                         alt="<?= htmlspecialchars($booking['brand'] . ' ' . $booking['model']) ?>"
                         class="img-fluid rounded"
                         style="max-height:70px; object-fit:cover;"
                         onerror="this.src='/assets/images/placeholder.jpg'">
                </div>

                <div class="col-12 col-sm-9 col-md-4">
                    <div class="fw-bold">
                        <?= htmlspecialchars($booking['brand'] . ' ' . $booking['model']) ?>
                        <?= htmlspecialchars($booking['year']) ?>
                    </div>
                    <div class="text-muted small">
                        <i class="bi bi-geo-alt me-1" aria-hidden="true"></i>
                        <?= htmlspecialchars($booking['pickup_location']) ?>
                    </div>
                </div>

                <!-- Dates -->
                <div class="col-6 col-md-3">
                    <div class="small text-muted">Pick-up</div>
                    <div class="fw-semibold small">
                        <?= htmlspecialchars(date('d M Y', strtotime($booking['start_date']))) ?>
                    </div>
                    <div class="small text-muted mt-1">Return</div>
                    <div class="fw-semibold small">
                        <?= htmlspecialchars(date('d M Y', strtotime($booking['end_date']))) ?>
                    </div>
                </div>

                <!-- Cost -->
                <div class="col-6 col-md-3 text-end">
                    <div class="fs-5 fw-bold text-dark">
                        SGD <?= number_format((float)$booking['total_cost'], 2) ?>
                    </div>
                    <?php if ($booking['discount_amount'] > 0): ?>
                    <div class="text-success small">
                        <i class="bi bi-tag me-1" aria-hidden="true"></i>
                        Saved SGD <?= number_format((float)$booking['discount_amount'], 2) ?>
                        <?php if ($booking['promo_code']): ?>
                        (<?= htmlspecialchars($booking['promo_code']) ?>)
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Actions footer -->
        <div class="card-footer bg-white border-top d-flex flex-wrap gap-2 align-items-center">

            <a href="/car-details.php?id=<?= (int)$booking['car_id'] ?>"
               class="btn btn-outline-dark btn-sm">
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

            <!-- Review button for confirmed bookings -->
            <?php if ($booking['status'] === 'confirmed' && !$booking['reviewed']): ?>
            <button class="btn btn-outline-warning btn-sm ms-auto"
                    data-bs-toggle="modal"
                    data-bs-target="#reviewModal<?= (int)$booking['id'] ?>">
                <i class="bi bi-star me-1" aria-hidden="true"></i>Write Review
            </button>
            <?php elseif ($booking['reviewed']): ?>
            <span class="ms-auto text-success small">
                <i class="bi bi-check-circle me-1" aria-hidden="true"></i>Reviewed
            </span>
            <?php endif; ?>

        </div>
    </div>

    <!-- Review Modal -->
    <?php if ($booking['status'] === 'confirmed' && !$booking['reviewed']): ?>
    <div class="modal fade" id="reviewModal<?= (int)$booking['id'] ?>" tabindex="-1"
         aria-labelledby="reviewModalLabel<?= (int)$booking['id'] ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reviewModalLabel<?= (int)$booking['id'] ?>">
                        Review: <?= htmlspecialchars($booking['brand'] . ' ' . $booking['model']) ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="/my-bookings.php" class="needs-validation" novalidate>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="action" value="review">
                        <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
                        <input type="hidden" name="rating" id="rating" value="">

                        <!-- Star selector -->
                        <div class="mb-3 text-center">
                            <label class="form-label fw-semibold d-block">Your Rating</label>
                            <div class="fs-2">
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                <i class="bi bi-star star-input text-secondary"
                                   data-value="<?= $s ?>"
                                   style="cursor:pointer;" role="button"
                                   aria-label="<?= $s ?> star<?= $s > 1 ? 's' : '' ?>">
                                </i>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label fw-semibold">Your Review</label>
                            <textarea class="form-control" name="message" id="message"
                                      rows="4" minlength="10" maxlength="500"
                                      placeholder="Tell us about your experience…" required></textarea>
                            <div class="invalid-feedback">Please write at least 10 characters.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning fw-bold">Submit Review</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php endforeach; ?>
    <?php endif; ?>

</div><!-- /.container -->

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/main.js"></script>

</body>
</html>
