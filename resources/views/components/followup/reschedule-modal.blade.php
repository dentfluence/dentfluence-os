{{-- resources/views/components/followup/reschedule-modal.blade.php --}}

<div class="fu-modal-overlay" id="rescheduleModal" style="display:none">
    <div class="fu-modal fu-modal-md">
        <div class="fu-modal-header">
            <h3 class="fu-modal-title">Reschedule Follow-up</h3>
            <button class="fu-modal-close" onclick="closeModal('rescheduleModal')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="fu-modal-patient">
            <div class="fu-modal-avatar">RS</div>
            <div class="fu-modal-patient-info">
                <span class="fu-modal-patient-name">Riya Sharma</span>
                <span class="fu-modal-patient-phone">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.63 3.38 2 2 0 0 1 3.6 1.21h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.81a16 16 0 0 0 6.29 6.29l1.87-1.87a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    98765 43210
                </span>
                <span class="fu-modal-lead-tag">New Lead</span>
            </div>
        </div>
        <div class="fu-modal-divider"></div>

        <div class="fu-complete-meta">
            <div class="fu-meta-row">
                <span class="fu-meta-label">Current Follow-up</span>
                <span class="fu-meta-value">Outgoing Call</span>
            </div>
            <div class="fu-meta-row">
                <span class="fu-meta-label">Current Date & Time</span>
                <span class="fu-meta-value">19 May 2025, 10:00 AM</span>
            </div>
        </div>
        <div class="fu-modal-divider"></div>

        <div class="fu-modal-body">
            <div class="fu-form-group">
                <label class="fu-form-label">Reason (Optional)</label>
                <div class="fu-select-wrap">
                    <select class="fu-select">
                        <option value="">Select a reason</option>
                        <option>Patient not available</option>
                        <option>Staff not available</option>
                        <option>Patient requested</option>
                        <option>Other</option>
                    </select>
                    <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
            </div>

            <div class="fu-form-group">
                <label class="fu-form-label">New Date & Time <span class="fu-required">*</span></label>
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
                <label class="fu-form-label">Follow-up Type</label>
                <div class="fu-select-wrap">
                    <select class="fu-select">
                        <option value="call" selected>Outgoing Call</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="clinic_visit">Clinic Visit</option>
                    </select>
                    <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
            </div>

            <div class="fu-form-group">
                <label class="fu-form-label">Notes (Optional)</label>
                <textarea class="fu-textarea" rows="3" maxlength="250" placeholder="Add a note..."></textarea>
                <span class="fu-char-count">0/250</span>
            </div>

            <div class="fu-info-banner">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Riya Sharma will be notified about the updated follow-up.
            </div>
        </div>

        <div class="fu-modal-footer">
            <button class="fu-btn-outline" onclick="closeModal('rescheduleModal')">Cancel</button>
            <button class="fu-btn-primary" onclick="submitReschedule()">Reschedule Follow-up</button>
        </div>
    </div>
</div>
