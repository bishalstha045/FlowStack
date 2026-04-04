<?php
/**
 * FlowStack — PathCompare
 * GET  → history
 * POST → { option_a, option_b } → score + winner
 */
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/db.php';
setApiHeaders();
$uid = requireAuth();

try {
    $pdo = getPDO();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $s = $pdo->prepare('SELECT option_a, option_b, selected_option, created_at FROM path_compare WHERE user_id=? ORDER BY created_at DESC LIMIT 20');
        $s->execute([$uid]);
        jsonOk(['history' => $s->fetchAll()]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

    $body = jsonBody();
    $a    = trim($body['option_a'] ?? '');
    $b    = trim($body['option_b'] ?? '');
    if (strlen($a) < 3 || strlen($b) < 3) jsonError('Both options need at least 3 characters.');

    $pos = ['earn','grow','learn','build','create','lead','freedom','passion','impact','future','potential','skill','gain','opportunity','success'];
    $neg = ['risk','debt','stress','hard','difficult','uncertain','sacrifice','pressure','fear','doubt','problem','loss','struggle'];

    function scoreText(string $t, array $pos, array $neg): array {
        $l = strtolower($t); $p = $n = 0;
        foreach ($pos as $k) { if (str_contains($l,$k)) $p++; }
        foreach ($neg as $k) { if (str_contains($l,$k)) $n++; }
        return ['positive'=>$p,'negative'=>$n,'net'=>$p-$n];
    }

    $sa       = scoreText($a, $pos, $neg);
    $sb       = scoreText($b, $pos, $neg);
    $selected = $sa['net'] >= $sb['net'] ? 'A' : 'B';
    $reason   = "Option {$selected} scores higher on growth and opportunity indicators (net: " . ($selected==='A'?$sa['net']:$sb['net']) . ").";

    $pdo->prepare('INSERT INTO path_compare (user_id, option_a, option_b, selected_option) VALUES (?,?,?,?)')
        ->execute([$uid, $a, $b, $selected]);

    jsonOk(['selected'=>$selected,'reasoning'=>$reason,'score_a'=>$sa,'score_b'=>$sb], 201);

} catch (Exception $e) {
    jsonError('Server error: ' . $e->getMessage(), 500);
}
