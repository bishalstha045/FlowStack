<?php
/**
 * FlowStack Decisions CRUD
 * GET  → paginated list + stats
 * POST → add { decision_text, outcome }
 * POST ?delete=1 → delete { decision_id }
 */
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/db.php';
setApiHeaders();
$uid = requireAuth();

try {
    $pdo = getPDO();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = 10;
        $off   = ($page-1)*$limit;

        $r = $pdo->prepare('SELECT COUNT(*) FROM decisions WHERE user_id=?');
        $r->execute([$uid]); $count = (int)$r->fetchColumn();

        $stmt = $pdo->prepare("SELECT id, decision_text, outcome, IF(created_at = '0000-00-00 00:00:00', CURRENT_TIMESTAMP, created_at) as created_at FROM decisions WHERE user_id=? ORDER BY id DESC LIMIT ? OFFSET ?");
        $stmt->execute([$uid, $limit, $off]);

        $r = $pdo->prepare("SELECT outcome, COUNT(*) c FROM decisions WHERE user_id=? GROUP BY outcome");
        $r->execute([$uid]);
        $breakdown = ['good'=>0,'bad'=>0,'neutral'=>0];
        foreach ($r->fetchAll() as $row) $breakdown[$row['outcome']] = (int)$row['c'];
        $rate = $count > 0 ? round(($breakdown['good']/$count)*100) : 0;

        jsonOk([
            'decisions'    => $stmt->fetchAll(),
            'total'        => $count,
            'pages'        => (int)ceil(max($count,1)/$limit),
            'current_page' => $page,
            'stats'        => array_merge($breakdown, ['rate'=>$rate]),
        ]);
    }

    $body = jsonBody();

    if (isset($_GET['delete'])) {
        $pdo->prepare('DELETE FROM decisions WHERE id=? AND user_id=?')
            ->execute([(int)($body['decision_id']??0), $uid]);
        jsonOk();
    }

    $text    = trim($body['decision_text'] ?? '');
    $outcome = $body['outcome'] ?? 'neutral';
    if (strlen($text) < 5) jsonError('Decision needs at least 5 characters.');
    if (!in_array($outcome, ['good','bad','neutral'])) $outcome = 'neutral';

    $ins = $pdo->prepare('INSERT INTO decisions (user_id, decision_text, outcome, created_at) VALUES (?,?,?,NOW())');
    $ins->execute([$uid, $text, $outcome]);
    jsonOk(['id' => (int)$pdo->lastInsertId()], 201);

} catch (Exception $e) {
    jsonError('Server error: ' . $e->getMessage(), 500);
}
