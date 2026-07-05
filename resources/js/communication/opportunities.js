// resources/js/communication/opportunities.js
// Opportunity Engine — wired with real DB routes

document.addEventListener('DOMContentLoaded', function () {
    initDragDrop();
    initPatientSearch();
    initConvertButton();
});

// ── View switching ─────────────────────────────────────────────────────────────

function switchView(view) {
    const boardEl  = document.getElementById('view-board');
    const listEl   = document.getElementById('view-list');
    const btnBoard = document.getElementById('btn-board');
    const btnList  = document.getElementById('btn-list');

    if (view === 'board') {
        boardEl.style.display = 'flex';
        listEl.style.display  = 'none';
        btnBoard.classList.add('active');
        btnList.classList.remove('active');
    } else {
        boardEl.style.display = 'none';
        listEl.style.display  = 'block';
        btnBoard.classList.remove('active');
        btnList.classList.add('active');
    }
}

// ── Drag-and-Drop with AJAX stage save ────────────────────────────────────────

function initDragDrop() {
    const cards   = document.querySelectorAll('.opp-card');
    const columns = document.querySelectorAll('.opp-col-body');

    cards.forEach(card => {
        card.addEventListener('dragstart', e => {
            card.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', card.dataset.id);
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
            document.querySelectorAll('.opp-col-body').forEach(col => col.classList.remove('drag-over'));
        });
    });

    columns.forEach(col => {
        col.addEventListener('dragover', e => {
            e.preventDefault();
            col.classList.add('drag-over');
            const dragging = document.querySelector('.dragging');
            const addBtn   = col.querySelector('.opp-add-card-btn');
            if (dragging && addBtn) col.insertBefore(dragging, addBtn);
        });
        col.addEventListener('dragleave', () => col.classList.remove('drag-over'));
        col.addEventListener('drop', e => {
            e.preventDefault();
            col.classList.remove('drag-over');

            const oppId    = e.dataTransfer.getData('text/plain');
            const newStage = col.closest('.opp-column')?.dataset.stage;
            if (oppId && newStage) {
                saveStage(oppId, newStage);
            }
            updateColumnCounts();
        });
    });
}

function saveStage(oppId, newStatus) {
    const routes = window.oppRoutes || {};
    fetch(`${routes.base}/${oppId}/stage`, {
        method:  'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': routes.csrfToken,
        },
        body: JSON.stringify({ status: newStatus }),
    })
    .then(r => r.json())
    .then(d => {
        if (!d.success) console.error('Stage save failed', d);
        // Update card data attribute
        const card = document.querySelector(`.opp-card[data-id="${oppId}"]`);
        if (card) card.dataset.status = newStatus;
    })
    .catch(err => console.error('Stage PATCH error', err));
}

function updateColumnCounts() {
    document.querySelectorAll('.opp-column').forEach(col => {
        const count = col.querySelectorAll('.opp-card').length;
        const badge = col.querySelector('.opp-col-badge');
        if (badge) badge.textContent = count;
    });
}

// ── Add Opportunity Modal ──────────────────────────────────────────────────────

function openAddOpportunityModal(stage = null) {
    document.getElementById('add-opp-modal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeAddOpportunityModal(event) {
    if (event && event.target !== document.getElementById('add-opp-modal')) return;
    document.getElementById('add-opp-modal').style.display = 'none';
    document.body.style.overflow = '';
}

// ── Patient Autocomplete ───────────────────────────────────────────────────────

function initPatientSearch() {
    const input     = document.getElementById('patient-search-input');
    const hiddenId  = document.getElementById('patient-id-input');
    const results   = document.getElementById('patient-search-results');
    if (!input) return;

    let debounceTimer;

    input.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const q = this.value.trim();
        hiddenId.value = '';  // clear selection on edit

        if (q.length < 2) { results.style.display = 'none'; return; }

        debounceTimer = setTimeout(() => {
            const routes = window.oppRoutes || {};
            fetch(`${routes.patientSearch}?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(patients => {
                    results.innerHTML = '';
                    if (!patients.length) {
                        results.innerHTML = '<div style="padding:10px 14px;font-size:13px;color:#9ca3af">No patients found</div>';
                    } else {
                        patients.forEach(p => {
                            const div = document.createElement('div');
                            div.style.cssText = 'padding:10px 14px;cursor:pointer;font-size:13px;color:#111827;border-bottom:1px solid #f3f4f6';
                            div.innerHTML = `<strong>${p.name}</strong> <span style="color:#9ca3af">${p.phone}</span>`;
                            div.addEventListener('click', () => {
                                input.value    = `${p.name} — ${p.phone}`;
                                hiddenId.value = p.id;
                                results.style.display = 'none';
                            });
                            div.addEventListener('mouseover', () => div.style.background = '#f9fafb');
                            div.addEventListener('mouseout',  () => div.style.background = '');
                            results.appendChild(div);
                        });
                    }
                    results.style.display = 'block';
                })
                .catch(() => { results.style.display = 'none'; });
        }, 300);
    });

    // Close dropdown on outside click
    document.addEventListener('click', e => {
        if (!input.contains(e.target) && !results.contains(e.target)) {
            results.style.display = 'none';
        }
    });
}

// ── Convert to PRM Modal ──────────────────────────────────────────────────────

function openConvertModal(id, name, treatment) {
    window._convertOppId = id;
    const initials = (name || '').split(' ').map(w => (w[0] || '').toUpperCase()).join('').slice(0, 2);
    document.getElementById('convert-avatar').textContent    = initials || '—';
    document.getElementById('convert-name').textContent      = name || '—';
    document.getElementById('convert-treatment').textContent = treatment || '—';
    document.getElementById('convert-prm-modal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeConvertModal(event) {
    if (event && event.target !== document.getElementById('convert-prm-modal')) return;
    document.getElementById('convert-prm-modal').style.display = 'none';
    document.body.style.overflow = '';
}

function initConvertButton() {
    const btn = document.getElementById('btn-do-convert');
    if (!btn) return;

    btn.addEventListener('click', function () {
        const id    = window._convertOppId;
        const stage = document.getElementById('convert-stage')?.value || 'new';
        const routes = window.oppRoutes || {};

        btn.disabled    = true;
        btn.textContent = 'Converting…';

        fetch(`${routes.base}/${id}/convert`, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': routes.csrfToken,
            },
            body: JSON.stringify({ stage }),
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                closeConvertModal();
                closeOpportunityDetailModal();
                // Remove converted card from board and reload stats
                const card = document.querySelector(`.opp-card[data-id="${id}"]`);
                if (card) card.remove();
                updateColumnCounts();
                showToast('Converted to PRM lead successfully.');
            } else {
                alert('Conversion failed. Please try again.');
                btn.disabled    = false;
                btn.textContent = 'Convert to Lead';
            }
        })
        .catch(() => {
            alert('Network error. Please try again.');
            btn.disabled    = false;
            btn.textContent = 'Convert to Lead';
        });
    });
}

// ── Opportunity Detail Modal (board/list click-to-open, replaces full-page nav) ─

function openOpportunityDetailModal(id) {
    const modal = document.getElementById('opp-detail-modal');
    const body  = document.getElementById('opp-detail-modal-body');
    if (!modal || !body) return;

    window._detailOppId = id;
    body.innerHTML = '<div style="padding:48px 24px;text-align:center;color:#9ca3af;font-size:13px">Loading...</div>';
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    const routes = window.oppRoutes || {};
    fetch(`${routes.base}/${id}/modal`)
        .then(r => r.text())
        .then(html => { body.innerHTML = html; })
        .catch(() => {
            body.innerHTML = '<div style="padding:48px 24px;text-align:center;color:#e74c3c;font-size:13px">Could not load this opportunity. Please try again.</div>';
        });
}

function closeOpportunityDetailModal(event) {
    const modal = document.getElementById('opp-detail-modal');
    if (!modal) return;
    if (event && event.target !== modal) return;
    modal.style.display = 'none';
    document.body.style.overflow = '';
}

// ── Stage move — shared by board quick-actions and the detail modal ───────────

function moveStage(id, newStatus) {
    const routes = window.oppRoutes || {};
    fetch(`${routes.base}/${id}/stage`, {
        method:  'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': routes.csrfToken,
        },
        body: JSON.stringify({ status: newStatus }),
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) window.location.reload();
        else alert('Failed to update stage.');
    })
    .catch(() => alert('Network error. Please try again.'));
}

// ── Misc helpers ───────────────────────────────────────────────────────────────

function toggleCardMenu(btn) {
    // Future: context menu
}

function showToast(msg) {
    const t = document.createElement('div');
    t.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#10b981;color:#fff;padding:12px 20px;border-radius:8px;font-size:14px;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,.15)';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

// ESC closes modals
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeAddOpportunityModal();
        closeConvertModal();
        closeOpportunityDetailModal();
    }
});
