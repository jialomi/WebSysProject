<?php
/**
 * car-details.php
 * DriveEasy Car Rentals — Single Car Detail Page
 *
 * Features:
 *  - Bootstrap image carousel (multiple placeholder images)
 *  - Full specs table
 *  - Booking form (redirects to booking.php with car_id)
 *  - Availability badge
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Validate and fetch car ID from query string
$carId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$carId) {
    header('Location: /fleet.php');
    exit;
}

// Fetch car record using a prepared statement
$stmt = $pdo->prepare("SELECT * FROM cars WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $carId]);
$car = $stmt->fetch();

if (!$car) {
    header('Location: /fleet.php?msg=not_found');
    exit;
}

$pageTitle = htmlspecialchars($car['brand'] . ' ' . $car['model']) . ' – DriveEasy Car Rentals';
$csrf      = generateCsrfToken();

// Build a set of carousel images (use same image with slight variation for demo)
$carouselImages = [
    $car['image_path'],
    $car['image_path'],  // In production, store multiple image paths in a car_images table
    $car['image_path'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Rent the <?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?> from DriveEasy at RM <?= number_format((float)$car['daily_rate'], 2) ?>/day.">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<main>

<!-- Page Hero -->
<div class="page-hero" role="banner">
    <div class="container">
        <nav aria-label="Breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="/index.php" class="text-warning">Home</a></li>
                <li class="breadcrumb-item"><a href="/fleet.php" class="text-warning">Fleet</a></li>
                <li class="breadcrumb-item active text-white">
                    <?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?>
                </li>
            </ol>
        </nav>
        <h1 class="fw-bold text-white mb-1">
            <?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?>
            <span class="text-warning"><?= htmlspecialchars($car['year']) ?></span>
        </h1>
        <div class="d-flex align-items-center gap-3 mt-2">
            <?php if ($car['status'] === 'available'): ?>
                <span class="availability-available">
                    <i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>Available
                </span>
            <?php else: ?>
                <span class="availability-unavailable">
                    <i class="bi bi-x-circle-fill me-1" aria-hidden="true"></i>Unavailable
                </span>
            <?php endif; ?>
            <span class="badge bg-warning text-dark fw-semibold px-3 py-2">
                <?= htmlspecialchars(ucfirst($car['type'])) ?>
            </span>
        </div>
    </div>
</div>

<div class="container py-5">
    <div class="row g-5">

        <!-- ── LEFT: Carousel + Description ──────────────── -->
        <div class="col-12 col-lg-7">

            <!-- Bootstrap Carousel -->
            <div id="carCarousel" class="carousel slide car-detail-carousel mb-4"
                 data-bs-ride="carousel" aria-label="<?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?> images">
                <div class="carousel-indicators">
                    <?php foreach ($carouselImages as $i => $img): ?>
                    <button type="button" data-bs-target="#carCarousel"
                            data-bs-slide-to="<?= $i ?>"
                            <?= $i === 0 ? 'class="active" aria-current="true"' : '' ?>
                            aria-label="Slide <?= $i + 1 ?>"></button>
                    <?php endforeach; ?>
                </div>

                <div class="carousel-inner">
                    <?php foreach ($carouselImages as $i => $img): ?>
                    <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                        <img src="<?= htmlspecialchars($img) ?>"
                             class="d-block w-100"
                             alt="<?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?> – view <?= $i + 1 ?>"
                             onerror="this.src='/assets/images/placeholder.jpg'">
                    </div>
                    <?php endforeach; ?>
                </div>

                <button class="carousel-control-prev" type="button"
                        data-bs-target="#carCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button"
                        data-bs-target="#carCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>

            <!-- Description -->
            <h2 class="h5 fw-bold mb-3">About This Car</h2>
            <p class="text-muted"><?= htmlspecialchars($car['description']) ?></p>

            <!-- Full Specs -->
            <h2 class="h5 fw-bold mt-4 mb-3">Full Specifications</h2>
            <div class="car-spec-grid">
                <?php
                $specs = [
                    ['bi-car-front',    'Brand',        $car['brand']],
                    ['bi-tags',         'Model',        $car['model']],
                    ['bi-calendar',     'Year',         $car['year']],
                    ['bi-grid',         'Type',         ucfirst($car['type'])],
                    ['bi-people',       'Seats',        $car['seats'] . ' passengers'],
                    ['bi-gear',         'Transmission', ucfirst($car['transmission'])],
                    ['bi-fuel-pump',    'Fuel Type',    $car['fuel_type']],
                    ['bi-speedometer',  'Mileage',      $car['mileage'] ?? 'Unlimited'],
                ];
                foreach ($specs as $spec): ?>
                <div class="car-spec-item">
                    <i class="bi <?= $spec[0] ?>" aria-hidden="true"></i>
                    <div>
                        <div style="font-size:0.72rem;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;">
                            <?= htmlspecialchars($spec[1]) ?>
                        </div>
                        <div class="fw-semibold" style="font-size:0.88rem;">
                            <?= htmlspecialchars((string)$spec[2]) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        </div>

        <!-- ── RIGHT: Price Card + Quick Booking ─────────── -->
        <div class="col-12 col-lg-5">

            <!-- Price Card -->
            <div class="bg-white rounded-3 shadow-sm p-4 mb-4 border">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="car-card__price fs-2">
                            RM <?= number_format((float)$car['daily_rate'], 2) ?>
                        </div>
                        <div class="text-muted small">per day · inclusive of insurance</div>
                    </div>
                    <span class="badge bg-warning text-dark fs-6 px-3 py-2">
                        <?= htmlspecialchars(ucfirst($car['type'])) ?>
                    </span>
                </div>

                <hr>

                <!-- Inclusions -->
                <ul class="list-unstyled small mb-4">
                    <?php
                    $inclusions = [
                        'Comprehensive insurance included',
                        'Free cancellation (24 hrs notice)',
                        'Unlimited mileage',
                        '24/7 roadside assistance',
                    ];
                    foreach ($inclusions as $inc): ?>
                    <li class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i>
                        <?= htmlspecialchars($inc) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($car['status'] === 'available'): ?>
                    <a href="/booking.php?car_id=<?= (int)$car['id'] ?>"
                       class="btn btn-warning w-100 fw-bold py-2 btn-lg">
                        <i class="bi bi-calendar-plus me-2" aria-hidden="true"></i>
                        Book This Car
                    </a>
                <?php else: ?>
                    <button class="btn btn-secondary w-100 fw-bold py-2 btn-lg" disabled>
                        Currently Unavailable
                    </button>
                <?php endif; ?>
            </div>

            <!-- Quick Booking Form -->
            <?php if ($car['status'] === 'available'): ?>
            <div class="bg-light rounded-3 p-4 border">
                <h2 class="h6 fw-bold mb-3">
                    <i class="bi bi-lightning-fill text-warning me-2" aria-hidden="true"></i>
                    Quick Date Check
                </h2>
                <form action="/booking.php" method="GET" class="needs-validation" novalidate>
                    <input type="hidden" name="car_id" value="<?= (int)$car['id'] ?>">
                    <div class="mb-3">
                        <label for="qb_start" class="form-label small fw-semibold">Pick-up Date</label>
                        <input type="date" class="form-control" id="qb_start"
                               name="start_date" required>
                        <div class="invalid-feedback">Required.</div>
                    </div>
                    <div class="mb-3">
                        <label for="qb_end" class="form-label small fw-semibold">Return Date</label>
                        <input type="date" class="form-control" id="qb_end"
                               name="end_date" required>
                        <div class="invalid-feedback">Required.</div>
                    </div>
                    <button type="submit" class="btn btn-dark w-100 fw-semibold">
                        Check Availability
                    </button>
                </form>
            </div>
            <?php endif; ?>

        </div>

    </div>
</div>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/main.js"></script>

<script>
// Quick-booking date validation
(function() {
    const s = document.getElementById('qb_start');
    const e = document.getElementById('qb_end');
    if (!s || !e) return;
    const today = new Date().toISOString().split('T')[0];
    s.setAttribute('min', today);
    s.addEventListener('change', function() {
        const d = new Date(this.value);
        d.setDate(d.getDate() + 1);
        const minEnd = d.toISOString().split('T')[0];
        e.setAttribute('min', minEnd);
        if (e.value && e.value <= this.value) e.value = minEnd;
    });
})();
</script>

</body>
</html>
