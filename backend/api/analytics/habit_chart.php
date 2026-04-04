<?php
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/db.php';
setApiHeaders();
$uid = requireAuth();
try {
    $pdo  = getPDO();
    $stmt = $pdo->prepare("SELECT completed_date, COUNT(*) AS completions FROM habit_logs WHERE user_id=? AND completed_date>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY completed_date ORDER BY completed_date ASC");
    $stmt->execute([$uid]);
    $map = [];
    foreach ($stmt->fetchAll() as $r) $map[$r['completed_date']] = (int)$r['completions'];
    $labels = []; $data = [];
    for ($i=6;$i>=0;$i--) { $d=date('Y-m-d',strtotime("-{$i} days")); $labels[]=date('D',strtotime($d)); $data[]=$map[$d]??0; }
    $top = $pdo->prepare('SELECT name, streak FROM habits WHERE user_id=? ORDER BY streak DESC LIMIT 5');
    $top->execute([$uid]);
    jsonOk(['labels'=>$labels,'data'=>$data,'top_streaks'=>$top->fetchAll()]);
} catch (Exception $e) { jsonError('Server error', 500); }
