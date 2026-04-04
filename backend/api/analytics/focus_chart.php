<?php
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/db.php';
setApiHeaders();
$uid = requireAuth();
try {
    $pdo  = getPDO();
    $stmt = $pdo->prepare("SELECT session_date, SUM(duration_minutes) AS minutes FROM focus_sessions WHERE user_id=? AND session_date>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY session_date ORDER BY session_date ASC");
    $stmt->execute([$uid]);
    $map = [];
    foreach ($stmt->fetchAll() as $r) $map[$r['session_date']] = (int)$r['minutes'];
    $labels = []; $data = [];
    for ($i=6;$i>=0;$i--) { $d=date('Y-m-d',strtotime("-{$i} days")); $labels[]=date('D',strtotime($d)); $data[]=$map[$d]??0; }
    $tod = $pdo->prepare("SELECT time_of_day, COUNT(*) AS sessions, SUM(duration_minutes) AS total_mins FROM focus_sessions WHERE user_id=? GROUP BY time_of_day");
    $tod->execute([$uid]);
    jsonOk(['labels'=>$labels,'data'=>$data,'time_of_day'=>$tod->fetchAll()]);
} catch (Exception $e) { jsonError('Server error', 500); }
