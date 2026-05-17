@extends('layouts.app')

@section('page-title', 'New Consultation — '.$patient->name)

@section('head-extra')
<style>
    #df-topbar        { display: none !important; }
    #df-content-inner { padding: 0 !important; max-width: 100% !important; }
    #df-content-area  { background: #f3f4f8 !important; }

    * { box-sizing: border-box; }
    [x-cloak] { display: none !important; }

    /* ── Sticky topbar ── */
    #consult-topbar {
        position: sticky; top: 0; z-index: 50;
        background: white; border-bottom: 1px solid #e5e7eb;
        padding: 0 24px; height: 52px;
        display: flex; align-items: center; justify-content: space-between;
        box-shadow: 0 1px 4px rgba(106,15,112,0.07);
    }
    .ctb-left  { display: flex; align-items: center; gap: 12px; }
    .ctb-tabs  { display: flex; gap: 4px; margin-left: 16px; }
    .ctb-tab   { padding: 5px 14px; font-size: 12px; font-weight: 600; border-radius: 3px; cursor: pointer; border: 1px solid #e5e7eb; color: #6b7280; background: white; transition: all .15s; }
    .ctb-tab.active { background: #6a0f70; color: white; border-color: #6a0f70; }
    .ctb-right { display: flex; align-items: center; gap: 10px; }
    .btn-draft { padding: 6px 14px; font-size: 12px; font-weight: 600; border: 1px solid #d1d5db; background: white; color: #374151; border-radius: 3px; cursor: pointer; transition: all .15s; }
    .btn-draft:hover { border-color: #6a0f70; color: #6a0f70; }
    .btn-save  { padding: 6px 16px; font-size: 12px; font-weight: 600; background: #6a0f70; color: white; border: none; border-radius: 3px; cursor: pointer; transition: background .15s; display: flex; align-items: center; gap: 6px; }
    .btn-save:hover { background: #380740; }

    /* ── Progress ── */
    #prog-wrap { display:flex;align-items:center;gap:8px;font-size:11px;color:#9ca3af; }
    #prog-bar  { width:100px;height:3px;background:#e5e7eb;border-radius:99px;overflow:hidden; }
    #prog-fill { height:100%;background:linear-gradient(90deg,#6a0f70,#b95cb7);border-radius:99px;transition:width .4s; }

    /* ── Patient strip ── */
    #patient-strip { background:white;border-bottom:1px solid #e5e7eb;padding:14px 24px;display:flex;align-items:center;gap:20px;flex-wrap:wrap; }
    .ps-avatar { width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,#6a0f70,#380740);display:flex;align-items:center;justify-content:center;color:white;font-size:18px;font-weight:600;flex-shrink:0;font-family:'Cormorant Garamond',serif; }
    .ps-info   { flex:1;min-width:200px; }
    .ps-name   { font-size:16px;font-weight:700;color:#111827;display:flex;align-items:center;gap:8px;font-family:'Cormorant Garamond',serif; }
    .ps-badge  { font-size:10px;font-weight:700;padding:2px 8px;border-radius:99px;background:#dcfce7;color:#16a34a; }
    .ps-meta   { display:flex;gap:16px;flex-wrap:wrap;margin-top:4px; }
    .ps-meta span { font-size:12px;color:#6b7280;display:flex;align-items:center;gap:4px; }
    .ps-meta b { color:#374151; }
    .ps-divider { width:1px;height:52px;background:#f3f4f6;flex-shrink:0; }
    .ps-alerts { display:flex;flex-direction:column;gap:4px;min-width:200px; }
    .ps-alert-row { display:flex;align-items:flex-start;gap:6px;font-size:11px; }
    .ps-alert-label { font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;min-width:72px;flex-shrink:0; }
    .ps-alert-val { color:#374151; }
    .ps-alert-val.danger { color:#dc2626;font-weight:600; }
    .ps-alert-val.warn   { color:#d97706;font-weight:600; }
    .ps-alert-val.ok     { color:#16a34a; }

    /* ── Section cards ── */
    .c-card { background:white;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;transition:border-color .15s; }
    .c-card:focus-within { border-color:#b95cb7;box-shadow:0 0 0 3px rgba(106,15,112,.06); }
    .c-card-head { padding:12px 18px;border-bottom:1px solid #f3f4f6;background:#faf5fb;display:flex;align-items:center;justify-content:space-between;cursor:pointer; }
    .sec-label { font-size:11px;font-weight:700;color:#6a0f70;letter-spacing:.07em;text-transform:uppercase;display:flex;align-items:center;gap:6px; }
    .sec-num   { width:20px;height:20px;border-radius:50%;background:#6a0f70;color:white;font-size:10px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0; }

    /* ── Section collapse ── */
    .sec-collapse-btn { width:100%;display:flex;align-items:center;justify-content:space-between;background:none;border:none;cursor:pointer;padding:0;font-family:inherit;text-align:left; }
    .sec-chevron { transition:transform .2s;color:#9ca3af;flex-shrink:0; }
    .sec-chevron.open { transform:rotate(180deg); }
    .sec-summary { font-size:10px;color:#9ca3af;font-weight:400;margin-left:auto;margin-right:10px; }

    /* ── Form inputs ── */
    .df-input { width:100%;border:1px solid #e5e7eb;border-radius:5px;padding:7px 10px;font-size:13px;font-family:'DM Sans',sans-serif;color:#111827;background:white;outline:none;transition:border-color .15s,box-shadow .15s; }
    .df-input:focus { border-color:#6a0f70;box-shadow:0 0 0 3px rgba(106,15,112,.09); }
    .df-input::placeholder { color:#9ca3af; }
    textarea.df-input { resize:vertical; }
    .df-label { display:block;font-size:11px;font-weight:600;color:#6b7280;margin-bottom:4px;text-transform:uppercase;letter-spacing:.04em; }
    .df-label .req { color:#dc2626; }

    /* ── Body ── */
    .consult-body { padding:20px 24px;display:flex;flex-direction:column;gap:18px; }

    /* ── Visit type cards ── */
    .vt-card { border:2px solid #e5e7eb;border-radius:7px;padding:12px 10px;cursor:pointer;transition:all .15s;text-align:center;background:white; }
    .vt-card:hover { border-color:#b95cb7;background:#faf5fb; }
    .vt-card.sel-emergency { border-color:#dc2626;background:#fef2f2; }
    .vt-card.sel-routine   { border-color:#6a0f70;background:#faf5fb; }
    .vt-card.sel-followup  { border-color:#2563eb;background:#eff6ff; }
    .vt-icon  { width:38px;height:38px;border-radius:50%;margin:0 auto 6px;display:flex;align-items:center;justify-content:center; }
    .vt-check { width:18px;height:18px;border:2px solid #e5e7eb;border-radius:50%;margin:8px auto 0;display:flex;align-items:center;justify-content:center;transition:all .15s; }
    .sel-emergency .vt-check { border-color:#dc2626;background:#dc2626; }
    .sel-routine   .vt-check { border-color:#6a0f70;background:#6a0f70; }
    .sel-followup  .vt-check { border-color:#2563eb;background:#2563eb; }

    /* ── Severity pills ── */
    .sev-btn { padding:5px 12px;border-radius:99px;font-size:12px;font-weight:600;border:1.5px solid #e5e7eb;background:white;cursor:pointer;transition:all .12s;color:#6b7280; }
    .sev-btn:hover { border-color:#b95cb7;color:#6a0f70; }
    .sev-mild     { background:#f0fdf4;border-color:#22c55e;color:#16a34a; }
    .sev-moderate { background:#fff7ed;border-color:#f97316;color:#ea580c; }
    .sev-severe   { background:#fef2f2;border-color:#ef4444;color:#dc2626; }

    /* ── Photo grid ── */
    .photo-grid { display:grid;grid-template-columns:repeat(5,1fr);gap:10px; }
    .photo-slot { border:1.5px dashed #d1d5db;border-radius:6px;aspect-ratio:1;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;transition:all .15s;background:#fafafa;position:relative;overflow:hidden; }
    .photo-slot:hover { border-color:#6a0f70;background:#faf5fb; }
    .photo-slot.filled { border-style:solid;border-color:#6a0f70; }
    .photo-slot img { width:100%;height:100%;object-fit:cover; }
    .photo-check { position:absolute;top:4px;right:4px;width:16px;height:16px;background:#6a0f70;border-radius:50%;display:flex;align-items:center;justify-content:center; }
    .photo-label { font-size:9px;color:#9ca3af;text-align:center;margin-top:5px;line-height:1.3; }

    /* ── Investigation rows ── */
    .inv-row { display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #f9fafb; }
    .inv-row:last-child { border-bottom:none; }

    /* ── Treatment columns ── */
    .tx-col-head { font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:6px 0;border-bottom:2px solid currentColor;margin-bottom:8px; }
    .tx-row { display:flex;align-items:center;justify-content:space-between;padding:5px 8px;background:#f9fafb;border-radius:5px;margin-bottom:4px;font-size:12px;color:#374151; }
    .tx-row .tx-rm { color:#d1d5db;cursor:pointer;transition:color .12s;background:none;border:none;padding:0;line-height:1; }
    .tx-row .tx-rm:hover { color:#dc2626; }
    .tx-add-select { width:100%;border:1.5px dashed #d1d5db;border-radius:5px;padding:6px 8px;font-size:12px;color:#6b7280;cursor:pointer;background:white;outline:none;transition:border-color .15s; }
    .tx-add-select:focus { border-color:#6a0f70;border-style:solid; }

    /* ── Treatment plan table ── */
    .tp-table { width:100%;border-collapse:collapse;font-size:12px; }
    .tp-table th { text-align:left;padding:7px 10px;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #e5e7eb;background:#f9fafb; }
    .tp-table td { padding:6px 8px;border-bottom:1px solid #f3f4f6;vertical-align:middle; }
    .tp-table tbody tr:hover td { background:#faf5fb; }
    .tp-table tfoot td { padding:8px 10px;font-weight:700;font-size:12px;background:#f9fafb;border-top:1px solid #e5e7eb; }

    /* ── DBM ── */
    .dbm-sec-head { font-size:10px;font-weight:700;color:#6a0f70;border-bottom:1.5px solid #e9d5ff;padding-bottom:3px;margin-bottom:4px;margin-top:2px; }
    .dbm-hdr { display:grid;grid-template-columns:1fr 42px 42px 42px;gap:0;font-size:9px;color:#9ca3af;font-weight:600;text-align:center;padding:2px 4px;margin-bottom:2px; }
    .dbm-hdr span:first-child { text-align:left; }
    .dbm-row { display:grid;grid-template-columns:1fr 42px 42px 42px;align-items:center;padding:3px 4px;border-radius:4px;transition:background .1s; }
    .dbm-row:hover { background:#f9fafb; }
    .dbm-row span { font-size:10px;color:#374151; }
    .dbm-dot-wrap { display:flex;align-items:center;justify-content:center; }
    .dbm-dot { width:14px;height:14px;border-radius:50%;border:1.5px solid #d1d5db;cursor:pointer;transition:all .12s;background:white;display:flex;align-items:center;justify-content:center; }
    .dbm-dot:hover { border-color:#6a0f70; }
    .dbm-dot.active { background:#6a0f70;border-color:#6a0f70; }
    .dbm-dot.active::after { content:'';display:block;width:5px;height:5px;border-radius:50%;background:white; }

    /* ── Prescription ── */
    .rx-collapse-btn { width:100%;display:flex;align-items:center;justify-content:space-between;padding:11px 18px;background:white;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;color:#374151;border-bottom:1px solid #f3f4f6;transition:background .12s; }
    .rx-collapse-btn:hover { background:#f9fafb; }
    .rx-collapse-btn.open  { background:#faf5fb;color:#6a0f70; }
    .rx-section-head { display:flex;align-items:center;gap:8px;font-size:11px;font-weight:700;color:#6a0f70;text-transform:uppercase;letter-spacing:.06em; }
    .rx-input { border:1px solid #e5e7eb;border-radius:5px;padding:5px 8px;font-size:12px;font-family:'DM Sans',sans-serif;color:#374151;background:white;outline:none;transition:border-color .15s;width:100%; }
    .rx-input:focus { border-color:#6a0f70; }
    .rx-pill { display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border-radius:99px;font-size:11px;font-weight:600;border:1.5px solid #e5e7eb;background:white;cursor:pointer;color:#6b7280;transition:all .12px;white-space:nowrap; }
    .rx-pill:hover { border-color:#6a0f70;color:#6a0f70; }
    .rx-pill.on { background:#6a0f70;border-color:#380740;color:white; }

    /* ── AOCP cards ── */
    .aocp-card { border:1.5px solid #e5e7eb;border-radius:6px;padding:10px 12px;cursor:pointer;transition:all .15s; }
    .aocp-card.active { border-color:#6a0f70;background:#faf5fb; }

    /* ── Recall pills ── */
    .recall-pill { padding:5px 12px;border-radius:99px;font-size:11px;font-weight:600;border:1.5px solid #e5e7eb;background:white;cursor:pointer;transition:all .12s;color:#6b7280;white-space:nowrap; }
    .recall-pill:hover { border-color:#b95cb7;color:#6a0f70; }
    .recall-pill.active { background:#6a0f70;border-color:#380740;color:white; }

    /* ── Upload zone ── */
    .upload-zone { border:1.5px dashed #d1d5db;border-radius:6px;padding:16px;text-align:center;cursor:pointer;transition:all .15s; }
    .upload-zone:hover { border-color:#6a0f70;background:#faf5fb; }
</style>
@endsection

@section('content')
<div x-data="consultationForm()" x-init="init()"
     @open-tooth-picker.window="showToothPicker=true"
     @open-tp-picker.window="openTpRowPicker($event.detail.type,$event.detail.idx)"
     @add-tp-row.window="addTpRow($event.detail.type)"
     @remove-tp-row.window="removeTpRow($event.detail.type,$event.detail.idx)"
     @recalc-tp.window="calcTotal($event.detail.type)"
     style="background:#f3f4f8;min-height:100vh;">

<form id="cForm" @submit.prevent="submitConsultation()">
<input type="hidden" name="_token" value="{{ csrf_token() }}">
<input type="hidden" name="patient_id" value="{{ $patient->id }}">
<input type="hidden" name="status" x-model="status">

{{-- ══ STICKY TOPBAR ══ --}}
<div id="consult-topbar">
    <div class="ctb-left">
        <a href="{{ route('patients.show', $patient) }}" style="color:#9ca3af;display:flex;align-items:center;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <div class="ctb-tabs">
            <button type="button" class="ctb-tab active">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;margin-right:4px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Consultation
            </button>
            <button type="button" class="ctb-tab">Follow-up</button>
            <button type="button" class="ctb-tab">Treatment Visit</button>
        </div>
    </div>
    <div class="ctb-right">
        <div id="prog-wrap">
            Completed
            <div id="prog-bar"><div id="prog-fill" :style="'width:'+progress+'%'"></div></div>
            <span x-text="progress+'%'" style="font-weight:700;color:#6a0f70;min-width:30px;"></span>
        </div>
        <button type="button" class="btn-draft" @click="saveDraft()">Save Draft</button>
        <button type="submit" class="btn-save">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Save &amp; Continue
        </button>
        <button type="button" style="background:none;border:1px solid #e5e7eb;border-radius:4px;padding:5px 8px;color:#6b7280;cursor:pointer;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
        </button>
    </div>
</div>

{{-- ══ PATIENT STRIP ══ --}}
<div id="patient-strip">
    <div class="ps-avatar">{{ $patient->initials }}</div>
    <div class="ps-info">
        <div class="ps-name">
            {{ $patient->name }}
            <span class="ps-badge">{{ ucfirst($patient->recall_status ?? 'Active') }}</span>
        </div>
        <div class="ps-meta">
            <span><b>PID:</b> PNT-{{ str_pad($patient->id,7,'0',STR_PAD_LEFT) }}</span>
            @if($patient->age ?? false)<span><b>{{ $patient->age }}Y</b> / {{ ucfirst($patient->gender ?? '') }}</span>@endif
            @if($patient->phone)<span>
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.1 12 19.79 19.79 0 0 1 1.03 3.33 2 2 0 0 1 3 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 21 16.92z"/></svg>
                {{ $patient->phone }}
            </span>@endif
            @if($patient->city)<span>
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                {{ $patient->city }}
            </span>@endif
            @if($patient->source)<span>Source: <b style="color:#6a0f70;">{{ $patient->source }}</b></span>@endif
            <span>Since: {{ $patient->created_at->format('d M Y') }}</span>
        </div>
    </div>
    <div class="ps-divider"></div>
    <div class="ps-alerts">
        <div class="ps-alert-row">
            <span class="ps-alert-label">Medical</span>
            <span class="ps-alert-val {{ $patient->medical_alert ? 'danger' : 'ok' }}">{{ $patient->medical_alert ?: 'No known conditions' }}</span>
        </div>
        <div class="ps-alert-row">
            <span class="ps-alert-label">Allergies</span>
            <span class="ps-alert-val {{ $patient->allergies ? 'warn' : 'ok' }}">
                @php $a = $patient->allergies; @endphp
                {{ $a ? (is_array($a) ? implode(', ',$a) : $a) : 'None reported' }}
            </span>
        </div>
        <div class="ps-alert-row">
            <span class="ps-alert-label">Habits</span>
            <span class="ps-alert-val">
                @php $h = $patient->habits; @endphp
                {{ $h ? (is_array($h) ? implode(', ',$h) : $h) : '—' }}
            </span>
        </div>
    </div>
</div>

{{-- ══ FORM BODY — single column ══ --}}
<div class="consult-body">

    {{-- Expand / Collapse All --}}
    <div style="display:flex;align-items:center;justify-content:flex-end;gap:8px;margin-bottom:-4px;">
        <button type="button" onclick="toggleAllSections(true)"
                style="font-size:11px;font-weight:600;color:#6a0f70;background:none;border:1px solid rgba(106,15,112,.25);padding:4px 12px;border-radius:4px;cursor:pointer;"
                onmouseover="this.style.background='#faf5fb'" onmouseout="this.style.background='none'">
            ↕ Expand All
        </button>
        <button type="button" onclick="toggleAllSections(false)"
                style="font-size:11px;font-weight:600;color:#6b7280;background:none;border:1px solid #e5e7eb;padding:4px 12px;border-radius:4px;cursor:pointer;"
                onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='none'">
            ↕ Collapse All
        </button>
    </div>

    {{-- ─── 1. Chief Complaint ─── --}}
    <div class="c-card" x-data="{open:false}">
        <div class="c-card-head" @click="open=!open">
            <span class="sec-label"><span class="sec-num">1</span>Chief Complaint</span>
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="sec-summary" x-show="!open && form.chief_complaint" x-cloak
                      x-text="form.chief_complaint.substring(0,50)+(form.chief_complaint.length>50?'…':'')"></span>
                <svg class="sec-chevron" :class="open?'open':''" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </div>
        </div>
        <div x-show="open" x-collapse style="padding:18px;display:flex;flex-direction:column;gap:14px;">
            <div>
                <label class="df-label">Chief Complaint <span class="req">*</span></label>
                <textarea name="chief_complaint" x-model="form.chief_complaint" class="df-input" rows="2"
                          placeholder="e.g. Sensitivity in upper and lower right back teeth since 3 days."></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;">
                <div>
                    <label class="df-label">Duration</label>
                    <select name="complaint_duration" x-model="form.complaint_duration" class="df-input">
                        <option value="">Select</option>
                        @foreach(['1 Day','2 Days','3 Days','1 Week','2 Weeks','1 Month','3 Months','6 Months','1 Year','Chronic'] as $d)
                        <option>{{ $d }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="df-label">Severity</label>
                    <div style="display:flex;gap:6px;margin-top:4px;">
                        @foreach(['Mild','Moderate','Severe'] as $sev)
                        <button type="button" @click="form.severity='{{ $sev }}'"
                                :class="form.severity==='{{ $sev }}' ? 'sev-{{ strtolower($sev) }}' : ''"
                                class="sev-btn" style="flex:1;">{{ $sev }}</button>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="df-label">Location</label>
                    <select name="location" x-model="form.location" class="df-input">
                        <option value="">Select</option>
                        @foreach(['Upper Left','Upper Right','Lower Left','Lower Right','Upper & Lower Left','Upper & Lower Right','Full Mouth','Anterior','Posterior'] as $loc)
                        <option>{{ $loc }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- FIX 1: Tooth/Area — dispatch window event so parent Alpine scope handles showToothPicker --}}
                <div>
                    <label class="df-label">Tooth / Area</label>
                    <div style="display:flex;gap:4px;">
                        <input type="text" name="tooth_area" x-model="form.tooth_area"
                               class="df-input" placeholder="#14, 15, 46" style="flex:1;" readonly
                               @click="$dispatch('open-tooth-picker')">
                        <button type="button" @click="$dispatch('open-tooth-picker')"
                                style="border:1px solid #e5e7eb;border-radius:5px;padding:0 10px;background:white;cursor:pointer;color:#6a0f70;font-size:11px;font-weight:600;white-space:nowrap;transition:all .15s;"
                                onmouseover="this.style.background='#faf5fb'" onmouseout="this.style.background='white'">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                        </button>
                    </div>
                </div>
            </div>
            <div>
                <label class="df-label">Notes (optional)</label>
                <textarea name="complaint_notes" x-model="form.complaint_notes" class="df-input" rows="2"
                          placeholder="Additional context about the complaint…"></textarea>
            </div>
        </div>
    </div>

    {{-- ─── 2. Visit Type ─── --}}
    <div class="c-card" x-data="{open:false}">
        <div class="c-card-head" @click="open=!open">
            <span class="sec-label"><span class="sec-num">2</span>Visit Type</span>
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="sec-summary" x-show="!open && form.visit_type" x-cloak
                      x-text="form.visit_type ? form.visit_type.charAt(0).toUpperCase()+form.visit_type.slice(1) : ''"></span>
                <svg class="sec-chevron" :class="open?'open':''" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </div>
        </div>
        <div x-show="open" x-collapse style="padding:18px;">
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;max-width:560px;">
                @foreach([
                    ['emergency','#dc2626','#fef2f2','Pain / Swelling / Trauma','M21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3ZM12 9v4M12 17h.01'],
                    ['routine','#6a0f70','#f5f3ff','Full Mouth Evaluation','M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z'],
                    ['followup','#2563eb','#eff6ff','Review / Re-evaluation','M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zM9 22V12h6v10'],
                ] as [$vt,$col,$bg,$sub,$path])
                <div class="vt-card" :class="form.visit_type==='{{ $vt }}' ? 'sel-{{ $vt }}' : ''"
                     @click="form.visit_type='{{ $vt }}'">
                    <div class="vt-icon" :style="form.visit_type==='{{ $vt }}' ? 'background:{{ $bg }}' : 'background:#f3f4f6'">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                             :stroke="form.visit_type==='{{ $vt }}' ? '{{ $col }}' : '#9ca3af'"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="{{ $path }}"/>
                        </svg>
                    </div>
                    <div style="font-size:11px;font-weight:700;" :style="form.visit_type==='{{ $vt }}' ? 'color:{{ $col }}' : 'color:#6b7280'">
                        {{ ucfirst($vt === 'routine' ? 'Routine / Comprehensive' : $vt) }}
                    </div>
                    <div style="font-size:10px;color:#9ca3af;margin-top:2px;">{{ $sub }}</div>
                    <div class="vt-check">
                        <svg x-show="form.visit_type==='{{ $vt }}'" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                </div>
                @endforeach
            </div>
            <input type="hidden" name="visit_type" x-model="form.visit_type">
            @if($patient->medical_alert)
            <div style="margin-top:12px;display:flex;align-items:flex-start;gap:6px;padding:8px 12px;background:#fef2f2;border:1px solid #fecaca;border-radius:5px;font-size:11px;color:#dc2626;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                <span><strong>Alert:</strong> {{ $patient->medical_alert }}</span>
            </div>
            @endif
        </div>
    </div>

    {{-- ─── 3. Photographs ─── --}}
    <div class="c-card" x-data="{open:false}">
        <div class="c-card-head" @click="open=!open">
            <span class="sec-label">
                <span class="sec-num">3</span>Photographs
                <span style="font-size:9px;color:#9ca3af;font-weight:400;text-transform:none;letter-spacing:0;">Mandatory for Comprehensive</span>
            </span>
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:11px;color:#9ca3af;"><span x-text="photoCount" style="font-weight:700;color:#6a0f70;"></span>/9</span>
                <svg class="sec-chevron" :class="open?'open':''" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </div>
        </div>
        <div x-show="open" x-collapse style="padding:18px;">
            <div class="photo-grid">
                @php $slots=['Extraoral','Extraoral Smile','Upper Arch','Lower Arch','Right Buccal','Left Buccal','Front','Occlusal Right','Occlusal Left']; @endphp
                @foreach($slots as $i=>$name)
                <div>
                    <div class="photo-slot" :class="photos[{{ $i }}] ? 'filled':''" @click="triggerPhoto({{ $i }})">
                        <template x-if="photos[{{ $i }}]">
                            <div style="width:100%;height:100%;position:relative;">
                                <img :src="photos[{{ $i }}].preview">
                                <div class="photo-check"><svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                            </div>
                        </template>
                        <template x-if="!photos[{{ $i }}]">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                        </template>
                        <input type="file" accept="image/*" style="display:none;" id="ph-{{ $i }}" @change="handlePhoto($event,{{ $i }})">
                    </div>
                    <div class="photo-label">{{ $name }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ─── 4. Intraoral Scans ─── --}}
    <div class="c-card" x-data="{open:false}">
        <div class="c-card-head" @click="open=!open">
            <span class="sec-label"><span class="sec-num">4</span>Intraoral Scans</span>
            <div style="display:flex;align-items:center;gap:8px;">
                <span x-show="scanFiles.length>0" style="font-size:10px;font-weight:700;color:#16a34a;" x-text="scanFiles.length+' file(s)'"></span>
                <svg class="sec-chevron" :class="open?'open':''" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </div>
        </div>
        <div x-show="open" x-collapse style="padding:18px;">
            <div style="border:2px dashed #d1d5db;border-radius:10px;padding:28px 16px;text-align:center;cursor:pointer;transition:all .15s;background:#fafafa;"
                 @click="document.getElementById('scan-upload').click()"
                 onmouseover="this.style.borderColor='#6a0f70';this.style.background='#faf5fb';"
                 onmouseout="this.style.borderColor='#d1d5db';this.style.background='#fafafa';">
                <div style="width:44px;height:44px;border-radius:50%;background:#f5f3ff;margin:0 auto 10px;display:flex;align-items:center;justify-content:center;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                </div>
                <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:3px;">Upload Scan Files</div>
                <div style="font-size:11px;color:#9ca3af;">STL · DICOM · JPG · PNG — Multiple files allowed</div>
                <input type="file" id="scan-upload" name="scan_files[]" multiple accept=".stl,.dcm,.jpg,.jpeg,.png,.pdf" style="display:none;" @change="handleScanUpload($event)">
            </div>
            <template x-if="scanFiles.length>0">
                <div style="margin-top:12px;">
                    <div style="font-size:10px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">Uploaded Files</div>
                    <template x-for="(f,i) in scanFiles" :key="i">
                        <div style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:#f9fafb;border-radius:6px;margin-bottom:4px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <div style="flex:1;overflow:hidden;">
                                <div x-text="f.name" style="font-size:12px;color:#374151;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></div>
                                <div x-text="(f.size/1024/1024).toFixed(2)+' MB'" style="font-size:10px;color:#9ca3af;"></div>
                            </div>
                            <button type="button" @click="scanFiles.splice(i,1)" style="background:none;border:1px solid #fecaca;border-radius:4px;color:#dc2626;cursor:pointer;padding:2px 6px;font-size:10px;font-weight:600;">✕</button>
                        </div>
                    </template>
                </div>
            </template>
            <div style="margin-top:14px;">
                <label class="df-label">Scan Date</label>
                <input type="date" name="scan_date" x-model="form.scan_date" class="df-input" style="max-width:220px;">
            </div>
        </div>
    </div>

    {{-- ─── 5. Investigations ─── --}}
    <div class="c-card" x-data="{open:false}">
        <div class="c-card-head" @click="open=!open">
            <span class="sec-label"><span class="sec-num">5</span>Investigations</span>
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="sec-summary" x-show="!open && form.investigations.length" x-cloak x-text="form.investigations.length+' selected'"></span>
                <svg class="sec-chevron" :class="open?'open':''" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </div>
        </div>
        <div x-show="open" x-collapse style="padding:14px 18px;">
            @php $invs=['iopa'=>'IOPA','opg'=>'OPG','cbct'=>'CBCT','photographs'=>'Photographs','intraoral'=>'Intraoral Scan','blood_tests'=>'Blood Tests','mri_usg'=>'MRI / USG','other'=>'Other']; @endphp
            @foreach($invs as $key=>$label)
            <div class="inv-row" style="flex-wrap:wrap;gap:4px;">
                <div style="display:flex;align-items:center;gap:6px;flex:1;min-width:140px;">
                    <input type="checkbox" id="inv_{{$key}}" name="investigations[]" value="{{$key}}"
                           x-model="form.investigations"
                           style="width:13px;height:13px;accent-color:#6a0f70;cursor:pointer;flex-shrink:0;">
                    <label for="inv_{{$key}}" style="font-size:13px;color:#374151;cursor:pointer;">{{$label}}</label>
                </div>
                <template x-if="form.investigations.includes('{{$key}}')">
                    <div style="display:flex;align-items:center;gap:6px;width:100%;padding-left:19px;margin-top:4px;">
                        <input type="text" name="inv_detail_{{$key}}"
                               placeholder="{{ in_array($key,['iopa','opg','photographs','intraoral']) ? '# images' : 'details' }}"
                               class="df-input" style="flex:1;padding:4px 8px;font-size:12px;">
                        @if(in_array($key,['iopa','opg','cbct','photographs','intraoral']))
                        <label style="display:flex;align-items:center;gap:3px;font-size:11px;color:#6a0f70;font-weight:600;cursor:pointer;white-space:nowrap;border:1px solid rgba(106,15,112,.3);padding:4px 9px;border-radius:4px;background:white;">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Upload
                            <input type="file" name="inv_file_{{$key}}[]" multiple accept="image/*,.pdf,.dcm" style="display:none;" @change="handleInvUpload($event,'{{$key}}')">
                        </label>
                        @endif
                        <span x-show="(invFiles['{{$key}}']||[]).length>0"
                              style="font-size:10px;color:#16a34a;font-weight:600;white-space:nowrap;"
                              x-text="(invFiles['{{$key}}']||[]).length+' file(s)'"></span>
                    </div>
                </template>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ─── 6. Clinical Findings ─── --}}
    <div class="c-card" x-data="{open:false}">
        <div class="c-card-head" @click="open=!open">
            <span class="sec-label"><span class="sec-num">6</span>Clinical Findings</span>
            <div style="display:flex;align-items:center;gap:8px;">
                <button type="button" @click.stop="document.getElementById('tooth-chart-modal').style.display='flex'"
                        style="font-size:10px;color:#6a0f70;border:1px solid rgba(106,15,112,.25);padding:3px 9px;border-radius:3px;background:white;cursor:pointer;display:flex;align-items:center;gap:4px;">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    Chart Teeth
                </button>
                <svg class="sec-chevron" :class="open?'open':''" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </div>
        </div>
        <div x-show="open" x-collapse style="padding:14px 18px;">
            @php
            $clinFields = [
                'soft_tissue'         => ['Soft Tissue', ['Normal','Mild Gingival Inflammation','Moderate Gingival Inflammation','Severe Gingival Inflammation','Ulceration','Swelling','Stomatitis','Other']],
                'caries'              => ['Caries', ['None','Caries #14,15,46','Multiple Caries','Deep Caries (Pulp)','Secondary Caries','Caries All Quads','Other']],
                'periodontal'         => ['Periodontal', ['Normal','Mild Gingivitis','Moderate Gingivitis','Severe Gingivitis','Mild Periodontitis','Moderate Periodontitis','Severe Periodontitis']],
                'bleeding_on_probing' => ['Bleeding on Probing', ['Absent','Present','Generalised']],
                'plaque_index'        => ['Plaque Index', ['Good','Fair','Moderate','Poor']],
                'occlusion'           => ['Occlusion', ['Class I','Class II Div 1','Class II Div 2','Class III','Edge to Edge','Cross Bite']],
                'tmj'                 => ['TMJ', ['Normal','No Clicking / No Pain','Clicking','Pain on Opening','Restricted Opening','TMD']],
                'existing_condition'  => ['Existing Condition', ['None','Composite Filling','Amalgam Filling','PFM Crown','Zirconia Crown','Bridge (PFM)','Bridge (Zirconia)','Implant','Implant + Crown','RCT Done','Stainless Steel Crown','Denture','Orthodontic Brackets','Other']],
                'oral_hygiene'        => ['Oral Hygiene', ['Excellent','Good','Fair','Poor']],
            ];
            @endphp
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                {{-- FIX 4: Clinical selects — handle __add__ custom option with @change --}}
                @foreach($clinFields as $fld=>[$lbl,$opts])
                <div>
                    <label class="df-label" style="margin-bottom:2px;">{{ $lbl }}</label>
                    <select name="clinical_{{$fld}}" x-model="form.clinical.{{$fld}}" class="df-input" style="padding:6px 8px;font-size:13px;"
                            @change="if($event.target.value==='__add__'){var v=prompt('Enter custom {{ $lbl }}:');if(v&&v.trim()){form.clinical.{{$fld}}=v.trim();}else{form.clinical.{{$fld}}='';}}">
                        <option value="">Select</option>
                        @foreach($opts as $o)<option>{{$o}}</option>@endforeach
                        <option value="__add__">+ Add custom</option>
                    </select>
                </div>
                @endforeach
            </div>
            <div style="margin-top:12px;">
                <label class="df-label">Additional Notes</label>
                <textarea name="clinical_notes" x-model="form.clinical.notes" class="df-input" rows="2"
                          placeholder="e.g. Generalised sensitivity, tartar deposits in lower anteriors."></textarea>
            </div>
            <div id="clinical-chart-summary-wrap" style="margin-top:12px;display:none;">
                <div style="padding:8px 12px;background:#faf5fb;border:1px solid rgba(106,15,112,.15);border-radius:6px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                        <span style="font-size:10px;font-weight:700;color:#6a0f70;text-transform:uppercase;letter-spacing:.05em;">Tooth Charting</span>
                        <button type="button" onclick="document.getElementById('tooth-chart-modal').style.display='flex'"
                                style="font-size:10px;color:#6a0f70;background:none;border:none;cursor:pointer;font-weight:600;">Edit Chart →</button>
                    </div>
                    <div id="clinical-chart-summary" style="font-size:11px;color:#374151;line-height:1.8;"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ─── 7. Radiographic Findings ─── --}}
    <div class="c-card" x-data="{open:false}">
        <div class="c-card-head" @click="open=!open">
            <span class="sec-label"><span class="sec-num">7</span>Radiographic Findings</span>
            <svg class="sec-chevron" :class="open?'open':''" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
        </div>
        <div x-show="open" x-collapse style="padding:14px 18px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            @foreach(['opg'=>'OPG Findings','iopa'=>'IOPA Findings','cbct'=>'CBCT Findings','notes'=>'Interpretation'] as $k=>$lbl)
            <div>
                <label class="df-label" style="margin-bottom:2px;">{{ $lbl }}</label>
                <textarea name="radio_{{$k}}" x-model="form.radio.{{$k}}" class="df-input" rows="3"
                          style="resize:vertical;"
                          placeholder="{{ $k==='cbct' ? 'N/A' : 'Enter findings…' }}"></textarea>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ─── 8. DBM 35-Point Checklist ─── FIX 2: Added proper open/close with chevron --}}
    <div class="c-card" x-data="{open:false}">
        <div class="c-card-head" @click="open=!open">
            <span class="sec-label"><span class="sec-num">8</span>DBM 35-Point Checklist</span>
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="display:flex;align-items:center;gap:6px;">
                    <div style="width:80px;height:3px;background:#f3f4f6;border-radius:99px;overflow:hidden;">
                        <div id="dbm-prog-fill" style="height:100%;width:0%;background:#6a0f70;border-radius:99px;transition:width .3s;"></div>
                    </div>
                    <span id="dbm-score-badge" style="font-size:11px;font-weight:700;color:#6a0f70;">0/33</span>
                </div>
                <div style="display:flex;align-items:center;gap:6px;padding:4px 10px;background:#f9fafb;border-radius:5px;" @click.stop>
                    <span style="font-size:10px;color:#9ca3af;font-weight:600;">Shade</span>
                    <input type="text" name="dbm_tooth_shade" placeholder="A2" style="width:40px;border:1px solid #e5e7eb;border-radius:4px;padding:2px 5px;font-size:11px;outline:none;">
                    <span style="font-size:10px;color:#9ca3af;font-weight:600;margin-left:4px;">Whitening</span>
                    <button type="button" onclick="event.stopPropagation();dbmYN(this,'whitening','Y')" style="padding:1px 7px;border:1.5px solid #e5e7eb;border-radius:3px;font-size:10px;font-weight:700;cursor:pointer;background:white;">Y</button>
                    <button type="button" onclick="event.stopPropagation();dbmYN(this,'whitening','N')" style="padding:1px 7px;border:1.5px solid #e5e7eb;border-radius:3px;font-size:10px;font-weight:700;cursor:pointer;background:white;">N</button>
                </div>
                <svg class="sec-chevron" :class="open?'open':''" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </div>
        </div>
        <div x-show="open" x-collapse style="padding:14px 18px;">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr;gap:16px;">

                {{-- Col 1: Hard / Soft Tissue --}}
                <div>
                    <div class="dbm-sec-head">Hard / Soft Tissue</div>
                    <div style="display:grid;grid-template-columns:1fr 38px 38px;font-size:9px;color:#9ca3af;font-weight:600;text-align:center;padding:2px 4px;margin-bottom:2px;"><span></span><span>Healthy</span><span>Observe</span></div>
                    @foreach([[0,'Jaw joints'],[1,'Glands'],[2,'Muscles'],[3,'Cheek tissue'],[4,'Tongue'],[5,'Floor of mouth'],[6,'Roof of mouth'],[7,'Oral cancer'],[8,'Lip muscle']] as [$i,$l])
                    <div class="dbm-row" style="grid-template-columns:1fr 30px 30px;" id="dbmr-{{$i}}">
                        <span>{{$l}}</span>
                        <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{$i}},'healthy',this)"></div></div>
                        <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{$i}},'observe',this)"></div></div>
                    </div>
                    @endforeach
                </div>

                {{-- Col 2: Gum Assessment --}}
                <div>
                    <div class="dbm-sec-head">Gum Assessment</div>
                    <div class="dbm-hdr"><span></span><span>Y</span><span>N</span><span>N/A</span></div>
                    @foreach([[9,'Gum score done'],[10,'Gum recession present'],[11,'Hygiene referral discussed']] as [$i,$l])
                    <div class="dbm-row" id="dbmr-{{$i}}">
                        <span>{{$l}}</span>
                        <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{$i}},'yes',this)"></div></div>
                        <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{$i}},'no',this)"></div></div>
                        <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{$i}},'na',this)"></div></div>
                    </div>
                    @endforeach
                </div>

                {{-- Col 3: X-Rays & Photos --}}
                <div>
                    <div class="dbm-sec-head">X-Rays &amp; Photos</div>
                    <div class="dbm-hdr"><span></span><span>Y</span><span>N</span><span>N/A</span></div>
                    @foreach([[12,'Routine X-rays'],[13,'Special X-rays'],[14,'Full mouth scan'],[15,'Photos taken']] as [$i,$l])
                    <div class="dbm-row" id="dbmr-{{$i}}">
                        <span>{{$l}}</span>
                        <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{$i}},'yes',this)"></div></div>
                        <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{$i}},'no',this)"></div></div>
                        <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{$i}},'na',this)"></div></div>
                    </div>
                    @endforeach
                </div>

                {{-- Col 4: Digital Base Records --}}
                <div>
                    <div class="dbm-sec-head">Digital Base Records</div>
                    <div class="dbm-hdr"><span></span><span>P</span><span>A</span><span>N/A</span></div>
                    @foreach([[16,'Missing teeth'],[17,'Silver fillings'],[18,'White fillings'],[19,'Crowns / Bridge'],[20,'Veneers'],[21,'Implants'],[22,'Crowding'],[23,'Chipped teeth'],[24,'Dentures'],[25,'Decayed teeth']] as [$i,$l])
                    <div class="dbm-row" id="dbmr-{{$i}}">
                        <span>{{$l}}</span>
                        <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{$i}},'present',this)"></div></div>
                        <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{$i}},'absent',this)"></div></div>
                        <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{$i}},'na',this)"></div></div>
                    </div>
                    @endforeach
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:3px 4px;margin-top:2px;background:#f9fafb;border-radius:4px;font-size:10px;">
                        <span style="color:#374151;">Tooth monitored</span>
                        <div style="display:flex;gap:3px;">
                            <button type="button" onclick="dbmYN(this,'tooth_monitored','Y')" style="padding:1px 6px;border:1.5px solid #e5e7eb;border-radius:3px;font-size:10px;font-weight:700;cursor:pointer;background:white;">Y</button>
                            <button type="button" onclick="dbmYN(this,'tooth_monitored','N')" style="padding:1px 6px;border:1.5px solid #e5e7eb;border-radius:3px;font-size:10px;font-weight:700;cursor:pointer;background:white;">N</button>
                        </div>
                    </div>
                </div>

                {{-- Col 5: Treatment Required --}}
                <div>
                    <div class="dbm-sec-head">Treatment Required</div>
                    <div class="dbm-hdr"><span></span><span>Y</span><span>N</span><span>N/A</span></div>
                    @foreach([[26,'Fillings'],[27,'Crowns / Bridges'],[28,'Root Canal'],[29,'Extractions'],[30,'Replacement'],[31,'Cosmetic'],[32,'Braces']] as [$i,$l])
                    <div class="dbm-row" id="dbmr-{{$i}}">
                        <span>{{$l}}</span>
                        <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{$i}},'yes',this)"></div></div>
                        <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{$i}},'no',this)"></div></div>
                        <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{$i}},'na',this)"></div></div>
                    </div>
                    @endforeach
                </div>

            </div>
        </div>
    </div>

    {{-- ─── Rx: Prescriptions & Instructions ─── FIX 5: Standardised header with chevron like all other cards --}}
    <div class="c-card" x-data="rxForm()">
        <div class="c-card-head" @click="rxOpen=!rxOpen">
            <span class="sec-label">
                <span class="sec-num" style="background:#dc2626;">Rx</span>
                Prescriptions &amp; Instructions
                <span x-show="drugs.length>0" style="font-size:10px;color:#6a0f70;font-weight:700;text-transform:none;letter-spacing:0;margin-left:4px;" x-text="'('+drugs.length+' drug(s))'"></span>
            </span>
            <div style="display:flex;align-items:center;gap:8px;">
                <svg class="sec-chevron" :class="rxOpen?'open':''" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </div>
        </div>
        <div x-show="rxOpen" x-collapse>
            {{-- Prescriptions sub-section --}}
            <button type="button" class="rx-collapse-btn" :class="showRxDrugs?'open':''" @click="showRxDrugs=!showRxDrugs">
                <div class="rx-section-head">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 2-5 5"/><path d="m2 19 5-5"/><rect x="5" y="2" width="5" height="20" rx="1" transform="rotate(-45 5 2)"/></svg>
                    Prescriptions
                </div>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     :style="showRxDrugs?'transform:rotate(180deg)':''" style="transition:transform .2s;color:#9ca3af;"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div x-show="showRxDrugs" x-collapse>
                <div style="padding:14px 18px;">
                    <div style="display:grid;grid-template-columns:220px 40px 80px 80px 80px 90px 100px 70px 32px;gap:4px;align-items:center;margin-bottom:6px;">
                        <span style="font-size:10px;font-weight:700;color:#dc2626;text-transform:uppercase;letter-spacing:.05em;">Drug</span>
                        <span></span>
                        <span style="font-size:10px;font-weight:700;color:#dc2626;text-align:center;text-transform:uppercase;letter-spacing:.05em;">Morning</span>
                        <span style="font-size:10px;font-weight:700;color:#dc2626;text-align:center;text-transform:uppercase;letter-spacing:.05em;">Noon</span>
                        <span style="font-size:10px;font-weight:700;color:#dc2626;text-align:center;text-transform:uppercase;letter-spacing:.05em;">Night</span>
                        <span style="font-size:10px;font-weight:700;color:#dc2626;text-transform:uppercase;letter-spacing:.05em;">Duration</span>
                        <span style="font-size:10px;font-weight:700;color:#dc2626;text-transform:uppercase;letter-spacing:.05em;">Unit</span>
                        <span style="font-size:10px;font-weight:700;color:#dc2626;text-align:center;text-transform:uppercase;letter-spacing:.05em;">Total</span>
                        <span></span>
                    </div>
                    <template x-for="(drug,i) in drugs" :key="i">
                        <div>
                            <div style="display:grid;grid-template-columns:220px 40px 80px 80px 80px 90px 100px 70px 32px;gap:4px;align-items:center;margin-bottom:4px;">
                                <select x-model="drug.name" class="rx-input"
                                        @change="if($event.target.value==='__custom__'){var v=prompt('Enter drug name:');if(v&&v.trim()){drug.name=v.trim();}else{drug.name='';}}">
                                    <option value="">Select Drug</option>
                                    @foreach(['Amoxicillin 500mg','Metronidazole 400mg','Ibuprofen 400mg','Ibuprofen 600mg','Paracetamol 500mg','Diclofenac 50mg','Clindamycin 300mg','Chlorhexidine Mouthwash','Tetracycline 250mg','Ciprofloxacin 500mg','Doxycycline 100mg','Cephalexin 500mg','Naproxen 250mg','Pantoprazole 40mg','Multivitamin'] as $drug)
                                    <option>{{$drug}}</option>
                                    @endforeach
                                    <option value="__custom__">+ Add custom…</option>
                                </select>
                                <div style="display:flex;flex-direction:column;align-items:center;gap:1px;cursor:pointer;" @click="drug.sos=!drug.sos">
                                    <div style="width:18px;height:18px;border-radius:50%;border:2px solid;display:flex;align-items:center;justify-content:center;transition:all .12s;"
                                         :style="drug.sos?'border-color:#6a0f70;background:#6a0f70;':'border-color:#d1d5db;'">
                                        <div x-show="drug.sos" style="width:7px;height:7px;border-radius:50%;background:white;"></div>
                                    </div>
                                    <span style="font-size:9px;font-weight:600;color:#9ca3af;">SOS</span>
                                </div>
                                <input type="number" x-model="drug.morning" class="rx-input" placeholder="Morning" min="0" step="0.5" style="text-align:center;" @input="calcTotal(i)">
                                <input type="number" x-model="drug.noon" class="rx-input" placeholder="Noon" min="0" step="0.5" style="text-align:center;" @input="calcTotal(i)">
                                <input type="number" x-model="drug.night" class="rx-input" placeholder="Night" min="0" step="0.5" style="text-align:center;" @input="calcTotal(i)">
                                <input type="number" x-model="drug.duration" class="rx-input" placeholder="Duration" min="1" @input="calcTotal(i)">
                                <select x-model="drug.dur_unit" class="rx-input" @change="calcTotal(i)">
                                    <option value="days">Days</option>
                                    <option value="weeks">Weeks</option>
                                    <option value="months">Months</option>
                                </select>
                                <input type="number" x-model="drug.total_qty" class="rx-input" placeholder="Total" style="text-align:center;">
                                <button type="button" @click="drugs.splice(i,1)"
                                        style="width:28px;height:28px;border:1px solid #fecaca;border-radius:5px;background:white;cursor:pointer;color:#dc2626;display:flex;align-items:center;justify-content:center;">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </div>
                            <div style="display:grid;grid-template-columns:160px 120px 1fr 200px;gap:4px;align-items:center;margin-bottom:8px;padding-left:4px;">
                                <select x-model="drug.food" class="rx-input">
                                    <option value="">Food timing</option>
                                    <option>Before Food</option><option>After Food</option><option>With Food</option>
                                    <option>Empty Stomach</option><option>Bedtime</option><option>As Directed</option>
                                </select>
                                <select x-model="drug.language" class="rx-input">
                                    <option>English</option><option>Hindi</option><option>Marathi</option>
                                </select>
                                <input type="text" x-model="drug.instruction" class="rx-input" placeholder="Instruction (e.g. Apply on affected area)">
                                <input type="text" x-model="drug.notes" class="rx-input" placeholder="Notes">
                            </div>
                            <div x-show="i < drugs.length-1" style="border-top:1px solid #f3f4f6;margin:4px 0 8px;"></div>
                        </div>
                    </template>
                    <button type="button" @click="addDrug()"
                            style="display:flex;align-items:center;gap:6px;padding:8px 14px;border:1.5px dashed #d1d5db;border-radius:6px;background:white;cursor:pointer;font-size:12px;font-weight:600;color:#6b7280;width:100%;margin-top:4px;"
                            onmouseover="this.style.borderColor='#6a0f70';this.style.color='#6a0f70'"
                            onmouseout="this.style.borderColor='#d1d5db';this.style.color='#6b7280'">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                        Add Drug
                    </button>
                </div>
            </div>

            {{-- Instructions sub-section --}}
            <button type="button" class="rx-collapse-btn" :class="showRxInstr?'open':''" @click="showRxInstr=!showRxInstr">
                <div class="rx-section-head">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Instructions to Patient
                </div>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     :style="showRxInstr?'transform:rotate(180deg)':''" style="transition:transform .2s;color:#9ca3af;"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div x-show="showRxInstr" x-collapse style="padding:14px 18px;">
                <div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:12px;">
                    @foreach(['Avoid hard/crunchy food for 24 hrs','Do not rinse vigorously','Keep the area clean','Use warm saline rinse','Apply ice pack for swelling','Avoid alcohol & smoking','Avoid spicy food','Complete the full course of antibiotics','Rest for 24 hours','Return if bleeding does not stop'] as $instr)
                    <button type="button" @click="toggleInstruction('{{$instr}}')"
                            :class="instructions.includes('{{$instr}}') ? 'rx-pill on' : 'rx-pill'">
                        <svg x-show="instructions.includes('{{$instr}}')" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        {{$instr}}
                    </button>
                    @endforeach
                </div>
                <div>
                    <label class="df-label">Additional Instructions</label>
                    <textarea x-model="customInstruction" class="df-input" rows="2"
                              placeholder="Type any additional post-treatment instructions…"></textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- ─── 9. Diagnosis ─── --}}
    <div class="c-card" x-data="{open:false}">
        <div class="c-card-head" @click="open=!open">
            <span class="sec-label"><span class="sec-num">9</span>Diagnosis</span>
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="sec-summary" x-show="!open && form.diagnosis.primary" x-cloak
                      x-text="form.diagnosis.primary.substring(0,50)+(form.diagnosis.primary.length>50?'…':'')"></span>
                <svg class="sec-chevron" :class="open?'open':''" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </div>
        </div>
        <div x-show="open" x-collapse style="padding:18px;display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div>
                <label class="df-label">Primary Diagnosis <span class="req">*</span></label>
                <textarea name="primary_diagnosis" x-model="form.diagnosis.primary" class="df-input" rows="3"
                          placeholder="e.g. Early Caries #14, 15, 46 with Dentin Hypersensitivity"></textarea>
            </div>
            <div>
                <label class="df-label">Secondary Diagnosis</label>
                <textarea name="secondary_diagnosis" x-model="form.diagnosis.secondary" class="df-input" rows="3"
                          placeholder="e.g. Mild Chronic Gingivitis"></textarea>
            </div>
            <div>
                <label class="df-label">Risk Assessment</label>
                <select name="risk_assessment" x-model="form.diagnosis.risk" class="df-input">
                    <option value="">Select</option>
                    @foreach(['Low Risk','Moderate Risk','High Risk','Very High Risk'] as $r)
                    <option>{{$r}}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="df-label">Notes</label>
                <textarea name="diagnosis_notes" x-model="form.diagnosis.notes" class="df-input" rows="3"
                          placeholder="Clinical reasoning, patient concerns…"></textarea>
            </div>
        </div>
    </div>

    {{-- ─── 10. Treatment Advised ─── --}}
    <div class="c-card" x-data="{open:false}">
        <div class="c-card-head" @click="open=!open">
            <span class="sec-label">
                <span class="sec-num">10</span>Treatment Advised
                <span style="font-size:9px;color:#9ca3af;font-weight:400;text-transform:none;letter-spacing:0;">Select → appears in treatment plan</span>
            </span>
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="sec-summary" x-show="!open && allTxSelected.length" x-cloak x-text="allTxSelected.length+' treatment(s)'"></span>
                <svg class="sec-chevron" :class="open?'open':''" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </div>
        </div>
        <div x-show="open" x-collapse style="padding:18px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px;">
            {{-- Emergency --}}
            <div>
                <div class="tx-col-head" style="color:#dc2626;border-color:#fecaca;">Emergency</div>
                <template x-for="(tx,i) in txSelected.emergency" :key="'em'+i">
                    <div class="tx-row" style="flex-direction:column;align-items:flex-start;gap:4px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;width:100%;">
                            <span x-text="tx" style="font-weight:600;"></span>
                            <button type="button" class="tx-rm" @click="removeTx('emergency',i)">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                            </button>
                        </div>
                        <input type="text" @click.stop="openTxTeethPicker('emergency',i)"
                               :value="(txTeeth.emergency[i]||[]).join(', ')||'Click to select teeth…'" readonly
                               style="width:100%;border:1px dashed #e5e7eb;border-radius:4px;padding:3px 6px;font-size:10px;color:#6b7280;cursor:pointer;background:white;"
                               onmouseover="this.style.borderColor='#6a0f70'" onmouseout="this.style.borderColor='#e5e7eb'">
                    </div>
                </template>
                <select class="tx-add-select" @change="addTx('emergency',$event)">
                    <option value="">+ Add treatment</option>
                    @foreach(['Pain Relief / Stabilization','RCT (if needed)','Extraction (if needed)','Temporary Filling','I&D Abscess','Pulpotomy','Splinting'] as $t)
                    <option>{{$t}}</option>
                    @endforeach
                    <option value="__custom__">+ Custom…</option>
                </select>
            </div>
            {{-- Protective --}}
            <div>
                <div class="tx-col-head" style="color:#2563eb;border-color:#bfdbfe;">Protective</div>
                <template x-for="(tx,i) in txSelected.protective" :key="'pr'+i">
                    <div class="tx-row" style="flex-direction:column;align-items:flex-start;gap:4px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;width:100%;">
                            <span x-text="tx" style="font-weight:600;"></span>
                            <button type="button" class="tx-rm" @click="removeTx('protective',i)">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                            </button>
                        </div>
                        <input type="text" @click.stop="openTxTeethPicker('protective',i)"
                               :value="(txTeeth.protective[i]||[]).join(', ')||'Click to select teeth…'" readonly
                               style="width:100%;border:1px dashed #e5e7eb;border-radius:4px;padding:3px 6px;font-size:10px;color:#6b7280;cursor:pointer;background:white;"
                               onmouseover="this.style.borderColor='#6a0f70'" onmouseout="this.style.borderColor='#e5e7eb'">
                    </div>
                </template>
                <select class="tx-add-select" @change="addTx('protective',$event)">
                    <option value="">+ Add treatment</option>
                    @foreach(['Scaling & Polishing','Fluoride Therapy','Restorations (Fillings)','RCT','Crown / Onlay','Gum Treatment','Composite Filling','GIC Filling'] as $t)
                    <option>{{$t}}</option>
                    @endforeach
                    <option value="__custom__">+ Custom…</option>
                </select>
            </div>
            {{-- Transformative --}}
            <div>
                <div class="tx-col-head" style="color:#6a0f70;border-color:#e9d5ff;">Transformative</div>
                <template x-for="(tx,i) in txSelected.transformative" :key="'tr'+i">
                    <div class="tx-row" style="flex-direction:column;align-items:flex-start;gap:4px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;width:100%;">
                            <span x-text="tx" style="font-weight:600;"></span>
                            <button type="button" class="tx-rm" @click="removeTx('transformative',i)">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                            </button>
                        </div>
                        <input type="text" @click.stop="openTxTeethPicker('transformative',i)"
                               :value="(txTeeth.transformative[i]||[]).join(', ')||'Click to select teeth…'" readonly
                               style="width:100%;border:1px dashed #e5e7eb;border-radius:4px;padding:3px 6px;font-size:10px;color:#6b7280;cursor:pointer;background:white;"
                               onmouseover="this.style.borderColor='#6a0f70'" onmouseout="this.style.borderColor='#e5e7eb'">
                    </div>
                </template>
                <select class="tx-add-select" @change="addTx('transformative',$event)">
                    <option value="">+ Add treatment</option>
                    @foreach(['Veneers','Crowns / Bridges','Implants','Aligners','Smile Design','Full Mouth Rehab','Teeth Whitening','Gum Contouring'] as $t)
                    <option>{{$t}}</option>
                    @endforeach
                    <option value="__custom__">+ Custom…</option>
                </select>
            </div>
        </div>
    </div>

    {{-- ─── 11A. Treatment Plan — Best Options ─── --}}
    <div class="c-card" x-data="{open:false}">
        <div class="c-card-head">
            <span class="sec-label" @click="open=!open" style="flex:1;cursor:pointer;display:flex;align-items:center;gap:6px;">
                <span class="sec-num" style="font-size:8px;width:22px;">11A</span>Treatment Plan — Best Options
            </span>
            <div style="display:flex;align-items:center;gap:8px;">
                <button type="button" @click.stop="$dispatch('add-tp-row',{type:'best'})"
                        style="font-size:11px;color:#6a0f70;border:1px solid rgba(106,15,112,.25);padding:3px 10px;border-radius:3px;background:white;cursor:pointer;white-space:nowrap;">
                    + Add Row
                </button>
                <svg class="sec-chevron" :class="open?'open':''" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="cursor:pointer;" @click="open=!open"><path d="m6 9 6 6 6-6"/></svg>
            </div>
        </div>
        <div x-show="open" x-collapse>
            <div style="overflow-x:auto;">
                <table class="tp-table">
                    <thead><tr>
                        <th style="width:160px;">Tooth / Area</th>
                        <th>Treatment</th>
                        <th style="width:100px;text-align:right;">Cost (₹)</th>
                        <th style="width:28px;"></th>
                    </tr></thead>
                    <tbody>
                        <template x-for="(row,i) in tpBest" :key="'best'+i">
                        <tr>
                            <td>
                                <div style="display:flex;gap:3px;">
                                    <input type="text" x-model="row.tooth" class="df-input" placeholder="#14, Full Mouth" style="padding:4px 7px;font-size:12px;flex:1;" readonly
                                           @click="$dispatch('open-tp-picker',{type:'best',idx:i})">
                                    <button type="button" @click="$dispatch('open-tp-picker',{type:'best',idx:i})"
                                            style="border:1px solid #e5e7eb;border-radius:4px;padding:0 7px;background:white;cursor:pointer;color:#6a0f70;flex-shrink:0;"
                                            title="Select teeth">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <div style="display:flex;gap:4px;">
                                    <input type="text" x-model="row.treatment" class="df-input" placeholder="e.g. Composite Filling" style="padding:4px 7px;font-size:12px;flex:1;">
                                    <select @change="row.treatment=$event.target.value;$event.target.value=''"
                                            style="border:1px solid #e5e7eb;border-radius:4px;font-size:11px;color:#9ca3af;padding:0 4px;background:white;cursor:pointer;max-width:70px;">
                                        <option value="">Quick</option>
                                        <template x-for="tx in allTxSelected" :key="tx"><option :value="tx" x-text="tx"></option></template>
                                    </select>
                                </div>
                            </td>
                            <td><input type="number" x-model="row.cost" class="df-input" placeholder="0" @input="$dispatch('recalc-tp',{type:'best'})" style="padding:4px 7px;font-size:12px;text-align:right;"></td>
                            <td><button type="button" @click="$dispatch('remove-tp-row',{type:'best',idx:i})" style="color:#d1d5db;background:none;border:none;cursor:pointer;padding:2px;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button></td>
                        </tr>
                        </template>
                    </tbody>
                    <tfoot><tr>
                        <td colspan="2" style="color:#6b7280;font-size:11px;">Estimated Total</td>
                        <td style="text-align:right;color:#6a0f70;">₹ <span x-text="tpBestTotal.toLocaleString('en-IN')"></span></td>
                        <td></td>
                    </tr></tfoot>
                </table>
            </div>
            <div style="padding:10px 16px;border-top:1px solid #f3f4f6;">
                <div style="font-size:10px;font-weight:700;color:#6a0f70;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">AOCP (if applicable)</div>
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px;">
                    <input type="checkbox" id="aocp_best" x-model="form.aocp_best" style="width:13px;height:13px;accent-color:#6a0f70;">
                    <label for="aocp_best" style="font-size:12px;color:#374151;cursor:pointer;">Include AOCP</label>
                </div>
                <div x-show="form.aocp_best" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;max-width:400px;">
                    @foreach(['Silver — ₹3,999/yr','Gold — ₹5,999/yr','Platinum — ₹8,999/yr'] as $plan)
                    <div class="aocp-card" :class="form.aocp_best_plan==='{{$plan}}'?'active':''" @click="form.aocp_best_plan='{{$plan}}'">
                        <div style="font-size:11px;font-weight:700;color:#374151;">{{ Str::before($plan,' —') }}</div>
                        <div style="font-size:10px;color:#9ca3af;">{{ Str::after($plan,'— ') }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ─── 11B. Treatment Plan — Acceptable Options ─── --}}
    <div class="c-card" x-data="{open:false}">
        <div class="c-card-head">
            <span class="sec-label" @click="open=!open" style="flex:1;cursor:pointer;display:flex;align-items:center;gap:6px;">
                <span class="sec-num" style="font-size:8px;width:22px;">11B</span>Treatment Plan — Acceptable Options
            </span>
            <div style="display:flex;align-items:center;gap:8px;">
                <button type="button" @click.stop="$dispatch('add-tp-row',{type:'acceptable'})"
                        style="font-size:11px;color:#6a0f70;border:1px solid rgba(106,15,112,.25);padding:3px 10px;border-radius:3px;background:white;cursor:pointer;white-space:nowrap;">
                    + Add Row
                </button>
                <svg class="sec-chevron" :class="open?'open':''" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="cursor:pointer;" @click="open=!open"><path d="m6 9 6 6 6-6"/></svg>
            </div>
        </div>
        <div x-show="open" x-collapse>
            <div style="overflow-x:auto;">
                <table class="tp-table">
                    <thead><tr>
                        <th style="width:160px;">Tooth / Area</th>
                        <th>Treatment</th>
                        <th style="width:100px;text-align:right;">Cost (₹)</th>
                        <th style="width:28px;"></th>
                    </tr></thead>
                    <tbody>
                        <template x-for="(row,i) in tpAcceptable" :key="'acceptable'+i">
                        <tr>
                            <td>
                                <div style="display:flex;gap:3px;">
                                    <input type="text" x-model="row.tooth" class="df-input" placeholder="#14, Full Mouth" style="padding:4px 7px;font-size:12px;flex:1;" readonly
                                           @click="$dispatch('open-tp-picker',{type:'acceptable',idx:i})">
                                    <button type="button" @click="$dispatch('open-tp-picker',{type:'acceptable',idx:i})"
                                            style="border:1px solid #e5e7eb;border-radius:4px;padding:0 7px;background:white;cursor:pointer;color:#6a0f70;flex-shrink:0;"
                                            title="Select teeth">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <div style="display:flex;gap:4px;">
                                    <input type="text" x-model="row.treatment" class="df-input" placeholder="e.g. Composite Filling" style="padding:4px 7px;font-size:12px;flex:1;">
                                    <select @change="row.treatment=$event.target.value;$event.target.value=''"
                                            style="border:1px solid #e5e7eb;border-radius:4px;font-size:11px;color:#9ca3af;padding:0 4px;background:white;cursor:pointer;max-width:70px;">
                                        <option value="">Quick</option>
                                        <template x-for="tx in allTxSelected" :key="tx"><option :value="tx" x-text="tx"></option></template>
                                    </select>
                                </div>
                            </td>
                            <td><input type="number" x-model="row.cost" class="df-input" placeholder="0" @input="$dispatch('recalc-tp',{type:'acceptable'})" style="padding:4px 7px;font-size:12px;text-align:right;"></td>
                            <td><button type="button" @click="$dispatch('remove-tp-row',{type:'acceptable',idx:i})" style="color:#d1d5db;background:none;border:none;cursor:pointer;padding:2px;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button></td>
                        </tr>
                        </template>
                    </tbody>
                    <tfoot><tr>
                        <td colspan="2" style="color:#6b7280;font-size:11px;">Estimated Total</td>
                        <td style="text-align:right;color:#6a0f70;">₹ <span x-text="tpAcceptableTotal.toLocaleString('en-IN')"></span></td>
                        <td></td>
                    </tr></tfoot>
                </table>
            </div>
            <div style="padding:10px 16px;border-top:1px solid #f3f4f6;">
                <div style="font-size:10px;font-weight:700;color:#6a0f70;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">AOCP (if applicable)</div>
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px;">
                    <input type="checkbox" id="aocp_acceptable" x-model="form.aocp_acceptable" style="width:13px;height:13px;accent-color:#6a0f70;">
                    <label for="aocp_acceptable" style="font-size:12px;color:#374151;cursor:pointer;">Include AOCP</label>
                </div>
                <div x-show="form.aocp_acceptable" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;max-width:400px;">
                    @foreach(['Silver — ₹3,999/yr','Gold — ₹5,999/yr','Platinum — ₹8,999/yr'] as $plan)
                    <div class="aocp-card" :class="form.aocp_acceptable_plan==='{{$plan}}'?'active':''" @click="form.aocp_acceptable_plan='{{$plan}}'">
                        <div style="font-size:11px;font-weight:700;color:#374151;">{{ Str::before($plan,' —') }}</div>
                        <div style="font-size:10px;color:#9ca3af;">{{ Str::after($plan,'— ') }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ─── 12. Finishing Section ─── --}}
    <div class="c-card" x-data="{open:false}">
        <div class="c-card-head" @click="open=!open">
            <span class="sec-label"><span class="sec-num">12</span>Finishing Section</span>
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="sec-summary" x-show="!open && form.finishing.next_visit_date" x-cloak x-text="'Next: '+form.finishing.next_visit_date"></span>
                <svg class="sec-chevron" :class="open?'open':''" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </div>
        </div>
        <div x-show="open" x-collapse style="padding:18px;display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:16px;">
            <div>
                <label class="df-label">Notes / Additional Comments</label>
                <textarea name="finishing_notes" x-model="form.finishing.notes" class="df-input" rows="4"
                          placeholder="Type your notes here…"></textarea>
            </div>
            <div>
                <label class="df-label">Next Visit Type</label>
                <select name="next_visit_type" x-model="form.finishing.next_visit_type" class="df-input" style="margin-bottom:8px;">
                    <option value="">Select</option>
                    @foreach(['Review & Treatment','Follow-up','Procedure','Consultation','Recall'] as $v)
                    <option>{{$v}}</option>
                    @endforeach
                </select>
                <label class="df-label">Date</label>
                <input type="date" name="next_visit_date" x-model="form.finishing.next_visit_date" class="df-input">
            </div>
            <div>
                <label class="df-label">Recall Interval</label>
                <div style="display:flex;flex-wrap:wrap;gap:5px;margin-top:4px;">
                    @foreach(['1 Day','3 Days','5 Days','1 Week','2 Weeks','1 Month','3 Months','6 Months','Custom'] as $ri)
                    <button type="button" @click="form.finishing.recall_interval='{{$ri}}'"
                            :class="form.finishing.recall_interval==='{{$ri}}'?'active':''"
                            class="recall-pill">{{$ri}}</button>
                    @endforeach
                </div>
                <input x-show="form.finishing.recall_interval==='Custom'"
                       type="text" x-model="form.finishing.recall_custom"
                       class="df-input" style="margin-top:6px;" placeholder="e.g. 10 days">
            </div>
            <div>
                <label class="df-label">Responsible Doctor</label>
                <select name="responsible_user_id" x-model="form.finishing.responsible_id" class="df-input" style="margin-bottom:8px;">
                    @foreach($doctors ?? [] as $doc)
                    <option value="{{$doc->id}}">{{$doc->name}}</option>
                    @endforeach
                </select>
                <label class="df-label">Attachments</label>
                <div class="upload-zone" @click="document.getElementById('att-input').click()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto 3px;display:block;"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                    <div style="font-size:11px;color:#9ca3af;">+ Add File</div>
                    <input type="file" id="att-input" name="attachments[]" multiple style="display:none;" @change="handleAttachments($event)">
                </div>
                <template x-for="(f,i) in attachments" :key="i">
                    <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:#374151;margin-top:4px;">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <span x-text="f.name" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                        <button type="button" @click="attachments.splice(i,1)" style="background:none;border:none;color:#d1d5db;cursor:pointer;">×</button>
                    </div>
                </template>
            </div>
        </div>
        {{-- Footer --}}
        <div style="padding:14px 18px;border-top:1px solid #f3f4f6;background:#fafafa;display:flex;align-items:center;gap:10px;">
            <a href="{{ route('patients.show', $patient) }}"
               style="padding:7px 16px;font-size:13px;border:1px solid #e5e7eb;color:#6b7280;border-radius:3px;text-decoration:none;"
               onmouseover="this.style.borderColor='#fca5a5';this.style.color='#dc2626';"
               onmouseout="this.style.borderColor='#e5e7eb';this.style.color='#6b7280';">
                Cancel Consultation
            </a>
            <div style="flex:1;"></div>
            <button type="button" class="btn-draft" @click="saveDraft()">Save Draft</button>
            <button type="submit" class="btn-save">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                Save &amp; Continue
            </button>
        </div>
    </div>

</div>{{-- /consult-body --}}

{{-- ══ COMPLAINT TOOTH PICKER OVERLAY ══ --}}
<div x-show="showToothPicker" x-cloak @click.self="showToothPicker=false"
     style="position:fixed;inset:0;z-index:200;background:rgba(14,1,24,.45);display:flex;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:14px;width:580px;max-width:96vw;box-shadow:0 24px 64px rgba(0,0,0,.22);overflow:hidden;">
        <div style="padding:16px 20px 12px;background:linear-gradient(135deg,#6a0f70,#380740);display:flex;align-items:center;justify-content:space-between;">
            <div>
                <div style="font-size:14px;font-weight:700;color:white;">Select Tooth / Area</div>
                <div style="font-size:11px;color:rgba(255,255,255,.6);margin-top:2px;">Click individual teeth or use quick-select</div>
            </div>
            <button type="button" @click="showToothPicker=false" style="background:rgba(255,255,255,.15);border:none;color:white;width:28px;height:28px;border-radius:50%;cursor:pointer;font-size:16px;line-height:1;display:flex;align-items:center;justify-content:center;">×</button>
        </div>
        <div style="padding:16px 20px;">
            <div style="display:flex;gap:5px;flex-wrap:wrap;margin-bottom:14px;">
                @foreach(['Upper','Lower','Full Mouth','Right','Left','Anterior','Posterior'] as $qs)
                <button type="button" @click="quickSelectComplaint('{{ strtolower($qs) }}')"
                        style="font-size:11px;font-weight:600;padding:5px 12px;border:1.5px solid #e5e7eb;border-radius:99px;background:white;cursor:pointer;color:#6b7280;transition:all .12s;"
                        onmouseover="this.style.background='#6a0f70';this.style.color='white';this.style.borderColor='#6a0f70';"
                        onmouseout="this.style.background='white';this.style.color='#6b7280';this.style.borderColor='#e5e7eb';">{{$qs}}</button>
                @endforeach
                <button type="button" @click="clearToothPicker()"
                        style="font-size:11px;font-weight:600;padding:5px 12px;border:1.5px solid #fecaca;border-radius:99px;background:white;cursor:pointer;color:#dc2626;margin-left:auto;">Clear</button>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                <span style="font-size:10px;font-weight:700;color:#dc2626;">◀ RIGHT</span>
                <span style="font-size:10px;font-weight:700;color:#2563eb;">LEFT ▶</span>
            </div>
            <div style="display:grid;grid-template-columns:repeat(16,1fr);gap:3px;margin-bottom:2px;">
                @foreach([18,17,16,15,14,13,12,11,21,22,23,24,25,26,27,28] as $t)
                <div style="border:1.5px solid #e5e7eb;border-radius:5px;padding:7px 2px;text-align:center;cursor:pointer;font-size:11px;font-weight:700;color:#9ca3af;transition:all .12s;background:white;user-select:none;"
                     :style="selectedTeeth.includes({{$t}}) ? 'background:#6a0f70;border-color:#380740;color:white;box-shadow:0 2px 6px rgba(106,15,112,.3);' : ''"
                     @click="toggleComplaintTooth({{$t}})">{{$t}}</div>
                @endforeach
            </div>
            <div style="display:flex;align-items:center;gap:6px;margin:6px 0;">
                <div style="flex:1;height:1px;background:linear-gradient(to right,transparent,#d1d5db);"></div>
                <span style="font-size:9px;color:#9ca3af;font-weight:600;letter-spacing:.06em;">UPPER / LOWER</span>
                <div style="flex:1;height:1px;background:linear-gradient(to left,transparent,#d1d5db);"></div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(16,1fr);gap:3px;margin-bottom:14px;">
                @foreach([48,47,46,45,44,43,42,41,31,32,33,34,35,36,37,38] as $t)
                <div style="border:1.5px solid #e5e7eb;border-radius:5px;padding:7px 2px;text-align:center;cursor:pointer;font-size:11px;font-weight:700;color:#9ca3af;transition:all .12s;background:white;user-select:none;"
                     :style="selectedTeeth.includes({{$t}}) ? 'background:#6a0f70;border-color:#380740;color:white;box-shadow:0 2px 6px rgba(106,15,112,.3);' : ''"
                     @click="toggleComplaintTooth({{$t}})">{{$t}}</div>
                @endforeach
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#f9fafb;border-radius:8px;">
                <div>
                    <div style="font-size:10px;color:#9ca3af;font-weight:600;margin-bottom:2px;">SELECTED</div>
                    <div style="font-size:13px;font-weight:700;color:#6a0f70;min-height:20px;"
                         x-text="selectedTeeth.length ? '#'+selectedTeeth.sort((a,b)=>a-b).join(', #') : '—'"></div>
                </div>
                <button type="button" @click="showToothPicker=false"
                        style="padding:9px 24px;background:#6a0f70;color:white;border:none;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer;"
                        onmouseover="this.style.background='#380740'" onmouseout="this.style.background='#6a0f70'">Done</button>
            </div>
        </div>
    </div>
</div>

{{-- ══ TX TEETH PICKER OVERLAY (Treatment Advised section) ══ --}}
<div x-show="showTxTeethPicker" x-cloak @click.self="showTxTeethPicker=false"
     style="position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:12px;padding:20px;width:500px;max-width:96vw;box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
            <span style="font-size:13px;font-weight:700;color:#111827;">Select Teeth for Treatment</span>
            <button type="button" @click="showTxTeethPicker=false" style="background:none;border:none;color:#9ca3af;cursor:pointer;font-size:20px;line-height:1;">×</button>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:3px;font-size:9px;font-weight:700;">
            <span style="color:#dc2626;">RIGHT</span><span style="color:#2563eb;">LEFT</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(16,1fr);gap:2px;margin-bottom:2px;">
            @foreach([18,17,16,15,14,13,12,11,21,22,23,24,25,26,27,28] as $t)
            <div style="border:1.5px solid #e5e7eb;border-radius:4px;padding:5px 1px;text-align:center;cursor:pointer;font-size:10px;font-weight:600;color:#9ca3af;transition:all .1s;user-select:none;"
                 :style="selectedTeeth.includes({{$t}}) ? 'background:#6a0f70;border-color:#380740;color:white;' : 'background:white;'"
                 @click="toggleComplaintTooth({{$t}})">{{$t}}</div>
            @endforeach
        </div>
        <div style="border-top:1.5px dashed #e5e7eb;margin:4px 0;"></div>
        <div style="display:grid;grid-template-columns:repeat(16,1fr);gap:2px;margin-bottom:14px;">
            @foreach([48,47,46,45,44,43,42,41,31,32,33,34,35,36,37,38] as $t)
            <div style="border:1.5px solid #e5e7eb;border-radius:4px;padding:5px 1px;text-align:center;cursor:pointer;font-size:10px;font-weight:600;color:#9ca3af;transition:all .1s;user-select:none;"
                 :style="selectedTeeth.includes({{$t}}) ? 'background:#6a0f70;border-color:#380740;color:white;' : 'background:white;'"
                 @click="toggleComplaintTooth({{$t}})">{{$t}}</div>
            @endforeach
        </div>
        <div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:14px;">
            @foreach(['Upper','Lower','Full Mouth','Right','Left','Anterior','Posterior'] as $qs)
            <button type="button" @click="quickSelectComplaint('{{ strtolower($qs) }}')"
                    style="font-size:10px;padding:3px 9px;border:1px solid #e5e7eb;border-radius:4px;background:white;cursor:pointer;color:#6b7280;">{{$qs}}</button>
            @endforeach
            <button type="button" @click="selectedTeeth=[]"
                    style="font-size:10px;padding:3px 9px;border:1px solid #fecaca;border-radius:4px;background:white;cursor:pointer;color:#dc2626;">Clear</button>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:11px;color:#6a0f70;font-weight:600;"
                  x-text="selectedTeeth.length ? selectedTeeth.sort((a,b)=>a-b).join(', ') : 'No teeth selected'"></span>
            <button type="button" @click="confirmTxTeethPicker()"
                    style="padding:7px 20px;background:#6a0f70;color:white;border:none;border-radius:5px;font-size:12px;font-weight:600;cursor:pointer;">Confirm</button>
        </div>
    </div>
</div>

{{-- ══ TP ROW TOOTH PICKER OVERLAY (Treatment Plan 11A/11B rows) ══ --}}
<div x-show="showTpRowPicker" x-cloak @click.self="showTpRowPicker=false"
     style="position:fixed;inset:0;z-index:200;background:rgba(14,1,24,.45);display:flex;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:14px;width:580px;max-width:96vw;box-shadow:0 24px 64px rgba(0,0,0,.22);overflow:hidden;">
        <div style="padding:16px 20px 12px;background:linear-gradient(135deg,#6a0f70,#380740);display:flex;align-items:center;justify-content:space-between;">
            <div>
                <div style="font-size:14px;font-weight:700;color:white;">Select Tooth / Area</div>
                <div style="font-size:11px;color:rgba(255,255,255,.6);margin-top:2px;">For treatment plan row</div>
            </div>
            <button type="button" @click="showTpRowPicker=false" style="background:rgba(255,255,255,.15);border:none;color:white;width:28px;height:28px;border-radius:50%;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;">×</button>
        </div>
        <div style="padding:16px 20px;">
            <div style="display:flex;gap:5px;flex-wrap:wrap;margin-bottom:14px;">
                @foreach(['Upper','Lower','Full Mouth','Right','Left','Anterior','Posterior'] as $qs)
                <button type="button" @click="tpPickerQuickSelect('{{ strtolower($qs) }}')"
                        style="font-size:11px;font-weight:600;padding:5px 12px;border:1.5px solid #e5e7eb;border-radius:99px;background:white;cursor:pointer;color:#6b7280;transition:all .12s;"
                        onmouseover="this.style.background='#6a0f70';this.style.color='white';this.style.borderColor='#6a0f70';"
                        onmouseout="this.style.background='white';this.style.color='#6b7280';this.style.borderColor='#e5e7eb';">{{$qs}}</button>
                @endforeach
                <button type="button" @click="tpPickerTeeth=[]"
                        style="font-size:11px;font-weight:600;padding:5px 12px;border:1.5px solid #fecaca;border-radius:99px;background:white;cursor:pointer;color:#dc2626;margin-left:auto;">Clear</button>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                <span style="font-size:10px;font-weight:700;color:#dc2626;">◀ RIGHT</span>
                <span style="font-size:10px;font-weight:700;color:#2563eb;">LEFT ▶</span>
            </div>
            <div style="display:grid;grid-template-columns:repeat(16,1fr);gap:3px;margin-bottom:2px;">
                @foreach([18,17,16,15,14,13,12,11,21,22,23,24,25,26,27,28] as $t)
                <div style="border:1.5px solid #e5e7eb;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:10px;font-weight:700;color:#9ca3af;transition:all .12s;background:white;user-select:none;margin:0 auto;"
                     :style="tpPickerTeeth.includes({{$t}}) ? 'background:#6a0f70;border-color:#380740;color:white;box-shadow:0 2px 6px rgba(106,15,112,.3);' : ''"
                     @click="tpPickerToggle({{$t}})">{{$t}}</div>
                @endforeach
            </div>
            <div style="display:flex;align-items:center;gap:6px;margin:8px 0;">
                <div style="flex:1;height:1px;background:linear-gradient(to right,transparent,#d1d5db);"></div>
                <span style="font-size:9px;color:#9ca3af;font-weight:600;letter-spacing:.06em;">UPPER / LOWER</span>
                <div style="flex:1;height:1px;background:linear-gradient(to left,transparent,#d1d5db);"></div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(16,1fr);gap:3px;margin-bottom:14px;">
                @foreach([48,47,46,45,44,43,42,41,31,32,33,34,35,36,37,38] as $t)
                <div style="border:1.5px solid #e5e7eb;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:10px;font-weight:700;color:#9ca3af;transition:all .12s;background:white;user-select:none;margin:0 auto;"
                     :style="tpPickerTeeth.includes({{$t}}) ? 'background:#6a0f70;border-color:#380740;color:white;box-shadow:0 2px 6px rgba(106,15,112,.3);' : ''"
                     @click="tpPickerToggle({{$t}})">{{$t}}</div>
                @endforeach
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#f9fafb;border-radius:8px;">
                <div>
                    <div style="font-size:10px;color:#9ca3af;font-weight:600;margin-bottom:2px;">SELECTED</div>
                    <div style="font-size:13px;font-weight:700;color:#6a0f70;min-height:20px;"
                         x-text="tpPickerTeeth.length ? '#'+tpPickerTeeth.slice().sort((a,b)=>a-b).join(', #') : '—'"></div>
                </div>
                <button type="button" @click="confirmTpRowPicker()"
                        style="padding:9px 24px;background:#6a0f70;color:white;border:none;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer;"
                        onmouseover="this.style.background='#380740'" onmouseout="this.style.background='#6a0f70'">Done</button>
            </div>
        </div>
    </div>
</div>

{{-- ══ TOOTH CHART MODAL — ARCH SVG ══ --}}
<div id="tooth-chart-modal"
     style="display:none;position:fixed;inset:0;z-index:300;background:rgba(0,0,0,.55);align-items:center;justify-content:center;">
    <div style="background:white;border-radius:14px;width:980px;max-width:98vw;max-height:96vh;overflow-y:auto;box-shadow:0 24px 64px rgba(0,0,0,.28);display:flex;flex-direction:column;">

        {{-- Header --}}
        <div style="padding:14px 20px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:white;z-index:2;flex-shrink:0;">
            <span style="font-size:14px;font-weight:700;color:#111827;">Tooth Chart — Existing Condition</span>
            <button onclick="document.getElementById('tooth-chart-modal').style.display='none'"
                    style="background:none;border:none;color:#9ca3af;cursor:pointer;font-size:22px;line-height:1;">×</button>
        </div>

        <div style="display:flex;flex:1;overflow:hidden;">

            {{-- ── Left: Tool palette ── --}}
            <div style="width:170px;flex-shrink:0;border-right:1px solid #f3f4f6;padding:14px 12px;display:flex;flex-direction:column;gap:6px;background:#fafafa;">
                <div style="font-size:9px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;">Condition</div>
                @php $tools=[['C','#ef4444','Caries'],['F','#16a34a','Filling'],['RCT','#6a0f70','Root Canal'],['CR','#d97706','Crown'],['B','#2563eb','Bridge'],['I','#0891b2','Implant'],['V','#db2777','Veneer'],['M','#9ca3af','Missing'],['X','#dc2626','Extraction'],['FR','#f97316','Fracture'],['A','#374151','Amalgam'],['GI','#059669','GIC']]; @endphp
                @foreach($tools as [$sym,$col,$lbl])
                <button type="button" id="tool-btn-{{$sym}}" onclick="setChartTool('{{$sym}}','{{$col}}','{{$lbl}}')"
                        style="display:flex;align-items:center;gap:8px;padding:6px 10px;border:1.5px solid #e5e7eb;border-radius:7px;background:white;cursor:pointer;font-size:11px;font-weight:600;color:#374151;transition:all .12s;text-align:left;">
                    <span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:{{$col}}18;color:{{$col}};font-size:10px;font-weight:800;flex-shrink:0;">{{$sym}}</span>
                    <span style="color:#6b7280;font-size:10px;">{{$lbl}}</span>
                </button>
                @endforeach
                <div style="margin-top:8px;padding:8px;background:white;border:1px solid #e5e7eb;border-radius:7px;text-align:center;">
                    <div style="font-size:9px;color:#9ca3af;margin-bottom:3px;">ACTIVE</div>
                    <div id="chart-active-sym" style="font-size:18px;font-weight:800;color:#ef4444;line-height:1;">C</div>
                    <div id="chart-active-lbl" style="font-size:9px;color:#6b7280;margin-top:1px;">Caries</div>
                </div>
            </div>

            {{-- ── Right: Arch chart ── --}}
            <div style="flex:1;padding:16px;display:flex;flex-direction:column;align-items:center;overflow-y:auto;">

                {{-- Labels row --}}
                <div style="display:flex;align-items:center;justify-content:space-between;width:100%;max-width:680px;margin-bottom:6px;">
                    <span style="font-size:10px;font-weight:700;color:#dc2626;">◀ RIGHT</span>
                    <span style="font-size:10px;font-weight:600;color:#9ca3af;letter-spacing:.06em;">UPPER JAW</span>
                    <span style="font-size:10px;font-weight:700;color:#2563eb;">LEFT ▶</span>
                </div>

                {{-- SVG Arch Chart --}}
                <svg id="arch-chart-svg" viewBox="0 0 680 560" style="width:100%;max-width:680px;" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <!-- tooth shape: circle for selector + root line below -->
                        <style>
                            .arch-tooth { cursor:pointer; }
                            .arch-tooth:hover .tooth-circle { stroke:#6a0f70; stroke-width:2.5; filter:drop-shadow(0 0 3px rgba(106,15,112,.3)); }
                            .tooth-circle { transition:all .15s; }
                            .tooth-num { font-size:9px; font-weight:700; fill:#9ca3af; pointer-events:none; font-family:'DM Sans',sans-serif; }
                        </style>
                    </defs>

                    {{-- ── UPPER ARCH ── --}}
                    {{-- Arch guide line (decorative) --}}
                    <path d="M 40 260 Q 340 20 640 260" fill="none" stroke="#f3f4f6" stroke-width="1" stroke-dasharray="4,4"/>

                    {{-- Upper teeth: 18..11 right side, 21..28 left side --}}
                    {{-- Positions computed along upper arch ellipse centre y=250, rx=280, ry=210 --}}
                    {{-- Angles: tooth 18=180°, 17=168°, 16=156°, 15=144°, 14=130°, 13=112°, 12=96°, 11=82° --}}
                    {{--          tooth 21=98°, 22=84°…  mirrored --}}

                    @php
                    $cx = 340; $cy = 270;
                    $rx = 270; $ry = 195;
                    // Upper arch: right side 18-11, left side 21-28
                    // Angles in degrees from 3-o'clock (0°=right), going counter-clockwise for right side
                    $upperRight = [18=>168, 17=>156, 16=>142, 15=>127, 14=>112, 13=>96, 12=>83, 11=>70];
                    $upperLeft  = [21=>110, 22=>97, 23=>84, 24=>69, 25=>54, 26=>38, 27=>22, 28=>8]; // mirrored
                    // Actually use standard: angles from top, fanning out
                    // Let's use absolute (x,y) positions computed for a horseshoe arch
                    // Upper arch teeth positions (cx,cy offset from SVG origin):
                    $upperPositions = [
                        18 => [68,  245], 17 => [95,  195], 16 => [127, 153], 15 => [163, 120],
                        14 => [204, 97],  13 => [248, 83],  12 => [293, 76],  11 => [338, 73],
                        21 => [342, 73],  22 => [387, 76],  23 => [432, 83],  24 => [476, 97],
                        25 => [517,120],  26 => [553, 153], 27 => [585, 195], 28 => [612, 245],
                    ];
                    $lowerPositions = [
                        48 => [88,  320], 47 => [113, 370], 46 => [143, 408], 45 => [178, 435],
                        44 => [218, 453], 43 => [260, 463], 42 => [302, 468], 41 => [340, 469],
                        31 => [340, 469], 32 => [378, 468], 33 => [420, 463], 34 => [462, 453],
                        35 => [502, 435], 36 => [537, 408], 37 => [567, 370], 38 => [592, 320],
                    ];
                    $r = 22; // circle radius
                    @endphp

                    {{-- Upper arch arch-line --}}
                    <path d="M 68 245 C 68 180, 200 60, 340 56 C 480 60, 612 180, 612 245"
                          fill="none" stroke="#e9d5ff" stroke-width="2"/>

                    {{-- Upper teeth --}}
                    @foreach($upperPositions as $num => [$tx,$ty])
                    <g class="arch-tooth" id="ct-{{$num}}" onclick="archChartClick({{$num}},this)">
                        <circle id="ct-{{$num}}-O" cx="{{$tx}}" cy="{{$ty}}" r="{{$r}}" fill="white" stroke="#d1d5db" stroke-width="1.5" class="tooth-circle"/>
                        {{-- Inner division cross (occlusal surface) --}}
                        <line x1="{{$tx-$r*0.5}}" y1="{{$ty}}" x2="{{$tx+$r*0.5}}" y2="{{$ty}}" stroke="#e5e7eb" stroke-width="1" pointer-events="none"/>
                        <line x1="{{$tx}}" y1="{{$ty-$r*0.5}}" x2="{{$tx}}" y2="{{$ty+$r*0.5}}" stroke="#e5e7eb" stroke-width="1" pointer-events="none"/>
                        {{-- Root line going up --}}
                        <line id="ct-{{$num}}-R" x1="{{$tx}}" y1="{{$ty-$r}}" x2="{{$tx}}" y2="{{$ty-$r-10}}" stroke="#e5e7eb" stroke-width="2" stroke-linecap="round"/>
                        <text x="{{$tx}}" y="{{$ty+1}}" text-anchor="middle" dominant-baseline="central" class="tooth-num">{{$num}}</text>
                    </g>
                    @endforeach

                    {{-- Arch separator --}}
                    <line x1="40" y1="280" x2="640" y2="280" stroke="#e5e7eb" stroke-width="1" stroke-dasharray="6,4"/>
                    <text x="340" y="275" text-anchor="middle" style="font-size:8px;fill:#d1d5db;font-family:'DM Sans',sans-serif;font-weight:600;letter-spacing:.04em;">UPPER · LOWER</text>

                    {{-- Lower arch arch-line --}}
                    <path d="M 88 320 C 88 390, 200 490, 340 493 C 480 490, 592 390, 592 320"
                          fill="none" stroke="#e9d5ff" stroke-width="2"/>

                    {{-- Lower teeth --}}
                    @foreach($lowerPositions as $num => [$tx,$ty])
                    <g class="arch-tooth" id="ct-{{$num}}" onclick="archChartClick({{$num}},this)">
                        <circle id="ct-{{$num}}-O" cx="{{$tx}}" cy="{{$ty}}" r="{{$r}}" fill="white" stroke="#d1d5db" stroke-width="1.5" class="tooth-circle"/>
                        <line x1="{{$tx-$r*0.5}}" y1="{{$ty}}" x2="{{$tx+$r*0.5}}" y2="{{$ty}}" stroke="#e5e7eb" stroke-width="1" pointer-events="none"/>
                        <line x1="{{$tx}}" y1="{{$ty-$r*0.5}}" x2="{{$tx}}" y2="{{$ty+$r*0.5}}" stroke="#e5e7eb" stroke-width="1" pointer-events="none"/>
                        {{-- Root line going down --}}
                        <line id="ct-{{$num}}-R" x1="{{$tx}}" y1="{{$ty+$r}}" x2="{{$tx}}" y2="{{$ty+$r+10}}" stroke="#e5e7eb" stroke-width="2" stroke-linecap="round"/>
                        <text x="{{$tx}}" y="{{$ty+1}}" text-anchor="middle" dominant-baseline="central" class="tooth-num">{{$num}}</text>
                    </g>
                    @endforeach

                    {{-- Jaw labels --}}
                    <text x="340" y="35" text-anchor="middle" style="font-size:9px;fill:#9ca3af;font-family:'DM Sans',sans-serif;font-weight:600;letter-spacing:.06em;">UPPER JAW</text>
                    <text x="340" y="548" text-anchor="middle" style="font-size:9px;fill:#9ca3af;font-family:'DM Sans',sans-serif;font-weight:600;letter-spacing:.06em;">LOWER JAW</text>
                </svg>

                {{-- Summary --}}
                <div style="margin-top:12px;padding:10px 14px;background:#f9fafb;border-radius:8px;min-height:36px;width:100%;max-width:680px;">
                    <div style="font-size:10px;font-weight:600;color:#6a0f70;margin-bottom:4px;">Charted Findings</div>
                    <div id="chart-summary" style="font-size:11px;color:#374151;line-height:2;">No findings charted yet.</div>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div style="padding:12px 20px;border-top:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center;background:white;flex-shrink:0;">
            <button type="button" onclick="clearArchChart()" style="padding:7px 16px;border:1px solid #e5e7eb;border-radius:5px;font-size:12px;font-weight:600;color:#6b7280;background:white;cursor:pointer;">Clear All</button>
            <button type="button" onclick="document.getElementById('tooth-chart-modal').style.display='none'"
                    style="padding:7px 24px;background:#6a0f70;color:white;border:none;border-radius:5px;font-size:12px;font-weight:600;cursor:pointer;">Done</button>
        </div>
    </div>
</div>

{{-- ══ ARCH TOOTH CHART JS ══ --}}
<script>
var chartTool = { sym:'C', color:'#ef4444', lbl:'Caries' };
var chartData = {};

function setChartTool(sym,color,lbl) {
    chartTool = {sym:sym,color:color,lbl:lbl};
    document.getElementById('chart-active-sym').textContent = sym;
    document.getElementById('chart-active-sym').style.color = color;
    document.getElementById('chart-active-lbl').textContent = lbl;
    document.querySelectorAll('[id^="tool-btn-"]').forEach(function(b){
        b.style.borderColor='#e5e7eb';b.style.background='white';
    });
    var btn = document.getElementById('tool-btn-'+sym);
    if(btn){btn.style.borderColor=color;btn.style.background=color+'18';}
}

function archChartClick(tooth, gEl) {
    if(!chartData[tooth]) chartData[tooth]={};
    var circEl = document.getElementById('ct-'+tooth+'-O');
    var rootEl = document.getElementById('ct-'+tooth+'-R');
    if(!circEl) return;

    // Toggle: if same tool already applied, remove it
    if(chartData[tooth].sym === chartTool.sym) {
        delete chartData[tooth].sym;
        delete chartData[tooth].color;
        circEl.setAttribute('fill','white');
        circEl.setAttribute('stroke','#d1d5db');
        circEl.setAttribute('stroke-width','1.5');
        if(rootEl){rootEl.setAttribute('stroke','#e5e7eb');}
        // reset text
        var textEl = gEl.querySelector('text');
        if(textEl) textEl.setAttribute('fill','#9ca3af');
    } else {
        chartData[tooth].sym   = chartTool.sym;
        chartData[tooth].color = chartTool.color;
        circEl.setAttribute('fill', chartTool.color+'30');
        circEl.setAttribute('stroke', chartTool.color);
        circEl.setAttribute('stroke-width','2.5');
        if(rootEl){rootEl.setAttribute('stroke',chartTool.color+'80');}
        // update number text colour
        var textEl = gEl.querySelector('text');
        if(textEl) textEl.setAttribute('fill', chartTool.color);
    }
    updateArchChartSummary();
}

function updateArchChartSummary() {
    var groups = {};
    Object.entries(chartData).forEach(function(te){
        var tooth=te[0], data=te[1];
        if(!data.sym) return;
        if(!groups[data.sym]) groups[data.sym]={color:data.color,teeth:[]};
        groups[data.sym].teeth.push(parseInt(tooth));
    });
    var html='';
    Object.entries(groups).forEach(function(g){
        var sym=g[0],info=g[1];
        info.teeth.sort(function(a,b){return a-b;});
        html+='<span style="display:inline-flex;align-items:center;gap:5px;margin-right:16px;margin-bottom:3px;padding:3px 8px;border-radius:99px;background:'+info.color+'15;border:1px solid '+info.color+'40;">'
            +'<span style="font-weight:800;color:'+info.color+';font-size:11px;">'+sym+'</span>'
            +'<span style="color:#374151;font-size:10px;">#'+info.teeth.join(', #')+'</span>'
            +'</span>';
    });
    var summaryEl=document.getElementById('chart-summary');
    if(summaryEl) summaryEl.innerHTML=html||'No findings charted yet.';
    var clinWrap=document.getElementById('clinical-chart-summary-wrap');
    var clinEl=document.getElementById('clinical-chart-summary');
    if(clinWrap&&clinEl){clinWrap.style.display=html?'block':'none';clinEl.innerHTML=html;}
}

function clearArchChart() {
    chartData={};
    document.querySelectorAll('.arch-tooth').forEach(function(gEl){
        var tooth = gEl.id.replace('ct-','');
        var circEl = document.getElementById('ct-'+tooth+'-O');
        var rootEl = document.getElementById('ct-'+tooth+'-R');
        if(circEl){circEl.setAttribute('fill','white');circEl.setAttribute('stroke','#d1d5db');circEl.setAttribute('stroke-width','1.5');}
        if(rootEl){rootEl.setAttribute('stroke','#e5e7eb');}
        var textEl = gEl.querySelector('text');
        if(textEl) textEl.setAttribute('fill','#9ca3af');
    });
    updateArchChartSummary();
}

setChartTool('C','#ef4444','Caries');
</script>

</form>
</div>

@push('scripts')
<script>
/* ── Expand / Collapse All — Alpine 3 compatible ── */
function toggleAllSections(expand) {
    document.querySelectorAll('[x-data]').forEach(function(el) {
        try {
            var data = Alpine.$data(el);
            if ('open'   in data) data.open   = expand;
            if ('open2'  in data) data.open2  = expand;
            if ('rxOpen' in data) data.rxOpen = expand;
        } catch(e) {}
    });
}

/* ── DBM checklist ── */
var dbmState = Array(33).fill(null);
function dbmSet(idx, val, dotEl) {
    dbmState[idx] = val;
    var row = document.getElementById('dbmr-'+idx);
    if(row) row.querySelectorAll('.dbm-dot').forEach(function(d){d.classList.remove('active');});
    dotEl.classList.add('active');
    dbmUpdateScore();
}
function dbmYN(btn, key, val) {
    var wrap = btn.parentElement;
    wrap.querySelectorAll('button').forEach(function(b){b.style.background='white';b.style.color='#374151';b.style.borderColor='#e5e7eb';});
    btn.style.background='#6a0f70';btn.style.color='white';btn.style.borderColor='#6a0f70';
}
function dbmUpdateScore() {
    var answered = dbmState.filter(function(v){return v!==null;}).length;
    var pct = Math.round(answered/33*100);
    var fill  = document.getElementById('dbm-prog-fill');
    var badge = document.getElementById('dbm-score-badge');
    if(fill)  fill.style.width = pct+'%';
    if(badge) badge.textContent = answered+'/33';
}

/* ── Rx form component ── */
function rxForm() {
    return {
        rxOpen: false,
        showRxDrugs: true,
        showRxInstr: false,
        drugs: [],
        instructions: [],
        customInstruction: '',
        addDrug() {
            this.drugs.push({name:'',sos:false,morning:'',noon:'',night:'',duration:'',dur_unit:'days',total_qty:'',food:'',language:'English',instruction:'',notes:''});
        },
        calcTotal(i) {
            var d=this.drugs[i];
            var perDay=(parseFloat(d.morning)||0)+(parseFloat(d.noon)||0)+(parseFloat(d.night)||0);
            var dur=parseFloat(d.duration)||0;
            var mult=d.dur_unit==='weeks'?7:d.dur_unit==='months'?30:1;
            d.total_qty=perDay>0&&dur>0?Math.ceil(perDay*dur*mult):'';
        },
        toggleInstruction(instr) {
            var i=this.instructions.indexOf(instr);
            if(i>=0) this.instructions.splice(i,1); else this.instructions.push(instr);
        },
    }
}

/* ── Main consultation form component ── */
function consultationForm() {
    return {
        status: 'draft',
        showToothPicker: false,
        showTxTeethPicker: false,
        selectedTeeth: [],
        progress: 0,
        photoCount: 0,
        photos: Array(9).fill(null),
        scanTeeth: [],
        scanFiles: [],
        attachments: [],
        dbm: Array(33).fill(null),
        tpBest:            [{ tooth:'', treatment:'', cost:'' }],
        tpAcceptable:      [{ tooth:'', treatment:'', cost:'' }],
        tpBestTotal:       0,
        tpAcceptableTotal: 0,
        txSelected: { emergency:[], protective:[], transformative:[] },
        txTeeth:    { emergency:[], protective:[], transformative:[] },
        invFiles: {},
        activeTxPicker: null,
        _prevTeeth: [],
        showTpRowPicker: false,
        tpPickerTeeth: [],
        activeTpPicker: null,

        get allTxSelected() {
            return [...this.txSelected.emergency,...this.txSelected.protective,...this.txSelected.transformative];
        },

        form: {
            chief_complaint:'', complaint_duration:'', severity:'',
            tooth_area:'', location:'', complaint_notes:'',
            visit_type:'routine', scan_date:'', investigations:[],
            clinical:{ soft_tissue:'',caries:'',periodontal:'',bleeding_on_probing:'',plaque_index:'',occlusion:'',tmj:'',existing_condition:'',oral_hygiene:'',notes:'' },
            radio:{ opg:'',iopa:'',cbct:'',notes:'' },
            diagnosis:{ primary:'',secondary:'',risk:'',notes:'' },
            aocp_best:false, aocp_best_plan:'',
            aocp_acceptable:false, aocp_acceptable_plan:'',
            finishing:{ notes:'',next_visit_type:'',next_visit_date:'',recall_interval:'',recall_custom:'',responsible_id:'' },
        },

        init() {
            this.$watch('form', () => this.updateProgress(), { deep:true });
        },

        toggleComplaintTooth(t) {
            var i=this.selectedTeeth.indexOf(t);
            if(i>=0) this.selectedTeeth.splice(i,1); else this.selectedTeeth.push(t);
            this.form.tooth_area=this.selectedTeeth.sort((a,b)=>a-b).join(', ');
        },
        clearToothPicker() { this.selectedTeeth=[];this.form.tooth_area=''; },
        quickSelectComplaint(q) {
            var map={
                upper:[11,12,13,14,15,16,17,18,21,22,23,24,25,26,27,28],
                lower:[31,32,33,34,35,36,37,38,41,42,43,44,45,46,47,48],
                right:[11,12,13,14,15,16,17,18,41,42,43,44,45,46,47,48],
                left: [21,22,23,24,25,26,27,28,31,32,33,34,35,36,37,38],
                'full mouth':[11,12,13,14,15,16,17,18,21,22,23,24,25,26,27,28,31,32,33,34,35,36,37,38,41,42,43,44,45,46,47,48],
                anterior:[11,12,13,21,22,23,31,32,33,41,42,43],
                posterior:[14,15,16,17,18,24,25,26,27,28,34,35,36,37,38,44,45,46,47,48],
            };
            (map[q]||[]).forEach(t=>{if(!this.selectedTeeth.includes(t))this.selectedTeeth.push(t);});
            this.form.tooth_area=this.selectedTeeth.sort((a,b)=>a-b).join(', ');
        },

        triggerPhoto(i) { document.getElementById('ph-'+i).click(); },
        handlePhoto(e,i) {
            var f=e.target.files[0]; if(!f) return;
            var r=new FileReader();
            r.onload=ev=>{this.photos[i]={file:f,preview:ev.target.result};this.photoCount=this.photos.filter(Boolean).length;this.updateProgress();};
            r.readAsDataURL(f);
        },
        handleInvUpload(e,key) {
            if(!this.invFiles[key]) this.invFiles[key]=[];
            Array.from(e.target.files).forEach(f=>this.invFiles[key].push(f));
        },
        handleScanUpload(e) { Array.from(e.target.files).forEach(f=>this.scanFiles.push(f)); },
        handleAttachments(e) { Array.from(e.target.files).forEach(f=>this.attachments.push(f)); },

        openTxTeethPicker(col,i) {
            this.activeTxPicker={col,i};
            this._prevTeeth=[...this.selectedTeeth];
            this.selectedTeeth=[...(this.txTeeth[col][i]||[])];
            this.showToothPicker=false;
            this.showTxTeethPicker=true;
        },
        confirmTxTeethPicker() {
            if(this.activeTxPicker){
                var {col,i}=this.activeTxPicker;
                if(!this.txTeeth[col]) this.txTeeth[col]=[];
                this.txTeeth[col][i]=[...this.selectedTeeth];
            }
            this.selectedTeeth=this._prevTeeth||[];
            this.showTxTeethPicker=false;
            this.activeTxPicker=null;
        },

        addTx(col,e) {
            var val=e.target.value; if(!val) return;
            if(val==='__custom__'){var c=prompt('Enter custom treatment:');if(c)this.txSelected[col].push(c.trim());}
            else if(!this.txSelected[col].includes(val)) this.txSelected[col].push(val);
            e.target.value='';
        },
        removeTx(col,i) { this.txSelected[col].splice(i,1); },

        /* ── TP row tooth picker ── */
        openTpRowPicker(type, idx) {
            this.activeTpPicker = {type, idx};
            var arr = type==='best' ? this.tpBest : this.tpAcceptable;
            var existing = (arr[idx] && arr[idx].tooth) ? arr[idx].tooth.split(',').map(s=>parseInt(s.replace('#','').trim())).filter(n=>!isNaN(n)) : [];
            this.tpPickerTeeth = existing;
            this.showTpRowPicker = true;
        },
        tpPickerToggle(t) {
            var i = this.tpPickerTeeth.indexOf(t);
            if(i>=0) this.tpPickerTeeth.splice(i,1); else this.tpPickerTeeth.push(t);
        },
        tpPickerQuickSelect(q) {
            var map={
                upper:[11,12,13,14,15,16,17,18,21,22,23,24,25,26,27,28],
                lower:[31,32,33,34,35,36,37,38,41,42,43,44,45,46,47,48],
                right:[11,12,13,14,15,16,17,18,41,42,43,44,45,46,47,48],
                left: [21,22,23,24,25,26,27,28,31,32,33,34,35,36,37,38],
                'full mouth':[11,12,13,14,15,16,17,18,21,22,23,24,25,26,27,28,31,32,33,34,35,36,37,38,41,42,43,44,45,46,47,48],
                anterior:[11,12,13,21,22,23,31,32,33,41,42,43],
                posterior:[14,15,16,17,18,24,25,26,27,28,34,35,36,37,38,44,45,46,47,48],
            };
            (map[q]||[]).forEach(t=>{if(!this.tpPickerTeeth.includes(t))this.tpPickerTeeth.push(t);});
        },
        confirmTpRowPicker() {
            if(this.activeTpPicker) {
                var {type, idx} = this.activeTpPicker;
                var arr = type==='best' ? this.tpBest : this.tpAcceptable;
                if(arr[idx]) {
                    arr[idx].tooth = this.tpPickerTeeth.length
                        ? '#'+this.tpPickerTeeth.slice().sort((a,b)=>a-b).join(', #')
                        : '';
                }
            }
            this.showTpRowPicker = false;
            this.activeTpPicker = null;
            this.tpPickerTeeth = [];
        },

        /* FIX 3: addTpRow / removeTpRow / calcTotal now also handle window events */
        addTpRow(type) {
            (type==='best'?this.tpBest:this.tpAcceptable).push({tooth:'',treatment:'',cost:''});
        },
        removeTpRow(type,i) {
            var arr=type==='best'?this.tpBest:this.tpAcceptable;
            if(arr.length>1) arr.splice(i,1);
            this.calcTotal(type);
        },
        calcTotal(type) {
            var arr=type==='best'?this.tpBest:this.tpAcceptable;
            var t=arr.reduce((s,r)=>s+(parseFloat(r.cost)||0),0);
            if(type==='best') this.tpBestTotal=t; else this.tpAcceptableTotal=t;
        },

        updateProgress() {
            var s=0;
            if(this.form.chief_complaint.trim())   s+=15;
            if(this.form.visit_type)               s+=5;
            if(this.photoCount>0)                  s+=10;
            if(this.form.investigations.length)    s+=5;
            if(this.form.clinical.soft_tissue)     s+=10;
            if(this.form.radio.opg.trim())         s+=10;
            if(this.form.diagnosis.primary.trim()) s+=20;
            if(this.tpBest.some(r=>r.treatment))   s+=15;
            if(this.form.finishing.next_visit_date) s+=10;
            this.progress=Math.min(s,100);
        },

        saveDraft() { this.status='draft'; this.$nextTick(()=>document.getElementById('cForm').requestSubmit()); },

        async submitConsultation() {
            var fd=new FormData(document.getElementById('cForm'));
            fd.set('clinical_data',JSON.stringify(this.form.clinical));
            fd.set('radio_data',JSON.stringify(this.form.radio));
            fd.set('diagnosis_data',JSON.stringify(this.form.diagnosis));
            fd.set('treatment_plan_best',JSON.stringify(this.tpBest));
            fd.set('treatment_plan_acceptable',JSON.stringify(this.tpAcceptable));
            fd.set('scan_teeth',JSON.stringify(this.scanTeeth));
            fd.set('dbm_checklist',JSON.stringify(this.dbm));
            fd.set('tx_emergency',JSON.stringify(this.txSelected.emergency));
            fd.set('tx_protective',JSON.stringify(this.txSelected.protective));
            fd.set('tx_transformative',JSON.stringify(this.txSelected.transformative));
            this.photos.forEach((p,i)=>{if(p?.file) fd.set('photo_'+i,p.file);});
            this.scanFiles.forEach((f,i)=>{fd.set('scan_file_'+i,f);});
            try {
                var r=await fetch('{{ route("consultations.store") }}',{
                    method:'POST',
                    headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'},
                    body:fd,
                });
                var d=await r.json();
                if(d.success){
                    window.DFLayout?.toast(d.message,'success');
                    setTimeout(()=>window.location.href=d.redirect||'{{ route("patients.show",$patient) }}',800);
                } else {
                    window.DFLayout?.toast(d.message||'Error saving.','error');
                }
            } catch(e){ window.DFLayout?.toast('Network error.','error'); }
        },
    }
}
</script>
@endpush
@endsection