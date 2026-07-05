{{-- Add Communication — PRM Update 2026-06-13 --}}
@extends('layouts.communication')

@push('communication-styles')
<style>
/* ── Add Communication Form ── */
.add-comm { max-width:760px; margin:0 auto; padding:24px 24px 48px; font-family:var(--comm-font,'Inter',sans-serif); }
.add-comm__back { display:inline-flex; align-items:center; gap:6px; font-size:12px; color:#64748b; text-decoration:none; margin-bottom:16px; }
.add-comm__back:hover { color:#6a0f70; }
.add-comm__title { font-size:18px; font-weight:600; color:#0f172a; margin:0 0 4px; }
.add-comm__sub { font-size:12px; color:#94a3b8; margin:0 0 24px; }

/* Cards */
.add-comm__card { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:20px; margin-bottom:16px; }
.add-comm__card-title { font-size:11px; font-weight:700; color:#6a0f70; text-transform:uppercase; letter-spacing:.08em; margin:0 0 14px; }

/* Form fields */
.add-comm__row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.add-comm__row--3 { grid-template-columns:1fr 1fr 1fr; }
.add-comm__row--full { grid-template-columns:1fr; }
.add-comm__field { display:flex; flex-direction:column; gap:4px; }
.add-comm__label { font-size:11px; font-weight:600; color:#374151; }
.add-comm__label--req::after { content:' *'; color:#dc2626; }
.add-comm__input, .add-comm__select, .add-comm__textarea {
    border:1px solid #e2e8f0; border-radius:6px; padding:8px 12px;
    font-size:13px; outline:none; transition:border-color .15s; background:#fff;
}
.add-comm__input:focus, .add-comm__select:focus, .add-comm__textarea:focus { border-color:#6a0f70; }
.add-comm__textarea { resize:vertical; min-height:80px; }

/* Patient lookup */
.patient-lookup { position:relative; }
.patient-lookup__results { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #e2e8f0; border-radius:6px; box-shadow:0 4px 16px rgba(0,0,0,.1); z-index:40; max-height:220px; overflow-y:auto; margin-top:2px; }
.patient-lookup__item { padding:9px 14px; font-size:13px; cursor:pointer; border-bottom:1px solid #f1f5f9; color:#374151; }
.patient-lookup__item:hover { background:#f8f4fa; }
.patient-lookup__item:last-child { border-bottom:none; }
.patient-lookup__loading { padding:10px 14px; font-size:12px; color:#94a3b8; }
.patient-lookup__selected { display:flex; align-items:center; justify-content:space-between; padding:7px 12px; background:#f8f4fa; border:1px solid #d8b4e2; border-radius:6px; font-size:13px; color:#4e0b52; }

/* Button groups */
.add-comm__btn-group { display:flex; flex-wrap:wrap; gap:8px; }
.add-comm__toggle-btn { padding:7px 14px; font-size:12px; font-weight:500; border:1px solid #e2e8f0; border-radius:6px; cursor:pointer; background:#fff; color:#374151; transition:all .15s; }
.add-comm__toggle-btn:hover { border-color:#6a0f70; color:#6a0f70; }
.add-comm__toggle-btn.is-active { background:#6a0f70; color:#fff; border-color:#6a0f70; }
.add-comm__toggle-btn--lg { padding:10px 18px; font-size:13px; }

/* Direction toggle */
.add-comm__dir-group { display:flex; gap:0; }
.add-comm__dir-btn { flex:1; padding:8px; text-align:center; font-size:13px; font-weight:500; border:1px solid #e2e8f0; cursor:pointer; background:#fff; color:#374151; transition:all .15s; }
.add-comm__dir-btn:first-child { border-radius:6px 0 0 6px; }
.add-comm__dir-btn:last-child  { border-radius:0 6px 6px 0; border-left:none; }
.add-comm__dir-btn.is-active.incoming { background:#eff6ff; color:#2563eb; border-color:#bfdbfe; }
.add-comm__dir-btn.is-active.outgoing { background:#f0fdf4; color:#15803d; border-color:#bbf7d0; }

/* Priority */
.add-comm__priority-high.is-active   { background:#fff1f2 !important; color:#dc2626 !important; border-color:#dc2626 !important; }
.add-comm__priority-medium.is-active { background:#fffbeb !important; color:#d97706 !important; border-color:#d97706 !important; }
.add-comm__priority-low.is-active    { background:#f1f5f9 !important; color:#475569 !important; border-color:#94a3b8 !important; }

/* Move To */
.add-comm__move-btn { padding:10px 18px; font-size:12px; font-weight:500; border:2px solid #e2e8f0; border-radius:8px; cursor:pointer; background:#fff; color:#374151; transition:all .15s; }
.add-comm__move-btn:hover { border-color:#6a0f70; color:#6a0f70; }
.add-comm__move-btn.is-active { background:#6a0f70; color:#fff; border-color:#6a0f70; }

/* Actions */
.add-comm__actions { display:flex; gap:10px; justify-content:flex-end; margin-top:8px; }
.add-comm__submit { padding:10px 28px; font-size:14px; font-weight:600; background:#6a0f70; color:#fff; border:none; border-radius:7px; cursor:pointer; transition:background .15s; }
.add-comm__submit:hover { background:#4e0b52; }
.add-comm__submit:disabled { opacity:.6; cursor:not-allowed; }
.add-comm__cancel { padding:10px 20px; font-size:14px; color:#64748b; background:#fff; border:1px solid #e2e8f0; border-radius:7px; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; }
.add-comm__cancel:hover { background:#f9fafb; }
.add-comm__error { background:#fff1f2; border:1px solid #fecaca; color:#dc2626; padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:12px; }
</style>
@endpush

@section('communication-content')
<div class="add-comm" x-data="{
    commType: '',
    channel: '',
    direction: 'incoming',
    nextAction: '',
    priority: 'medium',
    moveTo: '',
    patientQuery: '',
    patientResults: [],
    patientLoading: false,
    selectedPatient: null,
    searchTimer: null,

    needsPatient() {
        return ['existing_patient','ongoing_treatment'].includes(this.commType);
    },

    searchPatients(q) {
        if (q.length < 2) { this.patientResults = []; return; }
        clearTimeout(this.searchTimer);
        this.patientLoading = true;
        this.searchTimer = setTimeout(() => {
            fetch('{{ route('communication.manager.patient.search') }}?q=' + encodeURIComponent(q), {
                headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
            })
            .then(r => r.json())
            .then(data => { this.patientResults = data; this.patientLoading = false; })
            .catch(() => { this.patientLoading = false; });
        }, 300);
    },

    selectPatient(p) {
        this.selectedPatient = p;
        this.patientResults = [];
        this.patientQuery = p.label;
        const nameEl = document.getElementById('person_name');
        const phoneEl = document.getElementById('phone');
        if (nameEl && !nameEl.value) nameEl.value = p.name;
        if (phoneEl && !phoneEl.value) phoneEl.value = p.phone;
    },

    clearPatient() {
        this.selectedPatient = null;
        this.patientQuery = '';
    }
}" x-init="priority='medium'">

    <a href="{{ route('communication.manager.index') }}" class="add-comm__back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Communication List
    </a>

    <h1 class="add-comm__title">Add Communication</h1>
    <p class="add-comm__sub">Log a new communication. Target: under 20 seconds.</p>

    @if($errors->any())
        <div class="add-comm__error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('communication.manager.log.store') }}">
        @csrf

        {{-- ── Card 1: Contact ── --}}
        <div class="add-comm__card">
            <p class="add-comm__card-title">1. Contact</p>
            <div class="add-comm__row">
                <div class="add-comm__field">
                    <label class="add-comm__label add-comm__label--req" for="phone">Mobile Number</label>
                    <input type="tel" id="phone" name="phone"
                        class="add-comm__input"
                        value="{{ old('phone') }}"
                        placeholder="e.g. 9876543210"
                        autocomplete="off">
                </div>
                <div class="add-comm__field">
                    <label class="add-comm__label add-comm__label--req" for="person_name">Name</label>
                    <input type="text" id="person_name" name="person_name"
                        class="add-comm__input"
                        value="{{ old('person_name') }}"
                        placeholder="Patient / caller name"
                        autocomplete="off">
                </div>
            </div>
        </div>

        {{-- ── Card 2: Communication Type ── --}}
        <div class="add-comm__card">
            <p class="add-comm__card-title">2. Communication Type <span style="color:#dc2626;">*</span></p>
            <input type="hidden" name="comm_type" :value="commType">
            <div class="add-comm__btn-group">
                @foreach($commTypes as $key => $label)
                <button type="button"
                    class="add-comm__toggle-btn"
                    :class="commType === '{{ $key }}' ? 'is-active' : ''"
                    @click="commType = '{{ $key }}'">
                    {{ $label }}
                </button>
                @endforeach
            </div>

            {{-- Patient lookup — shown for existing patient / ongoing treatment --}}
            <div x-show="needsPatient()" x-transition style="display:none;margin-top:14px;">
                <div class="add-comm__field">
                    <label class="add-comm__label">Link to Patient</label>
                    <p style="font-size:11px;color:#94a3b8;margin:0 0 6px;">Search by name, mobile, or patient ID.</p>

                    <div x-show="!selectedPatient" class="patient-lookup">
                        <input type="text" class="add-comm__input"
                            placeholder="Type to search patient…"
                            x-model="patientQuery"
                            @input="searchPatients($event.target.value)"
                            autocomplete="off">
                        <div class="patient-lookup__results" x-show="patientResults.length > 0 || patientLoading">
                            <div x-show="patientLoading" class="patient-lookup__loading">Searching…</div>
                            <template x-for="p in patientResults" :key="p.id">
                                <div class="patient-lookup__item" @click="selectPatient(p)" x-text="p.label"></div>
                            </template>
                        </div>
                    </div>

                    <div x-show="selectedPatient" style="display:none;">
                        <div class="patient-lookup__selected">
                            <span x-text="selectedPatient ? selectedPatient.label : ''"></span>
                            <button type="button" @click="clearPatient()"
                                style="background:none;border:none;cursor:pointer;color:#94a3b8;font-size:18px;padding:0 0 0 8px;">×</button>
                        </div>
                        <input type="hidden" name="patient_id" :value="selectedPatient ? selectedPatient.id : ''">
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Card 3: Channel & Direction ── --}}
        <div class="add-comm__card">
            <p class="add-comm__card-title">3. Channel & Direction <span style="color:#dc2626;">*</span></p>

            <div class="add-comm__field" style="margin-bottom:14px;">
                <label class="add-comm__label">Channel</label>
                <input type="hidden" name="channel" :value="channel">
                <div class="add-comm__btn-group">
                    @foreach($channels as $key => $label)
                    <button type="button" class="add-comm__toggle-btn"
                        :class="channel === '{{ $key }}' ? 'is-active' : ''"
                        @click="channel = '{{ $key }}'">
                        {{ $label }}
                    </button>
                    @endforeach
                </div>
            </div>

            <div class="add-comm__field">
                <label class="add-comm__label">Direction</label>
                <input type="hidden" name="direction" :value="direction">
                <div class="add-comm__dir-group" style="max-width:260px;">
                    <button type="button"
                        class="add-comm__dir-btn incoming"
                        :class="direction === 'incoming' ? 'is-active incoming' : ''"
                        @click="direction='incoming'">
                        ↓ Incoming
                    </button>
                    <button type="button"
                        class="add-comm__dir-btn outgoing"
                        :class="direction === 'outgoing' ? 'is-active outgoing' : ''"
                        @click="direction='outgoing'">
                        ↑ Outgoing
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Card 4: Purpose & Note ── --}}
        <div class="add-comm__card">
            <p class="add-comm__card-title">4. Purpose & Note</p>
            <div class="add-comm__row" style="margin-bottom:14px;">
                <div class="add-comm__field">
                    <label class="add-comm__label" for="purpose">Purpose</label>
                    <select id="purpose" name="purpose" class="add-comm__select">
                        <option value="">Select purpose…</option>
                        @foreach($purposes as $key => $label)
                            <option value="{{ $key }}" {{ old('purpose') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="add-comm__field">
                <label class="add-comm__label" for="note">Note</label>
                <textarea id="note" name="note" class="add-comm__textarea"
                    placeholder="Quick note — what was discussed?">{{ old('note') }}</textarea>
            </div>
        </div>

        {{-- ── Card 5: Next Action ── --}}
        <div class="add-comm__card">
            <p class="add-comm__card-title">5. Next Action</p>
            <input type="hidden" name="next_action" :value="nextAction">
            <div class="add-comm__btn-group">
                @foreach($nextActions as $key => $label)
                <button type="button"
                    class="add-comm__toggle-btn add-comm__toggle-btn--lg"
                    :class="nextAction === '{{ $key }}' ? 'is-active' : ''"
                    @click="nextAction = (nextAction === '{{ $key }}') ? '' : '{{ $key }}'">
                    {{ $label }}
                </button>
                @endforeach
            </div>

            {{-- Follow-up date/time — shown unless no follow-up needed --}}
            <div x-show="nextAction && nextAction !== 'close' && nextAction !== 'wait'" x-transition
                 style="display:none;margin-top:14px;">
                <div class="add-comm__row">
                    <div class="add-comm__field">
                        <label class="add-comm__label">Follow-up Date</label>
                        <input type="date" name="follow_up_date" class="add-comm__input"
                            value="{{ old('follow_up_date') }}"
                            min="{{ date('Y-m-d') }}">
                    </div>
                    <div class="add-comm__field">
                        <label class="add-comm__label">Follow-up Time</label>
                        <input type="time" name="follow_up_time" class="add-comm__input"
                            value="{{ old('follow_up_time') }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Card 6: Priority & Assignment ── --}}
        <div class="add-comm__card">
            <p class="add-comm__card-title">6. Priority & Assignment</p>
            <div class="add-comm__row">
                <div class="add-comm__field">
                    <label class="add-comm__label">Priority</label>
                    <input type="hidden" name="priority" :value="priority">
                    <div class="add-comm__btn-group">
                        <button type="button"
                            class="add-comm__toggle-btn add-comm__priority-high"
                            :class="priority === 'high' ? 'is-active' : ''"
                            @click="priority='high'">High</button>
                        <button type="button"
                            class="add-comm__toggle-btn add-comm__priority-medium"
                            :class="priority === 'medium' ? 'is-active' : ''"
                            @click="priority='medium'">Medium</button>
                        <button type="button"
                            class="add-comm__toggle-btn add-comm__priority-low"
                            :class="priority === 'low' ? 'is-active' : ''"
                            @click="priority='low'">Low</button>
                    </div>
                </div>

                <div class="add-comm__field">
                    <label class="add-comm__label" for="assigned_to">Assign To</label>
                    <select id="assigned_to" name="assigned_to" class="add-comm__select">
                        <option value="">— Me ({{ auth()->user()->name }}) —</option>
                        @foreach($users as $u)
                            <option value="{{ $u->name }}" {{ old('assigned_to') === $u->name ? 'selected' : '' }}>
                                {{ $u->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- ── Card 7: Move To ── --}}
        <div class="add-comm__card" style="border-color:#6a0f70;border-width:1.5px;">
            <p class="add-comm__card-title">7. Move To <span style="color:#dc2626;">*</span></p>
            <p style="font-size:11px;color:#94a3b8;margin:-8px 0 12px;">Where should this go after logging?</p>
            <input type="hidden" name="move_to" :value="moveTo">
            <div class="add-comm__btn-group">
                <button type="button" class="add-comm__move-btn"
                    :class="moveTo === 'stay' ? 'is-active' : ''"
                    @click="moveTo='stay'">
                    Stay in List
                </button>
                <button type="button" class="add-comm__move-btn"
                    :class="moveTo === 'prm_pipeline' ? 'is-active' : ''"
                    @click="moveTo='prm_pipeline'">
                    Lead Pipeline
                </button>
                <button type="button" class="add-comm__move-btn"
                    :class="moveTo === 'follow_ups' ? 'is-active' : ''"
                    @click="moveTo='follow_ups'">
                    Follow-ups
                </button>
                <button type="button" class="add-comm__move-btn"
                    :class="moveTo === 'calendar' ? 'is-active' : ''"
                    @click="moveTo='calendar'">
                    Calendar
                </button>
                <button type="button" class="add-comm__move-btn"
                    :class="moveTo === 'task' ? 'is-active' : ''"
                    @click="moveTo='task'">
                    Create Task
                </button>
                <button type="button" class="add-comm__move-btn"
                    :class="moveTo === 'archive' ? 'is-active' : ''"
                    @click="moveTo='archive'">
                    Archive
                </button>
            </div>
            <p x-show="!moveTo" style="display:none;margin-top:8px;font-size:12px;color:#dc2626;">
                Please select where to move this communication.
            </p>
        </div>

        {{-- ── Submit ── --}}
        <div class="add-comm__actions">
            <a href="{{ route('communication.manager.index') }}" class="add-comm__cancel">Cancel</a>
            <button type="submit" class="add-comm__submit"
                :disabled="!commType || !channel || !moveTo"
                @click.prevent="
                    if (!commType) { alert('Please select a Communication Type.'); return; }
                    if (!channel)  { alert('Please select a Channel.'); return; }
                    if (!moveTo)   { alert('Please select where to Move To.'); return; }
                    $el.closest('form').submit();
                ">
                Log Communication
            </button>
        </div>

    </form>
</div>
@endsection
