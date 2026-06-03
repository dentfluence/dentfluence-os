{{-- resources/views/components/followup/change-status-modal.blade.php --}}

<div class="fu-modal-overlay" id="changeStatusModal" style="display:none">
    <div class="fu-modal fu-modal-sm">
        <div class="fu-modal-header">
            <h3 class="fu-modal-title">Change Status</h3>
            <button class="fu-modal-close" onclick="closeModal('changeStatusModal')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="fu-modal-patient">
            <div class="fu-modal-avatar">RS</div>
            <div class="fu-modal-patient-info">
                <span class="fu-modal-patient-name">Riya Sharma</span>
                <span class="fu-modal-patient-phone">98765 43210</span>
                <span class="fu-modal-lead-tag">New Lead</span>
            </div>
        </div>
        <div class="fu-modal-divider"></div>
        <div class="fu-modal-body">
            <div class="fu-form-group">
                <label class="fu-form-label">Current Status</label>
                <div class="fu-current-status">
                    <span class="fu-status-dot" style="background:#6B5BDF"></span>
                    New Lead
                </div>
            </div>
            <div class="fu-form-group">
                <label class="fu-form-label">New Status <span class="fu-required">*</span></label>
                <div class="fu-status-list" id="statusList">
                    @php
                    $statuses = [
                        ['value' => 'new_lead',      'label' => 'New Lead',           'sub' => 'Just received / not contacted yet', 'color' => '#6B5BDF'],
                        ['value' => 'contacted',     'label' => 'Contacted',          'sub' => 'Spoke to the lead',                 'color' => '#3B82F6'],
                        ['value' => 'interested',    'label' => 'Interested',         'sub' => 'Lead is interested',               'color' => '#22C55E', 'selected' => true],
                        ['value' => 'appt_booked',   'label' => 'Appointment Scheduled','sub' => 'Appointment fixed',              'color' => '#F97316'],
                        ['value' => 'visited',       'label' => 'Visited Clinic',     'sub' => 'Lead visited the clinic',          'color' => '#0EA5E9'],
                        ['value' => 'converted',     'label' => 'Converted to Patient','sub' => 'Lead converted to patient',       'color' => '#22C55E'],
                        ['value' => 'not_interested','label' => 'Not Interested',     'sub' => 'Lead is not interested',           'color' => '#EF4444'],
                        ['value' => 'lost',          'label' => 'Lost / No Response', 'sub' => 'No response or not reachable',     'color' => '#6B7280'],
                    ];
                    @endphp
                    @foreach($statuses as $s)
                    <div class="fu-status-option {{ !empty($s['selected']) ? 'fu-status-selected' : '' }}"
                         onclick="selectStatus('{{ $s['value'] }}', this)">
                        <span class="fu-status-dot" style="background:{{ $s['color'] }}"></span>
                        <div class="fu-status-option-text">
                            <span class="fu-status-option-label">{{ $s['label'] }}</span>
                            <span class="fu-status-option-sub">{{ $s['sub'] }}</span>
                        </div>
                        @if(!empty($s['selected']))
                        <svg class="fu-status-check" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="fu-modal-footer">
            <button class="fu-btn-outline" onclick="closeModal('changeStatusModal')">Cancel</button>
            <button class="fu-btn-primary" onclick="submitChangeStatus()">Update Status</button>
        </div>
    </div>
</div>
