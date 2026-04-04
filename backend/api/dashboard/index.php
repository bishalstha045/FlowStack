<?php
/**
 * FlowStack — Dashboard Stats
 * GET /backend/api/dashboard/index.php
 */
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/db.php';
setApiHeaders();
$uid = requireAuth();

try {
    $pdo = getPDO();

    $q = fn($sql, $p=[]) => (function() use($pdo,$sql,$p){ $s=$pdo->prepare($sql); $s->execute($p); return $s; })();

    $activeHabits = (int) $q('SELECT COUNT(*) FROM habits WHERE user_id=?',[$uid])->fetchColumn();
    $focusMins    = (int) $q("SELECT COALESCE(SUM(duration_minutes),0) FROM focus_sessions WHERE user_id=? AND session_date>=DATE_SUB(CURDATE(),INTERVAL 6 DAY)",[$uid])->fetchColumn();
    $totalDec     = (int) $q('SELECT COUNT(*) FROM decisions WHERE user_id=?',[$uid])->fetchColumn();
    $goodDec      = (int) $q("SELECT COUNT(*) FROM decisions WHERE user_id=? AND outcome='good'",[$uid])->fetchColumn();
    $bestStreak   = (int) $q('SELECT COALESCE(MAX(streak),0) FROM habits WHERE user_id=?',[$uid])->fetchColumn();
    $skillCount   = (int) $q('SELECT COUNT(*) FROM skills WHERE user_id=?',[$uid])->fetchColumn();
    $todayFocus   = (int) $q("SELECT COALESCE(SUM(duration_minutes),0) FROM focus_sessions WHERE user_id=? AND session_date=CURDATE()",[$uid])->fetchColumn();

    $successRate = $totalDec > 0 ? round(($goodDec / $totalDec) * 100) : 0;

    $habits = $q('SELECT id, name, streak, last_completed FROM habits WHERE user_id=? ORDER BY streak DESC LIMIT 5',[$uid])->fetchAll();

    $insights = [];
    foreach ($habits as $h) {
        if ((int)$h['streak'] >= 7)
            $insights[] = "🔥 {$h['streak']} day streak on \"{$h['name']}\" keep it going!";
    }
    if ($focusMins >= 120)
        $insights[] = "⏱️ You've logged " . round($focusMins/60,1) . " hours of focus this week.";
    if ($successRate >= 70 && $totalDec > 0)
        $insights[] = "✅ Your decision win rate is {$successRate}% excellent judgment.";
    if (empty($insights))
        $insights[] = "👋 Start by adding your first habit to build momentum.";

    jsonOk([
        'active_habits'   => $activeHabits,
        'focus_hours'     => round($focusMins/60, 1),
        'decision_rate'   => $successRate,
        'total_decisions' => $totalDec,
        'best_streak'     => $bestStreak,
        'skill_count'     => $skillCount,
        'today_focus'     => $todayFocus,
        'habits'          => $habits,
        'insights'        => $insights,
    ]);
} catch (Exception $e) {
    jsonError('Server error: ' . $e->getMessage(), 500);
}
