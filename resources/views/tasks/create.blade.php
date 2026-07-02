@extends('layouts.app')

@section('title', 'New Task')

@push('scripts')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

    #df-content-inner { font-family: 'Inter', sans-serif; }

    /* ── Page Header ─────────────────────────────────────── */
    .ct-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 28px;
    }
    .ct-back {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 8px;
        background: #f5eefa;
        border: 1px solid #e8d5f0;
        color: #6a0f70;
        text-decoration: none;
        flex-shrink: 0;
        transition: background 0.15s;
    }
    .ct-back:hover { background: #e8d5f0; }
    .ct-page-title {
        font-size: 22px;
        font-weight: 700;
        color: #380740;
        letter-spacing: -0.3px;
    }
    .ct-page-sub {
        font-size: 13px;
        color: #9a6aaa;
        margin-top: 1px;
    }

    /* ── Form Card ───────────────────────────────────────── */
    .ct-card {
        background: #fff;
        border: 1px solid #e8d5f0;
        border-radius: 14px;
        padding: 32px 36px;
        max-width: 760px;
    }

    /* ── Field Groups ────────────────────────────────────── */
    .ct-row {
        display: grid;
        gap: 20px;
        margin-bottom: 20px;
    }
    .ct-row.cols-2 { grid-template-columns: 1fr 1fr; }
    .ct-row.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
    .ct-row.cols-1 { grid-template-columns: 1fr; }

    .ct-field { display: flex; flex-direction: column; gap: 6px; }

    .ct-label {
        font-size: 12.5px;
        font-weight: 600;
        color: #6a0f70;
        letter-spacing: 0.15px;
    }
    .ct-label span { color: #b91c1c; margin-left: 1px; }

    .ct-input,
    .ct-select,
    .ct-textarea {
        font-family: 'Inter', sans-serif;
        font-size: 14px;
        color: #1f1232;
        background: #faf5fd;
        border: 1px solid #e8d5f0;
        border-radius: 9px;
        padding: 10px 13px;
        outline: none;
        transition: border-color 0.15s, box-shadow 0.15s;
        width: 100%;
        box-sizing: border-box;
    }
    .ct-input:focus,
    .ct-select:focus,
    .ct-textarea:focus {
        border-color: #6a0f70;
        box-shadow: 0 0 0 3px rgba(106,15,112,0.08);
    }
    .ct-input::placeholder,
    .ct-textarea::placeholder { color: #c4a0d4; }

    .ct-textarea { resize: vertical; min-height: 88px; }

    .ct-select { appearance: none; -webkit-appearance: none; cursor: pointer; padding-right: 34px; }
    .ct-select-wrap { position: relative; }
    .ct-select-wrap::after {
        content: '';
        position: absolute;
        right: 13px;
        top: 50%;
        transform: translateY(-50%);
        width: 0;
        height: 0;
        border-left: 4px solid transparent;
        border-right: 4px solid transparent;
        border-top: 5px solid #9a6aaa;
        pointer-events: none;
    }

    /* Error messages */
    .ct-error {
        font-size: 11.5px;
        color: #b91c1c;
        margin-top: 2px;
    }

    /* Patient search */
    .ct-patient-results {
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid #e8d5f0;
        border-radius: 9px;
        box-shadow: 0 8px 24px rgba(56,7,64,0.1);
        z-index: 100;
        max-height: 200px;
        overflow-y: auto;
    }
    .ct-patient-result-item {
        padding: 9px 13px;
        font-size: 13.5px;
        color: #1f1232;
        cursor: pointer;
        transition: background 0.1s;
        border-bottom: 1px solid #f5eefa;
    }
    .ct-patient-result-item:last-child { border-bottom: none; }
    .ct-patient-result-item:hover { background: #f5eefa; }
    .ct-patient-result-item small { color: #9a6aaa; font-size: 11.5px; display: block; }

    .ct-patient-selected {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #f5eefa;
        border: 1px solid #e8d5f0;
        border-radius: 9px;
        padding: 9px 13px;
        font-size: 13.5px;
        color: #380740;
        font-weight: 500;
    }
    .ct-patient-clear {
        background: none;
        border: none;
        color: #9a6aaa;
        cursor: pointer;
        font-size: 16px;
        line-height: 1;
        padding: 0 2px;
    }
    .ct-patient-clear:hover { color: #b91c1c; }

    /* Section divider */
    .ct-divider {
        border: none;
        border-top: 1px solid #f0e6f7;
        margin: 24px 0;
    }
    .ct-section-label {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.8px;
        text-transform: uppercase;
        color: #c4a0d4;
        margin-bottom: 16px;
    }

    /* Priority option colours */
    .ct-priority-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 8px;
    }
    .ct-priority-opt {
        display: none;
    }
    .ct-priority-opt + label {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        padding: 10px 8px;
        border-radius: 9px;
        border: 2px solid #e8d5f0;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        color: #9a6aaa;
        transition: border-color 0.15s, background 0.15s;
        text-align: center;
    }
    .ct-priority-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
    }
    .ct-priority-opt[value="urgent"] + label .ct-priority-dot { background: #ef4444; }
    .ct-priority-opt[value="high"]   + label .ct-priority-dot { background: #f59e0b; }
    .ct-priority-opt[value="medium"] + label .ct-priority-dot { background: #3b82f6; }
    .ct-priority-opt[value="low"]    + label .ct-priority-dot { background: #22c55e; }

    .ct-priority-opt:checked + label {
        border-color: #6a0f70;
        background: #f5eefa;
        color: #380740;
    }
    .ct-priority-opt[value="urgent"]:checked + label { border-color: #ef4444; background: #fee2e2; color: #b91c1c; }
    .ct-priority-opt[value="high"]:checked   + label { border-color: #f59e0b; background: #fef3c7; color: #b45309; }
    .ct-priority-opt[value="medium"]:checked + label { border-color: #3b82f6; background: #dbeafe; color: #1e40af; }
    .ct-priority-opt[value="low"]:checked    + label { border-color: #22c55e; background: #dcfce7; color: #166534; }

    /* Submit row */
    .ct-actions {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-top: 28px;
    }
    .ct-btn-submit {
        font-family: 'Inter', sans-serif;
        font-size: 14px;
        font-weight: 700;
        color: #fff;
        background: #380740;
        border: none;
        border-radius: 9px;
        padding: 11px 28px;
        cursor: pointer;
        transition: background 0.15s;
        display: inline-flex;
        align-items: center;
        gap: 7px;
    }
    .ct-btn-submit:hover { background: #6a0f70; }
    .ct-btn-cancel {
        font-family: 'Inter', sans-serif;
        font-size: 14px;
        font-weight: 500;
        color: #9a6aaa;
        background: transparent;
        border: 1px solid #e8d5f0;
        border-radius: 9px;
        padding: 11px 20px;
        text-decoration: none;
        transition: background 0.15s;
    }
    .ct-btn-cancel:hover { background: #f5eefa; color: #380740; }

    @media (max-width: 640px) {
        .ct-card { padding: 22px 18px; }
        .ct-row.cols-2,
        .ct-row.cols-3 { grid-template-columns: 1fr; }
        .ct-priority-grid { grid-template-columns: repeat(2, 1fr); }
    }
</style>
@endpush

@section('content')
<div x-data="createTask()">

    {{-- ── Page Header ──────────────────────────────────── --}}
    <div class="ct-header">
        <a href="{{ route('tasks.index') }}" class="ct-back" title="Back to tasks">
            <svg width="16" height="16" style="display:inline-block!important;width:16px;height:16px;" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 12L6 8l4-4" stroke="#6a0f70" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </a>
        <div>
            <div class="ct-page-title">New Task</div>
            <div class="ct-page-sub">Assign and schedule a task for your branch</div>
        </div>
    </div>

    {{-- ── Form ─────────────────────────────────────────── --}}
    <div class="ct-card">
        <form action="{{ route('tasks.store') }}" method="POST">
            @csrf

            {{-- Title --}}
            <div class="ct-section-label">Task Details</div>
            <div class="ct-row cols-1">
                <div class="ct-field">
                    <label class="ct-label" for="title">Title <span>*</span></label>
                    <input
                        id="title"
                        name="title"
                        type="text"
                        class="ct-input"
                        placeholder="e.g. Call patient to confirm tomorrow's appointment"
                        value="{{ old('title') }}"
                        required
                        autofocus
                    >
                    @error('title')
                        <div class="ct-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- Description --}}
            <div class="ct-row cols-1">
                <div class="ct-field">
                    <label class="ct-label" for="description">Description</label>
                    <textarea
                        id="description"
                        name="description"
                        class="ct-textarea"
                        placeholder="Add any additional notes or context..."
                    >{{ old('description') }}</textarea>
                    @error('description')
                        <div class="ct-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <hr class="ct-divider">
            <div class="ct-section-label">Assignment</div>

            {{-- Assign To + Patient --}}
            <div class="ct-row cols-2">
                <div class="ct-field">
                    <label class="ct-label" for="assigned_to">Assign To</label>
                    <div class="ct-select-wrap">
                        <select id="assigned_to" name="assigned_to" class="ct-select">
                            <option value="">— Unassigned —</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" {{ old('assigned_to') == $u->id ? 'selected' : '' }}>
                                    {{ $u->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @error('assigned_to')
                        <div class="ct-error">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Patient Link --}}
                <div class="ct-field" style="position:relative;">
                    <label class="ct-label" for="patient_search">Link Patient <span style="color:#c4a0d4;font-weight:400;">(optional)</span></label>

                    {{-- Hidden input that holds the actual patient ID --}}
                    <input type="hidden" name="patient_id" x-model="patient.id">

                    {{-- Selected state --}}
                    <div x-show="patient.id" class="ct-patient-selected">
                        <span x-text="patient.name"></span>
                        <button type="button" class="ct-patient-clear" @click="clearPatient()" title="Remove patient">×</button>
                    </div>

                    {{-- Search input --}}
                    <div x-show="!patient.id" style="position:relative;">
                        <input
                            id="patient_search"
                            type="text"
                            class="ct-input"
                            placeholder="Search by name or phone…"
                            x-model="patient.query"
                            @input.debounce.300ms="searchPatients()"
                            @focus="patient.showResults = patient.results.length > 0"
                            autocomplete="off"
                        >
                        <div
                            x-show="patient.showResults && patient.results.length > 0"
                            class="ct-patient-results"
                        >
                            <template x-for="p in patient.results" :key="p.id">
                                <div class="ct-patient-result-item" @click="selectPatient(p)">
                                    <span x-text="p.name"></span>
                                    <small x-text="p.phone"></small>
                                </div>
                            </template>
                        </div>
                        <div
                            x-show="patient.query.length > 1 && patient.results.length === 0 && !patient.loading"
                            class="ct-patient-results"
                        >
                            <div class="ct-patient-result-item" style="color:#c4a0d4;cursor:default;">No patients found</div>
                        </div>
                    </div>

                    @error('patient_id')
                        <div class="ct-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <hr class="ct-divider">
            <div class="ct-section-label">Scheduling</div>

            {{-- Due Date + Due Time --}}
            <div class="ct-row cols-2">
                <div class="ct-field">
                    <label class="ct-label" for="due_date">Due Date <span>*</span></label>
                    <input
                        id="due_date"
                        name="due_date"
                        type="date"
                        class="ct-input"
                        value="{{ old('due_date', now()->toDateString()) }}"
                        required
                    >
                    @error('due_date')
                        <div class="ct-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="ct-field">
                    <label class="ct-label" for="due_time">Due Time <span style="color:#c4a0d4;font-weight:400;">(optional)</span></label>
                    <input
                        id="due_time"
                        name="due_time"
                        type="time"
                        class="ct-input"
                        value="{{ old('due_time') }}"
                    >
                    @error('due_time')
                        <div class="ct-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <hr class="ct-divider">
            <div class="ct-section-label">Classification</div>

            {{-- Priority --}}
            <div class="ct-row cols-1" style="margin-bottom:20px;">
                <div class="ct-field">
                    <label class="ct-label">Priority <span>*</span></label>
                    <div class="ct-priority-grid">
                        @foreach(['urgent','high','medium','low'] as $p)
                            <div>
                                <input
                                    type="radio"
                                    class="ct-priority-opt"
                                    id="priority_{{ $p }}"
                                    name="priority"
                                    value="{{ $p }}"
                                    {{ old('priority', 'medium') === $p ? 'checked' : '' }}
                                >
                                <label for="priority_{{ $p }}">
                                    <span class="ct-priority-dot"></span>
                                    {{ ucfirst($p) }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('priority')
                        <div class="ct-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- Category --}}
            <div class="ct-row cols-1">
                <div class="ct-field">
                    <label class="ct-label" for="category">Category <span>*</span></label>
                    <div class="ct-select-wrap">
                        <select id="category" name="category" class="ct-select">
                            <option value="admin"     {{ old('category','admin') === 'admin'     ? 'selected' : '' }}>Admin</option>
                            <option value="clinical"  {{ old('category') === 'clinical'  ? 'selected' : '' }}>Clinical</option>
                            <option value="lab"       {{ old('category') === 'lab'       ? 'selected' : '' }}>Lab</option>
                            <option value="follow_up" {{ old('category') === 'follow_up' ? 'selected' : '' }}>Follow Up</option>
                            <option value="other"     {{ old('category') === 'other'     ? 'selected' : '' }}>Other</option>
                        </select>
                    </div>
                    @error('category')
                        <div class="ct-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- Actions --}}
            <div class="ct-actions">
                <button type="submit" class="ct-btn-submit">
                    <svg width="14" height="14" style="display:inline-block!important;width:14px;height:14px;" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M2.5 7.5l3 3 6-6" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Create Task
                </button>
                <a href="{{ route('tasks.index') }}" class="ct-btn-cancel">Cancel</a>
            </div>

        </form>
    </div>

</div>
@endsection

@push('scripts')
<script>
function createTask() {
    return {
        patient: {
            id: '{{ old('patient_id') }}',
            name: '',
            query: '',
            results: [],
            showResults: false,
            loading: false,
        },

        async searchPatients() {
            const q = this.patient.query.trim();
            if (q.length < 2) {
                this.patient.results = [];
                this.patient.showResults = false;
                return;
            }
            this.patient.loading = true;
            try {
                const res = await fetch(`/patients/search?q=${encodeURIComponent(q)}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                this.patient.results = data;
                this.patient.showResults = true;
            } catch {
                this.patient.results = [];
            }
            this.patient.loading = false;
        },

        selectPatient(p) {
            this.patient.id = p.id;
            this.patient.name = p.name + (p.phone ? ' · ' + p.phone : '');
            this.patient.query = '';
            this.patient.results = [];
            this.patient.showResults = false;
        },

        clearPatient() {
            this.patient.id = '';
            this.patient.name = '';
            this.patient.query = '';
        },
    };
}
</script>
@endpush
