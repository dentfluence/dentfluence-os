/**
 * cms-case-viewer.js
 * Handles the slide-in case viewer panel.
 */
(function () {
    'use strict';

    // ── Open case viewer ──────────────────────────────────────
    window.openCaseViewer = function (id) {
        var overlay = document.getElementById('cms-case-overlay');
        var panel   = document.getElementById('cms-case-panel');
        var content = document.getElementById('cms-case-content');

        if (!panel || !content) return;

        // Show loading skeleton
        content.innerHTML = '<div style="padding:24px;">'
            + '<div class="df-skeleton" style="height:80px;border-radius:8px;margin-bottom:16px;"></div>'
            + '<div class="df-skeleton" style="height:20px;width:60%;border-radius:4px;margin-bottom:10px;"></div>'
            + '<div class="df-skeleton" style="height:20px;width:40%;border-radius:4px;margin-bottom:20px;"></div>'
            + '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">'
            + '<div class="df-skeleton" style="aspect-ratio:1;border-radius:6px;"></div>'
            + '<div class="df-skeleton" style="aspect-ratio:1;border-radius:6px;"></div>'
            + '<div class="df-skeleton" style="aspect-ratio:1;border-radius:6px;"></div>'
            + '</div></div>';

        // Slide in
        if (overlay) { overlay.style.display = 'block'; }
        panel.style.right = '0';
        document.body.style.overflow = 'hidden';

        // Fetch case data
        fetch('/content-management/case/' + id, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept':           'application/json',
                'X-CSRF-TOKEN':     CMS.csrfToken(),
            },
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            content.innerHTML = data.html || '<div style="padding:24px;color:#9ca3af;">Could not load case.</div>';
        })
        .catch(function () {
            content.innerHTML = '<div style="padding:24px;color:#dc2626;font-size:13px;">Failed to load case viewer. Please try again.</div>';
        });
    };

    // ── Close case viewer ─────────────────────────────────────
    window.closeCaseViewer = function () {
        var overlay = document.getElementById('cms-case-overlay');
        var panel   = document.getElementById('cms-case-panel');

        if (panel)   panel.style.right = '-680px';
        if (overlay) {
            setTimeout(function () {
                overlay.style.display = 'none';
            }, 300);
        }
        document.body.style.overflow = '';
    };

    // ── Keyboard: Escape closes panel ────────────────────────
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var panel = document.getElementById('cms-case-panel');
            if (panel && panel.style.right === '0px') {
                closeCaseViewer();
            }
        }
    });

})();
