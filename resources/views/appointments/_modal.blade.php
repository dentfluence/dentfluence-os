{{-- resources/views/appointments/_modal.blade.php
     ╔══════════════════════════════════════════════════════════════╗
     ║  SHARED Add Appointment / Walk-In Modal (tabbed)            ║
     ║  @include('appointments._modal') in any view               ║
     ╚══════════════════════════════════════════════════════════════╝
--}}

{{-- ── Backdrop ──────────────────────────────────────────────── --}}
<div id="combined-modal-backdrop" class="modal-backdrop-custom"
     onclick="closeCombinedModal()"></div>

{{-- ── Modal Shell ───────────────────────────────────────────── --}}
<div id="combined-modal" class="modal-custom">

    {{-- Header --}}
    <div class="modal-custom-header">
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;background:#eff6ff;border-radius:8px;
                        display:flex;align-items:center;justify-content:center;font-size:16px;">📅</div>
            <div class="modal-custom-title" id="combined-modal-title">New Appointment</div>
        </div>
        <button onclick="closeCombinedModal()"
                style="border:none;background:#f1f5f9;width:28px;height:28px;border-radius:6px;
                       font-size:14px;color:#64748b;cursor:pointer;display:flex;
                       align-items:center;justify-content:center;transition:background .15s;"
                onmouseover="this.style.background='#e2e8f0'"
                onmouseout="this.style.background='#f1f5f9'">✕</button>
    </div>

    {{-- Tabs --}}
    <div class="modal-tabs">
        <button class="modal-tab-btn active" id="tab-btn-appointment"
                onclick="switchModalTab('appointment')">
            📅 Appointment
        </button>
        <button class="modal-tab-btn" id="tab-btn-walkin"
                onclick="switchModalTab('walkin')">
            🚶 Walk-In
        </button>
    </div>

    {{-- ══ TAB: Appointment ═══════════════════════════════════ --}}
    <div id="tab-appointment" class="modal-tab-panel active">
        <div class="modal-custom-body">

            {{-- Patient name search --}}
            <div style="margin-bottom:12px;">
                <label class="form-label-sm">Patient Name *</label>
                <div style="position:relative;">
                    <svg style="position:absolute;left:9px;top:50%;transform:translateY(-50%);
                                color:#94a3b8;pointer-events:none;"
                         width="13" height="13" fill="none" stroke="currentColor"
                         stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input class="form-control-sm" id="am-patient-search" type="text"
                           placeholder="Search by name or phone…"
                           style="padding-left:30px;"
                           oninput="filterPatientDropdown(this.value)"
                           autocomplete="off">
                    <div id="am-patient-dropdown"
                         style="display:none;position:absolute;top:calc(100% + 2px);left:0;right:0;
                                background:#fff;border:1.5px solid #e2e8f0;border-radius:8px;
                                max-height:180px;overflow-y:auto;z-index:2000;
                                box-shadow:0 4px 16px rgba(0,0,0,.1);">
                    </div>
                    <input type="hidden" id="am-patient" value="">
                </div>
            </div>

            {{-- Mobile No --}}
            <div style="margin-bottom:12px;">
                <label class="form-label-sm">Mobile No</label>
                <input class="form-control-sm" id="am-mobile" type="tel"
                       placeholder="Auto-filled from patient" readonly
                       style="background:#f8fafc;color:#64748b;">
            </div>

            {{-- Date + Time --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
                <div>
                    <label class="form-label-sm">Date *</label>
                    <input class="form-control-sm" id="am-date" type="date">
                </div>
                <div>
                    <label class="form-label-sm">Time *</label>
                    <input class="form-control-sm" id="am-time" type="time">
                </div>
            </div>

            {{-- Doctor + Duration --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
                <div>
                    <label class="form-label-sm">Doctor *</label>
                    <select class="form-control-sm" id="am-doctor">
                        <option value="">— Select —</option>
                        @foreach($doctors as $doc)
                        <option value="{{ $doc->id }}">{{ $doc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label-sm">Duration (min)</label>
                    <input class="form-control-sm" id="am-duration" type="number"
                           value="30" min="10" max="240">
                </div>
            </div>

            {{-- Treatment Category --}}
            <div style="margin-bottom:12px;">
                <label class="form-label-sm">Treatment Category</label>
                <select class="form-control-sm" id="am-category" onchange="amAutoFill()">
                    <option value="">— Select Category —</option>
                    @foreach($treatmentCategories as $cat)
                    <option value="{{ $cat->id }}"
                            data-duration="{{ $cat->default_duration ?? '' }}">
                        {{ $cat->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- Type toggle --}}
            <div style="margin-bottom:12px;">
                <label class="form-label-sm">Type *</label>
                <div style="display:flex;gap:6px;">
                    <button type="button" id="type-btn-consultation"
                            class="type-toggle-btn active"
                            onclick="setApptType('consultation')">
                        🩺 Consultation
                    </button>
                    <button type="button" id="type-btn-treatment"
                            class="type-toggle-btn"
                            onclick="setApptType('treatment')">
                        🦷 Treatment
                    </button>
                    <button type="button" id="type-btn-followup"
                            class="type-toggle-btn"
                            onclick="setApptType('followup')">
                        🔁 Follow-Up
                    </button>
                </div>
                <input type="hidden" id="am-type" value="consultation">
            </div>

            {{-- Notes --}}
            <div style="margin-bottom:6px;">
                <label class="form-label-sm">Notes / Chief Complaint</label>
                <textarea class="form-control-sm" id="am-notes" rows="2"
                          placeholder="e.g. Toothache upper left, sensitivity to cold…"></textarea>
            </div>

            {{-- Conflict warning --}}
            <div id="am-conflict-warn" class="conflict-warning">
                <span>⚠️</span>
                <span id="am-conflict-text">Potential scheduling conflict detected.</span>
            </div>

        </div>
        <div class="modal-custom-footer">
            <button class="btn-secondary-sm" onclick="closeCombinedModal()">Cancel</button>
            <button class="btn-primary-sm" onclick="submitApptModal()" id="am-submit-btn">
                Book Appointment
            </button>
        </div>
    </div>{{-- /tab-appointment --}}

    {{-- ══ TAB: Walk-In ════════════════════════════════════════ --}}
    <div id="tab-walkin" class="modal-tab-panel">
        <div class="modal-custom-body">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
                <div>
                    <label class="form-label-sm">First Name *</label>
                    <input class="form-control-sm" id="wi-first" type="text" placeholder="Rahul">
                </div>
                <div>
                    <label class="form-label-sm">Last Name *</label>
                    <input class="form-control-sm" id="wi-last" type="text" placeholder="Sharma">
                </div>
            </div>

            <div style="margin-bottom:12px;">
                <label class="form-label-sm">Mobile *</label>
                <input class="form-control-sm" id="wi-mobile" type="tel" placeholder="9876543210">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
                <div>
                    <label class="form-label-sm">Doctor</label>
                    <select class="form-control-sm" id="wi-doctor">
                        <option value="">— Select —</option>
                        @foreach($doctors as $doc)
                        <option value="{{ $doc->id }}">{{ $doc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label-sm">Time</label>
                    <input class="form-control-sm" id="wi-time" type="time"
                           value="{{ now()->format('H:i') }}">
                </div>
            </div>

            <div style="margin-bottom:12px;">
                <label class="form-label-sm">Treatment Category</label>
                <select class="form-control-sm" id="wi-category">
                    <option value="">— Select —</option>
                    @foreach($treatmentCategories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <div style="margin-bottom:6px;">
                <label class="form-label-sm">Notes</label>
                <input class="form-control-sm" id="wi-notes" type="text"
                       placeholder="Chief complaint or note">
            </div>

            <div id="wi-success"
                 style="display:none;margin-top:8px;padding:10px 12px;background:#dcfce7;
                        border-radius:8px;font-size:12px;color:#15803d;font-weight:700;">
                ✓ Walk-in added successfully!
            </div>
        </div>
        <div class="modal-custom-footer">
            <button class="btn-secondary-sm" onclick="closeCombinedModal()">Cancel</button>
            <button class="btn-primary-sm" onclick="submitWalkin()" id="wi-submit-btn">
                Add Walk-In
            </button>
        </div>
    </div>{{-- /tab-walkin --}}

</div>{{-- /combined-modal --}}


{{-- ── Shared Modal JS (included once per page) ─────────────── --}}
<style>
/* Type toggle buttons */
.type-toggle-btn {
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 700;
    border: 1.5px solid #e2e8f0;
    border-radius: 7px;
    background: #f8fafc;
    color: #475569;
    cursor: pointer;
    transition: all .15s;
    white-space: nowrap;
}
.type-toggle-btn:hover { background: #f1f5f9; }
.type-toggle-btn.active {
    background: #eff6ff;
    border-color: #bfdbfe;
    color: #2563eb;
}

/* Patient dropdown items */
.patient-dd-item {
    padding: 8px 12px;
    cursor: pointer;
    font-size: 12.5px;
    color: #1e293b;
    border-bottom: 1px solid #f1f5f9;
    transition: background .1s;
}
.patient-dd-item:last-child { border-bottom: none; }
.patient-dd-item:hover { background: #f8fafc; }
.patient-dd-sub { font-size: 11px; color: #94a3b8; margin-top: 1px; }
</style>

<script>
// ── Patient search ─────────────────────────────────────────────
const __ALL_PATIENTS = @json(\App\Models\Patient::where('branch_id', Auth::user()->branch_id)->orderBy('name')->get(['id','name','phone']));

function filterPatientDropdown(q) {
    const dd = document.getElementById('am-patient-dropdown');
    if (!q || q.length < 1) { dd.style.display = 'none'; return; }
    const lower = q.toLowerCase();
    const matches = __ALL_PATIENTS.filter(p =>
        p.name.toLowerCase().includes(lower) ||
        (p.phone && p.phone.includes(q))
    ).slice(0, 10);

    if (!matches.length) { dd.style.display = 'none'; return; }

    dd.innerHTML = matches.map(p => `
        <div class="patient-dd-item" onclick="selectPatient(${p.id},'${p.name.replace(/'/g,"\\'")}','${p.phone || ''}')">
            <div>${p.name}</div>
            <div class="patient-dd-sub">${p.phone || 'No phone'}</div>
        </div>
    `).join('');
    dd.style.display = 'block';
}

function selectPatient(id, name, phone) {
    document.getElementById('am-patient').value          = id;
    document.getElementById('am-patient-search').value   = name;
    document.getElementById('am-mobile').value           = phone;
    document.getElementById('am-patient-dropdown').style.display = 'none';
}

document.addEventListener('click', e => {
    const dd = document.getElementById('am-patient-dropdown');
    if (dd && !dd.contains(e.target) &&
        e.target.id !== 'am-patient-search') {
        dd.style.display = 'none';
    }
});

// ── Type toggle ────────────────────────────────────────────────
function setApptType(type) {
    document.getElementById('am-type').value = type;
    ['consultation','treatment','followup'].forEach(t => {
        document.getElementById(`type-btn-${t}`)?.classList.toggle('active', t === type);
    });
}

// ── Auto-fill duration from category ──────────────────────────
const AUTO_DURATION_MAP = {
    consultation: 30, rct: 60, 'root canal': 60,
    implant: 90, surgery: 90, cleaning: 45, scaling: 45,
    follow: 30, crown: 60, extraction: 30, filling: 45,
    orthodontic: 30, braces: 30, xray: 15, whitening: 60,
};

function autoDurationFromName(catName) {
    if (!catName) return 30;
    const lower = catName.toLowerCase();
    for (const [k, v] of Object.entries(AUTO_DURATION_MAP)) {
        if (lower.includes(k)) return v;
    }
    return 30;
}

function amAutoFill() {
    const sel  = document.getElementById('am-category');
    const opt  = sel.options[sel.selectedIndex];
    const dur  = opt.dataset.duration || autoDurationFromName(opt.text);
    document.getElementById('am-duration').value = dur;

    const doctorId = document.getElementById('am-doctor')?.value;
    const date     = document.getElementById('am-date')?.value;
    const time     = document.getElementById('am-time')?.value;
    if (doctorId && date && time) checkConflict(doctorId, date, time, dur);
}

// ── Conflict check ─────────────────────────────────────────────
async function checkConflict(doctorId, date, time, duration) {
    try {
        const url = new URL(window.__APPT_DATA.routes.checkConflict);
        url.searchParams.set('doctor_id',         doctorId);
        url.searchParams.set('appointment_date',  date);
        url.searchParams.set('appointment_time',  time);
        url.searchParams.set('duration_minutes',  duration);

        const res  = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.__APPT_DATA.csrfToken }
        });
        const data = await res.json();
        const warn = document.getElementById('am-conflict-warn');

        if (data.has_conflict) {
            const c = data.conflicts[0];
            document.getElementById('am-conflict-text').textContent =
                `Conflict: ${c.patient_name} at ${c.time} (${c.duration} min)`;
            warn.style.display = 'flex';
        } else {
            warn.style.display = 'none';
        }
    } catch {}
}

// ── Modal open / close / tab switch ───────────────────────────
function openCombinedModal(tab = 'appointment', dateStr = null, dateObj = null) {
    switchModalTab(tab);

    if (tab === 'appointment') {
        const apptDate = dateStr
            ? dateStr.split('T')[0]
            : new Date().toISOString().split('T')[0];
        const timeStr = dateObj
            ? dateObj.getHours().toString().padStart(2,'0') + ':' +
              dateObj.getMinutes().toString().padStart(2,'0')
            : new Date().toTimeString().slice(0, 5);

        document.getElementById('am-date').value = apptDate;
        document.getElementById('am-time').value = timeStr;
        document.getElementById('am-conflict-warn').style.display = 'none';
        document.getElementById('am-patient-search').value = '';
        document.getElementById('am-patient').value = '';
        document.getElementById('am-mobile').value = '';
    }

    document.getElementById('combined-modal-backdrop').style.display = 'block';
    document.getElementById('combined-modal').style.display  = 'block';
}

function closeCombinedModal() {
    document.getElementById('combined-modal-backdrop').style.display = 'none';
    document.getElementById('combined-modal').style.display  = 'none';
}

function switchModalTab(tab) {
    ['appointment', 'walkin'].forEach(t => {
        document.getElementById(`tab-${t}`)?.classList.toggle('active', t === tab);
        document.getElementById(`tab-btn-${t}`)?.classList.toggle('active', t === tab);
    });
}

// ── Submit Appointment ─────────────────────────────────────────
async function submitApptModal() {
    const patient = document.getElementById('am-patient').value;
    const doctor  = document.getElementById('am-doctor').value;
    const date    = document.getElementById('am-date').value;
    const time    = document.getElementById('am-time').value;
    const type    = document.getElementById('am-type').value;

    if (!patient || !doctor || !date || !time) {
        alert('Please fill in all required fields (Patient, Doctor, Date, Time).');
        return;
    }

    const btn = document.getElementById('am-submit-btn');
    btn.textContent = 'Booking…';
    btn.disabled    = true;

    try {
        const res = await fetch(window.__APPT_DATA.routes.store, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.__APPT_DATA.csrfToken,
                'Accept':       'application/json',
            },
            body: JSON.stringify({
                patient_id:            patient,
                doctor_id:             doctor,
                appointment_date:      date,
                appointment_time:      time,
                type,
                duration_minutes:      parseInt(document.getElementById('am-duration').value) || 30,
                treatment_category_id: document.getElementById('am-category').value || null,
                notes:                 document.getElementById('am-notes').value || '',
            }),
        });

        const data = await res.json();
        if (data.ok || data.success) {
            closeCombinedModal();
            if (typeof window._apptApp !== 'undefined') {
                if (data.appointment) window._apptApp.addAppointmentToCalendar(data.appointment);
                window._apptApp.refreshQueue();
            }
        } else {
            alert(data.message || 'Failed to book appointment.');
        }
    } catch {
        alert('Network error. Please try again.');
    } finally {
        btn.textContent = 'Book Appointment';
        btn.disabled    = false;
    }
}

// ── Submit Walk-In ────────────────────────────────────────────
async function submitWalkin() {
    const first  = document.getElementById('wi-first').value.trim();
    const last   = document.getElementById('wi-last').value.trim();
    const mobile = document.getElementById('wi-mobile').value.trim();
    const time   = document.getElementById('wi-time').value;

    if (!first || !last || !mobile) {
        alert('Please fill in First Name, Last Name, and Mobile.');
        return;
    }

    const btn = document.getElementById('wi-submit-btn');
    btn.textContent = 'Adding…';
    btn.disabled    = true;

    try {
        const today = new Date().toISOString().split('T')[0];
        const res   = await fetch(window.__APPT_DATA.routes.store, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.__APPT_DATA.csrfToken,
                'Accept':       'application/json',
            },
            body: JSON.stringify({
                first_name:            first,
                last_name:             last,
                mobile,
                doctor_id:             document.getElementById('wi-doctor').value,
                appointment_date:      today,
                appointment_time:      time,
                treatment_category_id: document.getElementById('wi-category').value || null,
                notes:                 document.getElementById('wi-notes').value || '',
                is_walkin:             true,
            }),
        });

        const data = await res.json();
        if (data.ok || data.success) {
            document.getElementById('wi-success').style.display = 'block';
            if (typeof window._apptApp !== 'undefined') {
                if (data.appointment) window._apptApp.addAppointmentToCalendar(data.appointment);
            }
            setTimeout(() => {
                closeCombinedModal();
                if (typeof window._apptApp !== 'undefined') window._apptApp.refreshQueue();
                ['wi-first','wi-last','wi-mobile','wi-notes'].forEach(id =>
                    document.getElementById(id).value = ''
                );
                document.getElementById('wi-category').value = '';
                document.getElementById('wi-success').style.display = 'none';
            }, 1200);
        } else {
            alert('Error: ' + (data.message || 'Failed to add walk-in'));
        }
    } catch {
        alert('Network error. Please try again.');
    } finally {
        btn.textContent = 'Add Walk-In';
        btn.disabled    = false;
    }
}
</script>
