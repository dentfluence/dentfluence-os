{{-- ══ GLOBAL CREATE TASK MODAL ══════════════════════════════════════════════ --}}
{{-- Trigger: window.dispatchEvent(new CustomEvent('open-create-task', { detail: { patient_id, patient_name, category } }))
     category is optional — defaults to 'admin' if omitted. Pass 'maintenance' to
     land directly on the Maintenance Details fields (e.g. from Failures/Maintenance's
     "+ Add New Issue"). --}}
@php
    $ctmUsers = \App\Models\User::where('branch_id', auth()->user()->branch_id)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id','name']);
@endphp

<div
    x-data="{
        open: false,
        category: 'admin',
        isRecurring: false,
        linkedPatient: false,

        /* ── patient search (inlined to avoid nested x-data scope issues) ── */
        ptQuery: '',
        ptId: '',
        ptResults: [],
        async ptSearch() {
            if (this.ptQuery.length < 2) { this.ptResults = []; return; }
            try {
                const r = await fetch('/patients/search?q=' + encodeURIComponent(this.ptQuery),
                                      { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                this.ptResults = await r.json();
            } catch(e) { this.ptResults = []; }
        },
        ptPick(p) {
            this.ptId      = p.id;
            this.ptQuery   = p.name + (p.patient_id ? ' (' + p.patient_id + ')' : '');
            this.ptResults = [];
        },
        ptClear() { this.ptId = ''; this.ptQuery = ''; this.ptResults = []; },

        /* ── open handler ── */
        init() {
            window.addEventListener('open-create-task', e => {
                this.open          = true;
                this.category      = (e.detail && e.detail.category) ? e.detail.category : 'admin';
                this.isRecurring   = false;
                this.ptId          = '';
                this.ptQuery       = '';
                this.ptResults     = [];
                if (e.detail && e.detail.patient_id) {
                    this.linkedPatient = true;
                    this.ptId          = e.detail.patient_id;
                    this.ptQuery       = e.detail.patient_name || '';
                } else {
                    this.linkedPatient = false;
                }
            });
        }
    }"
    x-show="open"
    x-cloak
    @keydown.escape.window="open = false"
    @click.outside="ptResults = []"
    style="position:fixed;inset:0;z-index:9999;"
>
    {{-- Backdrop --}}
    <div style="position:absolute;inset:0;background:rgba(14,1,24,.45);" @click="open=false"></div>

    {{-- Modal card --}}
    <div @click.stop style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:90%;max-width:500px;background:#fff;border-radius:14px;box-shadow:0 8px 40px rgba(14,1,24,.22);overflow-y:auto;max-height:92vh;padding:28px;z-index:1;">

        {{-- Header --}}
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;">
            <h2 style="font-family:'Cormorant Garamond',serif;font-size:21px;font-weight:700;color:#1a0320;margin:0;">Create a Task</h2>
            <button @click="open=false" style="background:none;border:none;cursor:pointer;color:#9a7aaa;padding:4px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <form action="{{ route('tasks.store') }}" method="POST">
            @csrf

            {{-- Task title --}}
            <div style="margin-bottom:14px;">
                <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Task *</label>
                <input type="text" name="title" required placeholder="What needs to be done?"
                       style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
            </div>

            {{-- Assign To --}}
            <div style="margin-bottom:14px;">
                <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Assign To *</label>
                <select name="assigned_to" required
                        style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;color:#1a0320;box-sizing:border-box;">
                    <option value="">— Select —</option>
                    @foreach($ctmUsers as $u)
                        <option value="{{ $u->id }}" {{ $u->id == auth()->id() ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Due Date + Priority --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Due Date *</label>
                    <input type="date" name="due_date" required value="{{ today()->toDateString() }}"
                           style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;-webkit-appearance:none;appearance:none;background:#fff;color:#1a0320;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Priority</label>
                    <select name="priority"
                            style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                        <option value="urgent">Urgent</option>
                        <option value="high">High</option>
                        <option value="medium" selected>Medium</option>
                        <option value="low">Low</option>
                    </select>
                </div>
            </div>

            {{-- Category + Due Time --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Category</label>
                    <select name="category" x-model="category"
                            style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                        <optgroup label="— Communication —">
                            <option value="call">Call</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="follow_up">Follow-up</option>
                        </optgroup>
                        <optgroup label="— Internal —">
                            <option value="admin" selected>Admin</option>
                            <option value="clinical">Clinical</option>
                            <option value="lab">Lab</option>
                            <option value="maintenance">Maintenance / AMC</option>
                            <option value="other">Other</option>
                        </optgroup>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Due Time</label>
                    <input type="time" name="due_time"
                           style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                </div>
            </div>

            {{-- Maintenance fields --}}
            <div x-show="category === 'maintenance'" x-cloak
                 style="background:#fff9ec;border:1.5px solid #ffe8a0;border-radius:9px;padding:14px 16px;margin-bottom:14px;">
                <div style="font-size:11.5px;font-weight:700;color:#7a5c00;margin-bottom:12px;letter-spacing:.06em;text-transform:uppercase;">Maintenance Details</div>
                <select name="maintenance_type"
                        style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;margin-bottom:10px;">
                    <option value="">— Select type —</option>
                    <option value="ac_service">AC Service</option>
                    <option value="pest_control">Pest Control</option>
                    <option value="deep_cleaning">Deep Cleaning</option>
                    <option value="autoclave">Autoclave Maintenance</option>
                    <option value="dental_chair">Dental Chair Servicing</option>
                    <option value="xray_machine">X-Ray Machine</option>
                    <option value="water_purifier">Water Purifier</option>
                    <option value="fire_safety">Fire Safety Check</option>
                    <option value="generator">Generator / UPS</option>
                    <option value="other">Other</option>
                </select>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                    <label style="display:inline-flex;align-items:center;cursor:pointer;">
                        <input type="checkbox" name="is_recurring" value="1" x-model="isRecurring" style="opacity:0;width:0;height:0;position:absolute;">
                        <div :style="isRecurring ? 'width:36px;height:20px;border-radius:10px;background:#6a0f70;display:inline-block;position:relative;cursor:pointer;' : 'width:36px;height:20px;border-radius:10px;background:#e0d5e8;display:inline-block;position:relative;cursor:pointer;'">
                            <div :style="isRecurring ? 'position:absolute;top:3px;left:3px;width:14px;height:14px;border-radius:50%;background:#fff;transform:translateX(16px);box-shadow:0 1px 3px rgba(0,0,0,.18);' : 'position:absolute;top:3px;left:3px;width:14px;height:14px;border-radius:50%;background:#fff;transform:translateX(0);box-shadow:0 1px 3px rgba(0,0,0,.18);'"></div>
                        </div>
                    </label>
                    <span style="font-size:13px;color:#1a0320;">Recurring (auto-schedule next)</span>
                </div>
                <div x-show="isRecurring" x-cloak style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Every</label>
                        <input type="number" name="recurrence_interval" min="1" max="365" placeholder="e.g. 3"
                               style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Unit</label>
                        <select name="recurrence_unit" style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                            <option value="days">Day(s)</option>
                            <option value="weeks">Week(s)</option>
                            <option value="months" selected>Month(s)</option>
                            <option value="years">Year(s)</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Patient link toggle --}}
            <div style="margin-bottom:20px;background:#f8f4fc;border:1.5px solid #ede4f3;border-radius:9px;padding:12px 14px;">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <span style="font-size:13px;font-weight:600;color:#1a0320;">Related to a patient?</span>
                    <label style="display:inline-flex;align-items:center;cursor:pointer;">
                        <input type="checkbox" x-model="linkedPatient" style="opacity:0;width:0;height:0;position:absolute;">
                        <div :style="linkedPatient ? 'width:36px;height:20px;border-radius:10px;background:#6a0f70;display:inline-block;position:relative;cursor:pointer;' : 'width:36px;height:20px;border-radius:10px;background:#e0d5e8;display:inline-block;position:relative;cursor:pointer;'">
                            <div :style="linkedPatient ? 'position:absolute;top:3px;left:3px;width:14px;height:14px;border-radius:50%;background:#fff;transform:translateX(16px);box-shadow:0 1px 3px rgba(0,0,0,.18);' : 'position:absolute;top:3px;left:3px;width:14px;height:14px;border-radius:50%;background:#fff;transform:translateX(0);box-shadow:0 1px 3px rgba(0,0,0,.18);'"></div>
                        </div>
                    </label>
                </div>

                {{-- Patient search — no nested x-data, uses parent scope directly --}}
                <div x-show="linkedPatient" x-cloak style="margin-top:12px;">
                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Select Patient</label>
                    <input type="hidden" name="patient_id" x-model="ptId">
                    <div style="position:relative;">
                        <input type="text"
                               x-model="ptQuery"
                               @input.debounce.300ms="ptSearch()"
                               @focus="if(ptQuery.length >= 2) ptSearch()"
                               placeholder="Search by name or phone…"
                               autocomplete="off"
                               style="width:100%;padding:10px 13px;border:1.5px solid #b39bc8;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;background:#fff;">
                        {{-- Dropdown results --}}
                        <div x-show="ptResults.length > 0"
                             style="position:absolute;top:calc(100% + 4px);left:0;right:0;background:#fff;border:1.5px solid #d4bde8;border-radius:8px;box-shadow:0 4px 18px rgba(14,1,24,.13);z-index:200;max-height:200px;overflow-y:auto;">
                            <template x-for="p in ptResults" :key="p.id">
                                <div @click="ptPick(p)"
                                     style="padding:9px 13px;cursor:pointer;border-bottom:1px solid #f3eef7;display:flex;align-items:center;gap:10px;"
                                     @mouseenter="$el.style.background='#f5eefa'"
                                     @mouseleave="$el.style.background='#fff'">
                                    <div style="width:30px;height:30px;border-radius:50%;background:#6a0f7020;color:#6a0f70;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;"
                                         x-text="p.name.charAt(0).toUpperCase()"></div>
                                    <div>
                                        <div style="font-size:13px;font-weight:600;color:#1a0320;" x-text="p.name"></div>
                                        <div style="font-size:11.5px;color:#9a7aaa;" x-text="(p.patient_id ?? '') + (p.phone ? ' · ' + p.phone : '')"></div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                    {{-- Linked confirmation --}}
                    <div x-show="ptId" style="margin-top:5px;font-size:11.5px;color:#6a0f70;display:flex;align-items:center;gap:5px;">
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        <span x-text="'Linked: ' + ptQuery"></span>
                        <span @click="ptClear()" style="cursor:pointer;color:#b52020;margin-left:4px;">✕</span>
                    </div>
                </div>

                {{-- Contact name/type — Call/WhatsApp with no patient linked
                     (2026-07-08). Lets a Call/WhatsApp task be about a vendor,
                     lab, or doctor instead of a patient — surfaces on Today's
                     Actions as a "logged_communications" row. See
                     docs/feature-specs/feature-spec-manual-add-call.md. --}}
                <div x-show="!linkedPatient && (category === 'call' || category === 'whatsapp')" x-cloak style="margin-top:12px;">
                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Contact</label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <input type="text" name="contact_name"
                               :required="!linkedPatient && (category === 'call' || category === 'whatsapp')"
                               placeholder="e.g. ABC Dental Lab, Dr. Mehta"
                               style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                        <select name="contact_type"
                                style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                            <option value="vendor">Vendor</option>
                            <option value="lab">Lab</option>
                            <option value="consultant">Doctor</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Submit --}}
            <button type="submit"
                    style="width:100%;padding:12px;background:#6a0f70;color:#fff;border:none;border-radius:7px;font-size:13.5px;font-weight:600;cursor:pointer;font-family:inherit;">
                Assign Task
            </button>
        </form>
    </div>
</div>
