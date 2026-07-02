{{-- resources/views/components/followup/create-case-modal.blade.php --}}

<div class="fu-modal-overlay" id="createCaseModal" style="display:none">
    <div class="fu-modal fu-modal-md">
        <div class="fu-modal-header">
            <h3 class="fu-modal-title">Create Case / Trigger</h3>
            <button class="fu-modal-close" onclick="closeModal('createCaseModal')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="fu-info-banner fu-info-banner-top">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            This will create a case for the patient and route it to the relevant department for follow-up.
        </div>
        <div class="fu-modal-body">
            <div class="fu-section-label">Patient Information</div>
            <div class="fu-modal-patient" style="background:var(--fu-bg-secondary); border-radius:8px; padding:12px; margin-bottom:16px;">
                <div class="fu-modal-avatar">RS</div>
                <div class="fu-modal-patient-info">
                    <span class="fu-modal-patient-name">Riya Sharma</span>
                    <span class="fu-modal-lead-tag">New Lead</span>
                </div>
                <span class="fu-modal-patient-phone" style="margin-left:auto">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07"/></svg>
                    98765 43210
                </span>
            </div>
            <div class="fu-form-group">
                <label class="fu-form-label">Reason for Case <span class="fu-required">*</span></label>
                <div class="fu-select-wrap">
                    <select class="fu-select">
                        <option value="">Select reason</option>
                        <option>Post-Op Complication</option>
                        <option>Treatment Complaint</option>
                        <option>Billing Issue</option>
                        <option>Appointment Related</option>
                        <option>Other</option>
                    </select>
                    <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
            </div>
            <div class="fu-form-row">
                <div class="fu-form-group" style="flex:1">
                    <label class="fu-form-label">Case Category <span class="fu-required">*</span></label>
                    <div class="fu-select-wrap">
                        <select class="fu-select">
                            <option value="">Select category</option>
                            <option>Clinical</option>
                            <option>Administrative</option>
                            <option>Financial</option>
                            <option>Communication</option>
                        </select>
                        <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                </div>
                <div class="fu-form-group" style="flex:1">
                    <label class="fu-form-label">Priority <span class="fu-required">*</span></label>
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
                <label class="fu-form-label">Assign To <span class="fu-required">*</span></label>
                <div class="fu-select-wrap">
                    <select class="fu-select">
                        <option value="">Select department / team</option>
                        <option value="1">Front Desk</option>
                        <option value="2">Treatment Coordinator</option>
                        <option value="3">Doctor</option>
                        <option value="4">Billing</option>
                    </select>
                    <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
            </div>
            <div class="fu-form-group">
                <label class="fu-form-label">Case Description <span class="fu-required">*</span></label>
                <textarea class="fu-textarea" rows="4" maxlength="500" placeholder="Enter case details..."></textarea>
                <span class="fu-char-count">0/500</span>
            </div>
            <div class="fu-form-group">
                <label class="fu-form-label">Attachments (Optional)</label>
                <div class="fu-upload-area">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
                    Upload File
                    <span class="fu-upload-hint">Max file size: 10MB (PDF, JPG, PNG)</span>
                </div>
            </div>
        </div>
        <div class="fu-modal-footer">
            <button class="fu-btn-outline" onclick="closeModal('createCaseModal')">Cancel</button>
            <button class="fu-btn-primary" onclick="submitCase()">Create Case</button>
        </div>
    </div>
</div>
