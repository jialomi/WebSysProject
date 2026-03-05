<?php
/**
 * includes/db.php
 * DriveEasy Car Rentals — PDO Database Connection
 *
 * Returns a singleton PDO instance. All queries in this project
 * MUST use prepared statements through this connection.
 *
 * Usage:  require_once __DIR__ . '/db.php';
 *         $stmt = $pdo->prepare("SELECT ...");
 */

// ── Database credentials ─────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'driveeasy_db');
define('DB_USER', 'inf1005-sqldev');       // Change for production
define('DB_PASS', 'sasuke8744');           // Change for production
define('DB_CHAR', 'utf8mb4');

/**
 * Returns a shared PDO connection (singleton pattern).
 * Throws a RuntimeException on failure (never exposes credentials).
 */
function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHAR
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // Throw exceptions on error
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // Return assoc arrays
            PDO::ATTR_EMULATE_PREPARES   => false,                    // Use real prepared statements
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log the real error privately; show a generic message publicly
            error_log('DB Connection Error: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed. Please try again later.');
        }
    }

    return $pdo;
}

// Expose $pdo as a global variable for convenience
$pdo = getPDO();
