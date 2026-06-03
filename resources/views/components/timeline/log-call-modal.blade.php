{{-- resources/views/components/timeline/log-call-modal.blade.php --}}
<div class="modal-overlay" id="logCallModal" style="display:none;">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Log a Call</h3>
            <button class="modal-close" onclick="closeModal('logCallModal')">
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
                    <label class="form-label">Call Direction <span class="req">*</span></label>
                    <select class="form-select" id="callDirection">
                        <option value="outgoing">↗ Outgoing</option>
                        <option value="incoming">↙ Incoming</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Call Outcome <span class="req">*</span></label>
                    <select class="form-select" id="callOutcome">
                        <option value="">Select outcome...</option>
                        <option value="connected">✅ Connected</option>
                        <option value="not_reachable">❌ Not Reachable</option>
                        <option value="busy">📵 Busy</option>
                        <option value="voicemail">📬 Voicemail</option>
                        <option value="callback_requested">🔄 Callback Requested</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Result</label>
                    <select class="form-select" id="callResult">
                        <option value="">Select result...</option>
                        <option value="interested">Interested</option>
                        <option value="not_interested">Not Interested</option>
                        <option value="appointment_booked">Appointment Booked</option>
                        <option value="follow_up_needed">Follow-up Needed</option>
                        <option value="converted">Converted</option>
                        <option value="lost">Lost</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Duration</label>
                    <input type="text" class="form-input" id="callDuration" placeholder="e.g. 02:30">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" class="form-input" id="callDate">
                </div>
                <div class="form-group">
                    <label class="form-label">Time</label>
                    <input type="time" class="form-input" id="callTime">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Call Notes</label>
                <textarea class="form-textarea" id="callNotes" rows="3"
                    placeholder="What was discussed? Key points from this call..." maxlength="500"></textarea>
                <div class="char-count"><span id="callNoteCount">0</span>/500</div>
            </div>
            <div class="form-group">
                <label class="form-label">
                    <input type="checkbox" id="scheduleNextFollowup" checked>
                    Schedule next follow-up after this call
                </label>
            </div>
            <div id="nextFollowupSection" class="followup-inline-section">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Next Follow-up Date</label>
                        <input type="date" class="form-input" id="nextFollowupDate">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Time</label>
                        <input type="time" class="form-input" id="nextFollowupTime" value="10:00">
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModal('logCallModal')">Cancel</button>
            <button class="btn-primary" onclick="saveCallLog()">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Save Call Log
            </button>
        </div>
    </div>
</div>
