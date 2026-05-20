/**
 * lead-drawer.js
 * Controls the lead detail drawer (right-side panel) and all sub-modals:
 *   – Change Status
 *   – Convert to Patient
 *   – Stage Selector
 */

const LeadDrawer = (() => {

    const OVERLAY_ID = 'drawerOverlay';
    const AJAX_TARGET_ID = 'drawerAjaxTarget';

    let _currentLeadId = null;

    // ── Drawer open / close ────────────────────────────────────────────

    function open(leadId) {
        _currentLeadId = leadId;
        const overlay = document.getElementById(OVERLAY_ID);
        if (!overlay) return;

        overlay.style.display = 'block';
        document.body.classList.add('prm-drawer--open');

        const url = (window.PRM_CONFIG?.leadDetailUrl || '').replace('__ID__', leadId);
        fetch(url, { headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                document.getElementById(AJAX_TARGET_ID).innerHTML = html;
                _bindDrawerEvents();
            })
            .catch(() => {
                document.getElementById(AJAX_TARGET_ID).innerHTML =
                    '<p style="padding:24px;color:var(--c-text-muted)">Failed to load lead details.</p>';
            });
    }

    function close() {
        const overlay = document.getElementById(OVERLAY_ID);
        if (overlay) overlay.style.display = 'none';
        document.body.classList.remove('prm-drawer--open');
        _currentLeadId = null;
    }

    // ── Sub-modal helpers ──────────────────────────────────────────────

    function openModal(overlayId) {
        const el = document.getElementById(overlayId);
        if (el) el.style.display = 'flex';
    }

    function closeModal(overlayId) {
        const el = document.getElementById(overlayId);
        if (el) el.style.display = 'none';
    }

    // ── Change Status Modal ────────────────────────────────────────────

    function openChangeStatus(leadId, currentStage) {
        _currentLeadId = leadId;
        const labelEl = document.getElementById('csCurrentLabel');
        if (labelEl) labelEl.textContent = _stageLabelById(currentStage);

        // Clear previous selection
        document.querySelectorAll('.prm-status-option').forEach(opt => {
            opt.classList.remove('prm-status-option--selected');
        });

        openModal('changeStatusOverlay');
    }

    function _bindChangeStatus() {
        document.querySelectorAll('.prm-status-option').forEach(opt => {
            opt.addEventListener('click', () => {
                document.querySelectorAll('.prm-status-option').forEach(o => o.classList.remove('prm-status-option--selected'));
                opt.classList.add('prm-status-option--selected');
            });
        });

        const updateBtn = document.getElementById('csUpdateBtn');
        if (updateBtn) {
            updateBtn.addEventListener('click', () => {
                const selected = document.querySelector('.prm-status-option--selected');
                if (!selected || !_currentLeadId) return;

                const newStatus = selected.dataset.status;
                PrmBoard.persistChangeStatus(_currentLeadId, newStatus)
                    .then(() => {
                        closeModal('changeStatusOverlay');
                        _showToast('Status updated successfully.');
                    })
                    .catch(() => _showToast('Failed to update status.', 'error'));
            });
        }
    }

    // ── Convert to Patient Modal ───────────────────────────────────────

    function openConvertToPatient(leadId, leadName, leadPhone) {
        _currentLeadId = leadId;
        const nameInput = document.getElementById('cpName');
        if (nameInput) nameInput.value = leadName || '';

        // Toggle followup section based on checkbox
        const checkbox = document.getElementById('cpScheduleFollowup');
        const section  = document.getElementById('cpFollowupSection');
        if (checkbox && section) {
            section.style.display = checkbox.checked ? 'block' : 'none';
            checkbox.addEventListener('change', () => {
                section.style.display = checkbox.checked ? 'block' : 'none';
            });
        }

        openModal('convertPatientOverlay');
    }

    function _bindConvertToPatient() {
        const convertBtn = document.getElementById('cpConvertBtn');
        if (!convertBtn) return;

        convertBtn.addEventListener('click', () => {
            const form = document.getElementById('convertPatientForm');
            if (!form) return;

            const data = Object.fromEntries(new FormData(form));
            PrmBoard.persistConvertToPatient(_currentLeadId, data)
                .then(r => r.json())
                .then(() => {
                    closeModal('convertPatientOverlay');
                    close();
                    _showToast('Lead converted to patient successfully.');
                    // Refresh page to reflect conversion in Session 11
                    setTimeout(() => window.location.reload(), 800);
                })
                .catch(() => _showToast('Conversion failed. Please try again.', 'error'));
        });

        // Character counter for notes
        const notes = document.querySelector('[name="notes"]');
        const counter = document.getElementById('cpNotesCount');
        if (notes && counter) {
            notes.addEventListener('input', () => {
                counter.textContent = notes.value.length + ' / 250';
            });
        }
    }

    // ── Bind all events after drawer HTML loads ────────────────────────

    function _bindDrawerEvents() {
        // Close button
        const closeBtn = document.getElementById('drawerClose');
        if (closeBtn) closeBtn.addEventListener('click', close);

        // Action buttons inside drawer
        document.querySelectorAll('[data-action]').forEach(el => {
            el.addEventListener('click', (e) => {
                const action = el.dataset.action;
                const leadId = el.dataset.lead || _currentLeadId;

                switch (action) {
                    case 'open-lead-detail':
                        open(leadId || el.closest('[data-lead-id]')?.dataset.leadId);
                        break;
                    case 'close-drawer':
                        close();
                        break;
                    case 'close-modal':
                        closeModal(el.dataset.target);
                        break;
                    case 'change-status': {
                        const card = document.querySelector(`[data-lead-id="${leadId}"]`);
                        openChangeStatus(leadId, card?.dataset.stage || 'new_lead');
                        break;
                    }
                    case 'convert-to-patient': {
                        const card = document.querySelector(`[data-lead-id="${leadId}"]`);
                        const name = card?.querySelector('.prm-lead-card__name')?.textContent;
                        openConvertToPatient(leadId, name, null);
                        break;
                    }
                    case 'move-stage': {
                        _openStageSelectorModal(leadId);
                        break;
                    }
                    case 'reschedule':
                        // Handled by followup calendar JS in Session 4
                        _showToast('Reschedule flow available in Follow-up Calendar.');
                        break;
                    case 'mark-done':
                        _showToast('Follow-up marked as done.');
                        close();
                        break;
                    case 'not-reachable':
                        _showToast('Marked as not reachable.');
                        break;
                    case 'add-note':
                        // Handled by notes modal – stub for Session 5
                        _showToast('Add Note modal coming in Session 5.');
                        break;
                }
            });
        });
    }

    // ── Stage Selector Modal ───────────────────────────────────────────

    function _openStageSelectorModal(leadId) {
        const list = document.getElementById('ssStagelist');
        if (!list) return;

        const stages = window.PRM_CONFIG?.stages || [];
        list.innerHTML = stages.map(s => `
            <button class="prm-stage-selector__option" data-stage="${s.id}" data-lead="${leadId}">
                <span class="prm-stage-selector__dot" style="background:${s.color}"></span>
                ${s.label}
            </button>
        `).join('');

        list.querySelectorAll('.prm-stage-selector__option').forEach(btn => {
            btn.addEventListener('click', () => {
                const toStage = btn.dataset.stage;
                const card    = document.querySelector(`[data-lead-id="${leadId}"]`);
                const fromStage = card?.dataset.stage;
                if (fromStage && toStage !== fromStage) {
                    // Reuse board move logic
                    document.dispatchEvent(new CustomEvent('prm:moveStage', { detail: { leadId, fromStage, toStage } }));
                }
                closeModal('stageSelectorOverlay');
            });
        });

        openModal('stageSelectorOverlay');
    }

    // ── Toast helper ───────────────────────────────────────────────────

    function _showToast(message, type = 'success') {
        const existing = document.getElementById('prmToast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.id = 'prmToast';
        toast.className = `prm-toast prm-toast--${type}`;
        toast.innerHTML = `<i class="ti ti-${type === 'success' ? 'circle-check' : 'alert-circle'}"></i> ${message}`;
        document.body.appendChild(toast);

        setTimeout(() => toast.classList.add('prm-toast--visible'), 10);
        setTimeout(() => { toast.classList.remove('prm-toast--visible'); setTimeout(() => toast.remove(), 300); }, 3000);
    }

    function _stageLabelById(stageId) {
        const stage = (window.PRM_CONFIG?.stages || []).find(s => s.id === stageId);
        return stage ? stage.label : stageId;
    }

    // ── Global init ────────────────────────────────────────────────────

    function init() {
        // Click on lead cards
        document.addEventListener('click', (e) => {
            const card = e.target.closest('[data-action="open-lead-detail"][data-lead-id]');
            if (card) {
                e.stopPropagation();
                open(card.dataset.leadId);
            }
        });

        // Overlay backdrop click to close
        document.getElementById(OVERLAY_ID)?.addEventListener('click', (e) => {
            if (e.target.id === OVERLAY_ID) close();
        });

        // Close modals on backdrop click
        document.querySelectorAll('.prm-modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) closeModal(overlay.id);
            });
        });

        // Keyboard ESC to close
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.prm-modal-overlay[style*="flex"]');
                if (openModal) closeModal(openModal.id);
                else close();
            }
        });

        _bindChangeStatus();
        _bindConvertToPatient();

        // Bind global action buttons (already in DOM before AJAX)
        _bindDrawerEvents();

        // Listen for board move events triggered by stage selector
        document.addEventListener('prm:moveStage', (e) => {
            const { leadId, fromStage, toStage } = e.detail;
            // Trigger DOM move (reuse internal board function via public API)
            PrmBoard.onDragStart({ dataTransfer: { effectAllowed: '' } }, leadId, fromStage);
        });
    }

    return { open, close, openModal, closeModal, openChangeStatus, openConvertToPatient, init };

})();

document.addEventListener('DOMContentLoaded', LeadDrawer.init);
