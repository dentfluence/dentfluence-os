@extends('layouts.communication')
@push('communication-styles')
    @vite('resources/css/communication/prm.css')
@endpush
@section('title', isset($lead) ? 'Edit Lead' : 'Add New Lead')

@section('communication-content')

<div style="padding:10px 20px 10px 28px;border-bottom:1px solid rgba(0,0,0,0.06);background:#fff;">
    <a href="{{ route('prm.index') }}" style="font-size:12px;color:#5A5A56;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Pipeline
    </a>
</div>

<div style="max-width:720px;margin:0 auto;padding:28px 24px 60px;">

    {{-- Header --}}
    <div style="margin-bottom:28px;">
        <h1 style="font-family:'Cormorant Garamond',serif;font-size:26px;color:#1a0320;margin:0 0 4px;">
            {{ isset($lead) ? 'Edit Lead' : 'Add New Lead' }}
        </h1>
        <p style="color:#9a7aaa;font-size:13px;margin:0;">
            {{ isset($lead) ? 'Update lead details and pipeline stage.' : 'Capture a new patient lead into the pipeline.' }}
        </p>
    </div>

    @if($errors->any())
        <div style="background:#FEF2F2;border:1px solid #FCA5A5;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:#B91C1C;">
            @foreach($errors->all() as $error)
                <div>• {{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST"
          action="{{ isset($lead) ? route('prm.update-lead', $lead->id) : route('prm.store-lead') }}"
          style="display:flex;flex-direction:column;gap:0;">

        @csrf

        {{-- ── SECTION 1: Contact Info ─────────────────────────────────── --}}
        <div class="form-section">
            <div class="form-section-title">Contact Info</div>
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Name <span class="req">*</span></label>
                    <input type="text" name="name" class="form-input"
                           value="{{ old('name', $lead->name ?? '') }}"
                           placeholder="Patient's full name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone <span class="req">*</span></label>
                    <input type="tel" name="phone" class="form-input"
                           value="{{ old('phone', $lead->phone ?? '') }}"
                           placeholder="e.g. 9876543210" required>
                </div>
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Alternate Phone</label>
                    <input type="tel" name="alt_phone" class="form-input"
                           value="{{ old('alt_phone', $lead->alt_phone ?? '') }}"
                           placeholder="Optional">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input"
                           value="{{ old('email', $lead->email ?? '') }}"
                           placeholder="Optional">
                </div>
            </div>
        </div>

        {{-- ── SECTION 2: Lead Source (Phase 3) ───────────────────────── --}}
        <div class="form-section">
            <div class="form-section-title">Where did this lead come from?</div>
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Lead Source</label>
                    <select name="lead_source" class="form-select">
                        <option value="">— Select channel —</option>
                        @foreach($leadSources as $key => $label)
                            <option value="{{ $key }}"
                                {{ old('lead_source', $lead->lead_source ?? '') === $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Referred By <small style="color:#aaa;">(if Referral)</small></label>
                    <input type="text" name="referred_by" class="form-input"
                           value="{{ old('referred_by', $lead->referred_by ?? '') }}"
                           placeholder="Referring patient or doctor name">
                </div>
            </div>
        </div>

        {{-- ── SECTION 3: Treatment + Value ───────────────────────────── --}}
        <div class="form-section">
            <div class="form-section-title">Treatment Interest &amp; Value</div>
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Primary Treatment</label>
                    <select name="treatment" class="form-select">
                        <option value="">— Select treatment —</option>
                        @foreach($treatments as $t)
                            <option value="{{ $t }}"
                                {{ old('treatment', $lead->treatment ?? '') === $t ? 'selected' : '' }}>
                                {{ $t }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Secondary Treatment</label>
                    <select name="secondary_treatment" class="form-select">
                        <option value="">— None —</option>
                        @foreach($treatments as $t)
                            <option value="{{ $t }}"
                                {{ old('secondary_treatment', $lead->secondary_treatment ?? '') === $t ? 'selected' : '' }}>
                                {{ $t }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">
                        Estimated Value (Rs. )
                        <span style="font-size:11px;color:#aaa;font-weight:400;">— treatment cost estimate</span>
                    </label>
                    <div style="position:relative;">
                        <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#888;font-size:14px;">Rs. </span>
                        <input type="number" name="lead_value" class="form-input" style="padding-left:28px;"
                               value="{{ old('lead_value', $lead->lead_value ?? '') }}"
                               placeholder="e.g. 45000" min="0" step="500">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Urgency</label>
                    <div class="radio-group-row">
                        @foreach(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'] as $val => $lbl)
                            <label class="radio-pill">
                                <input type="radio" name="urgency" value="{{ $val }}"
                                    {{ old('urgency', $lead->urgency ?? 'medium') === $val ? 'checked' : '' }}>
                                <span>{{ $lbl }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ── SECTION 4: Pipeline ─────────────────────────────────────── --}}
        <div class="form-section">
            <div class="form-section-title">Pipeline Stage &amp; Assignment</div>
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Pipeline Stage</label>
                    <select name="stage" class="form-select">
                        @foreach($stages as $key => $info)
                            <option value="{{ $key }}"
                                {{ old('stage', $lead->stage ?? 'new_lead') === $key ? 'selected' : '' }}>
                                {{ $info['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Assigned To</label>
                    <select name="assigned_to" class="form-select">
                        <option value="">— Unassigned —</option>
                        @foreach($staff as $s)
                            <option value="{{ $s }}"
                                {{ old('assigned_to', $lead->assigned_to ?? '') === $s ? 'selected' : '' }}>
                                {{ $s }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Follow-up Date</label>
                    <input type="date" name="followup_date" class="form-input"
                           value="{{ old('followup_date', isset($lead->followup_date) ? $lead->followup_date->format('Y-m-d') : '') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Preferred Time</label>
                    <select name="followup_time" class="form-select">
                        <option value="">— Any time —</option>
                        @foreach($timeSlots as $slot)
                            <option value="{{ $slot }}"
                                {{ old('followup_time', $lead->followup_time ?? '') === $slot ? 'selected' : '' }}>
                                {{ $slot }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- ── SECTION 5: Preferred Contact + Notes ───────────────────── --}}
        <div class="form-section">
            <div class="form-section-title">Contact Preference &amp; Notes</div>
            <div class="form-row-2" style="margin-bottom:16px;">
                <div class="form-group">
                    <label class="form-label">Preferred Contact Method</label>
                    <div class="radio-group-row">
                        @foreach(['call' => 'Call', 'whatsapp' => 'WhatsApp', 'email' => 'Email'] as $val => $lbl)
                            <label class="radio-pill">
                                <input type="radio" name="preferred_contact" value="{{ $val }}"
                                    {{ old('preferred_contact', $lead->preferred_contact ?? 'call') === $val ? 'checked' : '' }}>
                                <span>{{ $lbl }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Preferred Language</label>
                    <select name="language" class="form-select">
                        <option value="">— Any —</option>
                        @foreach($languages as $lang)
                            <option value="{{ $lang }}"
                                {{ old('language', $lead->language ?? '') === $lang ? 'selected' : '' }}>
                                {{ $lang }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-textarea" rows="3"
                          placeholder="Any context about this lead — what they asked, concerns, etc.">{{ old('notes', $lead->notes ?? '') }}</textarea>
            </div>
        </div>

        {{-- ── ACTIONS ─────────────────────────────────────────────────── --}}
        <div style="display:flex;gap:12px;padding-top:8px;">
            <button type="submit" class="btn-primary-sm" style="padding:10px 28px;font-size:14px;">
                <i class="ti ti-check" aria-hidden="true"></i>
                {{ isset($lead) ? 'Save Changes' : 'Add to Pipeline' }}
            </button>
            <a href="{{ route('prm.index') }}" class="btn-ghost" style="padding:10px 20px;font-size:14px;text-decoration:none;">
                Cancel
            </a>
        </div>

    </form>
</div>

@endsection

@push('communication-scripts')
<style>
/* Add-lead form styles — inlined here for isolation */
.form-section {
    background: #fff;
    border: 1px solid #EDE8F3;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 16px;
}
.form-section-title {
    font-size: 12px;
    font-weight: 600;
    color: #7C5CA8;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 14px;
    padding-bottom: 8px;
    border-bottom: 1px solid #F0EAF7;
}
.form-row-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    margin-bottom: 14px;
}
.form-row-2:last-child { margin-bottom: 0; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-label { font-size: 13px; font-weight: 500; color: #3D2B50; }
.req { color: #E24B4A; }
.form-input,
.form-select,
.form-textarea {
    border: 1px solid #DDD6E8;
    border-radius: 7px;
    padding: 9px 12px;
    font-size: 13px;
    color: #1a0320;
    background: #FDFCFF;
    outline: none;
    transition: border-color 0.15s;
    font-family: inherit;
}
.form-input:focus,
.form-select:focus,
.form-textarea:focus { border-color: #8B5CF6; box-shadow: 0 0 0 3px rgba(139,92,246,0.08); }
.form-textarea { resize: vertical; }
.radio-group-row { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 2px; }
.radio-pill input { display: none; }
.radio-pill span {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 12px;
    border: 1px solid #DDD6E8;
    border-radius: 20px;
    font-size: 12px;
    cursor: pointer;
    color: #5A5A56;
    background: #FDFCFF;
    transition: all 0.15s;
    user-select: none;
}
.radio-pill input:checked + span {
    background: #EDE8F3;
    border-color: #8B5CF6;
    color: #5B21B6;
    font-weight: 500;
}
@media (max-width: 600px) {
    .form-row-2 { grid-template-columns: 1fr; }
}
</style>
@endpush
