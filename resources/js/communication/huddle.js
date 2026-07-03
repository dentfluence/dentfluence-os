/**
 * resources/js/communication/huddle.js
 * Communication widgets embedded in Daily Huddle
 */

/* ── Alert card actions ─────────────────────────────────────────
   Phase 8 PRM Retirement (Slice 5): PRM board retired, all alert types
   now land on PRE's lead pipeline. It doesn't parse ?filter= yet, so the
   pre-filtered view is lost for now — lands on the full board instead. */
window.commHuddleAlertAction = function (type) {
    window.location.href = '/relationship/pipeline';
};

/* ── Quick call from overdue list ───────────────────────────── */
window.commHuddleCall = function (phone, name) {
    // For now: open call manager. Later: trigger dialer.
    const clean = phone.replace(/\s/g, '');
    if (window.confirm(`Call ${name} at ${phone}?`)) {
        window.location.href = `/communication/call-manager?dial=${clean}`;
    }
};

/* ── Auto-refresh counts every 5 minutes via AJAX ──────────── */
(function initCommHuddleRefresh () {
    const INTERVAL = 5 * 60 * 1000; // 5 minutes

    function refreshCounts () {
        fetch('/communication/huddle/counts', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            // Update summary bar numbers
            updateStat('.comm-summary-stat__num--red',    data.overdue_callbacks);
            updateStat('.comm-summary-stat__num--blue',   data.pending_today);
            updateStat('.comm-summary-stat__num--purple', data.ongoing_treatments);
            updateStat('.comm-summary-stat__num--green',  data.long_term_followups);
            updateStat('.comm-summary-stat__num--orange', data.pending_estimates);

            // Update overdue badge
            const badge = document.querySelector('.comm-overdue-summary__badge');
            if (badge) badge.textContent = data.overdue_callbacks;
        })
        .catch(() => {
            // Silently fail — stale counts are fine during Daily Huddle
        });
    }

    function updateStat (selector, value) {
        const el = document.querySelector(selector);
        if (el && value !== undefined) {
            el.textContent = value;
        }
    }

    // Only start polling if the widgets are present on the page
    if (document.getElementById('commHuddleAlerts') ||
        document.getElementById('commOverdueSummary')) {
        setTimeout(refreshCounts, INTERVAL);
        setInterval(refreshCounts, INTERVAL);
    }
}());

/* ── Animate counts on load ─────────────────────────────────── */
(function animateCountsOnLoad () {
    function animateNumber (el, target, duration) {
        const start     = 0;
        const startTime = performance.now();

        function step (now) {
            const progress = Math.min((now - startTime) / duration, 1);
            const val      = Math.round(progress * target);
            el.textContent = val;
            if (progress < 1) requestAnimationFrame(step);
        }

        requestAnimationFrame(step);
    }

    document.querySelectorAll('.comm-alert-card__count').forEach(el => {
        const target = parseInt(el.textContent, 10);
        if (!isNaN(target) && target > 0) {
            animateNumber(el, target, 600);
        }
    });

    document.querySelectorAll('.comm-summary-stat__num').forEach(el => {
        const target = parseInt(el.textContent, 10);
        if (!isNaN(target) && target > 0) {
            animateNumber(el, target, 800);
        }
    });
}());
