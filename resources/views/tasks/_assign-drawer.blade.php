{{-- Assign Task drawer — lifted verbatim out of tasks/index.blade.php when the
     list was rebuilt as rows (Task Manager V2). The create form itself is
     unchanged; only its home moved, so the new index stays readable. --}}
<div x-show="drawerOpen" x-cloak style="position:fixed;inset:0;z-index:60;">
    {{-- Backdrop --}}
    <div style="position:absolute;inset:0;background:rgba(14,1,24,.45);" @click="drawerOpen=false"></div>
    {{-- Modal card — centred with transform --}}
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:90%;max-width:500px;background:#fff;border-radius:14px;box-shadow:0 8px 40px rgba(14,1,24,.22);overflow-y:auto;max-height:90vh;padding:28px;z-index:1;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
            <h2 style="font-family:'Cormorant Garamond',serif;font-size:21px;font-weight:700;color:#1a0320;margin:0;">Create a Task</h2>
            <button @click="drawerOpen=false" style="background:none;border:none;cursor:pointer;color:#9a7aaa;padding:4px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form action="{{ route('tasks.store') }}" method="POST" x-data="drawerForm()">
            @csrf
            <div style="margin-bottom:14px;">
                <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Task *</label>
                <input type="text" name="title" required placeholder="What needs to be done?" style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
            </div>
            <div style="margin-bottom:14px;">
                <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Assign To *</label>
                <select name="assigned_to" required style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;color:#1a0320;box-sizing:border-box;">
                    <option value="">— Select —</option>
                    @foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Due Date *</label>
                    <input type="date" name="due_date" required value="{{ today()->toDateString() }}" style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Priority</label>
                    <select name="priority" style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                        <option value="urgent">Urgent</option><option value="high">High</option><option value="medium" selected>Medium</option><option value="low">Low</option>
                    </select>
                </div>
            </div>
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
                    <input type="time" name="due_time" style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                </div>
            </div>

            {{-- ── MAINTENANCE FIELDS (only when category = maintenance) ── --}}
            <div x-show="category === 'maintenance'" x-cloak
                 style="background:#fff9ec;border:1.5px solid #ffe8a0;border-radius:9px;padding:14px 16px;margin-bottom:14px;">
                <div style="font-size:11.5px;font-weight:700;color:#7a5c00;margin-bottom:12px;letter-spacing:.06em;text-transform:uppercase;">
                    Maintenance Details
                </div>

                {{-- Maintenance sub-type --}}
                <div style="margin-bottom:12px;">
                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Type of Maintenance</label>
                    <select name="maintenance_type" style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                        <option value="">— Select type —</option>
                        {{-- Clinic's own list (Tasks > Settings), not a frozen
                             set of hard-coded options. --}}
                        @foreach(\App\Models\Task::maintenanceTypeOptions() as $mtKey => $mtLabel)
                            <option value="{{ $mtKey }}">{{ $mtLabel }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Recurring toggle --}}
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                    <label style="display:inline-flex;align-items:center;cursor:pointer;">
                        <div style="position:relative;">
                            <input type="checkbox" name="is_recurring" value="1"
                                   x-model="isRecurring"
                                   style="opacity:0;width:0;height:0;position:absolute;">
                            <div :style="isRecurring
                                    ? 'width:36px;height:20px;border-radius:10px;background:#6a0f70;display:inline-block;position:relative;transition:background 180ms;cursor:pointer;'
                                    : 'width:36px;height:20px;border-radius:10px;background:#e0d5e8;display:inline-block;position:relative;transition:background 180ms;cursor:pointer;'">
                                <div :style="isRecurring
                                        ? 'position:absolute;top:3px;left:3px;width:14px;height:14px;border-radius:50%;background:#fff;transition:transform 180ms;transform:translateX(16px);box-shadow:0 1px 3px rgba(0,0,0,.18);'
                                        : 'position:absolute;top:3px;left:3px;width:14px;height:14px;border-radius:50%;background:#fff;transition:transform 180ms;transform:translateX(0);box-shadow:0 1px 3px rgba(0,0,0,.18);'">
                                </div>
                            </div>
                        </div>
                    </label>
                    <span style="font-size:13px;color:#1a0320;">Recurring (auto-schedule next)</span>
                </div>

                {{-- Recurrence interval — only when recurring --}}
                <div x-show="isRecurring" x-cloak style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Every</label>
                        <input type="number" name="recurrence_interval" min="1" max="365"
                               placeholder="e.g. 3"
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
            {{-- ── /MAINTENANCE FIELDS ── --}}

            {{-- ── PATIENT LINK ── --}}
            <div style="margin-bottom:14px;background:#f8f4fc;border:1.5px solid #ede4f3;border-radius:9px;padding:12px 14px;">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <span style="font-size:13px;font-weight:600;color:#1a0320;">Related to a patient?</span>
                    <label style="display:inline-flex;align-items:center;cursor:pointer;">
                        <input type="checkbox" x-model="linkedPatient" style="opacity:0;width:0;height:0;position:absolute;">
                        <div :style="linkedPatient
                                ? 'width:36px;height:20px;border-radius:10px;background:#6a0f70;display:inline-block;position:relative;transition:background 180ms;cursor:pointer;'
                                : 'width:36px;height:20px;border-radius:10px;background:#e0d5e8;display:inline-block;position:relative;transition:background 180ms;cursor:pointer;'">
                            <div :style="linkedPatient
                                    ? 'position:absolute;top:3px;left:3px;width:14px;height:14px;border-radius:50%;background:#fff;transition:transform 180ms;transform:translateX(16px);box-shadow:0 1px 3px rgba(0,0,0,.18);'
                                    : 'position:absolute;top:3px;left:3px;width:14px;height:14px;border-radius:50%;background:#fff;transition:transform 180ms;transform:translateX(0);box-shadow:0 1px 3px rgba(0,0,0,.18);'">
                            </div>
                        </div>
                    </label>
                </div>
                <div x-show="linkedPatient" x-cloak style="margin-top:12px;"
                     x-data="patientSearch()" @click.outside="results=[]">
                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Select Patient *</label>
                    <input type="hidden" name="patient_id" x-model="selectedId">
                    <div style="position:relative;">
                        <input type="text" name="patient_search" x-model="query"
                               @input.debounce.300ms="search()"
                               @focus="search()"
                               placeholder="Search by name or phone…"
                               autocomplete="off"
                               style="width:100%;padding:10px 13px;border:1.5px solid #b39bc8;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;background:#fff;">
                        <div x-show="results.length > 0"
                             style="position:absolute;top:calc(100% + 4px);left:0;right:0;background:#fff;border:1.5px solid #d4bde8;border-radius:8px;box-shadow:0 4px 18px rgba(14,1,24,.13);z-index:99;max-height:220px;overflow-y:auto;">
                            <template x-for="p in results" :key="p.id">
                                <div @click="pick(p)"
                                     style="padding:9px 13px;cursor:pointer;border-bottom:1px solid #f3eef7;display:flex;align-items:center;gap:10px;"
                                     @mouseenter="$el.style.background='#f5eefa'" @mouseleave="$el.style.background='#fff'">
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
                    <div x-show="selectedId" style="margin-top:6px;font-size:11.5px;color:#6a0f70;display:flex;align-items:center;gap:6px;">
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        <span x-text="'Patient linked: ' + query"></span>
                        <span @click="selectedId='';query=''" style="cursor:pointer;color:#b52020;margin-left:4px;">✕</span>
                    </div>
                </div>
            </div>
            {{-- ── /PATIENT LINK ── --}}
            <div style="margin-bottom:20px;"></div>
            <button type="submit" style="width:100%;padding:12px;background:#6a0f70;color:#fff;border:none;border-radius:7px;font-size:13.5px;font-weight:600;cursor:pointer;font-family:inherit;">Assign Task</button>
        </form>
    </div>
</div>

<script>
// Create-form state: category drives the maintenance/recurring block, and
// whether the task is linked to a patient.
function drawerForm(){
    return { category: 'admin', isRecurring: false, linkedPatient: false };
}

// Patient autocomplete used by the create form.
function patientSearch(){
    return {
        query: '', selectedId: '', results: [],
        async search(){
            if(this.query.length < 2){ this.results = []; return; }
            try {
                const res = await fetch(`/patients/search?q=${encodeURIComponent(this.query)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                this.results = await res.json();
            } catch(e){ this.results = []; }
        },
        pick(p){
            this.selectedId = p.id;
            this.query      = p.name + (p.patient_id ? ' (' + p.patient_id + ')' : '');
            this.results    = [];
        },
    };
}
</script>
