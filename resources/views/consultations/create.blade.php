@extends('layouts.app')

@section('page-title', isset($consultation) ? 'Edit Consultation' : 'New Consultation')

@section('head-extra')
<style>
    #df-topbar        { display:none !important; }
    #df-content-inner { padding:0 !important; max-width:100% !important; }
    #df-content-area  { background:#f4f5f7 !important; }
    * { box-sizing:border-box; }
    [x-cloak] { display:none !important; }

    /* ── Topbar ── */
    #ctopbar {
        position:sticky; top:0; z-index:100; background:#fff;
        border-bottom:1px solid #e5e7eb;
        height:52px; display:flex; align-items:center;
        justify-content:space-between; padding:0 24px;
    }
    .ctb-left  { display:flex; align-items:center; gap:12px; }
    /* Shared "← Back" button — identical across all consultation workflows */
    .btn-outline {
        padding:6px 14px; font-size:12px; font-weight:600; font-family:'Inter',sans-serif;
        border:1px solid #d1d5db; background:#fff; color:#6b7280;
        border-radius:5px; cursor:pointer; text-decoration:none; transition:all .15s;
    }
    .btn-outline:hover { border-color:#6a0f70; color:#6a0f70; }
    .ctb-title { font-size:13px; font-weight:600; color:#374151; font-family:'Inter',sans-serif; }
    .ctb-sub   { font-size:11px; color:#9ca3af; font-family:'Inter',sans-serif; }
    .ctb-right { display:flex; align-items:center; gap:8px; }
    .btn-draft {
        padding:6px 16px; font-size:12px; font-weight:600; font-family:'Inter',sans-serif;
        border:1px solid #d1d5db; background:#fff; color:#6b7280;
        border-radius:5px; cursor:pointer; transition:all .15s;
    }
    .btn-draft:hover { border-color:#6a0f70; color:#6a0f70; }
    .btn-save {
        padding:6px 16px; font-size:12px; font-weight:600; font-family:'Inter',sans-serif;
        background:#6a0f70; color:#fff; border:none;
        border-radius:5px; cursor:pointer; transition:background .15s;
    }
    .btn-save:hover { background:#380740; }

    /* ── Patient strip ── */
    #pstrip {
        background:#fff; border-bottom:1px solid #e5e7eb;
        padding:12px 24px; display:flex; align-items:center; gap:16px; flex-wrap:wrap;
    }
    .ps-avatar {
        width:44px; height:44px; border-radius:50%;
        background:linear-gradient(135deg,#6a0f70,#380740);
        display:flex; align-items:center; justify-content:center;
        color:#fff; font-size:16px; font-weight:600;
        font-family:'Cormorant Garamond',serif; flex-shrink:0;
    }
    .ps-name { font-size:15px; font-weight:700; color:#111827; font-family:'Cormorant Garamond',serif; }
    .ps-meta { font-size:11px; color:#9ca3af; font-family:'Inter',sans-serif; margin-top:2px; }
    .ps-divider { width:1px; height:40px; background:#f0f0f0; flex-shrink:0; }
    .ps-alert {
        font-size:11px; font-family:'Inter',sans-serif;
        padding:5px 10px; border-radius:4px; display:flex; align-items:flex-start; gap:6px;
    }
    .ps-alert.warn { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
    .ps-alert.ok   { background:#f0fdf4; color:#16a34a; }

    /* ── Page body ── */
    .consult-wrap {
        display:grid; grid-template-columns:1fr 270px;
        gap:16px; padding:16px 20px; align-items:start;
        max-width:100%;
    }
    .consult-main  { display:flex; flex-direction:column; gap:12px; min-width:0; }
    .consult-aside { display:flex; flex-direction:column; gap:10px; position:sticky; top:60px; min-width:0; }

    /* ── Cards ── */
    .c-card {
        background:#fff; border:1px solid #e5e7eb;
        border-radius:8px; overflow:visible;
    }
    .c-card-head {
        padding:11px 16px; border-bottom:1px solid #f3f4f6;
        display:flex; align-items:center; justify-content:space-between;
        background:#faf5fb; border-radius:8px 8px 0 0;
    }
    .c-head-label {
        font-size:10px; font-weight:700; letter-spacing:.07em;
        text-transform:uppercase; color:#6a0f70;
        font-family:'Inter',sans-serif;
        display:flex; align-items:center; gap:6px;
    }
    .c-num {
        width:18px; height:18px; border-radius:50%; background:#6a0f70;
        color:#fff; font-size:9px; font-weight:700;
        display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;
    }
    .c-body { padding:16px; }

    /* ── Consultation Type cards ── */
    .type-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; }
    .type-card {
        border:1.5px solid #e5e7eb; border-radius:8px; padding:12px 6px 10px;
        cursor:pointer; transition:all .15s; text-align:center; background:#fff;
        display:flex; flex-direction:column; align-items:center; gap:6px;
    }
    .type-card:hover { border-color:#b95cb7; background:#faf5fb; }
    .type-card.t-active          { border-color:#6a0f70; background:#faf5fb; }
    .type-card.t-active-emergency{ border-color:#dc2626; background:#fef2f2; }
    .type-icon {
        width:34px; height:34px; border-radius:50%;
        display:flex; align-items:center; justify-content:center;
    }
    .type-label {
        font-size:10px; font-weight:700; color:#6b7280;
        font-family:'Inter',sans-serif; line-height:1.3;
    }
    .type-card.t-active .type-label { color:#6a0f70; }
    .type-card.t-active-emergency .type-label { color:#dc2626; }
    .type-dot {
        width:6px; height:6px; border-radius:50%;
        border:1.5px solid #d1d5db; background:#fff;
    }
    .type-card.t-active .type-dot          { border-color:#6a0f70; background:#6a0f70; }
    .type-card.t-active-emergency .type-dot{ border-color:#dc2626; background:#dc2626; }

    /* ── Form inputs ── */
    .df-label {
        display:block; font-size:10px; font-weight:600; color:#6b7280;
        text-transform:uppercase; letter-spacing:.05em;
        font-family:'Inter',sans-serif; margin-bottom:4px;
    }
    .df-label .req { color:#dc2626; }
    .df-input {
        width:100%; border:1px solid #e5e7eb; border-radius:5px;
        padding:7px 10px; font-size:13px; font-family:'Inter',sans-serif;
        color:#111827; background:#fff; outline:none; transition:border-color .15s;
    }
    .df-input:focus { border-color:#6a0f70; box-shadow:0 0 0 3px rgba(106,15,112,.07); }
    .df-input::placeholder { color:#c4c9d0; }
    textarea.df-input { resize:vertical; }

    /* ── Severity pills ── */
    .sev-pill {
        flex:1; padding:5px 8px; border-radius:99px; font-size:11px; font-weight:600;
        border:1.5px solid #e5e7eb; background:#fff; cursor:pointer;
        transition:all .12s; color:#6b7280; font-family:'Inter',sans-serif; text-align:center;
    }
    .sev-mild     { background:#f0fdf4; border-color:#22c55e; color:#16a34a; }
    .sev-moderate { background:#fff7ed; border-color:#f97316; color:#ea580c; }
    .sev-severe   { background:#fef2f2; border-color:#ef4444; color:#dc2626; }

    /* ── Specialty module panels ── */
    .module-card { border:1px solid #e9d5ff; border-radius:8px; overflow:hidden; margin-bottom:10px; }
    .module-head {
        padding:9px 14px; background:#faf5fb;
        display:flex; align-items:center; justify-content:space-between;
        border-bottom:1px solid #f3e8ff;
    }
    .module-tag {
        font-size:10px; font-weight:700; text-transform:uppercase;
        letter-spacing:.07em; color:#6a0f70; font-family:'Inter',sans-serif;
        display:flex; align-items:center; gap:6px;
    }
    .module-body { padding:14px; }
    .module-grid   { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .module-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; }
    .mod-sel {
        width:100%; border:1px solid #e5e7eb; border-radius:4px;
        padding:5px 8px; font-size:11px; font-family:'Inter',sans-serif;
        color:#374151; background:#fff; outline:none;
    }
    .mod-sel:focus { border-color:#6a0f70; }

    /* ── Diagnosis columns ── */
    .diag-cols { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; }
    .diag-col { border:1px solid #e5e7eb; border-radius:7px; overflow:hidden; }
    .diag-col:focus-within { border-color:#6a0f70; }
    .diag-col-head {
        padding:7px 12px; font-size:10px; font-weight:700;
        text-transform:uppercase; letter-spacing:.06em;
        font-family:'Inter',sans-serif; border-bottom:1px solid transparent;
    }
    .diag-col-head.provisional   { background:#fff7ed; color:#ea580c; border-color:#fed7aa; }
    .diag-col-head.differential  { background:#eff6ff; color:#2563eb; border-color:#bfdbfe; }
    .diag-col-head.final         { background:#f0fdf4; color:#16a34a; border-color:#bbf7d0; }
    .diag-col textarea.df-input  { border:none; border-radius:0; font-size:12px; padding:10px 12px; }
    .diag-col textarea.df-input:focus { box-shadow:none; border-color:transparent; }

    /* ── CTA bar ── */
    #cta-bar {
        background:#fff; border-top:1px solid #e5e7eb;
        padding:12px 24px; display:flex; align-items:center; justify-content:space-between;
        position:sticky; bottom:0; z-index:50;
    }
    .btn-complete {
        padding:8px 20px; font-size:13px; font-weight:600;
        background:#6a0f70; color:#fff; border:none; border-radius:6px;
        cursor:pointer; font-family:'Inter',sans-serif;
        display:flex; align-items:center; gap:8px; transition:background .15s;
    }
    .btn-complete:hover { background:#380740; }

    /* ── Aside: Consult Assist ── */
    /* ── Consult Assist panel — light readable theme ── */
    .assist-card { background:#fff; border:1px solid #e9d5ff; border-radius:8px; overflow:hidden; }
    .assist-head {
        padding:10px 14px;
        background:#fff;
        border-bottom:2px solid #6a0f70;
        display:flex; align-items:center; gap:8px;
    }
    .assist-pulse {
        width:7px; height:7px; border-radius:50%; background:#6a0f70;
        animation:pulse 1.8s ease-in-out infinite; flex-shrink:0;
    }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.35} }
    .assist-title { font-size:11px; font-weight:800; color:#6a0f70; font-family:'Inter',sans-serif; letter-spacing:.06em; text-transform:uppercase; }
    .assist-body  { padding:12px; background:#faf5fb; }
    .assist-empty { font-size:12px; color:#9ca3af; font-family:'Inter',sans-serif; text-align:center; padding:20px 0 12px; line-height:1.7; }
    .suggest-chip {
        display:inline-flex; align-items:center; gap:4px;
        padding:4px 10px; border-radius:99px; font-size:11px; font-weight:600;
        border:1.5px solid #e9d5ff; background:#faf5fb; color:#6a0f70;
        font-family:'Inter',sans-serif; cursor:pointer; transition:all .15s; margin:2px;
    }
    .suggest-chip:hover           { background:#6a0f70; color:#fff; border-color:#6a0f70; }
    .suggest-chip.chip-accepted   { background:#6a0f70; color:#fff; border-color:#6a0f70; }

    /* ── Section headers used by partials ── */
    .sec-label {
        font-size:10px; font-weight:700; letter-spacing:.07em; text-transform:uppercase;
        color:#6a0f70; font-family:'Inter',sans-serif; display:flex; align-items:center; gap:6px;
    }
    .sec-num {
        width:20px; height:20px; border-radius:50%; background:#6a0f70; color:#fff;
        font-size:9px; font-weight:700; display:inline-flex; align-items:center;
        justify-content:center; flex-shrink:0;
    }
    .sec-summary { font-size:10px; color:#9ca3af; font-weight:400; text-transform:none; letter-spacing:0; }
    .sec-chevron { color:#9ca3af; transition:transform .2s; flex-shrink:0; }
    .sec-chevron.open { transform:rotate(180deg); }

    /* ── Tooth chart buttons ── */
    .tooth-btn {
        width:30px; height:30px; border:1.5px solid #e5e7eb; border-radius:4px;
        font-size:10px; font-weight:600; color:#6b7280;
        background:#fff; cursor:pointer; transition:all .12s;
        font-family:'Inter',sans-serif;
        display:inline-flex; align-items:center; justify-content:center;
        padding:0;
    }
    .tooth-btn:hover { border-color:#b95cb7; color:#6a0f70; background:#faf5fb; }
    .tooth-row { display:flex; justify-content:center; gap:3px; }
    .tooth-midline { width:1px; background:#e5e7eb; margin:0 6px; flex-shrink:0; }

    /* ── Adult/child (mixed dentition) per-tooth toggle ── */
    .tooth-slot { display:flex; flex-direction:column; align-items:center; gap:2px; }
    .tooth-dentition-toggle {
        width:18px; height:12px; border:1px solid #e5e7eb; border-radius:3px;
        font-size:7px; font-weight:800; line-height:1; color:#b0b0b8;
        background:#fafafa; cursor:pointer; font-family:'Inter',sans-serif;
        padding:0; display:inline-flex; align-items:center; justify-content:center;
        letter-spacing:.02em;
    }
    .tooth-dentition-toggle:hover { border-color:#b95cb7; color:#6a0f70; }
    .tooth-dentition-toggle.is-child { background:#fce7f3; border-color:#db2777; color:#db2777; }

    .aside-label {
        font-size:9px; font-weight:800; text-transform:uppercase;
        letter-spacing:.08em; color:#6a0f70; font-family:'Inter',sans-serif;
        margin:10px 0 5px; opacity:.75;
    }
    .assist-q {
        font-size:11px; color:#1f2937; font-family:'Inter',sans-serif;
        padding:5px 0; border-bottom:1px solid #ede9f5;
        display:flex; align-items:flex-start; gap:5px; line-height:1.5;
    }
    .assist-q::before { content:'→'; color:#6a0f70; flex-shrink:0; font-size:10px; margin-top:2px; }
    .assist-q:last-child { border-bottom:none; }
    .inv-chip {
        display:inline-flex; padding:3px 9px; border-radius:4px;
        font-size:10px; font-weight:700;
        background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0;
        font-family:'Inter',sans-serif; margin:2px;
    }

    /* ── Investigation section — simplified dropdown layout ── */
    .inv-simple-wrap { display:flex; gap:10px; align-items:flex-start; }
    .inv-dd-wrap { position:relative; flex-shrink:0; }
    .inv-dd-btn {
        display:flex; align-items:center; gap:6px;
        padding:8px 14px; border:1.5px solid #d8b4fe; border-radius:7px;
        background:#faf5fb; font-size:12px; font-weight:600;
        color:#6a0f70; font-family:'Inter',sans-serif;
        cursor:pointer; white-space:nowrap; transition:all .15s;
    }
    .inv-dd-btn:hover { background:#f3e8ff; border-color:#6a0f70; }
    .inv-dd-count { color:#6a0f70; font-weight:700; }
    .inv-dd-chevron { transition:transform .2s; }
    .inv-dd-chevron.open { transform:rotate(180deg); }
    .inv-dd-panel {
        position:absolute; top:calc(100% + 5px); left:0; z-index:9999;
        background:#fff; border:1.5px solid #e9d5ff; border-radius:8px;
        box-shadow:0 4px 16px rgba(106,15,112,.10);
        min-width:240px; max-height:320px; overflow-y:auto;
        padding:6px 0;
    }
    .inv-dd-item {
        display:flex; align-items:center; gap:8px;
        padding:7px 14px; cursor:pointer; transition:background .12s;
    }
    .inv-dd-item:hover { background:#faf5fb; }
    .inv-dd-check { width:13px; height:13px; accent-color:#6a0f70; flex-shrink:0; cursor:pointer; }
    .inv-dd-name { font-size:12px; font-weight:600; color:#374151; font-family:'Inter',sans-serif; }
    .inv-dd-desc { font-size:10px; color:#9ca3af; font-family:'Inter',sans-serif; margin-left:auto; }
    .inv-dd-sep { height:1px; background:#f3e8ff; margin:4px 10px; }
    .inv-notes-area {
        flex:1; padding:8px 12px; border:1.5px solid #e5e7eb; border-radius:7px;
        font-size:12px; font-family:'Inter',sans-serif; color:#374151;
        background:#fff; resize:vertical; outline:none; transition:border-color .15s;
        min-height:84px;
    }
    .inv-notes-area:focus { border-color:#b95cb7; }
    .inv-pills-wrap { display:flex; flex-wrap:wrap; gap:5px; margin-top:10px; }
    .inv-pill {
        display:inline-flex; align-items:center; gap:4px;
        padding:3px 8px 3px 10px; border-radius:999px;
        background:#f3e8ff; border:1px solid #d8b4fe;
        font-size:11px; font-weight:600; color:#6a0f70;
        font-family:'Inter',sans-serif;
    }
    .inv-pill-rm {
        background:none; border:none; cursor:pointer;
        font-size:9px; color:#9ca3af; padding:0 1px;
        line-height:1; transition:color .12s;
    }
    .inv-pill-rm:hover { color:#6a0f70; }

    /* ── Tooth chart summary pills (legend-style capsules) ── */
    .tc-legend-pill {
        display:inline-flex; align-items:center; gap:5px;
        padding:4px 10px 4px 7px; border-radius:999px;
        font-size:10.5px; font-weight:600; font-family:'Inter',sans-serif;
        border:1.5px solid; cursor:default;
    }
    .tc-legend-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
    .tc-tooth-chip {
        padding:2px 9px; border-radius:999px; background:transparent;
        border:1.5px solid; font-size:11px; font-weight:700;
        font-family:'Inter',sans-serif;
    }

    /* ── Tooth chart condition picker modal ── */
    .tc-cond-btn {
        width:100%; padding:8px 12px; border-radius:999px;
        cursor:pointer; transition:all .12s;
        display:flex; align-items:center; gap:9px;
        border:1.5px solid; text-align:left;
    }
    .tc-cond-dot  { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
    .tc-cond-label {
        font-size:11.5px; font-weight:600;
        font-family:'Inter',sans-serif; line-height:1; flex:1;
    }
    .tc-current-dot { width:6px; height:6px; border-radius:50%; flex-shrink:0; }
    .tc-current-label { font-size:10px; font-weight:600; font-family:'Inter',sans-serif; }
</style>
@endsection

@section('content')
<div x-data="consultForm()" x-init="init()"
     @prev-panel-updated.window="prevPanel = $event.detail">
<form id="cForm" method="POST"
      action="{{ isset($consultation)
          ? (isset($patient) && $patient->exists
              ? route('patients.consultations.update', [$patient, $consultation])
              : route('consultations.update', $consultation))
          : route('patients.consultations.store', $patient) }}"
      @submit="packModules">
    @csrf
    @if(isset($consultation)) @method('PUT') @endif
    <input type="hidden" name="patient_id"         value="{{ $patient->id }}">
    <input type="hidden" name="doctor_id"          value="{{ auth()->id() }}">
    <input type="hidden" name="branch_id"          value="{{ auth()->user()->branch_id ?? 1 }}">
    {{-- visit_type is derived in the controller from consultation_type; do not bind form.type here --}}
    <input type="hidden" name="consultation_type" x-bind:value="form.type">
    <input type="hidden" name="severity"          x-bind:value="form.severity">
    {{-- consultation_date is set by the visible date picker in the "Date of consultation" strip below.
         created_at (the staff entry log) is always stamped today by Laravel — backdating only affects the clinical date. --}}
    <input type="hidden" name="status"            value="completed">
    {{-- Specialty findings — populated by packModules() on submit --}}
    <input type="hidden" name="specialty_findings"   id="h-specialty-findings">
    <input type="hidden" name="accepted_specialties" id="h-accepted-specialties">

    {{-- ══ VALIDATION ERRORS ══ --}}
    @if($errors->any())
    <div style="background:#fef2f2;border-bottom:2px solid #fca5a5;padding:10px 24px;font-family:'Inter',sans-serif;">
        <div style="font-size:12px;font-weight:700;color:#dc2626;margin-bottom:4px;">Please fix the following errors:</div>
        <ul style="margin:0;padding-left:18px;">
            @foreach($errors->all() as $err)
            <li style="font-size:12px;color:#b91c1c;">{{ $err }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- ══ TOPBAR ══ --}}
    <div id="ctopbar">
        <div class="ctb-left">
            <a href="{{ route('patients.show', $patient) }}#consultation" class="btn-outline">← Back</a>
            <div>
                <div class="ctb-title">
                    {{ isset($consultation) ? 'Edit Consultation' : 'New Consultation' }}
                </div>
                <div class="ctb-sub">{{ $patient->name }}</div>
            </div>
        </div>
        <div class="ctb-right">
            <button type="button" class="btn-draft">Save Draft</button>
            <button type="submit" class="btn-save">Save Consultation</button>
        </div>
    </div>

    {{-- ══ PATIENT STRIP ══ --}}
    <div id="pstrip">
        <div class="ps-avatar">{{ $patient->initials ?? strtoupper(substr($patient->name,0,2)) }}</div>
        <div style="flex:1;min-width:200px;">
            <div class="ps-name">{{ $patient->name }}</div>
            <div class="ps-meta">
                @if($patient->age ?? false){{ $patient->age }}y &nbsp;·&nbsp; @endif
                @if($patient->gender ?? false){{ ucfirst($patient->gender) }} &nbsp;·&nbsp; @endif
                {{ $patient->phone ?? '' }}
                &nbsp;·&nbsp; Since {{ $patient->created_at->format('M Y') }}
            </div>
        </div>
        <div class="ps-divider"></div>
        @if($patient->medical_alert)
        <div class="ps-alert warn">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px;">
                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            <span><strong>Medical:</strong> {{ $patient->medical_alert }}</span>
        </div>
        @else
        <div class="ps-alert ok">No medical alerts</div>
        @endif
    </div>

    {{-- ══ DATE OF CONSULTATION (backdating) ══
         Default = today. Staff can pick any PAST date if they missed entering a visit.
         Future dates are blocked (max=today). The staff entry log (created_at) is always today. --}}
    <div style="background:#fffbeb;border-bottom:1px solid #fde68a;padding:8px 24px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;font-family:'Inter',sans-serif;">
        <svg width="14" height="14" fill="none" stroke="#b45309" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" style="flex-shrink:0;">
            <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
        <span style="font-size:11px;font-weight:600;color:#b45309;">Date of consultation</span>
        {{-- Visible date picker — defaults to today, cannot pick a future date --}}
        <input type="date" name="consultation_date" id="consult-date-input"
               value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}"
               onchange="document.getElementById('backdate-note').style.display = (this.value < '{{ now()->format('Y-m-d') }}') ? 'inline' : 'none';"
               style="font-size:11px;font-family:'Inter',sans-serif;border:1px solid #fcd34d;border-radius:5px;padding:4px 8px;background:#fff;color:#374151;cursor:pointer;">
        <span style="font-size:11px;color:#92400e;">Leave as today for a normal entry, or pick a past date for a missed entry.</span>
        <span id="backdate-note" style="display:none;font-size:11px;font-weight:700;color:#b45309;">⏱ Backdated — entry log still records today.</span>

        @if(isset($pastAppointments) && $pastAppointments->count())
        {{-- Optional shortcut: pick a past appointment to auto-fill the date above --}}
        <span style="font-size:11px;color:#92400e;border-left:1px solid #fde68a;padding-left:12px;">or link an appointment:</span>
        <select name="appointment_id" id="appt-link-select"
                onchange="
                    var iso = this.options[this.selectedIndex].dataset.iso || '';
                    if (iso) {
                        var di = document.getElementById('consult-date-input');
                        di.value = iso;
                        di.dispatchEvent(new Event('change'));
                    }"
                style="font-size:11px;font-family:'Inter',sans-serif;border:1px solid #fcd34d;border-radius:5px;padding:4px 8px;background:#fff;color:#374151;cursor:pointer;max-width:320px;">
            <option value="" data-iso="">— none —</option>
            @foreach($pastAppointments as $appt)
            <option value="{{ $appt->id }}" data-iso="{{ $appt->appointment_date->format('Y-m-d') }}">
                {{ $appt->appointment_date->format('d M Y') }}
                @if($appt->treatment?->name) · {{ Str::limit($appt->treatment->name, 30) }}@endif
                · {{ ucfirst($appt->status) }}
            </option>
            @endforeach
        </select>
        @endif
    </div>

    {{-- ══ BODY ══ --}}
    <div class="consult-wrap">

        {{-- ── LEFT: Main form ── --}}
        <div class="consult-main">

            {{-- 1. CONSULTATION TYPE — hidden when type is pre-set from URL (e.g. ?type=new from the patient tab buttons) --}}
            <div class="c-card" x-show="!typeLocked" x-cloak>
                <div class="c-card-head">
                    <span class="c-head-label">
                        <span class="c-num">1</span>
                        Consultation Type
                        <span style="font-weight:400;color:#9ca3af;text-transform:none;letter-spacing:0;font-size:10px;">— select to begin</span>
                    </span>
                    <span x-show="form.type" style="font-size:11px;font-weight:600;color:#6a0f70;font-family:'Inter',sans-serif;"
                          x-text="typeLabels[form.type] ?? ''"></span>
                </div>
                <div class="c-body">
                    <div class="type-grid">
                        @php
                        // Only 3 visit categories. COHA lives on the Patient page.
                        // Follow-Up / Minor Visit / 6M Recall / COHA removed per workflow spec.
                        $types = [
                            ['new',       '#6a0f70','#f5f3ff','New Consultation', 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 0 2-2h2a2 2 0 0 0 2 2m-6 9 2 2 4-4'],
                            ['same_issue','#d97706','#fffbeb','Same Issue',       'M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z'],
                            ['emergency', '#dc2626','#fef2f2','Emergency',        'M13 10V3L4 14h7v7l9-11h-7z'],
                        ];
                        @endphp
                        @foreach($types as [$key, $color, $bg, $label, $path])
                        <div class="type-card"
                             :class="form.type==='{{ $key }}'
                                 ? '{{ $key === 'emergency' ? 't-active-emergency' : 't-active' }}'
                                 : ''"
                             @click="form.type='{{ $key }}'">
                            <div class="type-icon"
                                 :style="form.type==='{{ $key }}' ? 'background:{{ $bg }}' : 'background:#f9fafb'">
                                <svg width="15" height="15" fill="none" viewBox="0 0 24 24"
                                     :stroke="form.type==='{{ $key }}' ? '{{ $color }}' : '#9ca3af'"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="{{ $path }}"/>
                                </svg>
                            </div>
                            <div class="type-label">{{ $label }}</div>
                            <div class="type-dot"></div>
                        </div>
                        @endforeach
                    </div>

                    {{-- ── P2C9: Previous Consultation Context Panel ─────────────────── --}}
                    <template x-if="form.type==='same_issue'">
                        <div style="margin-top:14px;" x-data="prevConsultPanel()">

                            @if($pastConsultations->isEmpty())
                            {{-- No past consultations exist --}}
                            <div style="padding:10px 14px;background:#fef9c3;border:1px solid #fde68a;border-radius:7px;font-size:12px;color:#92400e;font-family:'Inter',sans-serif;">
                                <strong>No previous consultations found</strong> for this patient. This will be recorded as a standalone consultation.
                            </div>
                            @else
                            {{-- Selector --}}
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                                <label style="font-size:11px;font-weight:600;color:#6b7280;white-space:nowrap;font-family:'Inter',sans-serif;">Linking to:</label>
                                <select
                                    name="previous_consultation_id"
                                    x-model="selectedId"
                                    @change="updatePanel()"
                                    style="flex:1;padding:5px 9px;border:1px solid #e5e7eb;border-radius:6px;font-size:12px;font-family:'Inter',sans-serif;background:#fff;color:#374151;">
                                    @foreach($pastConsultations as $pc)
                                    <option value="{{ $pc->id }}"
                                        data-date="{{ $pc->consultation_date->format('d M Y') }}"
                                        data-type="{{ str_replace('_', ' ', $pc->consultation_type ?? $pc->visit_type ?? 'consultation') }}"
                                        data-doctor="{{ $pc->doctor->name ?? '—' }}"
                                        data-complaint="{{ addslashes(Str::limit($pc->chief_complaint ?? '', 200)) }}"
                                        data-specialties="{{ addslashes(implode(', ', (array)($pc->accepted_specialties ?? []))) }}"
                                        data-diagnosis="{{ addslashes($pc->primary_diagnosis ?? '') }}"
                                        data-treatment="{{ addslashes(Str::limit($pc->treatment_done ?? '', 200)) }}"
                                        data-notes="{{ addslashes(Str::limit($pc->finishing_notes ?? $pc->diagnosis_notes ?? '', 150)) }}"
                                        {{ $loop->first ? 'selected' : '' }}>
                                        {{ $pc->consultation_date->format('d M Y') }}
                                        — {{ Str::limit($pc->primary_diagnosis ?? $pc->chief_complaint ?? 'No diagnosis', 50) }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Rich context card --}}
                            <div style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;font-family:'Inter',sans-serif;">
                                {{-- Header --}}
                                <div style="background:#f9fafb;border-bottom:1px solid #e5e7eb;padding:8px 14px;display:flex;align-items:center;justify-content:space-between;">
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        <span style="font-size:10px;font-weight:700;color:#6b7280;letter-spacing:.04em;text-transform:uppercase;">Previous Consultation — Read Only</span>
                                    </div>
                                    <span x-text="panel.date" style="font-size:11px;color:#6b7280;font-weight:500;"></span>
                                </div>

                                {{-- Body --}}
                                <div style="padding:12px 14px;background:#fff;display:flex;flex-direction:column;gap:10px;">

                                    {{-- Type + Doctor --}}
                                    <div style="display:flex;gap:20px;">
                                        <div style="flex:1;">
                                            <div style="font-size:9px;font-weight:700;color:#9ca3af;letter-spacing:.06em;text-transform:uppercase;margin-bottom:2px;">Type</div>
                                            <div x-text="panel.type" style="font-size:12px;color:#374151;font-weight:600;text-transform:capitalize;"></div>
                                        </div>
                                        <div style="flex:2;">
                                            <div style="font-size:9px;font-weight:700;color:#9ca3af;letter-spacing:.06em;text-transform:uppercase;margin-bottom:2px;">Doctor</div>
                                            <div x-text="panel.doctor || '—'" style="font-size:12px;color:#374151;"></div>
                                        </div>
                                    </div>

                                    {{-- Chief Complaint --}}
                                    <div x-show="panel.complaint">
                                        <div style="font-size:9px;font-weight:700;color:#9ca3af;letter-spacing:.06em;text-transform:uppercase;margin-bottom:2px;">Chief Complaint</div>
                                        <div x-text="panel.complaint" style="font-size:12px;color:#374151;line-height:1.5;"></div>
                                    </div>

                                    {{-- Specialties --}}
                                    <div x-show="panel.specialties">
                                        <div style="font-size:9px;font-weight:700;color:#9ca3af;letter-spacing:.06em;text-transform:uppercase;margin-bottom:4px;">Specialties</div>
                                        <div style="display:flex;flex-wrap:wrap;gap:5px;">
                                            <template x-for="sp in panel.specialtyList" :key="sp">
                                                <span x-text="sp.trim()" style="padding:2px 8px;background:#ede9fe;color:#7c3aed;border-radius:4px;font-size:10px;font-weight:600;text-transform:capitalize;"></span>
                                            </template>
                                        </div>
                                    </div>

                                    {{-- Diagnosis --}}
                                    <div x-show="panel.diagnosis">
                                        <div style="font-size:9px;font-weight:700;color:#9ca3af;letter-spacing:.06em;text-transform:uppercase;margin-bottom:2px;">Diagnosis</div>
                                        <div x-text="panel.diagnosis" style="font-size:12px;color:#374151;font-weight:600;"></div>
                                    </div>

                                    {{-- Treatment Done --}}
                                    <div x-show="panel.treatment">
                                        <div style="font-size:9px;font-weight:700;color:#9ca3af;letter-spacing:.06em;text-transform:uppercase;margin-bottom:2px;">Treatment Done</div>
                                        <div x-text="panel.treatment" style="font-size:12px;color:#374151;line-height:1.5;"></div>
                                    </div>

                                    {{-- Notes --}}
                                    <div x-show="panel.notes">
                                        <div style="font-size:9px;font-weight:700;color:#9ca3af;letter-spacing:.06em;text-transform:uppercase;margin-bottom:2px;">Notes</div>
                                        <div x-text="panel.notes" style="font-size:12px;color:#6b7280;font-style:italic;line-height:1.5;"></div>
                                    </div>

                                    {{-- Empty fallback --}}
                                    <div x-show="!panel.complaint && !panel.diagnosis && !panel.treatment && !panel.notes"
                                         style="font-size:12px;color:#9ca3af;font-style:italic;">
                                        No detailed clinical notes recorded for this consultation.
                                    </div>
                                </div>
                            </div>
                            @endif

                        </div>
                    </template>

                    {{-- COHA is a separate workflow on the Patient page — not shown here --}}
                </div>
            </div>

            {{-- 2. CHIEF COMPLAINT --}}
            <div class="c-card" x-show="form.type && form.type !== 'coha' && form.type !== 'same_issue'" x-cloak>
                <div class="c-card-head">
                    <span class="c-head-label"><span class="c-num">2</span>Chief Complaint</span>
                </div>
                <div class="c-body" style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="df-label">What does the patient say? <span class="req">*</span></label>
                        <textarea name="chief_complaint" x-model="form.chief_complaint"
                                  @input.debounce.600ms="runAssist()"
                                  class="df-input" rows="3"
                                  placeholder="Type in the patient's own words — e.g. 'My gums bleed when I brush and my teeth feel loose.'"></textarea>
                    </div>
                </div>
            </div>

            {{-- 3. SPECIALTY MODULES ZONE --}}
            <div x-show="form.type && form.type !== 'coha' && form.type !== 'same_issue'" x-cloak>

                {{-- Manual module selector chips --}}
                <div style="margin-bottom:12px;">
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;font-family:'Inter',sans-serif;margin-bottom:6px;">
                        Add Specialty Module
                    </div>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                        @foreach([
                            ['orthodontics',   'Orthodontics'],
                            ['periodontics',   'Periodontics'],
                            ['endodontics',    'Endodontics'],
                            ['smile_design',   'Smile Design'],
                            ['prosthodontics', 'Prosthodontics'],
                        ] as [$tag, $label])
                        <button type="button"
                                @click="toggleModule('{{ $tag }}')"
                                :class="acceptedModules.includes('{{ $tag }}')
                                    ? 'suggest-chip chip-accepted'
                                    : 'suggest-chip'"
                                style="font-size:11px;">
                            <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path x-show="acceptedModules.includes('{{ $tag }}')" d="M20 6 9 17l-5-5"/>
                                <path x-show="!acceptedModules.includes('{{ $tag }}')" d="M12 5v14M5 12h14"/>
                            </svg>
                            {{ $label }}
                        </button>
                        @endforeach
                    </div>
                </div>

                {{-- Empty state --}}
                <div x-show="acceptedModules.length === 0" x-cloak
                     style="border:1.5px dashed #e5e7eb;border-radius:8px;padding:22px;text-align:center;background:#fafafa;">
                    <div style="font-size:12px;color:#9ca3af;font-family:'Inter',sans-serif;line-height:1.7;">
                        Click a module above to add specialty findings
                    </div>
                </div>

                {{-- ── ORTHODONTIC module ── --}}
                <div x-show="acceptedModules.includes('orthodontics')" x-cloak class="module-card">
                    <div class="module-head">
                        <span class="module-tag">
                            <span style="width:6px;height:6px;border-radius:50%;background:#6a0f70;"></span>
                            Orthodontic findings
                        </span>
                        <button type="button" @click="removeModule('orthodontics')"
                                style="font-size:10px;color:#9ca3af;background:none;border:none;cursor:pointer;font-family:'Inter',sans-serif;">
                            ✕ Remove
                        </button>
                    </div>
                    <div class="module-body">
                        <div class="module-grid-3">
                            @foreach([
                                ['Crowding',        'ortho_crowding',  ['None','Mild','Moderate','Severe']],
                                ['Spacing',         'ortho_spacing',   ['None','Mild','Moderate','Severe']],
                                ['Overjet',         'ortho_overjet',   ['Normal','Increased','Reduced','Negative']],
                                ['Overbite',        'ortho_overbite',  ['Normal','Deep','Reduced','Open bite']],
                                ['Midline',         'ortho_midline',   ['Coincident','Shifted upper','Shifted lower']],
                                ['Skeletal pattern','ortho_skeletal',  ['Class I','Class II','Class III']],
                                ['Molar relation',  'ortho_molar',     ['Class I','Class II Div 1','Class II Div 2','Class III']],
                                ['Profile',         'ortho_profile',   ['Straight','Convex','Concave']],
                                ['Facial symmetry', 'ortho_symmetry',  ['Symmetric','Asymmetric']],
                            ] as [$lbl,$nm,$opts])
                            <div>
                                <label class="df-label" style="font-size:9px;">{{ $lbl }}</label>
                                <select name="{{ $nm }}" class="mod-sel">
                                    <option value="">Select</option>
                                    @foreach($opts as $o)<option>{{ $o }}</option>@endforeach
                                </select>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- ── PERIODONTIC module ── --}}
                <div x-show="acceptedModules.includes('periodontics')" x-cloak class="module-card">
                    <div class="module-head">
                        <span class="module-tag">
                            <span style="width:6px;height:6px;border-radius:50%;background:#6a0f70;"></span>
                            Periodontic findings
                        </span>
                        <button type="button" @click="removeModule('periodontics')"
                                style="font-size:10px;color:#9ca3af;background:none;border:none;cursor:pointer;font-family:'Inter',sans-serif;">
                            ✕ Remove
                        </button>
                    </div>
                    <div class="module-body">
                        <div class="module-grid">
                            @foreach([
                                ['BOP',           'perio_bop',     ['Absent','Localised','Generalised']],
                                ['Pocket depth',  'perio_pocket',  ['WNL (<3mm)','4–5mm','6mm+','>7mm']],
                                ['Recession',     'perio_recess',  ['None','Present — localised','Present — generalised']],
                                ['Mobility',      'perio_mobility',['None','Grade I','Grade II','Grade III']],
                                ['Furcation',     'perio_furc',    ['None','Class I','Class II','Class III']],
                                ['Calculus',      'perio_calc',    ['None','Mild','Moderate','Heavy']],
                                ['Plaque score',  'perio_plaque',  ['Good','Fair','Moderate','Poor']],
                                ['Oral hygiene',  'perio_hygiene', ['Excellent','Good','Fair','Poor']],
                            ] as [$lbl,$nm,$opts])
                            <div>
                                <label class="df-label" style="font-size:9px;">{{ $lbl }}</label>
                                <select name="{{ $nm }}" class="mod-sel">
                                    <option value="">Select</option>
                                    @foreach($opts as $o)<option>{{ $o }}</option>@endforeach
                                </select>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- ── ENDODONTIC module ── --}}
                <div x-show="acceptedModules.includes('endodontics')" x-cloak class="module-card">
                    <div class="module-head">
                        <span class="module-tag">
                            <span style="width:6px;height:6px;border-radius:50%;background:#6a0f70;"></span>
                            Endodontic findings
                        </span>
                        <button type="button" @click="removeModule('endodontics')"
                                style="font-size:10px;color:#9ca3af;background:none;border:none;cursor:pointer;font-family:'Inter',sans-serif;">
                            ✕ Remove
                        </button>
                    </div>
                    <div class="module-body">
                        <div class="module-grid">
                            @foreach([
                                ['Pain type',    'endo_pain',    ['Spontaneous','On stimulation','Dull ache','Sharp','Throbbing']],
                                ['Thermal test', 'endo_thermal', ['Normal','Prolonged','Absent','Hypersensitive']],
                                ['Percussion',   'endo_percuss', ['Negative','Positive']],
                                ['Palpation',    'endo_palp',    ['Negative','Positive']],
                                ['Swelling',     'endo_swell',   ['None','Hard','Fluctuant']],
                                ['Sinus tract',  'endo_sinus',   ['Absent','Present']],
                                ['Pulp status',  'endo_pulp',    ['Normal','Reversible pulpitis','Irreversible pulpitis','Necrotic']],
                                ['Mobility',     'endo_mob',     ['None','Grade I','Grade II','Grade III']],
                            ] as [$lbl,$nm,$opts])
                            <div>
                                <label class="df-label" style="font-size:9px;">{{ $lbl }}</label>
                                <select name="{{ $nm }}" class="mod-sel">
                                    <option value="">Select</option>
                                    @foreach($opts as $o)<option>{{ $o }}</option>@endforeach
                                </select>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- ── SMILE DESIGN module ── --}}
                <div x-show="acceptedModules.includes('smile_design')" x-cloak class="module-card">
                    <div class="module-head">
                        <span class="module-tag">
                            <span style="width:6px;height:6px;border-radius:50%;background:#6a0f70;"></span>
                            Smile design / cosmetic findings
                        </span>
                        <button type="button" @click="removeModule('smile_design')"
                                style="font-size:10px;color:#9ca3af;background:none;border:none;cursor:pointer;font-family:'Inter',sans-serif;">
                            ✕ Remove
                        </button>
                    </div>
                    <div class="module-body">
                        <div class="module-grid">
                            @foreach([
                                ['Shade',           'sd_shade',   ['A1','A2','A3','A3.5','B1','B2','C1','D2','Other']],
                                ['Smile line',      'sd_smile',   ['Low','Average','High','Gummy']],
                                ['Buccal corridor', 'sd_buccal',  ['Narrow','Average','Wide']],
                                ['Gingival display','sd_ging',    ['None','Mild','Moderate','Excessive']],
                                ['Tooth proportions','sd_props',  ['Ideal','Short','Long','Wide']],
                                ['Midline',         'sd_midline', ['Coincident','Deviated']],
                                ['Discoloration',   'sd_disco',   ['None','Intrinsic','Extrinsic','Tetracycline','Fluorosis']],
                            ] as [$lbl,$nm,$opts])
                            <div>
                                <label class="df-label" style="font-size:9px;">{{ $lbl }}</label>
                                <select name="{{ $nm }}" class="mod-sel">
                                    <option value="">Select</option>
                                    @foreach($opts as $o)<option>{{ $o }}</option>@endforeach
                                </select>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- ── PROSTHODONTIC module ── --}}
                <div x-show="acceptedModules.includes('prosthodontics')" x-cloak class="module-card">
                    <div class="module-head">
                        <span class="module-tag">
                            <span style="width:6px;height:6px;border-radius:50%;background:#6a0f70;"></span>
                            Prosthodontic findings
                        </span>
                        <button type="button" @click="removeModule('prosthodontics')"
                                style="font-size:10px;color:#9ca3af;background:none;border:none;cursor:pointer;font-family:'Inter',sans-serif;">
                            ✕ Remove
                        </button>
                    </div>
                    <div class="module-body">
                        <div class="module-grid">
                            @foreach([
                                ['Missing teeth',     'pros_miss',   ['None','Single tooth','Multiple teeth','Edentulous']],
                                ['Existing prosthesis','pros_exist', ['None','Partial denture','Complete denture','Bridge','Implant crown']],
                                ['Bone support',      'pros_bone',   ['Adequate','Reduced — localised','Reduced — generalised']],
                                ['Ridge condition',   'pros_ridge',  ['Well-formed','Resorbed','Irregular']],
                                ['Occlusal support',  'pros_occl',   ['Adequate','Inadequate']],
                                ['TMJ status',        'pros_tmj',    ['Normal','Clicking','Pain on opening','Restricted']],
                            ] as [$lbl,$nm,$opts])
                            <div>
                                <label class="df-label" style="font-size:9px;">{{ $lbl }}</label>
                                <select name="{{ $nm }}" class="mod-sel">
                                    <option value="">Select</option>
                                    @foreach($opts as $o)<option>{{ $o }}</option>@endforeach
                                </select>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

            </div>{{-- /specialty modules --}}

            {{-- 4. HOPI (P2C5 — enhanced auto-draft) --}}
            <div class="c-card" x-show="form.type && form.type !== 'coha' && form.type !== 'same_issue'" x-cloak>
                <div class="c-card-head">
                    <span class="c-head-label"><span class="c-num">3</span>History of Present Illness (HOPI)</span>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <span style="font-size:10px;color:#9ca3af;font-family:'Inter',sans-serif;">Pulls complaint · duration · severity · clinical findings · investigations</span>
                        <button type="button" @click="generateHopi()"
                                style="font-size:10px;font-weight:600;color:#6a0f70;border:1px solid rgba(106,15,112,.3);
                                       padding:3px 10px;border-radius:4px;background:#fff;cursor:pointer;font-family:'Inter',sans-serif;
                                       transition:all .15s;white-space:nowrap;"
                                onmouseover="this.style.background='#faf5fb'" onmouseout="this.style.background='#fff'">
                            Auto-draft HOPI
                        </button>
                    </div>
                </div>
                <div class="c-body">
                    {{-- hopi_auto = system-generated (hidden, stored for reference) --}}
                    <input type="hidden" name="hopi_auto" x-ref="hopiAuto">
                    {{-- hopi_final = what the doctor actually submits --}}
                    <textarea name="hopi_final" x-ref="hopiNote" class="df-input" rows="4"
                              placeholder="Auto-draft pulls from Chief Complaint, Clinical Findings & Investigations — or type directly.&#10;&#10;e.g. Patient presents with sensitivity in upper right molars for 3 weeks, moderate severity, exacerbated by cold stimuli. On examination, generalised mild gingivitis noted with mild caries on #14, 15.">{{ old('hopi_final', $consultation?->hopi_final) }}</textarea>
                    <div style="margin-top:5px;font-size:10px;color:#9ca3af;font-family:'Inter',sans-serif;">
                        Doctor is final authority — edit freely. Auto-draft overwrites this field.
                    </div>
                </div>
            </div>

            {{-- 4. TOOTH CHART --}}
            <div class="c-card" x-show="form.type && form.type !== 'coha' && form.type !== 'same_issue'" x-cloak
                 x-data="toothChart(@js($consultation?->chart_data ?? []))">
                <input type="hidden" name="chart_data" :value="serializedChart">

                <div class="c-card-head">
                    <span class="c-head-label">
                        <span class="c-num">4</span>Tooth Chart
                        <span x-show="markedCount > 0" x-cloak
                              style="font-weight:400;color:#9ca3af;text-transform:none;letter-spacing:0;font-size:10px;"
                              x-text="'· ' + markedCount + ' tooth marked'"></span>
                    </span>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <button type="button" x-show="markedCount > 0" x-cloak @click="clearAll()"
                                style="font-size:10px;color:#dc2626;background:none;border:none;cursor:pointer;font-family:'Inter',sans-serif;">
                            Clear all
                        </button>
                        <span style="font-size:10px;color:#9ca3af;font-family:'Inter',sans-serif;">
                            FDI notation · click tooth to mark · A/P toggles child tooth
                        </span>
                    </div>
                </div>

                <div class="c-body">
                    {{-- Upper --}}
                    <div style="text-align:center;font-size:9px;font-weight:700;color:#9ca3af;text-transform:uppercase;
                                letter-spacing:.07em;margin-bottom:5px;font-family:'Inter',sans-serif;">Upper</div>
                    <div class="tooth-row">
                        <template x-for="pos in [18,17,16,15,14,13,12,11]" :key="pos">
                            <div class="tooth-slot">
                                <button type="button" @click="clickTooth(pos)" class="tooth-btn"
                                        :style="toothData[codeAt(pos)] ? `background:${condColor(codeAt(pos))};border-color:${condColor(codeAt(pos))};color:#fff;font-weight:700;` : ''"
                                        x-text="codeAt(pos)"></button>
                                <button type="button" x-show="canToggle(pos)" x-cloak
                                        @click.stop="toggleDentition(pos)" class="tooth-dentition-toggle"
                                        :class="isChild(pos) ? 'is-child' : ''"
                                        :title="isChild(pos) ? 'Primary (child) tooth — click for permanent' : 'Permanent tooth — click for primary (child)'"
                                        x-text="isChild(pos) ? 'P' : 'A'"></button>
                            </div>
                        </template>
                        <div class="tooth-midline"></div>
                        <template x-for="pos in [21,22,23,24,25,26,27,28]" :key="pos">
                            <div class="tooth-slot">
                                <button type="button" @click="clickTooth(pos)" class="tooth-btn"
                                        :style="toothData[codeAt(pos)] ? `background:${condColor(codeAt(pos))};border-color:${condColor(codeAt(pos))};color:#fff;font-weight:700;` : ''"
                                        x-text="codeAt(pos)"></button>
                                <button type="button" x-show="canToggle(pos)" x-cloak
                                        @click.stop="toggleDentition(pos)" class="tooth-dentition-toggle"
                                        :class="isChild(pos) ? 'is-child' : ''"
                                        :title="isChild(pos) ? 'Primary (child) tooth — click for permanent' : 'Permanent tooth — click for primary (child)'"
                                        x-text="isChild(pos) ? 'P' : 'A'"></button>
                            </div>
                        </template>
                    </div>

                    <div style="text-align:center;font-size:8px;color:#d1d5db;font-family:'Inter',sans-serif;
                                margin:5px 0;letter-spacing:.18em;">— MIDLINE —</div>

                    {{-- Lower --}}
                    <div class="tooth-row">
                        <template x-for="pos in [48,47,46,45,44,43,42,41]" :key="pos">
                            <div class="tooth-slot">
                                <button type="button" @click="clickTooth(pos)" class="tooth-btn"
                                        :style="toothData[codeAt(pos)] ? `background:${condColor(codeAt(pos))};border-color:${condColor(codeAt(pos))};color:#fff;font-weight:700;` : ''"
                                        x-text="codeAt(pos)"></button>
                                <button type="button" x-show="canToggle(pos)" x-cloak
                                        @click.stop="toggleDentition(pos)" class="tooth-dentition-toggle"
                                        :class="isChild(pos) ? 'is-child' : ''"
                                        :title="isChild(pos) ? 'Primary (child) tooth — click for permanent' : 'Permanent tooth — click for primary (child)'"
                                        x-text="isChild(pos) ? 'P' : 'A'"></button>
                            </div>
                        </template>
                        <div class="tooth-midline"></div>
                        <template x-for="pos in [31,32,33,34,35,36,37,38]" :key="pos">
                            <div class="tooth-slot">
                                <button type="button" @click="clickTooth(pos)" class="tooth-btn"
                                        :style="toothData[codeAt(pos)] ? `background:${condColor(codeAt(pos))};border-color:${condColor(codeAt(pos))};color:#fff;font-weight:700;` : ''"
                                        x-text="codeAt(pos)"></button>
                                <button type="button" x-show="canToggle(pos)" x-cloak
                                        @click.stop="toggleDentition(pos)" class="tooth-dentition-toggle"
                                        :class="isChild(pos) ? 'is-child' : ''"
                                        :title="isChild(pos) ? 'Primary (child) tooth — click for permanent' : 'Permanent tooth — click for primary (child)'"
                                        x-text="isChild(pos) ? 'P' : 'A'"></button>
                            </div>
                        </template>
                    </div>
                    <div style="text-align:center;font-size:9px;font-weight:700;color:#9ca3af;text-transform:uppercase;
                                letter-spacing:.07em;margin-top:5px;font-family:'Inter',sans-serif;">Lower</div>

                    {{-- Summary by condition --}}
                    <template x-if="markedCount > 0">
                        <div style="margin-top:16px;background:#fafafa;border:1px solid #f0f0f2;border-radius:10px;
                                    padding:12px 14px;display:flex;flex-direction:column;gap:9px;">
                            <template x-for="c in conditions.filter(c => c.key !== 'other' && markedByCondition(c.key).length > 0)" :key="c.key">
                                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                    <span class="tc-legend-pill" :style="{ background: c.bg, color: c.color, borderColor: c.color + '60' }">
                                        <span class="tc-legend-dot" :style="{ background: c.color }"></span>
                                        <span x-text="c.label"></span>
                                    </span>
                                    <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                        <template x-for="t in markedByCondition(c.key)" :key="t">
                                            <span class="tc-tooth-chip"
                                                  :style="isPrimaryCode(t) ? { color: '#db2777', borderColor: '#f9a8d4' } : { color: c.color, borderColor: c.color + '70' }"
                                                  :title="toothTitle(t)"
                                                  x-text="t + surfaceSuffix(t) + (isPrimaryCode(t) ? ' ·P' : '')"></span>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- "Other" — free-text condition, one row per tooth since the label varies --}}
                            <template x-if="markedByCondition('other').length > 0">
                                <div style="display:flex;flex-direction:column;gap:6px;">
                                    <template x-for="t in markedByCondition('other')" :key="t">
                                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                            <span class="tc-legend-pill" style="background:#f3f4f6;color:#6b7280;border-color:#6b728060;">
                                                <span class="tc-legend-dot" style="background:#6b7280;"></span>
                                                <span x-text="'Tooth ' + t + ' — Other'"></span>
                                            </span>
                                            <span style="font-size:11.5px;color:#374151;font-family:'Inter',sans-serif;font-weight:600;"
                                                  x-text="toothData[t].custom || '—'"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            {{-- Legacy rows saved before condition-tracking existed — no condition on file yet --}}
                            <template x-if="unspecified().length > 0">
                                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                    <span class="tc-legend-pill" style="background:#f3f4f6;color:#9ca3af;border-color:#e5e7eb;">
                                        <span class="tc-legend-dot" style="background:#d1d5db;"></span>
                                        <span>Unspecified</span>
                                    </span>
                                    <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                        <template x-for="t in unspecified()" :key="t">
                                            <span class="tc-tooth-chip" style="color:#6b7280;border-color:#e5e7eb;"
                                                  title="Marked before a condition was recorded — reopen to set one"
                                                  x-text="t"></span>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- ── Condition picker modal ── --}}
                <template x-if="activeTooth !== null">
                    <div @click.self="activeTooth = null"
                         style="position:fixed;inset:0;z-index:300;display:flex;align-items:center;
                                justify-content:center;background:rgba(15,5,20,.45);backdrop-filter:blur(3px);">
                        <div @click.stop
                             style="background:#fff;border-radius:16px;padding:0;width:266px;
                                    box-shadow:0 24px 64px rgba(0,0,0,.22),0 0 0 1px rgba(106,15,112,.08);
                                    overflow:hidden;">

                            {{-- Header --}}
                            <div style="padding:14px 16px 12px;border-bottom:1px solid #f3f4f6;
                                        display:flex;align-items:center;justify-content:space-between;">
                                <div>
                                    <span style="font-size:11px;font-weight:500;color:#9ca3af;
                                                 font-family:'Inter',sans-serif;letter-spacing:.04em;
                                                 text-transform:uppercase;">Tooth</span>
                                    <span x-text="activeTooth"
                                          style="font-size:11px;font-weight:700;color:#6a0f70;
                                                 font-family:'Inter',sans-serif;margin-left:4px;letter-spacing:.04em;"></span>
                                    <div x-show="toothData[activeTooth] && condLabel(activeTooth)"
                                         style="display:flex;align-items:center;gap:4px;margin-top:2px;">
                                        <span class="tc-current-dot"
                                              :style="{ background: condColor(activeTooth) }"></span>
                                        <span class="tc-current-label"
                                              x-text="condLabel(activeTooth)"
                                              :style="{ color: condColor(activeTooth) }"></span>
                                    </div>
                                </div>
                                <button type="button" @click="activeTooth = null"
                                        style="width:24px;height:24px;border-radius:50%;border:1px solid #e5e7eb;
                                               background:#f9fafb;color:#9ca3af;font-size:14px;line-height:1;
                                               cursor:pointer;display:flex;align-items:center;justify-content:center;
                                               padding:0;transition:all .1s;"
                                        onmouseover="this.style.background='#f3f4f6';this.style.color='#374151'"
                                        onmouseout="this.style.background='#f9fafb';this.style.color='#9ca3af'">×</button>
                            </div>

                            {{-- Capsule list --}}
                            <div style="padding:10px 12px;display:flex;flex-direction:column;gap:5px;">
                                <template x-for="c in conditions" :key="c.key">
                                    {{-- Use CSS class + :style OBJECT so Alpine doesn't wipe border-radius --}}
                                    <button type="button" @click="c.key === 'other' ? pickOther() : setCondition(c.key)"
                                            class="tc-cond-btn"
                                            :style="toothData[activeTooth]?.condition === c.key
                                                ? { background: c.color, borderColor: c.color }
                                                : { background: c.bg,    borderColor: c.color + '40' }">
                                        <span class="tc-cond-dot"
                                              :style="toothData[activeTooth]?.condition === c.key
                                                  ? { background: 'rgba(255,255,255,.8)' }
                                                  : { background: c.color }"></span>
                                        <span class="tc-cond-label"
                                              :style="toothData[activeTooth]?.condition === c.key
                                                  ? { color: '#fff' }
                                                  : { color: c.color }"
                                              x-text="c.key === 'other' && toothData[activeTooth]?.condition === 'other' ? (toothData[activeTooth].custom || 'Other') : c.label"></span>
                                        <svg x-show="toothData[activeTooth]?.condition === c.key"
                                             width="12" height="12" viewBox="0 0 24 24" fill="none"
                                             stroke="rgba(255,255,255,.9)" stroke-width="3"
                                             stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="20 6 9 17 4 12"/>
                                        </svg>
                                    </button>
                                </template>
                            </div>

                            {{-- Surface picker — only for restorations where surface matters clinically --}}
                            <template x-if="toothData[activeTooth] && needsSurfaces(toothData[activeTooth].condition)">
                                <div style="padding:2px 12px 10px;border-top:1px solid #f3f4f6;">
                                    <div style="font-size:9.5px;font-weight:700;color:#9ca3af;text-transform:uppercase;
                                                letter-spacing:.06em;margin:10px 0 6px;font-family:'Inter',sans-serif;">
                                        Surface(s)
                                    </div>
                                    <div style="display:flex;flex-wrap:wrap;gap:5px;">
                                        <template x-for="s in SURFACES" :key="s.key">
                                            <button type="button" @click="toggleSurface(s.key)" :title="s.label"
                                                    :style="(toothData[activeTooth].surfaces||[]).includes(s.key)
                                                        ? 'padding:5px 11px;border-radius:999px;border:1px solid #6a0f70;background:#6a0f70;color:#fff;font-size:11px;font-weight:700;cursor:pointer;font-family:Inter,sans-serif;'
                                                        : 'padding:5px 11px;border-radius:999px;border:1px solid #e5e7eb;background:#f9fafb;color:#6b7280;font-size:11px;font-weight:600;cursor:pointer;font-family:Inter,sans-serif;'"
                                                    x-text="s.key"></button>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- Footer --}}
                            <div style="padding:0 12px 12px;">
                                <template x-if="toothData[activeTooth]">
                                    <button type="button" @click="clearTooth()"
                                            style="width:100%;padding:7px;border-radius:999px;border:1px dashed #e5e7eb;
                                                   background:transparent;color:#d1d5db;font-size:10.5px;font-weight:600;
                                                   cursor:pointer;font-family:'Inter',sans-serif;letter-spacing:.02em;
                                                   transition:all .1s;"
                                            onmouseover="this.style.borderColor='#fca5a5';this.style.color='#ef4444'"
                                            onmouseout="this.style.borderColor='#e5e7eb';this.style.color='#d1d5db'">
                                        Remove marking
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

            </div>

            {{-- 5. INVESTIGATIONS --}}
            <div x-show="form.type && form.type !== 'coha' && form.type !== 'same_issue'">
                @include('consultations.partials.investigations', ['consultation' => $consultation ?? null])
            </div>

            {{-- 5b. FINDINGS SUMMARY (P2C5) --}}
            <div class="c-card" x-show="form.type && form.type !== 'coha' && form.type !== 'same_issue'" x-cloak>
                <div class="c-card-head">
                    <span class="c-head-label">
                        <span class="c-num" style="font-size:8px;">5b</span>Findings Summary
                    </span>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <span style="font-size:10px;color:#9ca3af;font-family:'Inter',sans-serif;">Compiles clinical exam · investigations · specialty modules</span>
                        <button type="button" @click="generateFindingsSummary()"
                                style="font-size:10px;font-weight:600;color:#6a0f70;border:1px solid rgba(106,15,112,.3);
                                       padding:3px 10px;border-radius:4px;background:#fff;cursor:pointer;font-family:'Inter',sans-serif;
                                       transition:all .15s;white-space:nowrap;"
                                onmouseover="this.style.background='#faf5fb'" onmouseout="this.style.background='#fff'">
                            Auto-draft Summary
                        </button>
                    </div>
                </div>
                <div class="c-body">
                    {{-- findings_summary_auto = system generated snapshot (hidden) --}}
                    <input type="hidden" name="findings_summary_auto" x-ref="findingsAuto">
                    {{-- findings_summary_final = doctor-edited narrative submitted to DB --}}
                    <textarea name="findings_summary_final" x-ref="findingsNote" class="df-input" rows="4"
                              value="{{ old('findings_summary_final', $consultation?->findings_summary_final) }}"
                              placeholder="Clinical examination reveals generalised mild gingivitis with caries on #14, 15, 46. BOP: absent. Plaque index: fair. IOPA requested for #46. Investigations: OPG taken.&#10;&#10;Auto-draft fills this from your clinical findings above.">{{ old('findings_summary_final', $consultation?->findings_summary_final) }}</textarea>
                    <div style="margin-top:5px;font-size:10px;color:#9ca3af;font-family:'Inter',sans-serif;">
                        Bridges clinical examination → diagnosis. Edit freely before saving.
                    </div>
                </div>
            </div>

            {{-- 6. DIAGNOSIS —— single provisional field (simplified) --}}
            {{-- Outer wrapper owns x-show (parent consultForm scope); inner div owns x-data --}}
            <div x-show="form.type && form.type !== 'coha' && form.type !== 'same_issue'" x-cloak>
            <div class="c-card" x-data="{ diagText: @js(old('provisional_diagnosis', $consultation?->provisional_diagnosis ?? '')) }">

                <div class="c-card-head">
                    <span class="c-head-label"><span class="c-num">6</span>Diagnosis</span>
                    <span style="font-size:10px;color:#9ca3af;font-family:'Inter',sans-serif;font-style:italic;">
                        Consultation ends here — treatment plan is the next step
                    </span>
                </div>

                <div class="c-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">

                        {{-- Left: diagnosis textarea + Consult Assist suggestions --}}
                        <div style="display:flex;flex-direction:column;gap:8px;">
                            <div>
                                <label class="df-label">Provisional Diagnosis <span class="req">*</span></label>
                                <textarea name="provisional_diagnosis" x-model="diagText" class="df-input" rows="5"
                                          placeholder="Working diagnosis — e.g. Symptomatic irreversible pulpitis tooth #16">{{ old('provisional_diagnosis', $consultation?->provisional_diagnosis ?? '') }}</textarea>
                                {{-- Mirror to primary_diagnosis so controller/show pages still work --}}
                                <input type="hidden" name="primary_diagnosis" :value="diagText">
                            </div>
                            {{-- Consult Assist one-click suggestions --}}
                            <div>
                                <label class="df-label">
                                    Suggested from Consult Assist
                                    <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#b5b5b5;font-size:9px;">(click to use)</span>
                                </label>
                                <div style="display:flex;flex-wrap:wrap;gap:5px;padding:8px;min-height:60px;border:1px dashed #e5e7eb;border-radius:5px;background:#fafafa;">
                                    <template x-for="diag in ($root.suggestions || []).flatMap(s=>s.diagnoses||[]).filter((v,i,a)=>a.indexOf(v)===i).slice(0,8)" :key="diag">
                                        <button type="button" @click="diagText = diagText ? diagText + ', ' + diag : diag"
                                                x-text="diag"
                                                style="font-size:10px;padding:3px 8px;border-radius:99px;border:1px solid rgba(106,15,112,.2);background:#faf5fb;color:#6a0f70;cursor:pointer;font-family:'Inter',sans-serif;transition:all .12s;"
                                                onmouseover="this.style.background='#ede4f5'" onmouseout="this.style.background='#faf5fb'"></button>
                                    </template>
                                    <template x-if="($root.suggestions||[]).flatMap(s=>s.diagnoses||[]).length===0">
                                        <span style="font-size:10px;color:#d1d5db;font-family:'Inter',sans-serif;align-self:center;">
                                            Enter chief complaint to get suggestions
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        {{-- Right: Risk, Notes --}}
                        <div style="display:flex;flex-direction:column;gap:10px;">
                            <div>
                                <label class="df-label">Risk Assessment</label>
                                <select name="diagnosis_risk" class="df-input">
                                    <option value="">Select risk level</option>
                                    @foreach(['Low Risk','Moderate Risk','High Risk','Very High Risk'] as $r)
                                        <option {{ old('diagnosis_risk', $consultation?->diagnosis_risk ?? '') === $r ? 'selected' : '' }}>{{ $r }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="df-label">Diagnosis Notes <span style="font-weight:400;letter-spacing:0;text-transform:none;color:#9ca3af;font-size:9px;">(optional)</span></label>
                                <textarea name="diagnosis_notes" class="df-input" rows="4"
                                          placeholder="Clinical reasoning, comorbidities, patient concerns…">{{ old('diagnosis_notes', $consultation?->diagnosis_notes ?? '') }}</textarea>
                            </div>
                        </div>

                    </div>
                </div>{{-- /c-body --}}

            </div>{{-- /diagnosis card --}}
            </div>{{-- /diagnosis outer x-show wrapper --}}

            {{-- COHA — dedicated page (P2C7) --}}
            {{-- SAME ISSUE simplified card — shown instead of full form --}}
            <div x-show="form.type === 'same_issue'" x-cloak
                 class="c-card" style="border-color:#fde68a;">
                <div class="c-card-head" style="background:#fffbeb;">
                    <span class="c-head-label" style="color:#d97706;">
                        <span class="c-num" style="background:#d97706;">→</span>
                        Same Issue — Update This Visit
                    </span>
                </div>
                <div class="c-body" style="display:flex;flex-direction:column;gap:16px;">

                    {{-- Previous data read-only row --}}
                    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:12px 14px;font-family:'Inter',sans-serif;">
                        <p style="font-size:9px;font-weight:700;color:#b45309;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;">
                            From Previous Consultation (read-only)
                        </p>
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                            <div>
                                <p style="font-size:9px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px;">Chief Complaint</p>
                                <p x-text="prevPanel.complaint || '—'" style="font-size:12px;color:#374151;line-height:1.5;"></p>
                            </div>
                            <div>
                                <p style="font-size:9px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px;">HOPI</p>
                                <p x-text="prevPanel.hopi || '—'" style="font-size:12px;color:#374151;line-height:1.5;"></p>
                            </div>
                            <div>
                                <p style="font-size:9px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px;">Diagnosis</p>
                                <p x-text="prevPanel.diagnosis || '—'" style="font-size:12px;color:#374151;line-height:1.5;"></p>
                            </div>
                        </div>
                    </div>

                    {{-- 3 editable fields --}}
                    <div>
                        <label class="df-label">Progress / Update since last visit <span class="req">*</span></label>
                        <textarea name="chief_complaint" x-model="form.chief_complaint"
                                  @input.debounce.600ms="runAssist()"
                                  class="df-input" rows="3"
                                  placeholder="Describe what has changed, improved, or worsened since the last visit…"></textarea>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <label class="df-label">Updated HOPI</label>
                            <textarea name="hopi_final" class="df-input" rows="3"
                                      placeholder="Any updates to the history of present illness…">{{ old('hopi_final', $consultation?->hopi_final) }}</textarea>
                        </div>
                        <div>
                            <label class="df-label">Updated Diagnosis</label>
                            <input type="text" name="primary_diagnosis" class="df-input"
                                   value="{{ old('primary_diagnosis', $consultation?->primary_diagnosis) }}"
                                   placeholder="e.g. Periapical periodontitis — resolving">
                        </div>
                    </div>

                    {{-- Hidden: carry forward previous consultation link --}}
                    <input type="hidden" name="previous_consultation_id"
                           x-bind:value="prevPanel.id || '{{ old('previous_consultation_id', $consultation?->previous_consultation_id) }}'">
                    {{-- consultation_type is already bound via the top-level hidden input (line 361); no duplicate needed --}}
                </div>
            </div>


            {{-- SAVE CTA — shown for all types --}}
            <div x-show="form.type" x-cloak
                 style="background:#f9f5ff;border:1.5px solid #e9d5ff;border-radius:8px;padding:16px 20px;
                        display:flex;align-items:center;justify-content:space-between;gap:16px;">
                <div style="font-size:11px;color:#9ca3af;font-family:'Inter',sans-serif;">
                    Treatment plan is managed separately from the patient profile.
                </div>
                <div style="display:flex;gap:8px;flex-shrink:0;">
                    <button type="submit" name="status" value="draft" class="btn-draft">Save Draft</button>
                    <button type="submit" class="btn-save">Save Consultation</button>
                </div>
            </div>

        </div>{{-- /consult-main --}}

        {{-- ── RIGHT: Consult Assist sidebar ── --}}
        <div class="consult-aside"
             :style="(form.type && form.type !== 'coha' && form.type !== 'same_issue') ? '' : 'visibility:hidden;pointer-events:none;'">

            {{-- Consult Assist Panel --}}
            <div style="background:#fff;border:1px solid #e9d5ff;border-radius:8px;overflow:hidden;">
                <div class="assist-head">
                    <span class="assist-pulse"></span>
                                     <span class="assist-title">Consult Assist</span>
                    <span x-show="isLoadingAssist" x-cloak
                          style="margin-left:auto;font-size:10px;color:#6a0f70;font-family:'DM Sans',sans-serif;opacity:.7;">
                        Analysing…
                    </span>
                </div>
                <div class="assist-body">

                    {{-- Empty state --}}
                    <div x-show="suggestions.length === 0 && treatmentMatches.length === 0 && !isLoadingAssist" x-cloak class="assist-empty">
                        Start typing the chief complaint<br>to get suggestions
                    </div>

                    {{-- ── LAYER 1: Treatment-level matches (most actionable — shown first) ── --}}
                    <template x-if="treatmentMatches.length > 0">
                        <div>
                            <div class="aside-label" style="margin-bottom:6px;letter-spacing:.06em;">
                                Suggested Treatments
                            </div>

                            <template x-for="tx in treatmentMatches" :key="tx.id">
                                <div style="margin-bottom:10px;background:#fff;border-radius:6px;padding:10px 12px;border-left:3px solid #6a0f70;box-shadow:0 1px 3px rgba(0,0,0,.06);"
                                     :style="`border-left-color:${tx.color || '#6a0f70'}`">

                                    {{-- Treatment name + price --}}
                                    <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;margin-bottom:6px;">
                                        <span style="font-size:12px;font-weight:700;color:#111827;font-family:'DM Sans',sans-serif;"
                                              x-text="tx.name"></span>
                                        <span style="font-size:10px;font-weight:600;color:#6a0f70;font-family:'DM Sans',sans-serif;white-space:nowrap;background:#faf5fb;padding:2px 7px;border-radius:4px;"
                                              x-text="'₹' + (tx.min_price ? tx.min_price.toLocaleString('en-IN') : '—') + '+'"></span>
                                    </div>

                                    {{-- Ask the patient --}}
                                    <template x-if="tx.questions && tx.questions.length">
                                        <div style="margin-bottom:6px;">
                                            <div class="aside-label">Ask the patient</div>
                                            <template x-for="q in tx.questions.slice(0,3)" :key="q">
                                                <div class="assist-q" x-text="q"></div>
                                            </template>
                                        </div>
                                    </template>

                                    {{-- Investigations --}}
                                    <template x-if="tx.investigations && tx.investigations.length">
                                        <div style="margin-bottom:6px;">
                                            <div class="aside-label">Investigations</div>
                                            <div>
                                                <template x-for="inv in tx.investigations.slice(0,4)" :key="inv">
                                                    <span class="inv-chip" x-text="inv"></span>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                    {{-- Likely diagnoses --}}
                                    <template x-if="tx.diagnoses && tx.diagnoses.length">
                                        <div>
                                            <div class="aside-label">Likely Diagnosis</div>
                                            <template x-for="(dx, i) in tx.diagnoses.slice(0,3)" :key="dx">
                                                <div style="display:flex;align-items:flex-start;gap:5px;margin-bottom:3px;">
                                                    <span style="font-size:9px;color:#6a0f70;font-weight:800;font-family:'DM Sans',sans-serif;margin-top:2px;"
                                                          x-text="(i+1)+'.'"></span>
                                                    <span style="font-size:11px;color:#1f2937;font-family:'DM Sans',sans-serif;line-height:1.5;"
                                                          x-text="dx"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </template>

                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- ── LAYER 2: Specialty modules (for activating the specialty panel) ── --}}
                    <template x-if="suggestions.length > 0">
                        <div :style="treatmentMatches.length ? 'margin-top:10px;padding-top:10px;border-top:1px solid #ede9f5;' : ''">
                            <div class="aside-label" style="margin-bottom:6px;letter-spacing:.06em;">
                                Specialty Modules
                            </div>

                            <template x-for="spec in suggestions" :key="spec.tag">
                                <div style="margin-bottom:8px;">

                                    {{-- Specialty chip --}}
                                    <button type="button"
                                            @click="toggleModule(spec.tag)"
                                            :class="acceptedModules.includes(spec.tag) ? 'suggest-chip chip-accepted' : 'suggest-chip'">
                                        <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2"
                                             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                            <path x-show="acceptedModules.includes(spec.tag)" d="M20 6 9 17l-5-5"/>
                                            <path x-show="!acceptedModules.includes(spec.tag)" d="M12 5v14M5 12h14"/>
                                        </svg>
                                        <span x-text="spec.label"></span>
                                    </button>

                                </div>
                            </template>
                        </div>
                    </template>

                </div>
            </div>

            {{-- Accepted modules summary --}}
            <div x-show="acceptedModules.length > 0" x-cloak
                 style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px 14px;">
                <div class="aside-label" style="margin-top:0;">Accepted modules</div>
                <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:5px;">
                    <template x-for="tag in acceptedModules" :key="tag">
                        <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;
                                     border-radius:99px;background:#faf5fb;border:1px solid #e9d5ff;
                                     font-size:10px;font-weight:600;color:#6a0f70;font-family:'DM Sans',sans-serif;">
                            <span x-text="tag.replace('_',' ')"></span>
                            <button type="button" @click="removeModule(tag)"
                                    style="background:none;border:none;cursor:pointer;color:#9ca3af;padding:0;font-size:11px;line-height:1;">✕</button>
                        </span>
                    </template>
                </div>
            </div>

        </div>{{-- /consult-aside --}}

    </div>{{-- /consult-wrap --}}

</form>
</div>{{-- /x-data consultForm --}}


@push('scripts')
<script>
// ── P2C: consultForm() Alpine component ─────────────────────────────────────
function consultForm() {
    return {
        form: {
            type:            @json(old('consultation_type', isset($consultation) ? ($consultation->consultation_type ?? '') : '')),
            // Seed from the existing record in edit mode, else blank. Without this,
            // Alpine's x-model would overwrite the server-rendered values with '' on load.
            chief_complaint: @json(old('chief_complaint', isset($consultation) ? ($consultation->chief_complaint ?? '') : '')),
            severity:        @json(old('severity', isset($consultation) ? ($consultation->severity ?? '') : '')),
            investigations:  @json(old('investigations', isset($consultation) ? ($consultation->investigations ?? []) : [])),
        },
        invFiles: {},   // tracks uploaded file counts per inv key
        typeLabels: {
            new:        'New Consultation',
            same_issue: 'Same Issue',
            emergency:  'Emergency',
        },

        // Holds previous consultation data for the Same Issue read-only display
        prevPanel: {
            id:        null,
            complaint: '',
            hopi:      '',
            diagnosis: '',
        },
        acceptedModules: {!! json_encode(isset($consultation) ? ($consultation->accepted_specialties ?? []) : []) !!},
        typeLocked:      false, // true when type comes from URL param (skip type selector)
        assistActive:    false,
        suggestions:     [],   // specialty-level matches
        treatmentMatches:[],   // treatment-level matches (new)
        suggestedQuestions:      [],
        suggestedInvestigations: [],
        isLoadingAssist: false,
        assistDebounce:  null,

        init() {
            // If a type is passed via URL (?type=new), pre-select it and skip the type card
            const urlType = new URLSearchParams(window.location.search).get('type');
            if (urlType && !this.form.type) {
                this.form.type = urlType;
                this.typeLocked = true;
            }
        },

        // ── Investigation file upload tracker ────────────────────────────────
        handleInvUpload(event, key) {
            const files = event.target.files;
            if (files && files.length) {
                this.invFiles[key] = Array.from(files);
            }
        },

        // ── Consult Assist AJAX ──────────────────────────────────────────────
        // Debounced: fires 600ms after the last keystroke.
        scheduleAssist() {
            clearTimeout(this.assistDebounce);
            this.assistDebounce = setTimeout(() => this.runAssist(), 600);
        },

        async runAssist() {
            const text = (this.form.chief_complaint || '').trim();
            if (text.length < 4) {
                this.assistActive    = false;
                this.suggestions     = [];
                this.treatmentMatches= [];
                this.suggestedQuestions      = [];
                this.suggestedInvestigations = [];
                return;
            }
            this.isLoadingAssist = true;
            try {
                const r = await fetch('{{ route("consult-assist.suggest") }}', {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept':       'application/json',
                    },
                    body: JSON.stringify({ complaint: text }),
                });
                if (!r.ok) return;
                const d = await r.json();
                const matched    = d.matched    || [];
                const treatments = d.treatments || [];

                if (matched.length || treatments.length) {
                    this.assistActive = true;

                    // Keep full data per specialty (fix: was stripped to {tag,label} only)
                    this.suggestions = matched.map(s => ({
                        tag:            s.tag,
                        label:          s.label,
                        icon:           s.icon,
                        questions:      s.questions      || [],
                        investigations: s.investigations || [],
                        diagnoses:      s.diagnoses      || [],
                    }));

                    // Treatment-level matches — full data from treatments table
                    this.treatmentMatches = treatments;

                    // Flat arrays still used by generateHopi
                    this.suggestedQuestions      = matched.flatMap(s => s.questions).slice(0, 5);
                    this.suggestedInvestigations = [...new Set(matched.flatMap(s => s.investigations))];
                } else {
                    this.assistActive     = false;
                    this.suggestions      = [];
                    this.treatmentMatches = [];
                    this.suggestedQuestions      = [];
                    this.suggestedInvestigations = [];
                }
            } catch(e) { console.warn('Consult Assist error:', e); }
            finally    { this.isLoadingAssist = false; }
        },

        // ── Module toggle ────────────────────────────────────────────────────
        toggleModule(tag) {
            this.acceptedModules.includes(tag) ? this.removeModule(tag) : this.acceptedModules.push(tag);
        },
        removeModule(tag) {
            this.acceptedModules = this.acceptedModules.filter(m => m !== tag);
        },
        // ── Generate HOPI draft ──────────────────────────────────────────────
        generateHopi() {
            const fv = name => { const el = document.querySelector(`[name="${name}"]`); return el ? el.value.trim() : ''; };
            const complaint = (this.form.chief_complaint || '').trim();
            if (!complaint) { alert('Please enter the chief complaint first.'); return; }

            const duration = fv('complaint_duration');
            const severity = this.form.severity || '';

            let draft = `Patient presents with ${complaint}`;
            if (duration) draft += `, ongoing for ${duration}`;
            if (severity) draft += `. Severity: ${severity}`;
            draft += '.';
            if (this.acceptedModules.length) {
                draft += ` Specialty areas assessed: ${this.acceptedModules.map(t => t.replace(/_/g,' ')).join(', ')}.`;
            }
            if (this.$refs.hopiNote) this.$refs.hopiNote.value = draft;
        },

        // ── Pack specialty modules before submit ─────────────────────────────
        packModules() {
            const form = document.getElementById('cForm');
            const moduleFields = {
                orthodontics:   ['ortho_crowding','ortho_spacing','ortho_overjet','ortho_overbite','ortho_midline','ortho_skeletal','ortho_molar','ortho_profile','ortho_symmetry'],
                periodontics:   ['perio_bop','perio_pocket','perio_recess','perio_mobility','perio_furc','perio_calc','perio_plaque','perio_hygiene'],
                endodontics:    ['endo_pain','endo_thermal','endo_percuss','endo_palp','endo_swell','endo_sinus','endo_pulp','endo_mob'],
                smile_design:   ['sd_shade','sd_smile','sd_buccal','sd_ging','sd_props','sd_midline','sd_disco'],
                prosthodontics: ['pros_miss','pros_exist','pros_bone','pros_ridge','pros_occl','pros_tmj'],
            };
            const specialtyFindings = {};
            form.querySelectorAll('[data-module-payload]').forEach(el => el.remove());

            this.acceptedModules.forEach((tag, idx) => {
                const fields   = moduleFields[tag] || [];
                const findings = {};
                fields.forEach(name => {
                    const el = form.querySelector(`[name="${name}"]`);
                    if (el && el.value) findings[name] = el.value;
                });
                specialtyFindings[tag] = findings;

                const tagEl = document.createElement('input');
                tagEl.type  = 'hidden';
                tagEl.name  = `specialty_modules[${idx}][specialty_tag]`;
                tagEl.value = tag;
                tagEl.setAttribute('data-module-payload', '1');
                form.appendChild(tagEl);

                Object.entries(findings).forEach(([k, v]) => {
                    const fi = document.createElement('input');
                    fi.type  = 'hidden';
                    fi.name  = `specialty_modules[${idx}][findings][${k}]`;
                    fi.value = v;
                    fi.setAttribute('data-module-payload', '1');
                    form.appendChild(fi);
                });
            });

            document.getElementById('h-specialty-findings').value   = JSON.stringify(specialtyFindings);
            document.getElementById('h-accepted-specialties').value = JSON.stringify(this.acceptedModules);
        },
    };
}

// ── Tooth chart ───────────────────────────────────────────────────────────────
// Positions are always keyed by the PERMANENT (adult) FDI code — that's what
// drives layout/ordering. `positionMode[pos] === 'primary'` means that slot is
// currently showing/marking the child (primary) tooth instead, e.g. position
// 11 displays & stores against code 51. Molars (16-18 etc.) have no primary
// predecessor, so they never get a toggle — see DentalNotation.hasPrimary().
// initialTeeth: previously-saved chart_data. Two shapes are accepted so old
// records still load cleanly:
//   - New shape (this fix, 2026-07-13): array of objects
//     { tooth, condition, custom, surfaces } — full round-trip of what the
//     dentist picked in the modal.
//   - Legacy shape (pre-fix): flat array of tooth codes, e.g. [11, 16, 55] —
//     only WHICH teeth were marked was ever saved, not the condition. These
//     hydrate into the "Unspecified" bucket in the summary so nothing is
//     silently dropped; the dentist can reopen the tooth to set a condition.
function toothChart(initialTeeth = []) {
    const seedData = {};
    const seedMode = {};
    (initialTeeth || []).forEach((entry) => {
        let code, condition = null, custom = null, surfaces = [];
        if (entry && typeof entry === 'object') {
            code      = Number(entry.tooth);
            condition = entry.condition ?? null;
            custom    = entry.custom ?? null;
            surfaces  = Array.isArray(entry.surfaces) ? entry.surfaces : [];
        } else {
            code = Number(entry); // legacy: bare tooth number, no condition on file
        }
        seedData[code] = { condition, custom, surfaces };
        if (window.DentalNotation.isPrimary(code)) {
            seedMode[window.DentalNotation.D2P[code]] = 'primary';
        }
    });

    return {
        toothData:    seedData, // { code: { condition, custom, surfaces } } — code is whichever (permanent|primary) is active
        positionMode: seedMode, // { permanentPos: 'primary' } — absent/'permanent' = adult tooth
        activeTooth:  null,

        conditions: [
            { key: 'crown',     label: 'Crown / Bridge',      color: '#d97706', bg: '#fef3c7' },
            { key: 'composite', label: 'Filling (Composite)', color: '#2563eb', bg: '#dbeafe' },
            { key: 'amalgam',   label: 'Silver Filling',      color: '#475569', bg: '#f1f5f9' },
            { key: 'veneer',    label: 'Veneer',              color: '#7c3aed', bg: '#ede9fe' },
            { key: 'rct',       label: 'RCT + Crown',         color: '#ea580c', bg: '#ffedd5' },
            { key: 'rct_only',  label: 'RCT',                 color: '#db2777', bg: '#fce7f3' },
            { key: 'implant',   label: 'Implant + Crown',     color: '#0891b2', bg: '#cffafe' },
            { key: 'mobile',    label: 'Mobile Tooth',        color: '#ca8a04', bg: '#fef9c3' },
            { key: 'missing',   label: 'Missing',             color: '#dc2626', bg: '#fee2e2' },
            { key: 'cavity',    label: 'Cavity',              color: '#991b1b', bg: '#fef2f2' },
            { key: 'other',     label: 'Other',               color: '#6b7280', bg: '#f3f4f6' },
        ],

        // Surface only matters clinically for restorations that sit on part of
        // the tooth, not the whole crown — Crown/Bridge, Missing, etc. don't need it.
        SURFACES: [
            { key: 'M', label: 'Mesial' },
            { key: 'D', label: 'Distal' },
            { key: 'O', label: 'Occlusal' },
            { key: 'B', label: 'Buccal' },
            { key: 'L', label: 'Lingual' },
        ],

        needsSurfaces(key) {
            return ['cavity', 'composite', 'amalgam'].includes(key);
        },

        get markedCount() {
            return Object.keys(this.toothData).length;
        },

        // Serialized for the hidden `chart_data` input — this is what actually
        // gets saved. Object shape (not just tooth numbers) is the whole point
        // of this fix: conditions used to be picked in the UI but discarded on
        // save, so reopening a consultation always showed teeth as generically
        // "marked" with no condition. See project memory / mentor note below.
        get serializedChart() {
            return JSON.stringify(
                Object.entries(this.toothData).map(([tooth, v]) => ({
                    tooth:     Number(tooth),
                    condition: v?.condition ?? null,
                    custom:    v?.custom ?? null,
                    surfaces:  (v?.surfaces && v.surfaces.length) ? v.surfaces : undefined,
                }))
            );
        },

        // ── Mixed dentition (adult/child per position) ──────────────────────
        codeAt(pos) {
            return window.DentalNotation.displayCode(pos, this.positionMode[pos] || 'permanent');
        },

        isChild(pos) {
            return this.positionMode[pos] === 'primary';
        },

        canToggle(pos) {
            return window.DentalNotation.hasPrimary(pos);
        },

        isPrimaryCode(code) {
            return window.DentalNotation.isPrimary(code);
        },

        toggleDentition(pos) {
            const oldCode = this.codeAt(pos);
            this.positionMode = { ...this.positionMode, [pos]: this.isChild(pos) ? 'permanent' : 'primary' };
            const newCode = this.codeAt(pos);
            if (oldCode === newCode) return;
            // Carry any existing marking over to the newly-shown code so
            // flipping the toggle never silently drops a dentist's entry.
            const d = { ...this.toothData };
            if (Object.prototype.hasOwnProperty.call(d, oldCode)) {
                d[newCode] = d[oldCode];
                delete d[oldCode];
            }
            this.toothData = d;
        },

        clickTooth(pos)   { this.activeTooth = this.codeAt(pos); },

        // Conditions that don't need a surface close the modal immediately
        // (unchanged behaviour). Conditions that do (cavity/filling) keep the
        // modal open so the surface chips can be picked, then the dentist
        // closes it manually via the × — same pattern as before, just deferred.
        setCondition(key, custom = null) {
            const existing = this.toothData[this.activeTooth] || {};
            const surfaces = this.needsSurfaces(key) ? (existing.surfaces || []) : [];
            this.toothData = { ...this.toothData, [this.activeTooth]: { condition: key, custom, surfaces } };
            if (!this.needsSurfaces(key)) {
                this.activeTooth = null;
            }
        },

        // "Other" — free-text condition (root piece, supraeruption, etc.).
        // Same +Add-custom pattern already used in Clinical Findings.
        pickOther() {
            const current = this.toothData[this.activeTooth];
            const prefill = (current && current.condition === 'other') ? (current.custom || '') : '';
            const v = prompt('Describe the condition (e.g. Root piece, Supraeruption):', prefill);
            if (v && v.trim()) {
                this.setCondition('other', v.trim());
            }
        },

        toggleSurface(s) {
            const cur = this.toothData[this.activeTooth];
            if (!cur) return;
            const set = new Set(cur.surfaces || []);
            set.has(s) ? set.delete(s) : set.add(s);
            this.toothData = { ...this.toothData, [this.activeTooth]: { ...cur, surfaces: Array.from(set) } };
        },

        clearTooth() {
            const d = { ...this.toothData };
            delete d[this.activeTooth];
            this.toothData = d;
            this.activeTooth = null;
        },

        clearAll() { this.toothData = {}; this.positionMode = {}; },

        condColor(t) {
            const key = this.toothData[t]?.condition;
            const c = this.conditions.find(c => c.key === key);
            return c ? c.color : '#6a0f70';
        },

        condLabel(t) {
            const d = this.toothData[t];
            if (!d || !d.condition) return '';
            if (d.condition === 'other') return d.custom || 'Other';
            const c = this.conditions.find(c => c.key === d.condition);
            if (!c) return '';
            const surf = (d.surfaces && d.surfaces.length) ? ' (' + d.surfaces.join(',') + ')' : '';
            return c.label + surf;
        },

        surfaceSuffix(t) {
            const d = this.toothData[t];
            return (d && d.surfaces && d.surfaces.length) ? ' ' + d.surfaces.join('') : '';
        },

        toothTitle(t) {
            const d = this.toothData[t];
            const parts = [];
            if (this.isPrimaryCode(t)) parts.push('Primary (child) tooth');
            if (d && d.surfaces && d.surfaces.length) parts.push('Surfaces: ' + d.surfaces.join(', '));
            return parts.join(' · ');
        },

        markedByCondition(key) {
            return Object.entries(this.toothData)
                .filter(([, v]) => v && v.condition === key)
                .map(([t]) => parseInt(t))
                .sort((a, b) => a - b);
        },

        // Legacy rows carried over from before condition-tracking existed —
        // marked but with no condition on file (see toothChart() header note).
        unspecified() {
            return Object.entries(this.toothData)
                .filter(([, v]) => !v || !v.condition)
                .map(([t]) => parseInt(t))
                .sort((a, b) => a - b);
        },
    };
}

// ── Previous consultation panel ───────────────────────────────────────────────
function prevConsultPanel() {
    function readOption(selectEl, val) {
        if (!selectEl) return {};
        const opt = selectEl.querySelector(`option[value="${val}"]`);
        if (!opt) return {};
        return {
            date:        opt.dataset.date        || '',
            type:        opt.dataset.type        || '',
            doctor:      opt.dataset.doctor      || '',
            complaint:   opt.dataset.complaint   || '',
            specialties: opt.dataset.specialties || '',
            diagnosis:   opt.dataset.diagnosis   || '',
            treatment:   opt.dataset.treatment   || '',
            notes:       opt.dataset.notes       || '',
        };
    }

    return {
        selectedId: '',
        panel: {
            date:'', type:'', doctor:'',
            complaint:'', specialties:'', specialtyList:[],
            diagnosis:'', treatment:'', notes:'',
        },

        init() {
            const sel = this.$el.querySelector('select[name="previous_consultation_id"]');
            if (sel && sel.options.length) {
                this.selectedId = sel.options[0].value;
                this.panel = this._read(sel, this.selectedId);
                this._dispatch();
            }
        },

        updatePanel() {
            const sel = this.$el.querySelector('select[name="previous_consultation_id"]');
            this.panel = this._read(sel, this.selectedId);
            this._dispatch();
        },

        // Bubble the key fields up to the parent consultForm() so the Same Issue
        // read-only block (which lives outside this component's scope) can read them.
        _dispatch() {
            this.$dispatch('prev-panel-updated', {
                id:        this.selectedId,
                complaint: this.panel.complaint,
                hopi:      this.panel.notes,       // notes holds HOPI/finishing notes
                diagnosis: this.panel.diagnosis,
            });
        },

        _read(sel, val) {
            const d = readOption(sel, val);
            return {
                date:          d.date,
                type:          d.type,
                doctor:        d.doctor,
                complaint:     d.complaint,
                specialties:   d.specialties,
                specialtyList: d.specialties ? d.specialties.split(',').filter(s => s.trim()) : [],
                diagnosis:     d.diagnosis,
                treatment:     d.treatment,
                notes:         d.notes,
            };
        },
    };
}
</script>
@endpush
@endsection
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               