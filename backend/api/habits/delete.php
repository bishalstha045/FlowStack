<?php
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/db.php';
setApiHeaders();
$uid = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);
$body     = jsonBody();
$habit_id = (int)($body['habit_id'] ?? 0);
if (!$habit_id) jsonError('habit_id required');
try {
    $pdo = getPDO();
    $pdo->prepare('DELETE FROM habits WHERE id=? AND user_id=?')->execute([$habit_id, $uid]);
    jsonOk();
} catch (Exception $e) { jsonError('Server error', 500); }
