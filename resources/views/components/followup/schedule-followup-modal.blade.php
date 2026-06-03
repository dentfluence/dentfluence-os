{{-- resources/views/components/followup/schedule-followup-modal.blade.php --}}

<div class="fu-modal-overlay" id="scheduleModal" style="display:none">
    <div class="fu-modal fu-modal-md">
        <div class="fu-modal-header">
            <h3 class="fu-modal-title">Schedule Follow-up</h3>
            <button class="fu-modal-close" onclick="closeModal('scheduleModal')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="fu-modal-body">

            {{-- Search patient/lead --}}
            <div class="fu-form-group">
                <label class="fu-form-label">Patient / Lead <span class="fu-required">*</span></label>
                <div class="fu-input-icon-wrap">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" class="fu-input fu-input-icon" placeholder="Search by name or phone...">
                </div>
            </div>

            <div class="fu-form-row">
                <div class="fu-form-group" style="flex:1">
                    <label class="fu-form-label">Follow-up Type <span class="fu-required">*</span></label>
                    <div class="fu-select-wrap">
                        <select class="fu-select">
                            <option value="call" selected>Outgoing Call</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="clinic_visit">Clinic Visit</option>
                            <option value="sms">SMS</option>
                        </select>
                        <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                </div>
                <div class="fu-form-group" style="flex:1">
                    <label class="fu-form-label">Priority</label>
                    <div class="fu-select-wrap">
                        <select class="fu-select">
                            <option value="high">High</option>
                            <option value="medium" selected>Medium</option>
                            <option value="low">Low</option>
                        </select>
                        <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                </div>
            </div>

            <div class="fu-form-group">
                <label class="fu-form-label">Date & Time <span class="fu-required">*</span></label>
                <div class="fu-date-time-row">
                    <div class="fu-input-icon-wrap" style="flex:1">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <input type="date" class="fu-input fu-input-icon">
                    </div>
                    <div class="fu-input-icon-wrap" style="flex:1">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <input type="time" class="fu-input fu-input-icon" value="10:00">
                    </div>
                </div>
            </div>

            <div class="fu-form-group">
                <label class="fu-form-label">Assign To <span class="fu-required">*</span></label>
                <div class="fu-select-wrap">
                    <select class="fu-select">
                        <option value="">Select staff member</option>
                        <option value="1" selected>Neha (Front Desk)</option>
                        <option value="2">Anjali Kapoor (Treatment Coordinator)</option>
                        <option value="3">Priya Singh (Front Desk)</option>
                    </select>
                    <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
            </div>

            <div class="fu-form-group">
                <label class="fu-form-label">Notes (Optional)</label>
                <textarea class="fu-textarea" rows="3" maxlength="500" placeholder="Add instructions or context..."></textarea>
                <span class="fu-char-count">0/500</span>
            </div>

            <div class="fu-checkbox-row">
                <input type="checkbox" id="notifyPatient" class="fu-checkbox">
                <label for="notifyPatient" class="fu-checkbox-label">Notify patient via WhatsApp</label>
            </div>

        </div>
        <div class="fu-modal-footer">
            <button class="fu-btn-outline" onclick="closeModal('scheduleModal')">Cancel</button>
            <button class="fu-btn-primary" onclick="submitSchedule()">Schedule Follow-up</button>
        </div>
    </div>
</div>
