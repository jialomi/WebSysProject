<?php
/**
 * booking.php
 * DriveEasy Car Rentals — Booking Form with Cost Calculator
 *
 * Features:
 *  - Live JS cost calculator (days × daily rate)
 *  - Date picker validation (no past dates, end >= start)
 *  - Promo code AJAX check
 *  - Server-side availability check
 *  - CSRF protection
 *  - Requires login
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Must be logged in to book
requireLogin('/booking.php?' . http_build_query($_GET));

$pageTitle = 'Book a Car – DriveEasy Car Rentals';
$csrf      = generateCsrfToken();
$errors    = [];
$success   = false;

// ── Resolve car_id from GET or POST ────────────────────────
$carId = filter_input(INPUT_GET, 'car_id', FILTER_VALIDATE_INT)
      ?: filter_input(INPUT_POST, 'car_id', FILTER_VALIDATE_INT);

if (!$carId) {
    header('Location: /fleet.php');
    exit;
}

// Fetch car details
$stmt = $pdo->prepare("SELECT * FROM cars WHERE id = :id AND status = 'available' LIMIT 1");
$stmt->execute([':id' => $carId]);
$car = $stmt->fetch();

if (!$car) {
    header('Location: /fleet.php?msg=unavailable');
    exit;
}

// Pre-fill dates from GET (e.g. from car-details.php quick form)
$prefillStart = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$prefillEnd   = filter_input(INPUT_GET, 'end_date',   FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

// ── Handle POST (form submission) ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. CSRF validation
    validateCsrfToken();

    // 2. Sanitize and validate inputs
    $pickupLocation = trim(filter_input(INPUT_POST, 'pickup_location', FILTER_SANITIZE_SPECIAL_CHARS));
    $startDate      = trim(filter_input(INPUT_POST, 'start_date',      FILTER_SANITIZE_SPECIAL_CHARS));
    $endDate        = trim(filter_input(INPUT_POST, 'end_date',        FILTER_SANITIZE_SPECIAL_CHARS));
    $totalCost      = filter_input(INPUT_POST, 'total_cost', FILTER_VALIDATE_FLOAT);
    $promoCode      = trim(strtoupper(filter_input(INPUT_POST, 'promo_code', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''));
    $notes          = trim(filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

    // Validate required fields
    if (empty($pickupLocation)) $errors[] = 'Pick-up location is required.';
    if (empty($startDate))      $errors[] = 'Pick-up date is required.';
    if (empty($endDate))        $errors[] = 'Return date is required.';

    // Validate dates
    $today = new DateTime('today');
    $start = $startDate ? new DateTime($startDate) : null;
    $end   = $endDate   ? new DateTime($endDate)   : null;

    if ($start && $start < $today) {
        $errors[] = 'Pick-up date cannot be in the past.';
    }
    if ($start && $end && $end <= $start) {
        $errors[] = 'Return date must be after pick-up date.';
    }

    // Validate total cost
    if (!$totalCost || $totalCost <= 0) {
        $errors[] = 'Invalid total cost. Please ensure dates are selected.';
    }

    // Check for overlapping bookings (availability check)
    if ($start && $end && empty($errors)) {
        $overlapStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM bookings
             WHERE car_id = :car_id
               AND status != 'cancelled'
               AND start_date < :end_date
               AND end_date   > :start_date"
        );
        $overlapStmt->execute([
            ':car_id'     => $carId,
            ':end_date'   => $endDate,
            ':start_date' => $startDate,
        ]);
        if ((int)$overlapStmt->fetchColumn() > 0) {
            $errors[] = 'Sorry, this car is already booked for the selected dates. Please choose different dates.';
        }
    }

    // Validate and apply promo code (server-side)
    $discountAmount = 0.00;
    $validPromo     = false;
    if (!empty($promoCode) && empty($errors)) {
        $promoStmt = $pdo->prepare(
            "SELECT discount_percent FROM promo_codes
             WHERE code = :code AND is_active = 1 AND expiry_date >= CURDATE()
             LIMIT 1"
        );
        $promoStmt->execute([':code' => $promoCode]);
        $promoRow = $promoStmt->fetch();
        if ($promoRow) {
            $discountAmount = round($totalCost * ($promoRow['discount_percent'] / 100), 2);
            $totalCost      = round($totalCost - $discountAmount, 2);
            $validPromo     = true;
        } else {
            // Invalid promo: don't block booking, just ignore the code
            $promoCode = '';
        }
    }

    // Insert booking if no errors
    if (empty($errors)) {
        $insertStmt = $pdo->prepare(
            "INSERT INTO bookings
                (user_id, car_id, pickup_location, start_date, end_date, total_cost, status, promo_code, discount_amount, notes)
             VALUES
                (:user_id, :car_id, :pickup_location, :start_date, :end_date, :total_cost, 'pending', :promo_code, :discount_amount, :notes)"
        );
        $insertStmt->execute([
            ':user_id'         => currentUserId(),
            ':car_id'          => $carId,
            ':pickup_location' => $pickupLocation,
            ':start_date'      => $startDate,
            ':end_date'        => $endDate,
            ':total_cost'      => $totalCost,
            ':promo_code'      => $validPromo ? $promoCode : null,
            ':discount_amount' => $discountAmount,
            ':notes'           => $notes ?: null,
        ]);

        $bookingId = $pdo->lastInsertId();
        setFlash('success', 'Booking #' . $bookingId . ' submitted successfully! We will confirm it shortly.');
        header('Location: /my-bookings.php');
        exit;
    }
}

$pickupLocations = [
    'Changi Airport Terminal 1',
    'Changi Airport Terminal 2',
    'Changi Airport Terminal 3',
    'Marina Bay Sands',
    'Orchard Road (Orchard MRT)',
    'Jurong East',
    'Woodlands',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Book the <?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?> from DriveEasy Car Rentals.">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<main>

<!-- Page Hero -->
<div class="page-hero">
    <div class="container">
        <nav aria-label="Breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="/index.php" class="text-warning">Home</a></li>
                <li class="breadcrumb-item"><a href="/fleet.php" class="text-warning">Fleet</a></li>
                <li class="breadcrumb-item">
                    <a href="/car-details.php?id=<?= (int)$carId ?>" class="text-warning">
                        <?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?>
                    </a>
                </li>
                <li class="breadcrumb-item active text-white">Book</li>
            </ol>
        </nav>
        <h1 class="fw-bold text-white mb-0">Book Your Car</h1>
    </div>
</div>

<div class="container py-5">

    <?= renderFlash() ?>

    <!-- Server-side validation errors -->
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>Please fix the following errors:</strong>
        <ul class="mb-0 mt-1">
            <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="row g-5">

        <!-- ── BOOKING FORM ────────────────────────────── -->
        <div class="col-12 col-lg-7">
            <div class="bg-white rounded-3 shadow-sm p-4 border">
                <h2 class="h5 fw-bold mb-4">
                    <i class="bi bi-calendar-plus text-warning me-2" aria-hidden="true"></i>
                    Booking Details
                </h2>

                <!-- Car summary strip -->
                <div class="d-flex gap-3 bg-light rounded-3 p-3 mb-4 align-items-center">
                    <img src="<?= htmlspecialchars($car['image_path']) ?>"
                         alt="<?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?>"
                         width="80" height="55"
                         style="object-fit:cover; border-radius:8px;"
                         onerror="this.src='/assets/images/placeholder.jpg'">
                    <div>
                        <div class="fw-bold"><?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?></div>
                        <div class="text-muted small">
                            <?= htmlspecialchars(ucfirst($car['type'])) ?> ·
                            <?= (int)$car['seats'] ?> seats ·
                            SGD <?= number_format((float)$car['daily_rate'], 2) ?>/day
                        </div>
                    </div>
                </div>

                <form action="/booking.php?car_id=<?= (int)$carId ?>" method="POST"
                      class="needs-validation" novalidate
                      id="bookingForm" data-daily-rate="<?= htmlspecialchars($car['daily_rate']) ?>">

                    <!-- CSRF token -->
                    <input type="hidden" name="csrf_token"
                           value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="car_id" value="<?= (int)$carId ?>">
                    <input type="hidden" name="total_cost" id="total_cost" value="0">
                    <input type="hidden" name="promo_code" id="applied_promo_code" value="">

                    <div class="row g-3">

                        <!-- Pick-up Location -->
                        <div class="col-12">
                            <label for="pickup_location" class="form-label fw-semibold">
                                Pick-up Location <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <select class="form-select" name="pickup_location"
                                    id="pickup_location" required>
                                <option value="" disabled selected>Select location…</option>
                                <?php foreach ($pickupLocations as $loc): ?>
                                <option value="<?= htmlspecialchars($loc) ?>"
                                    <?= (isset($pickupLocation) && $pickupLocation === $loc) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($loc) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a pick-up location.</div>
                        </div>

                        <!-- Dates -->
                        <div class="col-sm-6">
                            <label for="start_date" class="form-label fw-semibold">
                                Pick-up Date <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <input type="date" class="form-control"
                                   name="start_date" id="start_date"
                                   value="<?= htmlspecialchars($prefillStart ?: ($startDate ?? '')) ?>"
                                   required>
                            <div class="invalid-feedback">Please select a pick-up date.</div>
                        </div>

                        <div class="col-sm-6">
                            <label for="end_date" class="form-label fw-semibold">
                                Return Date <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <input type="date" class="form-control"
                                   name="end_date" id="end_date"
                                   value="<?= htmlspecialchars($prefillEnd ?: ($endDate ?? '')) ?>"
                                   required>
                            <div class="invalid-feedback">Return date must be after pick-up date.</div>
                        </div>

                        <!-- Promo Code -->
                        <div class="col-12">
                            <label for="promo_code" class="form-label fw-semibold">
                                Promo Code <span class="text-muted fw-normal">(optional)</span>
                            </label>
                            <div class="input-group">
                                <input type="text" class="form-control text-uppercase"
                                       id="promo_code" name="promo_code_display"
                                       placeholder="Enter code e.g. SAVE10"
                                       maxlength="30"
                                       autocomplete="off">
                                <button class="btn btn-outline-warning fw-semibold"
                                        type="button" id="applyPromoBtn">
                                    Apply
                                </button>
                            </div>
                            <div id="promoResult" class="mt-1" aria-live="polite"></div>
                        </div>

                        <!-- Notes -->
                        <div class="col-12">
                            <label for="notes" class="form-label fw-semibold">
                                Special Requests <span class="text-muted fw-normal">(optional)</span>
                            </label>
                            <textarea class="form-control" id="notes" name="notes"
                                      rows="3" maxlength="500"
                                      placeholder="e.g. child seat required, GPS unit…"><?= htmlspecialchars($notes ?? '') ?></textarea>
                        </div>

                        <!-- Submit -->
                        <div class="col-12 mt-2">
                            <button type="submit" class="btn btn-warning w-100 fw-bold py-2 btn-lg">
                                <i class="bi bi-check-circle me-2" aria-hidden="true"></i>
                                Confirm Booking
                            </button>
                            <p class="text-muted small text-center mt-2 mb-0">
                                By booking you agree to our
                                <a href="#" class="text-warning">Terms &amp; Conditions</a>.
                            </p>
                        </div>

                    </div>
                </form>
            </div>
        </div>

        <!-- ── COST SUMMARY ────────────────────────────── -->
        <div class="col-12 col-lg-5">
            <div class="cost-box sticky-top" style="top:80px;" role="region" aria-label="Cost summary">
                <h2 class="h6 fw-bold text-warning mb-4 text-uppercase letter-spacing-1">
                    <i class="bi bi-receipt me-2" aria-hidden="true"></i>
                    Cost Summary
                </h2>

                <div class="cost-box__row">
                    <span>Duration</span>
                    <span><strong id="calcDays">0</strong> day(s)</span>
                </div>
                <div class="cost-box__row">
                    <span>Daily Rate</span>
                    <span id="calcRate">SGD <?= number_format((float)$car['daily_rate'], 2) ?></span>
                </div>
                <div class="cost-box__row">
                    <span>Subtotal</span>
                    <span id="calcSubtotal">SGD 0.00</span>
                </div>
                <div class="cost-box__row text-success">
                    <span>Promo Discount</span>
                    <span id="calcDiscount">SGD 0.00</span>
                </div>

                <hr style="border-color:rgba(255,255,255,0.15);">

                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-white fw-bold">Total</span>
                    <div class="cost-box__total" aria-live="polite" id="calcTotal">
                        SGD 0.00
                    </div>
                </div>

                <p class="text-secondary small mt-3 mb-0">
                    <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                    Inclusive of insurance & taxes. Final cost shown above.
                </p>
            </div>

            <!-- Availability Notice -->
            <div class="alert alert-info mt-3 small" role="note">
                <i class="bi bi-calendar-check me-2" aria-hidden="true"></i>
                Availability is checked in real time. If your chosen dates are taken, you'll be notified.
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
