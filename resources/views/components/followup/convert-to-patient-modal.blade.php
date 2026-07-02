{{-- resources/views/components/followup/convert-to-patient-modal.blade.php --}}

<div class="fu-modal-overlay" id="convertModal" style="display:none">
    <div class="fu-modal fu-modal-md">
        <div class="fu-modal-header">
            <h3 class="fu-modal-title">Convert to Patient</h3>
            <button class="fu-modal-close" onclick="closeModal('convertModal')">
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
        <div class="fu-info-banner">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Converting this lead will create a new patient profile and move all communication history.
        </div>
        <div class="fu-modal-body">
            <div class="fu-section-label">Patient Details</div>
            <div class="fu-form-group">
                <label class="fu-form-label">Full Name <span class="fu-required">*</span></label>
                <input type="text" class="fu-input" value="Riya Sharma">
            </div>
            <div class="fu-form-row">
                <div class="fu-form-group" style="flex:1">
                    <label class="fu-form-label">Date of Birth <span class="fu-required">*</span></label>
                    <div class="fu-input-icon-wrap">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <input type="date" class="fu-input fu-input-icon" value="1995-04-12">
                    </div>
                </div>
                <div class="fu-form-group" style="flex:1">
                    <label class="fu-form-label">Gender <span class="fu-required">*</span></label>
                    <div class="fu-select-wrap">
                        <select class="fu-select">
                            <option value="female" selected>Female</option>
                            <option value="male">Male</option>
                            <option value="other">Other</option>
                        </select>
                        <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                </div>
            </div>
            <div class="fu-form-group">
                <label class="fu-form-label">Email</label>
                <input type="email" class="fu-input" value="riya.sharma@gmail.com">
            </div>
            <div class="fu-form-row">
                <div class="fu-form-group" style="flex:1">
                    <label class="fu-form-label">Patient Owner <span class="fu-required">*</span></label>
                    <div class="fu-select-wrap">
                        <select class="fu-select">
                            <option value="1" selected>Dr. Neha</option>
                            <option value="2">Dr. Mehta</option>
                        </select>
                        <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                </div>
                <div class="fu-form-group" style="flex:1">
                    <label class="fu-form-label">Source</label>
                    <div class="fu-select-wrap">
                        <select class="fu-select">
                            <option value="call" selected>Call Manager</option>
                            <option value="website">Website</option>
                            <option value="referral">Referral</option>
                        </select>
                        <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                </div>
            </div>
            <div class="fu-form-group">
                <label class="fu-form-label">Notes (Optional)</label>
                <textarea class="fu-textarea" rows="3" maxlength="250" placeholder="Any notes...">Interested in teeth whitening and general check-up.</textarea>
                <span class="fu-char-count">58/250</span>
            </div>
            <div class="fu-checkbox-row">
                <input type="checkbox" id="scheduleAppt" class="fu-checkbox" checked>
                <label for="scheduleAppt" class="fu-checkbox-label">Schedule next follow-up / appointment</label>
            </div>
            <div class="fu-form-row" id="apptDateRow">
                <div class="fu-form-group" style="flex:1">
                    <label class="fu-form-label">Date & Time</label>
                    <div class="fu-input-icon-wrap">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <input type="date" class="fu-input fu-input-icon" value="2025-05-22">
                    </div>
                </div>
                <div class="fu-form-group" style="flex:1">
                    <label class="fu-form-label">&nbsp;</label>
                    <div class="fu-input-icon-wrap">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <input type="time" class="fu-input fu-input-icon" value="11:30">
                    </div>
                </div>
            </div>
            <div class="fu-form-group" id="apptTypeRow">
                <label class="fu-form-label">Follow-up Type</label>
                <div class="fu-select-wrap">
                    <select class="fu-select">
                        <option value="clinic_visit" selected>Clinic Visit / Appointment</option>
                        <option value="call">Call</option>
                        <option value="whatsapp">WhatsApp</option>
                    </select>
                    <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
            </div>
        </div>
        <div class="fu-modal-footer">
            <button class="fu-btn-outline" onclick="closeModal('convertModal')">Cancel</button>
            <button class="fu-btn-primary" onclick="submitConvert()">Convert to Patient</button>
        </div>
    </div>
</div>
