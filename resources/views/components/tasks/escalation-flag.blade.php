{{-- Escalation Flag Modal --}}
<div class="escalation-modal-body">

    {{-- Lead context --}}
    <div class="modal-lead-context">
        <div class="modal-avatar" id="esc-lead-avatar">RS</div>
        <div class="modal-lead-info">
            <span class="modal-lead-name" id="esc-lead-name">Riya Sharma</span>
            <span class="modal-lead-phone" id="esc-lead-phone">98765 43210</span>
        </div>
        <span class="modal-lead-badge" id="esc-lead-badge">New Lead</span>
    </div>

    {{-- Escalation warning banner --}}
    <div class="escalation-warning-banner">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <span>Escalating this task will notify the supervisor immediately.</span>
    </div>

    <div class="form-grid">
        {{-- Current assignee --}}
        <div class="form-group form-full">
            <label class="form-label">Currently Assigned To</label>
            <div class="current-assignee-display">
                <div class="assignee-avatar-sm" style="background: #5B4FBE20; color: #5B4FBE">N</div>
                <span id="esc-current-assignee">Neha (Front Desk)</span>
            </div>
        </div>

        {{-- Escalate to --}}
        <div class="form-group form-full">
            <label class="form-label">Escalate To <span class="required">*</span></label>
            <select class="form-select">
                <option value="">Select supervisor / senior staff</option>
                <option value="anjali">Anjali Kapoor (Treatment Coordinator)</option>
                <option value="dr_mehta">Dr. Mehta (Dentist)</option>
                <option value="dr_neha">Dr. Neha (Front Desk - Senior)</option>
                <option value="manager">Clinic Manager</option>
            </select>
        </div>

        {{-- Escalation reason --}}
        <div class="form-group form-full">
            <label class="form-label">Reason for Escalation <span class="required">*</span></label>
            <select class="form-select">
                <option value="">Select reason</option>
                <option>Patient not responding — multiple attempts</option>
                <option>High-value patient needs attention</option>
                <option>Treatment concern raised</option>
                <option>Billing or payment issue</option>
                <option>Complaint from patient</option>
                <option>Overdue by more than 3 days</option>
                <option>Requires doctor decision</option>
                <option>Other</option>
            </select>
        </div>

        {{-- Priority override --}}
        <div class="form-group form-full">
            <label class="form-label">Escalation Priority</label>
            <div class="priority-selector">
                <button class="priority-btn" data-priority="medium" onclick="setPriority('medium', this)">
                    <span class="priority-dot-sm" style="background:#DD6B20"></span> Medium
                </button>
                <button class="priority-btn active" data-priority="high" onclick="setPriority('high', this)">
                    <span class="priority-dot-sm" style="background:#E53E3E"></span> High
                </button>
                <button class="priority-btn" data-priority="critical" onclick="setPriority('critical', this)">
                    <span class="priority-dot-sm" style="background:#742A2A"></span> Critical
                </button>
            </div>
        </div>

        {{-- Escalation note --}}
        <div class="form-group form-full">
            <label class="form-label">Escalation Note <span class="required">*</span></label>
            <textarea class="form-textarea" placeholder="Describe the situation and why escalation is needed..." rows="3" maxlength="500"></textarea>
            <span class="char-count">0 / 500</span>
        </div>

        {{-- Notify options --}}
        <div class="form-group form-full">
            <label class="form-label">Notify Via</label>
            <div class="notify-options">
                <label class="checkbox-option">
                    <input type="checkbox" checked> In-app notification
                </label>
                <label class="checkbox-option">
                    <input type="checkbox" checked> WhatsApp alert
                </label>
                <label class="checkbox-option">
                    <input type="checkbox"> Email
                </label>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button class="btn-ghost" onclick="closeEscalationModal()">Cancel</button>
        <button class="btn-escalate" onclick="confirmEscalation()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            Escalate Task
        </button>
    </div>
</div>
