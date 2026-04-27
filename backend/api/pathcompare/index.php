<?php
/**
 * FlowStack — PathCompare
 * GET  → history
 * POST → { option_a, option_b } → AI-powered analysis
 */
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/db.php';
setApiHeaders();
$uid = requireAuth();

try {
    $pdo = getPDO();

    // Ensure columns exist for storing analysis
    try {
        $pdo->exec("ALTER TABLE path_compare ADD COLUMN score_a INT DEFAULT NULL AFTER selected_option");
        $pdo->exec("ALTER TABLE path_compare ADD COLUMN score_b INT DEFAULT NULL AFTER score_a");
        $pdo->exec("ALTER TABLE path_compare ADD COLUMN reasoning TEXT DEFAULT NULL AFTER score_b");
        $pdo->exec("ALTER TABLE path_compare ADD COLUMN pros_a TEXT DEFAULT NULL AFTER reasoning");
        $pdo->exec("ALTER TABLE path_compare ADD COLUMN cons_a TEXT DEFAULT NULL AFTER pros_a");
        $pdo->exec("ALTER TABLE path_compare ADD COLUMN pros_b TEXT DEFAULT NULL AFTER cons_a");
        $pdo->exec("ALTER TABLE path_compare ADD COLUMN cons_b TEXT DEFAULT NULL AFTER pros_b");
    } catch (Exception $e) { /* columns already exist */ }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Check if we're requesting a single comparison
        if (isset($_GET['id'])) {
            $s = $pdo->prepare('SELECT id, option_a, option_b, selected_option, score_a, score_b, reasoning, pros_a, cons_a, pros_b, cons_b, created_at FROM path_compare WHERE id=? AND user_id=?');
            $s->execute([(int)$_GET['id'], $uid]);
            $row = $s->fetch();
            if (!$row) jsonError('Not found', 404);
            jsonOk(['comparison' => $row]);
        }

        $s = $pdo->prepare('SELECT id, option_a, option_b, selected_option, score_a, score_b, reasoning, created_at FROM path_compare WHERE user_id=? ORDER BY created_at DESC LIMIT 20');
        $s->execute([$uid]);
        jsonOk(['history' => $s->fetchAll()]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

    $body = jsonBody();
    $a    = trim($body['option_a'] ?? '');
    $b    = trim($body['option_b'] ?? '');
    if (strlen($a) < 3 || strlen($b) < 3) jsonError('Both options need at least 3 characters.');

    // Get scores, reasoning etc from body (frontend sends AI analysis or client-side analysis)
    $selected  = $body['selected_option'] ?? 'A';
    $score_a   = (int)($body['score_a'] ?? 50);
    $score_b   = (int)($body['score_b'] ?? 50);
    $reasoning = trim($body['reasoning'] ?? '');
    $pros_a    = trim($body['pros_a'] ?? '');
    $cons_a    = trim($body['cons_a'] ?? '');
    $pros_b    = trim($body['pros_b'] ?? '');
    $cons_b    = trim($body['cons_b'] ?? '');

    $pdo->prepare('INSERT INTO path_compare (user_id, option_a, option_b, selected_option, score_a, score_b, reasoning, pros_a, cons_a, pros_b, cons_b) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$uid, $a, $b, $selected, $score_a, $score_b, $reasoning, $pros_a, $cons_a, $pros_b, $cons_b]);

    jsonOk(['id' => (int)$pdo->lastInsertId(), 'selected' => $selected], 201);

} catch (Exception $e) {
    jsonError('Server error: ' . $e->getMessage(), 500);
}
