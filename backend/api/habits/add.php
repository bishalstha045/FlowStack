<?php
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/db.php';
setApiHeaders();
$uid = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);
$body = jsonBody();
$name = trim($body['name'] ?? '');
if (strlen($name) < 2 || strlen($name) > 200) jsonError('Habit name must be 2–200 characters.');
try {
    $pdo = getPDO();
    $ins = $pdo->prepare('INSERT INTO habits (user_id, name) VALUES (?,?)');
    $ins->execute([$uid, $name]);
    jsonOk(['id' => (int)$pdo->lastInsertId(), 'name' => $name], 201);
} catch (Exception $e) { jsonError('Server error', 500); }
