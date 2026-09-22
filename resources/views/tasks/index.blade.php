{{--
    Tasks — the staff work list. Rebuilt as rows (Task Manager V2).

    WHY ROWS AND NOT CARDS: the previous screen rendered one large card per
    task, so four or five tasks filled the viewport and reception scrolled to
    find anything. A receptionist works this list under time pressure between
    patients; density is the feature.

    WHY NO BOARD: a kanban board optimises for moving work between columns.
    Nobody here does that. They read what is due, act, and log what happened.

    Every control on this page posts to the server. The V1 screen carried a
    search box, a staff dropdown, counter cards and a Daily/Weekly/Monthly
    switch whose Alpine state nothing ever read — all four were decoration.
--}}
@extends('layouts.app')
@section('page-title', 'Tasks')

@section('content')
@php
    $q = fn(array $over = []) => route('tasks.index', array_merge(array_filter([
        'view'        => $filters['view'],
        'q'           => $filters['q'],
        'assigned_to' => $filters['assigned_to'],
        'category'    => $filters['category'],
        'priority'    => $filters['priority'],
        'source'      => $source ?? null,
    ]), $over));

    $chips = [
        'open'    => ['Open',      $counts['open'],    '#6a0f70'],
        'overdue' => ['Overdue',   $counts['overdue'], '#b52020'],
        'today'   => ['Today',     $counts['today'],   '#a05c00'],
        'week'    => ['This week', $counts['week'],    '#1a5ea8'],
        'done'    => ['Done',      $counts['done'],    '#1a7a45'],
    ];
@endphp

<div x-data="taskList()" style="font-family:'Inter',sans-serif;height:100%;display:flex;flex-direction:column;background:#fff;">

    {{-- ── HEADER ─────────────────────────────────────────────────────── --}}
    <div style="padding:22px 28px 0;display:flex;align-items:flex-start;justify-content:space-between;">
        <div>
            <h1 style="font-family:'Cormorant Garamond',serif;font-size:25px;font-weight:700;color:#1a0320;margin:0 0 2px;">Tasks</h1>
            <p style="font-size:12.5px;color:#9a7aaa;margin:0;">{{ today()->format('l, d M Y') }}</p>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
        <a href="{{ route('tasks.settings') }}"
           style="font-size:12.5px;color:#9a7aaa;text-decoration:none;padding:9px 4px;">Settings</a>
        <button @click="drawerOpen=true"
                style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:#6a0f70;color:#fff;border:none;border-radius:7px;font-size:13px;font-weight:500;cursor:pointer;">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
            Assign Task
        </button>
        </div>
    </div>

    {{-- ── COUNT CHIPS = THE VIEW FILTER ──────────────────────────────────
         These numbers and the rows below are built from the same query, so
         the card and the list can never disagree. --}}
    <div style="padding:16px 28px 0;display:flex;gap:8px;flex-wrap:wrap;">
        @foreach($chips as $key => [$label, $count, $colour])
            @php $on = $filters['view'] === $key; @endphp
            <a href="{{ $q(['view' => $key]) }}"
               style="display:inline-flex;align-items:baseline;gap:7px;padding:7px 14px;border-radius:8px;text-decoration:none;border:1.5px solid {{ $on ? $colour : '#ede4f3' }};background:{{ $on ? $colour.'12' : '#fff' }};">
                <span style="font-size:17px;font-weight:700;color:{{ $colour }};line-height:1;">{{ $count }}</span>
                <span style="font-size:12px;font-weight:{{ $on ? 600 : 500 }};color:{{ $on ? $colour : '#7a6088' }};">{{ $label }}</span>
            </a>
        @endforeach
    </div>

    {{-- ── FILTER BAR (GET form → everything lives in the URL) ─────────── --}}
    <form method="GET" action="{{ route('tasks.index') }}"
          style="padding:14px 28px 14px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;border-bottom:1.5px solid #ede4f3;">
        <input type="hidden" name="view" value="{{ $filters['view'] }}">
        @if($source ?? null)<input type="hidden" name="source" value="{{ $source }}">@endif

        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Search tasks…"
               style="flex:1;min-width:180px;padding:8px 13px;border:1.5px solid #ede4f3;border-radius:7px;font-size:13px;font-family:inherit;outline:none;">

        @php
            $isStaffRole = in_array(auth()->user()->role, [
                \App\Models\User::ROLE_ASSISTANT,
                \App\Models\User::ROLE_FRONT_DESK,
                \App\Models\User::ROLE_ACCOUNTS,
            ]);
        @endphp
        @unless($isStaffRole)
            <select name="assigned_to" style="padding:8px 12px;border:1.5px solid #ede4f3;border-radius:7px;font-size:13px;font-family:inherit;color:#1a0320;">
                <option value="">All staff</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected($filters['assigned_to'] == $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        @endunless

        <select name="category" style="padding:8px 12px;border:1.5px solid #ede4f3;border-radius:7px;font-size:13px;font-family:inherit;color:#1a0320;">
            <option value="">All types</option>
            @foreach(\App\Models\Task::CATEGORIES as $key => $label)
                <option value="{{ $key }}" @selected($filters['category'] === $key)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="priority" style="padding:8px 12px;border:1.5px solid #ede4f3;border-radius:7px;font-size:13px;font-family:inherit;color:#1a0320;">
            <option value="">Any priority</option>
            @foreach(['urgent'=>'Urgent','high'=>'High','medium'=>'Medium','low'=>'Low'] as $key => $label)
                <option value="{{ $key }}" @selected($filters['priority'] === $key)>{{ $label }}</option>
            @endforeach
        </select>

        <button type="submit" style="padding:8px 16px;background:#6a0f70;color:#fff;border:none;border-radius:7px;font-size:13px;font-weight:500;cursor:pointer;font-family:inherit;">Apply</button>

        @if($filters['q'] || $filters['assigned_to'] || $filters['category'] || $filters['priority'])
            <a href="{{ route('tasks.index', ['view' => $filters['view']]) }}"
               style="font-size:12.5px;color:#9a7aaa;text-decoration:none;">Clear</a>
        @endif

        <div style="display:flex;background:#f3eef7;border-radius:7px;padding:3px;gap:2px;margin-left:auto;">
            <a href="{{ $q(['source' => null]) }}"
               style="padding:5px 12px;border-radius:5px;font-size:12px;font-weight:600;text-decoration:none;{{ ($source ?? '') !== 'protocol' ? 'background:#6a0f70;color:#fff;' : 'color:#9a7aaa;' }}">All</a>
            <a href="{{ $q(['source' => 'protocol']) }}"
               style="padding:5px 12px;border-radius:5px;font-size:12px;font-weight:600;text-decoration:none;{{ ($source ?? '') === 'protocol' ? 'background:#6a0f70;color:#fff;' : 'color:#9a7aaa;' }}">Protocols</a>
        </div>
    </form>

    {{-- ── THE LIST ───────────────────────────────────────────────────── --}}
    <div style="flex:1;overflow-y:auto;">
        @forelse($tasks as $task)
            @include('tasks._row', ['task' => $task])
        @empty
            <div style="text-align:center;padding:64px 20px;color:#b0a0bb;">
                <p style="font-size:14px;font-weight:500;margin:0 0 4px;">Nothing here</p>
                <p style="font-size:12.5px;color:#c5b0d5;margin:0;">
                    {{ $filters['view'] === 'open' ? 'No open tasks. Click Assign Task to create one.' : 'No tasks match this filter.' }}
                </p>
            </div>
        @endforelse

        @if($tasks->hasPages())
            <div style="padding:14px 28px 28px;">{{ $tasks->links() }}</div>
        @endif
    </div>

    {{-- ── OUTCOME DRAWER ─────────────────────────────────────────────────
         This is the answer to "no option to record the response or reschedule
         after it's done". Opens over the list, so reception keeps its place.

         LAYOUT RULE: header and footer are fixed, only the middle scrolls.
         The clinic-editable call-outcome list runs to 40+ entries, so an
         action button that lives at the bottom of the scrolling area is an
         action button nobody can find. It stays pinned.

         STYLING RULE: no element here carries BOTH a static `style` and a
         bound `:style`. Alpine overwrites the static one, which is what
         flattened the mode buttons into a line of plain text. Anything
         conditional builds its whole style string in one binding. --}}
    <div x-show="panel" x-cloak style="position:fixed;inset:0;z-index:70;">
        <div style="position:absolute;inset:0;background:rgba(14,1,24,.40);" @click="close()"></div>
        <div style="position:absolute;top:0;right:0;bottom:0;width:100%;max-width:440px;background:#fff;box-shadow:-6px 0 34px rgba(14,1,24,.18);display:flex;flex-direction:column;">

            {{-- ── header (fixed) ── --}}
            <div style="padding:18px 22px 14px;border-bottom:1.5px solid #ede4f3;flex-shrink:0;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                    <div style="min-width:0;">
                        <div style="font-size:15px;font-weight:600;color:#1a0320;line-height:1.35;" x-text="task.title"></div>
                        <div style="font-size:12px;color:#9a7aaa;margin-top:4px;">
                            <span x-text="task.category_label"></span>
                            <template x-if="task.patient_name"><span> · <span x-text="task.patient_name"></span></span></template>
                            <template x-if="task.assigned_to"><span> · <span x-text="task.assigned_to"></span></span></template>
                        </div>
                        <div style="font-size:12px;margin-top:5px;">
                            <span style="color:#7a6088;">Due <span x-text="task.due_date_label"></span></span>
                            <template x-if="task.days_late > 0">
                                <span style="color:#b52020;font-weight:600;"> · <span x-text="task.days_late"></span> days late</span>
                            </template>
                            <template x-if="task.reschedule_count > 0">
                                <span style="color:#a05c00;"> · moved <span x-text="task.reschedule_count"></span>x</span>
                            </template>
                        </div>
                    </div>
                    <button @click="close()" style="background:none;border:none;cursor:pointer;color:#9a7aaa;font-size:22px;line-height:1;flex-shrink:0;">&times;</button>
                </div>

                {{-- mode picker --}}
                <div x-show="task.is_open" style="display:flex;gap:6px;margin-top:14px;">
                    <template x-for="m in modes" :key="m.k">
                        <button @click="mode=m.k;err=''"
                                :style="'padding:7px 12px;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;white-space:nowrap;'
                                        + (mode===m.k ? 'background:#6a0f70;color:#fff;' : 'background:#f3eef7;color:#7a6088;')"
                                x-text="m.l"></button>
                    </template>
                </div>
            </div>

            {{-- ── body (scrolls) ── --}}
            <div style="padding:16px 22px;overflow-y:auto;flex:1;">

                <template x-if="task.is_open">
                    <div>
                        {{-- Outcome is a SELECT, not a radio list. The call-outcome
                             vocabulary has 40+ entries; radios turn the drawer into a
                             scrolling wall and bury everything below it. --}}
                        <div x-show="mode !== 'cancel'" style="margin-bottom:14px;">
                            <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:6px;">What happened?</label>
                            <select x-model="outcomeKey"
                                    style="width:100%;padding:10px 12px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;background:#fff;color:#1a0320;box-sizing:border-box;">
                                <option value="">— select —</option>
                                <template x-for="(label, key) in options" :key="key">
                                    <option :value="key" x-text="label"></option>
                                </template>
                            </select>

                            {{-- An outcome meaning the work never happened cannot close
                                 the task. Say so before they press the button, not after. --}}
                            <p x-show="mode==='done' && nonClosing.includes(outcomeKey)"
                               style="font-size:11.5px;color:#a05c00;margin:7px 0 0;">
                               This outcome means the work did not actually happen — the task will stay open and be logged as an attempt.
                            </p>
                        </div>

                        <div x-show="mode === 'reschedule'" style="margin-bottom:14px;">
                            <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">New date</label>
                            <input type="date" x-model="newDate" :min="todayStr"
                                   style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;box-sizing:border-box;">
                            <p style="font-size:11.5px;color:#9a7aaa;margin:6px 0 0;">
                                The original due date is kept, so this task stays counted as overdue.
                            </p>
                        </div>

                        <div>
                            <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;"
                                   x-text="mode==='cancel' ? 'Why is this being cancelled?' : 'Note'"></label>
                            <textarea x-model="note" rows="3"
                                      :placeholder="mode==='cancel' ? 'e.g. patient shifted city, treatment dropped' : 'e.g. spoke to husband, will call back after 7pm'"
                                      style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;box-sizing:border-box;resize:vertical;"></textarea>
                        </div>
                    </div>
                </template>

                {{-- trail --}}
                <div style="margin-top:22px;">
                    <div style="font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#9a7aaa;margin-bottom:9px;">History</div>
                    <template x-if="trail.length === 0">
                        <p style="font-size:12.5px;color:#b0a0bb;margin:0;">Nothing logged yet.</p>
                    </template>
                    <div style="display:flex;flex-direction:column;gap:9px;">
                        <template x-for="(t,i) in trail" :key="i">
                            <div style="border-left:2px solid #ede4f3;padding-left:11px;">
                                <div style="font-size:12.5px;color:#1a0320;" x-text="t.summary"></div>
                                <div style="font-size:11px;color:#9a7aaa;margin-top:2px;">
                                    <span x-text="t.user"></span> · <span x-text="t.at"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- ── footer (fixed — always reachable) ── --}}
            <div style="padding:14px 22px;border-top:1.5px solid #ede4f3;flex-shrink:0;background:#fff;">
                <p x-show="err" x-text="err" style="font-size:12.5px;color:#b52020;margin:0 0 9px;"></p>

                <template x-if="task.is_open">
                    <button @click="submit()" :disabled="busy"
                            :style="'width:100%;padding:12px;background:#6a0f70;color:#fff;border:none;border-radius:7px;font-size:13.5px;font-weight:600;font-family:inherit;'
                                    + (busy ? 'opacity:.6;cursor:wait;' : 'cursor:pointer;')"
                            x-text="busy ? 'Saving…' : submitLabel()"></button>
                </template>

                <template x-if="task.id && !task.is_open">
                    <button @click="reopen()" :disabled="busy"
                            style="width:100%;padding:12px;background:#fff;color:#6a0f70;border:1.5px solid #6a0f70;border-radius:7px;font-size:13.5px;font-weight:600;cursor:pointer;font-family:inherit;">
                        Reopen task
                    </button>
                </template>
            </div>
        </div>
    </div>

    @include('tasks._assign-drawer')
</div>

<style>[x-cloak]{display:none!important;}</style>

<script>
function taskList(){
    return {
        drawerOpen: false,   // the Assign Task create form
        panel: false,        // the outcome drawer
        busy: false,
        err: '',
        id: null,
        task: {},
        options: {},
        nonClosing: [],
        trail: [],
        mode: 'done',
        modes: [
            {k:'done',       l:'Done'},
            {k:'attempted',  l:'Attempted'},
            {k:'reschedule', l:'Reschedule'},
            {k:'cancel',     l:'Cancel task'},
        ],
        outcomeKey: '',
        note: '',
        newDate: '',
        todayStr: new Date().toISOString().slice(0,10),

        async open(id, mode){
            this.id = id; this.mode = mode || 'done';
            this.outcomeKey = ''; this.note = ''; this.err = '';
            this.task = {}; this.trail = []; this.options = {};
            this.panel = true;
            const res = await fetch(`/tasks/${id}`, {headers:{'X-Requested-With':'XMLHttpRequest'}});
            const d = await res.json();
            this.task = d.task;
            this.options = d.options;
            this.nonClosing = d.non_closing_keys || [];
            this.trail = d.trail;
            this.newDate = d.task.due_date;
        },

        close(){ this.panel = false; },

        submitLabel(){
            return {done:'Mark done', attempted:'Log attempt', reschedule:'Reschedule', cancel:'Cancel task'}[this.mode];
        },

        async submit(){
            this.err = '';
            if(this.mode === 'cancel' && !this.note.trim()){
                this.err = 'A reason is required to cancel a task.'; return;
            }
            if(this.mode === 'reschedule' && !this.note.trim()){
                this.err = 'Say why it is being moved — the date alone does not explain it.'; return;
            }
            const url = {
                done:       `/tasks/${this.id}/done`,
                attempted:  `/tasks/${this.id}/attempt`,
                reschedule: `/tasks/${this.id}/reschedule`,
                cancel:     `/tasks/${this.id}/cancel`,
            }[this.mode];

            const body = this.mode === 'cancel'
                ? {reason: this.note}
                : {outcome_key: this.outcomeKey || null, note: this.note || null,
                   ...(this.mode === 'reschedule' ? {due_date: this.newDate} : {})};

            this.busy = true;
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(body),
                });
                const d = await res.json();
                if(!res.ok || !d.ok){
                    this.err = d.message || Object.values(d.errors || {}).flat().join(' ') || 'Could not save.';
                    this.busy = false; return;
                }
                window.location.reload();
            } catch(e){
                this.err = 'Network error — nothing was saved.';
                this.busy = false;
            }
        },

        async reopen(){
            this.busy = true;
            await fetch(`/tasks/${this.id}/reopen`, {
                method:'POST',
                headers:{
                    'Content-Type':'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With':'XMLHttpRequest',
                },
                body: JSON.stringify({}),
            });
            window.location.reload();
        },
    };
}
</script>
@endsection
