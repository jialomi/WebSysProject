<?php
/**
 * contact.php
 * DriveEasy Car Rentals — Contact Form
 *
 * Stores messages in the contact_messages table.
 * Full server-side validation + CSRF protection.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Contact Us – DriveEasy Car Rentals';
$csrf      = generateCsrfToken();
$errors    = [];
$formData  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    // Collect and sanitize inputs
    $formData['name']    = trim(filter_input(INPUT_POST, 'name',    FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $formData['email']   = trim(filter_input(INPUT_POST, 'email',   FILTER_SANITIZE_EMAIL) ?? '');
    $formData['subject'] = trim(filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $formData['message'] = trim(filter_input(INPUT_POST, 'message', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

    // Server-side validation
    if (empty($formData['name']))              $errors[] = 'Your name is required.';
    if (empty($formData['email']))             $errors[] = 'Your email address is required.';
    elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL))
                                               $errors[] = 'Please enter a valid email address.';
    if (empty($formData['message']))           $errors[] = 'A message is required.';
    elseif (strlen($formData['message']) < 20) $errors[] = 'Message must be at least 20 characters.';

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "INSERT INTO contact_messages (name, email, subject, message)
             VALUES (:name, :email, :subject, :message)"
        );
        $stmt->execute([
            ':name'    => $formData['name'],
            ':email'   => $formData['email'],
            ':subject' => $formData['subject'] ?: null,
            ':message' => $formData['message'],
        ]);
        setFlash('success', 'Thank you for your message! We\'ll get back to you within 24 hours.');
        header('Location: /contact.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Get in touch with DriveEasy Car Rentals. We're here to help 7 days a week.">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<main>

<div class="page-hero" role="banner">
    <div class="container">
        <nav aria-label="Breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="/index.php" class="text-warning">Home</a></li>
                <li class="breadcrumb-item active text-white">Contact Us</li>
            </ol>
        </nav>
        <h1 class="fw-bold text-white mb-1">Contact Us</h1>
        <p class="text-secondary mb-0">We'd love to hear from you. Send us a message!</p>
    </div>
</div>

<div class="container py-5">

    <?= renderFlash() ?>

    <div class="row g-5">

        <!-- ── CONTACT FORM ────────────────────────────── -->
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm p-4">
                <h2 class="h5 fw-bold mb-4">Send Us a Message</h2>

                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <form action="/contact.php" method="POST"
                      class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token"
                           value="<?= htmlspecialchars($csrf) ?>">

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="name" class="form-label fw-semibold">
                                Full Name <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <input type="text" class="form-control" name="name" id="name"
                                   value="<?= htmlspecialchars($formData['name'] ?? '') ?>"
                                   maxlength="100" required>
                            <div class="invalid-feedback">Name is required.</div>
                        </div>

                        <div class="col-sm-6">
                            <label for="email" class="form-label fw-semibold">
                                Email Address <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <input type="email" class="form-control" name="email" id="email"
                                   value="<?= htmlspecialchars($formData['email'] ?? '') ?>"
                                   maxlength="150" required>
                            <div class="invalid-feedback">Valid email is required.</div>
                        </div>

                        <div class="col-12">
                            <label for="subject" class="form-label fw-semibold">
                                Subject <span class="text-muted fw-normal">(optional)</span>
                            </label>
                            <input type="text" class="form-control" name="subject" id="subject"
                                   value="<?= htmlspecialchars($formData['subject'] ?? '') ?>"
                                   maxlength="200" placeholder="e.g. Booking enquiry">
                        </div>

                        <div class="col-12">
                            <label for="message" class="form-label fw-semibold">
                                Message <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <textarea class="form-control" name="message" id="message"
                                      rows="6" minlength="20" maxlength="2000" required
                                      placeholder="How can we help you?"><?= htmlspecialchars($formData['message'] ?? '') ?></textarea>
                            <div class="invalid-feedback">Message must be at least 20 characters.</div>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-warning fw-bold px-5 py-2">
                                <i class="bi bi-send me-2" aria-hidden="true"></i>Send Message
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── CONTACT INFO ────────────────────────────── -->
        <div class="col-12 col-lg-5">
            <h2 class="h5 fw-bold mb-4">Get in Touch</h2>

            <?php
            $info = [
                ['bi-telephone-fill', 'Phone',   '+65 6123 4567', 'tel:+6561234567'],
                ['bi-envelope-fill',  'Email',   'hello@driveeasy.com.sg', 'mailto:hello@driveeasy.com.sg'],
                ['bi-clock-fill',     'Hours',   'Monday – Sunday: 8:00 AM – 9:00 PM', null],
                ['bi-geo-alt-fill',   'Address', 'Level 12, One Raffles Place, 048616 Singapore', null],
            ];
            foreach ($info as $item): ?>
            <div class="d-flex gap-3 mb-4">
                <div class="bg-warning rounded p-2 flex-shrink-0" style="width:42px;height:42px;display:flex;align-items:center;justify-content:center;">
                    <i class="bi <?= $item[0] ?> text-dark" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="fw-semibold"><?= $item[1] ?></div>
                    <?php if ($item[3]): ?>
                    <a href="<?= $item[3] ?>" class="text-muted small"><?= htmlspecialchars($item[2]) ?></a>
                    <?php else: ?>
                    <div class="text-muted small"><?= htmlspecialchars($item[2]) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Branch locations -->
            <div class="card border-0 bg-light p-4 mt-4">
                <h3 class="h6 fw-bold mb-3">
                    <i class="bi bi-building me-2 text-warning" aria-hidden="true"></i>
                    Our Branches
                </h3>
                <ul class="list-unstyled small text-muted mb-0">
                    <li class="mb-1"><i class="bi bi-geo me-2" aria-hidden="true"></i>Changi Airport Terminal 1</li>
                    <li class="mb-1"><i class="bi bi-geo me-2" aria-hidden="true"></i>Changi Airport Terminal 2</li>
                    <li class="mb-1"><i class="bi bi-geo me-2" aria-hidden="true"></i>Changi Airport Terminal 3</li>
                    <li class="mb-1"><i class="bi bi-geo me-2" aria-hidden="true"></i>Marina Bay Sands</li>
                    <li class="mb-1"><i class="bi bi-geo me-2" aria-hidden="true"></i>Orchard Road (Orchard MRT)</li>
                    <li class="mb-1"><i class="bi bi-geo me-2" aria-hidden="true"></i>Jurong East</li>
                    <li class="mb-0"><i class="bi bi-geo me-2" aria-hidden="true"></i>Woodlands</li>
                </ul>
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
