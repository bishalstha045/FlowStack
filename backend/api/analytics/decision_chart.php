<?php
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/db.php';
setApiHeaders();
$uid = requireAuth();
try {
    $pdo = getPDO();
    $r   = $pdo->prepare("SELECT outcome, COUNT(*) c FROM decisions WHERE user_id=? GROUP BY outcome");
    $r->execute([$uid]);
    $breakdown = ['good'=>0,'bad'=>0,'neutral'=>0];
    foreach ($r->fetchAll() as $row) $breakdown[$row['outcome']] = (int)$row['c'];
    $total = array_sum($breakdown);
    $rate  = $total > 0 ? round(($breakdown['good']/$total)*100) : 0;
    jsonOk(['breakdown'=>$breakdown,'total'=>$total,'win_rate'=>$rate]);
} catch (Exception $e) { jsonError('Server error', 500); }
