/**
 * FlowStack — app.js
 * Core engine: API wrapper, auth guard, layout injector, utilities.
 */
;(function (window) {
'use strict';

const FS = {};

const savedTheme = localStorage.getItem('fs-theme') || 'dark';
if (savedTheme === 'light') document.documentElement.setAttribute('data-theme', 'light');

FS.toggleTheme = function() {
    const isLight = document.documentElement.getAttribute('data-theme') === 'light';
    const newTheme = isLight ? 'dark' : 'light';
    if (newTheme === 'light') {
        document.documentElement.setAttribute('data-theme', 'light');
    } else {
        document.documentElement.removeAttribute('data-theme');
    }
    localStorage.setItem('fs-theme', newTheme);
};

FS.initIcons = function() {
    if (window.lucide) {
        lucide.createIcons();
        return;
    }
    const script = document.createElement('script');
    script.src = "https://unpkg.com/lucide@latest";
    script.onload = () => lucide.createIcons();
    document.head.appendChild(script);
};
FS.initIcons();

// ── Compute backend base URL ──────────────────────────────────────────────
// Works on:
//   localhost/FlowStack/frontend/page.html  → /FlowStack/backend
//   yourdomain.epizy.com/frontend/page.html → /backend
//   yourdomain.com/frontend/page.html       → /backend
FS.BACKEND = (function () {
    const loc = window.location;
    const parts = loc.pathname.split('/').filter(Boolean);
    let basePath = '';

    const fsIndex = parts.findIndex(p => p.toLowerCase() === 'flowstack');
    if (fsIndex >= 0) {
        basePath = '/' + parts.slice(0, fsIndex + 1).join('/');
    } else {
        const feIndex = parts.findIndex(p => p.toLowerCase() === 'frontend');
        if (feIndex >= 0) {
            const sliced = parts.slice(0, feIndex);
            basePath = sliced.length ? '/' + sliced.join('/') : '';
        }
    }
    
    if (basePath === '/') basePath = '';
    return loc.origin + basePath + '/backend';
})();


// ── API fetch wrapper ─────────────────────────────────────────────────────
FS.api = async function (endpoint, method, body) {
    method = (method || 'GET').toUpperCase();
    const opts = {
        method,
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
    };
    if (body && method !== 'GET') opts.body = JSON.stringify(body);

    try {
        const res  = await fetch(FS.BACKEND + endpoint, opts);
        const rawText = await res.text();
        let json = null;

        try { 
            json = JSON.parse(rawText); 
        } catch (e) {
            // Salvage JSON if InfinityFree injected HTML tracking scripts at the end
            const match = rawText.match(/({[\s\S]*})/);
            if (match) {
                try { json = JSON.parse(match[1]); } catch (_) {}
            }
        }

        if (!json) {
            console.error('[FS.api] Invalid response:', rawText);
            return { ok: false, status: res.status, data: { error: 'Server configuration error or hosting intervention. Try again.' } };
        }

        if (res.status === 401) {
            sessionStorage.clear();
            window.location.replace(FS.loginUrl());
            return { ok: false, status: 401, data: json };
        }
        
        const isOk = (typeof json.ok === 'boolean') ? json.ok : res.ok;
        return { ok: isOk, status: res.status, data: json };
    } catch (err) {
        console.error('[FS.api] Network error:', err);
        return {
            ok: false, status: 0,
            data: { error: 'Network error. Could not reach the server. Please try again.' }
        };
    }
};

// ── Auth guard ────────────────────────────────────────────────────────────
FS.requireAuth = async function () {
    try {
        const res  = await fetch(FS.BACKEND + '/auth/check.php', {
            credentials: 'include',
            headers: { 'Accept': 'application/json' }
        });
        const rawText = await res.text();
        let data = null;
        try { data = JSON.parse(rawText); } catch (_) {
            const m = rawText.match(/({[\s\S]*})/);
            if (m) try { data = JSON.parse(m[1]); } catch (_) {}
        }
        
        if (!data || !data.authenticated) {
            window.location.replace(FS.loginUrl());
            return null;
        }
        return data.user;
    } catch (e) {
        console.error('[FS.requireAuth] failed:', e);
        window.location.replace(FS.loginUrl());
        return null;
    }
};

// ── URL helpers ───────────────────────────────────────────────────────────
FS.loginUrl = function () {
    const loc = window.location;
    const parts = loc.pathname.split('/').filter(Boolean);

    // Strategy 1: project subfolder named 'FlowStack'
    for (let i = 0; i < parts.length; i++) {
        if (parts[i].toLowerCase() === 'flowstack') {
            return loc.origin + '/' + parts.slice(0, i + 1).join('/') + '/frontend/login.html';
        }
    }
    // Strategy 2: 'frontend' folder detected
    for (let i = 0; i < parts.length; i++) {
        if (parts[i].toLowerCase() === 'frontend') {
            const base = loc.origin + '/' + parts.slice(0, i).join('/');
            return base + '/frontend/login.html';
        }
    }
    return loc.origin + '/frontend/login.html';
};

FS.logout = async function () {
    try { await fetch(FS.BACKEND + '/auth/logout.php', { method: 'POST', credentials: 'include' }); } catch (_) {}
    window.location.replace(FS.loginUrl());
};

// ── Sidebar + Header injector ─────────────────────────────────────────────
FS.initLayout = function (user, activePage) {
    const initials = (user.name || '?').trim().split(/\s+/)
        .map(n => n[0]).join('').toUpperCase().slice(0, 2);

    const navItems = [
        { key: 'dashboard',   label: 'Dashboard',     href: 'dashboard.html',   icon: 'layout-dashboard', color: 'var(--accent-1)' },
        { key: 'habits',      label: 'HabitSync',     href: 'habits.html',      icon: 'check-circle',     color: 'var(--accent-4)' },
        { key: 'focus',       label: 'Focus Tracker', href: 'focus.html',       icon: 'timer',            color: 'var(--accent-2)' },
        { key: 'decisions',   label: 'DecisionLog',   href: 'decisions.html',   icon: 'git-merge',        color: 'var(--accent-1)' },
        { key: 'skills',      label: 'Skills',        href: 'skills.html',      icon: 'award',            color: 'var(--accent-2)' },
        { key: 'pathcompare', label: 'Path Compare',  href: 'pathcompare.html', icon: 'scale',            color: 'var(--accent-5)' },
        { key: 'nextmove',    label: 'Next Move',     href: 'nextmove.html',    icon: 'compass',          color: 'var(--accent-2)' },
        { key: 'analytics',   label: 'Analytics',     href: 'analytics.html',   icon: 'bar-chart',        color: 'var(--accent-1)' },
    ];

    const navHTML = navItems.map(p => `
        <a href="${p.href}" class="nav-item ${activePage === p.key ? 'nav-active' : ''}" id="nav-${p.key}" style="--nav-color: ${p.color}">
            <i data-lucide="${p.icon}" class="nav-emoji" style="width:20px;height:20px;stroke-width:1.5;color:var(--nav-color)"></i>
            <span class="nav-label">${p.label}</span>
        </a>`).join('');

    const sb = document.getElementById('fs-sidebar');
    if (sb) {
        sb.style.backdropFilter = 'blur(20px)';
        sb.style.background = 'var(--bg-glass)';
        
        sb.innerHTML = `
            <div class="sidebar-logo">
                <i data-lucide="zap" style="color:var(--accent-2);width:24px;height:24px;animation: pulse 2s infinite"></i>
                <span class="logo-text">FlowStack</span>
            </div>
            <nav class="sidebar-nav">
                <p class="nav-section-label">Main Menu</p>
                ${navHTML}
            </nav>
            <div style="padding: 0 16px 16px;">
                <a href="focus.html" class="btn btn-primary" style="width:100%; justify-content:center; background:linear-gradient(135deg, var(--accent-1), var(--accent-2)); border:none;">
                    ⚡ Start Focus
                </a>
            </div>
            <div class="sidebar-user" style="border-top:1px solid var(--border-glow); margin-top:0;">
                <div class="user-avatar">${FS.escape(initials)}</div>
                <div class="user-info">
                    <div class="user-name">${FS.escape(user.name)}</div>
                    <div style="font-size:0.65rem; color:var(--text-muted); margin-top:2px;">XP <div class="progress-wrap" style="height:4px; margin-top:2px"><div class="progress-bar" style="width:60%; background:var(--accent-2)"></div></div></div>
                </div>
            </div>`;
    }

    const hdr = document.getElementById('fs-header');
    if (hdr) {
        hdr.innerHTML = `
            <div class="header-left">
                <button class="hamburger" id="fs-burger" aria-label="Toggle navigation" aria-expanded="false">
                    <span></span><span></span><span></span>
                </button>
                <span class="header-logo"><i data-lucide="zap" style="display:inline-block;vertical-align:middle;width:18px;height:18px;"></i> FlowStack</span>
            </div>
            <div class="header-right">
                <button class="btn btn-ghost" id="fs-theme-btn" aria-label="Toggle Theme" style="padding:4px; margin-right:4px" title="Toggle Light/Dark Theme"><i data-lucide="moon" style="width:18px;height:18px;"></i></button>
                <div class="user-avatar sm">${FS.escape(initials)}</div>
                <span class="header-name">${FS.escape(user.name.split(' ')[0])}</span>
                <button class="btn-signout" id="fs-logout">Sign out</button>
            </div>`;

        document.getElementById('fs-logout').addEventListener('click', FS.logout);
        document.getElementById('fs-theme-btn').addEventListener('click', FS.toggleTheme);

        const burger  = document.getElementById('fs-burger');
        const overlay = document.getElementById('fs-overlay');
        const sidebar = sb;

        const openSidebar  = () => { sidebar.classList.add('sidebar-open'); overlay.classList.add('visible'); burger.setAttribute('aria-expanded', 'true'); document.body.style.overflow = 'hidden'; };
        const closeSidebar = () => { sidebar.classList.remove('sidebar-open'); overlay.classList.remove('visible'); burger.setAttribute('aria-expanded', 'false'); document.body.style.overflow = ''; };

        burger.addEventListener('click', () => sidebar.classList.contains('sidebar-open') ? closeSidebar() : openSidebar());
        overlay.addEventListener('click', closeSidebar);
        window.addEventListener('resize', () => { if (window.innerWidth > 768) closeSidebar(); });
    }
    
    if (window.lucide) {
        lucide.createIcons();
    }
};

// ── Toast notifications ───────────────────────────────────────────────────
FS.toast = function (msg, type) {
    type = type || 'success';
    // Remove existing
    document.querySelectorAll('.fs-toast').forEach(el => el.remove());

    const el = document.createElement('div');
    el.className = 'fs-toast toast-' + type;
    el.setAttribute('role', 'alert');
    el.setAttribute('aria-live', 'polite');
    const iconName = type === 'success' ? 'check-circle' : type === 'danger' ? 'alert-triangle' : 'info';
    el.innerHTML = `
        <i data-lucide="${iconName}" class="toast-icon"></i>
        <span class="toast-msg">${FS.escape(msg)}</span>
        <button class="fs-toast-close" aria-label="Close"><i data-lucide="x" style="width:14px;height:14px;"></i></button>`;
    el.querySelector('.fs-toast-close').addEventListener('click', () => {
        el.classList.remove('show');
        setTimeout(() => el.remove(), 350);
    });
    document.body.appendChild(el);
    requestAnimationFrame(() => { requestAnimationFrame(() => el.classList.add('show')); });
    setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 350); }, 4500);
};

// ── Helpers ───────────────────────────────────────────────────────────────
FS.escape = function (str) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
    return String(str ?? '').replace(/[&<>"']/g, c => map[c]);
};

FS.relDate = function (dateStr) {
    if (!dateStr) return '';
    let cleanDate = dateStr.replace(' ', 'T');
    if (!cleanDate.includes('T')) cleanDate += 'T00:00:00';
    const d = new Date(cleanDate);
    if (isNaN(d)) return dateStr;
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

FS.showLoading = function (show) {
    const el = document.getElementById('fs-loading');
    if (el) el.style.display = show ? 'flex' : 'none';
};

// Debug
window.FS = FS;
console.info('[FlowStack] Backend:', FS.BACKEND);
})(window);
