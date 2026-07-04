{{-- ══ GLOBAL "YESTERDAY'S FLOW" QUICK-ACTION CARD ═══════════════════════════ --}}
{{-- Trigger: window.dispatchEvent(new CustomEvent('open-yesterday-followup-card', { detail: { patientId, patientName } }))
     Replaces the old behaviour of navigating straight to the patient profile
     when a card in Huddle's "Yesterday's Flow" column is clicked. The patient
     NAME inside this card still opens the full profile (@click.stop on the link). --}}
@php
    $yfcUsers = \App\Models\User::where('branch_id', auth()->user()->branch_id)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name']);
@endphp

<div
    x-data="{
        open: false,
        patientId: null,
        patientName: '',
        task: '',
        bookCall: false,
        date: '{{ now()->toDateString() }}',
        reason: '',
        assignedTo: '{{ auth()->id() }}',
        saving: false,
        error: '',

        init() {
            window.addEventListener('open-yesterday-followup-card', e => {
                this.open        = true;
                this.patientId   = e.detail.patientId;
                this.patientName = e.detail.patientName || '';
                this.task        = '';
                this.bookCall    = false;
                this.date        = '{{ now()->toDateString() }}';
                this.reason      = '';
                this.assignedTo  = '{{ auth()->id() }}';
                this.error       = '';
            });
        },

        async save() {
            if (!this.task.trim() && !this.bookCall) {
                this.error = 'Add a task or tick \'Book Follow-up call\' before saving.';
                return;
            }
            this.saving = true;
            this.error  = '';
            try {
                const r = await fetch('{{ route('huddle.yesterday-flow.log') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify({
                        patient_id: this.patientId,
                        task: this.task,
                        book_followup_call: this.bookCall,
                        date: this.bookCall ? this.date : null,
                        reason: this.reason,
                        assigned_to: this.assignedTo
                    })
                });
                const body = await r.json().catch(() => ({}));
                if (!r.ok) {
                    this.error = body.message || 'Could not save. Please try again.';
                    this.saving = false;
                    return;
                }
                this.saving = false;
                this.open   = false;
            } catch (e) {
                this.error  = 'Network error. Please check your connection.';
                this.saving = false;
            }
        }
    }"
    x-show="open"
    x-cloak
    @keydown.escape.window="open = false"
    style="position:fixed;inset:0;z-index:9999;"
>
    {{-- Backdrop --}}
    <div style="position:absolute;inset:0;background:rgba(14,1,24,.45);" @click="open=false"></div>

    {{-- Card --}}
    <div @click.stop style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:90%;max-width:440px;background:#fff;border-radius:14px;box-shadow:0 8px 40px rgba(14,1,24,.22);overflow-y:auto;max-height:92vh;padding:26px;z-index:1;">

        {{-- Header --}}
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
            <h2 style="font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:700;color:#1a0320;margin:0;">Yesterday's Flow</h2>
            <button @click="open=false" style="background:none;border:none;cursor:pointer;color:#9a7aaa;padding:4px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <p style="font-size:13px;color:#6a0f70;font-weight:600;margin:0 0 18px;" x-text="patientName"></p>

        {{-- Task --}}
        <div style="margin-bottom:14px;">
            <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Task</label>
            <input type="text" x-model="task" placeholder="What needs to be done? (optional)"
                   style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
        </div>

        {{-- Book Follow-up call --}}
        <div style="margin-bottom:14px;background:#f8f4fc;border:1.5px solid #ede4f3;border-radius:9px;padding:12px 14px;">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:13px;font-weight:600;color:#1a0320;">Book Follow-up call?</span>
                <label style="display:inline-flex;align-items:center;cursor:pointer;">
                    <input type="checkbox" x-model="bookCall" style="opacity:0;width:0;height:0;position:absolute;">
                    <div :style="bookCall ? 'width:36px;height:20px;border-radius:10px;background:#6a0f70;display:inline-block;position:relative;cursor:pointer;' : 'width:36px;height:20px;border-radius:10px;background:#e0d5e8;display:inline-block;position:relative;cursor:pointer;'">
                        <div :style="bookCall ? 'position:absolute;top:3px;left:3px;width:14px;height:14px;border-radius:50%;background:#fff;transform:translateX(16px);box-shadow:0 1px 3px rgba(0,0,0,.18);' : 'position:absolute;top:3px;left:3px;width:14px;height:14px;border-radius:50%;background:#fff;transform:translateX(0);box-shadow:0 1px 3px rgba(0,0,0,.18);'"></div>
                    </div>
                </label>
            </div>
            <div x-show="bookCall" x-cloak style="margin-top:12px;">
                <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Date</label>
                <input type="date" x-model="date" :min="'{{ now()->toDateString() }}'"
                       style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
            </div>
        </div>

        {{-- Reason --}}
        <div style="margin-bottom:14px;">
            <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Reason</label>
            <textarea x-model="reason" rows="2" placeholder="Why does this need follow-up?"
                      style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;resize:vertical;"></textarea>
        </div>

        {{-- Assign To --}}
        <div style="margin-bottom:8px;">
            <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Assign To *</label>
            <select x-model="assignedTo"
                    style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;color:#1a0320;box-sizing:border-box;">
                @foreach($yfcUsers as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
            <p style="font-size:11px;color:#9a7aaa;margin:5px 0 0;">Whoever this is assigned to will see it on their task list.</p>
        </div>

        {{-- Error --}}
        <p x-show="error" x-text="error" style="color:#c0392b;font-size:12px;margin:6px 0;"></p>

        {{-- Actions --}}
        <div style="display:flex;gap:10px;margin-top:16px;">
            <button @click="save()" :disabled="saving"
                    :style="saving ? 'opacity:.6;' : ''"
                    style="flex:1;padding:12px;background:#6a0f70;color:#fff;border:none;border-radius:7px;font-size:13.5px;font-weight:600;cursor:pointer;font-family:inherit;">
                <span x-show="!saving">Save</span>
                <span x-show="saving">Saving…</span>
            </button>
            <button type="button" @click="open=false"
                    style="padding:12px 20px;background:#f3eef7;color:#6a0f70;border:none;border-radius:7px;font-size:13.5px;font-weight:600;cursor:pointer;font-family:inherit;">
                Cancel
            </button>
        </div>
    </div>
</div>
