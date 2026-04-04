# FlowStack — Personal Analytics Platform

A clean, scalable SaaS-style personal analytics system with **strict frontend/backend separation**.

## Project Structure

```
FlowStack/
├── index.html                  ← Root entry (redirects to frontend/)
├── setup_db.php                ← One-time DB setup utility
├── .htaccess                   ← Apache config
├── database/
│   └── schema.sql              ← Full MySQL schema (8 tables)
├── frontend/                   ← ALL HTML + CSS + JS (no PHP)
│   ├── login.html
│   ├── register.html
│   ├── dashboard.html
│   ├── habits.html
│   ├── focus.html
│   ├── decisions.html
│   ├── skills.html
│   ├── nextmove.html
│   ├── pathcompare.html
│   ├── analytics.html
│   └── assets/
│       ├── css/
│       │   ├── app.css         ← App layout, components
│       │   └── style.css       ← Extended styles
│       └── js/
│           ├── app.js          ← Core engine (FS.api, FS.requireAuth…)
│           ├── charts.js       ← Chart helpers
│           └── main.js         ← Page utilities
└── backend/                    ← ALL PHP (no HTML)
    ├── .htaccess               ← Directory listing disabled
    ├── config/
    │   └── db.php              ← PDO singleton
    ├── helpers/
    │   └── response.php        ← jsonOk(), jsonError(), requireAuth()
    ├── auth/
    │   ├── check.php           ← GET  → session check
    │   ├── login.php           ← POST → login
    │   ├── register.php        ← POST → register
    │   └── logout.php          ← POST → logout
    └── api/
        ├── dashboard/index.php ← GET  → aggregated stats + insights
        ├── habits/
        │   ├── index.php       ← GET  → list habits
        │   ├── add.php         ← POST → add habit
        │   ├── update.php      ← POST → mark complete
        │   └── delete.php      ← POST → delete habit
        ├── focus/index.php     ← GET/POST → sessions
        ├── decisions/index.php ← GET/POST → decisions CRUD
        ├── skills/index.php    ← GET/POST → skills CRUD
        ├── nextmove/index.php  ← GET/POST → rule-based advice
        ├── pathcompare/index.php ← GET/POST → path scoring
        └── analytics/
            ├── habit_chart.php   ← Habit completions (7-day)
            ├── focus_chart.php   ← Focus minutes (7-day)
            └── decision_chart.php ← Decision breakdown
```

## Quick Start (XAMPP)

1. **Clone/copy** to `C:/xampp/htdocs/FlowStack/`
2. **Start** Apache + MySQL in XAMPP
3. **Run setup:** http://localhost/FlowStack/setup_db.php
4. **Launch:** http://localhost/FlowStack/

## API Pattern

All frontend pages use `fetch()` via the `FS.api()` wrapper:

```js
// GET data
const res = await FS.api('/api/habits/index.php');

// POST data
const res = await FS.api('/api/habits/add.php', 'POST', { name: 'Read daily' });
const res = await FS.api('/api/habits/update.php', 'POST', { habit_id: 3 });
const res = await FS.api('/api/habits/delete.php', 'POST', { habit_id: 3 });
```

## Database Tables

| Table | Purpose |
|---|---|
| `users` | Accounts (bcrypt passwords) |
| `habits` | Habit definitions + streak |
| `habit_logs` | Daily completions |
| `focus_sessions` | Timer sessions |
| `decisions` | Decision log + outcome |
| `skills` | Skill name + proficiency (1-10) |
| `path_compare` | PathCompare history |
| `next_move` | NextMove advice history |
| `analytics_logs` | General event log (extendable) |

## Tech Stack

- **Frontend:** HTML5, Vanilla CSS, Vanilla JavaScript, Chart.js
- **Backend:** PHP 8+, PDO (prepared statements only)
- **Database:** MySQL 8 / MariaDB
- **Server:** Apache (XAMPP)
