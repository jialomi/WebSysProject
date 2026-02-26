<?php
/**
 * includes/auth.php
 * DriveEasy Car Rentals — Session-Based Authentication Helpers
 *
 * Include this file on every page that needs auth awareness.
 * Always include BEFORE any output (headers must be sendable).
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Harden session cookie settings
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,   // Set true in production (HTTPS)
        'httponly' => true,    // Prevent JS access to session cookie
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── Authentication checks ────────────────────────────────────

/**
 * Returns true if a user is currently logged in.
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Returns true if the logged-in user has the 'admin' role.
 */
function isAdmin(): bool
{
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'admin';
}

/**
 * Redirects to login page if user is not logged in.
 * Preserves the attempted URL so we can redirect back after login.
 *
 * @param string $redirect  Absolute path to redirect to after login
 */
function requireLogin(string $redirect = ''): void
{
    if (!isLoggedIn()) {
        $target = $redirect ?: $_SERVER['REQUEST_URI'];
        $_SESSION['login_redirect'] = $target;
        header('Location: /login.php?msg=please_login');
        exit;
    }
}

/**
 * Redirects non-admin users to the home page.
 */
function requireAdmin(): void
{
    if (!isAdmin()) {
        header('Location: /index.php?msg=access_denied');
        exit;
    }
}

// ── CSRF Protection ──────────────────────────────────────────

/**
 * Generates a CSRF token and stores it in the session.
 * Call once per form page load; reuse the stored token within the session.
 *
 * @return string  64-char hex token
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates the CSRF token submitted with a POST form.
 * Dies with 403 on failure.
 */
function validateCsrfToken(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    $stored    = $_SESSION['csrf_token'] ?? '';

    if (!$stored || !hash_equals($stored, $submitted)) {
        http_response_code(403);
        die('Invalid or missing CSRF token. Please go back and try again.');
    }
    // Rotate the token after successful validation
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Session helpers ──────────────────────────────────────────

/**
 * Returns the logged-in user's ID, or null.
 */
function currentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/**
 * Returns the logged-in user's display name, or empty string.
 */
function currentUserName(): string
{
    return htmlspecialchars($_SESSION['user_name'] ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Sets a one-time flash message in the session.
 *
 * @param string $type  'success' | 'danger' | 'warning' | 'info'
 * @param string $msg   Message text (will be escaped on output)
 */
function setFlash(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

/**
 * Renders and clears the flash message if one exists.
 * Returns the Bootstrap alert HTML string.
 */
function renderFlash(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $f    = $_SESSION['flash'];
    $type = htmlspecialchars($f['type'], ENT_QUOTES, 'UTF-8');
    $msg  = htmlspecialchars($f['msg'],  ENT_QUOTES, 'UTF-8');
    unset($_SESSION['flash']);

    return <<<HTML
    <div class="alert alert-{$type} alert-dismissible fade show" role="alert">
        {$msg}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    HTML;
}
