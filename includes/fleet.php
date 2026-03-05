<?php
/**
 * fleet.php
 * DriveEasy Car Rentals — Full Car Catalogue
 *
 * Features:
 *  - JS live filter by car type and max price (no page reload)
 *  - Sort by price / name
 *  - Text search by brand/model
 *  - All cars pulled from MySQL via PDO
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Our Fleet – DriveEasy Car Rentals';

// Fetch all cars (both available and not, for display purposes)
$stmt = $pdo->prepare("SELECT * FROM cars ORDER BY type, daily_rate ASC");
$stmt->execute();
$allCars = $stmt->fetchAll();

// Max daily rate for the price slider
$maxRate = (float)($pdo->query("SELECT MAX(daily_rate) FROM cars")->fetchColumn() ?: 500);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Browse DriveEasy's full fleet of sedans, SUVs, MPVs and sports cars available for rent across Singapore.">
    <title><?= htmlspecialchars($pageTitle) ?></title>
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
                <li class="breadcrumb-item active text-white">Our Fleet</li>
            </ol>
        </nav>
        <h1 class="fw-bold text-white mb-1">Our Fleet</h1>
        <p class="text-secondary mb-0">
            <?= count($allCars) ?> vehicles available — filter to find your perfect ride.
        </p>
    </div>
</div>

<div class="container py-5">
    <div class="row g-4">

        <!-- ── FILTER SIDEBAR ──────────────────────────────── -->
        <aside class="col-12 col-lg-3" aria-label="Filter options">
            <div class="filter-bar sticky-top" style="top:80px;">
                <h2 class="h6 fw-bold text-uppercase mb-4">
                    <i class="bi bi-funnel me-2 text-warning" aria-hidden="true"></i>Filter Cars
                </h2>

                <!-- Text search -->
                <div class="mb-4">
                    <label for="searchQuery" class="form-label small fw-semibold">Search</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-search text-muted" aria-hidden="true"></i>
                        </span>
                        <input type="text" id="searchQuery" class="form-control border-start-0"
                               placeholder="Brand or model…" aria-label="Search cars">
                    </div>
                </div>

                <!-- Car type filter -->
                <div class="mb-4">
                    <label for="filterType" class="form-label small fw-semibold">Car Type</label>
                    <select id="filterType" class="form-select" aria-label="Filter by car type">
                        <option value="all">All Types</option>
                        <option value="sedan">Sedan</option>
                        <option value="SUV">SUV</option>
                        <option value="MPV">MPV</option>
                        <option value="sports">Sports</option>
                    </select>
                </div>

                <!-- Price range slider -->
                <div class="mb-4">
                    <label for="filterPrice" class="form-label small fw-semibold">
                        Max Price: <span id="filterPriceLabel" class="text-warning">Any price</span>
                    </label>
                    <input type="range" id="filterPrice" class="form-range"
                           min="50" max="<?= (int)ceil($maxRate / 50) * 50 ?>"
                           step="10"
                           value="<?= (int)ceil($maxRate / 50) * 50 ?>"
                           aria-label="Maximum price per day">
                    <div class="d-flex justify-content-between small text-muted mt-1">
                        <span>SGD 50</span>
                        <span>SGD <?= (int)ceil($maxRate / 50) * 50 ?></span>
                    </div>
                </div>

                <!-- Sort -->
                <div class="mb-4">
                    <label for="sortCars" class="form-label small fw-semibold">Sort By</label>
                    <select id="sortCars" class="form-select" aria-label="Sort cars">
                        <option value="default">Default</option>
                        <option value="price-asc">Price: Low to High</option>
                        <option value="price-desc">Price: High to Low</option>
                        <option value="name-asc">Name: A–Z</option>
                    </select>
                </div>

                <!-- Reset filters -->
                <button id="resetFilters" class="btn btn-outline-secondary w-100 btn-sm">
                    <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>
                    Reset Filters
                </button>
            </div>
        </aside>

        <!-- ── CAR GRID ────────────────────────────────────── -->
        <div class="col-12 col-lg-9">

            <!-- Results count -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <p class="text-muted mb-0 small" id="resultCount">
                    <?= count($allCars) ?> car<?= count($allCars) !== 1 ? 's' : '' ?> found
                </p>
            </div>

            <!-- No results message (hidden by default) -->
            <div id="noResults" class="text-center py-5" style="display:none;">
                <i class="bi bi-search fs-1 text-muted" aria-hidden="true"></i>
                <p class="mt-3 text-muted">No cars match your filters. Try adjusting your search.</p>
                <button id="resetFilters2" class="btn btn-warning mt-2">Reset Filters</button>
            </div>

            <!-- Cars grid -->
            <div class="row g-4" id="carsGrid">
                <?php if (empty($allCars)): ?>
                    <div class="col-12 text-center py-5">
                        <p class="text-muted">No cars in the fleet yet. Check back soon!</p>
                    </div>
                <?php else: ?>
                <?php foreach ($allCars as $car): ?>
                <div class="col-12 col-sm-6 col-xl-4 car-filter-item"
                     data-type="<?= htmlspecialchars(strtolower($car['type'])) ?>"
                     data-price="<?= htmlspecialchars($car['daily_rate']) ?>"
                     data-search="<?= htmlspecialchars(strtolower($car['brand'] . ' ' . $car['model'] . ' ' . $car['type'])) ?>">

                    <article class="car-card h-100">
                        <!-- Image -->
                        <div class="car-card__img-wrap">
                            <img src="<?= htmlspecialchars($car['image_path']) ?>"
                                 alt="<?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?> rental car"
                                 loading="lazy"
                                 onerror="this.src='/assets/images/placeholder.jpg'">
                            <span class="car-card__badge badge-<?= htmlspecialchars($car['type']) ?>">
                                <?= htmlspecialchars(ucfirst($car['type'])) ?>
                            </span>
                            <?php if ($car['status'] === 'unavailable'): ?>
                            <span class="position-absolute top-0 end-0 badge bg-danger m-2">
                                Unavailable
                            </span>
                            <?php endif; ?>
                        </div>

                        <div class="card-body p-4 d-flex flex-column">
                            <h3 class="h6 fw-bold mb-0">
                                <?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?>
                            </h3>
                            <p class="text-muted small mb-3"><?= htmlspecialchars($car['year']) ?></p>

                            <!-- Specs -->
                            <div class="car-card__specs mb-3">
                                <span title="Seats">
                                    <i class="bi bi-people me-1" aria-hidden="true"></i>
                                    <?= (int)$car['seats'] ?>
                                </span>
                                <span title="Transmission">
                                    <i class="bi bi-gear me-1" aria-hidden="true"></i>
                                    <?= htmlspecialchars(ucfirst($car['transmission'])) ?>
                                </span>
                                <span title="Fuel type">
                                    <i class="bi bi-fuel-pump me-1" aria-hidden="true"></i>
                                    <?= htmlspecialchars($car['fuel_type']) ?>
                                </span>
                            </div>

                            <div class="mt-auto d-flex justify-content-between align-items-center">
                                <div class="car-card__price">
                                    SGD <?= number_format((float)$car['daily_rate'], 2) ?>
                                    <span>/ day</span>
                                </div>
                                <?php if ($car['status'] === 'available'): ?>
                                <a href="/car-details.php?id=<?= (int)$car['id'] ?>"
                                   class="btn btn-warning btn-sm fw-semibold">
                                    View &amp; Book
                                </a>
                                <?php else: ?>
                                <span class="badge bg-secondary">Unavailable</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/main.js"></script>

<script>
// Wire up the second reset button
document.getElementById('resetFilters2')?.addEventListener('click', function() {
    document.getElementById('resetFilters')?.click();
});
document.getElementById('resetFilters')?.addEventListener('click', function() {
    document.getElementById('searchQuery').value = '';
    document.getElementById('filterType').value = 'all';
    const priceSlider = document.getElementById('filterPrice');
    priceSlider.value = priceSlider.max;
    document.getElementById('sortCars').value = 'default';
    // Trigger filter refresh
    priceSlider.dispatchEvent(new Event('input'));
});

// Pre-select filters if coming from hero search form
(function() {
    const params = new URLSearchParams(window.location.search);
    const t = params.get('type');
    if (t) {
        const sel = document.getElementById('filterType');
        if (sel) { sel.value = t; sel.dispatchEvent(new Event('change')); }
    }
})();
</script>

</body>
</html>
