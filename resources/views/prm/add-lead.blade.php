@extends('layouts.communication')

@section('title', 'Add Lead')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/communication/prm.css') }}">
@endpush

@section('content')
<div class="prm-form-page">

    {{-- ── Page Header ──────────────────────────────────────────────── --}}
    <div class="prm-form-page__header">
        <a href="{{ route('communication.prm.index') }}" class="prm-form-page__back">
            <i class="ti ti-arrow-left"></i>
        </a>
        <div>
            <h1 class="prm-form-page__title">Add Lead</h1>
            <p class="prm-form-page__subtitle">Add a new lead to start follow-up</p>
        </div>
        <button class="prm-btn prm-btn--outline prm-btn--add-from-call" style="margin-left:auto">
            <i class="ti ti-phone"></i> Add from Call
        </button>
    </div>

    <form action="{{ route('communication.prm.leads.store') }}" method="POST" id="addLeadForm">
        @csrf
        <div class="prm-form-grid">

            {{-- ══════════════════ LEFT COLUMN ══════════════════ --}}
            <div class="prm-form-col">

                {{-- 1. Lead Type --}}
                <div class="prm-form-section">
                    <h3 class="prm-form-section__title">1. Lead Type</h3>
                    @foreach([
                        ['value'=>'new',      'icon'=>'ti-user-plus', 'label'=>'New Lead',        'desc'=>'Person is new to the clinic'],
                        ['value'=>'existing', 'icon'=>'ti-user',      'label'=>'Existing Patient', 'desc'=>'Person is already a patient'],
                    ] as $type)
                    <label class="prm-lead-type-card {{ $loop->first ? 'prm-lead-type-card--selected' : '' }}"
                           id="leadType{{ ucfirst($type['value']) }}">
                        <input type="radio" name="lead_type" value="{{ $type['value'] }}"
                               {{ $loop->first ? 'checked' : '' }} class="prm-lead-type-card__radio">
                        <div class="prm-lead-type-card__icon">
                            <i class="ti {{ $type['icon'] }}"></i>
                        </div>
                        <div>
                            <div class="prm-lead-type-card__label">{{ $type['label'] }}</div>
                            <div class="prm-lead-type-card__desc">{{ $type['desc'] }}</div>
                        </div>
                    </label>
                    @endforeach
                </div>

                {{-- 2. Basic Information --}}
                <div class="prm-form-section">
                    <h3 class="prm-form-section__title">2. Basic Information</h3>

                    <div class="prm-field">
                        <label class="prm-label">Full Name <span class="prm-required">*</span></label>
                        <input type="text" name="name" class="prm-input" placeholder="Enter full name"
                               value="{{ old('name') }}" required>
                        @error('name')<span class="prm-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="prm-field">
                        <label class="prm-label">Mobile Number <span class="prm-required">*</span></label>
                        <div class="prm-phone-row">
                            <div class="prm-country-code">+91 <i class="ti ti-chevron-down"></i></div>
                            <input type="tel" name="phone" class="prm-input" placeholder="Enter mobile number"
                                   value="{{ old('phone') }}" required>
                            <button type="button" class="prm-phone-dial-btn">
                                <i class="ti ti-phone" style="color:#534AB7"></i>
                            </button>
                        </div>
                    </div>

                    <div class="prm-field">
                        <label class="prm-label">Alternate Number</label>
                        <div class="prm-phone-row">
                            <div class="prm-country-code">+91 <i class="ti ti-chevron-down"></i></div>
                            <input type="tel" name="alt_phone" class="prm-input" placeholder="Enter alternate number"
                                   value="{{ old('alt_phone') }}">
                            <button type="button" class="prm-phone-dial-btn">
                                <i class="ti ti-phone" style="color:#534AB7"></i>
                            </button>
                        </div>
                    </div>

                    <div class="prm-field">
                        <label class="prm-label">Preferred Contact</label>
                        <div class="prm-pref-contact-row">
                            @foreach(['call'=>'ti-phone', 'whatsapp'=>'ti-brand-whatsapp'] as $val => $icon)
                            <label class="prm-pref-contact {{ $val === 'call' ? 'prm-pref-contact--selected' : '' }}">
                                <input type="radio" name="preferred_contact" value="{{ $val }}"
                                       {{ $val === 'call' ? 'checked' : '' }}>
                                <i class="ti {{ $icon }}"></i>
                                {{ ucfirst($val) === 'Whatsapp' ? 'WhatsApp' : ucfirst($val) }}
                            </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="prm-field">
                        <label class="prm-label">Email ID</label>
                        <input type="email" name="email" class="prm-input" placeholder="Enter email address"
                               value="{{ old('email') }}">
                    </div>

                    <div class="prm-field-row">
                        <div class="prm-field">
                            <label class="prm-label">Date of Birth</label>
                            <div class="prm-input-icon-wrap">
                                <input type="date" name="dob" class="prm-input" value="{{ old('dob') }}">
                                <i class="ti ti-calendar prm-input-icon"></i>
                            </div>
                        </div>
                        <div class="prm-field">
                            <label class="prm-label">Gender</label>
                            <select name="gender" class="prm-select">
                                <option value="">Select gender</option>
                                @foreach(['Male','Female','Other'] as $g)
                                <option value="{{ $g }}" {{ old('gender') == $g ? 'selected' : '' }}>{{ $g }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

            </div>

            {{-- ══════════════════ RIGHT COLUMN ══════════════════ --}}
            <div class="prm-form-col">

                {{-- 3. Treatment Interest --}}
                <div class="prm-form-section">
                    <h3 class="prm-form-section__title">3. Treatment Interest</h3>
                    <div class="prm-field">
                        <label class="prm-label">Primary Interest <span class="prm-required">*</span></label>
                        <select name="primary_interest" class="prm-select" required>
                            <option value="">Select treatment</option>
                            @foreach($treatments as $t)
                            <option value="{{ $t }}" {{ old('primary_interest') == $t ? 'selected' : '' }}>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="prm-field">
                        <label class="prm-label">Secondary Interest <span class="prm-optional">(Optional)</span></label>
                        <select name="secondary_interest" class="prm-select">
                            <option value="">Select treatment</option>
                            @foreach($treatments as $t)
                            <option value="{{ $t }}" {{ old('secondary_interest') == $t ? 'selected' : '' }}>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- 4. Source --}}
                <div class="prm-form-section">
                    <h3 class="prm-form-section__title">4. Source</h3>
                    <div class="prm-field">
                        <label class="prm-label">Lead Source <span class="prm-required">*</span></label>
                        <select name="source" class="prm-select" required>
                            <option value="">Select source</option>
                            @foreach($sources as $s)
                            <option value="{{ $s }}" {{ old('source') == $s ? 'selected' : '' }}>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="prm-field">
                        <label class="prm-label">Referred By <span class="prm-optional">(Optional)</span></label>
                        <input type="text" name="referred_by" class="prm-input"
                               placeholder="Enter name or source" value="{{ old('referred_by') }}">
                    </div>
                </div>

                {{-- 5. Lead Details --}}
                <div class="prm-form-section">
                    <h3 class="prm-form-section__title">5. Lead Details</h3>
                    <div class="prm-field">
                        <label class="prm-label">Urgency</label>
                        <div class="prm-urgency-row">
                            @foreach([
                                ['val'=>'low',    'label'=>'Low',    'color'=>'#1D9E75'],
                                ['val'=>'medium', 'label'=>'Medium', 'color'=>'#EF9F27'],
                                ['val'=>'high',   'label'=>'High',   'color'=>'#E24B4A'],
                            ] as $u)
                            <label class="prm-urgency-btn {{ $u['val'] === 'medium' ? 'prm-urgency-btn--selected' : '' }}"
                                   style="--u-color:{{ $u['color'] }}">
                                <input type="radio" name="urgency" value="{{ $u['val'] }}"
                                       {{ old('urgency', 'medium') === $u['val'] ? 'checked' : '' }}>
                                <span class="prm-urgency-dot" style="background:{{ $u['color'] }}"></span>
                                {{ $u['label'] }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="prm-field">
                        <label class="prm-label">Preferred Time to Contact</label>
                        <select name="preferred_time" class="prm-select">
                            <option value="">Select time slot</option>
                            @foreach($timeSlots as $ts)
                            <option value="{{ $ts }}" {{ old('preferred_time') == $ts ? 'selected' : '' }}>{{ $ts }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="prm-field">
                        <label class="prm-label">How did they contact us?</label>
                        <select name="contact_how" class="prm-select">
                            <option value="">Select option</option>
                            @foreach(['Called the clinic','Walked in','WhatsApp message','Instagram DM','Google form','Website inquiry','Facebook message'] as $h)
                            <option value="{{ $h }}" {{ old('contact_how') == $h ? 'selected' : '' }}>{{ $h }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

            </div>
        </div>

        {{-- ── Follow-up & Notes (full-width) ────────────────────────── --}}
        <div class="prm-form-grid prm-form-grid--bottom">

            <div class="prm-form-col">
                <div class="prm-form-section">
                    <h3 class="prm-form-section__title">6. Follow-up &amp; Assignment</h3>
                    <div class="prm-field">
                        <label class="prm-label">Assign To <span class="prm-required">*</span></label>
                        <select name="assigned_to" class="prm-select" required>
                            @foreach($staff as $s)
                            <option value="{{ $s }}" {{ old('assigned_to', $staff[0]) == $s ? 'selected' : '' }}>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="prm-field-row">
                        <div class="prm-field">
                            <label class="prm-label">Follow-up Date <span class="prm-required">*</span></label>
                            <div class="prm-input-icon-wrap">
                                <input type="date" name="followup_date" class="prm-input"
                                       value="{{ old('followup_date', date('Y-m-d')) }}" required>
                                <i class="ti ti-calendar prm-input-icon"></i>
                            </div>
                        </div>
                        <div class="prm-field">
                            <label class="prm-label">Follow-up Time <span class="prm-required">*</span></label>
                            <div class="prm-input-icon-wrap">
                                <input type="time" name="followup_time" class="prm-input"
                                       value="{{ old('followup_time', '11:00') }}" required>
                                <i class="ti ti-clock prm-input-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="prm-info-banner">
                        <i class="ti ti-info-circle"></i>
                        Lead will appear in the communication list on the selected follow-up date.
                    </div>
                </div>
            </div>

            <div class="prm-form-col">
                <div class="prm-form-section">
                    <h3 class="prm-form-section__title">7. Notes</h3>
                    <div class="prm-field">
                        <label class="prm-label">Initial Note <span class="prm-optional">(Optional)</span></label>
                        <textarea name="note" class="prm-textarea" rows="4" maxlength="500"
                                  placeholder="Enter lead description, conversation summary, or any important notes...">{{ old('note') }}</textarea>
                        <div class="prm-char-count" id="noteCount">0 / 500</div>
                    </div>
                    <div class="prm-field">
                        <label class="prm-label">Add Tags <span class="prm-optional">(Optional)</span></label>
                        <div class="prm-tags-wrap" id="tagsWrap">
                            @foreach(['Dental Implant', 'High Value', 'Walk-in'] as $tag)
                            <span class="prm-tag">
                                {{ $tag }}
                                <button type="button" class="prm-tag__remove" data-tag="{{ $tag }}">×</button>
                                <input type="hidden" name="tags[]" value="{{ $tag }}">
                            </span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Additional Information ─────────────────────────────────── --}}
        <div class="prm-form-section prm-form-section--wide">
            <h3 class="prm-form-section__title">8. Additional Information <span class="prm-optional">(Optional)</span></h3>
            <div class="prm-form-grid prm-form-grid--three">
                <div class="prm-field">
                    <label class="prm-label">Occupation</label>
                    <input type="text" name="occupation" class="prm-input"
                           placeholder="Enter occupation" value="{{ old('occupation') }}">
                </div>
                <div class="prm-field">
                    <label class="prm-label">Location</label>
                    <input type="text" name="location" class="prm-input"
                           placeholder="Enter city / area" value="{{ old('location') }}">
                </div>
                <div class="prm-field">
                    <label class="prm-label">Language</label>
                    <select name="language" class="prm-select">
                        <option value="">Select language</option>
                        @foreach($languages as $lang)
                        <option value="{{ $lang }}" {{ old('language') == $lang ? 'selected' : '' }}>{{ $lang }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- ── Form Actions ────────────────────────────────────────────── --}}
        <div class="prm-form-actions">
            <a href="{{ route('communication.prm.index') }}" class="prm-btn prm-btn--ghost">Cancel</a>
            <div style="display:flex;gap:10px">
                <button type="submit" name="_action" value="save_another" class="prm-btn prm-btn--outline">
                    Save &amp; Add Another
                </button>
                <button type="submit" name="_action" value="save" class="prm-btn prm-btn--primary">
                    <i class="ti ti-check"></i> Save Lead
                </button>
            </div>
        </div>

    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/communication/prm-board.js') }}"></script>
<script>
    // Character counter for note textarea
    document.querySelector('[name="note"]')?.addEventListener('input', function () {
        document.getElementById('noteCount').textContent = this.value.length + ' / 500';
    });

    // Lead type card selection
    document.querySelectorAll('.prm-lead-type-card__radio').forEach(radio => {
        radio.addEventListener('change', () => {
            document.querySelectorAll('.prm-lead-type-card').forEach(c => c.classList.remove('prm-lead-type-card--selected'));
            radio.closest('.prm-lead-type-card').classList.add('prm-lead-type-card--selected');
        });
    });

    // Urgency button selection
    document.querySelectorAll('[name="urgency"]').forEach(radio => {
        radio.addEventListener('change', () => {
            document.querySelectorAll('.prm-urgency-btn').forEach(b => b.classList.remove('prm-urgency-btn--selected'));
            radio.closest('.prm-urgency-btn').classList.add('prm-urgency-btn--selected');
        });
    });

    // Preferred contact selection
    document.querySelectorAll('[name="preferred_contact"]').forEach(radio => {
        radio.addEventListener('change', () => {
            document.querySelectorAll('.prm-pref-contact').forEach(b => b.classList.remove('prm-pref-contact--selected'));
            radio.closest('.prm-pref-contact').classList.add('prm-pref-contact--selected');
        });
    });

    // Tag removal
    document.querySelectorAll('.prm-tag__remove').forEach(btn => {
        btn.addEventListener('click', () => btn.closest('.prm-tag').remove());
    });
</script>
@endpush
