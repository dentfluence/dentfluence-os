/**
 * resources/js/communication/followup-modals.js
 * All modal open/close/submit handlers for Follow-up Engine
 */

// ── Generic modal controls ─────────────────────────────────────────────────

function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        // Trigger animation
        setTimeout(() => modal.querySelector('.fu-modal')?.classList.add('fu-modal-visible'), 10);
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.querySelector('.fu-modal')?.classList.remove('fu-modal-visible');
        setTimeout(() => {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }, 200);
    }
}

// Close on overlay click
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('fu-modal-overlay')) {
        closeModal(e.target.id);
    }
});

// Close on Escape key
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.fu-modal-overlay').forEach(m => {
            if (m.style.display !== 'none') closeModal(m.id);
        });
    }
});

// ── Modal openers ───────────────────────────────────────────────────────────

function openCompleteModal(id) {
    // SESSION 11: fetch follow-up data by id and populate modal
    openModal('completeModal');
}

function openRescheduleModal(id) {
    openModal('rescheduleModal');
}

function openNoteModal(id) {
    openModal('addNoteModal');
}

function openStatusModal(id) {
    openModal('changeStatusModal');
}

function openScheduleModal() {
    openModal('scheduleModal');
}

function openConvertModal(id) {
    openModal('convertModal');
}

function openCaseModal(id) {
    openModal('createCaseModal');
}

function openFilterModal() {
    openModal('filterModal');
}

// ── Submit handlers (SESSION 11: wire to real AJAX) ─────────────────────────

function submitComplete() {
    const outcome  = document.getElementById('callOutcome')?.value;
    const result   = document.getElementById('callResult')?.value;
    const nextStep = document.getElementById('nextStep')?.value;
    const notes    = document.getElementById('completeNotes')?.value;

    if (!outcome || !result) {
        showToast('Please fill in required fields.', 'error');
        return;
    }

    // SESSION 11: replace with real AJAX call
    // fetch('/communication/followup-engine/1/complete', { method: 'POST', body: ... })
    showToast('Follow-up completed successfully.', 'success');
    closeModal('completeModal');

    // If next step is schedule, open schedule modal
    if (nextStep === 'schedule_followup') {
        setTimeout(() => openScheduleModal(), 300);
    }
    if (nextStep === 'convert_to_patient') {
        setTimeout(() => openConvertModal(), 300);
    }
}

function submitReschedule() {
    showToast('Follow-up rescheduled.', 'success');
    closeModal('rescheduleModal');
}

function submitNote() {
    const note = document.getElementById('noteText')?.value;
    if (!note || note.trim() === '') {
        showToast('Please enter a note.', 'error');
        return;
    }
    showToast('Note saved.', 'success');
    closeModal('addNoteModal');
}

function submitChangeStatus() {
    const selected = document.querySelector('.fu-status-option.fu-status-selected');
    if (!selected) {
        showToast('Please select a status.', 'error');
        return;
    }
    showToast('Status updated.', 'success');
    closeModal('changeStatusModal');
}

function submitSchedule() {
    showToast('Follow-up scheduled.', 'success');
    closeModal('scheduleModal');
}

function submitConvert() {
    showToast('Lead converted to patient.', 'success');
    closeModal('convertModal');
}

function submitCase() {
    showToast('Case created and routed.', 'success');
    closeModal('createCaseModal');
}

function applyFilters() {
    showToast('Filters applied.', 'success');
    closeModal('filterModal');
}

function clearFilters() {
    document.querySelectorAll('#filterModal select').forEach(s => s.selectedIndex = 0);
    document.querySelectorAll('#filterModal input[type=checkbox]').forEach(c => c.checked = false);
}

// ── Status selection ─────────────────────────────────────────────────────────

function selectStatus(value, el) {
    document.querySelectorAll('.fu-status-option').forEach(o => {
        o.classList.remove('fu-status-selected');
        const check = o.querySelector('.fu-status-check');
        if (check) check.remove();
    });
    el.classList.add('fu-status-selected');
    const check = document.createElement('svg');
    check.className = 'fu-status-check';
    check.setAttribute('width', '16');
    check.setAttribute('height', '16');
    check.setAttribute('fill', 'none');
    check.setAttribute('stroke', 'currentColor');
    check.setAttribute('stroke-width', '2.5');
    check.setAttribute('viewBox', '0 0 24 24');
    check.innerHTML = '<polyline points="20 6 9 17 4 12"/>';
    el.appendChild(check);
}

// ── Modal tabs ───────────────────────────────────────────────────────────────

function switchModalTab(tab, btn) {
    document.querySelectorAll('.fu-modal-tab').forEach(t => t.classList.remove('fu-modal-tab-active'));
    btn.classList.add('fu-modal-tab-active');
}

// ── Char counter for textareas ───────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.fu-textarea').forEach(function (ta) {
        const max     = ta.maxLength;
        const counter = ta.nextElementSibling;
        if (counter && counter.classList.contains('fu-char-count')) {
            ta.addEventListener('input', function () {
                counter.textContent = this.value.length + '/' + max;
            });
        }
    });

    // Complete notes counter
    const completeNotes = document.getElementById('completeNotes');
    const completeCount = document.getElementById('completeNotesCount');
    if (completeNotes && completeCount) {
        completeNotes.addEventListener('input', function () {
            completeCount.textContent = this.value.length + '/500';
        });
    }

    // Note text counter
    const noteText  = document.getElementById('noteText');
    const noteCount = document.getElementById('noteCount');
    if (noteText && noteCount) {
        noteText.addEventListener('input', function () {
            noteCount.textContent = this.value.length + '/1000';
        });
    }

    // Show/hide next follow-up date based on next step
    const nextStep = document.getElementById('nextStep');
    const fuDate   = document.getElementById('nextFollowupDate');
    if (nextStep && fuDate) {
        nextStep.addEventListener('change', function () {
            fuDate.style.display = this.value === 'schedule_followup' ? 'block' : 'none';
        });
    }
});

// ── WhatsApp button ──────────────────────────────────────────────────────────

function openWhatsApp(phone) {
    const num = (phone || '9876543210').replace(/\D/g, '');
    window.open('https://wa.me/91' + num, '_blank');
}

function makeCall(phone) {
    console.log('Initiate call to:', phone);
    // SESSION 11: integrate with call dialer
}

// ── Toast notifications ───────────────────────────────────────────────────────

function showToast(message, type) {
    type = type || 'success';
    const existing = document.getElementById('fu-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id    = 'fu-toast';
    toast.className = 'fu-toast fu-toast-' + type;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => toast.classList.add('fu-toast-visible'), 10);
    setTimeout(() => {
        toast.classList.remove('fu-toast-visible');
        setTimeout(() => toast.remove(), 300);
    }, 2500);
}
