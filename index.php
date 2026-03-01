<?php
/**
 * index.php
 * DriveEasy Car Rentals — Landing Page
 *
 * Sections: Hero + Search Widget, Stats Bar, Featured Cars,
 *           Why Choose Us, Testimonials
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'DriveEasy Car Rentals – Affordable Car Hire in Singapore';
$csrf = generateCsrfToken();

// ── Fetch 3 featured (available) cars ──────────────────────
$stmt = $pdo->prepare(
    "SELECT * FROM cars WHERE status = 'available' ORDER BY daily_rate DESC LIMIT 3"
);
$stmt->execute();
$featuredCars = $stmt->fetchAll();

// ── Fetch active testimonials ───────────────────────────────
$stmt = $pdo->prepare(
    "SELECT t.*, u.name AS reviewer_name
     FROM testimonials t
     JOIN users u ON u.id = t.user_id
     WHERE t.is_active = 1
     ORDER BY t.created_at DESC
     LIMIT 3"
);
$stmt->execute();
$testimonials = $stmt->fetchAll();

// ── Stats ───────────────────────────────────────────────────
$totalCars  = $pdo->query("SELECT COUNT(*) FROM cars WHERE status='available'")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$totalBooks = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="DriveEasy – rent sedans, SUVs, MPVs and sports cars across Singapore at unbeatable daily rates.">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<main>

<!-- ── HERO SECTION ────────────────────────────────────────── -->
<section class="hero-section" aria-label="Hero banner">
    <div class="container">
        <div class="row align-items-center g-5">

            <!-- Left: headline & CTA -->
            <div class="col-lg-6 hero-content fade-in-up">
                <div class="hero-badge">
                    <i class="bi bi-star-fill me-1" aria-hidden="true"></i>
                    Singapore's Most Trusted Car Rental
                </div>
                <h1 class="hero-title">
                    Drive the Road<br>
                    <span>Your Way.</span>
                </h1>
                <p class="text-secondary mt-3 mb-4 fs-5" style="max-width:480px; color: rgba(255,255,255,0.7) !important;">
                    Choose from <?= (int)$totalCars ?> premium vehicles. Flexible rentals,
                    transparent pricing, and 24/7 support across Singapore.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="/fleet.php" class="btn btn-warning btn-lg fw-bold px-4">
                        Browse Fleet <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
                    </a>
                    <a href="/about.php" class="btn btn-outline-light btn-lg px-4">
                        Learn More
                    </a>
                </div>
            </div>

            <!-- Right: Quick Booking Widget -->
            <div class="col-lg-6 fade-in-up delay-2">
                <div class="booking-widget">
                    <h2 class="text-white fw-bold mb-4 fs-4">
                        <i class="bi bi-search me-2 text-warning" aria-hidden="true"></i>
                        Quick Search
                    </h2>
                    <!-- This form navigates to fleet.php with GET params -->
                    <form action="/fleet.php" method="GET" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                        <div class="row g-3">
                            <!-- Pick-up Location -->
                            <div class="col-12">
                                <label class="form-label" for="pickup_location">Pick-up Location</label>
                                <select class="form-select" name="location" id="pickup_location" required>
                                    <option value="" disabled selected>Select location…</option>
                                    <option>Changi Airport Terminal 1</option>
                                    <option>Changi Airport Terminal 2</option>
                                    <option>Changi Airport Terminal 3</option>
                                    <option>Marina Bay Sands</option>
                                    <option>Orchard Road (Orchard MRT)</option>
                                    <option>Jurong East</option>
                                    <option>Woodlands</option>
                                </select>
                                <div class="invalid-feedback">Please select a pick-up location.</div>
                            </div>

                            <!-- Dates -->
                            <div class="col-sm-6">
                                <label class="form-label" for="start_date_hero">Pick-up Date</label>
                                <input type="date" class="form-control" name="start_date"
                                       id="start_date_hero" required>
                                <div class="invalid-feedback">Please select a pick-up date.</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="end_date_hero">Return Date</label>
                                <input type="date" class="form-control" name="end_date"
                                       id="end_date_hero" required>
                                <div class="invalid-feedback">Please select a return date.</div>
                            </div>

                            <!-- Car Type -->
                            <div class="col-12">
                                <label class="form-label" for="car_type_hero">Car Type</label>
                                <select class="form-select" name="type" id="car_type_hero">
                                    <option value="">Any Type</option>
                                    <option value="sedan">Sedan</option>
                                    <option value="SUV">SUV</option>
                                    <option value="MPV">MPV</option>
                                    <option value="sports">Sports</option>
                                </select>
                            </div>

                            <!-- Submit -->
                            <div class="col-12">
                                <button type="submit" class="btn btn-warning w-100 fw-bold py-2">
                                    <i class="bi bi-search me-2" aria-hidden="true"></i>
                                    Search Available Cars
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ── STATS BAR ───────────────────────────────────────────── -->
<section class="stats-bar py-4" aria-label="Company statistics">
    <div class="container">
        <div class="row g-3 justify-content-center text-center">
            <div class="col-6 col-md-3 stat-item">
                <div class="stat-number"><?= (int)$totalCars ?>+</div>
                <div class="stat-label">Cars Available</div>
            </div>
            <div class="col-6 col-md-3 stat-item">
                <div class="stat-number"><?= (int)$totalUsers ?>+</div>
                <div class="stat-label">Happy Customers</div>
            </div>
            <div class="col-6 col-md-3 stat-item">
                <div class="stat-number"><?= (int)$totalBooks ?>+</div>
                <div class="stat-label">Bookings Made</div>
            </div>
            <div class="col-6 col-md-3 stat-item">
                <div class="stat-number">7</div>
                <div class="stat-label">Locations</div>
            </div>
        </div>
    </div>
</section>

<!-- ── FEATURED CARS ───────────────────────────────────────── -->
<section class="py-6 py-5 bg-white" aria-labelledby="featured-heading">
    <div class="container">
        <div class="text-center mb-5">
            <p class="section-label">Our Vehicles</p>
            <h2 class="section-title" id="featured-heading">Featured Cars</h2>
            <p class="text-muted mx-auto" style="max-width:500px;">
                From family MPVs to exhilarating sports cars — find the perfect ride for every occasion.
            </p>
        </div>

        <?php if (empty($featuredCars)): ?>
            <p class="text-center text-muted">No cars currently available. Check back soon!</p>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($featuredCars as $car): ?>
            <div class="col-12 col-md-6 col-lg-4">
                <article class="car-card h-100">
                    <!-- Car image -->
                    <div class="car-card__img-wrap">
                        <img src="<?= htmlspecialchars($car['image_path']) ?>"
                             alt="<?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?> rental car"
                             loading="lazy"
                             onerror="this.src='/assets/images/placeholder.jpg'">
                        <span class="car-card__badge badge-<?= htmlspecialchars($car['type']) ?>">
                            <?= htmlspecialchars(ucfirst($car['type'])) ?>
                        </span>
                    </div>

                    <!-- Card body -->
                    <div class="card-body p-4">
                        <h3 class="h5 fw-bold mb-1">
                            <?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?>
                        </h3>
                        <p class="text-muted small mb-3"><?= htmlspecialchars($car['year']) ?></p>

                        <!-- Specs -->
                        <div class="car-card__specs mb-3">
                            <span><i class="bi bi-people me-1" aria-hidden="true"></i><?= (int)$car['seats'] ?> Seats</span>
                            <span><i class="bi bi-gear me-1" aria-hidden="true"></i><?= htmlspecialchars($car['transmission']) ?></span>
                            <span><i class="bi bi-fuel-pump me-1" aria-hidden="true"></i><?= htmlspecialchars($car['fuel_type']) ?></span>
                        </div>

                        <!-- Price + CTA -->
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="car-card__price">
                                SGD <?= number_format($car['daily_rate'], 2) ?>
                                <span>/ day</span>
                            </div>
                            <a href="/car-details.php?id=<?= (int)$car['id'] ?>"
                               class="btn btn-warning btn-sm fw-semibold">
                                View Details
                            </a>
                        </div>
                    </div>
                </article>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-5">
            <a href="/fleet.php" class="btn btn-outline-dark btn-lg px-5">
                View All Cars <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ── WHY CHOOSE US ───────────────────────────────────────── -->
<section class="py-5 bg-light" aria-labelledby="why-heading">
    <div class="container">
        <div class="text-center mb-5">
            <p class="section-label">Our Promise</p>
            <h2 class="section-title" id="why-heading">Why Choose DriveEasy?</h2>
        </div>
        <div class="row g-4">
            <?php
            $features = [
                ['bi-shield-check',    'Fully Insured',        'Every vehicle comes with comprehensive insurance. Drive with peace of mind.'],
                ['bi-currency-dollar', 'Best Price Guarantee', 'We price-match any like-for-like rental. Plus, promo codes for extra savings.'],
                ['bi-headset',         '24/7 Support',         'Our team is always on call. Roadside assistance available nationwide.'],
                ['bi-geo-alt',         '7 Convenient Locations','Pick up and drop off at Changi Airport, MRT hubs, and hotels across Singapore.'],
                ['bi-calendar-check',  'Flexible Bookings',    'Modify or cancel up to 24 hours before pick-up with no extra fees.'],
                ['bi-star',            'Premium Fleet',        'All cars are under 2 years old, regularly serviced, and spotlessly clean.'],
            ];
            foreach ($features as $f): ?>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="d-flex gap-3 align-items-start p-3">
                    <div class="bg-warning rounded p-2 flex-shrink-0">
                        <i class="bi <?= $f[0] ?> fs-5 text-dark" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h3 class="h6 fw-bold mb-1"><?= $f[1] ?></h3>
                        <p class="text-muted small mb-0"><?= $f[2] ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── TESTIMONIALS ─────────────────────────────────────────── -->
<?php if (!empty($testimonials)): ?>
<section class="py-5" aria-labelledby="testimonials-heading">
    <div class="container">
        <div class="text-center mb-5">
            <p class="section-label">Reviews</p>
            <h2 class="section-title" id="testimonials-heading">What Our Customers Say</h2>
        </div>
        <div class="row g-4">
            <?php foreach ($testimonials as $t): ?>
            <div class="col-12 col-md-4">
                <div class="testimonial-card">
                    <!-- Star rating -->
                    <div class="star-rating mb-3" aria-label="<?= (int)$t['rating'] ?> out of 5 stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi <?= $i <= (int)$t['rating'] ? 'bi-star-fill' : 'bi-star' ?>"
                               aria-hidden="true"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="text-muted fst-italic mb-3">
                        "<?= htmlspecialchars($t['message']) ?>"
                    </p>
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center fw-bold"
                             style="width:36px;height:36px;font-size:0.9rem;" aria-hidden="true">
                            <?= mb_strtoupper(mb_substr($t['reviewer_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="fw-semibold small">
                                <?= htmlspecialchars($t['reviewer_name']) ?>
                            </div>
                            <div class="text-muted" style="font-size:0.75rem;">
                                Verified Customer
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ── CTA BANNER ──────────────────────────────────────────── -->
<section class="py-5 bg-warning" aria-label="Call to action">
    <div class="container text-center">
        <h2 class="fw-bold fs-2 mb-2">Ready to Hit the Road?</h2>
        <p class="text-dark mb-4 fs-5">Book your car in minutes. No hidden fees. Cancel anytime.</p>
        <a href="/fleet.php" class="btn btn-dark btn-lg px-5 fw-bold me-2">Browse Fleet</a>
        <a href="/contact.php" class="btn btn-outline-dark btn-lg px-5 fw-bold">Contact Us</a>
    </div>
</section>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="/assets/js/main.js"></script>

<script>
// Sync hero date validation (separate IDs from booking.php)
(function() {
    const s = document.getElementById('start_date_hero');
    const e = document.getElementById('end_date_hero');
    if (!s || !e) return;
    const today = new Date().toISOString().split('T')[0];
    s.setAttribute('min', today);
    s.addEventListener('change', function() {
        const d = new Date(this.value);
        d.setDate(d.getDate() + 1);
        e.setAttribute('min', d.toISOString().split('T')[0]);
        if (e.value && e.value <= this.value) e.value = d.toISOString().split('T')[0];
    });
})();
</script>

</body>
</html>
