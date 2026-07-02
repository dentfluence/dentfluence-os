/**
 * resources/js/communication/followup-calendar.js
 * Follow-up Calendar interactions — view switching, navigation, mini calendar
 */

document.addEventListener('DOMContentLoaded', function () {

    // ── View switcher ──────────────────────────────────────────
    document.querySelectorAll('.fu-view-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.fu-view-btn').forEach(b => b.classList.remove('fu-view-active'));
            this.classList.add('fu-view-active');
            const view = this.dataset.view;
            // SESSION 11: fetch real data for view
            console.log('Switch view to:', view);
        });
    });

    // ── Calendar navigation ────────────────────────────────────
    document.getElementById('btnToday')?.addEventListener('click', function () {
        document.getElementById('calDateLabel').textContent = '18 – 24 May 2025';
    });

    document.getElementById('btnPrev')?.addEventListener('click', function () {
        console.log('Navigate previous');
    });

    document.getElementById('btnNext')?.addEventListener('click', function () {
        console.log('Navigate next');
    });

    // ── Mini calendar ──────────────────────────────────────────
    let miniYear  = 2025;
    let miniMonth = 4; // 0-indexed: April=3, May=4

    const monthNames = [
        'January','February','March','April','May','June',
        'July','August','September','October','November','December'
    ];

    function renderMiniCal() {
        const label = document.getElementById('miniMonthLabel');
        const grid  = document.getElementById('miniCalDays');
        if (!label || !grid) return;

        label.textContent = monthNames[miniMonth] + ' ' + miniYear;
        grid.innerHTML = '';

        const firstDay  = new Date(miniYear, miniMonth, 1).getDay(); // 0=Sun
        const daysInMon = new Date(miniYear, miniMonth + 1, 0).getDate();
        const today     = new Date();

        // Blank cells for days before first
        for (let i = 0; i < firstDay; i++) {
            const blank = document.createElement('div');
            blank.className = 'fu-mini-day fu-mini-day-blank';
            grid.appendChild(blank);
        }

        for (let d = 1; d <= daysInMon; d++) {
            const cell    = document.createElement('div');
            const isToday = (d === today.getDate() && miniMonth === today.getMonth() && miniYear === today.getFullYear());
            cell.className = 'fu-mini-day' + (isToday ? ' fu-mini-day-today' : '');
            cell.textContent = d;
            cell.addEventListener('click', function () {
                document.querySelectorAll('.fu-mini-day').forEach(c => c.classList.remove('fu-mini-day-selected'));
                this.classList.add('fu-mini-day-selected');
                console.log('Mini cal date clicked:', miniYear + '-' + (miniMonth + 1) + '-' + d);
            });
            grid.appendChild(cell);
        }
    }

    document.getElementById('miniPrev')?.addEventListener('click', function () {
        miniMonth--;
        if (miniMonth < 0) { miniMonth = 11; miniYear--; }
        renderMiniCal();
    });

    document.getElementById('miniNext')?.addEventListener('click', function () {
        miniMonth++;
        if (miniMonth > 11) { miniMonth = 0; miniYear++; }
        renderMiniCal();
    });

    renderMiniCal();

    // ── Schedule button ────────────────────────────────────────
    document.getElementById('btnSchedule')?.addEventListener('click', function () {
        openScheduleModal();
    });

    document.getElementById('btnFilters')?.addEventListener('click', function () {
        openFilterModal();
    });

    // ── Event click on calendar cells ─────────────────────────
    document.querySelectorAll('.fu-event').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.stopPropagation();
            const id   = this.dataset.id;
            const name = this.querySelector('.fu-event-name')?.textContent;
            openEventActions(id, name, 'call');
        });
    });

});

/**
 * Open action popup for a calendar event.
 * Shows a contextual mini-menu with: Complete, Reschedule, Note, Status, More.
 */
function openEventActions(id, name, channel) {
    // Remove existing popup
    const existing = document.getElementById('fu-event-popup');
    if (existing) existing.remove();

    const popup = document.createElement('div');
    popup.id    = 'fu-event-popup';
    popup.className = 'fu-event-popup';
    popup.innerHTML = `
        <div class="fu-event-popup-header">${name}</div>
        <button onclick="openCompleteModal(${id}); closeEventPopup()">✓ Complete Follow-up</button>
        <button onclick="openRescheduleModal(${id}); closeEventPopup()">↻ Reschedule</button>
        <button onclick="openNoteModal(); closeEventPopup()">✎ Add Note</button>
        <button onclick="openStatusModal(); closeEventPopup()">⬡ Change Status</button>
        <button onclick="openConvertModal(); closeEventPopup()">→ Convert to Patient</button>
        <button onclick="openCaseModal(); closeEventPopup()">⚑ Create Case</button>
    `;
    document.body.appendChild(popup);

    // Position near cursor — use setTimeout to get proper positioning
    setTimeout(function () {
        const el = document.querySelector('[data-id="' + id + '"]');
        if (el) {
            const rect = el.getBoundingClientRect();
            popup.style.top  = (rect.bottom + window.scrollY + 4) + 'px';
            popup.style.left = (rect.left + window.scrollX) + 'px';
        }
    }, 10);

    // Close on outside click
    setTimeout(function () {
        document.addEventListener('click', closeEventPopup, { once: true });
    }, 50);
}

function closeEventPopup() {
    const popup = document.getElementById('fu-event-popup');
    if (popup) popup.remove();
}
