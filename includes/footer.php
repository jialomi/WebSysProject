<?php
/**
 * includes/footer.php
 * DriveEasy Car Rentals — Site-wide Footer
 *
 * Included at the bottom of every page before </body>.
 */
$currentYear = date('Y');
?>
<footer class="bg-dark text-light pt-5 pb-3 mt-auto" role="contentinfo">
    <div class="container">
        <div class="row g-4">

            <!-- Brand column -->
            <div class="col-12 col-md-4">
                <h5 class="fw-bold mb-3">
                    <span class="text-warning">Drive</span>Easy Car Rentals
                </h5>
                <p class="text-secondary small">
                    Your trusted partner for affordable, reliable car rentals across Malaysia.
                    Drive in comfort, drive with confidence.
                </p>
                <!-- Social icons -->
                <div class="d-flex gap-3 mt-3">
                    <a href="#" class="text-secondary fs-5 footer-social" aria-label="Facebook">
                        <i class="bi bi-facebook" aria-hidden="true"></i>
                    </a>
                    <a href="#" class="text-secondary fs-5 footer-social" aria-label="Instagram">
                        <i class="bi bi-instagram" aria-hidden="true"></i>
                    </a>
                    <a href="#" class="text-secondary fs-5 footer-social" aria-label="Twitter / X">
                        <i class="bi bi-twitter-x" aria-hidden="true"></i>
                    </a>
                    <a href="#" class="text-secondary fs-5 footer-social" aria-label="WhatsApp">
                        <i class="bi bi-whatsapp" aria-hidden="true"></i>
                    </a>
                </div>
            </div>

            <!-- Quick links column -->
            <div class="col-6 col-md-2">
                <h6 class="text-uppercase fw-semibold text-warning mb-3">Quick Links</h6>
                <ul class="list-unstyled small">
                    <li class="mb-1"><a href="/index.php"   class="footer-link">Home</a></li>
                    <li class="mb-1"><a href="/fleet.php"   class="footer-link">Our Fleet</a></li>
                    <li class="mb-1"><a href="/booking.php" class="footer-link">Book Now</a></li>
                    <li class="mb-1"><a href="/about.php"   class="footer-link">About Us</a></li>
                    <li class="mb-1"><a href="/contact.php" class="footer-link">Contact</a></li>
                </ul>
            </div>

            <!-- Account links column -->
            <div class="col-6 col-md-2">
                <h6 class="text-uppercase fw-semibold text-warning mb-3">My Account</h6>
                <ul class="list-unstyled small">
                    <li class="mb-1"><a href="/login.php"       class="footer-link">Login</a></li>
                    <li class="mb-1"><a href="/register.php"    class="footer-link">Register</a></li>
                    <li class="mb-1"><a href="/my-bookings.php" class="footer-link">My Bookings</a></li>
                    <li class="mb-1"><a href="/profile.php"     class="footer-link">Profile</a></li>
                </ul>
            </div>

            <!-- Contact info column -->
            <div class="col-12 col-md-4">
                <h6 class="text-uppercase fw-semibold text-warning mb-3">Contact Us</h6>
                <address class="text-secondary small mb-0" style="font-style:normal;">
                    <p class="mb-1">
                        <i class="bi bi-geo-alt-fill me-2 text-warning" aria-hidden="true"></i>
                        Level 12, Menara DriveEasy, KLCC, 50088 Kuala Lumpur
                    </p>
                    <p class="mb-1">
                        <i class="bi bi-telephone-fill me-2 text-warning" aria-hidden="true"></i>
                        <a href="tel:+60312345678" class="footer-link">+603 1234 5678</a>
                    </p>
                    <p class="mb-1">
                        <i class="bi bi-envelope-fill me-2 text-warning" aria-hidden="true"></i>
                        <a href="mailto:hello@driveeasy.com.my" class="footer-link">hello@driveeasy.com.my</a>
                    </p>
                    <p class="mb-0">
                        <i class="bi bi-clock-fill me-2 text-warning" aria-hidden="true"></i>
                        Mon – Sun: 8:00 AM – 9:00 PM
                    </p>
                </address>
            </div>

        </div><!-- /.row -->

        <hr class="border-secondary mt-4">

        <!-- Bottom bar -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center small text-secondary">
            <p class="mb-1 mb-md-0">
                &copy; <?= $currentYear ?> DriveEasy Car Rentals Sdn. Bhd. All rights reserved.
            </p>
            <p class="mb-0">
                <a href="#" class="footer-link me-3">Privacy Policy</a>
                <a href="#" class="footer-link me-3">Terms &amp; Conditions</a>
                <a href="#" class="footer-link">Sitemap</a>
            </p>
        </div>
    </div><!-- /.container -->
</footer>
