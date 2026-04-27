<?php
/**
 * FlowStack — Skills CRUD + History Tracking  
 * GET  → list + avg
 * POST → add { skill_name, proficiency_level }
 * POST ?delete=1 → delete { skill_id }
 * POST ?update=1 → update level { skill_id, proficiency_level }
 */
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/db.php';
setApiHeaders();
$uid = requireAuth();

try {
    $pdo = getPDO();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare('SELECT id, skill_name, proficiency_level, created_at FROM skills WHERE user_id=? ORDER BY proficiency_level DESC, skill_name');
        $stmt->execute([$uid]);
        $skills = $stmt->fetchAll();
        $avg = count($skills) > 0 ? round(array_sum(array_column($skills, 'proficiency_level')) / count($skills), 1) : 0;
        jsonOk(['skills' => $skills, 'avg' => $avg, 'count' => count($skills)]);
    }

    $body = jsonBody();

    if (isset($_GET['delete'])) {
        $pdo->prepare('DELETE FROM skills WHERE id=? AND user_id=?')->execute([(int) ($body['skill_id'] ?? 0), $uid]);
        jsonOk();
    }

    if (isset($_GET['update'])) {
        $skillId = (int) ($body['skill_id'] ?? 0);
        $level = max(1, min(10, (int) ($body['proficiency_level'] ?? 5)));
        if (!$skillId)
            jsonError('skill_id required');

        // Update skill level
        $pdo->prepare('UPDATE skills SET proficiency_level=?, updated_at=NOW() WHERE id=? AND user_id=?')
            ->execute([$level, $skillId, $uid]);

        // Record history
        $pdo->prepare('INSERT INTO skill_history (skill_id, user_id, proficiency_level) VALUES (?,?,?)')
            ->execute([$skillId, $uid, $level]);

        jsonOk(['level' => $level]);
    }

    $name = trim($body['skill_name'] ?? '');
    $level = max(1, min(10, (int) ($body['proficiency_level'] ?? 5)));
    if (strlen($name) < 2)
        jsonError('Skill name too short.');

    // Insert skill
    $ins = $pdo->prepare('INSERT INTO skills (user_id, skill_name, proficiency_level) VALUES (?,?,?)');
    $ins->execute([$uid, $name, $level]);
    $skillId = (int) $pdo->lastInsertId();

    // Record initial history
    $pdo->prepare('INSERT INTO skill_history (skill_id, user_id, proficiency_level) VALUES (?,?,?)')
        ->execute([$skillId, $uid, $level]);

    jsonOk(['id' => $skillId], 201);

} catch (Exception $e) {
    jsonError('Server error: ' . $e->getMessage(), 500);
}
