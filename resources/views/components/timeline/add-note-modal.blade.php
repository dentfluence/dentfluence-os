{{-- resources/views/components/timeline/add-note-modal.blade.php --}}
<div class="modal-overlay" id="addNoteModal" style="display:none;">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Add Note</h3>
            <button class="modal-close" onclick="closeModal('addNoteModal')">
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
            <div class="form-group">
                <label class="form-label">Note Type <span class="req">*</span></label>
                <select class="form-select" id="noteType">
                    <option value="general">General Note</option>
                    <option value="call-note">Call Note</option>
                    <option value="treatment">Treatment Note</option>
                    <option value="internal">Internal Note</option>
                    <option value="complaint">Complaint</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Note <span class="req">*</span></label>
                <textarea class="form-textarea" id="noteText" rows="4"
                    placeholder="Enter note details..." maxlength="1000"></textarea>
                <div class="char-count"><span id="noteCount">0</span>/1000</div>
            </div>
            <div class="form-group">
                <label class="form-label">Visibility</label>
                <div class="radio-group">
                    <label class="radio-label">
                        <input type="radio" name="noteVisibility" value="team" checked> My Team
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="noteVisibility" value="me"> Only Me
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="noteVisibility" value="all"> Everyone
                    </label>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModal('addNoteModal')">Cancel</button>
            <button class="btn-primary" onclick="saveNote()">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Save Note
            </button>
        </div>
    </div>
</div>
