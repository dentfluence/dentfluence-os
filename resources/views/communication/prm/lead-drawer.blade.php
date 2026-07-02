{{--
    Lead Drawer Overlay + Sub-modals
    Included in prm/index.blade.php.
    JS in lead-drawer.js controls open/close states.
--}}

{{-- ── Drawer Overlay ────────────────────────────────────────────────── --}}
<div class="prm-drawer-overlay" id="drawerOverlay" style="display:none" aria-hidden="true">
    {{-- Drawer content loaded via AJAX into #leadDrawer inside this overlay --}}
    <div id="drawerAjaxTarget"></div>
</div>

{{-- ── Change Status Modal ───────────────────────────────────────────── --}}
<div class="prm-modal-overlay" id="changeStatusOverlay" style="display:none">
    <div class="prm-modal" id="changeStatusModal">
        <div class="prm-modal__header">
            <span class="prm-modal__title">Change Status</span>
            <button class="prm-modal__close" data-action="close-modal" data-target="changeStatusOverlay">
                <i class="ti ti-x"></i>
            </button>
        </div>
        <div class="prm-modal__body">

            {{-- Patient info injected by JS --}}
            <div class="prm-modal__lead-info" id="csLeadInfo"></div>

            <div class="prm-field">
                <label class="prm-label">Current Status</label>
                <div class="prm-input prm-input--readonly" id="csCurrentStatus">
                    <span class="prm-status-dot" id="csCurrentDot"></span>
                    <span id="csCurrentLabel">—</span>
                </div>
            </div>

            <div class="prm-field">
                <label class="prm-label">New Status <span class="prm-required">*</span></label>
                <div class="prm-status-list" id="csStatusList">
                    @php
                        $statusOptions = [
                            ['id'=>'new_lead',     'label'=>'New Lead',            'desc'=>'Just received / not contacted yet', 'color'=>'#534AB7'],
                            ['id'=>'contacted',    'label'=>'Contacted',           'desc'=>'Spoke to the lead',                 'color'=>'#0F6E56'],
                            ['id'=>'interested',   'label'=>'Interested',          'desc'=>'Lead is interested',                'color'=>'#3B6D11'],
                            ['id'=>'appointment',  'label'=>'Appointment Scheduled','desc'=>'Appointment fixed',               'color'=>'#854F0B'],
                            ['id'=>'visited',      'label'=>'Visited Clinic',      'desc'=>'Lead visited the clinic',           'color'=>'#185FA5'],
                            ['id'=>'converted',    'label'=>'Converted to Patient','desc'=>'Lead converted to patient',         'color'=>'#3B6D11'],
                            ['id'=>'not_interested','label'=>'Not Interested',     'desc'=>'Lead is not interested',            'color'=>'#A32D2D'],
                            ['id'=>'lost',         'label'=>'Lost / No Response',  'desc'=>'No response or not reachable',      'color'=>'#888780'],
                        ];
                    @endphp
                    @foreach($statusOptions as $opt)
                    <div class="prm-status-option" data-status="{{ $opt['id'] }}">
                        <span class="prm-status-dot" style="background:{{ $opt['color'] }}"></span>
                        <div class="prm-status-option__text">
                            <span class="prm-status-option__label">{{ $opt['label'] }}</span>
                            <span class="prm-status-option__desc">{{ $opt['desc'] }}</span>
                        </div>
                        <i class="ti ti-check prm-status-option__check"></i>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="prm-modal__footer">
            <button class="prm-btn prm-btn--outline" data-action="close-modal" data-target="changeStatusOverlay">Cancel</button>
            <button class="prm-btn prm-btn--primary" id="csUpdateBtn">Update Status</button>
        </div>
    </div>
</div>

{{-- ── Convert to Patient Modal ──────────────────────────────────────── --}}
<div class="prm-modal-overlay" id="convertPatientOverlay" style="display:none">
    <div class="prm-modal" id="convertPatientModal">
        <div class="prm-modal__header">
            <span class="prm-modal__title">Convert to Patient</span>
            <button class="prm-modal__close" data-action="close-modal" data-target="convertPatientOverlay">
                <i class="ti ti-x"></i>
            </button>
        </div>
        <div class="prm-modal__body">
            <div class="prm-modal__lead-info" id="cpLeadInfo"></div>
            <div class="prm-info-banner" style="margin-bottom:16px">
                <i class="ti ti-info-circle"></i>
                Converting this lead will create a new patient profile and move all communication history.
            </div>
            <div class="prm-form-section__title" style="color:#534AB7;margin-bottom:12px">Patient Details</div>
            <form id="convertPatientForm">
                <div class="prm-field">
                    <label class="prm-label">Full Name <span class="prm-required">*</span></label>
                    <input type="text" name="patient_name" class="prm-input" id="cpName" required>
                </div>
                <div class="prm-field-row">
                    <div class="prm-field">
                        <label class="prm-label">Date of Birth <span class="prm-required">*</span></label>
                        <input type="date" name="dob" class="prm-input" required>
                    </div>
                    <div class="prm-field">
                        <label class="prm-label">Gender <span class="prm-required">*</span></label>
                        <select name="gender" class="prm-select" required>
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female" selected>Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="prm-field">
                    <label class="prm-label">Email</label>
                    <input type="email" name="email" class="prm-input" id="cpEmail">
                </div>
                <div class="prm-field">
                    <label class="prm-label">Patient Owner <span class="prm-required">*</span></label>
                    <select name="patient_owner" class="prm-select" required>
                        @foreach(['Neha (Front Desk)','Anjali Kapoor','Priya Singh','Dr. Mehta'] as $s)
                        <option value="{{ $s }}" {{ $s === 'Dr. Neha' ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="prm-field">
                    <label class="prm-label">Source</label>
                    <select name="source" class="prm-select">
                        @foreach(['Call Manager','WhatsApp','Instagram','Website','Walk-in','Referral'] as $s)
                        <option value="{{ $s }}">{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="prm-field">
                    <label class="prm-label">Notes <span class="prm-optional">(Optional)</span></label>
                    <textarea name="notes" class="prm-textarea" rows="3" maxlength="250"></textarea>
                    <div class="prm-char-count" id="cpNotesCount">0 / 250</div>
                </div>
                <div class="prm-field">
                    <label class="prm-checkbox-label">
                        <input type="checkbox" name="schedule_followup" id="cpScheduleFollowup" checked>
                        Schedule next follow-up / appointment
                    </label>
                </div>
                <div id="cpFollowupSection" class="prm-form-section" style="background:var(--c-bg-secondary);border-radius:10px;padding:14px;margin-top:8px">
                    <div class="prm-field-row">
                        <div class="prm-field">
                            <label class="prm-label">Date &amp; Time</label>
                            <input type="date" name="followup_date" class="prm-input" value="{{ date('Y-m-d', strtotime('+4 days')) }}">
                        </div>
                        <div class="prm-field" style="margin-top:22px">
                            <input type="time" name="followup_time" class="prm-input" value="11:30">
                        </div>
                    </div>
                    <div class="prm-field">
                        <label class="prm-label">Follow-up Type</label>
                        <select name="followup_type" class="prm-select">
                            <option value="clinic_visit">Clinic Visit / Appointment</option>
                            <option value="call">Outgoing Call</option>
                            <option value="whatsapp">WhatsApp</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        <div class="prm-modal__footer">
            <button class="prm-btn prm-btn--outline" data-action="close-modal" data-target="convertPatientOverlay">Cancel</button>
            <button class="prm-btn prm-btn--primary" id="cpConvertBtn">Convert to Patient</button>
        </div>
    </div>
</div>

{{-- ── Stage Selector Modal (quick move) ─────────────────────────────── --}}
<div class="prm-modal-overlay" id="stageSelectorOverlay" style="display:none">
    <div class="prm-modal prm-modal--sm" id="stageSelectorModal">
        <div class="prm-modal__header">
            <span class="prm-modal__title">Move to Stage</span>
            <button class="prm-modal__close" data-action="close-modal" data-target="stageSelectorOverlay">
                <i class="ti ti-x"></i>
            </button>
        </div>
        <div class="prm-modal__body" id="ssStagelist">
            {{-- Populated by JS --}}
        </div>
    </div>
</div>
