{{-- Assignment Modal Component --}}
{{-- Used for both Add Task and Reassign flows --}}
<div class="assignment-modal-body">

    {{-- Lead/Patient context (shown when reassigning) --}}
    @if(isset($lead))
    <div class="modal-lead-context">
        <div class="modal-avatar">{{ $lead['initial'] }}</div>
        <div class="modal-lead-info">
            <span class="modal-lead-name">{{ $lead['name'] }}</span>
            <span class="modal-lead-phone">{{ $lead['phone'] }}</span>
        </div>
        @if(isset($lead['badge']))
        <span class="modal-lead-badge">{{ $lead['badge'] }}</span>
        @endif
    </div>
    @endif

    <div class="form-grid">
        {{-- Task Title --}}
        <div class="form-group form-full">
            <label class="form-label">Task Title <span class="required">*</span></label>
            <input type="text" class="form-input" placeholder="e.g. Follow up with patient about treatment plan">
        </div>

        {{-- Task Type --}}
        <div class="form-group">
            <label class="form-label">Task Type <span class="required">*</span></label>
            <select class="form-select">
                <option value="">Select type</option>
                <option value="call">Call</option>
                <option value="whatsapp">WhatsApp</option>
                <option value="clinic-visit">Clinic Visit</option>
                <option value="note">Note / Follow-up</option>
                <option value="estimate">Send Estimate</option>
                <option value="appointment">Book Appointment</option>
                <option value="other">Other</option>
            </select>
        </div>

        {{-- Priority --}}
        <div class="form-group">
            <label class="form-label">Priority <span class="required">*</span></label>
            <div class="priority-selector">
                <button class="priority-btn priority-low-btn" data-priority="low" onclick="setPriority('low', this)">
                    <span class="priority-dot-sm" style="background:#38A169"></span> Low
                </button>
                <button class="priority-btn priority-medium-btn active" data-priority="medium" onclick="setPriority('medium', this)">
                    <span class="priority-dot-sm" style="background:#DD6B20"></span> Medium
                </button>
                <button class="priority-btn priority-high-btn" data-priority="high" onclick="setPriority('high', this)">
                    <span class="priority-dot-sm" style="background:#E53E3E"></span> High
                </button>
            </div>
        </div>

        {{-- Assign To --}}
        <div class="form-group">
            <label class="form-label">Assign To <span class="required">*</span></label>
            <div class="assignee-select-wrap">
                <div class="assignee-select-display" onclick="toggleAssigneeDropdown()">
                    <div class="assignee-avatar-sm" style="background: #5B4FBE20; color: #5B4FBE">N</div>
                    <span>Neha (Front Desk)</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
                <div class="assignee-dropdown" id="assignee-dropdown" style="display:none">
                    @php
                    $staff = [
                        ['name' => 'Neha (Front Desk)', 'initial' => 'N', 'role' => 'Front Desk', 'color' => '#5B4FBE'],
                        ['name' => 'Anjali Kapoor', 'initial' => 'AK', 'role' => 'Treatment Coordinator', 'color' => '#0F6E56'],
                        ['name' => 'Priya Singh', 'initial' => 'PS', 'role' => 'Front Desk', 'color' => '#854F0B'],
                        ['name' => 'Siddharth Rao', 'initial' => 'SR', 'role' => 'Dentist', 'color' => '#993C1D'],
                        ['name' => 'Dr. Mehta', 'initial' => 'DM', 'role' => 'Dentist', 'color' => '#534AB7'],
                    ];
                    @endphp
                    @foreach($staff as $s)
                    <div class="assignee-option" onclick="selectAssignee('{{ $s['name'] }}', '{{ $s['initial'] }}', '{{ $s['color'] }}')">
                        <div class="assignee-avatar-sm" style="background: {{ $s['color'] }}20; color: {{ $s['color'] }}">{{ $s['initial'] }}</div>
                        <div>
                            <span class="assignee-opt-name">{{ $s['name'] }}</span>
                            <span class="assignee-opt-role">{{ $s['role'] }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Due Date --}}
        <div class="form-group">
            <label class="form-label">Due Date <span class="required">*</span></label>
            <div class="input-with-icon">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <input type="date" class="form-input" value="{{ date('Y-m-d') }}">
            </div>
        </div>

        {{-- Due Time --}}
        <div class="form-group">
            <label class="form-label">Due Time <span class="required">*</span></label>
            <div class="input-with-icon">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <select class="form-select">
                    <option>09:00 AM</option>
                    <option>10:00 AM</option>
                    <option selected>11:00 AM</option>
                    <option>12:00 PM</option>
                    <option>01:00 PM</option>
                    <option>02:00 PM</option>
                    <option>03:00 PM</option>
                    <option>04:00 PM</option>
                    <option>05:00 PM</option>
                </select>
            </div>
        </div>

        {{-- Linked Lead --}}
        <div class="form-group form-full">
            <label class="form-label">Linked Lead / Patient</label>
            <div class="input-with-icon">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" class="form-input" placeholder="Search lead or patient by name or phone...">
            </div>
        </div>

        {{-- Notes --}}
        <div class="form-group form-full">
            <label class="form-label">Task Notes (Optional)</label>
            <textarea class="form-textarea" placeholder="Add any relevant notes or context..." rows="3" maxlength="500"></textarea>
            <span class="char-count">0 / 500</span>
        </div>

        {{-- Reminder --}}
        <div class="form-group form-full">
            <label class="form-label">Reminder</label>
            <select class="form-select">
                <option>No reminder</option>
                <option selected>15 minutes before</option>
                <option>30 minutes before</option>
                <option>1 hour before</option>
                <option>1 day before</option>
            </select>
        </div>
    </div>

    <div class="modal-footer">
        <button class="btn-ghost" onclick="closeAddTaskModal()">Cancel</button>
        <button class="btn-primary" onclick="saveTask()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            Save Task
        </button>
    </div>
</div>
