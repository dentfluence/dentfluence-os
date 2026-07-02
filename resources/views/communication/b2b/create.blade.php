{{--
    B2B Comm — Add New (Staff Entry Form)
    Phase 4 · Dentfluence Communication OS

    UI RULE: Dead-simple for 12th-pass staff.
    - Max 5 fields visible at once
    - Step-by-step: Who → What → Notes
    - Dropdowns over free text
    - One big CTA button
--}}
@extends('layouts.communication')

@push('communication-styles')
<style>
/* ── Step form ── */
.b2b-form-wrap { max-width:560px; margin:32px auto; padding:0 16px 48px; }
.b2b-form-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; }
.b2b-form-header { background:#6a0f70; color:#fff; padding:18px 24px; }
.b2b-form-header h2 { margin:0; font-size:17px; font-weight:700; }
.b2b-form-header p  { margin:4px 0 0; font-size:13px; opacity:.8; }

.b2b-step { padding:24px; border-bottom:1px solid #f1f5f9; }
.b2b-step:last-child { border-bottom:none; }
.b2b-step-label { font-size:11px; font-weight:700; color:#6a0f70; text-transform:uppercase; letter-spacing:.07em; margin-bottom:14px; }

.b2b-field { margin-bottom:16px; }
.b2b-field:last-child { margin-bottom:0; }
.b2b-field label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:5px; }
.b2b-field select,
.b2b-field input,
.b2b-field textarea {
    width:100%; padding:10px 12px; font-size:14px;
    border:1px solid #d1d5db; border-radius:8px;
    background:#fff; outline:none; box-sizing:border-box;
    transition:border-color .15s;
}
.b2b-field select:focus,
.b2b-field input:focus,
.b2b-field textarea:focus { border-color:#6a0f70; box-shadow:0 0 0 3px rgba(106,15,112,.08); }
.b2b-field textarea { resize:vertical; min-height:80px; }
.b2b-field .helper { font-size:11px; color:#94a3b8; margin-top:4px; }

/* Contact type selector — big cards */
.b2b-type-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
.b2b-type-card { border:2px solid #e2e8f0; border-radius:10px; padding:12px 8px; text-align:center; cursor:pointer; transition:all .15s; }
.b2b-type-card:hover { border-color:#6a0f70; background:#faf5ff; }
.b2b-type-card input[type=radio] { display:none; }
.b2b-type-card.selected { border-color:#6a0f70; background:#faf5ff; }
.b2b-type-card .icon { font-size:22px; margin-bottom:4px; }
.b2b-type-card .label { font-size:12px; font-weight:600; color:#374151; }

/* Dynamic contact row */
#contact-row { display:none; }

/* Submit */
.b2b-submit-row { padding:20px 24px; background:#f8fafc; }
.b2b-submit-btn { width:100%; padding:14px; font-size:15px; font-weight:700; background:#6a0f70; color:#fff; border:none; border-radius:10px; cursor:pointer; transition:background .15s; }
.b2b-submit-btn:hover { background:#4e0b52; }

.b2b-error { background:#fff1f2; border:1px solid #fecaca; color:#dc2626; padding:10px 14px; border-radius:8px; font-size:13px; margin-bottom:16px; }

/* Lab case row — hidden until lab type selected */
#lab-case-row { display:none; }
</style>
@endpush

@section('communication-content')
<div class="b2b-form-wrap">

    @if($errors->any())
    <div class="b2b-error">
        Please fix the following: {{ $errors->first() }}
    </div>
    @endif

    <form method="POST" action="{{ route('communication.b2b.store') }}" id="b2bForm">
        @csrf
        <div class="b2b-form-card">

            {{-- Header --}}
            <div class="b2b-form-header">
                <h2>Log External Communication</h2>
                <p>Vendor, Lab, or Consultant</p>
            </div>

            {{-- Step 1: Who is this with? --}}
            <div class="b2b-step">
                <div class="b2b-step-label">Step 1 — Who is this with?</div>

                {{-- Contact type — big card selection --}}
                <div class="b2b-field">
                    <label>Type of contact</label>
                    <div class="b2b-type-grid">
                        <label class="b2b-type-card {{ old('contact_type', $prefill['contact_type']) == 'lab' ? 'selected' : '' }}" id="card-lab">
                            <input type="radio" name="contact_type" value="lab"
                                {{ old('contact_type', $prefill['contact_type']) == 'lab' ? 'checked' : '' }}>
                            <div class="label">Lab</div>
                        </label>
                        <label class="b2b-type-card {{ old('contact_type', $prefill['contact_type']) == 'vendor' ? 'selected' : '' }}" id="card-vendor">
                            <input type="radio" name="contact_type" value="vendor"
                                {{ old('contact_type', $prefill['contact_type']) == 'vendor' ? 'checked' : '' }}>
                            <div class="label">Vendor</div>
                        </label>
                        <label class="b2b-type-card {{ old('contact_type', $prefill['contact_type']) == 'consultant' ? 'selected' : '' }}" id="card-consultant">
                            <input type="radio" name="contact_type" value="consultant"
                                {{ old('contact_type', $prefill['contact_type']) == 'consultant' ? 'checked' : '' }}>
                            <div class="label">Consultant</div>
                        </label>
                    </div>
                </div>

                {{-- Contact dropdown — changes based on type --}}
                <div class="b2b-field" id="contact-row">
                    <label id="contact-label">Select</label>
                    {{-- Lab vendors --}}
                    <select name="contact_id" id="contact-select-lab" style="display:none">
                        <option value="">— Select Lab —</option>
                        @foreach($labVendors as $lv)
                        <option value="{{ $lv->id }}" {{ old('contact_id', $prefill['contact_id']) == $lv->id ? 'selected' : '' }}>
                            {{ $lv->name }}
                        </option>
                        @endforeach
                    </select>
                    {{-- Finance vendors --}}
                    <select name="contact_id" id="contact-select-vendor" style="display:none">
                        <option value="">— Select Vendor —</option>
                        @foreach($financeVendors as $fv)
                        <option value="{{ $fv->id }}" {{ old('contact_id', $prefill['contact_id']) == $fv->id ? 'selected' : '' }}>
                            {{ $fv->vendor_name }}
                        </option>
                        @endforeach
                    </select>
                    {{-- Consultant (free text since no consultant table yet) --}}
                    <input type="text" name="contact_id" id="contact-input-consultant"
                        placeholder="Consultant name / DR ID" style="display:none"
                        value="{{ old('contact_id', $prefill['contact_type'] == 'consultant' ? $prefill['contact_id'] : '') }}">
                </div>

                <div class="b2b-field">
                    <label>Contact person name <span style="color:#ef4444">*</span></label>
                    <input type="text" name="person_name" placeholder="e.g. Ravi from Dental Lab"
                        value="{{ old('person_name', $labCase?->labVendor?->contact_person ?? '') }}" required>
                </div>

                <div class="b2b-field">
                    <label>Phone number</label>
                    <input type="text" name="phone" placeholder="10-digit number"
                        value="{{ old('phone', $labCase?->labVendor?->phone ?? '') }}">
                </div>
            </div>

            {{-- Step 2: What is this about? --}}
            <div class="b2b-step">
                <div class="b2b-step-label">Step 2 — What is this about?</div>

                <div class="b2b-field">
                    <label>Purpose <span style="color:#ef4444">*</span></label>
                    <select name="b2b_subtype" id="b2b-subtype" required>
                        <option value="">— Select purpose —</option>
                        @foreach(\App\Models\CommunicationQueue::B2B_SUBTYPES as $val => $label)
                        <option value="{{ $val }}" {{ old('b2b_subtype', $prefill['b2b_subtype']) == $val ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Lab case link — shown only when subtype = lab_case_status --}}
                <div class="b2b-field" id="lab-case-row">
                    <label>Which lab case?</label>
                    <select name="lab_case_id" id="lab-case-select">
                        <option value="">— Select lab case —</option>
                        @if($labCase)
                        <option value="{{ $labCase->id }}" selected>
                            #{{ $labCase->case_number }} — {{ \App\Models\LabCase::STATUS_LABELS[$labCase->status] }}
                        </option>
                        @endif
                    </select>
                    <div class="helper">Only shows open cases for the selected lab.</div>
                </div>

                <div class="b2b-field">
                    <label>How did you contact them?</label>
                    <select name="channel">
                        <option value="call"     {{ old('channel') == 'call'     ? 'selected' : '' }}>Called them</option>
                        <option value="whatsapp" {{ old('channel') == 'whatsapp' ? 'selected' : '' }}>WhatsApp</option>
                        <option value="email"    {{ old('channel') == 'email'    ? 'selected' : '' }}>Email</option>
                        <option value="walk_in"  {{ old('channel') == 'walk_in'  ? 'selected' : '' }}>They came in</option>
                        <option value="other"    {{ old('channel') == 'other'    ? 'selected' : '' }}>Other</option>
                    </select>
                </div>
            </div>

            {{-- Step 3: Notes + follow-up --}}
            <div class="b2b-step">
                <div class="b2b-step-label">Step 3 — Notes</div>

                <div class="b2b-field">
                    <label>What was discussed?</label>
                    <textarea name="note" placeholder="Brief summary of the conversation...">{{ old('note') }}</textarea>
                </div>

                <div class="b2b-field">
                    <label>Follow-up date (optional)</label>
                    <input type="date" name="follow_up_date" value="{{ old('follow_up_date') }}">
                    <div class="helper">Leave blank if no follow-up needed.</div>
                </div>

                <div class="b2b-field">
                    <label>Priority</label>
                    <select name="priority">
                        <option value="medium" {{ old('priority') == 'medium' ? 'selected' : '' }}>Normal</option>
                        <option value="high"   {{ old('priority') == 'high'   ? 'selected' : '' }}>Urgent</option>
                        <option value="low"    {{ old('priority') == 'low'    ? 'selected' : '' }}>Low</option>
                    </select>
                </div>
            </div>

            {{-- Submit --}}
            <div class="b2b-submit-row">
                <button type="submit" class="b2b-submit-btn">✓ Save Communication</button>
            </div>

        </div>{{-- /card --}}
    </form>

    <div style="text-align:center;margin-top:14px;">
        <a href="{{ route('communication.b2b.index') }}" style="font-size:13px;color:#94a3b8;">← Back to B2B Inbox</a>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    const typeCards    = document.querySelectorAll('.b2b-type-card');
    const contactRow   = document.getElementById('contact-row');
    const contactLabel = document.getElementById('contact-label');
    const labSelect    = document.getElementById('contact-select-lab');
    const vendorSelect = document.getElementById('contact-select-vendor');
    const consultInput = document.getElementById('contact-input-consultant');
    const subtypeSelect= document.getElementById('b2b-subtype');
    const labCaseRow   = document.getElementById('lab-case-row');
    const labCaseSel   = document.getElementById('lab-case-select');

    const SUBTYPE_LABELS = {
        lab:        'Which lab?',
        vendor:     'Which vendor?',
        consultant: "Consultant's name or ID"
    };

    function showContactFor(type) {
        contactRow.style.display = 'block';
        labSelect.style.display    = 'none';
        vendorSelect.style.display = 'none';
        consultInput.style.display = 'none';
        labSelect.disabled    = true;
        vendorSelect.disabled = true;
        consultInput.disabled = true;

        contactLabel.textContent = SUBTYPE_LABELS[type] || 'Select contact';

        if (type === 'lab')        { labSelect.style.display    = 'block'; labSelect.disabled    = false; }
        if (type === 'vendor')     { vendorSelect.style.display = 'block'; vendorSelect.disabled = false; }
        if (type === 'consultant') { consultInput.style.display = 'block'; consultInput.disabled = false; }
    }

    // Handle type card clicks
    typeCards.forEach(function(card) {
        card.addEventListener('click', function() {
            typeCards.forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            const type = card.querySelector('input[type=radio]').value;
            showContactFor(type);
            checkLabCaseRow();
        });
    });

    // Show/hide lab case row
    function checkLabCaseRow() {
        const subtype     = subtypeSelect.value;
        const typeChecked = document.querySelector('input[name=contact_type]:checked');
        const type        = typeChecked ? typeChecked.value : '';

        if (subtype === 'lab_case_status' && type === 'lab') {
            labCaseRow.style.display = 'block';
            // Reload open cases for the selected lab
            const labId = labSelect.value;
            if (labId) fetchLabCases(labId);
        } else {
            labCaseRow.style.display = 'none';
        }
    }

    subtypeSelect.addEventListener('change', checkLabCaseRow);

    // When lab vendor changes, reload open cases
    labSelect.addEventListener('change', function() {
        if (subtypeSelect.value === 'lab_case_status') {
            fetchLabCases(this.value);
        }
    });

    function fetchLabCases(labVendorId) {
        if (!labVendorId) return;
        fetch('{{ route('communication.b2b.ajax.lab-cases') }}?lab_vendor_id=' + labVendorId)
            .then(r => r.json())
            .then(function(cases) {
                labCaseSel.innerHTML = '<option value="">— Select lab case —</option>';
                cases.forEach(function(c) {
                    labCaseSel.innerHTML += '<option value="' + c.id + '">' + c.label + '</option>';
                });
            });
    }

    // Init from prefill
    const preselectedType = document.querySelector('input[name=contact_type]:checked');
    if (preselectedType) {
        showContactFor(preselectedType.value);
    }
    checkLabCaseRow();

})();
</script>
@endpush
