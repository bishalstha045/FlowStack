<?php
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/db.php';
setApiHeaders();
$uid = requireAuth();
try {
    $pdo   = getPDO();
    $range = $_GET['range'] ?? '7';
    $days  = $range === 'all' ? 365 : max(7, (int)$range);

    $stmt = $pdo->prepare("SELECT completed_date, COUNT(*) AS completions FROM habit_logs WHERE user_id=? AND completed_date>=DATE_SUB(CURDATE(),INTERVAL ? DAY) GROUP BY completed_date ORDER BY completed_date ASC");
    $stmt->execute([$uid, $days]);
    $map = [];
    foreach ($stmt->fetchAll() as $r) $map[$r['completed_date']] = (int)$r['completions'];
    
    $labels = []; $data = [];
    $displayDays = min($days, 30); // Cap display at 30 days for readability
    for ($i=$displayDays-1; $i>=0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $labels[] = $displayDays <= 7 ? date('D', strtotime($d)) : date('M d', strtotime($d));
        $data[] = $map[$d] ?? 0;
    }
    
    $top = $pdo->prepare('SELECT name, streak FROM habits WHERE user_id=? ORDER BY streak DESC LIMIT 5');
    $top->execute([$uid]);
    jsonOk(['labels'=>$labels, 'data'=>$data, 'top_streaks'=>$top->fetchAll()]);
} catch (Exception $e) { jsonError('Server error', 500); }
