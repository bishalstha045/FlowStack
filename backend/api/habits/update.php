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
    $v   = $pdo->prepare('SELECT id, streak, last_completed FROM habits WHERE id=? AND user_id=?');
    $v->execute([$habit_id, $uid]);
    $habit = $v->fetch();
    if (!$habit) jsonError('Habit not found', 404);
    $today     = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $ins = $pdo->prepare('INSERT IGNORE INTO habit_logs (habit_id, user_id, completed_date) VALUES (?,?,?)');
    $ins->execute([$habit_id, $uid, $today]);
    if ($ins->rowCount() > 0) {
        $streak = ($habit['last_completed'] === $yesterday) ? $habit['streak']+1 : 1;
        $pdo->prepare('UPDATE habits SET streak=?, last_completed=? WHERE id=?')->execute([$streak, $today, $habit_id]);
    }
    $r = $pdo->prepare('SELECT streak FROM habits WHERE id=?');
    $r->execute([$habit_id]);
    jsonOk(['streak' => (int)$r->fetchColumn()]);
} catch (Exception $e) { jsonError('Server error', 500); }
