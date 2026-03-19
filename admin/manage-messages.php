<?php
/**
 * admin/manage-messages.php
 * DriveEasy Car Rentals — Admin Contact Messages Management
 *
 * Features:
 * - View all contact messages (filter by read/unread)
 * - Mark messages as read / unread
 * - Delete messages
 * - View full message in a modal
 * - Requires admin role
 */
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

requireAdmin();

$pageTitle = 'Manage Messages – DriveEasy Admin';
$csrf      = generateCsrfToken();

// ── HANDLE POST ACTIONS ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    $action    = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS);
    $messageId = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT);

    if ($messageId && $action) {
        switch ($action) {
            case 'mark_read':
                $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = :id")
                    ->execute([':id' => $messageId]);
                setFlash('success', 'Message #' . $messageId . ' marked as read.');
                break;

            case 'mark_unread':
                $pdo->prepare("UPDATE contact_messages SET is_read = 0 WHERE id = :id")
                    ->execute([':id' => $messageId]);
                setFlash('success', 'Message #' . $messageId . ' marked as unread.');
                break;

            case 'delete':
                $pdo->prepare("DELETE FROM contact_messages WHERE id = :id")
                    ->execute([':id' => $messageId]);
                setFlash('success', 'Message #' . $messageId . ' deleted.');
                break;

            default:
                setFlash('danger', 'Invalid action.');
        }
    } else {
        setFlash('danger', 'Invalid request.');
    }

    header('Location: /admin/manage-messages.php' . (isset($_POST['filter']) ? '?filter=' . urlencode($_POST['filter']) : ''));
    exit;
}

// ── Filter parameters ───────────────────────────────────────
$filterRaw = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$filter = in_array($filterRaw, ['all', 'unread', 'read']) ? $filterRaw : 'all';

if ($filter === 'unread') {
    $sql = "SELECT id, name, email, subject, message, is_read, submitted_at FROM contact_messages WHERE is_read = 0 ORDER BY submitted_at DESC";
} elseif ($filter === 'read') {
    $sql = "SELECT id, name, email, subject, message, is_read, submitted_at FROM contact_messages WHERE is_read = 1 ORDER BY submitted_at DESC";
} else {
    $sql = "SELECT id, name, email, subject, message, is_read, submitted_at FROM contact_messages ORDER BY submitted_at DESC";
}

$messages = $pdo->query($sql)->fetchAll();

// ── Counts for badges ───────────────────────────────────────
$counts = [
    'all'    => (int) $pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn(),
    'unread' => (int) $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn(),
    'read'   => (int) $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 1")->fetchColumn(),
];
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
    <nav class="admin-sidebar d-none d-lg-flex flex-column" style="width:240px;flex-shrink:0;" aria-label="Admin navigation">
        <a href="/admin/dashboard.php" class="d-flex align-items-center gap-2 px-4 py-3 text-decoration-none mb-2">
            <span class="fw-bold fs-5"><span class="text-warning">Drive</span><span class="text-white">Easy</span></span>
            <span class="badge bg-warning text-dark" style="font-size:0.6rem;">ADMIN</span>
        </a>
        <div class="px-3">
            <ul class="nav flex-column">
                <li><a class="nav-link" href="/admin/dashboard.php"><i class="bi bi-speedometer2 me-2" aria-hidden="true"></i>Dashboard</a></li>
                <li><a class="nav-link" href="/admin/manage-cars.php"><i class="bi bi-car-front me-2" aria-hidden="true"></i>Manage Cars</a></li>
                <li><a class="nav-link" href="/admin/manage-bookings.php"><i class="bi bi-calendar-check me-2" aria-hidden="true"></i>Manage Bookings</a></li>
                <li>
                    <a class="nav-link active" href="/admin/manage-messages.php">
                        <i class="bi bi-envelope me-2" aria-hidden="true"></i>Messages
                        <?php if ($counts['unread'] > 0): ?>
                        <span class="badge bg-danger ms-auto"><?= $counts['unread'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
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
                <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-label="Open menu">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <h1 class="h5 mb-0 fw-bold">Messages</h1>
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php if ($counts['unread'] > 0): ?>
                <span class="badge bg-danger"><?= $counts['unread'] ?> unread</span>
                <?php endif; ?>
                <span class="small text-muted">Welcome, <?= currentUserName() ?></span>
            </div>
        </header>

        <main class="p-4">
            <?= renderFlash() ?>

            <!-- Filter tabs -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div class="d-flex flex-wrap gap-1" role="group" aria-label="Filter messages by status">
                    <?php
                    $tabLabels = ['all' => 'All', 'unread' => 'Unread', 'read' => 'Read'];
                    foreach ($tabLabels as $val => $label):
                        $active = $filter === $val ? 'btn-dark' : 'btn-outline-dark';
                    ?>
                        <a href="/admin/manage-messages.php?filter=<?= $val ?>" class="btn btn-sm <?= $active ?>">
                            <?= htmlspecialchars($label) ?>
                            <span class="badge <?= $filter === $val ? 'bg-warning text-dark' : 'bg-secondary' ?> ms-1"><?= $counts[$val] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Messages table -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Sender</th>
                                    <th scope="col">Subject</th>
                                    <th scope="col">Message</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($messages)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="bi bi-envelope-open fs-2 d-block mb-2" aria-hidden="true"></i>
                                        No <?= $filter !== 'all' ? htmlspecialchars($filter) . ' ' : '' ?>messages found.
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($messages as $m):
                                    $isUnread = ($m['is_read'] == 0);
                                    $msgPreview = (strlen($m['message']) > 80) ? substr($m['message'], 0, 80) . '...' : $m['message'];
                                ?>
                                <tr class="<?= $isUnread ? 'table-warning' : '' ?>">
                                    <td class="fw-semibold">#<?= (int)$m['id'] ?></td>
                                    <td>
                                        <div class="fw-semibold small"><?= htmlspecialchars($m['name']) ?></div>
                                        <div class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($m['email']) ?></div>
                                    </td>
                                    <td class="small">
                                        <?php if ($isUnread): ?><i class="bi bi-circle-fill text-danger me-1" style="font-size:0.5rem;" role="img" aria-label="Unread"></i><?php endif; ?>
                                        <?= htmlspecialchars($m['subject'] ?: '(No subject)') ?>
                                    </td>
                                    <td class="small text-muted" style="max-width:250px;">
                                        <?= htmlspecialchars($msgPreview) ?>
                                    </td>
                                    <td class="small text-muted text-nowrap">
                                        <?= htmlspecialchars(date('d M Y, H:i', strtotime($m['submitted_at']))) ?>
                                    </td>
                                    <td>
                                        <?php if ($isUnread): ?>
                                            <span class="badge bg-warning text-dark px-2 py-1">Unread</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary px-2 py-1">Read</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <!-- View button -->
                                            <button type="button" class="btn btn-xs btn-outline-secondary btn-sm"
                                                    title="View full message"
                                                    aria-label="View message #<?= (int)$m['id'] ?>"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#msgModal<?= (int)$m['id'] ?>">
                                                <i class="bi bi-eye" aria-hidden="true"></i>
                                            </button>

                                            <!-- Toggle read/unread -->
                                            <form method="POST" action="/admin/manage-messages.php" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                                <input type="hidden" name="message_id" value="<?= (int)$m['id'] ?>">
                                                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                                                <?php if ($isUnread): ?>
                                                    <input type="hidden" name="action" value="mark_read">
                                                    <button type="submit" class="btn btn-xs btn-success btn-sm"
                                                            title="Mark as read"
                                                            aria-label="Mark message #<?= (int)$m['id'] ?> as read">
                                                        <i class="bi bi-check-lg" aria-hidden="true"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <input type="hidden" name="action" value="mark_unread">
                                                    <button type="submit" class="btn btn-xs btn-warning btn-sm"
                                                            title="Mark as unread"
                                                            aria-label="Mark message #<?= (int)$m['id'] ?> as unread">
                                                        <i class="bi bi-envelope" aria-hidden="true"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </form>

                                            <!-- Delete -->
                                            <form method="POST" action="/admin/manage-messages.php" class="d-inline"
                                                  onsubmit="return confirm('Delete message #<?= (int)$m['id'] ?>?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                                <input type="hidden" name="message_id" value="<?= (int)$m['id'] ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                                                <button type="submit" class="btn btn-xs btn-danger btn-sm"
                                                        title="Delete message"
                                                        aria-label="Delete message #<?= (int)$m['id'] ?>">
                                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<!-- ── Message Detail Modals ─────────────────────────────────── -->
<?php foreach ($messages as $m):
    $isUnread = ($m['is_read'] == 0);
?>
<div class="modal fade" id="msgModal<?= (int)$m['id'] ?>" tabindex="-1"
     aria-labelledby="msgModalLabel<?= (int)$m['id'] ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="h5 modal-title" id="msgModalLabel<?= (int)$m['id'] ?>">
                    Message #<?= (int)$m['id'] ?>
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row small mb-0">
                    <dt class="col-sm-3">From</dt>
                    <dd class="col-sm-9">
                        <?= htmlspecialchars($m['name']) ?>
                        <br><span class="text-muted"><?= htmlspecialchars($m['email']) ?></span>
                    </dd>

                    <dt class="col-sm-3">Subject</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars($m['subject'] ?: '(No subject)') ?></dd>

                    <dt class="col-sm-3">Date</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars(date('d M Y, H:i:s', strtotime($m['submitted_at']))) ?></dd>

                    <dt class="col-sm-3">Status</dt>
                    <dd class="col-sm-9">
                        <?php if ($isUnread): ?>
                            <span class="badge bg-warning text-dark">Unread</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Read</span>
                        <?php endif; ?>
                    </dd>
                </dl>
                <hr>
                <div class="p-3 bg-light rounded" style="white-space:pre-wrap;word-break:break-word;">
                    <?= htmlspecialchars($m['message']) ?>
                </div>
            </div>
            <div class="modal-footer">
                <?php if ($isUnread): ?>
                <form method="POST" action="/admin/manage-messages.php" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="message_id" value="<?= (int)$m['id'] ?>">
                    <input type="hidden" name="action" value="mark_read">
                    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Mark as Read
                    </button>
                </form>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

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
            <li class="nav-item">
                <a class="nav-link text-white" href="/admin/manage-messages.php">
                    Messages
                    <?php if ($counts['unread'] > 0): ?>
                    <span class="badge bg-danger ms-1"><?= $counts['unread'] ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item"><a class="nav-link text-white" href="/admin/manage-promotion.php">Promotions</a></li>
            <li class="nav-item"><a class="nav-link text-danger" href="/logout.php">Logout</a></li>
        </ul>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/main.js"></script>

</body>
</html>
