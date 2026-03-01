/**
 * assets/js/main.js
 * DriveEasy Car Rentals — All Custom JavaScript
 *
 * Sections:
 *  1. Booking Cost Calculator  (booking.php)
 *  2. Date Picker Validation   (booking.php)
 *  3. Promo Code AJAX Checker  (booking.php)
 *  4. Fleet Live Filter        (fleet.php)
 *  5. Client-side Form Validation (all forms)
 *  6. Miscellaneous / UI helpers
 */

'use strict';

/* ============================================================
   1. BOOKING COST CALCULATOR
   Dynamically calculates rental cost as user changes dates.
   Reads the daily rate from a data attribute on the page.
   ============================================================ */
(function initBookingCalculator() {
    const startInput  = document.getElementById('start_date');
    const endInput    = document.getElementById('end_date');
    const daysDisplay = document.getElementById('calcDays');
    const rateDisplay = document.getElementById('calcRate');
    const subtotalEl  = document.getElementById('calcSubtotal');
    const discountEl  = document.getElementById('calcDiscount');
    const totalEl     = document.getElementById('calcTotal');
    const totalInput  = document.getElementById('total_cost');  // hidden input

    if (!startInput || !endInput || !totalEl) return;  // Not on booking page

    // Daily rate is embedded in a data attribute by PHP
    const dailyRate = parseFloat(
        document.getElementById('bookingForm')?.dataset.dailyRate || 0
    );

    // Track applied promo discount (set by promo checker below)
    window.promoDiscount = 0;

    /**
     * Calculates number of rental days (end - start, minimum 1).
     * @returns {number}
     */
    function calcDays() {
        const s = new Date(startInput.value);
        const e = new Date(endInput.value);
        if (!startInput.value || !endInput.value || s >= e) return 0;
        const ms = e - s;
        return Math.ceil(ms / (1000 * 60 * 60 * 24));
    }

    /**
     * Updates all displayed cost figures and the hidden total_cost input.
     */
    function updateCost() {
        const days     = calcDays();
        const subtotal = days * dailyRate;
        const discount = subtotal * (window.promoDiscount / 100);
        const total    = Math.max(0, subtotal - discount);

        if (daysDisplay)  daysDisplay.textContent  = days;
        if (rateDisplay)  rateDisplay.textContent  = 'SGD ' + dailyRate.toFixed(2);
        if (subtotalEl)   subtotalEl.textContent   = 'SGD ' + subtotal.toFixed(2);
        if (discountEl)   discountEl.textContent   = discount > 0
            ? '- SGD ' + discount.toFixed(2)
            : 'SGD 0.00';
        if (totalEl)      totalEl.textContent      = 'SGD ' + total.toFixed(2);
        if (totalInput)   totalInput.value         = total.toFixed(2);
    }

    startInput.addEventListener('change', updateCost);
    endInput.addEventListener('change',   updateCost);

    // Expose for promo checker to call after discount changes
    window.updateBookingCost = updateCost;

    // Run once on page load in case values are pre-filled
    updateCost();
})();


/* ============================================================
   2. DATE PICKER VALIDATION
   - Disables past dates on start_date
   - Ensures end_date >= start_date + 1 day
   ============================================================ */
(function initDateValidation() {
    const startInput = document.getElementById('start_date');
    const endInput   = document.getElementById('end_date');

    if (!startInput || !endInput) return;

    /**
     * Returns today's date string in YYYY-MM-DD format.
     */
    function todayStr() {
        const d = new Date();
        return d.toISOString().split('T')[0];
    }

    /**
     * Adds one day to a YYYY-MM-DD date string.
     * @param {string} dateStr
     * @returns {string}
     */
    function addOneDay(dateStr) {
        const d = new Date(dateStr);
        d.setDate(d.getDate() + 1);
        return d.toISOString().split('T')[0];
    }

    // Set minimum selectable start date to today
    startInput.setAttribute('min', todayStr());

    // When start changes: move end minimum to start + 1 day
    startInput.addEventListener('change', function () {
        const minEnd = addOneDay(this.value);
        endInput.setAttribute('min', minEnd);

        // If current end is before new minimum, clear/adjust it
        if (endInput.value && endInput.value <= this.value) {
            endInput.value = minEnd;
            endInput.dispatchEvent(new Event('change'));
        }
    });

    // If start has a value on load, set end minimum accordingly
    if (startInput.value) {
        endInput.setAttribute('min', addOneDay(startInput.value));
    }
})();


/* ============================================================
   3. PROMO CODE AJAX CHECKER
   Sends promo code to api/check-promo.php and displays result.
   Updates the booking calculator with any discount.
   ============================================================ */
(function initPromoChecker() {
    const promoBtn    = document.getElementById('applyPromoBtn');
    const promoInput  = document.getElementById('promo_code');
    const promoResult = document.getElementById('promoResult');
    const promoHidden = document.getElementById('applied_promo_code');

    if (!promoBtn || !promoInput) return;

    promoBtn.addEventListener('click', async function () {
        const code = promoInput.value.trim().toUpperCase();
        if (!code) {
            promoResult.textContent = 'Please enter a promo code.';
            promoResult.className   = 'text-warning small mt-1';
            return;
        }

        promoBtn.disabled    = true;
        promoResult.textContent = 'Checking…';
        promoResult.className   = 'text-secondary small mt-1';

        try {
            const response = await fetch('/api/check-promo.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    'code=' + encodeURIComponent(code)
            });

            const data = await response.json();

            if (data.valid) {
                // Apply discount
                window.promoDiscount = data.discount_percent;
                promoResult.innerHTML =
                    `<i class="bi bi-check-circle-fill me-1"></i>` +
                    `Code applied: <strong>${data.discount_percent}% off</strong>`;
                promoResult.className = 'text-success small mt-1';
                if (promoHidden) promoHidden.value = code;
                if (window.updateBookingCost) window.updateBookingCost();
            } else {
                // Invalid / expired
                window.promoDiscount = 0;
                promoResult.innerHTML =
                    `<i class="bi bi-x-circle-fill me-1"></i>` + data.message;
                promoResult.className = 'text-danger small mt-1';
                if (promoHidden) promoHidden.value = '';
                if (window.updateBookingCost) window.updateBookingCost();
            }
        } catch (err) {
            promoResult.textContent = 'Could not validate code. Please try again.';
            promoResult.className   = 'text-danger small mt-1';
        } finally {
            promoBtn.disabled = false;
        }
    });

    // Allow pressing Enter in the promo input
    promoInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            promoBtn.click();
        }
    });
})();


/* ============================================================
   4. FLEET LIVE FILTER
   Filters car cards by type and max daily price without reload.
   ============================================================ */
(function initFleetFilter() {
    const typeFilter     = document.getElementById('filterType');
    const priceFilter    = document.getElementById('filterPrice');
    const priceLabel     = document.getElementById('filterPriceLabel');
    const sortSelect     = document.getElementById('sortCars');
    const carsContainer  = document.getElementById('carsGrid');
    const noResultsEl    = document.getElementById('noResults');
    const resultCountEl  = document.getElementById('resultCount');

    if (!carsContainer) return;

    /**
     * Reads all car cards and filters/sorts them based on current filter values.
     */
    function applyFilters() {
        const selectedType  = typeFilter  ? typeFilter.value  : 'all';
        const maxPrice      = priceFilter ? parseFloat(priceFilter.value) : Infinity;
        const sortVal       = sortSelect  ? sortSelect.value  : 'default';
        const searchQuery   = (document.getElementById('searchQuery')?.value || '')
                              .trim().toLowerCase();

        // Update price label
        if (priceLabel) {
            priceLabel.textContent = isFinite(maxPrice)
                ? 'SGD ' + maxPrice.toFixed(0) + '/day'
                : 'Any price';
        }

        const cards = Array.from(carsContainer.querySelectorAll('.car-filter-item'));
        let visible = 0;

        cards.forEach(card => {
            const cardType  = (card.dataset.type  || '').toLowerCase();
            const cardPrice = parseFloat(card.dataset.price || 0);
            const cardText  = (card.dataset.search || '').toLowerCase();

            const typeMatch   = selectedType === 'all' || cardType === selectedType.toLowerCase();
            const priceMatch  = cardPrice <= maxPrice;
            const searchMatch = !searchQuery || cardText.includes(searchQuery);

            if (typeMatch && priceMatch && searchMatch) {
                card.style.display = '';
                visible++;
            } else {
                card.style.display = 'none';
            }
        });

        // Sort visible cards
        const visibleCards = cards.filter(c => c.style.display !== 'none');
        visibleCards.sort((a, b) => {
            if (sortVal === 'price-asc')  return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
            if (sortVal === 'price-desc') return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
            if (sortVal === 'name-asc')   return (a.dataset.search || '').localeCompare(b.dataset.search || '');
            return 0; // default: keep original order
        });
        visibleCards.forEach(c => carsContainer.appendChild(c));

        // Toggle "no results" message
        if (noResultsEl) noResultsEl.style.display = visible === 0 ? '' : 'none';
        if (resultCountEl) {
            resultCountEl.textContent = visible + ' car' + (visible !== 1 ? 's' : '') + ' found';
        }
    }

    // Attach listeners
    if (typeFilter)  typeFilter.addEventListener('change', applyFilters);
    if (priceFilter) priceFilter.addEventListener('input',  applyFilters);
    if (sortSelect)  sortSelect.addEventListener('change',  applyFilters);
    const searchInput = document.getElementById('searchQuery');
    if (searchInput) searchInput.addEventListener('input', applyFilters);

    applyFilters(); // Run on page load
})();


/* ============================================================
   5. CLIENT-SIDE FORM VALIDATION
   Adds Bootstrap validation styling before form submission.
   Server-side validation is always the authoritative check.
   ============================================================ */
(function initFormValidation() {
    // Apply to all forms with the class 'needs-validation'
    document.querySelectorAll('form.needs-validation').forEach(form => {
        form.addEventListener('submit', function (e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }

            // Extra: confirm password match on register form
            const pw  = form.querySelector('#password');
            const cpw = form.querySelector('#confirm_password');
            if (pw && cpw && pw.value !== cpw.value) {
                e.preventDefault();
                e.stopPropagation();
                cpw.setCustomValidity('Passwords do not match.');
                cpw.reportValidity();
            } else if (cpw) {
                cpw.setCustomValidity('');
            }

            // Extra: ensure booking dates are valid
            const start = form.querySelector('#start_date');
            const end   = form.querySelector('#end_date');
            if (start && end && start.value && end.value) {
                if (new Date(end.value) <= new Date(start.value)) {
                    e.preventDefault();
                    e.stopPropagation();
                    end.setCustomValidity('Return date must be after pick-up date.');
                    end.reportValidity();
                } else {
                    end.setCustomValidity('');
                }
            }

            form.classList.add('was-validated');
        }, false);
    });

    // Clear custom validity on input to allow re-validation
    document.querySelectorAll('input, select, textarea').forEach(el => {
        el.addEventListener('input', () => el.setCustomValidity(''));
    });
})();


/* ============================================================
   6. MISCELLANEOUS UI HELPERS
   ============================================================ */

/* -- Smooth scroll for anchor links -- */
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

/* -- Auto-dismiss flash alerts after 5 seconds -- */
(function autoDismissAlerts() {
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 5000);
    });
})();

/* -- Star rating interactive widget (testimonial form) -- */
(function initStarRating() {
    const stars      = document.querySelectorAll('.star-input');
    const ratingInput = document.getElementById('rating');

    if (!stars.length || !ratingInput) return;

    stars.forEach(star => {
        star.addEventListener('click', function () {
            const val = parseInt(this.dataset.value);
            ratingInput.value = val;
            stars.forEach(s => {
                const sv = parseInt(s.dataset.value);
                s.classList.toggle('bi-star-fill', sv <= val);
                s.classList.toggle('bi-star',      sv >  val);
            });
        });

        star.addEventListener('mouseenter', function () {
            const val = parseInt(this.dataset.value);
            stars.forEach(s => {
                const sv = parseInt(s.dataset.value);
                s.classList.toggle('text-warning', sv <= val);
            });
        });

        star.addEventListener('mouseleave', function () {
            stars.forEach(s => s.classList.remove('text-warning'));
        });
    });
})();

/* -- Cancel booking confirmation -- */
document.querySelectorAll('.btn-cancel-booking').forEach(btn => {
    btn.addEventListener('click', function (e) {
        if (!confirm('Are you sure you want to cancel this booking? This cannot be undone.')) {
            e.preventDefault();
        }
    });
});

/* -- Image preview for admin car upload -- */
(function initImagePreview() {
    const fileInput = document.getElementById('car_image');
    const preview   = document.getElementById('imagePreview');
    if (!fileInput || !preview) return;

    fileInput.addEventListener('change', function () {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = e => {
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });
})();
