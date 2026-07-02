@extends('layouts.app')
@section('page-title', 'Emergency Visit — ' . $patient->name)

@section('head-extra')
<style>
    #df-topbar        { display:none !important; }
    #df-content-inner { padding:0 !important; max-width:100% !important; }
    #df-content-area  { background:#f4f5f7 !important; }
    * { box-sizing:border-box; }
    [x-cloak] { display:none !important; }

    #ctopbar {
        position:sticky; top:0; z-index:100; background:#fff;
        border-bottom:1px solid #e5e7eb;
        height:52px; display:flex; align-items:center;
        justify-content:space-between; padding:0 24px;
    }
    .ctb-left  { display:flex; align-items:center; gap:12px; }
    .ctb-title { font-size:13px; font-weight:600; color:#374151; font-family:'Inter',sans-serif; }
    .ctb-sub   { font-size:11px; color:#9ca3af; }
    .ctb-right { display:flex; align-items:center; gap:8px; }
    .btn-save {
        padding:6px 18px; font-size:12px; font-weight:600;
        background:#b91c1c; color:#fff; border:none;
        border-radius:5px; cursor:pointer; transition:background .15s;
    }
    .btn-save:hover { background:#7f1d1d; }
    .btn-convert {
        padding:6px 16px; font-size:12px; font-weight:600;
        background:#fff; color:#6a0f70; border:1px solid #c4b5d4;
        border-radius:5px; cursor:pointer; transition:all .15s;
    }
    .btn-convert:hover { background:#faf5ff; border-color:#6a0f70; }
    .btn-outline {
        padding:6px 14px; font-size:12px; font-weight:600;
        border:1px solid #d1d5db; background:#fff; color:#6b7280;
        border-radius:5px; cursor:pointer; text-decoration:none;
        display:inline-flex; align-items:center;
    }

    #pstrip {
        background:#fff; border-bottom:2px solid #fee2e2;
        padding:12px 24px; display:flex; align-items:center; gap:16px; flex-wrap:wrap;
    }
    .ps-avatar {
        width:44px; height:44px; border-radius:50%;
        background:linear-gradient(135deg,#dc2626,#7f1d1d);
        display:flex; align-items:center; justify-content:center;
        color:#fff; font-size:16px; font-weight:600;
        font-family:'Cormorant Garamond',serif; flex-shrink:0;
    }
    .ps-name { font-size:15px; font-weight:700; color:#111827; font-family:'Cormorant Garamond',serif; }
    .ps-meta { font-size:11px; color:#9ca3af; margin-top:2px; }
    .badge-type {
        display:inline-flex; align-items:center; gap:6px;
        background:#fee2e2; color:#7f1d1d; border:1px solid #fecaca;
        padding:4px 10px; border-radius:4px; font-size:11px; font-weight:600;
    }

    .em-wrap { max-width:860px; margin:0 auto; padding:24px; display:flex; flex-direction:column; gap:20px; }

    .card {
        background:#fff; border:1px solid #e5e7eb; border-radius:8px;
        padding:22px 24px;
    }
    .card.urgent { border-left:4px solid #dc2626; }
    .card-title {
        font-size:13px; font-weight:700; color:#1a0320;
        text-transform:uppercase; letter-spacing:.06em;
        margin:0 0 16px; display:flex; align-items:center; gap:8px;
    }
    .card-title span.dot {
        width:8px; height:8px; border-radius:50%; background:#dc2626; flex-shrink:0;
    }

    .form-group { margin-bottom:16px; }
    .form-group label { font-size:12px; font-weight:600; color:#374151; display:block; margin-bottom:5px; }
    .form-group label .req { color:#dc2626; }
    .form-group textarea, .form-group input, .form-group select {
        width:100%; border:1px solid #d1d5db; border-radius:5px;
        padding:9px 12px; font-size:13px; font-family:'Inter',sans-serif;
        color:#111827; background:#fff; transition:border-color .15s; outline:none;
    }
    .form-group textarea:focus, .form-group input:focus, .form-group select:focus {
        border-color:#dc2626; box-shadow:0 0 0 2px rgba(220,38,38,.08);
    }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

    .emergency-examples {
        display:flex; flex-wrap:wrap; gap:6px; margin-bottom:14px;
    }
    .em-tag {
        background:#fef2f2; border:1px solid #fecaca; color:#b91c1c;
        font-size:11px; padding:3px 10px; border-radius:99px; cursor:pointer;
        transition:all .15s; user-select:none;
    }
    .em-tag:hover { background:#fee2e2; }

    .convert-box {
        background:#faf5ff; border:2px dashed #c4b5d4;
        border-radius:8px; padding:20px 24px;
        display:flex; align-items:center; justify-content:space-between; gap:16px;
    }
    .convert-box-text .title { font-size:14px; font-weight:700; color:#374151; margin-bottom:4px; }
    .convert-box-text .sub   { font-size:12px; color:#9ca3af; }
</style>
@endsection

@section('content')
<form method="POST" action="{{ route('patients.consultations.emergency.store', $patient) }}"
      x-data="emergencyForm()"
      id="em-form">
@csrf

{{-- ── Topbar ── --}}
<div id="ctopbar">
    <div class="ctb-left">
        <a href="{{ route('patients.show', $patient) }}#consultation" class="btn-outline">← Back</a>
        <div>
            <div class="ctb-title">Emergency Visit</div>
            <div class="ctb-sub">{{ $patient->name }} · Acute condition requiring immediate care</div>
        </div>
    </div>
    <div class="ctb-right">
        <button type="submit" name="_convert_to_new" value="1" class="btn-convert">
            Save &amp; Create New Consultation →
        </button>
        <button type="submit" class="btn-save">Save Emergency Visit</button>
    </div>
</div>

{{-- ── Patient strip ── --}}
<div id="pstrip">
    <div class="ps-avatar">{{ strtoupper(substr($patient->name, 0, 1)) }}</div>
    <div>
        <div class="ps-name">{{ $patient->name }}</div>
        <div class="ps-meta">
            {{ $patient->age ?? '—' }} yrs
            @if($patient->gender) · {{ ucfirst($patient->gender) }} @endif
            @if($patient->phone) · {{ $patient->phone }} @endif
        </div>
    </div>
    <div style="margin-left:auto;">
        <span class="badge-type">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Emergency
        </span>
    </div>
</div>

@if($errors->any())
<div style="background:#fef2f2;border:1px solid #fecaca;padding:12px 24px;font-size:13px;color:#dc2626;">
    @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
</div>
@endif

<div class="em-wrap">

    {{-- ── Meta row ── --}}
    <div class="card">
        <div class="card-title"><span class="dot"></span> Visit Details</div>
        <div class="form-row">
            <div class="form-group">
                <label>Doctor <span class="req">*</span></label>
                <select name="doctor_id" required>
                    @foreach($doctors as $doc)
                    <option value="{{ $doc->id }}" {{ $doc->id == auth()->id() ? 'selected' : '' }}>
                        {{ $doc->doctor_name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Date &amp; Time</label>
                <input type="datetime-local" name="consultation_date"
                    value="{{ now()->format('Y-m-d\TH:i') }}">
            </div>
        </div>
    </div>

    {{-- ── Chief Complaint ── --}}
    <div class="card urgent">
        <div class="card-title"><span class="dot"></span> Chief Complaint &amp; HOPI</div>

        <div class="emergency-examples">
            @foreach(['Severe Pain','Swelling','Trauma','Bleeding','Broken Tooth','Lost Filling','Tooth Avulsion','Abscess','Sensitivity','Locked Jaw'] as $eg)
            <span class="em-tag" @click="setComplaint('{{ $eg }}')">{{ $eg }}</span>
            @endforeach
        </div>

        <div class="form-group">
            <label>Chief Complaint <span class="req">*</span></label>
            <input type="text" name="chief_complaint" x-model="complaintText" required
                placeholder="Primary presenting complaint — be specific (tooth/location/severity)">
        </div>
        <div class="form-group">
            <label>History (HOPI)</label>
            <textarea name="hopi_final" rows="3"
                placeholder="Since when? What happened? Any prior treatment? Medications taken?">{{ old('hopi_final') }}</textarea>
        </div>
    </div>

    {{-- ── Emergency Examination ── --}}
    <div class="card">
        <div class="card-title"><span class="dot"></span> Emergency Examination</div>
        <div class="form-group">
            <label>Clinical Findings</label>
            <textarea name="clinical_data[notes]" rows="3"
                placeholder="Extraoral/intraoral findings, swelling extent, percussion/palpation, mobility, bleeding.">{{ old('clinical_data.notes') }}</textarea>
        </div>
        <div class="form-group">
            <label>Emergency Diagnosis</label>
            <textarea name="primary_diagnosis" rows="2"
                placeholder="Working diagnosis (e.g. Acute apical abscess #26, Ellis Class III fracture #11).">{{ old('primary_diagnosis') }}</textarea>
        </div>
    </div>

    {{-- ── Treatment Rendered ── --}}
    <div class="card urgent">
        <div class="card-title"><span class="dot"></span> Emergency Treatment Rendered</div>
        <div class="form-group">
            <label>Treatment Given <span class="req">*</span></label>
            <textarea name="emergency_treatment_rendered" rows="4" required
                placeholder="Describe all procedures performed during this emergency visit.&#10;e.g. Incision &amp; drainage of abscess, antibiotics prescribed, temporary dressing placed on #26.">{{ old('emergency_treatment_rendered') }}</textarea>
        </div>
        <div class="form-group">
            <label>Advice</label>
            <textarea name="advice" rows="2"
                placeholder="Post-treatment instructions. e.g. Ice pack 20 min on/off. Soft diet. Return if swelling worsens.">{{ old('advice') }}</textarea>
        </div>
    </div>


    {{-- ── Convert to New Consultation call-out ── --}}
    <div class="convert-box">
        <div class="convert-box-text">
            <div class="title">Does this patient need definitive treatment planning?</div>
            <div class="sub">
                Use "Save &amp; Create New Consultation" (top right) to save this emergency record
                and immediately open a New Consultation for full diagnosis and treatment planning.
            </div>
        </div>
        <button type="submit" name="_convert_to_new" value="1" class="btn-convert"
                style="white-space:nowrap;flex-shrink:0;">
            Save &amp; Plan Treatment →
        </button>
    </div>

    {{-- ── Submit ── --}}
    <div style="display:flex;justify-content:flex-end;gap:10px;padding-bottom:32px;">
        <a href="{{ route('patients.show', $patient) }}" class="btn-outline">Cancel</a>
        <button type="submit" class="btn-save" style="padding:8px 24px;">Save Emergency Visit</button>
    </div>

</div>
</form>
@endsection

@push('scripts')
<script>
function emergencyForm() {
    return {
        complaintText: '',
        setComplaint(text) { this.complaintText = text; },
    };
}
</script>
@endpush
