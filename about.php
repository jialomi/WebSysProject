<?php
/**
 * about.php
 * DriveEasy Car Rentals — About Us Page
 *
 * Sections: Company story, Mission & values, Team
 */
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'About Us – DriveEasy Car Rentals';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Learn about DriveEasy Car Rentals – our story, mission, and the team behind Malaysia's most trusted car rental service.">
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
                <li class="breadcrumb-item active text-white">About Us</li>
            </ol>
        </nav>
        <h1 class="fw-bold text-white mb-1">About DriveEasy</h1>
        <p class="text-secondary mb-0">Our story, mission, and the people who make it happen.</p>
    </div>
</div>

<!-- ── COMPANY STORY ─────────────────────────────────────── -->
<section class="py-5 bg-white" aria-labelledby="story-heading">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-12 col-lg-6">
                <p class="section-label">Our Story</p>
                <h2 class="section-title" id="story-heading">
                    From a Single Car to a National Fleet
                </h2>
                <p class="text-muted mt-3">
                    DriveEasy was founded in 2015 by a small team of automotive enthusiasts in Kuala Lumpur
                    with a simple belief: renting a car should be as easy as calling a friend. Starting with
                    just one Toyota Vios and a shared office, we quickly earned a reputation for transparent
                    pricing, spotless vehicles, and customer service that goes the extra mile.
                </p>
                <p class="text-muted">
                    Today, DriveEasy operates across seven major cities in Malaysia, with a fleet of over
                    50 premium vehicles spanning sedans, SUVs, MPVs, and sports cars. We're proud to have
                    served more than 10,000 happy customers — from weekend explorers to corporate travellers.
                </p>
                <p class="text-muted">
                    Our technology-first approach means you can book, manage, and modify your rental entirely
                    online, with real-time availability and instant confirmation. We believe great experiences
                    begin before you even start the engine.
                </p>
            </div>
            <div class="col-12 col-lg-6">
                <!-- Placeholder image area styled as a branded card -->
                <div class="bg-dark rounded-3 p-5 text-center" style="min-height:320px; display:flex; align-items:center; justify-content:center;">
                    <div>
                        <i class="bi bi-car-front-fill text-warning" style="font-size:5rem;" aria-hidden="true"></i>
                        <p class="text-secondary mt-3 mb-0">Est. 2015 · Kuala Lumpur, Malaysia</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── MISSION & VALUES ──────────────────────────────────── -->
<section class="py-5 bg-light" aria-labelledby="mission-heading">
    <div class="container">
        <div class="text-center mb-5">
            <p class="section-label">What Drives Us</p>
            <h2 class="section-title" id="mission-heading">Our Mission &amp; Values</h2>
        </div>

        <div class="row justify-content-center g-4 mb-5">
            <div class="col-12 col-md-8 text-center">
                <blockquote class="blockquote">
                    <p class="fs-4 fw-semibold fst-italic">
                        "To make personal mobility accessible, affordable, and enjoyable for every Malaysian."
                    </p>
                </blockquote>
            </div>
        </div>

        <div class="row g-4">
            <?php
            $values = [
                ['bi-people-fill',       'Customer First',    'Every decision we make starts with the question: how does this benefit our customer? Your experience is our scoreboard.'],
                ['bi-transparency',      'Transparency',      'No hidden fees. No surprise charges. The price you see is the price you pay. Our T&Cs are written in plain English.'],
                ['bi-arrow-repeat',      'Continuous Improvement', 'We invest in newer vehicles, better technology, and ongoing staff training to keep raising the bar.'],
                ['bi-globe',             'Sustainability',    'We\'re on a journey to electrify our fleet. Our hybrid vehicles are just the beginning of our green commitment.'],
                ['bi-shield-lock-fill',  'Safety',            'Every vehicle undergoes a 50-point inspection before each rental. We never compromise on safety.'],
                ['bi-heart-fill',        'Community',         'We sponsor local events, support road safety campaigns, and reinvest in the communities where we operate.'],
            ];
            foreach ($values as $v): ?>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm text-center p-4">
                    <div class="bg-warning rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center"
                         style="width:60px;height:60px;">
                        <i class="bi <?= $v[0] ?> fs-4 text-dark" aria-hidden="true"></i>
                    </div>
                    <h3 class="h6 fw-bold"><?= $v[1] ?></h3>
                    <p class="text-muted small mb-0"><?= $v[2] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── TEAM ─────────────────────────────────────────────── -->
<section class="py-5 bg-white" aria-labelledby="team-heading">
    <div class="container">
        <div class="text-center mb-5">
            <p class="section-label">The People</p>
            <h2 class="section-title" id="team-heading">Meet Our Leadership Team</h2>
        </div>

        <div class="row g-4 justify-content-center">
            <?php
            $team = [
                ['Daniel Ng',      'CEO & Co-Founder',      'Leading DriveEasy with a vision to redefine mobility in Southeast Asia. Former automotive industry consultant.'],
                ['Priya Ramasamy', 'COO & Co-Founder',      'Oversees operations and fleet management. Passionate about logistics and customer satisfaction.'],
                ['Lim Wei Jie',    'CTO',                   'Built the DriveEasy platform from scratch. Full-stack engineer with a love for elegant, scalable systems.'],
                ['Nurul Huda',     'Head of Customer Experience', 'Ensures every customer interaction is a positive one. 10+ years in hospitality and service.'],
            ];
            foreach ($team as $member): ?>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm text-center p-4 h-100 team-card">
                    <!-- Avatar placeholder -->
                    <div class="bg-warning rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center fw-bold fs-4"
                         style="width:80px;height:80px;"
                         aria-hidden="true">
                        <?= htmlspecialchars(mb_strtoupper(mb_substr($member[0], 0, 1))) ?>
                    </div>
                    <h3 class="h6 fw-bold mb-1"><?= htmlspecialchars($member[0]) ?></h3>
                    <p class="text-warning small fw-semibold mb-2"><?= htmlspecialchars($member[1]) ?></p>
                    <p class="text-muted small mb-0"><?= htmlspecialchars($member[2]) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── MILESTONES TIMELINE ───────────────────────────────── -->
<section class="py-5 bg-dark text-white" aria-labelledby="milestones-heading">
    <div class="container">
        <div class="text-center mb-5">
            <p class="section-label text-warning">Our Journey</p>
            <h2 class="section-title" id="milestones-heading">Key Milestones</h2>
        </div>
        <div class="row g-0">
            <?php
            $milestones = [
                ['2015', 'Founded in KL', 'Launched with 1 car and 3 founding team members.'],
                ['2017', '1,000 Customers', 'Hit our first major milestone and expanded to Penang.'],
                ['2019', 'Fleet of 20', 'Added SUVs and MPVs; opened Johor Bahru branch.'],
                ['2021', 'Digital-First Rebrand', 'Launched our new booking platform and mobile-friendly experience.'],
                ['2023', '10,000 Customers', 'Reached nationwide coverage with 7 locations across Malaysia.'],
                ['2025', 'Going Green', 'First hybrid vehicles added to the fleet as part of our sustainability pledge.'],
            ];
            foreach ($milestones as $i => $m): ?>
            <div class="col-12 col-md-4 p-4 border-start border-warning border-3">
                <div class="text-warning fw-bold fs-3 mb-1"><?= $m[0] ?></div>
                <div class="fw-bold mb-1"><?= $m[1] ?></div>
                <div class="text-secondary small"><?= $m[2] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/main.js"></script>

</body>
</html>
