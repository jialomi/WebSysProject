<?php
/**
 * admin/dashboard.php
 * DriveEasy Car Rentals — Admin Dashboard
 *
 * Features:
 *  - Summary stat cards
 *  - Chart.js bar chart: bookings per month (last 12 months)
 *  - Recent bookings table
 *  - Recent contact messages
 *  - Requires admin role
 */
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

requireAdmin();  // Redirects non-admins

$pageTitle = 'Admin Dashboard – DriveEasy';

// ── Summary Stats ───────────────────────────────────────────
$stats = [
    'total_cars'     => $pdo->query("SELECT COUNT(*) FROM cars")->fetchColumn(),
    'available_cars' => $pdo->query("SELECT COUNT(*) FROM cars WHERE status='available'")->fetchColumn(),
    'total_bookings' => $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
    'pending_books'  => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn(),
    'total_users'    => $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn(),
    'total_revenue'  => $pdo->query("SELECT COALESCE(SUM(total_cost),0) FROM bookings WHERE status!='cancelled'")->fetchColumn(),
    'unread_msgs'    => $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn(),
    'pending_reviews'=> $pdo->query("SELECT COUNT(*) FROM testimonials WHERE is_active=0")->fetchColumn(),
];

// ── Recent Bookings ─────────────────────────────────────────
$recentBookings = $pdo->query(
    "SELECT b.id, b.status, b.total_cost, b.created_at,
            u.name AS customer, c.brand, c.model
     FROM bookings b
     JOIN users u ON u.id = b.user_id
     JOIN cars  c ON c.id = b.car_id
     ORDER BY b.created_at DESC
     LIMIT 8"
)->fetchAll();

// ── Monthly Bookings for Chart.js (last 12 months) ─────────
$monthlyData = $pdo->query(
    "SELECT DATE_FORMAT(created_at, '%b %Y') AS month_label,
            DATE_FORMAT(created_at, '%Y-%m') AS month_key,
            COUNT(*) AS booking_count,
            COALESCE(SUM(total_cost),0) AS revenue
     FROM bookings
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
     GROUP BY month_key
     ORDER BY month_key ASC"
)->fetchAll();

// Build chart data arrays (fill empty months)
$chartLabels   = [];
$chartCounts   = [];
$chartRevenue  = [];

// Build all 12 months
$now = new DateTime();
for ($i = 11; $i >= 0; $i--) {
    $d = clone $now;
    $d->modify("-{$i} months");
    $key   = $d->format('Y-m');
    $label = $d->format('M Y');
    $chartLabels[]  = $label;

    // Find matching data
    $found = false;
    foreach ($monthlyData as $row) {
        if ($row['month_key'] === $key) {
            $chartCounts[]  = (int)$row['booking_count'];
            $chartRevenue[] = (float)$row['revenue'];
            $found = true;
            break;
        }
    }
    if (!$found) {
        $chartCounts[]  = 0;
        $chartRevenue[] = 0;
    }
}

// ── Car type breakdown (doughnut chart) ────────────────────
$carTypeData = $pdo->query(
    "SELECT type, COUNT(*) as cnt FROM cars GROUP BY type"
)->fetchAll();
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

<!-- Admin Layout: Sidebar + Main -->
<div class="d-flex" style="min-height:100vh;">

    <!-- ── SIDEBAR ────────────────────────────────────────── -->
    <nav class="admin-sidebar d-none d-lg-flex flex-column" style="width:240px; flex-shrink:0;"
         aria-label="Admin navigation">
        <!-- Brand -->
        <a href="/admin/dashboard.php" class="d-flex align-items-center gap-2 px-4 py-3 text-decoration-none mb-2">
            <span class="fw-bold fs-5">
                <span class="text-warning">Drive</span><span class="text-white">Easy</span>
            </span>
            <span class="badge bg-warning text-dark" style="font-size:0.6rem;">ADMIN</span>
        </a>

        <div class="px-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="/admin/dashboard.php">
                        <i class="bi bi-speedometer2 me-2" aria-hidden="true"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/manage-cars.php">
                        <i class="bi bi-car-front me-2" aria-hidden="true"></i>Manage Cars
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/manage-bookings.php">
                        <i class="bi bi-calendar-check me-2" aria-hidden="true"></i>Manage Bookings
                        <?php if ($stats['pending_books'] > 0): ?>
                        <span class="badge bg-warning text-dark ms-auto">
                            <?= (int)$stats['pending_books'] ?>
                        </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/fleet.php">
                        <i class="bi bi-grid me-2" aria-hidden="true"></i>View Site
                    </a>
                </li>
            </ul>

            <hr class="border-secondary my-3">

            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="/profile.php">
                        <i class="bi bi-gear me-2" aria-hidden="true"></i>Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-danger" href="/logout.php">
                        <i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Logout
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- ── MAIN CONTENT ──────────────────────────────────── -->
    <div class="flex-grow-1 bg-light">

        <!-- Top bar -->
        <header class="bg-white shadow-sm px-4 py-3 d-flex justify-content-between align-items-center sticky-top">
            <div class="d-flex align-items-center gap-3">
                <!-- Mobile menu toggle -->
                <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#adminSidebar"
                        aria-controls="adminSidebar" aria-label="Open menu">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <h1 class="h5 mb-0 fw-bold">Dashboard</h1>
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php if ($stats['unread_msgs'] > 0): ?>
                <span class="badge bg-danger"><?= (int)$stats['unread_msgs'] ?> new messages</span>
                <?php endif; ?>
                <span class="small text-muted">
                    Welcome, <?= currentUserName() ?>
                </span>
            </div>
        </header>

        <main class="p-4">
            <?= renderFlash() ?>

            <!-- ── STAT CARDS ─────────────────────────────── -->
            <div class="row g-3 mb-4">
                <?php
                $statCards = [
                    ['Total Revenue',   'RM '.number_format((float)$stats['total_revenue'],2), 'bi-currency-dollar', 'success', '/admin/manage-bookings.php'],
                    ['Total Bookings',  $stats['total_bookings'],  'bi-calendar-check',  'primary', '/admin/manage-bookings.php'],
                    ['Pending Bookings',$stats['pending_books'],   'bi-hourglass-split', 'warning', '/admin/manage-bookings.php?status=pending'],
                    ['Fleet Size',      $stats['total_cars'],      'bi-car-front',       'info',    '/admin/manage-cars.php'],
                    ['Customers',       $stats['total_users'],     'bi-people',          'secondary','/index.php'],
                    ['Unread Messages', $stats['unread_msgs'],     'bi-envelope',        'danger',  '/contact.php'],
                ];
                foreach ($statCards as $sc): ?>
                <div class="col-6 col-md-4 col-xl-2">
                    <a href="<?= $sc[4] ?>" class="text-decoration-none">
                        <div class="card admin-stat-card text-center p-3">
                            <i class="bi <?= $sc[2] ?> fs-2 text-<?= $sc[3] ?> mb-2"
                               aria-hidden="true"></i>
                            <div class="fw-bold fs-5"><?= htmlspecialchars((string)$sc[1]) ?></div>
                            <div class="text-muted small"><?= htmlspecialchars($sc[0]) ?></div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ── CHARTS ROW ────────────────────────────── -->
            <div class="row g-4 mb-4">

                <!-- Bookings per month (Bar) -->
                <div class="col-12 col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white fw-bold py-3">
                            <i class="bi bi-bar-chart me-2 text-warning" aria-hidden="true"></i>
                            Bookings &amp; Revenue – Last 12 Months
                        </div>
                        <div class="card-body">
                            <canvas id="bookingsChart" aria-label="Bookings per month bar chart"
                                    role="img" height="100"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Fleet type breakdown (Doughnut) -->
                <div class="col-12 col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white fw-bold py-3">
                            <i class="bi bi-pie-chart me-2 text-warning" aria-hidden="true"></i>
                            Fleet by Type
                        </div>
                        <div class="card-body d-flex align-items-center justify-content-center">
                            <canvas id="fleetChart" aria-label="Fleet type doughnut chart"
                                    role="img" width="200" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── RECENT BOOKINGS TABLE ─────────────────── -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between">
                    <span>
                        <i class="bi bi-list-check me-2 text-warning" aria-hidden="true"></i>
                        Recent Bookings
                    </span>
                    <a href="/admin/manage-bookings.php" class="btn btn-sm btn-outline-warning">
                        View All
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Customer</th>
                                    <th scope="col">Car</th>
                                    <th scope="col">Amount</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentBookings as $b): ?>
                                <tr>
                                    <td class="fw-semibold">#<?= (int)$b['id'] ?></td>
                                    <td><?= htmlspecialchars($b['customer']) ?></td>
                                    <td><?= htmlspecialchars($b['brand'] . ' ' . $b['model']) ?></td>
                                    <td>RM <?= number_format((float)$b['total_cost'], 2) ?></td>
                                    <td>
                                        <span class="badge status-<?= htmlspecialchars($b['status']) ?> px-2 py-1 text-capitalize">
                                            <?= htmlspecialchars($b['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small">
                                        <?= htmlspecialchars(date('d M Y', strtotime($b['created_at']))) ?>
                                    </td>
                                    <td>
                                        <a href="/admin/manage-bookings.php?edit=<?= (int)$b['id'] ?>"
                                           class="btn btn-xs btn-outline-secondary btn-sm">
                                            Edit
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recentBookings)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">No bookings yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main><!-- /main -->
    </div><!-- /main content -->
</div><!-- /flex row -->

<!-- Mobile Sidebar Offcanvas -->
<div class="offcanvas offcanvas-start bg-dark text-white" id="adminSidebar"
     tabindex="-1" aria-labelledby="adminSidebarLabel">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title fw-bold" id="adminSidebarLabel">
            <span class="text-warning">Drive</span>Easy Admin
        </h2>
        <button type="button" class="btn-close btn-close-white"
                data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-3">
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link text-white" href="/admin/dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="/admin/manage-cars.php">Manage Cars</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="/admin/manage-bookings.php">Manage Bookings</a></li>
            <li class="nav-item"><a class="nav-link text-danger"  href="/logout.php">Logout</a></li>
        </ul>
    </div>
</div>

<!-- Bootstrap + Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

<script>
// ── Bookings & Revenue Bar Chart ──────────────────────────
const bCtx = document.getElementById('bookingsChart').getContext('2d');
new Chart(bCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [
            {
                label: 'Bookings',
                data: <?= json_encode($chartCounts) ?>,
                backgroundColor: 'rgba(255, 193, 7, 0.8)',
                borderRadius: 4,
                yAxisID: 'y',
            },
            {
                label: 'Revenue (RM)',
                data: <?= json_encode($chartRevenue) ?>,
                type: 'line',
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.1)',
                fill: true,
                tension: 0.4,
                yAxisID: 'y1',
                pointRadius: 4,
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'top' },
            tooltip: {
                callbacks: {
                    label: ctx => ctx.datasetIndex === 1
                        ? ' RM ' + ctx.raw.toFixed(2)
                        : ' ' + ctx.raw + ' booking(s)'
                }
            }
        },
        scales: {
            y:  { beginAtZero: true, title: { display: true, text: 'Bookings' }, ticks: { stepSize: 1 } },
            y1: { beginAtZero: true, position: 'right', title: { display: true, text: 'Revenue (RM)' },
                  grid: { drawOnChartArea: false } },
        }
    }
});

// ── Fleet Doughnut Chart ──────────────────────────────────
const fCtx = document.getElementById('fleetChart').getContext('2d');
new Chart(fCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($carTypeData, 'type')) ?>,
        datasets: [{
            data:  <?= json_encode(array_column($carTypeData, 'cnt')) ?>,
            backgroundColor: ['#0d6efd','#198754','#ffc107','#dc3545'],
            hoverOffset: 8,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>

</body>
</html>
