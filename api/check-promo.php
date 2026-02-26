<?php
/**
 * api/check-promo.php
 * DriveEasy Car Rentals — AJAX Promo Code Validator
 *
 * Accepts POST: { code: string }
 * Returns JSON: { valid: bool, discount_percent: float, message: string }
 *
 * Security:
 *  - Only accepts POST requests
 *  - Uses PDO prepared statement
 *  - Outputs JSON with proper Content-Type header
 *  - No session or user auth required (public endpoint)
 */

// Force JSON response header always (even on errors)
header('Content-Type: application/json; charset=UTF-8');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['valid' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once dirname(__DIR__) . '/includes/db.php';

// Sanitize and normalise the submitted code
$code = strtoupper(trim(filter_input(INPUT_POST, 'code', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''));

if (empty($code)) {
    echo json_encode(['valid' => false, 'message' => 'No code provided.']);
    exit;
}

// Validate code length (prevent abuse with extremely long strings)
if (strlen($code) > 30) {
    echo json_encode(['valid' => false, 'message' => 'Invalid promo code.']);
    exit;
}

// Lookup the code with a prepared statement
// Checks: exact match, is_active = 1, and expiry_date >= today
$stmt = $pdo->prepare(
    "SELECT discount_percent, expiry_date
     FROM promo_codes
     WHERE code = :code
       AND is_active = 1
       AND expiry_date >= CURDATE()
     LIMIT 1"
);
$stmt->execute([':code' => $code]);
$promo = $stmt->fetch();

if ($promo) {
    echo json_encode([
        'valid'            => true,
        'discount_percent' => (float) $promo['discount_percent'],
        'expiry_date'      => $promo['expiry_date'],
        'message'          => number_format((float)$promo['discount_percent'], 0) . '% discount applied!',
    ]);
} else {
    // Check if code exists but is expired / inactive (for better UX messaging)
    $altStmt = $pdo->prepare(
        "SELECT expiry_date, is_active FROM promo_codes WHERE code = :code LIMIT 1"
    );
    $altStmt->execute([':code' => $code]);
    $altPromo = $altStmt->fetch();

    if ($altPromo && !$altPromo['is_active']) {
        $msg = 'This promo code has been deactivated.';
    } elseif ($altPromo && $altPromo['expiry_date'] < date('Y-m-d')) {
        $msg = 'This promo code expired on ' . date('d M Y', strtotime($altPromo['expiry_date'])) . '.';
    } else {
        $msg = 'Invalid promo code. Please check and try again.';
    }

    echo json_encode(['valid' => false, 'message' => $msg]);
}
