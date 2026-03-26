<?php
/**
 * admin/manage-cars.php
 * DriveEasy Car Rentals — Admin Car Management (Full CRUD)
 *
 * Actions:
 * - List all cars
 * - Add new car (with image upload)
 * - Edit existing car
 * - Delete car
 * - Toggle availability status
 */
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

requireAdmin();

$pageTitle = 'Manage Cars – DriveEasy Admin';
$csrf      = generateCsrfToken();
$errors    = [];

// ── Auto-create car_images table if it doesn't exist ────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS car_images (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        car_id     INT          NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        sort_order TINYINT      NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE
    ) ENGINE=InnoDB
");

// ── Determine view mode ─────────────────────────────────────
$editId   = filter_input(INPUT_GET, 'edit',         FILTER_VALIDATE_INT);
$delId    = filter_input(INPUT_GET, 'delete',       FILTER_VALIDATE_INT);
$delImgId = filter_input(INPUT_GET, 'delete_image', FILTER_VALIDATE_INT);

// ── HANDLE DELETE IMAGE ────────────────────────────────────
if ($delImgId) {
    $imgStmt = $pdo->prepare("SELECT * FROM car_images WHERE id = :id LIMIT 1");
    $imgStmt->execute([':id' => $delImgId]);
    $imgRow = $imgStmt->fetch();
    if ($imgRow) {
        $pdo->prepare("DELETE FROM car_images WHERE id = :id")->execute([':id' => $delImgId]);
        // If deleted image was the primary thumbnail, update cars.image_path
        $carStmt = $pdo->prepare("SELECT image_path FROM cars WHERE id = :id LIMIT 1");
        $carStmt->execute([':id' => $imgRow['car_id']]);
        $currentCar = $carStmt->fetch();
        if ($currentCar && $currentCar['image_path'] === $imgRow['image_path']) {
            $nextStmt = $pdo->prepare("SELECT image_path FROM car_images WHERE car_id = :cid ORDER BY sort_order, id LIMIT 1");
            $nextStmt->execute([':cid' => $imgRow['car_id']]);
            $next = $nextStmt->fetchColumn() ?: 'assets/images/placeholder.jpg';
            $pdo->prepare("UPDATE cars SET image_path = :img WHERE id = :id")->execute([':img' => $next, ':id' => $imgRow['car_id']]);
        }
        setFlash('success', 'Image deleted.');
    }
    header('Location: /admin/manage-cars.php?edit=' . (int)($imgRow['car_id'] ?? 0));
    exit;
}

// ── HANDLE DELETE CAR ───────────────────────────────────────
if ($delId) {
    $hasBookings = $pdo->prepare(
        "SELECT COUNT(*) FROM bookings WHERE car_id = :id AND status != 'cancelled'"
    );
    $hasBookings->execute([':id' => $delId]);
    if ((int)$hasBookings->fetchColumn() > 0) {
        setFlash('danger', 'Cannot delete car with active/confirmed bookings. Cancel those first.');
    } else {
        $pdo->prepare("DELETE FROM cars WHERE id = :id")->execute([':id' => $delId]);
        setFlash('success', 'Car deleted successfully.');
    }
    header('Location: /admin/manage-cars.php');
    exit;
}

// ── Fetch car being edited ──────────────────────────────────
$editCar    = null;
$editImages = [];
if ($editId) {
    $stmt = $pdo->prepare("SELECT * FROM cars WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $editId]);
    $editCar = $stmt->fetch();
    if (!$editCar) {
        header('Location: /admin/manage-cars.php');
        exit;
    }
    $imgStmt = $pdo->prepare("SELECT * FROM car_images WHERE car_id = :id ORDER BY sort_order, id");
    $imgStmt->execute([':id' => $editId]);
    $editImages = $imgStmt->fetchAll();
}

// ── HANDLE ADD / UPDATE (POST) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    $action  = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS);
    $postId  = filter_input(INPUT_POST, 'car_id', FILTER_VALIDATE_INT);

    // Collect and validate inputs
    $brand        = trim(filter_input(INPUT_POST, 'brand',        FILTER_SANITIZE_SPECIAL_CHARS));
    $model        = trim(filter_input(INPUT_POST, 'model',        FILTER_SANITIZE_SPECIAL_CHARS));
    $year         = filter_input(INPUT_POST, 'year',         FILTER_VALIDATE_INT);
    $type         = filter_input(INPUT_POST, 'type',         FILTER_SANITIZE_SPECIAL_CHARS);
    $dailyRate    = filter_input(INPUT_POST, 'daily_rate',   FILTER_VALIDATE_FLOAT);
    $status       = filter_input(INPUT_POST, 'status',       FILTER_SANITIZE_SPECIAL_CHARS);
    $seats        = filter_input(INPUT_POST, 'seats',        FILTER_VALIDATE_INT);
    $transmission = filter_input(INPUT_POST, 'transmission', FILTER_SANITIZE_SPECIAL_CHARS);
    $fuelType     = trim(filter_input(INPUT_POST, 'fuel_type', FILTER_SANITIZE_SPECIAL_CHARS));
    $description  = trim(filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS));

    // Validations
    if (empty($brand))      $errors[] = 'Brand is required.';
    if (empty($model))      $errors[] = 'Model is required.';
    if (!$year || $year < 2000 || $year > (int)date('Y') + 1)
                            $errors[] = 'Valid year required.';
    if (!in_array($type, ['sedan','SUV','MPV','sports']))
                            $errors[] = 'Invalid car type.';
    if (!$dailyRate || $dailyRate <= 0)
                            $errors[] = 'Daily rate must be greater than 0.';
    if (!in_array($status, ['available','unavailable']))
                            $errors[] = 'Invalid status.';
    if (!$seats || $seats < 1 || $seats > 9)
                            $errors[] = 'Seats must be 1–9.';

    // Handle multiple image uploads (optional)
    $uploadedPaths = [];
    $allowedMimes  = ['image/jpeg', 'image/png', 'image/webp'];
    $destDir       = dirname(__DIR__) . '/assets/images/';
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);

    if (!empty($_FILES['car_images']['name'][0])) {
        $fileCount = count($_FILES['car_images']['name']);
        $finfo     = new finfo(FILEINFO_MIME_TYPE);

        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['car_images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $tmpName  = $_FILES['car_images']['tmp_name'][$i];
            $size     = $_FILES['car_images']['size'][$i];
            $origName = $_FILES['car_images']['name'][$i];
            $mime     = $finfo->file($tmpName);

            if (!in_array($mime, $allowedMimes)) {
                $errors[] = '"' . htmlspecialchars($origName) . '" must be JPEG, PNG, or WebP.';
                continue;
            }
            if ($size > 3 * 1024 * 1024) {
                $errors[] = '"' . htmlspecialchars($origName) . '" exceeds 3 MB limit.';
                continue;
            }
            $ext      = pathinfo($origName, PATHINFO_EXTENSION);
            $safeName = 'car_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($tmpName, $destDir . $safeName)) {
                $uploadedPaths[] = 'assets/images/' . $safeName;
            } else {
                $errors[] = 'Failed to upload "' . htmlspecialchars($origName) . '".';
            }
        }
    }

    if (empty($errors)) {
        if ($action === 'add') {
            $primaryImage = $uploadedPaths[0] ?? 'assets/images/placeholder.jpg';
            $pdo->prepare(
                "INSERT INTO cars (brand, model, year, type, daily_rate, status, image_path, description, seats, transmission, fuel_type)
                 VALUES (:brand, :model, :year, :type, :rate, :status, :img, :desc, :seats, :trans, :fuel)"
            )->execute([
                ':brand'  => $brand,  ':model' => $model,   ':year'   => $year,
                ':type'   => $type,   ':rate'  => $dailyRate,':status' => $status,
                ':img'    => $primaryImage, ':desc' => $description,
                ':seats'  => $seats,  ':trans' => $transmission, ':fuel' => $fuelType,
            ]);
            $newCarId = (int)$pdo->lastInsertId();
            // Insert into car_images table
            $imgIns = $pdo->prepare("INSERT INTO car_images (car_id, image_path, sort_order) VALUES (:cid, :path, :ord)");
            foreach ($uploadedPaths as $idx => $path) {
                $imgIns->execute([':cid' => $newCarId, ':path' => $path, ':ord' => $idx]);
            }
            setFlash('success', 'Car "' . htmlspecialchars($brand . ' ' . $model) . '" added successfully.');

        } elseif ($action === 'update' && $postId) {
            $pdo->prepare(
                "UPDATE cars SET brand=:brand, model=:model, year=:year, type=:type,
                                  daily_rate=:rate, status=:status,
                                  description=:desc, seats=:seats, transmission=:trans,
                                  fuel_type=:fuel
                 WHERE id=:id"
            )->execute([
                ':brand'  => $brand,  ':model' => $model,   ':year'   => $year,
                ':type'   => $type,   ':rate'  => $dailyRate,':status' => $status,
                ':desc'   => $description,
                ':seats'  => $seats,  ':trans' => $transmission, ':fuel' => $fuelType,
                ':id'     => $postId,
            ]);
            // Append new images
            if (!empty($uploadedPaths)) {
                $maxOrdStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order),-1) FROM car_images WHERE car_id = :cid");
                $maxOrdStmt->execute([':cid' => $postId]);
                $maxOrd = (int)$maxOrdStmt->fetchColumn();
                $imgIns = $pdo->prepare("INSERT INTO car_images (car_id, image_path, sort_order) VALUES (:cid, :path, :ord)");
                foreach ($uploadedPaths as $idx => $path) {
                    $imgIns->execute([':cid' => $postId, ':path' => $path, ':ord' => $maxOrd + 1 + $idx]);
                }
            }
            // Sync primary thumbnail
            $firstStmt = $pdo->prepare("SELECT image_path FROM car_images WHERE car_id = :cid ORDER BY sort_order, id LIMIT 1");
            $firstStmt->execute([':cid' => $postId]);
            $thumb = $firstStmt->fetchColumn() ?: 'assets/images/placeholder.jpg';
            $pdo->prepare("UPDATE cars SET image_path = :img WHERE id = :id")->execute([':img' => $thumb, ':id' => $postId]);
            setFlash('success', 'Car updated successfully.');
        }
        header('Location: /admin/manage-cars.php');
        exit;
    }
}

// ── Fetch all cars for listing ──────────────────────────────
$allCars = $pdo->query(
    "SELECT c.*, (SELECT COUNT(*) FROM bookings b WHERE b.car_id=c.id AND b.status!='cancelled') AS active_bookings
     FROM cars c ORDER BY c.created_at DESC"
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

<div class="d-flex" style="min-height:100vh;">

    <nav class="admin-sidebar d-none d-lg-flex flex-column" style="width:240px;flex-shrink:0;"
         aria-label="Admin navigation">
        <a href="/admin/dashboard.php" class="d-flex align-items-center gap-2 px-4 py-3 text-decoration-none mb-2">
            <span class="fw-bold fs-5">
                <span class="text-warning">Drive</span><span class="text-white">Easy</span>
            </span>
            <span class="badge bg-warning text-dark" style="font-size:0.6rem;">ADMIN</span>
        </a>
        <div class="px-3">
            <ul class="nav flex-column">
                <li><a class="nav-link" href="/admin/dashboard.php"><i class="bi bi-speedometer2 me-2" aria-hidden="true"></i>Dashboard</a></li>
                <li><a class="nav-link active" href="/admin/manage-cars.php"><i class="bi bi-car-front me-2" aria-hidden="true"></i>Manage Cars</a></li>
                <li><a class="nav-link" href="/admin/manage-bookings.php"><i class="bi bi-calendar-check me-2" aria-hidden="true"></i>Manage Bookings</a></li>
                <li><a class="nav-link" href="/admin/manage-messages.php"><i class="bi bi-envelope me-2" aria-hidden="true"></i>Messages</a></li>
                <li><a class="nav-link" href="/admin/manage-promotion.php"><i class="bi bi-tag me-2" aria-hidden="true"></i>Promotions</a></li>
                <li><a class="nav-link" href="/fleet.php"><i class="bi bi-grid me-2" aria-hidden="true"></i>View Site</a></li>
            </ul>
            <hr class="border-secondary my-3">
            <ul class="nav flex-column">
                <li><a class="nav-link text-danger" href="/logout.php"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="flex-grow-1 bg-light">
        <header class="bg-white shadow-sm px-4 py-3 d-flex justify-content-between align-items-center sticky-top">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-label="Open admin menu" title="Open admin menu">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <h1 class="h5 mb-0 fw-bold">Manage Cars</h1>
            </div>
            <button class="btn btn-warning btn-sm fw-semibold"
                    data-bs-toggle="modal" data-bs-target="#carModal" aria-label="Add New Car" title="Add New Car">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Add New Car
            </button>
        </header>

        <main class="p-4">
            <?= renderFlash() ?>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
            </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Image</th>
                                    <th scope="col">Car</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Daily Rate</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Active Bookings</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allCars as $car): ?>
                                <tr>
                                    <td>
                                        <img src="/<?= htmlspecialchars($car['image_path']) ?>"
                                             alt=""
                                             width="70" height="48"
                                             style="object-fit:cover;border-radius:6px;"
                                             onerror="this.src='/assets/images/placeholder.jpg'">
                                    </td>
                                    <td>
                                        <div class="fw-semibold">
                                            <?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?>
                                        </div>
                                        <div class="text-muted small"><?= (int)$car['year'] ?></div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= htmlspecialchars($car['type']) ?>">
                                            <?= htmlspecialchars(ucfirst($car['type'])) ?>
                                        </span>
                                    </td>
                                    <td class="fw-semibold">SGD <?= number_format((float)$car['daily_rate'], 2) ?></td>
                                    <td>
                                        <?php if ($car['status'] === 'available'): ?>
                                        <span class="badge bg-success">Available</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Unavailable</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= (int)$car['active_bookings'] ?></td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="/admin/manage-cars.php?edit=<?= (int)$car['id'] ?>"
                                            class="btn btn-sm btn-outline-primary"
                                            aria-label="Edit <?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?>">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                            </a>
                                            <a href="/car-details.php?id=<?= (int)$car['id'] ?>"
                                            class="btn btn-sm btn-outline-info"
                                            target="_blank" rel="noopener"
                                            aria-label="View <?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?> on site">
                                                <i class="bi bi-eye" aria-hidden="true"></i>
                                            </a>
                                            <a href="/admin/manage-cars.php?delete=<?= (int)$car['id'] ?>"
                                            class="btn btn-sm btn-outline-danger btn-cancel-booking"
                                            aria-label="Delete <?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?>" title="Delete Car">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($allCars)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">No cars yet. Add one!</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<div class="modal fade" id="carModal" tabindex="-1"
     aria-labelledby="carModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 fw-bold" id="carModalLabel">
                    <?= $editCar ? 'Edit Car' : 'Add New Car' ?>
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close modal"></button>
            </div>
            <form action="/admin/manage-cars.php<?= $editId ? '?edit=' . $editId : '' ?>"
                  method="POST" enctype="multipart/form-data"
                  class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action"     value="<?= $editCar ? 'update' : 'add' ?>">
                    <?php if ($editCar): ?>
                    <input type="hidden" name="car_id"     value="<?= (int)$editCar['id'] ?>">
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="brand" class="form-label fw-semibold">Brand <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="brand" name="brand"
                                   value="<?= htmlspecialchars($editCar['brand'] ?? '') ?>"
                                   maxlength="50" required placeholder="e.g. Toyota">
                            <div class="invalid-feedback">Required.</div>
                        </div>
                        <div class="col-sm-6">
                            <label for="model" class="form-label fw-semibold">Model <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="model" name="model"
                                   value="<?= htmlspecialchars($editCar['model'] ?? '') ?>"
                                   maxlength="50" required placeholder="e.g. Camry">
                            <div class="invalid-feedback">Required.</div>
                        </div>
                        <div class="col-sm-3">
                            <label for="year" class="form-label fw-semibold">Year <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="number" class="form-control" id="year" name="year"
                                   value="<?= (int)($editCar['year'] ?? date('Y')) ?>"
                                   min="2000" max="<?= date('Y') + 1 ?>" required>
                            <div class="invalid-feedback">Required.</div>
                        </div>
                        <div class="col-sm-3">
                            <label for="type" class="form-label fw-semibold">Type <span class="text-danger" aria-hidden="true">*</span></label>
                            <select class="form-select" id="type" name="type" required>
                                <?php foreach (['sedan','SUV','MPV','sports'] as $t): ?>
                                <option value="<?= $t ?>"
                                    <?= (($editCar['type'] ?? '') === $t) ? 'selected' : '' ?>>
                                    <?= ucfirst($t) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <label for="daily_rate" class="form-label fw-semibold">Daily Rate (SGD) <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="number" class="form-control" id="daily_rate" name="daily_rate"
                                   value="<?= htmlspecialchars($editCar['daily_rate'] ?? '') ?>"
                                   step="0.01" min="1" required>
                            <div class="invalid-feedback">Required.</div>
                        </div>
                        <div class="col-sm-3">
                            <label for="seats" class="form-label fw-semibold">Seats <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="number" class="form-control" id="seats" name="seats"
                                   value="<?= (int)($editCar['seats'] ?? 5) ?>"
                                   min="1" max="9" required>
                        </div>
                        <div class="col-sm-4">
                            <label for="transmission" class="form-label fw-semibold">Transmission</label>
                            <select class="form-select" id="transmission" name="transmission">
                                <option value="automatic" <?= (($editCar['transmission'] ?? '') === 'automatic') ? 'selected' : '' ?>>Automatic</option>
                                <option value="manual"    <?= (($editCar['transmission'] ?? '') === 'manual')    ? 'selected' : '' ?>>Manual</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label for="fuel_type" class="form-label fw-semibold">Fuel Type</label>
                            <input type="text" class="form-control" id="fuel_type" name="fuel_type"
                                   value="<?= htmlspecialchars($editCar['fuel_type'] ?? 'Petrol') ?>"
                                   maxlength="30">
                        </div>
                        <div class="col-sm-4">
                            <label for="status" class="form-label fw-semibold">Status <span class="text-danger" aria-hidden="true">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="available"   <?= (($editCar['status'] ?? '') === 'available')   ? 'selected' : '' ?>>Available</option>
                                <option value="unavailable" <?= (($editCar['status'] ?? '') === 'unavailable') ? 'selected' : '' ?>>Unavailable</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label fw-semibold">Description</label>
                            <textarea class="form-control" id="description" name="description"
                                      rows="3" maxlength="1000"
                                      placeholder="Vehicle description visible to customers…"><?= htmlspecialchars($editCar['description'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label for="car_images" class="form-label fw-semibold">
                                Car Images <?= $editCar ? '<span class="text-muted fw-normal">(upload to add more)</span>' : '' ?>
                            </label>
                            <input type="file" class="form-control" id="car_images"
                                   name="car_images[]" accept="image/jpeg,image/png,image/webp" multiple>
                            <div class="form-text">JPEG / PNG / WebP, max 3 MB each. Select multiple files at once.</div>

                            <?php if (!empty($editImages)): ?>
                            <div class="mt-3">
                                <label class="form-label fw-semibold small text-muted">Existing Images (<?= count($editImages) ?>)</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($editImages as $img): ?>
                                    <div class="position-relative" style="width:100px;">
                                        <img src="/<?= htmlspecialchars($img['image_path']) ?>"
                                             alt="Car image" class="rounded border"
                                             style="width:100px;height:70px;object-fit:cover;"
                                             onerror="this.src='/assets/images/placeholder.jpg'">
                                        <a href="/admin/manage-cars.php?delete_image=<?= (int)$img['id'] ?>"
                                           class="btn btn-danger btn-sm position-absolute top-0 end-0 p-0 d-flex align-items-center justify-content-center"
                                           style="width:20px;height:20px;font-size:0.65rem;border-radius:50%;transform:translate(30%,-30%);"
                                           title="Delete this image"
                                           onclick="return confirm('Delete this image?')">
                                            <i class="bi bi-x" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold">
                        <?= $editCar ? 'Update Car' : 'Add Car' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mobile offcanvas sidebar -->
<div class="offcanvas offcanvas-start bg-dark text-white" id="adminSidebar"
     tabindex="-1" aria-labelledby="adminSidebarLabel">
    <div class="offcanvas-header">
        <div class="offcanvas-title h2 fw-bold" id="adminSidebarLabel">
            <span class="text-warning">Drive</span>Easy Admin
        </div>
        <button type="button" class="btn-close btn-close-white"
                data-bs-dismiss="offcanvas" aria-label="Close admin menu"></button>
    </div>
    <div class="offcanvas-body p-3">
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link text-white" href="/admin/dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="/admin/manage-cars.php">Manage Cars</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="/admin/manage-bookings.php">Manage Bookings</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="/admin/manage-messages.php">Messages</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="/admin/manage-promotion.php">Promotions</a></li>
            <li class="nav-item"><a class="nav-link text-danger" href="/logout.php">Logout</a></li>
        </ul>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/main.js"></script>

<script>
// Auto-open the modal if we're in edit mode
<?php if ($editCar): ?>
document.addEventListener('DOMContentLoaded', function() {
    new bootstrap.Modal(document.getElementById('carModal')).show();
});
<?php endif; ?>
</script>

</body>
</html>