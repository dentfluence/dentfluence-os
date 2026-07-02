/**
 * Communication OS — Navigation JS
 * Dentfluence · Tulip Dental · Session 1
 *
 * Handles:
 * - Sidebar collapse/expand
 * - Mobile sidebar toggle
 * - Active nav state persistence
 * - Outside-click close on mobile
 */

(function () {
    'use strict';

    // ── Element refs ────────────────────────────────────────────────
    const sidebar         = document.getElementById('comm-sidebar');
    const collapseBtn     = document.getElementById('sidebar-collapse-btn');
    const mobileToggle    = document.getElementById('sidebar-mobile-toggle');
    const STORAGE_KEY     = 'comm_sidebar_collapsed';

    if (!sidebar) return;

    // ── Sidebar collapse (desktop) ───────────────────────────────────
    function setSidebarCollapsed(collapsed) {
        sidebar.classList.toggle('is-collapsed', collapsed);
        if (collapseBtn) {
            collapseBtn.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        }
        try {
            localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
        } catch (_) {}
    }

    // Restore state on page load
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored === '1') setSidebarCollapsed(true);
    } catch (_) {}

    if (collapseBtn) {
        collapseBtn.addEventListener('click', function () {
            const isCollapsed = sidebar.classList.contains('is-collapsed');
            setSidebarCollapsed(!isCollapsed);
        });
    }

    // ── Mobile sidebar toggle ────────────────────────────────────────
    function openMobileSidebar() {
        sidebar.classList.add('is-open');
        if (mobileToggle) mobileToggle.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closeMobileSidebar() {
        sidebar.classList.remove('is-open');
        if (mobileToggle) mobileToggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    if (mobileToggle) {
        mobileToggle.addEventListener('click', function () {
            const isOpen = sidebar.classList.contains('is-open');
            isOpen ? closeMobileSidebar() : openMobileSidebar();
        });
    }

    // Close on outside click (mobile)
    document.addEventListener('click', function (e) {
        if (
            sidebar.classList.contains('is-open') &&
            !sidebar.contains(e.target) &&
            e.target !== mobileToggle
        ) {
            closeMobileSidebar();
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar.classList.contains('is-open')) {
            closeMobileSidebar();
            if (mobileToggle) mobileToggle.focus();
        }
    });

    // ── Highlight active nav on navigation ──────────────────────────
    // Active state is set server-side via $activeNav,
    // but this ensures correct state after back/forward navigation.
    const currentPath = window.location.pathname;
    document.querySelectorAll('.comm-sidebar__nav-link').forEach(function (link) {
        const href = link.getAttribute('href');
        if (href && href !== '#' && currentPath.startsWith(href) && href !== '/communication') {
            link.classList.add('is-active');
            link.setAttribute('aria-current', 'page');
        }
    });

})();
