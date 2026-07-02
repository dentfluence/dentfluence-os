/**
 * Communication Timeline — timeline.js
 * resources/js/communication/timeline.js
 */

document.addEventListener('DOMContentLoaded', function () {

    // ── Live search (index page) ───────────────────────────────
    const searchInput = document.getElementById('patientSearch');
    if (searchInput) {
        let debounceTimer;
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                document.getElementById('searchForm').submit();
            }, 400);
        });
    }

    // ── Character counters ─────────────────────────────────────
    setupCharCounter('noteText', 'noteCount');
    setupCharCounter('callNotes', 'callNoteCount');

    // ── Log call checkbox toggle ───────────────────────────────
    const scheduleCheckbox = document.getElementById('scheduleNextFollowup');
    if (scheduleCheckbox) {
        scheduleCheckbox.addEventListener('change', function () {
            const section = document.getElementById('nextFollowupSection');
            if (section) {
                section.style.display = this.checked ? 'block' : 'none';
            }
        });
    }

    // ── Set default dates ──────────────────────────────────────
    setDefaultDates();

    // ── Close modals on overlay click ──────────────────────────
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function (e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    });

    // ── Keyboard: Esc closes modal ─────────────────────────────
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay').forEach(m => {
                m.style.display = 'none';
            });
        }
    });

});

// ── Modal Controls ─────────────────────────────────────────────

function openAddNoteModal() {
    showModal('addNoteModal');
}

function openScheduleFollowupModal() {
    showModal('scheduleFollowupModal');
}

function openLogCallModal() {
    showModal('logCallModal');
}

function openConvertModal() {
    // Will be connected to Convert to Patient flow in Session 11
    alert('Convert to Patient flow — coming in Session 11 backend wiring.');
}

function showModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.style.display = 'flex';
        // Focus first input
        setTimeout(() => {
            const firstInput = modal.querySelector('input, select, textarea');
            if (firstInput) firstInput.focus();
        }, 100);
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.style.display = 'none';
}

// ── Save Actions (UI-only for Session 5) ───────────────────────

function saveNote() {
    const text = document.getElementById('noteText')?.value?.trim();
    if (!text) {
        alert('Please enter a note.');
        return;
    }
    // Session 11: POST to /communication/timeline/{id}/note
    showSuccessToast('Note saved successfully.');
    closeModal('addNoteModal');
    // Inject into timeline UI temporarily
    injectTimelineEvent({
        type: 'note',
        title: 'Note Added',
        description: text,
        actor: 'You',
        time: 'Just now',
        color: 'amber',
        outcome: null,
    });
}

function saveFollowup() {
    const date = document.getElementById('followupDate')?.value;
    const time = document.getElementById('followupTime')?.value;
    if (!date || !time) {
        alert('Please select a date and time.');
        return;
    }
    // Session 11: POST to /communication/timeline/{id}/followup
    showSuccessToast('Follow-up scheduled successfully.');
    closeModal('scheduleFollowupModal');
    injectTimelineEvent({
        type: 'followup',
        title: 'Follow-up Scheduled',
        description: `Follow-up scheduled on ${formatDate(date)} at ${formatTime(time)}.`,
        actor: 'You',
        time: 'Just now',
        color: 'purple',
        outcome: null,
    });
}

function saveCallLog() {
    const outcome = document.getElementById('callOutcome')?.value;
    if (!outcome) {
        alert('Please select a call outcome.');
        return;
    }
    const notes = document.getElementById('callNotes')?.value?.trim() || 'No notes added.';
    // Session 11: POST to /communication/timeline/{id}/call
    showSuccessToast('Call logged successfully.');
    closeModal('logCallModal');
    injectTimelineEvent({
        type: 'call',
        title: 'Call Logged',
        description: notes,
        actor: 'You',
        time: 'Just now',
        color: outcome === 'connected' ? 'green' : 'red',
        outcome: formatOutcome(outcome),
    });
}

// ── Inject new event at top of timeline ────────────────────────

function injectTimelineEvent(event) {
    const wrapper = document.querySelector('.timeline-wrapper');
    if (!wrapper) return;

    const colorMap = {
        green: '#ECFDF5', greenText: '#059669',
        red:   '#FFF1F2', redText:   '#BE123C',
        amber: '#FFFBEB', amberText: '#D97706',
        purple:'#F5F3FF', purpleText:'#7C3AED',
        blue:  '#EFF6FF', blueText:  '#2563EB',
        teal:  '#F0FDFA', tealText:  '#0D9488',
    };

    const bg   = colorMap[event.color] || '#F9FAFB';
    const text = colorMap[event.color + 'Text'] || '#374151';

    const el = document.createElement('div');
    el.className = `timeline-item tl-${event.type} tl-injected`;
    el.style.background = '#F0FDF4';
    el.innerHTML = `
        <div class="tl-connector">
            <div class="tl-dot tl-dot-${event.color}">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <div class="tl-line"></div>
        </div>
        <div class="tl-content">
            <div class="tl-header">
                <div class="tl-title-row">
                    <span class="tl-title">${event.title}</span>
                    ${event.outcome ? `<span class="tl-outcome-chip" style="background:${bg};color:${text}">${event.outcome}</span>` : ''}
                </div>
                <div class="tl-meta">
                    <span class="tl-time">${event.time}</span>
                    <span class="tl-sep">·</span>
                    <span class="tl-actor">by ${event.actor}</span>
                </div>
            </div>
            <p class="tl-description">${event.description}</p>
        </div>
    `;

    // Animate in
    el.style.opacity = '0';
    el.style.transform = 'translateY(-6px)';
    wrapper.insertBefore(el, wrapper.firstChild);
    requestAnimationFrame(() => {
        el.style.transition = 'opacity 0.3s, transform 0.3s';
        el.style.opacity = '1';
        el.style.transform = 'translateY(0)';
    });

    // Remove highlight after 3s
    setTimeout(() => {
        el.style.background = '';
    }, 3000);
}

// ── Toast Notifications ────────────────────────────────────────

function showSuccessToast(msg) {
    removeExistingToasts();
    const toast = document.createElement('div');
    toast.className = 'tl-toast tl-toast-success';
    toast.innerHTML = `
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <span>${msg}</span>
    `;
    toast.style.cssText = `
        position: fixed;
        bottom: 24px;
        right: 24px;
        background: #111827;
        color: #fff;
        padding: 12px 18px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
        z-index: 9999;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        animation: toastIn 0.25s ease;
    `;

    // Inject keyframe if not already present
    if (!document.getElementById('toastStyle')) {
        const style = document.createElement('style');
        style.id = 'toastStyle';
        style.textContent = `
            @keyframes toastIn {
                from { opacity:0; transform:translateY(8px); }
                to   { opacity:1; transform:translateY(0); }
            }
        `;
        document.head.appendChild(style);
    }

    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function removeExistingToasts() {
    document.querySelectorAll('.tl-toast').forEach(t => t.remove());
}

// ── Helpers ────────────────────────────────────────────────────

function setupCharCounter(textareaId, counterId) {
    const ta = document.getElementById(textareaId);
    const counter = document.getElementById(counterId);
    if (!ta || !counter) return;
    ta.addEventListener('input', () => {
        counter.textContent = ta.value.length;
    });
}

function setDefaultDates() {
    const today = new Date().toISOString().split('T')[0];
    const tomorrow = new Date(Date.now() + 86400000).toISOString().split('T')[0];
    const nowTime  = new Date().toTimeString().slice(0,5);

    ['callDate', 'callTime', 'followupDate', 'nextFollowupDate'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        if (el.type === 'date' && !el.value) {
            el.value = id.includes('next') ? tomorrow : today;
        }
        if (el.type === 'time' && !el.value) {
            el.value = nowTime;
        }
    });
}

function formatDate(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatTime(timeStr) {
    const [h, m] = timeStr.split(':');
    const hour = parseInt(h);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${hour12}:${m} ${ampm}`;
}

function formatOutcome(val) {
    const map = {
        connected:          'Connected',
        not_reachable:      'Not Reachable',
        busy:               'Busy',
        voicemail:          'Voicemail',
        callback_requested: 'Callback Requested',
    };
    return map[val] || val;
}
