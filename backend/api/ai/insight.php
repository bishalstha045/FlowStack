<?php
/**
 * FlowStack — AI Insight API  (powered by Google Gemini)
 * POST /backend/api/ai/insight.php
 *
 * Sends real user data to Gemini and returns a precise,
 * module-specific insight. Falls back to rule-based text
 * ONLY when GEMINI_API_KEY is empty.
 *
 * ── How Gemini API works ─────────────────────────────────────────
 *  URL  : https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={KEY}
 *  Body : { "systemInstruction": {...}, "contents": [...], "generationConfig": {...} }
 *  Reply: { "candidates": [{ "content": { "parts": [{ "text": "answer" }] } }] }
 * ────────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/secrets.php';
setApiHeaders();
$uid = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    jsonError('Method not allowed', 405);

$body   = jsonBody();
$module = trim($body['module'] ?? '');
$data   = $body['data'] ?? [];

if (!$module) jsonError('Module is required');

$pdo = getPDO();

// ── Ensure cache table exists ─────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS ai_insights_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    module VARCHAR(50) NOT NULL,
    insight_text TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY(user_id, module)
)");

// ── Cache check — skip when ?force=1 ─────────────────────────────────────────
if (empty($_GET['force'])) {
    $stmt = $pdo->prepare(
        "SELECT insight_text FROM ai_insights_cache
         WHERE user_id = ? AND module = ?
           AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
         ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->execute([$uid, $module]);
    $cached = $stmt->fetchColumn();
    if ($cached) {
        jsonOk(['insight' => $cached, 'cached' => true, 'source' => 'cache']);
    }
}

// ── Token budget ──────────────────────────────────────────────────────────────
$maxTokens = in_array($module, ['pathcompare', 'nextmove_advice', 'decisions']) ? 900 : 400;

// ══════════════════════════════════════════════════════════════════════════════
//  SYSTEM PROMPTS — define AI personality per module
// ══════════════════════════════════════════════════════════════════════════════

// Base personality (all modules)
$system =
    "You are an intelligent decision-making assistant integrated into FlowStack, a personal productivity web app.\n"
  . "Your job is to:\n"
  . "- Analyze user input and data deeply\n"
  . "- Give practical, realistic, and actionable advice\n"
  . "- Avoid generic answers — always reference the actual data or situation provided\n"
  . "- Be concise but insightful\n"
  . "- Structure your response clearly using the format specified in the user message\n\n"
  . "Tone: Smart but simple. Slightly analytical. No fluff, no unnecessary explanation.\n"
  . "Focus on real-world usefulness. Never add greetings, intros, or closings.";

// Module overrides
if ($module === 'pathcompare') {
    $system =
        "You are an expert decision comparison system inside FlowStack.\n"
      . "Your job: compare options objectively, score them numerically, and recommend the best choice.\n"
      . "Be analytical, fair, and specific. Base ALL analysis ONLY on the options provided.\n"
      . "Never add greetings, intros, closings, or disclaimers. Return only what is asked.";
}
if ($module === 'nextmove_advice') {
    $system =
        "You are a strategic planning assistant and empathetic coach inside FlowStack.\n"
      . "The user has described a real situation they are struggling with.\n"
      . "Your job: understand the situation, break it into logical next steps, suggest the most effective path forward.\n"
      . "Be specific to their exact words — never give generic life advice.\n"
      . "Tone: Direct, supportive, and practical. No fluff. No greetings or closings.";
}
if ($module === 'decisions') {
    $system =
        "You are a decision analysis expert inside FlowStack.\n"
      . "Your job: analyze the user's decision log data, identify behavioral patterns, give sharp actionable feedback.\n"
      . "Reference actual numbers. Never be generic.\n"
      . "Tone: Smart, analytical, no-nonsense.";
}

// ══════════════════════════════════════════════════════════════════════════════
//  USER PROMPTS — module-specific, data-driven, structured format
// ══════════════════════════════════════════════════════════════════════════════

switch ($module) {

    // ── Habits ────────────────────────────────────────────────────────────────
    case 'habits':
        $prompt =
            "Here is the user's habit tracking data:\n"
          . json_encode($data, JSON_PRETTY_PRINT) . "\n\n"
          . "Respond using this structure:\n"
          . "1. Summary: One sentence identifying the most important pattern in the actual numbers.\n"
          . "2. Key Insight: One specific observation referencing real data (streak, %, count).\n"
          . "3. Recommended Action: One concrete thing they can do TODAY — name the exact habit.\n"
          . "4. Risk: One risk if they continue the current pattern.\n"
          . "Keep each point to 1 sentence. No preamble.";
        break;

    // ── Focus ─────────────────────────────────────────────────────────────────
    case 'focus':
        $prompt =
            "Here is the user's focus / deep-work session data:\n"
          . json_encode($data, JSON_PRETTY_PRINT) . "\n\n"
          . "Respond using this structure:\n"
          . "1. Summary: One sentence about their focus pattern today vs average.\n"
          . "2. Key Insight: One observation about trend or weekly performance (use actual values).\n"
          . "3. Recommended Action: One concrete tip for their NEXT session.\n"
          . "4. Risk: What happens if this pattern continues?\n"
          . "Keep each point to 1 sentence. No preamble.";
        break;

    // ── Decisions ─────────────────────────────────────────────────────────────
    case 'decisions':
        $total    = (int)($data['total']    ?? 0);
        $good     = (int)($data['good']     ?? 0);
        $bad      = (int)($data['bad']      ?? 0);
        $neutral  = (int)($data['neutral']  ?? 0);
        $winRate  = (int)($data['winRate']  ?? 0);
        $trend    = $data['trend']          ?? 'stable';
        $recent   = $data['recentDecisions'] ?? [];
        $recentStr = is_array($recent) ? implode('; ', array_filter($recent)) : (string)$recent;

        $prompt =
            "User Decision Log Analysis:\n"
          . "- Total decisions logged: {$total}\n"
          . "- Good: {$good} | Bad: {$bad} | Neutral: {$neutral}\n"
          . "- Win rate: {$winRate}%\n"
          . "- Recent trend: {$trend}\n"
          . ($recentStr ? "- Recent decisions: {$recentStr}\n" : "")
          . "\nRespond using this EXACT format:\n"
          . "1. Decision Summary: One sentence on what the data shows about their decision-making.\n"
          . "2. Pros: What they are doing well (based on the numbers).\n"
          . "3. Cons: What is dragging down their win rate or causing poor outcomes.\n"
          . "4. Long-term Impact: What happens if this pattern continues for 6 months?\n"
          . "5. Final Suggestion: One specific concrete action to improve this week.\n"
          . "Keep each section to 1-2 sentences. No preamble.";
        break;

    // ── Skills ────────────────────────────────────────────────────────────────
    case 'skills':
        $prompt =
            "Here is the user's skill profile data:\n"
          . json_encode($data, JSON_PRETTY_PRINT) . "\n\n"
          . "Respond using this structure:\n"
          . "1. Summary: One sentence identifying their strongest vs weakest skill (use real names and levels).\n"
          . "2. Key Insight: One observation about skill balance or growth rate.\n"
          . "3. Recommended Action: One specific daily practice THIS WEEK to address the biggest gap.\n"
          . "4. Risk: What opportunity are they missing by not addressing the gap?\n"
          . "Keep each point to 1 sentence. No preamble.";
        break;

    // ── Dashboard ─────────────────────────────────────────────────────────────
    case 'dashboard':
        $prompt =
            "Here is the user's FlowStack dashboard summary:\n"
          . json_encode($data, JSON_PRETTY_PRINT) . "\n\n"
          . "Respond using this structure:\n"
          . "1. Summary: One sentence on their overall FlowScore and what it reflects.\n"
          . "2. Key Insight: The single weakest area dragging the score down (use actual numbers).\n"
          . "3. Recommended Action: One specific thing to do TODAY to raise the score.\n"
          . "4. Risk: What happens to momentum if they ignore this area?\n"
          . "Keep each point to 1 sentence. No preamble.";
        break;

    // ── PathCompare ───────────────────────────────────────────────────────────
    case 'pathcompare':
        $optA = trim($data['option_a'] ?? '');
        $optB = trim($data['option_b'] ?? '');
        if (!$optA || !$optB) jsonError('Both option_a and option_b are required');

        $prompt =
            "Compare these two options:\n"
          . "Option A: {$optA}\n"
          . "Option B: {$optB}\n\n"
          . "Evaluate based on: Cognitive value, long-term ROI, skill building, and productivity.\n\n"
          . "Return ONLY a valid JSON object — no markdown, no text outside JSON.\n"
          . "Use this exact structure:\n"
          . "{\n"
          . "  \"score_a\": <integer 0-100>,\n"
          . "  \"score_b\": <integer 0-100>,\n"
          . "  \"selected\": \"A\" or \"B\",\n"
          . "  \"pros_a\": \"• strength1\\n• strength2\\n• strength3\",\n"
          . "  \"cons_a\": \"• weakness1\\n• weakness2\",\n"
          . "  \"pros_b\": \"• strength1\\n• strength2\\n• strength3\",\n"
          . "  \"cons_b\": \"• weakness1\\n• weakness2\",\n"
          . "  \"reasoning\": \"Best Choice: Why Option X wins.\"\n"
          . "}\n"
          . "CRITICAL RULES:\n"
          . "- HEAVILY PENALIZE instant-gratification, video games (e.g. Freefire), and passive scrolling.\n"
          . "- HIGHLY REWARD cognitive growth, strategy games (e.g. Chess), coding, and learning.\n"
          . "- Score honestly based on real-world value.";
        break;

    // ── Skill Roadmap (Individual Skill Development) ──────────────────────────
    case 'skill_roadmap':
        $skillName = trim($data['skill_name'] ?? '');
        $level = (int)($data['level'] ?? 5);
        if (!$skillName) jsonError('Skill name is required');
        
        $prompt = 
            "The user wants to level up their skill: '{$skillName}'.\n"
          . "Their current self-rated proficiency is {$level}/10.\n\n"
          . "Generate a highly actionable, overpowered 3-step development roadmap for them to reach the next level.\n"
          . "Respond using this EXACT format (keep it brief and intense):\n"
          . "1. The Gap: One brutal truth about what separates a level {$level} from a master in {$skillName}.\n"
          . "2. Step 1 (Theory): What exact concept they need to study next.\n"
          . "3. Step 2 (Practice): A specific, difficult project or drill they must do to build muscle memory.\n"
          . "4. Step 3 (Validation): How they can prove to themselves they have leveled up.\n"
          . "Tone: Expert coach, no fluff, extremely tactical.";
        break;

    // ── NextMove — auto recommendations from live data ────────────────────────
    case 'nextmove':
        $incomplete   = $data['incompleteHabits'] ?? [];
        $habitList    = is_array($incomplete) ? implode(', ', $incomplete) : (string)$incomplete;
        $todayMin     = (int)($data['todayFocusMin'] ?? ($data['todayFocus'] ?? 0));
        $weekHrs      = $data['weekFocusHrs']  ?? ($data['weekFocus'] ?? 0);
        $streak       = (int)($data['bestStreak'] ?? 0);
        $activeHabits = (int)($data['activeHabits'] ?? 0);
        $winRate      = (int)($data['decisionWinRate'] ?? 0);
        $skillAvg     = $data['skillAvg'] ?? 5;

        $prompt =
            "Current Situation (user's live productivity data):\n"
          . "- Incomplete habits today: " . ($habitList ?: 'none') . "\n"
          . "- Focus minutes today: {$todayMin} min\n"
          . "- Focus hours this week: {$weekHrs} hrs\n"
          . "- Best habit streak: {$streak} days\n"
          . "- Active habits: {$activeHabits}\n"
          . "- Decision win rate: {$winRate}%\n"
          . "- Average skill level: {$skillAvg}/10\n\n"
          . "Respond using this EXACT format:\n"
          . "1. Situation Summary: One sentence about where they are right now (use real numbers).\n"
          . "2. Immediate Next Step: The single most impactful thing to do in the next 30 minutes.\n"
          . "3. Step-by-Step Plan:\n"
          . "   Step A: Action for today\n"
          . "   Step B: Action for this week\n"
          . "   Step C: Action for this month\n"
          . "4. Mistakes to Avoid: Two specific mistakes based on their current data.\n"
          . "Reference actual values in every section. No preamble.";
        break;

    // ── NextMove Advice — user describes situation ────────────────────────────
    case 'nextmove_advice':
        $situation = trim($data['situation'] ?? '');
        if (!$situation) jsonError('situation is required for nextmove_advice');

        $ctx = $data['context'] ?? [];
        $ctxLines = [];
        foreach ($ctx as $k => $v) {
            if (is_array($v)) $v = implode(', ', $v) ?: 'none';
            $ctxLines[] = "  - {$k}: {$v}";
        }
        $ctxStr = implode("\n", $ctxLines);

        $prompt =
            "Current Situation:\n"
          . "\"{$situation}\"\n\n"
          . ($ctxStr ? "User's productivity context:\n{$ctxStr}\n\n" : "")
          . "Respond using this EXACT format:\n"
          . "1. Situation Summary: One sentence capturing what they are really dealing with.\n"
          . "2. Immediate Next Step: The single most effective action they can take TODAY — be specific.\n"
          . "3. Step-by-Step Plan:\n"
          . "   Step A: Something they do today\n"
          . "   Step B: Something they do this week\n"
          . "   Step C: Something they build over the next 30 days\n"
          . "   Step D: Long-term habit to prevent this situation from recurring\n"
          . "4. Mistakes to Avoid: Two specific mistakes people make in this exact situation.\n\n"
          . "CRITICAL RULES:\n"
          . "- Every point must directly reference words or themes from their situation.\n"
          . "- Do NOT write generic steps like 'set goals', 'stay motivated', or 'be consistent'.\n"
          . "- Be honest — say hard truths clearly but kindly.\n"
          . "- No preamble, no closing remarks.";
        break;

    // ── Default ───────────────────────────────────────────────────────────────
    default:
        $prompt =
            "Here is the user's data for module '{$module}':\n"
          . json_encode($data, JSON_PRETTY_PRINT) . "\n\n"
          . "Respond using this structure:\n"
          . "1. Summary: What the data shows in one sentence.\n"
          . "2. Key Insight: One specific observation from the actual values.\n"
          . "3. Recommended Action: One concrete step to take this week.\n"
          . "4. Risk: What happens if they do nothing?\n"
          . "No preamble.";
        break;
}

// ══════════════════════════════════════════════════════════════════════════════
//  CALL GEMINI API
// ══════════════════════════════════════════════════════════════════════════════

$apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
$model  = defined('GEMINI_MODEL')   ? GEMINI_MODEL  : 'gemini-2.0-flash-lite';

if ($apiKey) {

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $payload = json_encode([
        'systemInstruction' => [
            'parts' => [['text' => $system]]
        ],
        'contents' => [
            [
                'role'  => 'user',
                'parts' => [['text' => $prompt]]
            ]
        ],
        'generationConfig' => [
            'maxOutputTokens' => $maxTokens,
            'temperature'     => 0.7,
        ]
    ]);

    set_time_limit(25);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    // Success
    if ($httpCode === 200) {
        $result  = json_decode($raw, true);
        $insight = trim($result['candidates'][0]['content']['parts'][0]['text'] ?? '');
        if ($insight) {
            $pdo->prepare("INSERT INTO ai_insights_cache (user_id, module, insight_text) VALUES (?, ?, ?)")
                ->execute([$uid, $module, $insight]);
            jsonOk(['insight' => $insight, 'cached' => false, 'source' => 'gemini']);
        }
    }

    // Rate limited — return immediately so frontend can show countdown + retry
    if ($httpCode === 429) {
        error_log("FlowStack/Gemini: 429 rate limit for module '{$module}'");
        jsonError('rate_limited', 429);
    }

    // Other error
    error_log("FlowStack/Gemini: HTTP {$httpCode} for '{$module}'. " . ($curlErr ? "cURL: {$curlErr}. " : "") . substr($raw, 0, 300));
}

// ══════════════════════════════════════════════════════════════════════════════
//  RULE-BASED FALLBACK — only when GEMINI_API_KEY is empty
// ══════════════════════════════════════════════════════════════════════════════

$insight = generateFallback($module, $data);

$pdo->prepare("INSERT INTO ai_insights_cache (user_id, module, insight_text) VALUES (?, ?, ?)")
    ->execute([$uid, $module, $insight]);

jsonOk(['insight' => $insight, 'cached' => false, 'source' => 'fallback']);


// ── Fallback generator ────────────────────────────────────────────────────────
function generateFallback(string $module, array $data): string
{
    switch ($module) {

        case 'habits': {
            $done   = (int)($data['doneToday']       ?? 0);
            $total  = (int)($data['total']            ?? 0);
            $pct    = (int)($data['avgConsistency']   ?? 0);
            $streak = (int)($data['maxStreak']        ?? 0);
            if ($total === 0) return "1. Summary: No habits tracked yet.\n2. Key Insight: Starting with one small habit builds the logging habit itself.\n3. Recommended Action: Add your first habit — even 'drink 8 glasses of water' counts.\n4. Risk: Without tracking, you can't identify what's holding you back.";
            if ($done === $total) return "1. Summary: All {$total} habits completed today — {$pct}% overall consistency with a {$streak}-day streak.\n2. Key Insight: Perfect execution today.\n3. Recommended Action: Pre-plan tomorrow's habits tonight while motivation is high.\n4. Risk: Complacency after good days is the most common streak-killer.";
            $left = $total - $done;
            return "1. Summary: {$done}/{$total} habits done today ({$pct}% consistency).\n2. Key Insight: {$left} habit(s) still remaining — your streak depends on completing them.\n3. Recommended Action: Start with whichever remaining habit takes under 2 minutes.\n4. Risk: Leaving habits incomplete today will reset your momentum streak.";
        }

        case 'focus': {
            $today = (int)($data['todayMins'] ?? 0);
            $avg   = (int)($data['avgMins']   ?? 0);
            $week  = $data['weekHrs']          ?? 0;
            $trend = $data['trend']            ?? 'stable';
            if ($today === 0) return "1. Summary: 0 focus minutes logged today.\n2. Key Insight: Breaking inertia requires just one 25-minute session.\n3. Recommended Action: Start a Pomodoro right now — set a timer for 25 minutes.\n4. Risk: Days without focus sessions compound into weeks of low output.";
            $vs = $today > $avg ? "above your {$avg}-min average" : "below your {$avg}-min average";
            return "1. Summary: {$today} min focused today — {$vs}.\n2. Key Insight: Weekly total is {$week} hrs with a {$trend} trend.\n3. Recommended Action: Schedule your next focus block before you close this tab.\n4. Risk: Inconsistent focus sessions prevent deep work from becoming a habit.";
        }

        case 'decisions': {
            $total   = (int)($data['total']   ?? 0);
            $winRate = (int)($data['winRate'] ?? 0);
            $bad     = (int)($data['bad']     ?? 0);
            if ($total < 2) return "1. Decision Summary: Not enough data yet.\n2. Pros: You started logging.\n3. Cons: Need at least 2 decisions to see patterns.\n4. Long-term Impact: Without logging, decision mistakes repeat invisibly.\n5. Final Suggestion: Log your next 3 decisions this week, however small.";
            return "1. Decision Summary: {$winRate}% win rate across {$total} decisions.\n2. Pros: " . ($winRate >= 60 ? "Solid win rate — your judgment is above average." : "You're actively logging and building self-awareness.") . "\n3. Cons: " . ($bad > 0 ? "{$bad} poor outcome(s) need review." : "No major bad outcomes logged.") . "\n4. Long-term Impact: " . ($winRate < 50 ? "Below 50% win rate compounds into poor outcomes." : "Strong judgment compounds into better opportunities.") . "\n5. Final Suggestion: " . ($bad > 0 ? "Review your {$bad} poor outcome(s) for a common root cause." : "Keep logging — consistency reveals patterns invisible in the moment.");
        }

        case 'skills': {
            $avg    = $data['avg']    ?? 0;
            $total  = (int)($data['total']  ?? 0);
            $expert = (int)($data['expert'] ?? 0);
            if ($total === 0) return "1. Summary: No skills tracked yet.\n2. Key Insight: Tracking skills makes gaps visible and progress measurable.\n3. Recommended Action: Add your first skill and rate your current level honestly.\n4. Risk: Without tracking, skill development stays vague and inconsistent.";
            return "1. Summary: {$total} skills tracked at {$avg}/10 average ({$expert} expert-level).\n2. Key Insight: " . ($avg < 6 ? "Most skills are below intermediate — there's significant growth potential." : "Strong skill base — focus on pushing top skills toward mastery.") . "\n3. Recommended Action: " . ($avg < 6 ? "Spend 20 min/day on the skill closest to level 7." : "Push your strongest skill toward 9-10 — that's where unique value is created.") . "\n4. Risk: Spreading effort across too many skills prevents mastery in any.";
        }

        case 'dashboard': {
            $score  = (int)($data['flowScore'] ?? 0);
            $stats  = $data['dashStats'] ?? [];
            $habits = (int)($stats['active_habits'] ?? 0);
            $hrs    = $stats['focus_hours'] ?? 0;
            return "1. Summary: FlowScore {$score}/100 — {$habits} active habits, {$hrs} focus hours this week.\n2. Key Insight: " . ($score < 50 ? "Score below 50 — habits and focus are the primary drag." : "Good momentum — one module is pulling the score down.") . "\n3. Recommended Action: " . ($score < 50 ? "Complete all habits today — it's your highest-leverage action." : "Target your lowest-scoring module specifically.") . "\n4. Risk: Ignoring the weakest module means overall score plateaus.";
        }

        case 'pathcompare': {
            return json_encode(['_error' => 'no_api_key']);
        }

        case 'skill_roadmap': {
            $skill = $data['skill_name'] ?? 'this skill';
            return "1. The Gap: To master {$skill}, you need consistent deliberate practice.\n2. Step 1 (Theory): Identify the core fundamentals you haven't mastered yet.\n3. Step 2 (Practice): Spend 20 minutes daily applying those fundamentals.\n4. Step 3 (Validation): Teach someone else what you've learned.";
        }

        case 'nextmove': {
            $incomplete = $data['incompleteHabits'] ?? [];
            $todayMin   = (int)($data['todayFocusMin'] ?? 0);
            $lines      = [];
            $lines[] = "1. Situation Summary: " . (empty($incomplete) ? "All habits done today." : count($incomplete) . " habit(s) still pending.");
            $lines[] = "2. Immediate Next Step: " . (!empty($incomplete) ? "Complete '{$incomplete[0]}' right now." : ($todayMin < 25 ? "Start a 25-minute focus session." : "Review tomorrow's priorities."));
            $lines[] = "3. Step-by-Step Plan:\n   Step A: Complete pending habits\n   Step B: Log one focus session today\n   Step C: Review your week and set next week's priority habit";
            $lines[] = "4. Mistakes to Avoid: Skipping habits 'just once' (breaks streaks), and planning without acting.";
            return implode("\n", $lines);
        }

        case 'nextmove_advice': {
            $situation = $data['situation'] ?? 'your situation';
            return "1. Situation Summary: You're dealing with: \"{$situation}\".\n"
                 . "2. Immediate Next Step: Write the core problem in one sentence right now to gain clarity.\n"
                 . "3. Step-by-Step Plan:\n   Step A: Define the problem clearly today\n   Step B: List 3 possible actions this week\n   Step C: Pick one action and execute it within 30 days\n   Step D: Build a weekly review habit to track progress\n"
                 . "4. Mistakes to Avoid: Overthinking without acting, and trying to solve everything at once.";
        }

        default:
            return "1. Summary: AI key not configured.\n2. Key Insight: Add your Gemini API key to backend/config/secrets.php.\n3. Recommended Action: Get a free key at aistudio.google.com.\n4. Risk: Without AI, insights are rule-based only.";
    }
}
