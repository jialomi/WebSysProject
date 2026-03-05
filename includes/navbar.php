<?php
/**
 * includes/navbar.php
 * DriveEasy Car Rentals — Responsive Bootstrap 5 Navbar
 *
 * Included at the top of every page.
 * Highlights the active page and shows auth-aware links.
 * Requires auth.php to have been included beforehand.
 */

// Determine current page for active-link highlighting
$currentPage = basename($_SERVER['PHP_SELF']);

/**
 * Returns 'active' if the given filename matches the current page.
 */
function navActive(string $page): string {
    global $currentPage;
    return ($currentPage === $page) ? 'active" aria-current="page' : '';
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm" id="mainNav">
    <div class="container">

        <!-- Brand / Logo -->
<a class="navbar-brand" href="/index.php">
    <img src="/assets/images/logo.png" alt="DriveEasy" class="navbar-logo">
</a>

        <!-- Mobile toggle -->
        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#navbarMain"
                aria-controls="navbarMain" aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Nav links -->
        <div class="collapse navbar-collapse" id="navbarMain">

            <!-- Left links -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= navActive('index.php') ?>" href="/index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= navActive('fleet.php') ?>" href="/fleet.php">Our Fleet</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= navActive('about.php') ?>" href="/about.php">About Us</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= navActive('contact.php') ?>" href="/contact.php">Contact</a>
                </li>
            </ul>

            <!-- Right: auth-aware links -->
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">

                <?php if (isLoggedIn()): ?>

                    <?php if (isAdmin()): ?>
                    <!-- Admin link -->
                    <li class="nav-item me-2">
                        <a class="nav-link text-warning fw-semibold <?= navActive('dashboard.php') ?>"
                           href="/admin/dashboard.php">
                            <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>Admin
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- User dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-1"
                           href="#" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false"
                           id="userDropdown">
                            <i class="bi bi-person-circle fs-5" aria-hidden="true"></i>
                            <span><?= currentUserName() ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                            <li>
                                <a class="dropdown-item" href="/my-bookings.php">
                                    <i class="bi bi-calendar-check me-2" aria-hidden="true"></i>My Bookings
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="/profile.php">
                                    <i class="bi bi-gear me-2" aria-hidden="true"></i>Profile
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="/logout.php">
                                    <i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Log Out
                                </a>
                            </li>
                        </ul>
                    </li>

                <?php else: ?>

                    <!-- Guest links -->
                    <li class="nav-item">
                        <a class="nav-link <?= navActive('login.php') ?>" href="/login.php">
                            <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Login
                        </a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-warning btn-sm fw-semibold px-3" href="/register.php">
                            Register
                        </a>
                    </li>

                <?php endif; ?>

            </ul>
        </div><!-- /.collapse -->
    </div><!-- /.container -->
</nav>
