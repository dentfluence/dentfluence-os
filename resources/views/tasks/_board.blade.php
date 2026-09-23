{{--
    THE TASKS BOARD — the markup, used in two places.

    ── WHY A PARTIAL ───────────────────────────────────────────────────────────
    This board renders at /tasks and again inline on My Day. It is the SAME
    board, not a summary of it: the same rows, the same outcome drawer, the
    same close-and-chain-a-follow-up flow. A second, simpler copy on My Day
    would have been quicker to write and would have taught reception that the
    two screens are different — which is the confusion My Day exists to end.

    ── $compact ────────────────────────────────────────────────────────────────
    false (default) — the full page: title, count chips, filter bar, its own
                      scroll area, lavender background.
    true            — embedded: the table, the drawers and the script only.
                      The chips and filter bar are dropped because they
                      navigate to /tasks, and a control that takes you off the
                      page you are working is worse than no control.

    ── IF YOU INCLUDE THIS TWICE ON ONE PAGE ───────────────────────────────────
    Do not. taskList() and the two drawers are declared at top level; a second
    copy redeclares them and the whole page's JavaScript dies, not just the
    second board. One board per page.
--}}
@php
    $compact = $compact ?? false;
@endphp

@php
    $q = fn(array $over = []) => route('tasks.index', array_merge(array_filter([
        'view'        => $filters['view'],
        'q'           => $filters['q'],
        'assigned_to' => $filters['assigned_to'],
        'category'    => $filters['category'],
        'priority'    => $filters['priority'],
        'date'        => $filters['date'],
        'source'      => $source ?? null,
    ]), $over));

    // ORDER IS DELIBERATE: what a receptionist acts on first, reading left to
    // right — today, then the rest of the week, then the debt, then everything
    // open, then what is finished. Overdue keeps its red weight even though it
    // is no longer first; it is the one number on this screen that cannot be
    // argued with.
    $chips = [
        'today'   => ['Today',     $counts['today'],   '#a05c00'],
        'week'    => ['This week', $counts['week'],    '#1a5ea8'],
        'overdue' => ['Overdue',   $counts['overdue'], '#b52020'],
        'open'    => ['Open',      $counts['open'],    '#6a0f70'],
        'done'    => ['Done',      $counts['done'],    '#1a7a45'],
    ];
@endphp


{{-- The page furniture deliberately mirrors Relationship > Today's Actions:
     lavender page, white card, pill filters, small-caps table head, status as a
     pill. Two screens that do the same kind of work — a list of things to act on
     today — should not teach two different visual languages. --}}
<div x-data="taskList()" style="font-family:'Inter',sans-serif;{{ $compact ? '' : 'height:100%;display:flex;flex-direction:column;background:#f4eff8;' }}">

@unless($compact)

    {{-- ── HEADER ─────────────────────────────────────────────────────── --}}
    <div style="padding:22px 28px 0;display:flex;align-items:flex-start;justify-content:space-between;">
        <div>
            <div style="display:flex;align-items:center;gap:12px;">
                <h1 style="font-family:'Cormorant Garamond',serif;font-size:25px;font-weight:700;color:#1a0320;margin:0;">Tasks</h1>
                <span style="background:#ede4f3;color:#6a0f70;font-size:12px;font-weight:600;padding:4px 12px;border-radius:999px;">
                    {{ $counts['open'] }} open · {{ $counts['overdue'] }} overdue
                </span>
            </div>
            <p style="font-size:12.5px;color:#9a7aaa;margin:3px 0 0;">
                @if($filters['date'])
                    Showing <strong style="color:#6a0f70;">{{ \Carbon\Carbon::parse($filters['date'])->format('l, d F Y') }}</strong>
                    — <a href="{{ route('tasks.index') }}" style="color:#6a0f70;">back to open work</a>
                @else
                    {{ today()->format('l, d F Y') }} · Staff work, not automation
                @endif
            </p>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
        {{-- Only for people who can read reports; everyone else never sees a
             per-colleague breakdown exists. The route enforces this too. --}}
        @if(auth()->user()->canAccess('reports'))
            <a href="{{ route('tasks.accountability') }}"
               style="font-size:12.5px;color:#9a7aaa;text-decoration:none;padding:9px 4px;">Accountability</a>
        @endif
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
    <div style="padding:16px 28px 0;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        @foreach($chips as $key => [$label, $count, $colour])
            @php $on = $filters['view'] === $key; @endphp
            <a href="{{ $q(['view' => $key, 'date' => null]) }}"
               style="display:inline-flex;align-items:center;gap:7px;padding:6px 14px;border-radius:999px;text-decoration:none;font-size:12.5px;
                      border:1.5px solid {{ $on ? '#6a0f70' : '#e2d6ea' }};
                      background:{{ $on ? '#6a0f70' : '#fff' }};
                      color:{{ $on ? '#fff' : '#5a4566' }};font-weight:{{ $on ? 600 : 500 }};">
                <span style="width:7px;height:7px;border-radius:50%;background:{{ $on ? '#fff' : $colour }};"></span>
                {{ $label }}
                <span style="font-weight:700;color:{{ $on ? '#fff' : $colour }};">{{ $count }}</span>
            </a>
        @endforeach
    </div>

    {{-- ── FILTER BAR (GET form → everything lives in the URL) ─────────── --}}
    <form method="GET" action="{{ route('tasks.index') }}"
          style="padding:14px 28px 10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
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
                {{-- A task nobody owns is a task nobody does, and until now
                     there was no way to find one. --}}
                <option value="none" @selected($filters['assigned_to'] === 'none')>
                    Unassigned{{ $counts['unassigned'] ? ' ('.$counts['unassigned'].')' : '' }}
                </option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected($filters['assigned_to'] == $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        @endunless

        {{-- Pick a day and the view chips step aside: "what is on for the 4th"
             is a different question from "what is still open", and answering
             both at once shows an empty screen with no explanation. --}}
        <input type="date" name="date" value="{{ $filters['date'] }}" title="Show one day"
               style="padding:8px 12px;border:1.5px solid {{ $filters['date'] ? '#6a0f70' : '#ede4f3' }};border-radius:7px;font-size:13px;font-family:inherit;color:#1a0320;">

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
@endunless

    {{-- ── THE TABLE ──────────────────────────────────────────────────────
         Columns, not free-form rows. A receptionist scanning for "everything
         Ankita owns" or "every urgent one" reads DOWN a column; she cannot do
         that when each line is a sentence. The header is sticky so the column
         meanings survive a long list.

         Still one line per task, still no board. --}}
    <div style="{{ $compact ? '' : 'flex:1;overflow-y:auto;padding:0 28px 28px;' }}">
        <div style="background:#fff;border:1.5px solid #e9dff0;border-radius:12px;overflow:hidden;">
        @if($tasks->count())
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="position:sticky;top:0;background:#faf6fc;z-index:2;">
                    <th style="text-align:left;padding:10px 18px;font-size:10.5px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#9a7aaa;border-bottom:1.5px solid #ede4f3;">Task</th>
                    <th style="text-align:left;padding:10px 10px;font-size:10.5px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#9a7aaa;border-bottom:1.5px solid #ede4f3;width:130px;">Staff</th>
                    <th style="text-align:left;padding:10px 10px;font-size:10.5px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#9a7aaa;border-bottom:1.5px solid #ede4f3;width:100px;">Type</th>
                    <th style="text-align:left;padding:10px 10px;font-size:10.5px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#9a7aaa;border-bottom:1.5px solid #ede4f3;width:86px;">Priority</th>
                    <th style="text-align:left;padding:10px 10px;font-size:10.5px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#9a7aaa;border-bottom:1.5px solid #ede4f3;width:160px;">Status</th>
                    <th style="padding:10px 18px 10px 10px;border-bottom:1.5px solid #ede4f3;width:90px;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($tasks as $task)
                    @include('tasks._row', ['task' => $task])
                @endforeach
            </tbody>
        </table>
        @else
            <div style="text-align:center;padding:64px 20px;color:#b0a0bb;">
                <p style="font-size:14px;font-weight:500;margin:0 0 4px;">Nothing here</p>
                <p style="font-size:12.5px;color:#c5b0d5;margin:0;">
                    {{ $filters['view'] === 'open' ? 'No open tasks. Click Assign Task to create one.' : 'No tasks match this filter.' }}
                </p>
            </div>
        @endif

        @if($tasks->hasPages())
            <div style="padding:14px 18px;border-top:1px solid #f3eef7;">{{ $tasks->links() }}</div>
        @endif
        </div>
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
    {{-- z-index 900: the app shell in layouts/app.blade.php goes up to 130
         (topbar, sidebar, its own overlays). At 70 this drawer opened UNDER the
         topbar, which quietly ate its whole first row — the task title, the
         Edit button and the close X. The drawer looked like it was missing
         controls when it was simply covered. --}}
    <div x-show="panel" x-cloak style="position:fixed;inset:0;z-index:900;">
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
                    <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
                        {{-- x-if, not x-show. An element carrying BOTH x-show and a
                             bound :style is the conflict that flattened the mode
                             buttons earlier in this file — Alpine rewrites the whole
                             style attribute and the two fight over `display`.
                             x-if removes the element instead of hiding it, so the
                             :style binding is the only thing touching style here. --}}
                        <template x-if="task.is_open">
                            <button @click="editing = !editing; err=''"
                                    :style="'padding:6px 13px;border-radius:7px;font-size:12px;font-weight:600;font-family:inherit;cursor:pointer;border:1.5px solid;'
                                        + (editing ? 'background:#6a0f70;color:#fff;border-color:#6a0f70;' : 'background:#fff;color:#6a0f70;border-color:#d9c7e4;')"
                                    x-text="editing ? 'Cancel edit' : 'Edit'"></button>
                        </template>
                        <button @click="close()" style="background:none;border:none;cursor:pointer;color:#9a7aaa;font-size:22px;line-height:1;">&times;</button>
                    </div>
                </div>

                {{-- mode picker --}}
                {{-- Each one gets its own border. Without it, four flat grey
                     blocks sitting next to each other read as one control with
                     the words run together — which is exactly how they looked.
                     The "what happens next" buttons below were always legible
                     for this reason; these now match them. --}}
                <div x-show="task.is_open && !editing" style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap;">
                    <template x-for="m in modes" :key="m.k">
                        <button @click="mode=m.k;err=''"
                                :style="'padding:7px 13px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;white-space:nowrap;border:1.5px solid;'
                                        + (mode===m.k
                                            ? 'background:#6a0f70;color:#fff;border-color:#6a0f70;'
                                            : 'background:#fff;color:#7a6088;border-color:#ede4f3;')"
                                x-text="m.l"></button>
                    </template>
                </div>
            </div>

            {{-- ── body (scrolls) ── --}}
            <div style="padding:16px 22px;overflow-y:auto;flex:1;">

                {{-- The chair this task filled. Shown at the top of the drawer,
                     open or closed, because "did that recall call convert?" is
                     the first thing anyone asks about a finished call and the
                     answer was previously nowhere on screen. Links to the
                     calendar on that date rather than to a detail page — the
                     day sheet is where staff actually work. --}}
                <template x-if="task.appointment">
                    <a :href="task.appointment.url"
                       style="display:flex;align-items:center;gap:8px;text-decoration:none;margin-bottom:14px;padding:9px 12px;background:#f2fbf4;border:1px solid #cfe9d6;border-radius:7px;">
                        <span style="font-size:12px;font-weight:700;color:#1d7a3c;">Appointment booked</span>
                        <span style="font-size:12px;color:#2c6e42;" x-text="task.appointment.label"></span>
                        <template x-if="task.appointment.doctor">
                            <span style="font-size:12px;color:#5a8a6a;" x-text="'· ' + task.appointment.doctor"></span>
                        </template>
                        <span style="margin-left:auto;font-size:12px;color:#1d7a3c;font-weight:600;">View &rarr;</span>
                    </a>
                </template>

                {{-- ── EDIT ────────────────────────────────────────────────
                     Due date is NOT here on purpose. Moving a date is a
                     Reschedule: it asks why, keeps the original date and keeps
                     counting the delay. An editable date field would walk
                     around every one of those guards. Status is absent for the
                     same reason — closing goes through Done or Cancel. --}}
                <div x-show="editing && task.is_open">
                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Task</label>
                    <input type="text" x-model="form.title"
                           style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;box-sizing:border-box;margin-bottom:12px;">

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
                        <div>
                            <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Priority</label>
                            <select x-model="form.priority"
                                    style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;box-sizing:border-box;background:#fff;">
                                @foreach(['urgent'=>'Urgent','high'=>'High','medium'=>'Medium','low'=>'Low'] as $pk => $pl)
                                    <option value="{{ $pk }}">{{ $pl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Type</label>
                            <select x-model="form.category"
                                    style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;box-sizing:border-box;background:#fff;">
                                @foreach(\App\Models\Task::CATEGORIES as $ck => $cl)
                                    <option value="{{ $ck }}">{{ $cl }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Assigned to</label>
                    <select x-model="form.assigned_to"
                            style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;box-sizing:border-box;background:#fff;margin-bottom:12px;">
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>

                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Details</label>
                    <textarea x-model="form.description" rows="3"
                              style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;box-sizing:border-box;resize:vertical;"></textarea>

                    <p style="font-size:11.5px;color:#9a7aaa;margin:8px 0 0;">
                        To move the date, use <strong>Reschedule</strong> — it keeps the original
                        due date so the overdue count stays honest.
                    </p>
                </div>

                <template x-if="task.is_open">
                    <div x-show="!editing">
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

                        {{-- ── WHAT HAPPENS NEXT (closing only) ───────────────
                             Asked here it costs one line; asked tomorrow it is
                             never asked, and the follow-up quietly dies. This
                             is the whole reason a clinic loses a case after a
                             good call.

                             Booking is a link out, not a form: a real
                             appointment needs the doctor, chair, slot and
                             overlap check, and a second booking form here
                             would drift from the calendar's own rules. --}}
                        <div x-show="mode === 'done'" style="margin-top:16px;padding-top:14px;border-top:1.5px dashed #ede4f3;">
                            <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:7px;">What happens next?</label>

                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <template x-for="n in nextOptions" :key="n.k">
                                    <button type="button" @click="next = n.k"
                                            :disabled="n.k === 'appointment' && !task.patient_name"
                                            :style="'padding:6px 12px;border-radius:6px;font-size:12px;font-weight:600;font-family:inherit;border:1.5px solid;'
                                                + (next === n.k
                                                    ? 'background:#6a0f70;color:#fff;border-color:#6a0f70;cursor:pointer;'
                                                    : (n.k === 'appointment' && !task.patient_name
                                                        ? 'background:#fff;color:#c5b0d5;border-color:#ede4f3;cursor:not-allowed;'
                                                        : 'background:#fff;color:#7a6088;border-color:#ede4f3;cursor:pointer;'))"
                                            x-text="n.l"></button>
                                </template>
                            </div>

                            {{-- The follow-up is a REAL task, so it is created the way a
                                 real task is: an owner, a date, a type, a priority. The
                                 old version asked only for a title and silently inherited
                                 the rest, which is how a lab follow-up ended up assigned
                                 to the receptionist who closed the call.

                                 The patient link is not a field because it is not a
                                 choice — the follow-up is about the same case by
                                 definition. It is shown so staff can see what is being
                                 carried forward. --}}
                            <div x-show="next === 'task'" style="margin-top:10px;">
                                <template x-if="task.patient_name">
                                    <div style="font-size:12px;color:#7a6088;margin-bottom:9px;padding:7px 10px;background:#faf6fc;border-radius:6px;border:1px solid #f0e6f5;">
                                        About <strong x-text="task.patient_name"></strong>
                                        <span style="color:#9a7aaa;">— carried over from this task</span>
                                    </div>
                                </template>

                                <label style="font-size:11.5px;font-weight:600;color:#6a0f70;display:block;margin-bottom:4px;">Task</label>
                                <input type="text" x-model="nextTask.title" placeholder="e.g. Call again about the crown"
                                       style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;box-sizing:border-box;">

                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;">
                                    <div>
                                        <label style="font-size:11.5px;font-weight:600;color:#6a0f70;display:block;margin-bottom:4px;">Assigned to</label>
                                        <select x-model="nextTask.assigned_to" style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;box-sizing:border-box;background:#fff;">
                                            @foreach($users as $u)
                                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-size:11.5px;font-weight:600;color:#6a0f70;display:block;margin-bottom:4px;">Due date</label>
                                        <input type="date" x-model="nextTask.due_date" :min="todayStr" style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;box-sizing:border-box;">
                                    </div>
                                </div>

                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;">
                                    <div>
                                        <label style="font-size:11.5px;font-weight:600;color:#6a0f70;display:block;margin-bottom:4px;">Type</label>
                                        <select x-model="nextTask.category" style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;box-sizing:border-box;background:#fff;">
                                            @foreach(\App\Models\Task::CATEGORIES as $ck => $cl)
                                                <option value="{{ $ck }}">{{ $cl }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-size:11.5px;font-weight:600;color:#6a0f70;display:block;margin-bottom:4px;">Priority</label>
                                        <select x-model="nextTask.priority" style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;box-sizing:border-box;background:#fff;">
                                            @foreach(['urgent'=>'Urgent','high'=>'High','medium'=>'Medium','low'=>'Low'] as $pk => $pl)
                                                <option value="{{ $pk }}">{{ $pl }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <p style="font-size:11.5px;color:#9a7aaa;margin:8px 0 0;">
                                    Starts fresh — no attempts and no delay carried over.
                                </p>
                            </div>

                            {{-- Booked right here, for the patient this task is about.
                                 The form is new; the RULES are not — it posts to the same
                                 AppointmentController@store the calendar uses, so the
                                 blocked-slot and overlap checks still decide. Nothing
                                 about booking is re-implemented in this drawer.

                                 What is missing, honestly: there is no free-slot list
                                 (the calendar builds that server-side on its own page).
                                 You type a time; if it clashes, the server refuses and
                                 says so below. --}}
                            <div x-show="next === 'appointment' && task.patient_name" style="margin-top:10px;">
                                <div style="font-size:12px;color:#7a6088;margin-bottom:8px;">
                                    For <strong x-text="task.patient_name"></strong>
                                </div>

                                <div style="display:grid;grid-template-columns:1fr 130px;gap:8px;margin-bottom:8px;">
                                    <div>
                                        <label style="font-size:11.5px;font-weight:600;color:#6a0f70;display:block;margin-bottom:4px;">Doctor</label>
                                        <select x-model="appt.doctor_id"
                                                style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;box-sizing:border-box;background:#fff;">
                                            <option value="">— select —</option>
                                            @foreach($doctors as $doc)
                                                <option value="{{ $doc->id }}">{{ $doc->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        {{-- Required by AppointmentController@store; the calendar
                                             has always had it. Defaulted from the task's own type
                                             so the common case needs no thought. --}}
                                        <label style="font-size:11.5px;font-weight:600;color:#6a0f70;display:block;margin-bottom:4px;">Visit</label>
                                        <select x-model="appt.type"
                                                style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;box-sizing:border-box;background:#fff;">
                                            <option value="follow-up">Follow-up</option>
                                            <option value="consultation">Consultation</option>
                                            <option value="treatment">Treatment</option>
                                        </select>
                                    </div>
                                </div>

                                <div style="display:grid;grid-template-columns:1fr 110px;gap:8px;">
                                    <div>
                                        <label style="font-size:11.5px;font-weight:600;color:#6a0f70;display:block;margin-bottom:4px;">Date</label>
                                        <input type="date" x-model="appt.appointment_date" :min="todayStr"
                                               style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;box-sizing:border-box;">
                                    </div>
                                    <div>
                                        <label style="font-size:11.5px;font-weight:600;color:#6a0f70;display:block;margin-bottom:4px;">Time</label>
                                        <input type="time" x-model="appt.appointment_time"
                                               style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;box-sizing:border-box;">
                                    </div>
                                </div>

                                <p style="font-size:11.5px;color:#9a7aaa;margin:8px 0 0;">
                                    The appointment is booked first. If the slot clashes, the task stays open
                                    and nothing is lost.
                                </p>
                            </div>
                            <p x-show="next === 'appointment' && !task.patient_name" style="font-size:11.5px;color:#a05c00;margin:9px 0 0;">
                                No patient is linked to this task, so there is nobody to book.
                            </p>
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
                    <button @click="editing ? saveEdit() : submit()" :disabled="busy"
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
// Survives the reload that follows a close, so the confirmation is not lost
// with the page that produced it.
document.addEventListener('DOMContentLoaded', () => {
    const msg = sessionStorage.getItem('taskFlash');
    if(!msg) return;
    sessionStorage.removeItem('taskFlash');
    const bar = document.createElement('div');
    bar.textContent = msg;
    bar.style.cssText = 'position:fixed;left:50%;transform:translateX(-50%);bottom:26px;'
        + 'background:#1a0320;color:#fff;padding:11px 20px;border-radius:999px;font-size:13px;'
        + 'font-family:Inter,sans-serif;z-index:950;box-shadow:0 6px 24px rgba(14,1,24,.28);';
    document.body.appendChild(bar);
    setTimeout(() => bar.remove(), 5000);
});

function taskList(){
    return {
        // Reopened automatically when the server bounced a duplicate back, so
        // the person lands on their own half-filled form rather than an empty
        // board wondering what happened.
        drawerOpen: {{ session('duplicate_warning') ? 'true' : 'false' }},
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
        // Editing is NOT one of the modes above. Those four answer "what
        // happened to the work"; editing changes what the work IS. Putting
        // them in one row made the drawer ask two different questions at once.
        editing: false,
        nextOptions: [
            {k:'none',        l:'Nothing'},
            {k:'task',        l:'Follow-up task'},
            {k:'appointment', l:'Book appointment'},
        ],
        // Edit form state, filled from the task when the drawer opens.
        form: {title:'', description:'', priority:'medium', assigned_to:'', category:''},
        // "What happens next", asked only while closing. Default is nothing —
        // a prompt that pre-selects a follow-up would manufacture busywork.
        next: 'none',
        // Defaults are inherited from the task being closed, then overridable.
        // Inheriting silently was the bug; inheriting visibly is the feature.
        nextTask: {title:'', assigned_to:'', due_date:'', category:'', priority:'medium'},
        appt: {doctor_id:'', appointment_date:'', appointment_time:'', type:'follow-up'},
        bookedApptId: null,
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
            this.editing = false;
            this.next = 'none';
            this.bookedApptId = null;
            const t = new Date(); t.setDate(t.getDate() + 7);
            this.nextTask = {
                title:       '',
                assigned_to: d.task.assigned_to_id || '',
                due_date:    t.toISOString().slice(0,10),
                category:    d.task.category || '',
                priority:    d.task.priority || 'medium',
            };
            // A lab or clinical task that ends in a booking is almost always
            // treatment; a call or recall is a follow-up. Staff can override.
            const visitType = ['lab','clinical'].includes(d.task.category) ? 'treatment'
                            : (d.task.category === 'admin' ? 'consultation' : 'follow-up');
            this.appt = {
                doctor_id: '',
                appointment_date: t.toISOString().slice(0,10),
                appointment_time: '',
                type: visitType,
            };
            this.form = {
                title:       d.task.title || '',
                description: d.task.description || '',
                priority:    d.task.priority || 'medium',
                assigned_to: d.task.assigned_to_id || '',
                category:    d.task.category || '',
            };
        },

        close(){ this.panel = false; },

        submitLabel(){
            if(this.editing) return 'Save changes';
            return {done:'Mark done', attempted:'Log attempt',
                    reschedule:'Reschedule', cancel:'Cancel task'}[this.mode];
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
                   ...(this.mode === 'reschedule' ? {due_date: this.newDate} : {}),
                   ...(this.mode === 'done' && this.next !== 'none'
                        ? {next: this.next,
                           next_title:       this.nextTask.title,
                           next_due_date:    this.nextTask.due_date,
                           next_assigned_to: this.nextTask.assigned_to || null,
                           next_category:    this.nextTask.category || null,
                           next_priority:    this.nextTask.priority || null}
                        : {})};

            this.busy = true;

            // ORDER MATTERS. The appointment is booked FIRST, because it is the
            // step that can be refused — a clashing slot, a doctor on leave.
            // Close the task first and a rejected booking would leave the work
            // marked done with nothing scheduled, which is the worst of both.
            if(this.mode === 'done' && this.next === 'appointment'){
                const booked = await this.bookAppointment();
                if(!booked){ this.busy = false; return; }
                // Attached HERE, not when `body` was built above — the id does
                // not exist until the calendar has accepted the slot.
                if(this.bookedApptId) body.appointment_id = this.bookedApptId;
            }

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

                // A recurring service (AC, pest control, autoclave) books its own
                // next visit the moment this one closes. Say so — otherwise the
                // person quietly creates a duplicate by hand next week.
                if(d.next_due_date){
                    sessionStorage.setItem('taskFlash', 'Done. The next one is scheduled for ' + d.next_due_date + '.');
                } else if(d.chained_task_id){
                    sessionStorage.setItem('taskFlash', 'Done, and the follow-up task is on the list.');
                }
                window.location.reload();
            } catch(e){
                this.err = 'Network error — nothing was saved.';
                this.busy = false;
            }
        },

        /**
         * Posts to the calendar's own store endpoint. Every booking rule —
         * blocked slots, overlapping appointments, duration — is enforced there
         * and answers 422 with a message, which is shown as-is rather than
         * re-worded, because the calendar's wording is the one staff know.
         */
        async bookAppointment(){
            if(!this.appt.doctor_id || !this.appt.appointment_date || !this.appt.appointment_time){
                this.err = 'Pick a doctor, a date and a time to book.';
                return false;
            }
            try {
                const res = await fetch('{{ route('appointments.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        patient_id:       this.task.patient_id,
                        doctor_id:        this.appt.doctor_id,
                        appointment_date: this.appt.appointment_date,
                        appointment_time: this.appt.appointment_time,
                        type:             this.appt.type,
                        notes:            this.note || null,
                    }),
                });
                const d = await res.json();
                if(!res.ok){
                    this.err = d.message
                        || Object.values(d.errors || {}).flat().join(' ')
                        || 'That slot could not be booked.';
                    return false;
                }
                // The full-form path answers with `id`; the walk-in path only
                // with the formatted appointment. Read both, because a missing
                // id here would silently drop the link and nothing would fail.
                this.bookedApptId = d.id || (d.appointment && d.appointment.id) || null;
                return true;
            } catch(e){
                this.err = 'Network error — nothing was booked and the task is untouched.';
                return false;
            }
        },

        async saveEdit(){
            if(!this.form.title.trim()){ this.err = 'A task needs a title.'; return; }
            this.busy = true;
            try {
                const res = await fetch(`/tasks/${this.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(this.form),
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
