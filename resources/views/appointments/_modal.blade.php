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
                        display:flex;align-items:center;justify-content:center;font-size:16px;"></div>
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
            Appointment
        </button>
        <button class="modal-tab-btn" id="tab-btn-walkin"
                onclick="switchModalTab('walkin')">
            Walk-In
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

                {{-- Quick new patient form (hidden by default) --}}
                <div id="am-quick-patient-form" style="display:none;margin-top:8px;padding:12px;border:1.5px solid #d8b4fe;border-radius:8px;background:#faf5ff;">
                    <div style="font-size:12px;font-weight:600;color:#6a0f70;margin-bottom:8px;">New Patient</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">
                        <input class="form-control-sm" id="am-qp-first" type="text" placeholder="First name *">
                        <input class="form-control-sm" id="am-qp-last"  type="text" placeholder="Last name *">
                    </div>
                    <input class="form-control-sm" id="am-qp-phone" type="tel" placeholder="Phone number *"
                           oninput="checkQuickPatientPhone()"
                           style="width:100%;margin-bottom:6px;">
                    <div id="am-qp-warn" style="display:none;font-size:11px;color:#b45309;background:#fef3c7;padding:5px 8px;border-radius:5px;margin-bottom:6px;">
                        This phone number belongs to an existing patient. You can still proceed.
                    </div>
                    <div id="am-qp-error" style="display:none;font-size:11px;color:#dc2626;margin-bottom:6px;"></div>
                    <div style="display:flex;gap:8px;">
                        <button id="am-qp-submit" type="button" onclick="submitQuickPatient()"
                                style="flex:1;padding:6px 0;background:#6a0f70;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">
                            Create &amp; Select
                        </button>
                        <button type="button" onclick="hideQuickPatientForm()"
                                style="padding:6px 12px;background:#f1f5f9;color:#64748b;border:none;border-radius:6px;font-size:12px;cursor:pointer;">
                            Cancel
                        </button>
                    </div>
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
                    <input class="form-control-sm" id="am-date" type="text" placeholder="DD-MM-YYYY" readonly>
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
                        Consultation
                    </button>
                    <button type="button" id="type-btn-treatment"
                            class="type-toggle-btn"
                            onclick="setApptType('treatment')">
                        Treatment
                    </button>
                    <button type="button" id="type-btn-follow-up"
                            class="type-toggle-btn"
                            onclick="setApptType('follow-up')">
                        Follow-Up
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
                <span></span>
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
/* Modal overlay + shell */
.modal-backdrop-custom {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 9998;
}
.modal-custom {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 480px;
    max-width: calc(100vw - 32px);
    max-height: 90vh;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 8px 40px rgba(0,0,0,.18);
    z-index: 9999;
    overflow: hidden;
    display: none;
    flex-direction: column;
}
.modal-custom-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    flex-shrink: 0;
}
.modal-custom-title { font-size: 15px; font-weight: 700; color: #1e293b; }
.modal-tabs {
    display: flex;
    gap: 6px;
    padding: 10px 20px 0;
    border-bottom: 1px solid #f1f5f9;
    flex-shrink: 0;
}
.modal-tab-btn {
    padding: 7px 16px;
    font-size: 12.5px;
    font-weight: 600;
    border: none;
    background: transparent;
    color: #64748b;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    transition: all .15s;
}
.modal-tab-btn.active { color: #2563eb; border-bottom-color: #2563eb; }
.modal-tab-panel { display: none; flex-direction: column; overflow: hidden; }
.modal-tab-panel.active { display: flex; }
.modal-custom-body {
    padding: 16px 20px;
    overflow-y: auto;
    flex: 1;
}
.modal-custom-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 20px;
    border-top: 1px solid #f1f5f9;
    flex-shrink: 0;
}
.form-label-sm {
    display: block;
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-bottom: 4px;
}
.form-control-sm {
    width: 100%;
    padding: 7px 10px;
    font-size: 13px;
    border: 1.5px solid #e2e8f0;
    border-radius: 7px;
    outline: none;
    transition: border-color .15s;
    box-sizing: border-box;
    font-family: inherit;
    background: #fff;
}
.form-control-sm:focus { border-color: #6366f1; }
.btn-primary-sm {
    padding: 8px 18px;
    background: #6366f1;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s;
}
.btn-primary-sm:hover { background: #4f46e5; }
.btn-primary-sm:disabled { opacity: .6; cursor: not-allowed; }
.btn-secondary-sm {
    padding: 8px 18px;
    background: #f1f5f9;
    color: #475569;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s;
}
.btn-secondary-sm:hover { background: #e2e8f0; }
.conflict-warning {
    display: none;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    background: #fef9c3;
    border: 1px solid #fde047;
    border-radius: 7px;
    font-size: 12px;
    color: #854d0e;
    margin-top: 8px;
}

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
const __ALL_PATIENTS = {!! json_encode(auth()->check() ? \App\Models\Patient::where('branch_id', auth()->user()->branch_id)->orderBy('name')->get(['id','name','phone']) : []) !!};

function filterPatientDropdown(q) {
    const dd = document.getElementById('am-patient-dropdown');
    if (!q || q.length < 1) { dd.style.display = 'none'; return; }
    const lower = q.toLowerCase();
    const matches = __ALL_PATIENTS.filter(p =>
        p.name.toLowerCase().includes(lower) ||
        (p.phone && p.phone.includes(q))
    ).slice(0, 10);

    let html = matches.map(p => `
        <div class="patient-dd-item" onclick="selectPatient(${p.id},'${p.name.replace(/'/g,"\\'")}','${p.phone || ''}')">
            <div>${p.name}</div>
            <div class="patient-dd-sub">${p.phone || 'No phone'}</div>
        </div>
    `).join('');

    // Always show "Add New Patient" at bottom
    html += `
        <div class="patient-dd-item patient-dd-add-new" onclick="showQuickPatientForm()" style="color:#6a0f70;font-weight:600;display:flex;align-items:center;gap:6px;border-top:1px solid #e2e8f0;">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v8M8 12h8"/></svg>
            Add New Patient
        </div>
    `;

    dd.innerHTML = html;
    dd.style.display = 'block';
}

function selectPatient(id, name, phone) {
    document.getElementById('am-patient').value          = id;
    document.getElementById('am-patient-search').value   = name;
    document.getElementById('am-mobile').value           = phone;
    document.getElementById('am-patient-dropdown').style.display = 'none';
}

// ── Quick patient create form ───────────────────────────────────
function showQuickPatientForm() {
    document.getElementById('am-patient-dropdown').style.display = 'none';
    document.getElementById('am-quick-patient-form').style.display = 'block';
    document.getElementById('am-qp-first').focus();
}

function hideQuickPatientForm() {
    document.getElementById('am-quick-patient-form').style.display = 'none';
    document.getElementById('am-qp-first').value  = '';
    document.getElementById('am-qp-last').value   = '';
    document.getElementById('am-qp-phone').value  = '';
    document.getElementById('am-qp-warn').style.display  = 'none';
    document.getElementById('am-qp-error').style.display = 'none';
}

function checkQuickPatientPhone() {
    const phone = document.getElementById('am-qp-phone').value.trim();
    const warn  = document.getElementById('am-qp-warn');
    if (!phone) { warn.style.display = 'none'; return; }
    const exists = __ALL_PATIENTS.some(p => p.phone === phone);
    warn.style.display = exists ? 'block' : 'none';
}

async function submitQuickPatient() {
    const first = document.getElementById('am-qp-first').value.trim();
    const last  = document.getElementById('am-qp-last').value.trim();
    const phone = document.getElementById('am-qp-phone').value.trim();
    const errEl = document.getElementById('am-qp-error');

    if (!first || !last || !phone) {
        errEl.textContent = 'First name, last name and phone are required.';
        errEl.style.display = 'block';
        return;
    }

    const btn = document.getElementById('am-qp-submit');
    btn.textContent = 'Saving…';
    btn.disabled = true;
    errEl.style.display = 'none';

    try {
        const res  = await fetch(window.__APPT_DATA.routes.patientQuickStore, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.__APPT_DATA.csrfToken,
                'Accept':       'application/json',
            },
            body: JSON.stringify({ first_name: first, last_name: last, phone }),
        });
        const data = await res.json();

        if (data.ok) {
            // Add to local list so subsequent searches find this patient
            __ALL_PATIENTS.push({ id: data.patient.id, name: data.patient.name, phone: data.patient.phone });
            selectPatient(data.patient.id, data.patient.name, data.patient.phone);
            hideQuickPatientForm();

            // Phone conflict found server-side — warn after selection
            if (data.phone_conflict) {
                const warn = document.getElementById('am-conflict-warn');
                warn.textContent = 'Another patient with this phone number already exists. Appointment saved anyway.';
                warn.style.display = 'block';
            }
        } else {
            errEl.textContent = data.message || 'Failed to create patient.';
            errEl.style.display = 'block';
        }
    } catch {
        errEl.textContent = 'Network error. Please try again.';
        errEl.style.display = 'block';
    } finally {
        btn.textContent = 'Create & Select';
        btn.disabled = false;
    }
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
    ['consultation','treatment','follow-up'].forEach(t => {
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

        // Use flatpickr API to set date (required when altInput: true)
        if (window.amDatePicker) {
            window.amDatePicker.setDate(apptDate, true);
        } else {
            document.getElementById('am-date').value = apptDate;
        }
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

// ── Flatpickr for appointment date (DD-MM-YYYY display, YYYY-MM-DD value) ──
document.addEventListener('DOMContentLoaded', function () {
    window.amDatePicker = flatpickr('#am-date', {
        dateFormat:  'Y-m-d',   // actual value sent to backend
        altInput:    true,       // show a human-friendly display input
        altFormat:   'd-m-Y',   // DD-MM-YYYY shown to user
        allowInput:  false,
        defaultDate: new Date(),
    });
});
</script>