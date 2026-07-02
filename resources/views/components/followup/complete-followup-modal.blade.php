{{-- resources/views/components/followup/complete-followup-modal.blade.php --}}

<div class="fu-modal-overlay" id="completeModal" style="display:none">
    <div class="fu-modal fu-modal-md">
        <div class="fu-modal-header">
            <h3 class="fu-modal-title">Complete Follow-up</h3>
            <button class="fu-modal-close" onclick="closeModal('completeModal')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        {{-- Patient Info --}}
        <div class="fu-modal-patient">
            <div class="fu-modal-avatar" id="completeAvatar">RS</div>
            <div class="fu-modal-patient-info">
                <span class="fu-modal-patient-name" id="completePatientName">Riya Sharma</span>
                <span class="fu-modal-patient-phone" id="completePatientPhone">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.63 3.38 2 2 0 0 1 3.6 1.21h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.81a16 16 0 0 0 6.29 6.29l1.87-1.87a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    98765 43210
                </span>
                <span class="fu-modal-lead-tag">New Lead</span>
            </div>
        </div>
        <div class="fu-modal-divider"></div>

        {{-- Follow-up meta --}}
        <div class="fu-complete-meta">
            <div class="fu-meta-row">
                <span class="fu-meta-label">Follow-up Type</span>
                <span class="fu-meta-value">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.63 3.38 2 2 0 0 1 3.6 1.21h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.81a16 16 0 0 0 6.29 6.29l1.87-1.87a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    Outgoing Call
                </span>
            </div>
            <div class="fu-meta-row">
                <span class="fu-meta-label">Date & Time</span>
                <span class="fu-meta-value">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    19 May 2025, 10:00 AM
                </span>
            </div>
            <div class="fu-meta-row">
                <span class="fu-meta-label">Duration</span>
                <span class="fu-meta-value">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    04:32 mins
                </span>
            </div>
        </div>
        <div class="fu-modal-divider"></div>

        {{-- Form --}}
        <div class="fu-modal-body">
            <div class="fu-form-group">
                <label class="fu-form-label">Call Outcome <span class="fu-required">*</span></label>
                <div class="fu-select-wrap">
                    <select class="fu-select" id="callOutcome">
                        <option value="">Select outcome</option>
                        <option value="connected" selected>Connected</option>
                        <option value="not_reachable">Not Reachable</option>
                        <option value="callback_later">Callback Requested</option>
                    </select>
                    <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
            </div>

            <div class="fu-form-group">
                <label class="fu-form-label">Result <span class="fu-required">*</span></label>
                <div class="fu-select-wrap">
                    <select class="fu-select" id="callResult">
                        <option value="">Select result</option>
                        <option value="interested" selected>Interested</option>
                        <option value="not_interested">Not Interested</option>
                        <option value="converted">Converted</option>
                    </select>
                    <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
            </div>

            <div class="fu-form-group">
                <label class="fu-form-label">Next Step</label>
                <div class="fu-select-wrap">
                    <select class="fu-select" id="nextStep">
                        <option value="schedule_followup" selected>Schedule Next Follow-up</option>
                        <option value="move_pipeline">Move Pipeline Stage</option>
                        <option value="close_lead">Close Lead</option>
                        <option value="convert_to_patient">Convert to Patient</option>
                        <option value="no_action">No Further Action</option>
                    </select>
                    <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
            </div>

            <div class="fu-form-group" id="nextFollowupDate">
                <label class="fu-form-label">Next Follow-up Date & Time</label>
                <div class="fu-date-time-row">
                    <div class="fu-input-icon-wrap" style="flex:1">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <input type="date" class="fu-input fu-input-icon" value="2025-05-22">
                    </div>
                    <div class="fu-input-icon-wrap" style="flex:1">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <input type="time" class="fu-input fu-input-icon" value="11:30">
                    </div>
                </div>
            </div>

            <div class="fu-form-group">
                <label class="fu-form-label">Notes</label>
                <textarea class="fu-textarea" id="completeNotes" rows="3" maxlength="500" placeholder="Add notes about this follow-up...">Patient interested in teeth whitening. Wants to visit clinic next week.</textarea>
                <span class="fu-char-count" id="completeNotesCount">73/500</span>
            </div>

            <div class="fu-checkbox-row">
                <input type="checkbox" id="scheduleNextFu" checked class="fu-checkbox">
                <label for="scheduleNextFu" class="fu-checkbox-label">Schedule next follow-up</label>
            </div>

            <div class="fu-info-banner" id="fuNotifyBanner">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Riya Sharma will be notified about the next follow-up.
            </div>
        </div>

        <div class="fu-modal-footer">
            <button class="fu-btn-outline" onclick="closeModal('completeModal')">Cancel</button>
            <button class="fu-btn-primary" onclick="submitComplete()">Complete</button>
        </div>
    </div>
</div>
