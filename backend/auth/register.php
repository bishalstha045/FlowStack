<?php
/**
 * FlowStack Auth: Register
 * POST /backend/auth/register.php  { name, email, password, password_confirm }
 */
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/db.php';
setApiHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$body    = jsonBody();
$name    = trim($body['name']             ?? '');
$email   = trim($body['email']            ?? '');
$pass    =      $body['password']          ?? '';
$confirm =      $body['password_confirm']  ?? '';

if (!$name || !$email || !$pass || !$confirm) jsonError('All fields are required.');
if (strlen($name) < 2 || strlen($name) > 120) jsonError('Name must be 2–120 characters.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonError('Enter a valid email address.');
if (strlen($pass) < 8)  jsonError('Password must be at least 8 characters.');
if ($pass !== $confirm)  jsonError('Passwords do not match.');

try {
    $pdo   = getPDO();
    $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $check->execute([$email]);
    if ($check->fetch()) jsonError('An account with that email already exists.', 409);

    $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)')
        ->execute([$name, $email, password_hash($pass, PASSWORD_BCRYPT)]);

    jsonOk(['message' => 'Account created! You can now sign in.'], 201);
} catch (Exception $e) {
    jsonError('Database error. Please try again.', 500);
}
