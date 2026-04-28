<?php
/**
 * FlowStack Dashboard Stats + XP Level System
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

    // ── XP Level System ──────────────────────────────────────
    // Each habit completed: +10 XP
    $totalHabitCompletions = (int) $q('SELECT COUNT(*) FROM habit_logs WHERE user_id=?',[$uid])->fetchColumn();
    $habitXP = $totalHabitCompletions * 10;

    // Each focus session (20+ min): +15 XP
    $focusSessions20 = (int) $q("SELECT COUNT(*) FROM focus_sessions WHERE user_id=? AND duration_minutes >= 20",[$uid])->fetchColumn();
    $focusXP = $focusSessions20 * 15;

    // Each decision logged: +5 XP
    $decisionXP = $totalDec * 5;

    // Each skill added: +20 XP
    $skillXP = $skillCount * 20;

    // 7-day streak bonus: +50 XP per habit with 7+ streak
    $streakBonusHabits = (int) $q('SELECT COUNT(*) FROM habits WHERE user_id=? AND streak >= 7',[$uid])->fetchColumn();
    $streakXP = $streakBonusHabits * 50;

    $totalXP = $habitXP + $focusXP + $decisionXP + $skillXP + $streakXP;

    // Today's XP
    $todayHabitXP = (int) $q('SELECT COUNT(*) FROM habit_logs WHERE user_id=? AND completed_date=CURDATE()',[$uid])->fetchColumn() * 10;
    $todayFocusXP = (int) $q("SELECT COUNT(*) FROM focus_sessions WHERE user_id=? AND session_date=CURDATE() AND duration_minutes >= 20",[$uid])->fetchColumn() * 15;
    $todayDecXP = (int) $q("SELECT COUNT(*) FROM decisions WHERE user_id=? AND DATE(created_at)=CURDATE()",[$uid])->fetchColumn() * 5;
    $todayXP = $todayHabitXP + $todayFocusXP + $todayDecXP;

    // Level tiers
    if ($totalXP >= 1000) { $level = 'Elite'; $nextThreshold = 9999; $prevThreshold = 1000; }
    elseif ($totalXP >= 600) { $level = 'High Performer'; $nextThreshold = 1000; $prevThreshold = 600; }
    elseif ($totalXP >= 300) { $level = 'Consistent Grower'; $nextThreshold = 600; $prevThreshold = 300; }
    elseif ($totalXP >= 100) { $level = 'Building Momentum'; $nextThreshold = 300; $prevThreshold = 100; }
    else { $level = 'Starting Level'; $nextThreshold = 100; $prevThreshold = 0; }

    $xpProgress = $nextThreshold > $prevThreshold
        ? round(($totalXP - $prevThreshold) / ($nextThreshold - $prevThreshold) * 100)
        : 100;

    jsonOk([
        'active_habits'   => $activeHabits,
        'focus_hours'     => round($focusMins/60, 1),
        'decision_rate'   => $successRate,
        'total_decisions' => $totalDec,
        'best_streak'     => $bestStreak,
        'skill_count'     => $skillCount,
        'today_focus'     => $todayFocus,
        'habits'          => $habits,
        // XP/Level
        'xp'              => $totalXP,
        'today_xp'        => $todayXP,
        'level'           => $level,
        'xp_progress'     => min(100, $xpProgress),
        'next_threshold'  => $nextThreshold,
    ]);
} catch (Exception $e) {
    jsonError('Server error: ' . $e->getMessage(), 500);
}
