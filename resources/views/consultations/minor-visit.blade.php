@extends('layouts.app')
@section('page-title', 'Minor Visit — ' . $patient->name)

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
        background:linear-gradient(135deg,#0e7490,#155e75);
        display:flex; align-items:center; justify-content:center;
        color:#fff; font-size:16px; font-weight:600;
        font-family:'Cormorant Garamond',serif; flex-shrink:0;
    }
    .ps-name { font-size:15px; font-weight:700; color:#111827; font-family:'Cormorant Garamond',serif; }
    .ps-meta { font-size:11px; color:#9ca3af; margin-top:2px; }
    .badge-type {
        display:inline-flex; align-items:center; gap:6px;
        background:#cffafe; color:#155e75; border:1px solid #a5f3fc;
        padding:4px 10px; border-radius:4px; font-size:11px; font-weight:600;
    }

    .mv-wrap { max-width:860px; margin:0 auto; padding:24px; display:flex; flex-direction:column; gap:20px; }

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
        width:8px; height:8px; border-radius:50%; background:#0e7490; flex-shrink:0;
    }

    /* First question toggle */
    .big-choice {
        display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:4px;
    }
    .choice-btn {
        border:2px solid #e5e7eb; border-radius:8px;
        padding:16px 20px; cursor:pointer; transition:all .15s;
        background:#fff; text-align:left;
    }
    .choice-btn input[type=radio] { display:none; }
    .choice-btn .cb-title { font-size:14px; font-weight:700; color:#374151; margin-bottom:4px; }
    .choice-btn .cb-sub   { font-size:12px; color:#9ca3af; }
    .choice-btn.selected, .choice-btn:has(input:checked) {
        border-color:#0e7490; background:#f0fdfe;
    }
    .choice-btn.selected .cb-title { color:#0e7490; }

    .form-group { margin-bottom:16px; }
    .form-group label { font-size:12px; font-weight:600; color:#374151; display:block; margin-bottom:5px; }
    .form-group label .req { color:#dc2626; }
    .form-group textarea, .form-group input, .form-group select {
        width:100%; border:1px solid #d1d5db; border-radius:5px;
        padding:9px 12px; font-size:13px; font-family:'Inter',sans-serif;
        color:#111827; background:#fff; transition:border-color .15s; outline:none;
    }
    .form-group textarea:focus, .form-group input:focus, .form-group select:focus {
        border-color:#0e7490; box-shadow:0 0 0 2px rgba(14,116,144,.08);
    }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

    .minor-examples {
        display:flex; flex-wrap:wrap; gap:6px; margin-bottom:14px;
    }
    .minor-tag {
        background:#f0fdfe; border:1px solid #a5f3fc; color:#0e7490;
        font-size:11px; padding:3px 10px; border-radius:99px; cursor:pointer;
        transition:all .15s; user-select:none;
    }
    .minor-tag:hover { background:#cffafe; }

    .medico-note {
        background:#fffbeb; border:1px solid #fde68a; border-radius:5px;
        padding:10px 14px; font-size:12px; color:#92400e;
        display:flex; align-items:flex-start; gap:8px; margin-bottom:14px;
    }
</style>
@endsection

@section('content')
{{-- Slice 1 fix (2026-08-01): edit-aware — $consultation present means we're
     editing an existing Minor Visit (via consultations.minor-visit.edit),
     absent means the normal create flow. Same view, two routes. --}}
<form method="POST"
      action="{{ isset($consultation) ? route('patients.consultations.minor-visit.update', [$patient, $consultation]) : route('patients.consultations.minor-visit.store', $patient) }}"
      x-data="minorVisitForm(@js(isset($consultation) ? [
          'clinicRelated'  => (bool) $consultation->related_to_clinic_treatment,
          'procedureText'  => $consultation->procedure_performed ?? '',
      ] : null))"
      id="mv-form">
@csrf
@if(isset($consultation)) @method('PUT') @endif

{{-- ── Topbar ── --}}
<div id="ctopbar">
    <div class="ctb-left">
        <a href="{{ isset($consultation) ? route('consultations.show', $consultation) : route('patients.show', $patient) . '#consultation' }}" class="btn-outline">← Back</a>
        <div>
            <div class="ctb-title">{{ isset($consultation) ? 'Edit Minor Visit' : 'Minor Visit' }}</div>
            <div class="ctb-sub">{{ $patient->name }} · Standalone procedure or review</div>
        </div>
    </div>
    <div class="ctb-right">
        <button type="submit" class="btn-save">{{ isset($consultation) ? 'Update Minor Visit' : 'Save Minor Visit' }}</button>
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
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            Minor Visit
        </span>
    </div>
</div>

@if($errors->any())
<div style="background:#fef2f2;border:1px solid #fecaca;padding:12px 24px;font-size:13px;color:#dc2626;">
    @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
</div>
@endif

<div class="mv-wrap">

    {{-- ── Meta row ── --}}
    <div class="card">
        <div class="card-title"><span class="dot"></span> Visit Details</div>
        <div class="form-row">
            <div class="form-group">
                <label>Doctor <span class="req">*</span></label>
                <select name="doctor_id" required>
                    @php $selectedDoctorId = old('doctor_id', isset($consultation) ? $consultation->doctor_id : auth()->id()); @endphp
                    @foreach($doctors as $doc)
                    <option value="{{ $doc->id }}" {{ $doc->id == $selectedDoctorId ? 'selected' : '' }}>
                        {{ $doc->doctor_name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Date</label>
                {{-- Slice 3 (2026-08-03): backdating allowed, future dates blocked; server enforces before_or_equal:today --}}
                <input type="date" name="consultation_date"
                    value="{{ old('consultation_date', isset($consultation) ? $consultation->consultation_date->format('Y-m-d') : date('Y-m-d')) }}"
                    max="{{ date('Y-m-d') }}">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Reason</label>
                <input type="text" name="chief_complaint"
                    value="{{ old('chief_complaint', isset($consultation) ? $consultation->chief_complaint : '') }}"
                    placeholder="e.g. Suture removal, pain in tooth #46, denture adjustment">
            </div>
            <div class="form-group">
                <label>Charges <span style="font-weight:400;color:#9ca3af;">(optional)</span></label>
                <input type="number" name="charges" min="0" step="0.01"
                    placeholder="e.g. 500" {{ isset($consultation) ? 'disabled' : '' }}>
                @if(isset($consultation))
                <p style="font-size:11px;color:#9ca3af;margin-top:4px;">Charges aren't stored on the record and can't be edited here — queue a fresh billing prompt from the patient's Billing tab if needed.</p>
                @else
                <p style="font-size:11px;color:#9ca3af;margin-top:4px;">If set, a billing prompt is queued for front desk — no invoice is created automatically.</p>
                @endif
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Follow-up Date <span style="font-weight:400;color:#9ca3af;">(optional)</span></label>
                <input type="date" name="follow_up_date"
                    value="{{ old('follow_up_date', isset($consultation) && $consultation->follow_up_date ? $consultation->follow_up_date->format('Y-m-d') : '') }}">
            </div>
            <div class="form-group">
                <label>Follow-up Note <span style="font-weight:400;color:#9ca3af;">(optional)</span></label>
                <input type="text" name="follow_up_note"
                    value="{{ old('follow_up_note', isset($consultation) ? $consultation->follow_up_note : '') }}"
                    placeholder="e.g. Review healing in 1 week">
            </div>
        </div>
    </div>

    {{-- ── First question ── --}}
    <div class="card">
        <div class="card-title"><span class="dot"></span> Is this related to treatment performed at this clinic?</div>

        <div class="big-choice">
            <label class="choice-btn" :class="{ selected: clinicRelated === true }">
                <input type="radio" name="related_to_clinic_treatment" value="1"
                       x-model="clinicRelatedRaw" @change="clinicRelated = true" required>
                <div class="cb-title">✓ Yes, clinic treatment</div>
                <div class="cb-sub">Follow-up to procedure done here. No full documentation needed.</div>
            </label>
            <label class="choice-btn" :class="{ selected: clinicRelated === false }">
                <input type="radio" name="related_to_clinic_treatment" value="0"
                       x-model="clinicRelatedRaw" @change="clinicRelated = false">
                <div class="cb-title">✗ No, external / walk-in</div>
                <div class="cb-sub">Patient treated elsewhere. Full medico-legal documentation required.</div>
            </label>
        </div>
    </div>

    {{-- ── Clinic-related sub-form ── --}}
    <div x-show="clinicRelated === true" x-cloak>
        <div class="card">
            <div class="card-title"><span class="dot"></span> Procedure Details</div>

            {{-- Quick-select examples --}}
            <div class="minor-examples">
                @foreach(['Suture Removal','Crown Recementation','Temporary Filling Replacement','Dressing Change','Dry Socket Dressing','Denture Adjustment','Fluoride Application','Oral Hygiene Review'] as $eg)
                <span class="minor-tag" @click="setProcedure('{{ $eg }}')">{{ $eg }}</span>
                @endforeach
            </div>

            <div class="form-group">
                <label>Procedure Performed <span class="req">*</span></label>
                <textarea name="procedure_performed" rows="3" x-model="procedureText"
                    :required="clinicRelated === true"
                    placeholder="Describe what was done today."></textarea>
            </div>

            <div class="form-group">
                <label>Clinical Notes</label>
                <textarea name="finishing_notes" rows="2"
                    placeholder="Any observations or post-procedure findings.">{{ old('finishing_notes', isset($consultation) ? $consultation->finishing_notes : '') }}</textarea>
            </div>

            <div class="form-group">
                <label>Advice Given</label>
                {{-- Slice 1 fix (2026-08-01): this textarea shared name="advice" with the
                     external/walk-in one below. x-show only hides with CSS — both stayed
                     in the DOM and both posted, so whichever was later in DOM order
                     silently overwrote this one on submit. Given a unique name here;
                     merged back into the single `advice` column server-side in
                     minorVisitStore() based on related_to_clinic_treatment. --}}
                <textarea name="advice_clinic_related" rows="2"
                    placeholder="e.g. Avoid hard foods for 24 hours. Return if pain persists.">{{ old('advice_clinic_related', (isset($consultation) && $consultation->related_to_clinic_treatment) ? $consultation->advice : '') }}</textarea>
            </div>
        </div>
    </div>

    {{-- ── External / walk-in sub-form ── --}}
    <div x-show="clinicRelated === false" x-cloak>
        <div class="medico-note">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Patient treated externally — full documentation required for medico-legal protection.
        </div>

        <div class="card">
            <div class="card-title"><span class="dot"></span> History</div>
            {{-- Reason (chief_complaint) is captured once, in the shared Visit Details card above —
                 not duplicated here to avoid two inputs sharing the same field name. --}}
            <div class="form-group">
                <label>History (HOPI)</label>
                <textarea name="hopi_final" rows="3"
                    placeholder="Duration, nature of complaint, treatment already taken.">{{ old('hopi_final', isset($consultation) ? $consultation->hopi_final : '') }}</textarea>
            </div>
        </div>

        <div class="card">
            <div class="card-title"><span class="dot"></span> Examination & Findings</div>
            <div class="form-group">
                <label>Clinical Findings</label>
                <textarea name="clinical_data[notes]" rows="3"
                    placeholder="Examination findings — soft tissue, tooth condition, etc.">{{ old('clinical_data.notes', isset($consultation) ? ($consultation->clinical_data['notes'] ?? '') : '') }}</textarea>
            </div>
            <div class="form-group">
                <label>Diagnosis</label>
                <textarea name="primary_diagnosis" rows="2"
                    placeholder="Working diagnosis based on today's examination.">{{ old('primary_diagnosis', isset($consultation) ? $consultation->primary_diagnosis : '') }}</textarea>
            </div>
        </div>

        <div class="card">
            <div class="card-title"><span class="dot"></span> Procedure & Outcome</div>

            <div class="minor-examples">
                @foreach(['Suture Removal','Crown Recementation','Temporary Filling Replacement','Dressing Change','Dry Socket Dressing','Denture Adjustment','Fluoride Application','Oral Hygiene Review'] as $eg)
                <span class="minor-tag" @click="setProcedure('{{ $eg }}')">{{ $eg }}</span>
                @endforeach
            </div>

            <div class="form-group">
                <label>Procedure Performed <span class="req">*</span></label>
                <textarea name="procedure_performed" rows="3" x-model="procedureText"
                    :required="clinicRelated === false"
                    placeholder="Describe what was done today."></textarea>
            </div>

            <div class="form-group">
                <label>Advice Given</label>
                {{-- Slice 1 fix (2026-08-01): see matching note on the clinic-related
                     "Advice Given" field above — unique name, merged server-side. --}}
                <textarea name="advice_external" rows="2"
                    placeholder="Post-procedure instructions and advice.">{{ old('advice_external', (isset($consultation) && !$consultation->related_to_clinic_treatment) ? $consultation->advice : '') }}</textarea>
            </div>
        </div>
    </div>


    {{-- ── Submit ── --}}
    <div style="display:flex;justify-content:flex-end;gap:10px;padding-bottom:32px;">
        <a href="{{ route('patients.show', $patient) }}" class="btn-outline">Cancel</a>
        <button type="submit" class="btn-save" style="padding:8px 24px;" :disabled="clinicRelated === null">
            Save Minor Visit
        </button>
    </div>

</div>
</form>
@endsection

@push('scripts')
<script>
function minorVisitForm(initial) {
    // Slice 1 fix (2026-08-01): `initial` is passed from Blade when editing an
    // existing Minor Visit ({clinicRelated, procedureText}), or null when
    // creating one. Seeds the radio's checked state (clinicRelatedRaw), the
    // branch toggle (clinicRelated), and the shared Procedure Performed field
    // (procedureText is x-model-only with no server-rendered fallback) so
    // Edit opens on the correct sub-form with existing data intact instead of
    // a blank form.
    const clinicRelated = initial ? initial.clinicRelated : null;
    return {
        clinicRelated: clinicRelated,
        clinicRelatedRaw: clinicRelated === null ? '' : (clinicRelated ? '1' : '0'),
        procedureText: initial ? (initial.procedureText || '') : '',
        setProcedure(text) {
            this.procedureText = text;
        },
    };
}
</script>
@endpush
