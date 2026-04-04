/**
 * FlowStack Intelligence Engine v1
 * =================================
 * All analytical logic lives here. Backend provides raw data only.
 * This module runs entirely in the browser — no extra server calls.
 *
 * Exports: window.Intel = { habits, focus, decisions, skills, synthesize, render }
 */
;(function (window) {
'use strict';

const Intel = {};

// ─────────────────────────────────────────────────────────────────────────────
// HABIT INTELLIGENCE
// Input : habits[] from /api/habits/index.php
// Output: { score, insights[], metrics{} }
// ─────────────────────────────────────────────────────────────────────────────
Intel.habits = function (habits) {
  const result = { score: 0, insights: [], metrics: {}, perHabit: [] };
  if (!habits || !habits.length) return result;

  const n = habits.length;
  const avgConsistency = habits.reduce((s, h) => s + (+h.pct_week || 0), 0) / n;
  const avgStreak      = habits.reduce((s, h) => s + (+h.streak || 0), 0) / n;
  const maxStreak      = Math.max(...habits.map(h => +h.streak || 0));
  const doneToday      = habits.filter(h => h.done_today).length;
  const completionRate = Math.round((doneToday / n) * 100);

  const strong  = habits.filter(h => h.pct_week >= 75);
  const weak    = habits.filter(h => h.pct_week < 35);
  const medium  = habits.filter(h => h.pct_week >= 35 && h.pct_week < 75);

  // Streaks at risk: completed yesterday, streak > 2, not done today
  const yesterday = new Date(); yesterday.setDate(yesterday.getDate() - 1);
  yesterday.setHours(0,0,0,0);
  const atRisk = habits.filter(h => {
    if (h.done_today || !h.last_completed || +h.streak < 2) return false;
    const d = new Date(h.last_completed + 'T00:00:00');
    d.setHours(0,0,0,0);
    return d.getTime() === yesterday.getTime();
  });

  // Per-habit smart feedback
  habits.forEach(h => {
    const pct    = +h.pct_week || 0;
    const streak = +h.streak || 0;
    let fb = '';
    if (pct === 100) fb = 'Perfect week you\'re locked in. 🔒';
    else if (pct >= 80) fb = 'Very consistent keep this pattern. 💪';
    else if (pct >= 50) fb = 'Good, but 1‒2 missed days. Find a trigger cue.';
    else if (pct > 0)   fb = 'Struggling this week. Try habit stacking attach it to an existing routine.';
    else                fb = 'Not started this week. Shrink it to 2 minutes to remove resistance.';
    result.perHabit.push({ id: h.id, name: h.name, streak, pct, feedback: fb });
  });

  // Habit score (0-100):
  //  40% consistency, 30% max-streak, 20% today completion, 10% low weak count
  const score = Math.round(
    (avgConsistency * 0.4) +
    (Math.min(maxStreak / 21, 1) * 30) +
    (completionRate * 0.2) +
    (Math.max(0, 1 - weak.length / n) * 10)
  );
  result.score = score;

  result.metrics = {
    avgConsistency: Math.round(avgConsistency),
    avgStreak: Math.round(avgStreak),
    maxStreak, doneToday, total: n,
    completionRate, strong: strong.length, weak: weak.length, medium: medium.length
  };

  // ── Insights ──────────────────────────────────────────────────
  if (completionRate === 100) {
    result.insights.push({ t: 'success', msg: `🏆 Perfect day! Every habit completed you're building an identity, not just a routine.` });
  } else if (completionRate >= 70) {
    result.insights.push({ t: 'success', msg: `✅ ${doneToday}/${n} habits done today solid. Finish the remaining ${n - doneToday} to lock in your streak.` });
  } else if (completionRate > 0) {
    result.insights.push({ t: 'warn', msg: `⚡ ${doneToday}/${n} habits done. Your momentum drops after 6PM complete the rest now.` });
  } else {
    result.insights.push({ t: 'danger', msg: `⚠️ No habits done today yet. Start with your easiest habit action creates motivation, not the other way around.` });
  }

  if (atRisk.length) {
    const names = atRisk.map(h => `"${h.name}"`).slice(0, 2).join(', ');
    result.insights.push({ t: 'warn', msg: `🔥 Streak at risk: ${names}. You must complete ${atRisk.length === 1 ? 'it' : 'them'} today to keep your streak alive!` });
  }

  if (maxStreak >= 21) {
    result.insights.push({ t: 'success', msg: `🌟 ${maxStreak}-day streak! Research shows habits become semi-automatic around 21 days. You're past that threshold.` });
  } else if (maxStreak >= 7) {
    result.insights.push({ t: 'success', msg: `🔥 ${maxStreak}-day streak you're in the habit-formation window. Don't break the chain.` });
  }

  if (weak.length >= 2) {
    const weakNames = weak.map(h => `"${h.name}"`).slice(0, 2).join(' and ');
    result.insights.push({ t: 'danger', msg: `📉 ${weakNames} are below 35% consistency this week. Either simplify them down to 2 minutes, or replace with easier versions.` });
  }

  if (n > 4 && avgConsistency < 45) {
    result.insights.push({ t: 'warn', msg: `💡 You have ${n} habits but ${Math.round(avgConsistency)}% average consistency. The research is clear: fewer, stronger habits beat many weak ones. Cut to 3 core habits.` });
  }

  if (strong.length === n && n >= 3) {
    result.insights.push({ t: 'success', msg: `🎯 All ${n} habits above 75% consistency you've built a real system. Time to add a challenging new habit.` });
  }

  return result;
};

// ─────────────────────────────────────────────────────────────────────────────
// FOCUS INTELLIGENCE
// Input : sessions[], stats{} from /api/focus/index.php
// Output: { productivityScore, insights[], metrics{} }
// ─────────────────────────────────────────────────────────────────────────────
Intel.focus = function (sessions, stats) {
  const result = { productivityScore: 0, insights: [], metrics: {} };
  if (!sessions || !sessions.length) return result;

  const total     = sessions.length;
  const totalMins = sessions.reduce((s, x) => s + (+x.duration_minutes || 0), 0);
  const avgMins   = Math.round(totalMins / total);
  const weekHrs   = stats?.week_hours || 0;
  const todayMins = stats?.today_minutes || 0;

  // Classify sessions
  const deepWork    = sessions.filter(s => +s.duration_minutes >= 45);
  const flowState   = sessions.filter(s => +s.duration_minutes >= 90);
  const pomodoro    = sessions.filter(s => +s.duration_minutes >= 20 && +s.duration_minutes < 45);
  const shortBursts = sessions.filter(s => +s.duration_minutes < 20);

  // Best time of day by total minutes
  const todMap = {};
  sessions.forEach(s => {
    todMap[s.time_of_day] = (todMap[s.time_of_day] || 0) + +s.duration_minutes;
  });
  const bestTod = Object.entries(todMap).sort((a, b) => b[1] - a[1])[0]?.[0];

  // Consistency: sessions in last 7 unique days
  const uniqueDays = new Set(sessions.map(s => s.session_date)).size;
  const consistency = Math.round((uniqueDays / 7) * 100);

  // Trend: compare first half vs second half of session list
  const half   = Math.floor(sessions.length / 2);
  const recent = sessions.slice(0, half);
  const older  = sessions.slice(half);
  const recentAvg = recent.length ? recent.reduce((s, x) => s + +x.duration_minutes, 0) / recent.length : 0;
  const olderAvg  = older.length  ? older.reduce((s, x)  => s + +x.duration_minutes, 0) / older.length  : 0;
  const trend = recentAvg > olderAvg * 1.1 ? 'improving' : recentAvg < olderAvg * 0.9 ? 'declining' : 'stable';

  // Productivity score (0–100)
  // 35pts: avg session length (target 60 min), 30pts: % deep work, 25pts: weekly volume, 10pts: consistency
  const pScore = Math.round(
    Math.min((avgMins / 60), 1) * 35 +
    (deepWork.length / Math.max(total, 1)) * 30 +
    Math.min(weekHrs / 15, 1) * 25 +
    (consistency / 100) * 10
  );
  result.productivityScore = pScore;

  result.metrics = { avgMins, totalMins, total, weekHrs, todayMins, uniqueDays, consistency, deepWork: deepWork.length, flowState: flowState.length, pomodoro: pomodoro.length, shortBursts: shortBursts.length, bestTod, trend };

  // ── Insights ──────────────────────────────────────────────────
  if (pScore >= 80) {
    result.insights.push({ t: 'success', msg: `🏆 Elite productivity score: ${pScore}/100. You're operating like a professional. Protect this routine.` });
  } else if (pScore >= 60) {
    result.insights.push({ t: 'success', msg: `🎯 Strong productivity score: ${pScore}/100. You're building real deep-work capacity.` });
  } else if (pScore >= 40) {
    result.insights.push({ t: 'warn', msg: `📊 Moderate score: ${pScore}/100. Focus on one longer session per day to push past this plateau.` });
  } else {
    result.insights.push({ t: 'danger', msg: `⚡ Low score: ${pScore}/100. Even 1 consistent 30-minute session daily would dramatically improve this within a week.` });
  }

  if (flowState.length >= 2) {
    result.insights.push({ t: 'success', msg: `🧠 ${flowState.length} flow-state sessions (90+ min) this is where mastery happens. Elite performers aim for 3‒4 per week.` });
  } else if (deepWork.length >= 3) {
    result.insights.push({ t: 'success', msg: `💪 ${deepWork.length} deep work sessions (45+ min) solid. Challenge yourself to push one into 90+ minutes.` });
  } else if (shortBursts.length > total * 0.6) {
    result.insights.push({ t: 'danger', msg: `⚠️ ${shortBursts.length}/${total} sessions are under 20 minutes too short to build flow state. These count as warm-up, not real work.` });
  }

  if (trend === 'improving') {
    result.insights.push({ t: 'success', msg: `📈 Trending up! Your recent sessions are longer than your earlier ones you're improving.` });
  } else if (trend === 'declining') {
    result.insights.push({ t: 'warn', msg: `📉 Your focus sessions are getting shorter recently. Check for fatigue, distractions, or scheduling issues.` });
  }

  if (bestTod) {
    const labels = { morning: 'morning 🌅', afternoon: 'afternoon ☀️', evening: 'evening 🌆', night: 'night 🌙' };
    result.insights.push({ t: 'info', msg: `⏰ Peak performance: your best focus is in the ${labels[bestTod] || bestTod}. Block this time treat it like a meeting you can't miss.` });
  }

  if (consistency >= 80) {
    result.insights.push({ t: 'success', msg: `🔥 ${uniqueDays}/7 days with focus logged this week excellent consistency. Consistency beats intensity every time.` });
  } else if (consistency < 40 && total >= 2) {
    result.insights.push({ t: 'warn', msg: `📅 Only ${uniqueDays}/7 days with focus sessions. Aim for a minimum viable session every day even 10 minutes maintains the neural pathways.` });
  }

  if (todayMins >= 120) {
    result.insights.push({ t: 'success', msg: `🎯 ${todayMins} mins focused today! That's ${Math.round(todayMins/60 * 10)/10} hours. Exceptional day.` });
  }

  return result;
};

// ─────────────────────────────────────────────────────────────────────────────
// DECISION INTELLIGENCE
// Input : decisions[], stats{} from /api/decisions/index.php
// Output: { winRate, insights[], metrics{} }
// ─────────────────────────────────────────────────────────────────────────────
Intel.decisions = function (decisions, stats) {
  const result = { winRate: 0, insights: [], metrics: {} };
  if (!decisions || decisions.length < 2) return result;

  const total   = decisions.length;
  const good    = decisions.filter(d => d.outcome === 'good').length;
  const bad     = decisions.filter(d => d.outcome === 'bad').length;
  const neutral = decisions.filter(d => d.outcome === 'neutral').length;
  const winRate = stats?.rate ?? Math.round((good / total) * 100);
  result.winRate = winRate;

  // Day-of-week pattern (which days produce bad decisions?)
  const DAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  const dayBad = {};
  decisions.filter(d => d.outcome === 'bad').forEach(d => {
    const day = new Date(d.created_at).getDay();
    dayBad[day] = (dayBad[day] || 0) + 1;
  });
  const worstDayEntry = Object.entries(dayBad).sort((a, b) => b[1] - a[1])[0];
  const worstDay = worstDayEntry && dayBad[worstDayEntry[0]] >= 2 ? DAY_NAMES[+worstDayEntry[0]] : null;

  // Most recent consecutive good decisions streak
  let goodStreak = 0;
  for (const d of decisions) {
    if (d.outcome === 'good') goodStreak++;
    else break;
  }

  // Recent trend (last 5 vs older)
  const recent5 = decisions.slice(0, 5);
  const recentGood = recent5.filter(d => d.outcome === 'good').length;
  const recentRate = Math.round((recentGood / recent5.length) * 100);
  const trend = recentRate > winRate + 10 ? 'improving' : recentRate < winRate - 10 ? 'declining' : 'stable';

  // Bad:Good ratio
  const ratio = bad > 0 ? (good / bad).toFixed(1) : '∞';

  result.metrics = { total, good, bad, neutral, winRate, recentRate, trend, goodStreak, worstDay, ratio };

  // ── Insights ──────────────────────────────────────────────────
  if (winRate >= 75) {
    result.insights.push({ t: 'success', msg: `🏆 ${winRate}% win rate exceptional judgment. You're making decisions with clarity and intention.` });
  } else if (winRate >= 55) {
    result.insights.push({ t: 'success', msg: `📈 ${winRate}% win rate above average. Your decision-making is solid; keep logging to sharpen your awareness.` });
  } else if (winRate >= 35) {
    result.insights.push({ t: 'warn', msg: `⚠️ ${winRate}% win rate. Try the 24-hour rule: for non-urgent decisions, wait 24 hours before committing.` });
  } else {
    result.insights.push({ t: 'danger', msg: `🔴 ${winRate}% win rate. Before your next decision, write out the consequences on paper this dramatically improves outcome quality.` });
  }

  if (goodStreak >= 4) {
    result.insights.push({ t: 'success', msg: `🔥 ${goodStreak} good decisions in a row you're in a strong decision-making flow state right now. Use this momentum.` });
  }

  if (trend === 'improving') {
    result.insights.push({ t: 'success', msg: `📈 Your last 5 decisions: ${recentRate}% good better than your overall ${winRate}%. You're improving!` });
  } else if (trend === 'declining') {
    result.insights.push({ t: 'warn', msg: `📉 Your last 5 decisions: only ${recentRate}% good below your usual ${winRate}%. Something may have shifted check stress or sleep.` });
  }

  if (worstDay) {
    result.insights.push({ t: 'warn', msg: `📅 Poor decision pattern: you tend to make bad calls on ${worstDay}s. Avoid major decisions on those days, or add extra reflection first.` });
  }

  if (neutral > total * 0.5) {
    result.insights.push({ t: 'info', msg: `⚪ ${neutral} decisions tagged neutral. Go back and update the outcomes accurate tracking reveals patterns you can't see otherwise.` });
  }

  if (bad > 0) {
    result.insights.push({ t: 'info', msg: `💡 For your ${bad} bad decision${bad > 1 ? 's' : ''}: ask "what would I do differently?" Writing it down once encodes the lesson permanently.` });
  }

  return result;
};

// ─────────────────────────────────────────────────────────────────────────────
// SKILL INTELLIGENCE
// Input : skills[] from /api/skills/index.php
// Output: { insights[], metrics{} }
// ─────────────────────────────────────────────────────────────────────────────
Intel.skills = function (skills) {
  const result = { insights: [], metrics: {}, distribution: [] };
  if (!skills || !skills.length) return result;

  const levels      = skills.map(s => +s.proficiency_level);
  const avg         = +(levels.reduce((a, b) => a + b, 0) / levels.length).toFixed(1);
  const max         = Math.max(...levels);
  const min         = Math.min(...levels);
  const spread      = max - min;
  const expert      = skills.filter(s => +s.proficiency_level >= 8);
  const intermediate= skills.filter(s => +s.proficiency_level >= 5 && +s.proficiency_level < 8);
  const beginner    = skills.filter(s => +s.proficiency_level < 5);

  // Skill score
  const score = Math.min(Math.round(avg * 10), 100);

  // Distribution info
  result.distribution = [
    { label: 'Expert (8–10)',       count: expert.length,       color: 'success' },
    { label: 'Intermediate (5–7)', count: intermediate.length, color: 'warn' },
    { label: 'Beginner (1–4)',      count: beginner.length,     color: 'danger' },
  ];

  result.metrics = { avg, max, min, spread, score, total: skills.length, expert: expert.length, intermediate: intermediate.length, beginner: beginner.length };

  // ── Insights ──────────────────────────────────────────────────
  if (avg >= 7.5) {
    result.insights.push({ t: 'success', msg: `🏆 Average ${avg}/10 you're operating at a high-skill level. Focus on mastery (9–10) in your top 1–2 skills.` });
  } else if (avg >= 5.5) {
    result.insights.push({ t: 'success', msg: `📈 Average ${avg}/10 solid intermediate level. Choose one skill to push to expert (8+) in the next 90 days.` });
  } else {
    result.insights.push({ t: 'warn', msg: `📚 Average ${avg}/10 still building. Pick ONE skill and study it deliberately for 20 minutes daily this month.` });
  }

  if (expert.length >= 2) {
    const names = expert.slice(0, 2).map(s => s.skill_name).join(' & ');
    result.insights.push({ t: 'success', msg: `⭐ Expert-level: ${names} these are your competitive advantages. Leverage them actively.` });
  } else if (expert.length === 1) {
    result.insights.push({ t: 'success', msg: `⭐ Expert skill: ${expert[0].skill_name} (${expert[0].proficiency_level}/10). Build 1 more expert skill to compound your value.` });
  } else {
    result.insights.push({ t: 'warn', msg: `🎯 No expert-level skills yet. Pick your closest skill (level ${max}) and focus on pushing it past 8.` });
  }

  if (beginner.length >= 2) {
    result.insights.push({ t: 'info', msg: `💡 ${beginner.length} beginner skills. Decide: invest to grow them, or remove ones that aren't aligned with your goals.` });
  }

  if (spread > 5 && skills.length >= 4) {
    const topS = skills.find(s => +s.proficiency_level === max);
    const lowS = skills.find(s => +s.proficiency_level === min);
    result.insights.push({ t: 'info', msg: `📊 Wide skill gap from ${lowS?.skill_name} (${min}) to ${topS?.skill_name} (${max}). This kind of T-shape profile is powerful broad basics with deep expertise.` });
  }

  // Growth recommendation
  if (skills.length >= 3) {
    const bottomHalf = [...skills].sort((a, b) => +a.proficiency_level - +b.proficiency_level).slice(0, Math.ceil(skills.length / 2));
    const avgBottom = (bottomHalf.reduce((s, sk) => s + +sk.proficiency_level, 0) / bottomHalf.length).toFixed(1);
    if (avgBottom < 5) {
      result.insights.push({ t: 'warn', msg: `⚡ Bottom-half skills average ${avgBottom}/10. Consider focused practice on 1 skill from this tier to build momentum.` });
    }
  }

  if (skills.length < 4) {
    result.insights.push({ t: 'info', msg: `💡 Add ${4 - skills.length} more skills to get a clearer picture of your overall profile and where to invest next.` });
  }

  return result;
};

// ─────────────────────────────────────────────────────────────────────────────
// DASHBOARD SYNTHESIS — Combine all module scores into a FlowScore
// ─────────────────────────────────────────────────────────────────────────────
Intel.synthesize = function (habitResult, focusResult, decisionResult, skillResult) {
  const scores = [];
  const highlights = [];
  const priorities = [];

  if (habitResult?.score) {
    scores.push({ label: 'Habits', score: habitResult.score, icon: '✅' });
    if (habitResult.score >= 65) highlights.push('consistent habits');
    else priorities.push('habit consistency (' + habitResult.metrics.avgConsistency + '%)');
  }
  if (focusResult?.productivityScore) {
    scores.push({ label: 'Focus', score: focusResult.productivityScore, icon: '⏱' });
    if (focusResult.productivityScore >= 60) highlights.push('strong focus');
    else priorities.push('focus depth (' + focusResult.metrics.avgMins + ' min avg)');
  }
  if (decisionResult?.winRate) {
    scores.push({ label: 'Decisions', score: decisionResult.winRate, icon: '◆' });
    if (decisionResult.winRate >= 60) highlights.push('good judgment');
    else priorities.push('decision quality (' + decisionResult.winRate + '%)');
  }
  if (skillResult?.metrics?.score) {
    scores.push({ label: 'Skills', score: skillResult.metrics.score, icon: '⭐' });
    if (skillResult.metrics.score >= 55) highlights.push('growing skill set');
    else priorities.push('skill development (avg ' + skillResult.metrics.avg + '/10)');
  }

  const flowScore = scores.length
    ? Math.round(scores.reduce((s, x) => s + x.score, 0) / scores.length)
    : 0;

  const tier = flowScore >= 80 ? 'Elite'
    : flowScore >= 65 ? 'Strong'
    : flowScore >= 50 ? 'Building'
    : flowScore >= 35 ? 'Developing'
    : 'Starting';

  const tierColor = flowScore >= 65 ? 'success' : flowScore >= 45 ? 'warn' : 'danger';

  const summary = flowScore >= 80
    ? `You're operating at an elite level. Your systems are working. Now focus on refinement and raising your weakest score.`
    : flowScore >= 65
    ? `Strong performance across the board. You have real momentum ${priorities.length ? 'push ' + priorities[0] + ' to unlock the next level.' : 'keep building.'}`
    : flowScore >= 50
    ? `Solid foundation. You're building good systems. ${priorities.length ? 'Your biggest lever right now is ' + priorities[0] + '.' : 'Stay consistent.'}`
    : flowScore >= 35
    ? `You're in early stages. Focus on just 1 improvement area. ${priorities.length ? 'Start with ' + priorities[0] + '.' : 'Log more data to get insights.'}`
    : `Limited data to analyze yet. Keep logging daily patterns emerge after 7+ days.`;

  return { flowScore, tier, tierColor, scores, highlights, priorities, summary };
};

// ─────────────────────────────────────────────────────────────────────────────
// RENDER HELPERS — Build insight card HTML
// ─────────────────────────────────────────────────────────────────────────────
Intel.renderInsights = function (insights, container) {
  if (!container) return;
  if (!insights || !insights.length) {
    container.innerHTML = '<p class="no-insights">Log more data to unlock personalized insights.</p>';
    return;
  }
  container.innerHTML = insights.map(ins => `
    <div class="insight-card insight-${ins.t}">
      <span class="insight-text">${FS.escape(ins.msg)}</span>
    </div>`).join('');
};

Intel.renderScoreGauge = function (score, label, container) {
  if (!container) return;
  const color = score >= 70 ? '#10B981' : score >= 45 ? '#F59E0B' : '#EF4444';
  const tier  = score >= 80 ? 'Elite' : score >= 65 ? 'Strong' : score >= 50 ? 'Building' : score >= 35 ? 'Developing' : 'Low';
  const dash  = 283; // circumference of r=45 circle
  const offset = dash - (dash * score / 100);
  container.innerHTML = `
    <div class="score-gauge">
      <svg viewBox="0 0 100 100" width="110" height="110">
        <circle cx="50" cy="50" r="45" fill="none" stroke="#E2E8F0" stroke-width="8"/>
        <circle cx="50" cy="50" r="45" fill="none" stroke="${color}" stroke-width="8"
          stroke-dasharray="${dash}" stroke-dashoffset="${offset}"
          stroke-linecap="round" transform="rotate(-90 50 50)"
          style="transition:stroke-dashoffset 1s ease"/>
        <text x="50" y="46" text-anchor="middle" font-size="18" font-weight="800" fill="${color}">${score}</text>
        <text x="50" y="60" text-anchor="middle" font-size="9" fill="#94A3B8">${label}</text>
      </svg>
      <div class="gauge-tier" style="color:${color}">${tier}</div>
    </div>`;
};

window.Intel = Intel;
console.info('[FlowStack] Intelligence engine loaded.');
})(window);
