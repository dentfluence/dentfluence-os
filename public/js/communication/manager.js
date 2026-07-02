/**
 * Communication Manager — Core JS
 * Handles: refresh, modals (note, follow-up, actions menu), UI state
 */

// ── Refresh queue ──────────────────────────────────────────────────────────
function refreshQueue() {
    const btn = document.querySelector('[onclick="refreshQueue()"]');
    if (btn) {
        btn.textContent = '↻ Refreshing…';
        btn.disabled = true;
    }
    // In Session 11, this will hit the real API endpoint.
    // For now, just reload the page.
    setTimeout(() => window.location.reload(), 600);
}

// ── Note modal ────────────────────────────────────────────────────────────
function openNoteModal(itemId) {
    const existing = document.getElementById('note-modal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'note-modal';
    modal.innerHTML = `
        <div style="
            position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:1000;
            display:flex; align-items:center; justify-content:center; padding:20px;
        " onclick="closeModal('note-modal')">
            <div style="
                background:#fff; border-radius:12px; padding:28px; width:100%; max-width:480px;
                box-shadow:0 20px 60px rgba(0,0,0,0.2); font-family:'DM Sans',sans-serif;
            " onclick="event.stopPropagation()">
                <h3 style="font-size:17px;font-weight:700;margin-bottom:18px;letter-spacing:-0.3px;">
                    Add Note
                </h3>
                <textarea id="note-text" style="
                    width:100%; padding:12px; border:1px solid #E2DFD8; border-radius:8px;
                    font-family:'DM Sans',sans-serif; font-size:14px; resize:vertical;
                    min-height:100px; outline:none; color:#1A1916;
                " placeholder="Write your note here…"></textarea>
                <div style="display:flex;gap:10px;margin-top:16px;">
                    <button onclick="saveNote(${itemId})" style="
                        padding:9px 20px; background:#1A1916; color:#fff; border:none;
                        border-radius:7px; font-family:'DM Sans',sans-serif; font-size:13px;
                        font-weight:600; cursor:pointer;
                    ">Save Note</button>
                    <button onclick="closeModal('note-modal')" style="
                        padding:9px 20px; background:#F0EEE9; color:#1A1916; border:1px solid #E2DFD8;
                        border-radius:7px; font-family:'DM Sans',sans-serif; font-size:13px;
                        font-weight:500; cursor:pointer;
                    ">Cancel</button>
                </div>
            </div>
        </div>`;
    document.body.appendChild(modal);
    setTimeout(() => document.getElementById('note-text').focus(), 50);
}

function saveNote(itemId) {
    const text = document.getElementById('note-text').value.trim();
    if (!text) return;
    // Session 11: POST /api/communication/{id}/note
    console.log('Note for item', itemId, ':', text);
    closeModal('note-modal');
    showToast('Note saved');
}

// ── Follow-up modal ───────────────────────────────────────────────────────
function openFollowUpModal(itemId) {
    const existing = document.getElementById('followup-modal');
    if (existing) existing.remove();

    const now = new Date();
    now.setHours(now.getHours() + 2);
    const defaultDate = now.toISOString().slice(0, 16);

    const modal = document.createElement('div');
    modal.id = 'followup-modal';
    modal.innerHTML = `
        <div style="
            position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:1000;
            display:flex; align-items:center; justify-content:center; padding:20px;
        " onclick="closeModal('followup-modal')">
            <div style="
                background:#fff; border-radius:12px; padding:28px; width:100%; max-width:440px;
                box-shadow:0 20px 60px rgba(0,0,0,0.2); font-family:'DM Sans',sans-serif;
            " onclick="event.stopPropagation()">
                <h3 style="font-size:17px;font-weight:700;margin-bottom:18px;letter-spacing:-0.3px;">
                    Schedule Follow-up
                </h3>
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:11px;font-weight:600;color:#4A4843;
                        text-transform:uppercase;letter-spacing:0.5px;margin-bottom:7px;">
                        Due Date & Time
                    </label>
                    <input type="datetime-local" id="followup-date" value="${defaultDate}" style="
                        width:100%;padding:10px 14px;border:1px solid #E2DFD8;border-radius:7px;
                        font-family:'DM Sans',sans-serif;font-size:14px;outline:none;
                    ">
                </div>
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:11px;font-weight:600;color:#4A4843;
                        text-transform:uppercase;letter-spacing:0.5px;margin-bottom:7px;">
                        Note (optional)
                    </label>
                    <input type="text" id="followup-note" placeholder="e.g. Confirm appointment" style="
                        width:100%;padding:10px 14px;border:1px solid #E2DFD8;border-radius:7px;
                        font-family:'DM Sans',sans-serif;font-size:14px;outline:none;
                    ">
                </div>
                <div style="display:flex;gap:10px;">
                    <button onclick="saveFollowUp(${itemId})" style="
                        padding:9px 20px;background:#1A1916;color:#fff;border:none;
                        border-radius:7px;font-family:'DM Sans',sans-serif;font-size:13px;
                        font-weight:600;cursor:pointer;
                    ">Schedule</button>
                    <button onclick="closeModal('followup-modal')" style="
                        padding:9px 20px;background:#F0EEE9;color:#1A1916;border:1px solid #E2DFD8;
                        border-radius:7px;font-family:'DM Sans',sans-serif;font-size:13px;
                        font-weight:500;cursor:pointer;
                    ">Cancel</button>
                </div>
            </div>
        </div>`;
    document.body.appendChild(modal);
}

function saveFollowUp(itemId) {
    const date = document.getElementById('followup-date').value;
    const note = document.getElementById('followup-note').value;
    // Session 11: POST /api/communication/{id}/followup
    console.log('Follow-up for item', itemId, ':', date, note);
    closeModal('followup-modal');
    showToast('Follow-up scheduled');
}

// ── Actions menu ──────────────────────────────────────────────────────────
function openActionsMenu(btn, itemId) {
    const existing = document.getElementById('actions-menu');
    if (existing) { existing.remove(); return; }

    const rect = btn.getBoundingClientRect();
    const menu = document.createElement('div');
    menu.id = 'actions-menu';
    menu.style.cssText = `
        position:fixed; top:${rect.bottom + 6}px; right:${window.innerWidth - rect.right}px;
        background:#fff; border:1px solid #E2DFD8; border-radius:10px;
        box-shadow:0 8px 24px rgba(0,0,0,0.12); z-index:999;
        font-family:'DM Sans',sans-serif; min-width:200px; overflow:hidden;
    `;

    const actions = [
        { icon: '👤', label: 'Assign Staff',      fn: `assignStaff(${itemId})` },
        { icon: '📋', label: 'Move to PRM',        fn: `moveToPRM(${itemId})` },
        { icon: '🎯', label: 'Create Opportunity', fn: `createOpportunity(${itemId})` },
        { icon: '⚡', label: 'Escalate',           fn: `escalate(${itemId})` },
        { icon: '✅', label: 'Mark Completed',     fn: `markCompleted(${itemId})` },
    ];

    menu.innerHTML = actions.map(a => `
        <div onclick="${a.fn}; document.getElementById('actions-menu').remove();" style="
            padding:10px 16px; display:flex; align-items:center; gap:10px;
            font-size:13px; font-weight:500; cursor:pointer; color:#1A1916;
            transition:background 0.1s;
        " onmouseover="this.style.background='#F0EEE9'" onmouseout="this.style.background=''">
            <span>${a.icon}</span> ${a.label}
        </div>
    `).join('');

    document.body.appendChild(menu);

    // Close on outside click
    setTimeout(() => {
        document.addEventListener('click', function closeMenu(e) {
            if (!menu.contains(e.target)) {
                menu.remove();
                document.removeEventListener('click', closeMenu);
            }
        });
    }, 10);
}

// ── Action handlers (stub — wired in Session 11) ──────────────────────────
function assignStaff(itemId)       { showToast('Assign Staff — coming in Session 11'); }
function moveToPRM(itemId)         { showToast('Move to PRM — coming in Session 11'); }
function createOpportunity(itemId) { showToast('Create Opportunity — coming in Session 11'); }
function escalate(itemId)          { showToast('Escalated — coming in Session 11'); }
function markCompleted(itemId)     {
    const card = document.querySelector(`[data-id="${itemId}"]`);
    if (card) { card.style.opacity = '0.4'; card.style.transition = '0.3s'; }
    showToast('Marked as completed');
}

// ── Utilities ─────────────────────────────────────────────────────────────
function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.remove();
}

function showToast(message) {
    const existing = document.getElementById('cm-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id = 'cm-toast';
    toast.textContent = message;
    toast.style.cssText = `
        position:fixed; bottom:24px; left:50%; transform:translateX(-50%);
        background:#1A1916; color:#fff; padding:10px 20px; border-radius:8px;
        font-family:'DM Sans',sans-serif; font-size:13px; font-weight:500;
        z-index:9999; box-shadow:0 4px 12px rgba(0,0,0,0.3);
        animation: toastIn 0.2s ease;
    `;
    document.head.insertAdjacentHTML('beforeend', `
        <style>
        @keyframes toastIn { from { opacity:0; transform:translateX(-50%) translateY(8px); }
                              to   { opacity:1; transform:translateX(-50%) translateY(0); } }
        </style>`);
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2500);
}

// ── Filter helpers (for filter-bar component) ─────────────────────────────
function applyFilter(param, value) {
    const url = new URL(window.location.href);
    if (value) url.searchParams.set(param, value);
    else url.searchParams.delete(param);
    window.location.href = url.toString();
}

function toggleFilter(param) {
    const url = new URL(window.location.href);
    if (url.searchParams.has(param)) url.searchParams.delete(param);
    else url.searchParams.set(param, '1');
    window.location.href = url.toString();
}
