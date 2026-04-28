/**
 * FlowStack Charts JS
 * /assets/js/charts.js
 * Initializes all 4 Chart.js charts on analytics.php
 * Supports live date-range switching without page reload.
 */

(function () {
    'use strict';

    // Store chart instances to destroy before re-drawing
    window.fsCharts = {};

    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.font.size   = 12;
    Chart.defaults.color       = '#888780';

    // ── Helper: API base path ───────────────────────────────
    // Resolve API path relative to current page depth
    const depth   = window.location.pathname.split('/').filter(Boolean).length - 1;
    const apiBase = '../'.repeat(Math.max(0, depth - 1)) + 'modules/api/';

    // ── Fetch → render helper ───────────────────────────────
    async function fetchChart(endpoint, range) {
        const res  = await fetch(apiBase + endpoint + '?range=' + encodeURIComponent(range));
        if (!res.ok) throw new Error('API error: ' + res.status);
        return res.json();
    }

    // ── Destroy and recreate chart ──────────────────────────
    function destroyChart(key) {
        if (window.fsCharts[key]) {
            window.fsCharts[key].destroy();
            delete window.fsCharts[key];
        }
    }

    // ── 1. Habit Line Chart ─────────────────────────────────
    async function renderHabitChart(range) {
        destroyChart('habit');
        const data = await fetchChart('habit_chart.php', range);
        const ctx  = document.getElementById('habitChart');
        if (!ctx) return;

        window.fsCharts.habit = new Chart(ctx, {
            type: 'line',
            data: {
                labels:   data.labels,
                datasets: [{
                    label:           'Habits Completed',
                    data:            data.data,
                    borderColor:     '#7F77DD',
                    backgroundColor: 'rgba(127,119,221,0.1)',
                    borderWidth:     2.5,
                    pointRadius:     4,
                    pointBackgroundColor: '#534AB7',
                    pointBorderColor:    '#fff',
                    pointBorderWidth:    2,
                    tension:         0.4,
                    fill:            true,
                }]
            },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                interaction:  { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#2C2C2A',
                        padding:         10,
                        cornerRadius:    8,
                        callbacks: {
                            label: ctx => ` ${ctx.parsed.y} habit${ctx.parsed.y !== 1 ? 's' : ''}`
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 8 } },
                    y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(0,0,0,0.05)' } }
                }
            }
        });
    }

    // ── 2. Focus Bar Chart ──────────────────────────────────
    async function renderFocusChart(range) {
        destroyChart('focus');
        const data = await fetchChart('focus_chart.php', range);
        const ctx  = document.getElementById('focusChart');
        if (!ctx) return;

        window.fsCharts.focus = new Chart(ctx, {
            type: 'bar',
            data: {
                labels:   data.labels,
                datasets: [{
                    label:           'Focus (min)',
                    data:            data.data,
                    backgroundColor: 'rgba(29,158,117,0.8)',
                    borderColor:     '#1D9E75',
                    borderWidth:     0,
                    borderRadius:    6,
                    borderSkipped:   false,
                }]
            },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                interaction:  { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#2C2C2A',
                        padding:         10,
                        cornerRadius:    8,
                        callbacks: {
                            label: ctx => ` ${ctx.parsed.y} min`
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 7 } },
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } }
                }
            }
        });
    }

    // ── 3. Decision Doughnut Chart ──────────────────────────
    async function renderDecisionChart(range) {
        destroyChart('decision');
        const data = await fetchChart('decision_chart.php', range);
        const ctx  = document.getElementById('decisionChart');
        if (!ctx) return;

        const total = data.data.reduce((a, b) => a + b, 0);

        window.fsCharts.decision = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels:   data.labels,
                datasets: [{
                    data:            data.data,
                    backgroundColor: ['#1D9E75', '#D85A30', '#888780'],
                    borderColor:     ['#1D9E75', '#D85A30', '#888780'],
                    borderWidth:     0,
                    hoverOffset:     6,
                }]
            },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                cutout:              '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels:   { padding: 18, usePointStyle: true, pointStyleWidth: 10 }
                    },
                    tooltip: {
                        backgroundColor: '#2C2C2A',
                        padding:         10,
                        cornerRadius:    8,
                        callbacks: {
                            label: ctx => {
                                const pct = total > 0 ? Math.round((ctx.parsed / total) * 100) : 0;
                                return ` ${ctx.label}: ${ctx.parsed} (${pct}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    // ── 4. Focus Time-of-Day Polar Chart ───────────────────
    async function renderFocusTimeChart(range) {
        destroyChart('focusTime');
        const data = await fetchChart('focus_time_chart.php', range);
        const ctx  = document.getElementById('focusTimeChart');
        if (!ctx) return;

        window.fsCharts.focusTime = new Chart(ctx, {
            type: 'polarArea',
            data: {
                labels:   data.labels,
                datasets: [{
                    data:            data.data,
                    backgroundColor: [
                        'rgba(186,117,23,0.75)',  // morning  amber
                        'rgba(37,99,235,0.75)',   // afternoon blue
                        'rgba(83,74,183,0.75)',   // evening  purple
                        'rgba(136,135,128,0.75)', // night    gray
                    ],
                    borderColor: ['#BA7517','#2563EB','#534AB7','#888780'],
                    borderWidth: 1,
                }]
            },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels:   { padding: 14, usePointStyle: true, pointStyleWidth: 10 }
                    },
                    tooltip: {
                        backgroundColor: '#2C2C2A',
                        padding:         10,
                        cornerRadius:    8,
                        callbacks: {
                            label: ctx => ` ${ctx.label}: ${ctx.parsed.r} sessions`
                        }
                    }
                },
                scales: {
                    r: {
                        ticks:       { display: false },
                        grid:        { color: 'rgba(0,0,0,0.05)' },
                        beginAtZero: true,
                    }
                }
            }
        });
    }

    // ── Render all charts ───────────────────────────────────
    function renderAll(range) {
        renderHabitChart(range).catch(console.error);
        renderFocusChart(range).catch(console.error);
        renderDecisionChart(range).catch(console.error);
        renderFocusTimeChart(range).catch(console.error);
    }

    // ── Range selector buttons ──────────────────────────────
    const btns = document.querySelectorAll('.range-btn');
    let activeRange = 'week';

    btns.forEach(btn => {
        btn.addEventListener('click', function () {
            btns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            activeRange = this.dataset.range;
            renderAll(activeRange);
        });
    });

    // ── Boot on analytics page ──────────────────────────────
    if (document.getElementById('habitChart')) {
        renderAll(activeRange);
    }

})();
