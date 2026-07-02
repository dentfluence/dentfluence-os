/**
 * lead-drawer.js
 * Session 3 — Lead Detail Page interactions
 * Handles: tab switching, stage change confirmation
 */

document.addEventListener('DOMContentLoaded', function () {
    initLdTabs();
    initStageSelector();
});

// ── TAB SWITCHING ─────────────────────────────────────────────────────────────

function initLdTabs() {
    const tabs = document.querySelectorAll('.ld-tab');
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
        });
    });
}

function switchLdTab(tabKey, btn) {
    document.querySelectorAll('.ld-tab-panel').forEach(function (panel) {
        panel.style.display = 'none';
    });
    document.querySelectorAll('.ld-tab').forEach(function (t) {
        t.classList.remove('active');
    });
    const target = document.getElementById('tab-' + tabKey);
    if (target) target.style.display = '';
    if (btn) btn.classList.add('active');
}

// ── STAGE SELECTOR ────────────────────────────────────────────────────────────

function initStageSelector() {
    const sel = document.querySelector('.stage-selector');
    if (!sel) return;
    sel.addEventListener('change', function () {
        const newStage  = this.value;
        const leadId    = this.dataset.leadId || sel.name;
        const stageName = this.options[this.selectedIndex].text;
        if (!confirm('Move lead to "' + stageName + '"?')) {
            this.value = this.dataset.original || this.value;
            return;
        }
        // Session 11: AJAX call here
        console.log('Stage change to', newStage, 'for lead', leadId);
    });
    if (sel) sel.dataset.original = sel.value;
}
