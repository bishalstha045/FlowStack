<?php
/**
 * FlowStack Backend Shared Helpers
 * Every API file includes this FIRST.
 *
 * Handles: session cookie config, session start, JSON helpers, auth guard.
 */

// Configure session cookie BEFORE starting (must be before session_start)
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (int)($_SERVER['SERVER_PORT'] ?? 80) === 443;
    session_set_cookie_params([
        'lifetime' => 86400 * 7,  // 7 days
        'path'     => '/',        // share across /frontend and /backend
        'secure'   => $isHttps,   // HTTPS-only on production
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}


// ── Standard JSON response headers ─────────────────────────────
function setApiHeaders(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');

    // Allow same-origin requests with credentials
    // On InfinityFree, frontend and backend share the same domain so this is safe
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $host   = $_SERVER['HTTP_HOST'] ?? '';

    // Only allow the request if origin matches our host (same domain)
    if ($origin && (
        strpos($origin, $host) !== false ||   // Same domain
        strpos($origin, 'localhost') !== false // Local dev
    )) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Accept');
    }

    // Handle pre-flight
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204); exit;
    }
}


// ── JSON success ────────────────────────────────────────────────
function jsonOk(array $data = [], int $code = 200): void
{
    http_response_code($code);
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

// ── JSON error ──────────────────────────────────────────────────
function jsonError(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

// ── Auth guard ──────────────────────────────────────────────────
function requireAuth(): int
{
    if (empty($_SESSION['user_id']) || !is_int($_SESSION['user_id'])) {
        jsonError('Unauthorized please log in.', 401);
    }
    return (int) $_SESSION['user_id'];
}

// ── Parse JSON body ─────────────────────────────────────────────
function jsonBody(): array
{
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}
