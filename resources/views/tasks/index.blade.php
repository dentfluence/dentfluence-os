@extends('layouts.app')
@section('page-title', 'Tasks')

@section('content')
<div
    x-data="taskModule()"
    x-init="init()"
    style="font-family:'DM Sans',sans-serif;padding:0;height:100%;display:flex;flex-direction:column;"
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
            <select x-model="staffFilter" style="padding:9px 14px;border:1.5px solid #ede4f3;border-radius:7px;font-size:13px;font-family:inherit;color:#1a0320;min-width:160px;">
                <option value="">All Staff</option>
                @foreach($users as $u)
                <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
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
                                <option value="urgent">🔴 Urgent</option>
                                <option value="high">🟠 High</option>
                                <option value="medium" selected>🟡 Medium</option>
                                <option value="low">🟢 Low</option>
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
                    <div style="margin-bottom:14px;">
                        <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Link to Patient <span style="font-weight:400;color:#b0a0bb;">optional</span></label>
                        <input type="hidden" name="patient_id">
                        <input type="text" placeholder="Search patient…" style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                    </div>
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

{{-- SLIDE-IN DRAWER --}}
<div x-show="drawerOpen" x-cloak style="position:fixed;inset:0;z-index:60;display:flex;">
    <div style="position:absolute;inset:0;background:rgba(14,1,24,.35);" @click="drawerOpen=false"></div>
    <div style="position:absolute;right:0;top:0;bottom:0;width:420px;background:#fff;box-shadow:-4px 0 24px rgba(14,1,24,.15);overflow-y:auto;padding:28px;z-index:1;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
            <h2 style="font-family:'Cormorant Garamond',serif;font-size:21px;font-weight:700;color:#1a0320;margin:0;">Create a Task</h2>
            <button @click="drawerOpen=false" style="background:none;border:none;cursor:pointer;color:#9a7aaa;padding:4px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form action="{{ route('tasks.store') }}" method="POST">
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
                        <option value="urgent">🔴 Urgent</option><option value="high">🟠 High</option><option value="medium" selected>🟡 Medium</option><option value="low">🟢 Low</option>
                    </select>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Category</label>
                    <select name="category" style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                        <option value="admin">Admin</option><option value="clinical">Clinical</option><option value="lab">Lab</option><option value="follow_up">Follow-up</option><option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Due Time</label>
                    <input type="time" name="due_time" style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                </div>
            </div>
            <div style="margin-bottom:20px;">
                <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Link Patient <span style="font-weight:400;color:#b0a0bb;">optional</span></label>
                <input type="hidden" name="patient_id">
                <input type="text" placeholder="Patient name or ID…" style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
            </div>
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
        init(){},
        setFilter(f){ this.activeFilter = this.activeFilter===f ? null : f; },
    };
}
</script>
@endsection
