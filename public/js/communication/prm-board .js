/**
 * prm-board.js — PRM Kanban Board Interactions
 * Handles: drag-drop, lead drawer, modals, quick actions
 */

const PrmBoard = (function () {

    // ── State ──────────────────────────────────────────────────────
    let draggingId   = null;
    let draggingCard = null;

    // ── Init ───────────────────────────────────────────────────────
    function init() {
        initDragDrop();
        initLeadCards();
        initAddLeadBtn();
        initDrawerClose();
        initDonutChart();
    }

    // ── Drag & Drop ────────────────────────────────────────────────
    function initDragDrop() {
        document.querySelectorAll('.prm-lead-card').forEach(card => {
            card.setAttribute('draggable', 'true');
            card.addEventListener('dragstart', onDragStart);
            card.addEventListener('dragend',   onDragEnd);
        });
    }

    function onDragStart(e) {
        draggingId   = this.dataset.leadId;
        draggingCard = this;
        setTimeout(() => this.classList.add('prm-lead-card--dragging'), 0);
        e.dataTransfer.effectAllowed = 'move';
    }

    function onDragEnd() {
        this.classList.remove('prm-lead-card--dragging');
        document.querySelectorAll('.prm-column--drag-over')
                .forEach(col => col.classList.remove('prm-column--drag-over'));
        draggingId   = null;
        draggingCard = null;
    }

    function onDragOver(col) {
        col.classList.add('prm-column--drag-over');
    }

    function onDragLeave(col) {
        col.classList.remove('prm-column--drag-over');
    }

    function onDrop(e, stageId) {
        e.preventDefault();
        const col = document.getElementById('column-' + stageId);
        if (col) col.classList.remove('prm-column--drag-over');
        if (!draggingId || !draggingCard) return;

        // Move card in DOM
        const cardsContainer = document.getElementById('cards-' + stageId);
        if (cardsContainer && draggingCard) {
            cardsContainer.appendChild(draggingCard);
        }

        // Update count badges
        updateColumnCounts();

        // POST to server (no-op until Session 11)
        const url = (window.PRM_CONFIG?.moveStageUrl || '').replace('__ID__', draggingId);
        if (url) {
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.PRM_CONFIG?.csrfToken || '',
                },
                body: JSON.stringify({ stage: stageId }),
            }).catch(() => {});
        }

        showToast('Lead moved to ' + stageId.replace(/_/g, ' '), 'success');
    }

    function updateColumnCounts() {
        document.querySelectorAll('.prm-column').forEach(col => {
            const count   = col.querySelectorAll('.prm-lead-card').length;
            const badge   = col.querySelector('.prm-column__count');
            if (badge) badge.textContent = count;
        });
    }

    // ── Lead Cards — click to open drawer ─────────────────────────
    function initLeadCards() {
        document.querySelectorAll('.prm-lead-card').forEach(card => {
            card.addEventListener('click', function (e) {
                if (e.target.closest('.prm-lead-card__menu-btn') ||
                    e.target.closest('.prm-card-menu')) return;
                const leadId = this.dataset.leadId;
                openDrawer(leadId);
            });
        });

        // Context menu buttons
        document.querySelectorAll('.prm-lead-card__menu-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                const menu = this.nextElementSibling;
                closeAllMenus();
                if (menu) menu.classList.toggle('prm-card-menu--open');
            });
        });

        document.addEventListener('click', closeAllMenus);
    }

    function closeAllMenus() {
        document.querySelectorAll('.prm-card-menu--open')
                .forEach(m => m.classList.remove('prm-card-menu--open'));
    }

    // ── Lead Drawer ────────────────────────────────────────────────
    function openDrawer(leadId) {
        const overlay = document.getElementById('drawerOverlay');
        const drawer  = document.getElementById('leadDrawerContainer');
        if (!overlay || !drawer) return;

        // Show loading state
        drawer.innerHTML = '<div class="lead-drawer__loading"><i class="ti ti-loader-2"></i> Loading...</div>';
        overlay.classList.add('prm-overlay--open');
        drawer.classList.add('lead-drawer--open');

        // Fetch drawer content
        const url = (window.PRM_CONFIG?.leadDetailUrl || '').replace('__ID__', leadId);
        if (!url) return;

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                drawer.innerHTML = html;
                initDrawerTabs();
            })
            .catch(() => {
                drawer.innerHTML = '<div class="lead-drawer__loading">Failed to load lead details.</div>';
            });
    }

    function closeDrawer() {
        const overlay = document.getElementById('drawerOverlay');
        const drawer  = document.getElementById('leadDrawerContainer');
        if (overlay) overlay.classList.remove('prm-overlay--open');
        if (drawer)  drawer.classList.remove('lead-drawer--open');
    }

    function initDrawerClose() {
        const overlay = document.getElementById('drawerOverlay');
        if (overlay) {
            overlay.addEventListener('click', function (e) {
                if (e.target === this) closeDrawer();
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeDrawer();
        });
    }

    function initDrawerTabs() {
        document.querySelectorAll('.lead-drawer__tab').forEach(tab => {
            tab.addEventListener('click', function () {
                document.querySelectorAll('.lead-drawer__tab')
                        .forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.lead-drawer__tab-content')
                        .forEach(c => c.style.display = 'none');
                this.classList.add('active');
                const target = document.getElementById('drawerTab-' + this.dataset.tab);
                if (target) target.style.display = 'block';
            });
        });
    }

    // ── Add Lead Button ────────────────────────────────────────────
    function initAddLeadBtn() {
        const btn = document.getElementById('addLeadBtn');
        if (btn) {
            btn.addEventListener('click', function () {
                window.location.href = '{{ route("communication.prm.add-lead") }}' ||
                    '/communication/prm/leads/add';
            });
        }

        // Column-level add lead buttons
        document.querySelectorAll('[data-action="open-add-lead"]').forEach(btn => {
            btn.addEventListener('click', function () {
                const stage = this.dataset.stage || '';
                window.location.href = '/communication/prm/leads/add?stage=' + stage;
            });
        });
    }

    // ── Quick Actions ──────────────────────────────────────────────
    function openReschedule(leadId) {
        showToast('Reschedule — coming in Session 4', 'info');
    }

    function openAddNote(leadId) {
        showToast('Add Note — coming in Session 5', 'info');
    }

    function openMoreActions(leadId) {
        showToast('More actions — coming soon', 'info');
    }

    function markUnreachable(leadId) {
        showToast('Marked as not reachable', 'success');
        closeDrawer();
    }

    function markDone(leadId) {
        showToast('Marked as done', 'success');
        closeDrawer();
    }

    function openConvertToPatient(leadId) {
        showToast('Convert to Patient — coming in Session 11', 'info');
    }

    // ── Donut Chart ────────────────────────────────────────────────
    function initDonutChart() {
        const canvas = document.getElementById('pipelineDonut');
        if (!canvas || !window.PRM_CONFIG?.stages) return;

        const stages = window.PRM_CONFIG.stages;
        const ctx    = canvas.getContext('2d');
        const size   = canvas.width;
        const cx     = size / 2;
        const cy     = size / 2;
        const r      = 38;
        const thick  = 14;

        // Count leads per stage from DOM
        const data = stages.map(s => ({
            color: s.color,
            count: document.getElementById('cards-' + s.id)?.querySelectorAll('.prm-lead-card').length || 0,
        }));

        const total = data.reduce((a, b) => a + b.count, 0) || 1;
        let angle   = -Math.PI / 2;

        data.forEach(seg => {
            const slice = (seg.count / total) * 2 * Math.PI;
            ctx.beginPath();
            ctx.arc(cx, cy, r, angle, angle + slice);
            ctx.strokeStyle = seg.color;
            ctx.lineWidth   = thick;
            ctx.stroke();
            angle += slice;
        });
    }

    // ── Toast ──────────────────────────────────────────────────────
    function showToast(msg, type = 'success') {
        let toast = document.getElementById('prmToast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'prmToast';
            toast.className = 'prm-toast';
            document.body.appendChild(toast);
        }
        toast.className = 'prm-toast prm-toast--' + type;
        toast.innerHTML = '<i class="ti ti-' + (type === 'success' ? 'circle-check' : 'info-circle') + '"></i> ' + msg;
        requestAnimationFrame(() => toast.classList.add('prm-toast--visible'));
        setTimeout(() => toast.classList.remove('prm-toast--visible'), 3000);
    }

    // ── Public API ─────────────────────────────────────────────────
    return {
        init,
        onDragOver,
        onDragLeave,
        onDrop,
        closeDrawer,
        openReschedule,
        openAddNote,
        openMoreActions,
        markUnreachable,
        markDone,
        openConvertToPatient,
        showToast,
    };

})();

document.addEventListener('DOMContentLoaded', PrmBoard.init);
