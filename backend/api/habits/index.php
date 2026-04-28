<?php
/**
 * FlowStack Habits API
 * GET  → list habits
 * POST → add { name }
 * POST ?complete=1 → mark done { habit_id }
 * POST ?delete=1   → delete    { habit_id }
 */
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/db.php';
setApiHeaders();
$uid = requireAuth();

try {
    $pdo = getPDO();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare(
            'SELECT h.id, h.name, h.streak, h.last_completed, h.created_at,
                    (SELECT COUNT(*) FROM habit_logs hl
                     WHERE hl.habit_id=h.id
                       AND hl.completed_date>=DATE_SUB(CURDATE(),INTERVAL 6 DAY)) AS week_completions,
                    (SELECT COUNT(*) FROM habit_logs hl2
                     WHERE hl2.habit_id=h.id) AS total_completions
             FROM habits h WHERE h.user_id=? ORDER BY h.streak DESC, h.name'
        );
        $stmt->execute([$uid]);
        $habits = $stmt->fetchAll();
        $today  = date('Y-m-d');

        foreach ($habits as &$h) {
            $c = $pdo->prepare('SELECT 1 FROM habit_logs WHERE habit_id=? AND completed_date=?');
            $c->execute([$h['id'], $today]);
            $h['done_today'] = (bool)$c->fetchColumn();
            $h['pct_week']   = round(($h['week_completions']/7)*100);
            
            // Real consistency: (completed days / days since created) x 100
            $createdDate = new DateTime($h['created_at']);
            $now = new DateTime();
            $daysSinceCreated = max(1, $createdDate->diff($now)->days + 1);
            $h['consistency'] = round(($h['total_completions'] / $daysSinceCreated) * 100);
        }
        unset($h);
        jsonOk(['habits' => $habits]);
    }

    $body = jsonBody();

    // ── POST ?complete=1 ─────────────────────────────────────
    if (isset($_GET['complete'])) {
        $habit_id = (int)($body['habit_id'] ?? 0);
        if (!$habit_id) jsonError('habit_id required');

        $v = $pdo->prepare('SELECT id, streak, last_completed FROM habits WHERE id=? AND user_id=?');
        $v->execute([$habit_id, $uid]);
        $habit = $v->fetch();
        if (!$habit) jsonError('Habit not found', 404);

        $today     = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $ins = $pdo->prepare('INSERT IGNORE INTO habit_logs (habit_id, user_id, completed_date) VALUES (?,?,?)');
        $ins->execute([$habit_id, $uid, $today]);

        if ($ins->rowCount() > 0) {
            $streak = ($habit['last_completed'] === $yesterday) ? $habit['streak']+1 : 1;
            $pdo->prepare('UPDATE habits SET streak=?, last_completed=? WHERE id=?')
                ->execute([$streak, $today, $habit_id]);
        }
        $r = $pdo->prepare('SELECT streak FROM habits WHERE id=?');
        $r->execute([$habit_id]);
        jsonOk(['streak' => (int)$r->fetchColumn()]);
    }

    // ── POST ?delete=1 ───────────────────────────────────────
    if (isset($_GET['delete'])) {
        $habit_id = (int)($body['habit_id'] ?? 0);
        $pdo->prepare('DELETE FROM habits WHERE id=? AND user_id=?')->execute([$habit_id, $uid]);
        jsonOk();
    }

    // ── POST: add ────────────────────────────────────────────
    $name = trim($body['name'] ?? '');
    if (strlen($name) < 2 || strlen($name) > 200) jsonError('Habit name must be 2–200 characters.');
    $ins = $pdo->prepare('INSERT INTO habits (user_id, name) VALUES (?,?)');
    $ins->execute([$uid, $name]);
    jsonOk(['id' => (int)$pdo->lastInsertId(), 'name' => $name], 201);

} catch (Exception $e) {
    jsonError('Server error: ' . $e->getMessage(), 500);
}
