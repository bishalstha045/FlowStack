<?php
/**
 * FlowStack — NextMove
 * GET  → history
 * POST → { situation_text } → rule-based advice
 */
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/db.php';
setApiHeaders();
$uid = requireAuth();

try {
    $pdo = getPDO();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $s = $pdo->prepare('SELECT situation_text, generated_advice, cluster, created_at FROM next_move WHERE user_id=? ORDER BY created_at DESC LIMIT 10');
        $s->execute([$uid]);
        jsonOk(['history' => $s->fetchAll()]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

    $body = jsonBody();
    $text = trim($body['situation_text'] ?? '');
    $cluster = trim($body['cluster'] ?? 'general');
    if (strlen($text) < 10) jsonError('Describe your situation in at least 10 characters.');

    $clusters = [
        'career'       => ['keys'=>['job','work','career','interview','promotion','salary','resume','business','startup','office'],
            'advice'=>["Clarify your 90-day goal: what specific outcome do you want from this career move?","Research 3 people in your target role identify the skills gap and address it this week.","Update your LinkedIn and resume with measurable achievements (numbers matter).","Schedule 2 coffee chats with professionals in your target field.","Track your daily output visible productivity is your strongest negotiation tool."]],
        'study'        => ['keys'=>['study','learn','exam','university','course','degree','skill','class','assignment','book','read'],
            'advice'=>["Use the Pomodoro method: 25 min focus, 5 min break repeat 4x before a longer rest.","Write practice questions from memory active recall beats passive re-reading by 50%.","Build a weekly study schedule with specific topics per day, not just 'study more'.","Create a visual summary of your weakest topic teach it to yourself.","Track your streak in FlowStack HabitSync to maintain momentum."]],
        'money'        => ['keys'=>['money','finance','debt','save','invest','budget','income','spend','broke','loan','bank','rent'],
            'advice'=>["List every monthly expense categorize into needs, wants, and waste.","Set a 3-month savings goal with a specific number automate transfers on payday.","Identify one recurring expense you can cut this week without big impact.","Research one compound-growth investment suited to your timeline.","Log every purchase for 7 days awareness alone usually cuts spending 10–15%."]],
        'stress'       => ['keys'=>['stress','anxiety','overwhelmed','tired','burnout','mental','pressure','worry','panic','sleep'],
            'advice'=>["Reduce your task list to 3 critical priorities drop or defer the rest immediately.","Schedule 20 minutes of physical movement today this is your highest-ROI action.","Identify the #1 source of your stress write it down and list one actionable step.","Do a 5-minute breathing session: 4 counts in, hold 4, out 8.","Tell one person you trust exactly how you're feeling isolation multiplies stress."]],
        'relationship' => ['keys'=>['friend','family','partner','relationship','conflict','lonely','social','dating','people'],
            'advice'=>["Define what you need most from this relationship communicate it clearly.","Choose a specific time this week to have the difficult conversation you've been avoiding.","Focus on one thing you can control in this dynamic let go of the rest for now.","Check your own emotional state before addressing the issue with the other person.","Shared activity beats shared words plan one joint experience to reconnect."]],
        'habit'        => ['keys'=>['habit','routine','track','streak','consistent','discipline','motivation','keep','start','quit'],
            'advice'=>["The real problem is usually friction, not motivation. Make it ridiculously easy: 2 minutes, no exceptions.","Use habit stacking attach the new habit to one you already do (After I brush my teeth, I will…).","Design your environment make the habit obvious and easy (gym bag by the door, book on pillow).","Track it visually: a simple checkbox calendar on your wall. Don't break the chain.","Plan for lapses: missing once is human, missing twice is a new habit. Have a minimum viable version for hard days."]],
    ];

    $lower    = strtolower($text);
    $matched  = 'career';
    $maxScore = 0;
    foreach ($clusters as $key => $cluster) {
        $score = 0;
        foreach ($cluster['keys'] as $word) { if (str_contains($lower, $word)) $score++; }
        if ($score > $maxScore) { $maxScore = $score; $matched = $key; }
    }

    $advice     = $clusters[$matched]['advice'];
    
    // Build numbered advice text
    $adviceLines = [];
    foreach ($advice as $i => $step) {
        $adviceLines[] = ($i + 1) . ". " . $step;
    }
    $adviceText = implode("\n", $adviceLines);

    $pdo->prepare('INSERT INTO next_move (user_id, situation_text, generated_advice, cluster) VALUES (?,?,?,?)')
        ->execute([$uid, $text, $adviceText, $matched]);

    jsonOk(['cluster'=>$matched,'advice'=>$advice,'advice_text'=>$adviceText], 201);

} catch (Exception $e) {
    jsonError('Server error: ' . $e->getMessage(), 500);
}
