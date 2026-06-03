{{-- resources/views/components/followup/add-note-modal.blade.php --}}

<div class="fu-modal-overlay" id="addNoteModal" style="display:none">
    <div class="fu-modal fu-modal-md">
        <div class="fu-modal-header">
            <h3 class="fu-modal-title">Add Note</h3>
            <button class="fu-modal-close" onclick="closeModal('addNoteModal')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="fu-modal-patient">
            <div class="fu-modal-avatar">RS</div>
            <div class="fu-modal-patient-info">
                <span class="fu-modal-patient-name">Riya Sharma</span>
                <span class="fu-modal-patient-phone">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07"/></svg>
                    98765 43210
                </span>
                <span class="fu-modal-lead-tag">New Lead</span>
            </div>
        </div>
        <div class="fu-modal-divider"></div>
        <div class="fu-modal-body">
            <div class="fu-form-group">
                <label class="fu-form-label">Note Type <span class="fu-required">*</span></label>
                <div class="fu-select-wrap">
                    <select class="fu-select">
                        <option value="general" selected>General Note</option>
                        <option value="call_note">Call Note</option>
                        <option value="whatsapp_note">WhatsApp Note</option>
                        <option value="visit_note">Clinic Visit Note</option>
                    </select>
                    <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
            </div>
            <div class="fu-form-group">
                <label class="fu-form-label">Note <span class="fu-required">*</span></label>
                <textarea class="fu-textarea" rows="5" maxlength="1000" id="noteText" placeholder="Write your note here..."></textarea>
                <span class="fu-char-count" id="noteCount">0/1000</span>
            </div>
            <div class="fu-form-group">
                <label class="fu-form-label">Add to Follow-up</label>
                <div class="fu-select-wrap">
                    <select class="fu-select">
                        <option value="yes" selected>Yes, update this follow-up</option>
                        <option value="no">No, just save note</option>
                    </select>
                    <svg class="fu-select-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
            </div>
            <div class="fu-form-group">
                <label class="fu-form-label">Notes Visibility <span class="fu-required">*</span></label>
                <div class="fu-radio-group">
                    <label class="fu-radio-label"><input type="radio" name="noteVis" value="me" class="fu-radio"> Only me</label>
                    <label class="fu-radio-label"><input type="radio" name="noteVis" value="team" checked class="fu-radio"> My team</label>
                    <label class="fu-radio-label"><input type="radio" name="noteVis" value="everyone" class="fu-radio"> Everyone</label>
                </div>
            </div>
        </div>
        <div class="fu-modal-footer">
            <button class="fu-btn-outline" onclick="closeModal('addNoteModal')">Cancel</button>
            <button class="fu-btn-primary" onclick="submitNote()">Save Note</button>
        </div>
    </div>
</div>
