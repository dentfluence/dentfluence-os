@extends('layouts.app')
@section('page-title', 'Tasks')

@section('content')
<div
    x-data="taskModule()"
    x-init="init()"
    style="font-family:'Inter',sans-serif;padding:0;height:100%;display:flex;flex-direction:column;"
>

{{-- TOP BAR --}}
<div style="padding:24px 28px 0;display:flex;align-items:center;justify-content:space-between;">
    <div>
        <h1 style="font-family:'Cormorant Garamond',serif;font-size:26px;font-weight:700;color:#1a0320;margin:0 0 2px;">Tasks</h1>
        <p style="font-size:12.5px;color:#9a7aaa;margin:0;">{{ today()->format('l, d M Y') }}</p>
    </div>
    <button @click="drawerOpen=true"
            style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:#6a0f70;color:#fff;border:none;border-radius:7px;font-size:13px;font-weight:500;cursor:pointer;box-shadow:0 2px 8px rgba(106,15,112,.25);">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
        Assign Task
    </button>
</div>

{{-- COUNTER CARDS --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;padding:20px 28px 0;">

    <div @click="setFilter('backlog')"
         :style="activeFilter==='backlog'?'border-color:#b52020;background:#fdeaea;':''"
         style="background:#fff;border:1.5px solid #ede4f3;border-radius:10px;padding:16px 18px;cursor:pointer;transition:all 140ms;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <span style="font-size:11px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:#b52020;">Backlog</span>
            <div style="width:30px;height:30px;border-radius:8px;background:#fdeaea;display:flex;align-items:center;justify-content:center;">
                <svg width="15" height="15" fill="none" stroke="#b52020" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
        </div>
        <div style="font-size:28px;font-weight:700;color:#b52020;line-height:1;">{{ $overdue->count() }}</div>
        <div style="font-size:11.5px;color:#c88080;margin-top:3px;">Overdue tasks</div>
    </div>

    <div @click="setFilter('today')"
         :style="activeFilter==='today'?'border-color:#a05c00;background:#fff4e0;':''"
         style="background:#fff;border:1.5px solid #ede4f3;border-radius:10px;padding:16px 18px;cursor:pointer;transition:all 140ms;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <span style="font-size:11px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:#a05c00;">Due Today</span>
            <div style="width:30px;height:30px;border-radius:8px;background:#fff4e0;display:flex;align-items:center;justify-content:center;">
                <svg width="15" height="15" fill="none" stroke="#a05c00" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
            </div>
        </div>
        <div style="font-size:28px;font-weight:700;color:#a05c00;line-height:1;">{{ $today->count() }}</div>
        <div style="font-size:11.5px;color:#c8a060;margin-top:3px;">Pending today</div>
    </div>

    <div @click="setFilter('done')"
         :style="activeFilter==='done'?'border-color:#1a7a45;background:#e8f7ef;':''"
         style="background:#fff;border:1.5px solid #ede4f3;border-radius:10px;padding:16px 18px;cursor:pointer;transition:all 140ms;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <span style="font-size:11px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:#1a7a45;">Completed</span>
            <div style="width:30px;height:30px;border-radius:8px;background:#e8f7ef;display:flex;align-items:center;justify-content:center;">
                <svg width="15" height="15" fill="none" stroke="#1a7a45" stroke-width="2.2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
        </div>
        <div style="font-size:28px;font-weight:700;color:#1a7a45;line-height:1;">{{ $done->count() }}</div>
        <div style="font-size:11.5px;color:#60a87a;margin-top:3px;">This period</div>
    </div>

    <div @click="setFilter('upcoming')"
         :style="activeFilter==='upcoming'?'border-color:#1a5ea8;background:#e6f0fb;':''"
         style="background:#fff;border:1.5px solid #ede4f3;border-radius:10px;padding:16px 18px;cursor:pointer;transition:all 140ms;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <span style="font-size:11px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:#1a5ea8;">Upcoming</span>
            <div style="width:30px;height:30px;border-radius:8px;background:#e6f0fb;display:flex;align-items:center;justify-content:center;">
                <svg width="15" height="15" fill="none" stroke="#1a5ea8" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/></svg>
            </div>
        </div>
        <div style="font-size:28px;font-weight:700;color:#1a5ea8;line-height:1;">{{ $upcoming->count() }}</div>
        <div style="font-size:11.5px;color:#608ab8;margin-top:3px;">Next 30 days</div>
    </div>

</div>

{{-- TABS + PERIOD FILTER --}}
<div style="padding:16px 28px 0;display:flex;align-items:flex-end;justify-content:space-between;border-bottom:1.5px solid #ede4f3;">
    {{-- Tabs --}}
    <div style="display:flex;gap:2px;">
        <button @click="activeTab='dashboard'" class="task-tab" :class="activeTab==='dashboard' ? 'task-tab--active' : ''">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            Dashboard
        </button>
        <button @click="activeTab='mine'" class="task-tab" :class="activeTab==='mine' ? 'task-tab--active' : ''">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            My Tasks
        </button>
        <button @click="activeTab='assign'" class="task-tab" :class="activeTab==='assign' ? 'task-tab--active' : ''">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0"><path d="M12 4v16m8-8H4"/></svg>
            Assign Task
        </button>
    </div>
    {{-- Period Segmented Control --}}
    <div style="display:flex;background:#f3eef7;border-radius:8px;padding:3px;gap:2px;margin-bottom:6px;">
        <button @click="period='daily'" class="task-period" :class="period==='daily' ? 'task-period--active' : ''">Daily</button>
        <button @click="period='weekly'" class="task-period" :class="period==='weekly' ? 'task-period--active' : ''">Weekly</button>
        <button @click="period='monthly'" class="task-period" :class="period==='monthly' ? 'task-period--active' : ''">Monthly</button>
    </div>
</div>

{{-- TAB CONTENT --}}
<div style="flex:1;overflow-y:auto;padding:20px 28px;">

    {{-- DASHBOARD TAB --}}
    <div x-show="activeTab==='dashboard'">
        <div style="display:flex;gap:10px;margin-bottom:16px;">
            <input type="text" x-model="search" placeholder="Search tasks…"
                   style="flex:1;padding:9px 14px;border:1.5px solid #ede4f3;border-radius:7px;font-size:13px;font-family:inherit;outline:none;">
            @php
                $isStaffRole = in_array(auth()->user()->role, [
                    \App\Models\User::ROLE_ASSISTANT,
                    \App\Models\User::ROLE_FRONT_DESK,
                    \App\Models\User::ROLE_ACCOUNTS,
                ]);
            @endphp
            @if(!$isStaffRole)
            <select x-model="staffFilter" style="padding:9px 14px;border:1.5px solid #ede4f3;border-radius:7px;font-size:13px;font-family:inherit;color:#1a0320;min-width:160px;">
                <option value="">All Staff</option>
                @foreach($users as $u)
                <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
            @endif

            {{-- Source filter: All vs Practice Protocols --}}
            <div style="display:flex;background:#f3eef7;border-radius:7px;padding:3px;gap:2px;flex-shrink:0;">
                <a href="{{ route('tasks.index') }}"
                   style="padding:6px 13px;border-radius:5px;font-size:12px;font-weight:600;text-decoration:none;{{ ($source ?? '') !== 'protocol' ? 'background:#6a0f70;color:#fff;' : 'color:#9a7aaa;' }}">All</a>
                <a href="{{ route('tasks.index', ['source' => 'protocol']) }}"
                   style="padding:6px 13px;border-radius:5px;font-size:12px;font-weight:600;text-decoration:none;{{ ($source ?? '') === 'protocol' ? 'background:#6a0f70;color:#fff;' : 'color:#9a7aaa;' }}">Protocols</a>
            </div>
        </div>

        @if($overdue->count())
        <div style="margin-bottom:20px;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                <div style="width:8px;height:8px;border-radius:50%;background:#b52020;"></div>
                <span style="font-size:11.5px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#b52020;">Backlog · {{ $overdue->count() }}</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;">
                @foreach($overdue as $task)@include('tasks._card',['task'=>$task,'badge'=>'backlog'])@endforeach
            </div>
        </div>
        @endif

        @if($today->count())
        <div style="margin-bottom:20px;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                <div style="width:8px;height:8px;border-radius:50%;background:#a05c00;"></div>
                <span style="font-size:11.5px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#a05c00;">Due Today · {{ $today->count() }}</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;">
                @foreach($today as $task)@include('tasks._card',['task'=>$task,'badge'=>'today'])@endforeach
            </div>
        </div>
        @endif

        @if($upcoming->count())
        <div style="margin-bottom:20px;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                <div style="width:8px;height:8px;border-radius:50%;background:#1a5ea8;"></div>
                <span style="font-size:11.5px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#1a5ea8;">Upcoming · {{ $upcoming->count() }}</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;">
                @foreach($upcoming as $task)@include('tasks._card',['task'=>$task,'badge'=>'upcoming'])@endforeach
            </div>
        </div>
        @endif

        @if($done->count())
        <div style="margin-bottom:20px;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                <div style="width:8px;height:8px;border-radius:50%;background:#1a7a45;"></div>
                <span style="font-size:11.5px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#1a7a45;">Completed · {{ $done->count() }}</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;">
                @foreach($done as $task)@include('tasks._card',['task'=>$task,'badge'=>'done'])@endforeach
            </div>
        </div>
        @endif

        @if($overdue->isEmpty() && $today->isEmpty() && $upcoming->isEmpty() && $done->isEmpty())
        <div style="text-align:center;padding:60px 20px;color:#b0a0bb;">
            <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24" style="margin:0 auto 14px;display:block;opacity:.35;"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            <p style="font-size:14px;font-weight:500;margin:0 0 4px;">No tasks yet</p>
            <p style="font-size:12.5px;color:#c5b0d5;margin:0;">Click Assign Task to create one.</p>
        </div>
        @endif
    </div>

    {{-- MY TASKS TAB --}}
    <div x-show="activeTab==='mine'" x-cloak>
        @php
            $myTasks = collect()
                ->merge($overdue->where('assigned_to', auth()->id()))
                ->merge($today->where('assigned_to', auth()->id()));
        @endphp
        @if($myTasks->isEmpty())
        <div style="text-align:center;padding:60px 20px;color:#b0a0bb;">
            <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24" style="margin:0 auto 14px;display:block;opacity:.35;"><polyline points="20 6 9 17 4 12"/></svg>
            <p style="font-size:14px;font-weight:500;margin:0;">You're all caught up!</p>
        </div>
        @else
        <div style="display:flex;flex-direction:column;gap:8px;">
            @foreach($myTasks as $task)
                @include('tasks._card',['task'=>$task,'badge'=>$task->due_date->lt(today())?'backlog':'today','showActions'=>true])
            @endforeach
        </div>
        @endif
    </div>

    {{-- ASSIGN TASK TAB --}}
    <div x-show="activeTab==='assign'" x-cloak>
        <div style="max-width:600px;">
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;padding:28px;">
                <h3 style="font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:700;color:#1a0320;margin:0 0 20px;">New Task Assignment</h3>
                <form action="{{ route('tasks.store') }}" method="POST">
                    @csrf
                    <div style="margin-bottom:14px;">
                        <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Task Description *</label>
                        <input type="text" name="title" required placeholder="e.g. Call katara dental lab regarding crown case"
                               style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                    </div>
                    <div style="margin-bottom:14px;">
                        <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Assign To *</label>
                        <select name="assigned_to" required style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;color:#1a0320;box-sizing:border-box;">
                            <option value="">— Select staff member —</option>
                            @foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                        </select>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                        <div>
                            <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Due Date *</label>
                            <input type="date" name="due_date" required value="{{ today()->toDateString() }}"
                                   style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Due Time</label>
                            <input type="time" name="due_time" style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                        <div>
                            <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Priority</label>
                            <select name="priority" style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                                <option value="urgent">Urgent</option>
                                <option value="high">High</option>
                                <option value="medium" selected>Medium</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Category</label>
                            <select name="category" style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                                <option value="admin">Admin</option>
                                <option value="clinical">Clinical</option>
                                <option value="lab">Lab</option>
                                <option value="follow_up">Follow-up</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    {{-- Patient toggle --}}
                    <div style="margin-bottom:14px;background:#f8f4fc;border:1.5px solid #ede4f3;border-radius:9px;padding:12px 14px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span style="font-size:13px;font-weight:600;color:#1a0320;">Related to a patient?</span>
                            <label style="display:inline-flex;align-items:center;cursor:pointer;">
                                <input type="checkbox" x-model="linkedPatientTab" style="opacity:0;width:0;height:0;position:absolute;">
                                <div :style="linkedPatientTab
                                        ? 'width:36px;height:20px;border-radius:10px;background:#6a0f70;display:inline-block;position:relative;transition:background 180ms;cursor:pointer;'
                                        : 'width:36px;height:20px;border-radius:10px;background:#e0d5e8;display:inline-block;position:relative;transition:background 180ms;cursor:pointer;'">
                                    <div :style="linkedPatientTab
                                            ? 'position:absolute;top:3px;left:3px;width:14px;height:14px;border-radius:50%;background:#fff;transition:transform 180ms;transform:translateX(16px);box-shadow:0 1px 3px rgba(0,0,0,.18);'
                                            : 'position:absolute;top:3px;left:3px;width:14px;height:14px;border-radius:50%;background:#fff;transition:transform 180ms;transform:translateX(0);box-shadow:0 1px 3px rgba(0,0,0,.18);'">
                                    </div>
                                </div>
                            </label>
                        </div>
                        <div x-show="linkedPatientTab" x-cloak style="margin-top:12px;"
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
                                     style="position:absolute;top:calc(100% + 4px);left:0;right:0;background:#fff;border:1.5px solid #d4bde8;border-radius:8px;box-shadow:0 4px 18px rgba(14,1,24,.13);z-index:99;max-height:200px;overflow-y:auto;">
                                    <template x-for="p in results" :key="p.id">
                                        <div @click="pick(p)"
                                             style="padding:9px 13px;cursor:pointer;border-bottom:1px solid #f3eef7;display:flex;align-items:center;gap:10px;"
                                             @mouseenter="$el.style.background='#f5eefa'" @mouseleave="$el.style.background='#fff'">
                                            <div style="width:28px;height:28px;border-radius:50%;background:#6a0f7020;color:#6a0f70;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;"
                                                 x-text="p.name.charAt(0).toUpperCase()"></div>
                                            <div>
                                                <div style="font-size:13px;font-weight:600;color:#1a0320;" x-text="p.name"></div>
                                                <div style="font-size:11.5px;color:#9a7aaa;" x-text="(p.patient_id ?? '') + (p.phone ? ' · ' + p.phone : '')"></div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div x-show="selectedId" style="margin-top:5px;font-size:11.5px;color:#6a0f70;display:flex;align-items:center;gap:5px;">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                <span x-text="'Linked: ' + query"></span>
                                <span @click="selectedId='';query=''" style="cursor:pointer;color:#b52020;margin-left:4px;">✕</span>
                            </div>
                        </div>
                    </div>
                    {{-- /Patient toggle --}}
                    <div style="margin-bottom:20px;display:flex;align-items:center;gap:10px;">
                        <label class="df-toggle" :class="needEvidence?'on':''">
                            <input type="checkbox" name="need_evidence" x-model="needEvidence" style="display:none;">
                            <span class="df-toggle-track"></span>
                        </label>
                        <span style="font-size:13px;color:#1a0320;">Require evidence on completion</span>
                    </div>
                    <div style="display:flex;gap:10px;">
                        <button type="submit" style="flex:1;padding:11px;background:#6a0f70;color:#fff;border:none;border-radius:7px;font-size:13.5px;font-weight:600;cursor:pointer;font-family:inherit;">Assign Task</button>
                        <button type="button" @click="activeTab='dashboard'" style="padding:11px 20px;background:#fff;color:#6a0f70;border:1.5px solid #ede4f3;border-radius:7px;font-size:13px;cursor:pointer;font-family:inherit;">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>{{-- /tab content --}}

{{-- CENTERED MODAL --}}
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

<style>
[x-cloak]{display:none!important;}

/* ── Task Tabs ── */
.task-tab {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 18px;
    background: none;
    border: none;
    border-bottom: 2.5px solid transparent;
    font-size: 13px;
    font-family: inherit;
    font-weight: 500;
    color: #9a7aaa;
    cursor: pointer;
    transition: color 150ms, border-color 150ms;
    margin-bottom: -1.5px;
    white-space: nowrap;
}
.task-tab:hover { color: #6a0f70; }
.task-tab--active {
    color: #6a0f70 !important;
    border-bottom-color: #6a0f70 !important;
    font-weight: 600;
}

/* ── Period Segmented Control ── */
.task-period {
    padding: 5px 14px;
    background: none;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-family: inherit;
    font-weight: 500;
    color: #9a7aaa;
    cursor: pointer;
    transition: all 150ms;
}
.task-period--active {
    background: #6a0f70 !important;
    color: #fff !important;
    box-shadow: 0 1px 4px rgba(106,15,112,.25);
}

/* ── Toggle ── */
.df-toggle{display:inline-flex;align-items:center;cursor:pointer;}
.df-toggle-track{width:36px;height:20px;border-radius:10px;background:#e0d5e8;display:inline-block;position:relative;transition:background 180ms;}
.df-toggle-track::after{content:'';position:absolute;top:3px;left:3px;width:14px;height:14px;border-radius:50%;background:#fff;transition:transform 180ms;box-shadow:0 1px 3px rgba(0,0,0,.18);}
.df-toggle.on .df-toggle-track{background:#6a0f70;}
.df-toggle.on .df-toggle-track::after{transform:translateX(16px);}
</style>

<script>
function taskModule(){
    return {
        activeTab:'dashboard', activeFilter:null, period:'daily',
        drawerOpen:false, search:'', staffFilter:'', needEvidence:false,
        linkedPatientTab: false,  // toggle for Assign Task tab form
        init(){},
        setFilter(f){ this.activeFilter = this.activeFilter===f ? null : f; },
    };
}

// Alpine component for the drawer/create form — tracks category + recurring toggle
function drawerForm(){
    return {
        category: 'admin',
        isRecurring: false,
        linkedPatient: false,  // toggle: is this task patient-related?
    };
}

// Patient autocomplete — used by both drawer and tab form
function patientSearch(){
    return {
        query: '',
        selectedId: '',
        results: [],
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
@endsection
