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
// Works at: http://localhost/FlowStack/frontend/page.html
// Produces: http://localhost/FlowStack/backend
FS.BACKEND = (function () {
    const parts  = window.location.pathname.split('/');  // ['','FlowStack','frontend','page.html']
    for (let i = 0; i < parts.length; i++) {
        if (parts[i].toLowerCase() === 'flowstack') {
            const base = window.location.origin + parts.slice(0, i + 1).join('/');
            return base + '/backend';
        }
    }
    // Hard fallback
    return window.location.origin + '/FlowStack/backend';
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
        let json   = {};
        try { json = await res.json(); } catch (_) {}

        if (res.status === 401) {
            sessionStorage.clear();
            window.location.replace(FS.loginUrl());
            return { ok: false, status: 401, data: json };
        }
        return { ok: res.ok, status: res.status, data: json };
    } catch (err) {
        console.error('[FS.api] Network error:', err);
        return {
            ok: false, status: 0,
            data: { error: 'Network error is XAMPP running and MySQL on?' }
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
        let data = {};
        try { data = await res.json(); } catch (_) {}
        if (!data.authenticated) {
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
    const parts = window.location.pathname.split('/');
    for (let i = 0; i < parts.length; i++) {
        if (parts[i].toLowerCase() === 'flowstack') {
            return window.location.origin + parts.slice(0, i + 1).join('/') + '/frontend/login.html';
        }
    }
    return window.location.origin + '/FlowStack/frontend/login.html';
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
        { key: 'dashboard',   label: 'Dashboard',     href: 'dashboard.html',   icon: 'layout-dashboard' },
        { key: 'habits',      label: 'HabitSync',     href: 'habits.html',      icon: 'check-circle' },
        { key: 'focus',       label: 'Focus Tracker', href: 'focus.html',       icon: 'timer' },
        { key: 'decisions',   label: 'DecisionLog',   href: 'decisions.html',   icon: 'git-merge' },
        { key: 'skills',      label: 'Skills',        href: 'skills.html',      icon: 'award' },
        { key: 'pathcompare', label: 'Path Compare',  href: 'pathcompare.html', icon: 'scale' },
        { key: 'nextmove',    label: 'Next Move',     href: 'nextmove.html',    icon: 'compass' },
        { key: 'analytics',   label: 'Analytics',     href: 'analytics.html',   icon: 'bar-chart' },
    ];

    const navHTML = navItems.map(p => `
        <a href="${p.href}" class="nav-item ${activePage === p.key ? 'nav-active' : ''}" id="nav-${p.key}">
            <i data-lucide="${p.icon}" class="nav-emoji" style="width:20px;height:20px;stroke-width:1.5;"></i>
            <span class="nav-label">${p.label}</span>
            ${activePage === p.key ? '<span class="nav-dot"></span>' : ''}
        </a>`).join('');

    const sb = document.getElementById('fs-sidebar');
    if (sb) {
        sb.innerHTML = `
            <div class="sidebar-logo">
                <i data-lucide="zap" style="color:var(--p);width:24px;height:24px;"></i>
                <span class="logo-text">FlowStack</span>
            </div>
            <nav class="sidebar-nav">
                <p class="nav-section-label">Main Menu</p>
                ${navHTML}
            </nav>
            <div class="sidebar-user">
                <div class="user-avatar">${FS.escape(initials)}</div>
                <div class="user-info">
                    <div class="user-name">${FS.escape(user.name)}</div>
                    <div class="user-email">${FS.escape(user.email)}</div>
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
    const d    = new Date(dateStr.includes('T') ? dateStr : dateStr + 'T00:00:00');
    const diff = Math.floor((Date.now() - d.getTime()) / 86400000);
    if (diff === 0) return 'Today';
    if (diff === 1) return 'Yesterday';
    if (diff < 7)   return diff + 'd ago';
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

FS.showLoading = function (show) {
    const el = document.getElementById('fs-loading');
    if (el) el.style.display = show ? 'flex' : 'none';
};

// Debug
window.FS = FS;
console.info('[FlowStack] Backend:', FS.BACKEND);
})(window);
