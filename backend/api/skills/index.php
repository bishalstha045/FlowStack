<?php
/**
 * FlowStack — Skills CRUD
 * GET  → list + avg
 * POST → add { skill_name, proficiency_level }
 * POST ?delete=1 → delete { skill_id }
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
        $avg    = count($skills) > 0 ? round(array_sum(array_column($skills,'proficiency_level'))/count($skills),1) : 0;
        jsonOk(['skills'=>$skills,'avg'=>$avg,'count'=>count($skills)]);
    }

    $body = jsonBody();

    if (isset($_GET['delete'])) {
        $pdo->prepare('DELETE FROM skills WHERE id=? AND user_id=?')->execute([(int)($body['skill_id']??0),$uid]);
        jsonOk();
    }

    $name  = trim($body['skill_name'] ?? '');
    $level = max(1, min(10, (int)($body['proficiency_level'] ?? 5)));
    if (strlen($name) < 2) jsonError('Skill name too short.');

    $ins = $pdo->prepare('INSERT INTO skills (user_id, skill_name, proficiency_level) VALUES (?,?,?)');
    $ins->execute([$uid, $name, $level]);
    jsonOk(['id' => (int)$pdo->lastInsertId()], 201);

} catch (Exception $e) {
    jsonError('Server error: ' . $e->getMessage(), 500);
}
