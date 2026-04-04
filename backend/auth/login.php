<?php
/**
 * FlowStack — Auth: Login
 * POST /backend/auth/login.php  { email, password }
 */
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/db.php';
setApiHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$body     = jsonBody();
$email    = trim($body['email']    ?? '');
$password =      $body['password'] ?? '';

if (!$email || !$password)                        jsonError('Email and password are required.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL))   jsonError('Enter a valid email address.');

try {
    $pdo  = getPDO();
    $stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonError('Incorrect email or password.', 401);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['name']    = $user['name'];
    $_SESSION['email']   = $user['email'];

    jsonOk(['name' => $user['name']]);
} catch (Exception $e) {
    jsonError('Database error. Please try again.', 500);
}
