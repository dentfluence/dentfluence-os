@extends('layouts.app')
@section('page-title', 'Same Issue — ' . $patient->name)

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
    .btn-save  {
        padding:6px 18px; font-size:12px; font-weight:600;
        background:#6a0f70; color:#fff; border:none;
        border-radius:5px; cursor:pointer; transition:background .15s;
    }
    .btn-save:hover { background:#380740; }
    .btn-outline {
        padding:6px 14px; font-size:12px; font-weight:600;
        border:1px solid #d1d5db; background:#fff; color:#6b7280;
        border-radius:5px; cursor:pointer; text-decoration:none;
        display:inline-flex; align-items:center;
    }

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
    .ps-meta { font-size:11px; color:#9ca3af; margin-top:2px; }
    .badge-type {
        display:inline-flex; align-items:center; gap:6px;
        background:#fef3c7; color:#92400e; border:1px solid #fde68a;
        padding:4px 10px; border-radius:4px; font-size:11px; font-weight:600;
    }

    .si-wrap { max-width:860px; margin:0 auto; padding:24px; display:flex; flex-direction:column; gap:20px; }

    .card {
        background:#fff; border:1px solid #e5e7eb; border-radius:8px;
        padding:22px 24px;
    }
    .card-title {
        font-size:13px; font-weight:700; color:#1a0320;
        text-transform:uppercase; letter-spacing:.06em;
        margin:0 0 16px; display:flex; align-items:center; gap:8px;
    }
    .card-title span.dot {
        width:8px; height:8px; border-radius:50%; background:#6a0f70; flex-shrink:0;
    }

    /* Previous context box */
    .prev-box {
        background:#faf5ff; border:1px solid #e9d5ff;
        border-radius:6px; padding:16px 18px;
        font-size:13px; color:#374151;
    }
    .prev-box-label {
        font-size:10px; font-weight:700; text-transform:uppercase;
        letter-spacing:.08em; color:#7c3aed; margin-bottom:8px;
    }
    .prev-field { margin-bottom:10px; }
    .prev-field strong { font-size:11px; color:#6b7280; display:block; margin-bottom:2px; }
    .prev-field p { margin:0; font-size:13px; color:#1f2937; }

    .form-group { margin-bottom:16px; }
    .form-group label { font-size:12px; font-weight:600; color:#374151; display:block; margin-bottom:5px; }
    .form-group label .req { color:#dc2626; }
    .form-group textarea, .form-group input, .form-group select {
        width:100%; border:1px solid #d1d5db; border-radius:5px;
        padding:9px 12px; font-size:13px; font-family:'Inter',sans-serif;
        color:#111827; background:#fff; transition:border-color .15s;
        outline:none;
    }
    .form-group textarea:focus, .form-group input:focus, .form-group select:focus {
        border-color:#6a0f70; box-shadow:0 0 0 2px rgba(106,15,112,.08);
    }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

    .tx-plan-row {
        display:flex; align-items:flex-start; justify-content:space-between; gap:12px;
        padding:12px 14px; border:1px solid #e5e7eb; border-radius:6px;
        margin-bottom:8px; font-size:13px; background:#fafafa;
    }
    .tx-plan-row .plan-meta { flex:1; min-width:0; }
    .tx-plan-row .plan-title { font-weight:600; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .tx-plan-row .plan-sub   { font-size:11px; color:#9ca3af; margin-top:2px; }
    .tx-plan-row .plan-badge {
        padding:2px 8px; border-radius:99px; font-size:10px; font-weight:700;
        text-transform:uppercase; letter-spacing:.05em; flex-shrink:0;
    }
    .badge-pending    { background:#fef9c3; color:#92400e; }
    .badge-approved   { background:#d1fae5; color:#065f46; }
    .badge-in_progress{ background:#dbeafe; color:#1e40af; }
    .badge-completed  { background:#f3f4f6; color:#6b7280; }

    .info-note {
        background:#fffbeb; border:1px solid #fde68a; border-radius:5px;
        padding:10px 14px; font-size:12px; color:#92400e; display:flex; gap:8px;
    }
</style>
@endsection

@section('content')
<form method="POST" action="{{ route('patients.consultations.same-issue.store', $patient) }}"
      id="si-form">
@csrf

{{-- ── Topbar ── --}}
<div id="ctopbar">
    <div class="ctb-left">
        <a href="{{ route('patients.show', $patient) }}#consultation" class="btn-outline">← Back</a>
        <div>
            <div class="ctb-title">Same Issue Consultation</div>
            <div class="ctb-sub">{{ $patient->name }} · Returning for same complaint</div>
        </div>
    </div>
    <div class="ctb-right">
        <button type="submit" class="btn-save">Save Consultation</button>
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
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,12 2,6"/></svg>
            Same Issue
        </span>
    </div>
</div>

@if($errors->any())
<div style="background:#fef2f2;border:1px solid #fecaca;padding:12px 24px;font-size:13px;color:#dc2626;">
    @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
</div>
@endif

<div class="si-wrap">

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
                <label>Date</label>
                <input type="date" name="consultation_date" value="{{ date('Y-m-d') }}">
            </div>
        </div>
    </div>

    {{-- ── Previous consultation context ── --}}
    @if($previousConsultation)
    <div class="card">
        <div class="card-title"><span class="dot"></span> Previous Consultation (Auto-loaded)</div>
        <div class="prev-box">
            <div class="prev-box-label">
                {{ $previousConsultation->consultation_date?->format('d M Y') ?? '—' }}
                &nbsp;·&nbsp; {{ $previousConsultation->doctor?->doctor_name ?? '—' }}
                &nbsp;·&nbsp; {{ $previousConsultation->typeLabel() }}
            </div>
            @if($previousConsultation->chief_complaint)
            <div class="prev-field">
                <strong>Chief Complaint</strong>
                <p>{{ $previousConsultation->chief_complaint }}</p>
            </div>
            @endif
            @if($previousConsultation->hopi_final ?? $previousConsultation->hopi_auto)
            <div class="prev-field">
                <strong>History (HOPI)</strong>
                <p>{{ $previousConsultation->hopi_final ?? $previousConsultation->hopi_auto }}</p>
            </div>
            @endif
            @if($previousConsultation->primary_diagnosis)
            <div class="prev-field">
                <strong>Diagnosis</strong>
                <p>{{ $previousConsultation->primary_diagnosis }}</p>
            </div>
            @endif
        </div>

        <input type="hidden" name="previous_consultation_id" value="{{ $previousConsultation->id }}">

        {{-- Previous Treatment Plans ── --}}
        @if($previousConsultation->treatmentPlans && $previousConsultation->treatmentPlans->isNotEmpty())
        <div style="margin-top:18px;">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#6b7280;letter-spacing:.06em;margin-bottom:10px;">
                Existing Treatment Plans
            </div>
            @foreach($previousConsultation->treatmentPlans as $tp)
            <div class="tx-plan-row">
                <div class="plan-meta">
                    <div class="plan-title">{{ $tp->title ?? 'Treatment Plan #' . $tp->id }}</div>
                    <div class="plan-sub">
                        Created {{ $tp->created_at?->format('d M Y') }}
                        @if($tp->total_amount) · Rs. {{ number_format($tp->total_amount, 0) }} @endif
                    </div>
                </div>
                <span class="plan-badge badge-{{ $tp->status ?? 'pending' }}">
                    {{ ucfirst(str_replace('_', ' ', $tp->status ?? 'Pending')) }}
                </span>
                <a href="{{ route('treatment-plans.from-consultation', [$patient, $previousConsultation]) }}"
                   style="font-size:12px;color:#6a0f70;text-decoration:none;flex-shrink:0;">
                   Revise →
                </a>
            </div>
            @endforeach
        </div>
        @endif
    </div>
    @else
    <div class="info-note">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        No previous consultation found for this patient. This will be recorded as the first visit.
    </div>
    @endif

    {{-- ── Current Update ── --}}
    <div class="card">
        <div class="card-title"><span class="dot"></span> Current Update <span style="font-size:10px;font-weight:400;color:#9ca3af;text-transform:none;letter-spacing:0;">(What has changed or why they returned)</span></div>
        <div class="form-group">
            <label>Patient Update <span class="req">*</span></label>
            <textarea name="update_notes" rows="4" required
                placeholder="e.g. Patient discussed with family and wants Implant instead of Bridge. Previously quoted Rs. 18,000 for bridge. Requesting revised quote for implant option.">{{ old('update_notes') }}</textarea>
        </div>
        <div class="form-group">
            <label>Additional Findings Today <span style="font-size:11px;font-weight:400;color:#9ca3af;">(if any new observations)</span></label>
            <textarea name="additional_findings" rows="3"
                placeholder="e.g. Slight increase in sensitivity. Tooth #46 shows early periapical changes on today's IOPA.">{{ old('additional_findings') }}</textarea>
        </div>
    </div>

    {{-- ── Updated Diagnosis ── --}}
    <div class="card">
        <div class="card-title"><span class="dot"></span> Updated Diagnosis <span style="font-size:10px;font-weight:400;color:#9ca3af;text-transform:none;letter-spacing:0;">(only if changed)</span></div>
        <div class="form-group">
            <label>Diagnosis</label>
            <textarea name="primary_diagnosis" rows="2"
                placeholder="Leave blank if unchanged from previous visit.">{{ old('primary_diagnosis', $previousConsultation?->primary_diagnosis) }}</textarea>
        </div>
        <div class="form-group">
            <label>Notes</label>
            <textarea name="diagnosis_notes" rows="2"
                placeholder="Any changes in risk assessment or differential.">{{ old('diagnosis_notes') }}</textarea>
        </div>
    </div>


    {{-- ── Follow-up Notes ── --}}
    <div class="card">
        <div class="card-title"><span class="dot"></span> Closing Notes</div>
        <div class="form-group">
            <label>Notes / Next Steps</label>
            <textarea name="finishing_notes" rows="3"
                placeholder="e.g. Revised quotation to be prepared. Patient to return after reviewing implant cost.">{{ old('finishing_notes') }}</textarea>
        </div>
    </div>

    {{-- ── Submit ── --}}
    <div style="display:flex;justify-content:flex-end;gap:10px;padding-bottom:32px;">
        <a href="{{ route('patients.show', $patient) }}" class="btn-outline">Cancel</a>
        <button type="submit" class="btn-save" style="padding:8px 24px;">Save Same Issue Consultation</button>
    </div>

</div>
</form>
@endsection

