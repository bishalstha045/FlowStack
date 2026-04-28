<?php
/**
 * FlowStack Database Configuration
 * /backend/config/db.php
 * Auto-detects: uses localhost when running on XAMPP, live DB on production.
 */

// ── Session cookie path only set if response.php hasn't done it already ─
// (response.php is always included first, so this is a safety net)
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (int)($_SERVER['SERVER_PORT'] ?? 80) === 443;
    session_set_cookie_params([
        'lifetime' => 86400 * 7,   // 7 days
        'path'     => '/',          // share cookie across all paths
        'secure'   => $isHttps,     // only send over HTTPS on production
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}


// ── Auto-detect environment ────────────────────────────────────────
$_fs_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_fs_is_local = (strpos($_fs_host, 'localhost') !== false ||
                 strpos($_fs_host, '127.0.0.1') !== false ||
                 strpos($_fs_host, '::1') !== false);

if ($_fs_is_local) {
    // ── Local XAMPP ────────────────────────────────────────────────
    define('DB_HOST',    'localhost');
    define('DB_NAME',    'flowstack');
    define('DB_USER',    'root');
    define('DB_PASS',    '');
} else {
    // ── InfinityFree Production ────────────────────────────────────
    define('DB_HOST',    'sql107.infinityfree.com');
    define('DB_NAME',    'if0_41579372_flowstack');
    define('DB_USER',    'if0_41579372');
    define('DB_PASS',    'lJkHcqxsqr');
}
define('DB_CHARSET', 'utf8mb4');

function getPDO(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(503);
            header('Content-Type: application/json; charset=utf-8');
            die(json_encode(['ok' => false, 'error' => 'Database unavailable. Please try again later.']));
        }
    }
    return $pdo;
}
