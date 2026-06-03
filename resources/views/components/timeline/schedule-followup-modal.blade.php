{{-- resources/views/components/timeline/schedule-followup-modal.blade.php --}}
<div class="modal-overlay" id="scheduleFollowupModal" style="display:none;">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Schedule Follow-up</h3>
            <button class="modal-close" onclick="closeModal('scheduleFollowupModal')">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Person info --}}
        <div class="modal-person-bar">
            <div class="mpb-avatar {{ $person['type'] === 'patient' ? 'avatar-patient' : 'avatar-lead' }}">
                {{ $person['avatar'] }}
            </div>
            <div>
                <div class="mpb-name">{{ $person['name'] }}</div>
                <div class="mpb-phone">{{ $person['phone'] }}</div>
            </div>
            <span class="mpb-badge {{ $person['type'] === 'patient' ? 'badge-patient' : 'badge-lead' }}">
                {{ $person['status'] }}
            </span>
        </div>

        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Follow-up Type <span class="req">*</span></label>
                    <select class="form-select" id="followupType">
                        <option value="call">📞 Outgoing Call</option>
                        <option value="whatsapp">💬 WhatsApp</option>
                        <option value="clinic_visit">🏥 Clinic Visit</option>
                        <option value="email">📧 Email</option>
                        <option value="sms">📱 SMS</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <select class="form-select" id="followupPriority">
                        <option value="medium" selected>🟡 Medium</option>
                        <option value="high">🔴 High</option>
                        <option value="low">🟢 Low</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Date <span class="req">*</span></label>
                    <input type="date" class="form-input" id="followupDate">
                </div>
                <div class="form-group">
                    <label class="form-label">Time <span class="req">*</span></label>
                    <input type="time" class="form-input" id="followupTime" value="10:00">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Assign To</label>
                <select class="form-select" id="followupAssign">
                    <option>Neha (Front Desk)</option>
                    <option>Rahul (Treatment Coordinator)</option>
                    <option>Anjali Kapoor</option>
                    <option>Dr. Mehta</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Note (Optional)</label>
                <textarea class="form-textarea" id="followupNote" rows="2"
                    placeholder="Add context for this follow-up..." maxlength="250"></textarea>
            </div>
            <div class="info-banner">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                This follow-up will appear in {{ $person['name'] }}'s communication queue on the selected date.
            </div>
        </div>

        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModal('scheduleFollowupModal')">Cancel</button>
            <button class="btn-primary" onclick="saveFollowup()">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Schedule Follow-up
            </button>
        </div>
    </div>
</div>
