@extends('layouts.app')

@section('content')
<div
    x-data="taskPage()"
    x-init="init()"
    class="max-w-5xl mx-auto px-4 py-8"
    style="font-family:'DM Sans',sans-serif"
>

    {{-- ── Page Header ──────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">All Tasks</h1>
            <p class="text-sm text-gray-500 mt-0.5">Branch overview · {{ today()->format('d M Y') }}</p>
        </div>
        <button @click="drawerOpen = true"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white shadow-sm hover:opacity-90"
                style="background:#6a0f70">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            New Task
        </button>
    </div>

    {{-- ── Flash ────────────────────────────────────────────────────────────── --}}
    @if(session('success'))
    <div class="mb-4 px-4 py-3 rounded-lg bg-green-50 text-green-700 text-sm border border-green-200">
        {{ session('success') }}
    </div>
    @endif

    {{-- ── Filter Bar ───────────────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('tasks.index') }}"
          class="flex flex-wrap gap-3 mb-8 p-4 bg-white rounded-xl border border-gray-100 shadow-sm">

        <input type="date" name="date" value="{{ request('date') }}"
               class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-400">

        <select name="priority" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-400">
            <option value="">All Priorities</option>
            <option value="urgent" @selected(request('priority')=='urgent')>🔴 Urgent</option>
            <option value="high"   @selected(request('priority')=='high')>🟠 High</option>
            <option value="medium" @selected(request('priority')=='medium')>🟡 Medium</option>
            <option value="low"    @selected(request('priority')=='low')>🟢 Low</option>
        </select>

        <select name="assigned_to" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-400">
            <option value="">All Staff</option>
            @foreach($users as $u)
            <option value="{{ $u->id }}" @selected(request('assigned_to')==$u->id)>{{ $u->name }}</option>
            @endforeach
        </select>

        <select name="status" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-400">
            <option value="">All Statuses</option>
            <option value="pending"   @selected(request('status')=='pending')>Pending</option>
            <option value="done"      @selected(request('status')=='done')>Done</option>
            <option value="escalated" @selected(request('status')=='escalated')>Escalated</option>
        </select>

        <button type="submit" class="px-4 py-2 text-sm font-medium text-white rounded-lg" style="background:#6a0f70">Filter</button>

        @if(request()->anyFilled(['date','priority','assigned_to','status']))
        <a href="{{ route('tasks.index') }}" class="px-4 py-2 text-sm text-gray-500 rounded-lg border border-gray-200 hover:bg-gray-50">Clear</a>
        @endif
    </form>

    {{-- ── Overdue ───────────────────────────────────────────────────────────── --}}
    @if($overdue->count())
    <div class="mb-6">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-red-600 mb-3 flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span>
            Overdue · {{ $overdue->count() }}
        </h2>
        <div class="space-y-2">
            @foreach($overdue as $task)
                @include('tasks._task_row', ['task' => $task])
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── Today ─────────────────────────────────────────────────────────────── --}}
    <div class="mb-6">
        <h2 class="text-xs font-semibold uppercase tracking-wider mb-3 flex items-center gap-2" style="color:#6a0f70">
            <span class="w-2 h-2 rounded-full inline-block" style="background:#6a0f70"></span>
            Today · <span id="today-count">{{ $today->count() }}</span>
        </h2>
        <div class="space-y-2" id="list-today">
            @forelse($today as $task)
                @include('tasks._task_row', ['task' => $task])
            @empty
                <p class="text-sm text-gray-400 py-4 text-center" id="today-empty">No tasks due today.</p>
            @endforelse
        </div>
    </div>

    {{-- ── Upcoming ──────────────────────────────────────────────────────────── --}}
    @if($upcoming->count())
    <div class="mb-6">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-blue-600 mb-3 flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-blue-400 inline-block"></span>
            Upcoming · {{ $upcoming->count() }}
        </h2>
        <div class="space-y-2" id="list-upcoming">
            @foreach($upcoming as $task)
                @include('tasks._task_row', ['task' => $task])
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── Done ─────────────────────────────────────────────────────────────── --}}
    @if($done->count())
    <div class="mb-6">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3 flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-gray-300 inline-block"></span>
            Done · {{ $done->count() }}
        </h2>
        <div class="space-y-2 opacity-60">
            @foreach($done as $task)
                @include('tasks._task_row', ['task' => $task])
            @endforeach
        </div>
    </div>
    @endif

    {{-- ════════════════════════════════════════════════════════════════════
         SLIDE-IN DRAWER  — everything in the SAME x-data scope
    ═══════════════════════════════════════════════════════════════════════ --}}

    {{-- Backdrop --}}
    <div x-show="drawerOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="drawerOpen = false"
         class="fixed inset-0 bg-black/40 z-40"
         x-cloak></div>

    {{-- Panel --}}
    <div x-show="drawerOpen"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         @click.stop
         class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 flex flex-col"
         x-cloak>

        {{-- Drawer Header --}}
        <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100 shrink-0">
            <h2 class="text-lg font-semibold text-gray-900">New Task</h2>
            <button @click="drawerOpen = false" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Scrollable Form Body --}}
        <form @submit.prevent="submitTask()" class="flex flex-col flex-1 overflow-y-auto px-6 py-6 gap-5">

            {{-- Error banner --}}
            <div x-show="formError" x-text="formError" x-cloak
                 class="px-4 py-3 rounded-lg bg-red-50 text-red-700 text-sm border border-red-200"></div>

            {{-- Title --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">Task Title <span class="text-red-400">*</span></label>
                <input type="text" x-model="form.title" placeholder="e.g. Call patient Mrs. Sharma"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400" required>
            </div>

            {{-- Description --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">Description</label>
                <textarea x-model="form.description" rows="2" placeholder="Any notes…"
                          class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400 resize-none"></textarea>
            </div>

            {{-- Assign + Priority --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Assign To <span class="text-red-400">*</span></label>
                    <select x-model="form.assigned_to" required
                            class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400">
                        <option value="">Select…</option>
                        @foreach($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Priority <span class="text-red-400">*</span></label>
                    <select x-model="form.priority" required
                            class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400">
                        <option value="medium">🟡 Medium</option>
                        <option value="low">🟢 Low</option>
                        <option value="high">🟠 High</option>
                        <option value="urgent">🔴 Urgent</option>
                    </select>
                </div>
            </div>

            {{-- Due Date + Category --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Due Date <span class="text-red-400">*</span></label>
                    <input type="date" x-model="form.due_date" required
                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Category</label>
                    <select x-model="form.category"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400">
                        <option value="admin">Admin</option>
                        <option value="clinical">Clinical</option>
                        <option value="lab">Lab</option>
                        <option value="follow_up">Follow-up</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>

            {{-- ── Patient Linkage ──────────────────────────────────────────── --}}
            <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-medium text-gray-600">Link to Patient?</span>
                    <div class="flex rounded-lg overflow-hidden border border-gray-200 text-xs font-medium">
                        <button type="button"
                                @click="linkPatient = false; clearPatient()"
                                :class="!linkPatient ? 'bg-gray-700 text-white' : 'bg-white text-gray-500'"
                                class="px-4 py-1.5 transition-colors">No</button>
                        <button type="button"
                                @click="linkPatient = true"
                                :style="linkPatient ? 'background:#6a0f70;color:white' : ''"
                                :class="!linkPatient ? 'bg-white text-gray-500' : ''"
                                class="px-4 py-1.5 transition-colors">Yes</button>
                    </div>
                </div>

                <div x-show="linkPatient" x-cloak>

                    {{-- Selected patient chip --}}
                    <template x-if="selectedPatient">
                        <div class="flex items-center justify-between px-3 py-2 rounded-lg border border-purple-200 bg-purple-50">
                            <div>
                                <p class="text-sm font-medium text-gray-900" x-text="selectedPatient.name"></p>
                                <p class="text-xs text-gray-500" x-text="selectedPatient.phone ?? '—'"></p>
                            </div>
                            <button type="button" @click="clearPatient()"
                                    class="text-gray-400 hover:text-red-500 text-xl leading-none ml-3">&times;</button>
                        </div>
                    </template>

                    {{-- Search box --}}
                    <template x-if="!selectedPatient">
                        <div class="relative">
                            <input type="text"
                                   x-model="patientQuery"
                                   @input.debounce.350ms="searchPatients()"
                                   placeholder="Search by name or phone…"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400 bg-white">

                            {{-- spinner --}}
                            <div x-show="patientSearching" x-cloak
                                 class="absolute right-3 top-3 w-4 h-4 border-2 border-purple-400 border-t-transparent rounded-full animate-spin"></div>

                            {{-- results --}}
                            <div x-show="patientResults.length > 0" x-cloak
                                 class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                <template x-for="p in patientResults" :key="p.id">
                                    <button type="button" @click="selectPatient(p)"
                                            class="w-full text-left px-4 py-2.5 hover:bg-purple-50 border-b border-gray-50 last:border-0">
                                        <p class="text-sm font-medium text-gray-900" x-text="p.name"></p>
                                        <p class="text-xs text-gray-400" x-text="p.phone ?? '—'"></p>
                                    </button>
                                </template>
                            </div>

                            {{-- no results --}}
                            <div x-show="patientNoResults" x-cloak
                                 class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow px-4 py-3 text-sm text-gray-400">
                                No patients found.
                            </div>
                        </div>
                    </template>

                </div>
            </div>
            {{-- /patient linkage --}}

            {{-- Action buttons — pinned to bottom --}}
            <div class="flex gap-3 pt-2">
                <button type="button" @click="drawerOpen = false"
                        class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" :disabled="submitting"
                        class="flex-1 px-4 py-2.5 text-sm font-medium text-white rounded-lg disabled:opacity-60"
                        style="background:#6a0f70">
                    <span x-text="submitting ? 'Saving…' : 'Create Task'">Create Task</span>
                </button>
            </div>

        </form>
    </div>
    {{-- /drawer panel --}}

</div>{{-- /x-data --}}
@endsection


@push('scripts')
<script>
function taskPage() {
    return {
        // drawer
        drawerOpen: false,

        // form data
        form: {
            title:       '',
            description: '',
            assigned_to: '',
            due_date:    '',
            priority:    'medium',
            category:    'admin',
            patient_id:  null,
        },
        submitting: false,
        formError:  '',

        // patient search
        linkPatient:      false,
        patientQuery:     '',
        patientResults:   [],
        patientNoResults: false,
        patientSearching: false,
        selectedPatient:  null,

        init() {
            this.form.due_date = new Date().toISOString().slice(0, 10);
        },

        // ── patient search ────────────────────────────────────────────────────
        async searchPatients() {
            const q = this.patientQuery.trim();
            if (q.length < 2) { this.patientResults = []; this.patientNoResults = false; return; }
            this.patientSearching = true;
            this.patientNoResults = false;
            try {
                const res  = await fetch(`/patients/search?q=${encodeURIComponent(q)}`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() }
                });
                const data = await res.json();
                this.patientResults   = data;
                this.patientNoResults = data.length === 0;
            } catch(e) {
                this.patientResults = [];
            } finally {
                this.patientSearching = false;
            }
        },

        selectPatient(p) {
            this.selectedPatient  = p;
            this.form.patient_id  = p.id;
            this.patientResults   = [];
            this.patientNoResults = false;
        },

        clearPatient() {
            this.selectedPatient = null;
            this.form.patient_id = null;
            this.patientQuery    = '';
            this.patientResults  = [];
            this.patientNoResults= false;
        },

        // ── submit ────────────────────────────────────────────────────────────
        async submitTask() {
            this.submitting = true;
            this.formError  = '';
            try {
                const res  = await fetch('{{ route("tasks.store") }}', {
                    method:  'POST',
                    headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': csrf() },
                    body:    JSON.stringify(this.form),
                });
                const data = await res.json();
                if (!res.ok) {
                    const msgs = data.errors ? Object.values(data.errors).flat() : [];
                    this.formError = msgs[0] ?? data.message ?? 'Something went wrong.';
                    return;
                }
                this.drawerOpen = false;
                this.insertNewTask(data.task);
                this.resetForm();
            } catch(e) {
                this.formError = 'Network error — please try again.';
            } finally {
                this.submitting = false;
            }
        },

        // ── inject new row ────────────────────────────────────────────────────
        insertNewTask(task) {
            const today  = new Date().toISOString().slice(0, 10);
            const listId = task.due_date_ts === today ? 'list-today' : 'list-upcoming';
            const list   = document.getElementById(listId);
            if (!list) return;
            document.getElementById('today-empty')?.remove();

            const badge = { urgent:'bg-red-100 text-red-700', high:'bg-orange-100 text-orange-700',
                            medium:'bg-yellow-100 text-yellow-700', low:'bg-green-100 text-green-700' };
            const bc    = badge[task.priority] ?? badge.medium;

            const row = document.createElement('div');
            row.className = 'flex items-start justify-between bg-white border border-gray-100 rounded-xl px-4 py-3 shadow-sm';
            row.dataset.taskId = task.id;
            row.innerHTML = `
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">${esc(task.title)}</p>
                    <p class="text-xs text-gray-400 mt-0.5">
                        ${esc(task.assigned_to)} · ${esc(task.due_date)}
                        ${task.patient_name ? ` · <span class="text-purple-600 font-medium">👤 ${esc(task.patient_name)}</span>` : ''}
                    </p>
                </div>
                <div class="flex items-center gap-2 ml-4 shrink-0">
                    <span class="text-xs font-medium px-2 py-0.5 rounded-full ${bc}">${esc(task.priority)}</span>
                    <button onclick="markDone(${task.id},this)"
                            class="text-xs text-gray-400 hover:text-green-600 px-2 py-1 rounded border border-gray-200 hover:border-green-300">Done</button>
                </div>`;
            list.prepend(row);

            if (listId === 'list-today') {
                const ctr = document.getElementById('today-count');
                if (ctr) ctr.textContent = parseInt(ctr.textContent || 0) + 1;
            }
            row.style.transition = 'background 0.7s';
            row.style.background = '#f3e8f4';
            setTimeout(() => { row.style.background = ''; }, 900);
        },

        resetForm() {
            this.form = { title:'', description:'', assigned_to:'', due_date: new Date().toISOString().slice(0,10), priority:'medium', category:'admin', patient_id:null };
            this.clearPatient();
            this.linkPatient = false;
            this.formError   = '';
        },
    };
}

async function markDone(taskId, btn) {
    btn.disabled = true; btn.textContent = '…';
    try {
        const res = await fetch(`/tasks/${taskId}/done`, {
            method: 'PATCH',
            headers: { 'Accept':'application/json', 'X-CSRF-TOKEN': csrf() }
        });
        if (res.ok) {
            const row = btn.closest('[data-task-id]');
            if (row) { row.style.transition='opacity 0.4s'; row.style.opacity='0'; setTimeout(()=>row.remove(),400); }
        }
    } catch(e) { btn.disabled=false; btn.textContent='Done'; }
}

function csrf() { return document.querySelector('meta[name="csrf-token"]').content; }
function esc(s) { return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>
@endpush