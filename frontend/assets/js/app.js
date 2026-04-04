/**
 * FlowStack — frontend/assets/js/app.js
 * Core frontend engine. Included by every page.
 *
 * All API calls go to /FlowStack/backend/
 * FS.BACKEND is computed from window.location so it works regardless of server path.
 */
;(function (window) {
    'use strict';

    const FS = {};

    // ── Compute backend base URL ────────────────────────────────
    // Works at: http://localhost/FlowStack/frontend/any-page.html
    // Produces: http://localhost/FlowStack/backend
    FS.BACKEND = (function () {
        // pathname e.g. /FlowStack/frontend/habits.html
        const parts = window.location.pathname.split('/').filter(Boolean);
        // Find the project root segment ('FlowStack')
        const idx = parts.findIndex(p => p.toLowerCase() === 'flowstack');
        if (idx !== -1) {
            return window.location.origin + '/' + parts.slice(0, idx + 1).join('/') + '/backend';
        }
        // Fallback: climb two levels from current page
        return window.location.origin + '/FlowStack/backend';
    })();

    // ── fetch() wrapper ─────────────────────────────────────────
    FS.api = async function (endpoint, method, body) {
        method = (method || 'GET').toUpperCase();
        const opts = {
            method,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'include',   // send session cookie cross-path
        };
        if (body && method !== 'GET') opts.body = JSON.stringify(body);

        try {
            const res  = await fetch(FS.BACKEND + endpoint, opts);
            const json = await res.json().catch(() => ({}));
            if (res.status === 401) {
                window.location.replace(FS.loginUrl());
                return { ok: false, status: 401, data: json };
            }
            return { ok: res.ok, status: res.status, data: json };
        } catch (err) {
            console.error('[FS] fetch error:', err);
            return { ok: false, status: 0, data: { error: 'Network error — is XAMPP running?' } };
        }
    };

    // ── Auth guard ──────────────────────────────────────────────
    FS.requireAuth = async function () {
        try {
            const res  = await fetch(FS.BACKEND + '/auth/check.php', {
                credentials: 'include',
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.authenticated) {
                window.location.replace(FS.loginUrl());
                return null;
            }
            return data.user;
        } catch (e) {
            console.error('[FS] auth check failed', e);
            window.location.replace(FS.loginUrl());
            return null;
        }
    };

    // ── Login URL ───────────────────────────────────────────────
    FS.loginUrl = function () {
        const parts = window.location.pathname.split('/').filter(Boolean);
        const idx   = parts.findIndex(p => p.toLowerCase() === 'flowstack');
        const base  = idx !== -1
            ? window.location.origin + '/' + parts.slice(0, idx + 1).join('/')
            : window.location.origin + '/FlowStack';
        return base + '/frontend/login.html';
    };

    // ── Logout ──────────────────────────────────────────────────
    FS.logout = async function () {
        await fetch(FS.BACKEND + '/auth/logout.php', { method: 'POST', credentials: 'include' });
        window.location.replace(FS.loginUrl());
    };

    // ── Sidebar + Header injector ───────────────────────────────
    FS.initLayout = function (user, activePage) {
        if (!user) return;

        const initials = (user.name || '')
            .split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) || '?';

        const pages = [
            { key:'dashboard',   label:'Dashboard',     href:'dashboard.html',   icon:'<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>' },
            { key:'habits',      label:'HabitSync',     href:'habits.html',      icon:'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>' },
            { key:'focus',       label:'Focus Tracker', href:'focus.html',       icon:'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' },
            { key:'decisions',   label:'DecisionLog',   href:'decisions.html',   icon:'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>' },
            { key:'skills',      label:'Skills',        href:'skills.html',      icon:'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>' },
            { key:'pathcompare', label:'Path Compare',  href:'pathcompare.html', icon:'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>' },
            { key:'nextmove',    label:'Next Move',     href:'nextmove.html',    icon:'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>' },
            { key:'analytics',   label:'Analytics',     href:'analytics.html',   icon:'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>' },
        ];

        const navHTML = pages.map(p => `
            <a href="${p.href}" class="nav-item ${activePage === p.key ? 'nav-active' : ''}">
                <span class="nav-icon">${p.icon}</span><span>${p.label}</span>
            </a>`).join('');

        const sb = document.getElementById('fs-sidebar');
        if (sb) {
            sb.innerHTML = `
                <div class="sidebar-logo">⚡ FlowStack</div>
                <nav class="sidebar-nav">${navHTML}</nav>
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
                <button class="hamburger" id="fs-burger" aria-label="Toggle nav"><span></span><span></span><span></span></button>
                <span class="header-logo">⚡ FlowStack</span>
                <div class="header-right">
                    <div class="user-avatar sm">${FS.escape(initials)}</div>
                    <span class="header-name">${FS.escape(user.name)}</span>
                    <button class="btn-signout" id="fs-logout">Sign out</button>
                </div>`;

            document.getElementById('fs-logout').addEventListener('click', FS.logout);

            const burger  = document.getElementById('fs-burger');
            const overlay = document.getElementById('fs-overlay');
            const sidebar = document.getElementById('fs-sidebar');
            const open  = () => { sidebar.classList.add('sidebar-open');    overlay.classList.add('visible');    burger.setAttribute('aria-expanded','true');  document.body.style.overflow='hidden'; };
            const close = () => { sidebar.classList.remove('sidebar-open'); overlay.classList.remove('visible'); burger.setAttribute('aria-expanded','false'); document.body.style.overflow=''; };
            burger.addEventListener('click', () => sidebar.classList.contains('sidebar-open') ? close() : open());
            overlay.addEventListener('click', close);
            window.addEventListener('resize', () => { if (window.innerWidth > 768) close(); });
        }
    };

    // ── Toast ────────────────────────────────────────────────────
    FS.toast = function (msg, type) {
        type = type || 'success';
        const el = document.createElement('div');
        el.className = 'fs-toast fs-toast-' + type;
        el.setAttribute('role', 'alert');
        el.innerHTML = `<span>${FS.escape(msg)}</span><button onclick="this.parentElement.remove()">✕</button>`;
        document.body.appendChild(el);
        requestAnimationFrame(() => el.classList.add('show'));
        setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 350); }, 4000);
    };

    // ── Helpers ──────────────────────────────────────────────────
    FS.escape = function (str) {
        const m = { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":"&#39;" };
        return String(str || '').replace(/[&<>"']/g, c => m[c]);
    };

    FS.relDate = function (dateStr) {
        const d    = new Date(dateStr);
        const diff = Math.floor((Date.now() - d) / 86400000);
        if (diff === 0) return 'Today';
        if (diff === 1) return 'Yesterday';
        if (diff < 7)   return diff + 'd ago';
        return d.toLocaleDateString('en-US', { month:'short', day:'numeric' });
    };

    FS.showLoading = function (state) {
        const el = document.getElementById('fs-loading');
        if (el) el.style.display = state ? 'flex' : 'none';
    };

    window.FS = FS;

    // Debug: log computed backend URL
    console.log('[FlowStack] BACKEND =', FS.BACKEND);

})(window);
