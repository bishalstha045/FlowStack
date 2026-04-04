<?php
/**
 * FlowStack — Focus Sessions
 * GET  → stats + recent sessions
 * POST → save session { duration_minutes }
 */
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/db.php';
setApiHeaders();
$uid = requireAuth();

try {
    $pdo = getPDO();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $r = $pdo->prepare("SELECT COALESCE(SUM(duration_minutes),0) FROM focus_sessions WHERE user_id=? AND session_date=CURDATE()");
        $r->execute([$uid]); $todayMins = (int)$r->fetchColumn();

        $r = $pdo->prepare("SELECT COALESCE(SUM(duration_minutes),0) FROM focus_sessions WHERE user_id=? AND session_date>=DATE_SUB(CURDATE(),INTERVAL 6 DAY)");
        $r->execute([$uid]); $weekMins  = (int)$r->fetchColumn();

        $r = $pdo->prepare("SELECT COALESCE(AVG(duration_minutes),0) FROM focus_sessions WHERE user_id=?");
        $r->execute([$uid]); $avgMins   = (int)round($r->fetchColumn());

        $r = $pdo->prepare("SELECT id, duration_minutes, session_date, time_of_day FROM focus_sessions WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
        $r->execute([$uid]);

        jsonOk([
            'today_minutes' => $todayMins,
            'week_hours'    => round($weekMins/60, 1),
            'avg_minutes'   => $avgMins,
            'sessions'      => $r->fetchAll(),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

    $body = jsonBody();
    $mins = (int)($body['duration_minutes'] ?? 0);
    if ($mins < 1) jsonError('Minimum 1 minute.');

    $hour = (int)date('H');
    $tod  = $hour < 12 ? 'morning' : ($hour < 17 ? 'afternoon' : ($hour < 21 ? 'evening' : 'night'));

    $pdo->prepare('INSERT INTO focus_sessions (user_id, duration_minutes, session_date, time_of_day) VALUES (?,?,CURDATE(),?)')
        ->execute([$uid, $mins, $tod]);

    jsonOk(['duration_minutes' => $mins, 'time_of_day' => $tod], 201);

} catch (Exception $e) {
    jsonError('Server error: ' . $e->getMessage(), 500);
}
