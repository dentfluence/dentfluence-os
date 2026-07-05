{{-- ══ GLOBAL "TODAY'S PATIENT FLOW" QUICK-ACTION CARD ════════════════════════ --}}
{{-- Trigger: window.dispatchEvent(new CustomEvent('open-today-flow-card', { detail: {
        appointmentId, patientId, patientName, isWalkin,
        instruction, prepItem, amountToCollect, chairsideAssistantId
     } }))
     Replaces the old behaviour of navigating straight to the patient profile
     when a card in Huddle's "Today's Patient Flow" column is clicked (and the
     old inline click-to-edit note, folded into this one popup). The patient
     NAME inside the card still opens the full profile directly (@click.stop).
     "Amount to Collect" only applies to scheduled appointments — walk-ins
     aren't planned ahead of time, so there's nothing to prep a figure against. --}}
@php
    $tfcUsers = \App\Models\User::where('branch_id', auth()->user()->branch_id)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name']);
@endphp

<div
    x-data="{
        open: false,
        appointmentId: null,
        patientName: '',
        isWalkin: false,
        instruction: '',
        prepItem: '',
        amountToCollect: '',
        chairsideAssistantId: '',
        saving: false,
        error: '',

        init() {
            window.addEventListener('open-today-flow-card', e => {
                this.open                 = true;
                this.appointmentId        = e.detail.appointmentId;
                this.patientName          = e.detail.patientName || '';
                this.isWalkin             = !!e.detail.isWalkin;
                this.instruction          = e.detail.instruction || '';
                this.prepItem             = e.detail.prepItem || '';
                this.amountToCollect      = e.detail.amountToCollect ?? '';
                this.chairsideAssistantId = e.detail.chairsideAssistantId || '';
                this.error                = '';
            });
        },

        async save() {
            this.saving = true;
            this.error  = '';
            try {
                const r = await fetch('{{ url('/huddle/appointments') }}/' + this.appointmentId + '/instruction', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify({
                        staff_instruction: this.instruction,
                        prep_item: this.prepItem,
                        amount_to_collect: this.isWalkin ? null : (this.amountToCollect === '' ? null : this.amountToCollect),
                        chairside_assistant_id: this.chairsideAssistantId || null
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
                window.location.reload();
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
            <h2 style="font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:700;color:#1a0320;margin:0;">Today's Patient Flow</h2>
            <button @click="open=false" style="background:none;border:none;cursor:pointer;color:#9a7aaa;padding:4px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <p style="font-size:13px;color:#6a0f70;font-weight:600;margin:0 0 18px;" x-text="patientName"></p>

        {{-- Notes --}}
        <div style="margin-bottom:14px;">
            <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Notes</label>
            <textarea x-model="instruction" rows="2" placeholder="Anything the front desk / chairside team should know…"
                      style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;resize:vertical;"></textarea>
        </div>

        {{-- Amount to Collect — scheduled appointments only --}}
        <div style="margin-bottom:14px;">
            <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Amount to Collect</label>
            <template x-if="!isWalkin">
                <input type="number" min="0" x-model="amountToCollect" placeholder="e.g. 2500"
                       style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
            </template>
            <template x-if="isWalkin">
                <p style="font-size:12px;color:#9a7aaa;margin:0;">Not tracked for walk-ins — this wasn't planned ahead of time.</p>
            </template>
        </div>

        {{-- Essential item / task --}}
        <div style="margin-bottom:14px;">
            <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Essential Item / Task</label>
            <input type="text" x-model="prepItem" placeholder="e.g. Carry surgical kit, OPG needed"
                   style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
        </div>

        {{-- Chairside assistant --}}
        <div style="margin-bottom:8px;">
            <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Chairside Assistant</label>
            <select x-model="chairsideAssistantId"
                    style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;color:#1a0320;box-sizing:border-box;">
                <option value="">— Unassigned —</option>
                @foreach($tfcUsers as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
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
