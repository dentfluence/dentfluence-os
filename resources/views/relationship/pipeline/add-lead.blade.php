{{--
|==========================================================================
| PRE — Add / Edit Lead (Phase 8 · Slice 2 — PRM Retirement)
| Route: GET  /relationship/pipeline/add          [relationship.pipeline.add-lead]
|        GET  /relationship/pipeline/{id}/edit    [relationship.pipeline.edit-lead]
|        POST /relationship/pipeline/add          [relationship.pipeline.store-lead]
|        POST /relationship/pipeline/{id}/edit    [relationship.pipeline.update-lead]
|
| Same fields as communication/prm/add-lead.blade.php, same validation
| (LeadPipelineController::validateLeadForm — identical rules to PRM's),
| PRE's layout/styling. $lead is present only in edit mode.
| Variables: $leadSources, $treatments, $staff, $stages, $languages,
|            $timeSlots, $lead (optional)
|==========================================================================
--}}
@extends('layouts.app')

@section('page-title', isset($lead) ? 'Edit Lead' : 'Add New Lead')

@section('content')
<div style="max-width:720px;margin:0 auto;padding:24px 16px 60px;">

    <a href="{{ route('relationship.pipeline') }}" style="font-size:12px;color:#6b7280;text-decoration:none;display:inline-flex;align-items:center;gap:6px;margin-bottom:20px;">
        ← Back to Pipeline
    </a>

    <h1 style="font-size:22px;font-weight:700;color:#1f2937;font-family:'Cormorant Garamond',serif;margin:0 0 4px;">{{ isset($lead) ? 'Edit Lead' : 'Add New Lead' }}</h1>
    <p style="color:#6b7280;font-size:13px;margin:0 0 22px;">
        {{ isset($lead) ? 'Update lead details and pipeline stage.' : 'Capture a new patient lead into the pipeline.' }}
    </p>

    @if ($errors->any())
        <div style="background:#FDECEC;border:1px solid #f5b5b5;border-radius:8px;padding:12px 14px;margin-bottom:18px;font-size:13px;color:#8A1F1F;">
            @foreach ($errors->all() as $error)
                <div>• {{ $error }}</div>
            @endforeach
        </div>
    @endif

    @php $inputStyle = 'width:100%;box-sizing:border-box;padding:9px 12px;border:1px solid #e5e7eb;border-radius:7px;font-size:13px;background:#fff;'; @endphp

    <form method="POST" action="{{ isset($lead) ? route('relationship.pipeline.update-lead', $lead->id) : route('relationship.pipeline.store-lead') }}">
        @csrf

        {{-- Contact info --}}
        <div style="background:#fff;border:1px solid #eceef2;border-radius:10px;padding:18px;margin-bottom:14px;">
            <div style="font-size:11.5px;font-weight:700;color:#534AB7;text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px;">Contact Info</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Name <span style="color:#c0392b;">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $lead->name ?? '') }}" placeholder="Patient's full name" required style="{{ $inputStyle }}">
                </div>
                <div>
                    <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Phone <span style="color:#c0392b;">*</span></label>
                    <input type="tel" name="phone" value="{{ old('phone', $lead->phone ?? '') }}" placeholder="e.g. 9876543210" required style="{{ $inputStyle }}">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Alternate Phone</label>
                    <input type="tel" name="alt_phone" value="{{ old('alt_phone', $lead->alt_phone ?? '') }}" placeholder="Optional" style="{{ $inputStyle }}">
                </div>
                <div>
                    <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Email</label>
                    <input type="email" name="email" value="{{ old('email', $lead->email ?? '') }}" placeholder="Optional" style="{{ $inputStyle }}">
                </div>
            </div>
        </div>

        {{-- Source --}}
        <div style="background:#fff;border:1px solid #eceef2;border-radius:10px;padding:18px;margin-bottom:14px;">
            <div style="font-size:11.5px;font-weight:700;color:#534AB7;text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px;">Where did this lead come from?</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Lead Source</label>
                    <select name="lead_source" style="{{ $inputStyle }}">
                        <option value="">— Select channel —</option>
                        @foreach ($leadSources as $key => $label)
                            <option value="{{ $key }}" {{ old('lead_source', $lead->lead_source ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Referred By <small style="color:#9ca3af;">(if Referral)</small></label>
                    <input type="text" name="referred_by" value="{{ old('referred_by', $lead->referred_by ?? '') }}" placeholder="Referring patient or doctor name" style="{{ $inputStyle }}">
                </div>
            </div>
        </div>

        {{-- Treatment + value --}}
        <div style="background:#fff;border:1px solid #eceef2;border-radius:10px;padding:18px;margin-bottom:14px;">
            <div style="font-size:11.5px;font-weight:700;color:#534AB7;text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px;">Treatment Interest &amp; Value</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Primary Treatment</label>
                    <select name="treatment" style="{{ $inputStyle }}">
                        <option value="">— Select treatment —</option>
                        @foreach ($treatments as $t)
                            <option value="{{ $t }}" {{ old('treatment', $lead->treatment ?? '') === $t ? 'selected' : '' }}>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Secondary Treatment</label>
                    <select name="secondary_treatment" style="{{ $inputStyle }}">
                        <option value="">— None —</option>
                        @foreach ($treatments as $t)
                            <option value="{{ $t }}" {{ old('secondary_treatment', $lead->secondary_treatment ?? '') === $t ? 'selected' : '' }}>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Estimated Value (₹)</label>
                    <input type="number" name="lead_value" value="{{ old('lead_value', $lead->lead_value ?? '') }}" placeholder="e.g. 45000" min="0" step="500" style="{{ $inputStyle }}">
                </div>
                <div>
                    <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Urgency</label>
                    <select name="urgency" style="{{ $inputStyle }}">
                        @foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'] as $val => $lbl)
                            <option value="{{ $val }}" {{ old('urgency', $lead->urgency ?? 'medium') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Pipeline --}}
        <div style="background:#fff;border:1px solid #eceef2;border-radius:10px;padding:18px;margin-bottom:14px;">
            <div style="font-size:11.5px;font-weight:700;color:#534AB7;text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px;">Pipeline Stage &amp; Assignment</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Pipeline Stage</label>
                    <select name="stage" style="{{ $inputStyle }}">
                        @foreach ($stages as $key => $info)
                            <option value="{{ $key }}" {{ old('stage', $lead->stage ?? 'new_lead') === $key ? 'selected' : '' }}>{{ $info['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Assigned To</label>
                    <select name="assigned_to" style="{{ $inputStyle }}">
                        <option value="">— Unassigned —</option>
                        @foreach ($staff as $s)
                            <option value="{{ $s }}" {{ old('assigned_to', $lead->assigned_to ?? '') === $s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Follow-up Date</label>
                    <input type="date" name="followup_date" value="{{ old('followup_date', isset($lead->followup_date) ? $lead->followup_date->format('Y-m-d') : '') }}" style="{{ $inputStyle }}">
                </div>
                <div>
                    <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Preferred Time</label>
                    <select name="followup_time" style="{{ $inputStyle }}">
                        <option value="">— Any time —</option>
                        @foreach ($timeSlots as $slot)
                            <option value="{{ $slot }}" {{ old('followup_time', $lead->followup_time ?? '') === $slot ? 'selected' : '' }}>{{ $slot }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Contact preference + notes --}}
        <div style="background:#fff;border:1px solid #eceef2;border-radius:10px;padding:18px;margin-bottom:20px;">
            <div style="font-size:11.5px;font-weight:700;color:#534AB7;text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px;">Contact Preference &amp; Notes</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Preferred Contact Method</label>
                    <select name="preferred_contact" style="{{ $inputStyle }}">
                        @foreach (['call' => 'Call', 'whatsapp' => 'WhatsApp', 'email' => 'Email'] as $val => $lbl)
                            <option value="{{ $val }}" {{ old('preferred_contact', $lead->preferred_contact ?? 'call') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Preferred Language</label>
                    <select name="language" style="{{ $inputStyle }}">
                        <option value="">— Any —</option>
                        @foreach ($languages as $lang)
                            <option value="{{ $lang }}" {{ old('language', $lead->language ?? '') === $lang ? 'selected' : '' }}>{{ $lang }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Notes</label>
                <textarea name="notes" rows="3" placeholder="Any context about this lead — what they asked, concerns, etc." style="{{ $inputStyle }}resize:vertical;">{{ old('notes', $lead->notes ?? '') }}</textarea>
            </div>
        </div>

        <div style="display:flex;gap:10px;">
            <button type="submit" style="background:#534AB7;color:#fff;border:none;border-radius:8px;padding:11px 26px;font-size:14px;font-weight:600;cursor:pointer;">
                {{ isset($lead) ? 'Save Changes' : 'Add to Pipeline' }}
            </button>
            <a href="{{ route('relationship.pipeline') }}" style="padding:11px 20px;font-size:14px;color:#6b7280;text-decoration:none;">Cancel</a>
        </div>
    </form>
</div>
@endsection
