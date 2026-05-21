@extends('layouts.communication')
@section('title', isset($lead) ? 'Edit Lead — PRM' : 'Add Lead — PRM')

@push('communication-styles')
    @vite('resources/css/communication/prm.css')
@endpush

@section('communication-content')
@section('communication-content')

<div class="prm-topbar">
    <x-communication.top-nav-tabs :counts="$navCounts" active="pipeline" />
</div>

    <div class="prm-brand">
        <div class="prm-logo"><i class="ti ti-tooth" aria-hidden="true"></i></div>
        <div>
            <div class="prm-brand-name">PRM</div>
            <div class="prm-brand-sub">Patient Relationship Manager</div>
        </div>
    </div>
    <x-communication.top-nav-tabs :counts="$navCounts" active="pipeline" />
    <div class="prm-topbar-right">
        <div class="prm-notif"><i class="ti ti-bell" aria-hidden="true"></i><span class="notif-badge">0</span></div>
        <div class="prm-user">
            <div class="prm-avatar">N</div>
            <div><div class="prm-user-name">Dr. Neha</div><div class="prm-user-role">Front Desk</div></div>
        </div>
    </div>
</div>

<div class="add-lead-page">

    {{-- Header --}}
    <div class="al-page-header">
        <div class="al-back-title">
            <a href="{{ route('prm.index') }}" class="back-btn">
                <i class="ti ti-arrow-left" aria-hidden="true"></i>
            </a>
            <div>
                <h1 class="page-title">{{ isset($lead) ? 'Edit Lead' : 'Add Lead' }}</h1>
                <p class="page-sub">{{ isset($lead) ? 'Update lead information and follow-up details' : 'Add a new lead to start follow-up' }}</p>
            </div>
        </div>
        @if(!isset($lead))
        <a href="/communication/call-manager" class="btn-outline-sm">
            <i class="ti ti-phone" aria-hidden="true"></i> Add from Call
        </a>
        @else
        <a href="{{ route('prm.lead-detail', $lead['id']) }}" class="btn-outline-sm">
            <i class="ti ti-eye" aria-hidden="true"></i> View Lead Details
        </a>
        @endif
    </div>

    <form method="POST"
          action="{{ isset($lead) ? route('prm.update-lead', $lead['id']) : route('prm.store-lead') }}"
          id="leadForm">
        @csrf

        <div class="al-form-grid">

            {{-- ── LEFT COLUMN ──────────────────────────── --}}
            <div class="al-col">

                {{-- Section 1: Lead Type --}}
                <div class="form-section">
                    <div class="section-title">1. Lead Type</div>
                    <div class="lead-type-cards">
                        <label class="type-card {{ (!isset($lead) || $lead['stage'] === 'new_lead') ? 'selected' : '' }}">
                            <input type="radio" name="lead_type" value="new_patient"
                                   {{ (!isset($lead) || $lead['stage'] !== 'existing') ? 'checked' : '' }}>
                            <div class="type-card-icon"><i class="ti ti-user-plus" aria-hidden="true"></i></div>
                            <div>
                                <div class="type-card-label">New Lead</div>
                                <div class="type-card-sub">Person is new to the clinic</div>
                            </div>
                        </label>
                        <label class="type-card">
                            <input type="radio" name="lead_type" value="existing_patient">
                            <div class="type-card-icon"><i class="ti ti-user-check" aria-hidden="true"></i></div>
                            <div>
                                <div class="type-card-label">Existing Patient</div>
                                <div class="type-card-sub">Person is already a patient</div>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- Section 2: Basic Information --}}
                <div class="form-section">
                    <div class="section-title">2. Basic Information</div>
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="req">*</span></label>
                        <input type="text" name="name" class="form-input"
                               placeholder="Enter full name"
                               value="{{ $lead['name'] ?? '' }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mobile Number <span class="req">*</span></label>
                        <div class="phone-input-wrap">
                            <select name="country_code" class="country-code-select">
                                <option value="+91" selected>+91</option>
                                <option value="+1">+1</option>
                                <option value="+44">+44</option>
                            </select>
                            <input type="tel" name="phone" class="form-input phone-field"
                                   placeholder="Enter mobile number"
                                   value="{{ isset($lead) ? preg_replace('/\s+/', '', $lead['phone']) : '' }}" required>
                            <button type="button" class="phone-call-btn" title="Call this number">
                                <i class="ti ti-phone" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Alternate Number</label>
                        <div class="phone-input-wrap">
                            <select name="alt_country_code" class="country-code-select">
                                <option value="+91" selected>+91</option>
                            </select>
                            <input type="tel" name="alt_phone" class="form-input phone-field"
                                   placeholder="Enter alternate number"
                                   value="{{ $lead['alt_phone'] ?? '' }}">
                            <button type="button" class="phone-call-btn">
                                <i class="ti ti-phone" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Preferred Contact</label>
                        <div class="toggle-group">
                            <label class="toggle-btn {{ (!isset($lead) || $lead['preferred_contact'] === 'call') ? 'active' : '' }}">
                                <input type="radio" name="preferred_contact" value="call"
                                       {{ (!isset($lead) || $lead['preferred_contact'] === 'call') ? 'checked' : '' }}>
                                <i class="ti ti-phone" aria-hidden="true"></i> Call
                            </label>
                            <label class="toggle-btn {{ (isset($lead) && $lead['preferred_contact'] === 'whatsapp') ? 'active' : '' }}">
                                <input type="radio" name="preferred_contact" value="whatsapp"
                                       {{ (isset($lead) && $lead['preferred_contact'] === 'whatsapp') ? 'checked' : '' }}>
                                <i class="ti ti-brand-whatsapp" aria-hidden="true"></i> WhatsApp
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email ID</label>
                        <input type="email" name="email" class="form-input"
                               placeholder="Enter email address"
                               value="{{ $lead['email'] ?? '' }}">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="dob" class="form-input"
                                   value="{{ $lead['dob'] ?? '' }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="">Select gender</option>
                                <option value="Male"   {{ (isset($lead) && $lead['gender'] === 'Male')   ? 'selected' : '' }}>Male</option>
                                <option value="Female" {{ (isset($lead) && $lead['gender'] === 'Female') ? 'selected' : '' }}>Female</option>
                                <option value="Other"  {{ (isset($lead) && $lead['gender'] === 'Other')  ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Section 6: Follow-up & Assignment --}}
                <div class="form-section">
                    <div class="section-title">6. Follow-up & Assignment</div>
                    <div class="form-group">
                        <label class="form-label">Assign To <span class="req">*</span></label>
                        <select name="assigned_to" class="form-select" required>
                            @foreach($staff as $member)
                                <option value="{{ $member['id'] }}"
                                    {{ (isset($lead) && $lead['assigned_to'] === $member['name']) ? 'selected' : '' }}>
                                    {{ $member['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Follow-up Date <span class="req">*</span></label>
                            <input type="date" name="followup_date" class="form-input"
                                   value="{{ $lead['followup_date'] ?? date('Y-m-d', strtotime('+1 day')) }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Follow-up Time <span class="req">*</span></label>
                            <input type="time" name="followup_time" class="form-input"
                                   value="{{ isset($lead) ? \Carbon\Carbon::parse($lead['followup_time'])->format('H:i') : '11:00' }}" required>
                        </div>
                    </div>
                    <div class="info-note">
                        <i class="ti ti-info-circle" aria-hidden="true"></i>
                        Lead will appear in the communication list on the selected follow-up date.
                    </div>
                </div>

            </div>

            {{-- ── RIGHT COLUMN ─────────────────────────── --}}
            <div class="al-col">

                {{-- Section 3: Treatment Interest --}}
                <div class="form-section">
                    <div class="section-title">3. Treatment Interest</div>
                    <div class="form-group">
                        <label class="form-label">Primary Interest <span class="req">*</span></label>
                        <select name="treatment" class="form-select" required>
                            <option value="">Select treatment</option>
                            @foreach($treatments as $t)
                                <option value="{{ $t }}"
                                    {{ (isset($lead) && $lead['treatment'] === $t) ? 'selected' : '' }}>
                                    {{ $t }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Secondary Interest (Optional)</label>
                        <select name="secondary_treatment" class="form-select">
                            <option value="">Select treatment</option>
                            @foreach($treatments as $t)
                                <option value="{{ $t }}"
                                    {{ (isset($lead) && ($lead['secondary_treatment'] ?? '') === $t) ? 'selected' : '' }}>
                                    {{ $t }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Section 4: Source --}}
                <div class="form-section">
                    <div class="section-title">4. Source</div>
                    <div class="form-group">
                        <label class="form-label">Lead Source <span class="req">*</span></label>
                        <select name="source" class="form-select" required>
                            <option value="">Select source</option>
                            @foreach($sources as $src)
                                <option value="{{ $src }}"
                                    {{ (isset($lead) && $lead['source'] === $src) ? 'selected' : '' }}>
                                    {{ $src }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Referred By (Optional)</label>
                        <input type="text" name="referred_by" class="form-input"
                               placeholder="Enter name or source"
                               value="{{ $lead['referred_by'] ?? '' }}">
                    </div>
                </div>

                {{-- Section 5: Lead Details --}}
                <div class="form-section">
                    <div class="section-title">5. Lead Details</div>
                    <div class="form-group">
                        <label class="form-label">Urgency</label>
                        <div class="urgency-group">
                            @foreach(['low' => ['Low','#3B6D11'], 'medium' => ['Medium','#854F0B'], 'high' => ['High','#A32D2D']] as $val => [$label, $color])
                            <label class="urgency-btn {{ (isset($lead) && $lead['urgency'] === $val) || (!isset($lead) && $val === 'medium') ? 'active' : '' }}"
                                   style="{{ ((isset($lead) && $lead['urgency'] === $val) || (!isset($lead) && $val === 'medium')) ? 'border-color:'.$color.';color:'.$color : '' }}">
                                <input type="radio" name="urgency" value="{{ $val }}"
                                       {{ (isset($lead) && $lead['urgency'] === $val) || (!isset($lead) && $val === 'medium') ? 'checked' : '' }}>
                                <span class="urgency-dot-sm" style="background:{{ $color }}"></span>
                                {{ $label }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Preferred Time to Contact</label>
                        <select name="preferred_time" class="form-select">
                            <option>Select time slot</option>
                            <option>Morning (9 AM - 1 PM)</option>
                            <option>Afternoon (1 PM - 5 PM)</option>
                            <option>Evening (5 PM - 8 PM)</option>
                            <option>Anytime</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">How did they contact us?</label>
                        <select name="contact_method" class="form-select">
                            <option>Select option</option>
                            <option>Called the clinic</option>
                            <option>WhatsApp message</option>
                            <option>Walked in</option>
                            <option>Website form</option>
                            <option>Instagram DM</option>
                            <option>Facebook message</option>
                        </select>
                    </div>
                </div>

                {{-- Section 7: Notes --}}
                <div class="form-section">
                    <div class="section-title">7. Notes</div>
                    <div class="form-group">
                        <label class="form-label">Initial Note (Optional)</label>
                        <textarea name="notes" class="form-textarea" rows="4"
                                  placeholder="Enter lead description, conversation summary, or any important notes..."
                                  maxlength="500">{{ $lead['notes'] ?? '' }}</textarea>
                        <div class="char-count"><span id="noteCount">{{ strlen($lead['notes'] ?? '') }}</span> / 500</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Add Tags (Optional)</label>
                        <div class="tags-input-wrap">
                            @if(!empty($lead['tags']))
                                @foreach($lead['tags'] as $tag)
                                    <span class="tag-chip">{{ $tag }} <button type="button" onclick="removeTag(this)">×</button></span>
                                @endforeach
                            @endif
                            <input type="text" class="tags-text-input" placeholder="Type and press Enter..."
                                   onkeydown="addTag(event)">
                        </div>
                        <input type="hidden" name="tags" id="tagsInput"
                               value="{{ json_encode($lead['tags'] ?? []) }}">
                    </div>
                </div>

            </div>

        </div>

        {{-- Section 8: Additional Information --}}
        <div class="form-section al-full-section">
            <div class="section-title">8. Additional Information (Optional)</div>
            <div class="al-additional-grid">
                <div class="form-group">
                    <label class="form-label">Occupation</label>
                    <input type="text" name="occupation" class="form-input"
                           placeholder="Enter occupation"
                           value="{{ $lead['occupation'] ?? '' }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-input"
                           placeholder="Enter city / area"
                           value="{{ $lead['location'] ?? '' }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Language</label>
                    <select name="language" class="form-select">
                        <option value="">Select language</option>
                        @foreach(['English','Hindi','Marathi','Gujarati','Tamil','Telugu','Kannada','Bengali','Malayalam','Other'] as $lang)
                            <option value="{{ $lang }}"
                                {{ (isset($lead) && $lead['language'] === $lang) ? 'selected' : '' }}>
                                {{ $lang }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Form Footer --}}
        <div class="form-footer">
            <a href="{{ route('prm.index') }}" class="btn-ghost">Cancel</a>
            <button type="submit" name="action" value="save_another" class="btn-outline-sm">
                Save & Add Another
            </button>
            <button type="submit" name="action" value="save" class="btn-primary-sm">
                <i class="ti ti-check" aria-hidden="true"></i>
                {{ isset($lead) ? 'Save Changes' : 'Save Lead' }}
            </button>
            @if(isset($lead))
            <button type="button" class="btn-danger-sm" onclick="confirmDeleteLead({{ $lead['id'] }})">
                <i class="ti ti-trash" aria-hidden="true"></i> Delete Lead
            </button>
            @endif
        </div>

    </form>

</div>

@endsection

@push('scripts')
<script>
document.querySelector('textarea[name="notes"]')?.addEventListener('input', function() {
    document.getElementById('noteCount').textContent = this.value.length;
});
document.querySelectorAll('.toggle-btn input, .urgency-btn input').forEach(function(radio) {
    radio.addEventListener('change', function() {
        this.closest('.toggle-group, .urgency-group')
            .querySelectorAll('label').forEach(l => l.classList.remove('active'));
        this.closest('label').classList.add('active');
    });
});
document.querySelectorAll('.type-card input').forEach(function(radio) {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.type-card').forEach(c => c.classList.remove('selected'));
        this.closest('.type-card').classList.add('selected');
    });
});
function addTag(e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const val = e.target.value.trim();
    if (!val) return;
    const wrap = e.target.closest('.tags-input-wrap');
    const chip = document.createElement('span');
    chip.className = 'tag-chip';
    chip.innerHTML = val + ' <button type="button" onclick="removeTag(this)">×</button>';
    wrap.insertBefore(chip, e.target);
    e.target.value = '';
    updateTagsInput();
}
function removeTag(btn) {
    btn.closest('.tag-chip').remove();
    updateTagsInput();
}
function updateTagsInput() {
    const tags = [...document.querySelectorAll('.tag-chip')].map(c => c.textContent.trim().replace('×','').trim());
    document.getElementById('tagsInput').value = JSON.stringify(tags);
}
</script>
@endpush
