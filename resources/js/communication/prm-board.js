/**
 * prm-board.js
 * Session 3 — PRM Pipeline UI
 * Handles: drag-and-drop, view switching, filters modal, quick actions, search
 */

document.addEventListener('DOMContentLoaded', function () {
    initDragAndDrop();
    initDonutChart('pipelineDonut', window.pipelineLegendData || null);
    closeOnOutsideClick();
});

// ── DRAG AND DROP ────────────────────────────────────────────────────────────

function initDragAndDrop() {
    document.querySelectorAll('.lead-card').forEach(function (card) {
        card.addEventListener('dragstart', function (e) {
            e.dataTransfer.setData('leadId', card.dataset.leadId);
            e.dataTransfer.setData('fromStage', card.dataset.stage);
            card.classList.add('dragging');
        });
        card.addEventListener('dragend', function () {
            card.classList.remove('dragging');
            document.querySelectorAll('.pipeline-col').forEach(function (col) {
                col.classList.remove('drag-over');
            });
        });
    });
}

function onDropLead(event, toStage) {
    event.preventDefault();
    const leadId   = event.dataTransfer.getData('leadId');
    const fromStage = event.dataTransfer.getData('fromStage');

    if (!leadId || fromStage === toStage) {
        event.currentTarget.classList.remove('drag-over');
        return;
    }

    const card = document.querySelector(`.lead-card[data-lead-id="${leadId}"]`);
    if (!card) return;

    const targetCards = document.getElementById('col-cards-' + toStage);
    if (targetCards) {
        card.dataset.stage = toStage;
        targetCards.prepend(card);
    }

    event.currentTarget.classList.remove('drag-over');
    updateColumnCounts();

    // Persist stage change to DB
    fetch('/communication/prm/lead/' + leadId + '/move', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify({ stage: toStage })
    }).catch(function () {
        // Silently ignore network errors — UI already updated optimistically
    });
}

function updateColumnCounts() {
    document.querySelectorAll('.pipeline-col').forEach(function (col) {
        const stage  = col.dataset.stage;
        const count  = col.querySelectorAll('.lead-card').length;
        const badge  = col.querySelector('.col-count');
        if (badge) badge.textContent = count;
    });
}

// ── VIEW SWITCHING ───────────────────────────────────────────────────────────

function switchView(view) {
    const board = document.getElementById('boardView');
    const list  = document.getElementById('listView');
    const btnB  = document.getElementById('btnBoard');
    const btnL  = document.getElementById('btnList');

    if (view === 'board') {
        board.style.display = '';
        list.style.display  = 'none';
        btnB.classList.add('active');
        btnL.classList.remove('active');
    } else {
        board.style.display = 'none';
        list.style.display  = '';
        btnB.classList.remove('active');
        btnL.classList.add('active');
    }
}

// ── FILTERS MODAL ────────────────────────────────────────────────────────────

function openFilters() {
    document.getElementById('filtersModal').style.display = 'flex';
}

function closeFilters() {
    document.getElementById('filtersModal').style.display = 'none';
}

function applyFilters() {
    const stage     = document.getElementById('filterStage')?.value.toLowerCase().replace(/\s+/g, '_');
    const source    = document.getElementById('filterSource')?.value;
    const assigned  = document.getElementById('filterAssigned')?.value;
    const urgency   = document.getElementById('filterUrgency')?.value.toLowerCase();
    const treatment = document.getElementById('filterTreatment')?.value;

    document.querySelectorAll('.lead-card').forEach(function (card) {
        const data = {
            stage:     card.dataset.stage,
            source:    card.dataset.source || '',
            assigned:  card.dataset.assigned || '',
            urgency:   card.dataset.urgency || '',
            treatment: card.dataset.treatment || '',
        };

        const match = (!stage     || data.stage === stage)
                   && (!source    || data.source === source)
                   && (!assigned  || data.assigned === assigned)
                   && (!urgency   || data.urgency === urgency)
                   && (!treatment || data.treatment === treatment);

        card.style.display = match ? '' : 'none';
    });

    closeFilters();
}

function clearFilters() {
    ['filterStage','filterSource','filterAssigned','filterUrgency','filterTreatment'].forEach(function (id) {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    document.querySelectorAll('.lead-card').forEach(function (card) {
        card.style.display = '';
    });
    closeFilters();
}

// ── GLOBAL SEARCH ─────────────────────────────────────────────────────────────

function filterLeadCards(query) {
    const q = query.toLowerCase();
    document.querySelectorAll('.lead-card').forEach(function (card) {
        const name  = card.querySelector('.lc-name')?.textContent.toLowerCase() || '';
        const phone = card.querySelector('.lc-phone')?.textContent.toLowerCase() || '';
        card.style.display = (!q || name.includes(q) || phone.includes(q)) ? '' : 'none';
    });
    document.querySelectorAll('.lead-row').forEach(function (row) {
        const name  = row.querySelector('.td-name')?.textContent.toLowerCase() || '';
        const phone = row.querySelector('.td-phone')?.textContent.toLowerCase() || '';
        row.style.display = (!q || name.includes(q) || phone.includes(q)) ? '' : 'none';
    });
}

// ── LEAD CONTEXT MENU ─────────────────────────────────────────────────────────

function toggleLeadMenu(leadId) {
    const menu = document.getElementById('menu-' + leadId);
    if (!menu) return;
    const isOpen = menu.style.display === 'block';
    document.querySelectorAll('.lead-context-menu').forEach(m => m.style.display = 'none');
    menu.style.display = isOpen ? 'none' : 'block';
}

function closeOnOutsideClick() {
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.lc-icon-btn')) {
            document.querySelectorAll('.lead-context-menu').forEach(m => m.style.display = 'none');
        }
        if (!e.target.closest('.btn-add-group')) {
            const dd = document.getElementById('addDropdown');
            if (dd) dd.style.display = 'none';
        }
    });
}

// ── ADD DROPDOWN ─────────────────────────────────────────────────────────────

function toggleAddMenu() {
    const dd = document.getElementById('addDropdown');
    if (dd) dd.style.display = dd.style.display === 'block' ? 'none' : 'block';
}

// ── LIST VIEW SORT ────────────────────────────────────────────────────────────

let sortDir = {};
function sortTable(field) {
    sortDir[field] = sortDir[field] === 'asc' ? 'desc' : 'asc';
    const tbody  = document.querySelector('.leads-table tbody');
    if (!tbody) return;
    const rows   = Array.from(tbody.querySelectorAll('tr'));
    const colMap = { name: '.td-name', assigned_to: '.td-assigned', followup_date: '.td-date' };
    const sel    = colMap[field];
    rows.sort(function (a, b) {
        const aText = a.querySelector(sel)?.textContent.trim() || '';
        const bText = b.querySelector(sel)?.textContent.trim() || '';
        return sortDir[field] === 'asc'
            ? aText.localeCompare(bText)
            : bText.localeCompare(aText);
    });
    rows.forEach(function (row) { tbody.appendChild(row); });
}

// ── QUICK ACTION STUBS (wired in Session 11) ─────────────────────────────────

function openChangeStage(leadId, currentStage) {
    alert('Change stage for lead #' + leadId + ' — modal coming in Session 11');
}

function openAddNote(leadId) {
    alert('Add note for lead #' + leadId + ' — modal coming in Session 11');
}

function openScheduleFollowup(leadId) {
    alert('Schedule follow-up for lead #' + leadId + ' — coming in Session 11');
}

function openReschedule(leadId) {
    alert('Reschedule follow-up for lead #' + leadId + ' — coming in Session 11');
}

function openQuickAdd() {
    alert('Quick add lead — coming in Session 11');
}

function openConvertToPatient(leadId) {
    alert('Convert lead #' + leadId + ' to patient — coming in Session 11');
}

function markNotReachable(leadId) {
    if (confirm('Mark lead as not reachable?')) {
        alert('Marked as not reachable — will sync in Session 11');
    }
}

function markAsDone(leadId) {
    if (confirm('Mark this follow-up as done?')) {
        alert('Marked as done — will sync in Session 11');
    }
}

function confirmDeleteLead(leadId) {
    if (confirm('Are you sure you want to delete this lead? This cannot be undone.')) {
        alert('Delete lead #' + leadId + ' — wired in Session 11');
    }
}

function confirmStageChange(select, leadId) {
    const newStage = select.value;
    if (confirm('Change pipeline stage to ' + select.options[select.selectedIndex].text + '?')) {
        alert('Stage changed — will persist in Session 11');
    }
}

// ── DONUT CHART ───────────────────────────────────────────────────────────────

function initDonutChart(canvasId, data) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || !data) return;
    const ctx    = canvas.getContext('2d');
    const total  = data.counts.reduce((a, b) => a + b, 0);
    if (total === 0) return;

    const cx = canvas.width / 2;
    const cy = canvas.height / 2;
    const r  = Math.min(cx, cy) - 6;
    const ir = r * 0.55;

    let startAngle = -Math.PI / 2;
    data.counts.forEach(function (count, i) {
        const slice = (count / total) * 2 * Math.PI;
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.arc(cx, cy, r, startAngle, startAngle + slice);
        ctx.closePath();
        ctx.fillStyle = data.colors[i];
        ctx.fill();
        startAngle += slice;
    });

    ctx.beginPath();
    ctx.arc(cx, cy, ir, 0, 2 * Math.PI);
    ctx.fillStyle = getComputedStyle(document.body).getPropertyValue('--color-background-primary') || '#ffffff';
    ctx.fill();
}

// ── CSRF HELPER (for Session 11 AJAX) ────────────────────────────────────────

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}
