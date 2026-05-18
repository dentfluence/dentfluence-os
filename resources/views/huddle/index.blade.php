@extends('layouts.app')

@section('title', 'Daily Huddle — ' . $today->format('l, d F Y'))

@section('head-extra')
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
@endsection

@push('scripts')
<style>
.hd-escape { margin: -28px -32px -48px; }
@media(max-width:767px){ .hd-escape { margin: -20px -16px -40px; } }
.hd svg { display: inline-block !important; flex-shrink: 0 !important; overflow: visible !important; }
.hd {
    --dp: #380740; --dm: #6a0f70; --dl: #e8d5f0; --dp2: #f5eefa;
    --tm: #1a0820; --ts: #7a6082; --border: #ede0f5; --white: #fff;
    --red-bg: #fff5f5; --amber-bg: #fffbeb;
    font-family: 'DM Sans', sans-serif; background: #f7f0fc; min-height: 100vh; color: var(--tm);
}
.hd-hero { background: linear-gradient(135deg, #380740 0%, #6a0f70 100%); padding: 1.75rem 2rem 1.5rem; display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.hd-hero-title { font-family: 'Cormorant Garamond', serif; font-size: 2.4rem; font-weight: 700; color: #fff; margin: 0 0 .15rem; line-height: 1; }
.hd-hero-sub { color: rgba(255,255,255,.6); font-size: .82rem; margin: 0; }
.hd-hero-date { background: rgba(255,255,255,.13); border: 1px solid rgba(255,255,255,.22); color: #e8d5f0; padding: .35rem 1rem; border-radius: 8px; font-size: .8rem; letter-spacing: .04em; display: inline-flex; align-items: center; gap: .4rem; margin-bottom: .5rem; }
.hd-clock { font-family: 'Cormorant Garamond', serif; font-size: 1.6rem; color: #fff; opacity: .9; letter-spacing: .06em; display: block; }
.hd-qa { background: var(--dp); padding: .65rem 2rem; display: flex; gap: .55rem; flex-wrap: wrap; align-items: center; border-bottom: 1px solid rgba(255,255,255,.07); }
.hd-qa-label { color: rgba(255,255,255,.4); font-size: .68rem; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; margin-right: .2rem; }
.hd-qa a { display: inline-flex; align-items: center; gap: .38rem; background: rgba(255,255,255,.09); border: 1px solid rgba(255,255,255,.15); color: #fff !important; font-size: .77rem; font-weight: 500; padding: .36rem .82rem; border-radius: 7px; text-decoration: none !important; transition: background .15s; }
.hd-qa a:hover { background: rgba(255,255,255,.18); }
.hd-body { padding: 1.5rem 2rem 3rem; max-width: 1400px; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem; }
.hd-sec { display: flex; align-items: center; gap: .55rem; margin-bottom: .85rem; }
.hd-sec-title { font-size: .7rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--dm); }
.hd-sec-sub { font-size: .74rem; color: var(--ts); }
.hd-3col { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1rem; align-items: start; }
@media(max-width:1000px){ .hd-3col { grid-template-columns: 1fr; } }
.hd-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; align-items: start; }
@media(max-width:900px){ .hd-2col { grid-template-columns: 1fr; } }
.hd-card { background: var(--white); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; box-shadow: 0 1px 5px rgba(56,7,64,.07); }
.hd-ch { display: flex; align-items: center; padding: .82rem 1.1rem .7rem; border-bottom: 1px solid var(--border); gap: .52rem; }
.hd-ch-ico { width: 27px; height: 27px; background: var(--dp2); border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.hd-ch h3 { font-family: 'Cormorant Garamond', serif; font-size: .98rem; font-weight: 700; color: var(--dp); margin: 0; flex: 1; }
.hd-badge { background: var(--dm); color: #fff; font-size: .66rem; font-weight: 700; padding: .1rem .48rem; border-radius: 999px; }
.hd-badge-red { background: #c53030; }
.hd-pr { display: flex; align-items: center; gap: .65rem; padding: .58rem 1.1rem; border-bottom: 1px solid #f4eaf9; font-size: .81rem; }
.hd-pr:last-child { border-bottom: none; }
.hd-pr-time { font-weight: 700; color: var(--dm); font-size: .79rem; min-width: 3rem; font-variant-numeric: tabular-nums; }
.hd-pr-name { font-weight: 500; color: var(--tm); text-decoration: none; }
.hd-pr-name:hover { color: var(--dm); }
.hd-pr-doc { font-size: .72rem; color: var(--ts); }
.hd-p { display:inline-block; padding:.1rem .5rem; border-radius:999px; font-size:.65rem; font-weight:700; white-space:nowrap; }
.hd-p-scheduled  { background:#f3f0ff; color:#5b21b6; }
.hd-p-checkin    { background:#dbeafe; color:#1e40af; }
.hd-p-in_chair   { background:#e0f2fe; color:#0369a1; }
.hd-p-checkout   { background:#fef9c3; color:#854d0e; }
.hd-p-done       { background:#dcfce7; color:#166534; }
.hd-p-cancelled  { background:#fee2e2; color:#991b1b; }
.hd-p-no_show    { background:#f3f4f6; color:#6b7280; }
.hd-p-urgent     { background:#fee2e2; color:#991b1b; }
.hd-p-high       { background:#fef3c7; color:#92400e; }
.hd-p-medium     { background:#dbeafe; color:#1e40af; }
.hd-p-low        { background:#dcfce7; color:#166534; }
.hd-p-in_progress{ background:#dbeafe; color:#1e40af; }
.hd-instr { font-size:.73rem; color:var(--ts); font-style:italic; cursor:pointer; min-height:1.1em; }
.hd-instr:empty::before { content:'Add note…'; opacity:.35; }
.hd-instr-inp { font-size:.73rem; font-family:'DM Sans',sans-serif; border:1px solid var(--dm); border-radius:5px; padding:.18rem .38rem; outline:none; width:100%; }
.hd-stats { display: grid; grid-template-columns: repeat(4,1fr); gap: .7rem; margin-bottom: .9rem; }
@media(max-width:700px){ .hd-stats { grid-template-columns: repeat(2,1fr); } }
.hd-stat { background: var(--dp2); border: 1px solid var(--border); border-radius: 10px; padding: .8rem .7rem; text-align: center; }
.hd-stat-v { font-family:'Cormorant Garamond',serif; font-size:2rem; font-weight:700; color:var(--dm); line-height:1; }
.hd-stat-l { font-size:.66rem; color:var(--ts); text-transform:uppercase; letter-spacing:.05em; margin-top:.18rem; }
.hd-al { display:flex; align-items:flex-start; gap:.5rem; padding:.55rem .65rem; border-radius:8px; margin-bottom:.38rem; font-size:.8rem; line-height:1.4; }
.hd-al:last-child { margin-bottom:0; }
.hd-al-error   { background:var(--red-bg);   border:1px solid #fecaca; color:#9b1c1c; }
.hd-al-warning { background:var(--amber-bg); border:1px solid #fde68a; color:#a06010; }
.hd-al-clear   { text-align:center; padding:1.5rem; color:var(--ts); font-size:.8rem; }
.hd-tabs { display:flex; padding:.48rem .75rem 0; background:var(--dp2); border-bottom:1px solid var(--border); gap:2px; }
.hd-tab { padding:.35rem .8rem; font-size:.74rem; font-weight:600; border-radius:6px 6px 0 0; border:1px solid transparent; border-bottom:none; cursor:pointer; background:transparent; color:var(--ts); transition:background .12s; }
.hd-tab.active { background:#fff; border-color:var(--border); border-bottom-color:#fff; color:var(--dm); }
.hd-tab:hover:not(.active) { background:rgba(106,15,112,.07); }
.hd-tdot { width:6px; height:6px; border-radius:50%; display:inline-block; margin-right:3px; vertical-align:middle; }
.hd-tdot-wins { background:#16a34a; } .hd-tdot-lows { background:#d97706; }
.hd-tdot-failures { background:#dc2626; } .hd-tdot-concerns { background:#7c3aed; }
.hd-ni { display:flex; justify-content:space-between; align-items:flex-start; padding:.52rem .6rem; border-bottom:1px solid #f4eaf9; font-size:.8rem; gap:.5rem; }
.hd-ni:last-child { border-bottom:none; }
.hd-ni-meta { font-size:.67rem; color:var(--ts); white-space:nowrap; }
.hd-nform { display:flex; gap:.42rem; padding:.7rem .8rem; border-top:1px solid var(--border); }
.hd-ninp { flex:1; font-size:.79rem; font-family:'DM Sans',sans-serif; border:1px solid var(--border); border-radius:7px; padding:.38rem .65rem; outline:none; transition:border-color .12s; }
.hd-ninp:focus { border-color:var(--dm); }
.hd-nbtn { background:var(--dm); color:#fff; border:none; border-radius:7px; padding:.38rem .8rem; font-size:.77rem; font-weight:600; cursor:pointer; }
.hd-nbtn:hover { background:var(--dp); }
.hd-nbtn:disabled { opacity:.45; cursor:not-allowed; }
.hd-tr { display:flex; align-items:center; gap:.65rem; padding:.62rem 1.1rem; border-bottom:1px solid #f4eaf9; font-size:.81rem; }
.hd-tr:last-child { border-bottom:none; }
.hd-tck { width:16px; height:16px; border:2px solid var(--border); border-radius:4px; flex-shrink:0; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background .12s,border-color .12s; }
.hd-tck.done { background:var(--dm); border-color:var(--dm); }
.hd-tck svg { display:none; }
.hd-tck.done svg { display:block; }
.hd-tn { flex:1; color:var(--tm); }
.hd-tn.done { text-decoration:line-through; color:var(--ts); }
.hd-tt { font-size:.7rem; color:var(--ts); }
.hd-cr { display:flex; align-items:center; gap:.7rem; padding:.62rem 1.1rem; border-bottom:1px solid #f4eaf9; font-size:.81rem; }
.hd-cr:last-child { border-bottom:none; }
.hd-ctype { font-size:.63rem; padding:.08rem .42rem; border-radius:4px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; white-space:nowrap; }
.hd-ctype-referral    { background:#dbeafe; color:#1e3a8a; }
.hd-ctype-testimonial { background:#dcfce7; color:#14532d; }
.hd-ctype-follow_up   { background:#ede9fe; color:#4c1d95; }
.hd-cdone { margin-left:auto; font-size:.69rem; color:var(--dm); cursor:pointer; background:none; border:1px solid var(--border); border-radius:5px; padding:.15rem .48rem; white-space:nowrap; transition:background .12s; flex-shrink:0; }
.hd-cdone:hover { background:var(--dp2); }
.hd-soon { display:flex; align-items:center; justify-content:center; gap:.5rem; padding:1.4rem; font-size:.78rem; color:var(--ts); background:repeating-linear-gradient(45deg,transparent,transparent 8px,rgba(106,15,112,.025) 8px,rgba(106,15,112,.025) 16px); border-top:1px solid var(--border); }
.hd-empty { text-align:center; padding:1.8rem 1rem; color:var(--ts); font-size:.81rem; }
</style>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('huddleBooking', () => ({
        treatmentCategories: @json($treatmentCategories),
        timeSlots: @json($timeSlots),
        newAppt: {
            open: false, date: '{{ $today->toDateString() }}', time: '09:00',
            patientId: null, patientSearch: '', patientPhone: '',
            patientResults: [], showPatientDropdown: false,
            doctorId: '', duration: '30', type: 'consultation',
            treatmentCategoryId: '', treatmentId: '',
            notes: '', submitting: false, errors: [],
        },
        filteredTreatments() {
            if (!this.newAppt.treatmentCategoryId) return [];
            const cat = this.treatmentCategories.find(c => String(c.id) === String(this.newAppt.treatmentCategoryId));
            return cat ? (cat.treatments ?? []) : [];
        },
        applyTreatmentDefaults() {
            const t = this.filteredTreatments().find(t => String(t.id) === String(this.newAppt.treatmentId));
            if (t?.default_duration_minutes) {
                const d = [15,30,45,60,90,120].reduce((p,c) => Math.abs(c-t.default_duration_minutes) < Math.abs(p-t.default_duration_minutes) ? c : p);
                this.newAppt.duration = String(d);
            }
        },
        async searchPatients() {
            const q = this.newAppt.patientSearch.trim();
            if (q.length < 2) { this.newAppt.patientResults = []; return; }
            try {
                const res = await fetch(`/patients/search?q=${encodeURIComponent(q)}&json=1`);
                if (res.ok) this.newAppt.patientResults = await res.json();
            } catch(e) {}
        },
        selectPatient(p) {
            this.newAppt.patientId = p.id;
            this.newAppt.patientSearch = p.name;
            this.newAppt.patientPhone = p.phone ?? '';
            this.newAppt.patientResults = [];
            this.newAppt.showPatientDropdown = false;
        },
        async submitAppointment() {
            this.newAppt.errors = [];
            if (!this.newAppt.patientId)   this.newAppt.errors.push('Please select a patient.');
            if (!this.newAppt.doctorId)    this.newAppt.errors.push('Please select a doctor.');
            if (!this.newAppt.date)        this.newAppt.errors.push('Date is required.');
            if (!this.newAppt.time)        this.newAppt.errors.push('Time is required.');
            if (!this.newAppt.notes.trim()) this.newAppt.errors.push('Notes are required.');
            if (this.newAppt.type === 'treatment') {
                if (!this.newAppt.treatmentCategoryId) this.newAppt.errors.push('Please select a treatment category.');
                if (!this.newAppt.treatmentId)         this.newAppt.errors.push('Please select a treatment.');
            }
            if (this.newAppt.errors.length) return;
            this.newAppt.submitting = true;
            try {
                const res = await fetch('/appointments', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        patient_id: this.newAppt.patientId,
                        doctor_id: this.newAppt.doctorId,
                        appointment_date: this.newAppt.date,
                        appointment_time: this.newAppt.time,
                        duration_minutes: this.newAppt.duration,
                        type: this.newAppt.type,
                        notes: this.newAppt.notes,
                        treatment_category_id: this.newAppt.treatmentCategoryId || null,
                        treatment_id: this.newAppt.treatmentId || null,
                    }),
                });
                const data = await res.json();
                if (res.ok) {
                    this.newAppt.open = false;
                    window.location.reload();
                } else {
                    this.newAppt.errors = data.errors ? Object.values(data.errors).flat() : [data.message ?? 'Something went wrong.'];
                }
            } catch(e) {
                this.newAppt.errors = ['Network error. Please try again.'];
            } finally {
                this.newAppt.submitting = false;
            }
        },
        init() {
            window.addEventListener('open-booking-modal', () => {
                this.newAppt.open = true;
                this.newAppt.date = '{{ $today->toDateString() }}';
            });
        }
    }));
});
</script>
@endpush

@section('content')
{{-- ============================================================
     Single root div — x-data here handles modal dispatch only
     All other x-data blocks are self-contained inside the page
     ============================================================ --}}
<div class="hd hd-escape"
     x-data="{ showAddAppointment: false, showAddPatient: false }"
     x-on:open-add-appointment.window="showAddAppointment = true"
     x-on:open-add-patient.window="showAddPatient = true">

{{-- HERO --}}
<div class="hd-hero">
    <div>
        <h1 class="hd-hero-title">Daily Huddle</h1>
        <p class="hd-hero-sub">Today's plan. Yesterday's reality.</p>
    </div>
    <div style="text-align:right;">
        <div class="hd-hero-date">
            <svg style="display:inline-block!important;flex-shrink:0;width:12px;height:12px;" width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            {{ $today->format('d M Y') }}
        </div>
        <span class="hd-clock"
              x-data
              x-text="new Date().toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'})"
              x-init="setInterval(()=>$el.textContent=new Date().toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'}),10000)">
        </span>
    </div>
</div>

{{-- QUICK ACTIONS --}}
<div class="hd-qa">
    <span class="hd-qa-label">⚡ Quick Actions</span>
    <a href="#" @click.prevent="$dispatch('open-add-patient')">
        <svg style="display:inline-block!important;flex-shrink:0;width:12px;height:12px;" width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
        New Patient
    </a>
    <a href="#" @click.prevent="window.dispatchEvent(new CustomEvent('open-booking-modal'))">
    <svg style="display:inline-block!important;flex-shrink:0;width:12px;height:12px;" width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
    New Appointment
    </a>
    <a href="#">
        <svg style="display:inline-block!important;flex-shrink:0;width:12px;height:12px;" width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
        Lab Entry
    </a>
    <a href="{{ route('patients.index') }}">
        <svg style="display:inline-block!important;flex-shrink:0;width:12px;height:12px;" width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        Find Patient
    </a>
</div>

<div class="hd-body">

    {{-- ── TODAY ── --}}
    <div>
        <div class="hd-sec">
            <svg style="display:inline-block!important;flex-shrink:0;width:16px;height:16px;" width="16" height="16" fill="none" stroke="#6a0f70" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <span class="hd-sec-title">TODAY</span>
            <span class="hd-sec-sub">Focus for the day</span>
        </div>
        <div class="hd-3col">

            {{-- Today's Patients --}}
            <div class="hd-card">
                <div class="hd-ch">
                    <div class="hd-ch-ico">
                        <svg style="display:inline-block!important;flex-shrink:0;width:13px;height:13px;" width="13" height="13" fill="none" stroke="#6a0f70" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <h3>Today's Patients</h3>
                    <span class="hd-badge">{{ $todaysAppointments->count() }}</span>
                </div>
                @forelse($todaysAppointments as $appt)
                <div class="hd-pr"
                     x-data="{
                        editing: false,
                        instruction: '{{ addslashes($appt->staff_instruction ?? '') }}',
                        original: '{{ addslashes($appt->staff_instruction ?? '') }}',
                        saving: false,
                        async save() {
                            this.saving = true;
                            await fetch('{{ route('huddle.appointments.instruction', $appt->id) }}', {
                                method: 'PATCH',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                                },
                                body: JSON.stringify({ staff_instruction: this.instruction })
                            });
                            this.original = this.instruction;
                            this.editing = false;
                            this.saving = false;
                        }
                     }">
                    <span class="hd-pr-time">{{ $appt->appointment_time ? \Carbon\Carbon::parse($appt->appointment_time)->format('H:i') : '—' }}</span>
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;gap:.3rem;flex-wrap:wrap;">
                            <a href="{{ route('patients.show', $appt->patient_id) }}" class="hd-pr-name">{{ $appt->patient->name ?? '—' }}</a>
                            @if(!empty($appt->patient->medical_alert))
                                <span title="{{ $appt->patient->medical_alert }}" style="color:#c53030;font-size:.7rem;cursor:help;">⚠</span>
                            @endif
                            <span class="hd-p hd-p-{{ $appt->status }}">{{ str_replace('_', ' ', ucfirst($appt->status)) }}</span>
                        </div>
                        <span class="hd-pr-doc">{{ $appt->doctor->name ?? '—' }}</span>
                    </div>
                    <div style="min-width:80px;max-width:120px;">
                        <div x-show="!editing" @click="editing=true" class="hd-instr" x-text="instruction || ''"></div>
                        <template x-if="editing">
                            <div>
                                <input x-model="instruction"
                                       @keydown.enter="save()"
                                       @keydown.escape="instruction=original;editing=false"
                                       class="hd-instr-inp"
                                       placeholder="Add note…"
                                       x-init="$el.focus()"
                                       :disabled="saving">
                                <span @click="save()"
                                      style="font-size:.67rem;color:var(--dm);cursor:pointer;text-decoration:underline;"
                                      x-text="saving ? 'Saving…' : 'Save'"></span>
                            </div>
                        </template>
                    </div>
                </div>
                @empty
                <div class="hd-empty">No appointments today.</div>
                @endforelse
            </div>

            {{-- Lab Due Today --}}
            <div class="hd-card">
                <div class="hd-ch">
                    <div class="hd-ch-ico">
                        <svg style="display:inline-block!important;flex-shrink:0;width:13px;height:13px;" width="13" height="13" fill="none" stroke="#6a0f70" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                    </div>
                    <h3>Lab Due Today</h3>
                    <span class="hd-badge">{{ $labsDueToday->count() }}</span>
                </div>
                @forelse($labsDueToday as $lab)
                <div class="hd-pr">
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:.8rem;color:var(--dp);">{{ $lab->case_number }}</div>
                        <div style="font-size:.73rem;color:var(--ts);">{{ $lab->patient_name ?? '' }}</div>
                    </div>
                    <div style="text-align:right;">
                        <span class="hd-p hd-p-{{ $lab->status }}">{{ ucfirst(str_replace('_', ' ', $lab->status)) }}</span>
                    </div>
                </div>
                @empty
                <div class="hd-soon">
                    <svg style="display:inline-block!important;flex-shrink:0;width:13px;height:13px;" width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Lab module coming soon
                </div>
                @endforelse
            </div>

            {{-- Critical Alerts --}}
            <div class="hd-card">
                <div class="hd-ch">
                    <div class="hd-ch-ico" style="background:#fff0f0;">
                        <svg style="display:inline-block!important;flex-shrink:0;width:13px;height:13px;" width="13" height="13" fill="none" stroke="#c53030" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <h3>Critical Alerts</h3>
                    @if($criticalAlerts->isNotEmpty())
                        <span class="hd-badge hd-badge-red">{{ $criticalAlerts->count() }}</span>
                    @endif
                </div>
                <div style="padding:.75rem .9rem;">
                    @forelse($criticalAlerts as $alert)
                    <div class="hd-al hd-al-{{ $alert['level'] }}">
                        <svg style="display:inline-block!important;flex-shrink:0;width:14px;height:14px;" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span>{{ $alert['message'] }}</span>
                    </div>
                    @empty
                    <div class="hd-al-clear">
                        <svg style="display:inline-block!important;flex-shrink:0;width:28px;height:28px;" width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        All clear — no critical alerts.
                    </div>
                    @endforelse
                </div>
            </div>

        </div>
    </div>

    {{-- ── YESTERDAY ── --}}
    <div>
        <div class="hd-sec">
            <svg style="display:inline-block!important;flex-shrink:0;width:16px;height:16px;" width="16" height="16" fill="none" stroke="#6a0f70" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            <span class="hd-sec-title">YESTERDAY</span>
            <span class="hd-sec-sub">{{ \Carbon\Carbon::yesterday()->format('d M') }} — what happened</span>
        </div>
        <div class="hd-card">
            <div style="padding:.9rem 1.1rem;">
                <div class="hd-stats">
                    <div class="hd-stat">
                        <div class="hd-stat-v">{{ $yesterdaySummary['patients_treated'] }}</div>
                        <div class="hd-stat-l">Patients Treated</div>
                    </div>
                    <div class="hd-stat">
                        <div class="hd-stat-v">{{ $yesterdaySummary['treatments_done']->sum() }}</div>
                        <div class="hd-stat-l">Cases Done</div>
                    </div>
                    <div class="hd-stat">
                        <div class="hd-stat-v">{{ $yesterdaySummary['lab_sent'] }}</div>
                        <div class="hd-stat-l">Lab Sent</div>
                    </div>
                    <div class="hd-stat">
                        <div class="hd-stat-v">{{ $yesterdaySummary['lab_received'] }}</div>
                        <div class="hd-stat-l">Lab Received</div>
                    </div>
                </div>
                @if($yesterdaySummary['treatments_done']->isNotEmpty())
                    <div style="font-size:.68rem;color:var(--ts);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.45rem;">Treatment breakdown</div>
                    <div style="display:flex;flex-wrap:wrap;gap:.3rem;">
                        @foreach($yesterdaySummary['treatments_done'] as $tid => $count)
                            <span style="background:var(--dp2);border:1px solid var(--border);border-radius:6px;padding:.18rem .55rem;font-size:.72rem;color:var(--dp);">
                                Treatment #{{ $tid }} <strong>×{{ $count }}</strong>
                            </span>
                        @endforeach
                    </div>
                @else
                    <p style="font-size:.79rem;color:var(--ts);text-align:center;margin:0;padding:.3rem 0;">No completed treatments yesterday.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- ── TEAM NOTES + TASKS ── --}}
    <div>
        <div class="hd-sec">
            <svg style="display:inline-block!important;flex-shrink:0;width:16px;height:16px;" width="16" height="16" fill="none" stroke="#6a0f70" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span class="hd-sec-title">TEAM NOTES</span>
            <span class="hd-sec-sub">Quick notes from the team</span>
        </div>
        <div class="hd-2col">

            {{-- Team Notes --}}
            <div class="hd-card"
                 x-data="{
                    activeTab: 'wins',
                    tabs: ['wins','lows','failures','concerns'],
                    newNote: '',
                    submitting: false,
                    notes: {
                        wins:     @json($huddleNotes->get('wins', collect())->values()),
                        lows:     @json($huddleNotes->get('lows', collect())->values()),
                        failures: @json($huddleNotes->get('failures', collect())->values()),
                        concerns: @json($huddleNotes->get('concerns', collect())->values()),
                    },
                    async addNote() {
                        if (!this.newNote.trim()) return;
                        this.submitting = true;
                        const res = await fetch('{{ route('huddle.notes.store') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                            },
                            body: JSON.stringify({
                                category: this.activeTab,
                                body: this.newNote,
                                date: '{{ $today->toDateString() }}'
                            })
                        });
                        const d = await res.json();
                        this.notes[this.activeTab].push({
                            body: d.body,
                            author: { name: d.author },
                            created_at_formatted: d.created_at
                        });
                        this.newNote = '';
                        this.submitting = false;
                    }
                 }">
                <div class="hd-tabs">
                    <template x-for="tab in tabs" :key="tab">
                        <button class="hd-tab" :class="{ active: activeTab === tab }" @click="activeTab = tab">
                            <span class="hd-tdot" :class="'hd-tdot-' + tab"></span>
                            <span x-text="tab.charAt(0).toUpperCase() + tab.slice(1)"></span>
                            <span x-show="notes[tab].length" style="margin-left:2px;font-weight:700;" x-text="'(' + notes[tab].length + ')'"></span>
                        </button>
                    </template>
                </div>
                <div style="min-height:110px;">
                    <template x-for="tab in tabs" :key="tab">
                        <div x-show="activeTab === tab">
                            <template x-if="notes[tab].length === 0">
                                <p style="font-size:.79rem;color:var(--ts);text-align:center;padding:1.1rem 0;margin:0;">No notes yet — add one below.</p>
                            </template>
                            <template x-for="(note, i) in notes[tab]" :key="i">
                                <div class="hd-ni">
                                    <span x-text="note.body" style="flex:1;"></span>
                                    <span class="hd-ni-meta" x-text="(note.author?.name ?? 'You') + ' · ' + (note.created_at_formatted ?? '')"></span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
                <div class="hd-nform">
                    <input x-model="newNote"
                           class="hd-ninp"
                           :placeholder="'Add a ' + activeTab + ' note…'"
                           @keydown.enter="addNote()"
                           :disabled="submitting">
                    <button class="hd-nbtn" @click="addNote()" :disabled="submitting || !newNote.trim()">
                        <span x-text="submitting ? '…' : 'Add'"></span>
                    </button>
                </div>
            </div>

            {{-- My Tasks --}}
            <div class="hd-card"
                 x-data="{
                    tasks: @json($myTasks->values()),
                    toggle(i) { this.tasks[i].done = !this.tasks[i].done; }
                 }">
                <div class="hd-ch">
                    <div class="hd-ch-ico">
                        <svg style="display:inline-block!important;flex-shrink:0;width:13px;height:13px;" width="13" height="13" fill="none" stroke="#6a0f70" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    </div>
                    <h3>My Tasks Today</h3>
                    <span class="hd-badge" x-text="tasks.filter(t => !t.done).length + ' left'"></span>
                </div>
                <template x-if="tasks.length === 0">
                    <div class="hd-soon">
                        <svg style="display:inline-block!important;flex-shrink:0;width:12px;height:12px;" width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        No pending tasks
                    </div>
                </template>
                <template x-for="(task, i) in tasks" :key="task.id">
                    <div class="hd-tr">
                        <div class="hd-tck" :class="{ done: task.done }" @click="toggle(i)">
                            <svg style="display:inline-block!important;flex-shrink:0;width:8px;height:8px;" width="8" height="8" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <span class="hd-tn" :class="{ done: task.done }" x-text="task.title"></span>
                        <span class="hd-p" :class="'hd-p-' + (task.priority || 'medium')" x-text="task.priority || 'medium'"></span>
                        <span class="hd-tt" x-text="task.due_time ? task.due_time.substring(0,5) : ''"></span>
                    </div>
                </template>
            </div>

        </div>
    </div>

    {{-- ── COMMUNICATION LIST ── --}}
    <div>
        <div class="hd-sec">
            <svg style="display:inline-block!important;flex-shrink:0;width:16px;height:16px;" width="16" height="16" fill="none" stroke="#6a0f70" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
            <span class="hd-sec-title">COMMUNICATION LIST</span>
            <span class="hd-sec-sub">Calls due today</span>
        </div>
        <div class="hd-card"
             x-data="{
                items: @json($commList->values()),
                markDone(id, i) {
                    this.items.splice(i, 1);
                    fetch('/communication-entries/' + id + '/done', {
                        method: 'PATCH',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                    });
                }
             }">
            <template x-if="items.length === 0">
                <div class="hd-soon">
                    <svg style="display:inline-block!important;flex-shrink:0;width:12px;height:12px;" width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Communication module coming soon
                </div>
            </template>
            <template x-for="(item, i) in items" :key="item.id">
                <div class="hd-cr">
                    <span class="hd-ctype" :class="'hd-ctype-' + item.type" x-text="item.type.replace('_', ' ')"></span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:500;font-size:.81rem;" x-text="item.patient_name"></div>
                        <div style="font-size:.72rem;color:var(--ts);" x-text="item.phone ?? ''"></div>
                    </div>
                    <button class="hd-cdone" @click="markDone(item.id, i)">Done ✓</button>
                </div>
            </template>
        </div>
    </div>

</div>{{-- /hd-body --}}

{{-- Modals --}}
<div x-data="huddleBooking">
    @include('partials.appointment-booking-modal')
</div>
@include('partials.add-patient-modal')

</div>{{-- /hd hd-escape --}}
@endsection