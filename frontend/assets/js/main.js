/**
 * FlowStack — Main JS
 * /assets/js/main.js
 * Sidebar toggle, mobile menu, global UI behaviors
 */

(function () {
    'use strict';

    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const burger  = document.getElementById('hamburger-btn');

    // ── Sidebar toggle (mobile) ─────────────────────────────
    function openSidebar() {
        sidebar?.classList.add('sidebar-open');
        overlay?.classList.add('visible');
        burger?.classList.add('is-active');
        burger?.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar?.classList.remove('sidebar-open');
        overlay?.classList.remove('visible');
        burger?.classList.remove('is-active');
        burger?.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    burger?.addEventListener('click', () => {
        const isOpen = sidebar?.classList.contains('sidebar-open');
        isOpen ? closeSidebar() : openSidebar();
    });

    overlay?.addEventListener('click', closeSidebar);

    // Close sidebar on nav-item click (mobile)
    sidebar?.querySelectorAll('.nav-item').forEach(el => {
        el.addEventListener('click', () => {
            if (window.innerWidth <= 768) closeSidebar();
        });
    });

    // Close on resize past breakpoint
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) closeSidebar();
    });

    // ── Auto-dismiss alerts ─────────────────────────────────
    document.querySelectorAll('.alert').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity 400ms ease, max-height 400ms ease';
            el.style.opacity    = '0';
            el.style.maxHeight  = '0';
            el.style.overflow   = 'hidden';
            el.style.marginBottom = '0';
            setTimeout(() => el.remove(), 450);
        }, 5000);
    });

    // ── Confirm dialogs ─────────────────────────────────────
    // Handled inline via onclick="return confirm()" — no override needed

    // ── Active nav highlight fallback ───────────────────────
    // Already handled by PHP $currentPage, but this adds a JS fallback
    // for any dynamically‑loaded partial.
    const path = window.location.pathname;
    sidebar?.querySelectorAll('.nav-item').forEach(link => {
        if (link.getAttribute('href') && path.includes(link.getAttribute('href').split('/').pop().replace('.php', ''))) {
            link.classList.add('nav-active');
        }
    });

})();
