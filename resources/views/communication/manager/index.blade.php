@extends('layouts.communication')

@push('communication-styles')
<style>
/* ── Communication List — PRM Update 2026-06-13 ── */
.cl-page { font-family: var(--comm-font, 'Inter', sans-serif); }
.cl-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; padding:14px 24px; background:#fff; border-bottom:1px solid #e2e8f0; }
.cl-header__left { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.cl-header__title { font-size:15px; font-weight:600; color:#0f172a; margin:0; }
.cl-chip { display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:500; padding:3px 10px; border-radius:20px; border:1px solid #e2e8f0; background:#f8fafc; color:#475569; white-space:nowrap; }
.cl-chip__count { font-weight:700; }
.cl-chip--danger  { background:#fff1f2; border-color:#fecaca; color:#dc2626; }
.cl-chip--warning { background:#fffbeb; border-color:#fde68a; color:#d97706; }
.cl-chip--info    { background:#eff6ff; border-color:#bfdbfe; color:#2563eb; }
.cl-chip--muted   { background:#f1f5f9; border-color:#e2e8f0; color:#64748b; }

.cl-search-row { display:flex; align-items:center; gap:6px; }
.cl-search-input { border:1px solid #e2e8f0; padding:6px 12px; font-size:13px; border-radius:6px; width:200px; outline:none; }
.cl-search-input:focus { border-color:#6a0f70; }
.cl-btn { display:inline-flex; align-items:center; gap:5px; padding:6px 14px; font-size:13px; font-weight:500; border-radius:6px; cursor:pointer; border:none; transition:background .15s; text-decoration:none; white-space:nowrap; }
.cl-btn--primary { background:#6a0f70; color:#fff; }
.cl-btn--primary:hover { background:#4e0b52; color:#fff; }
.cl-btn--outline { background:#fff; color:#374151; border:1px solid #d1d5db; }
.cl-btn--outline:hover { background:#f9fafb; }
.cl-btn--sm { padding:5px 10px; font-size:12px; }

.cl-filter-panel { background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:10px 24px; display:flex; align-items:flex-end; flex-wrap:wrap; gap:10px; }
.cl-filter-group { display:flex; flex-direction:column; gap:3px; }
.cl-filter-label { font-size:10px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.05em; }
.cl-filter-select, .cl-filter-input { border:1px solid #e2e8f0; padding:5px 10px; font-size:12px; border-radius:5px; background:#fff; outline:none; min-width:110px; }
.cl-filter-select:focus, .cl-filter-input:focus { border-color:#6a0f70; }

.cl-tabs { display:flex; border-bottom:2px solid #e2e8f0; background:#fff; padding:0 24px; overflow-x:auto; gap:0; }
.cl-tab { display:inline-flex; align-items:center; gap:5px; padding:10px 14px; font-size:12px; font-weight:500; color:#64748b; border-bottom:2px solid transparent; margin-bottom:-2px; white-space:nowrap; text-decoration:none; transition:color .15s, border-color .15s; }
.cl-tab:hover { color:#6a0f70; }
.cl-tab.is-active { color:#6a0f70; border-bottom-color:#6a0f70; font-weight:600; }
.cl-tab__badge { font-size:10px; font-weight:700; background:#f1f5f9; color:#475569; padding:1px 6px; border-radius:9px; }
.cl-tab.is-active .cl-tab__badge { background:#6a0f70; color:#fff; }
.cl-tab__badge--red { background:#fee2e2 !important; color:#dc2626 !important; }

.cl-table-wrap { padding:16px 24px 32px; overflow-x:auto; }
.cl-table { width:100%; border-collapse:collapse; font-size:13px; }
.cl-table thead th { padding:9px 12px; font-size:10px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.05em; border-bottom:2px solid #e2e8f0; text-align:left; white-space:nowrap; background:#f8fafc; }
.cl-table tbody tr { border-bottom:1px solid #f1f5f9; cursor:pointer; }
.cl-table tbody tr:hover { background:#f8f4fa; }
.cl-table tbody td { padding:9px 12px; vertical-align:middle; }
.cl-table input[type=checkbox] { cursor:pointer; width:14px; height:14px; accent-color:#6a0f70; }

.cl-badge { display:inline-block; font-size:10px; font-weight:600; padding:2px 7px; border-radius:9px; white-space:nowrap; }
.cl-badge--warning  { background:#fffbeb; color:#b45309; border:1px solid #fde68a; }
.cl-badge--danger   { background:#fff1f2; color:#dc2626; border:1px solid #fecaca; }
.cl-badge--info     { background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; }
.cl-badge--success  { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; }
.cl-badge--secondary{ background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; }

.cl-actions { display:flex; align-items:center; gap:2px; }
.cl-action-btn { display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:4px; border:none; cursor:pointer; background:transparent; color:#94a3b8; transition:background .1s, color .1s; }
.cl-action-btn:hover { background:#f1f5f9; color:#0f172a; }
.cl-action-btn--ok:hover { background:#f0fdf4; color:#16a34a; }

.cl-bulk-bar { position:sticky; bottom:0; background:#1e293b; color:#fff; padding:10px 24px; display:flex; align-items:center; gap:10px; flex-wrap:wrap; z-index:30; }
.cl-bulk-bar__count { font-size:13px; font-weight:600; }
.cl-bulk-btn { padding:5px 12px; font-size:12px; font-weight:500; border-radius:5px; border:none; cursor:pointer; background:rgba(255,255,255,.12); color:#fff; }
.cl-bulk-btn:hover { background:rgba(255,255,255,.22); }
.cl-bulk-btn--danger { background:rgba(239,68,68,.25); }
.cl-bulk-btn--danger:hover { background:rgba(239,68,68,.4); }

.cl-empty { padding:60px 24px; text-align:center; color:#94a3b8; }
.cl-empty__icon { font-size:32px; margin-bottom:8px; }

.cl-flash { margin:10px 24px 0; padding:9px 16px; border-radius:6px; font-size:13px; font-weight:500; }
.cl-flash--success { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; }
.cl-flash--error   { background:#fff1f2; border:1px solid #fecaca; color:#dc2626; }

.cl-name { font-weight:500; color:#0f172a; font-size:13px; }
.cl-patient-link { font-size:10px; color:#6a0f70; text-decoration:none; }
.cl-patient-link:hover { text-decoration:underline; }
</style>
@endpush

@section('communication-content')
<div class="cl-page" x-data="{
    selected: [],
    showFilters: {{ count(array_filter($filters)) > 0 ? 'true' : 'false' }},
    showBulkAssign: false,

    toggleAll(e) {
        const boxes = document.querySelectorAll('.cl-row-check');
        this.selected = e.target.checked ? Array.from(boxes).map(b => parseInt(b.value)) : [];
        boxes.forEach(b => b.checked = e.target.checked);
    },
    toggleRow(id) {
        const idx = this.selected.indexOf(id);
        idx === -1 ? this.selected.push(id) : this.selected.splice(idx, 1);
    },
    goToRow(url, e) {
        if (e.target.closest('input,button,a,form,[data-no-nav]')) return;
        window.location.href = url;
    }
}">

    {{-- Flash --}}
    @if(session('success'))
        <div class="cl-flash cl-flash--success">✓ {{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="cl-flash cl-flash--error">{{ $errors->first() }}</div>
    @endif

    {{-- ── Header ── --}}
    <div class="cl-header">
        <div class="cl-header__left">
            <h1 class="cl-header__title">Communication List</h1>
            <span class="cl-chip cl-chip--warning">
                <span class="cl-chip__count">{{ $headerCounts['pending'] }}</span> Pending
            </span>
            <span class="cl-chip cl-chip--danger">
                <span class="cl-chip__count">{{ $headerCounts['overdue'] }}</span> Overdue
            </span>
            <span class="cl-chip cl-chip--info">
                <span class="cl-chip__count">{{ $headerCounts['closed_today'] }}</span> Closed Today
            </span>
            <span class="cl-chip cl-chip--muted">
                <span class="cl-chip__count">{{ $headerCounts['my_queue'] }}</span> My Queue
            </span>
        </div>

        <div class="cl-search-row">
            <form method="GET" action="{{ route('communication.manager.index') }}" style="display:flex;gap:6px;">
                @foreach($filters as $k => $v) @if($v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="text" name="search" class="cl-search-input"
                    placeholder="Search name or mobile…" value="{{ $search }}" autocomplete="off">
                <button type="submit" class="cl-btn cl-btn--outline cl-btn--sm">Search</button>
            </form>

            <button class="cl-btn cl-btn--outline cl-btn--sm" @click="showFilters = !showFilters">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                Filters
                @if(count(array_filter($filters)) > 0)
                    <span style="background:#6a0f70;color:#fff;font-size:9px;font-weight:700;padding:1px 5px;border-radius:8px;margin-left:2px;">{{ count(array_filter($filters)) }}</span>
                @endif
            </button>

            <a href="{{ route('communication.manager.log.form') }}" class="cl-btn cl-btn--primary">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Communication
            </a>
        </div>
    </div>

    {{-- ── Filter panel ── --}}
    <div class="cl-filter-panel" x-show="showFilters" x-transition style="display:none;">
        <form method="GET" action="{{ route('communication.manager.index') }}" style="display:contents;">
            <input type="hidden" name="tab" value="{{ $tab }}">
            @if($search)<input type="hidden" name="search" value="{{ $search }}">@endif

            <div class="cl-filter-group">
                <span class="cl-filter-label">Date</span>
                <input type="date" name="filter_date" class="cl-filter-input" value="{{ $filters['filter_date'] ?? '' }}">
            </div>
            <div class="cl-filter-group">
                <span class="cl-filter-label">Owner</span>
                <select name="filter_owner" class="cl-filter-select">
                    <option value="">All Owners</option>
                    @foreach($users as $u)
                        <option value="{{ $u->name }}" {{ ($filters['filter_owner'] ?? '') === $u->name ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="cl-filter-group">
                <span class="cl-filter-label">Channel</span>
                <select name="filter_channel" class="cl-filter-select">
                    <option value="">All Channels</option>
                    @foreach($channels as $key => $label)
                        <option value="{{ $key }}" {{ ($filters['filter_channel'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="cl-filter-group">
                <span class="cl-filter-label">Type</span>
                <select name="filter_type" class="cl-filter-select">
                    <option value="">All Types</option>
                    @foreach($commTypes as $key => $label)
                        <option value="{{ $key }}" {{ ($filters['filter_type'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="cl-filter-group">
                <span class="cl-filter-label">Status</span>
                <select name="filter_status" class="cl-filter-select">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $key => $label)
                        <option value="{{ $key }}" {{ ($filters['filter_status'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="cl-filter-group">
                <span class="cl-filter-label">Priority</span>
                <select name="filter_priority" class="cl-filter-select">
                    <option value="">All</option>
                    <option value="high"   {{ ($filters['filter_priority'] ?? '') === 'high'   ? 'selected' : '' }}>High</option>
                    <option value="medium" {{ ($filters['filter_priority'] ?? '') === 'medium' ? 'selected' : '' }}>Medium</option>
                    <option value="low"    {{ ($filters['filter_priority'] ?? '') === 'low'    ? 'selected' : '' }}>Low</option>
                </select>
            </div>

            <button type="submit" class="cl-btn cl-btn--primary cl-btn--sm" style="align-self:flex-end;">Apply</button>
            <a href="{{ route('communication.manager.index', ['tab' => $tab]) }}" class="cl-btn cl-btn--outline cl-btn--sm" style="align-self:flex-end;">Clear</a>
        </form>
    </div>

    {{-- ── Tabs ── --}}
    @php
    $tabDefs = ['pending'=>'Pending','today'=>'Today','overdue'=>'Overdue','completed'=>'Completed','my_queue'=>'My Queue','all'=>'All'];
    $tabParams = array_filter(array_merge($filters, $search ? ['search'=>$search] : []));
    @endphp
    <div class="cl-tabs">
        @foreach($tabDefs as $key => $label)
        <a href="{{ route('communication.manager.index', array_merge($tabParams, ['tab'=>$key])) }}"
           class="cl-tab {{ $tab === $key ? 'is-active' : '' }}">
            {{ $label }}
            <span class="cl-tab__badge {{ $key==='overdue' && $counts[$key]>0 ? 'cl-tab__badge--red' : '' }}">
                {{ $counts[$key] }}
            </span>
        </a>
        @endforeach
    </div>

    {{-- ── Table ── --}}
    <div class="cl-table-wrap">
        @if($items->isEmpty())
            <div class="cl-empty">
                <p style="font-size:14px;margin:0;">No communications in this view.</p>
                <p style="font-size:12px;color:#cbd5e1;margin:4px 0 0;">
                    <a href="{{ route('communication.manager.log.form') }}" style="color:#6a0f70;">Add the first one →</a>
                </p>
            </div>
        @else
        <table class="cl-table">
            <thead>
                <tr>
                    <th style="width:36px;"><input type="checkbox" @change="toggleAll($event)"></th>
                    <th>Date</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Channel</th>
                    <th>Type</th>
                    <th>Next Action</th>
                    <th>Due Date</th>
                    <th>Owner</th>
                    <th>Status</th>
                    <th style="width:120px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                @php $dueDate = $item->follow_up_date ?? $item->due_at; @endphp
                <tr @click="goToRow('{{ route('communication.manager.show', $item->id) }}', $event)">

                    <td style="width:36px;" data-no-nav>
                        <input type="checkbox" class="cl-row-check" value="{{ $item->id }}"
                            @change="toggleRow({{ $item->id }})">
                    </td>

                    <td style="color:#64748b;font-size:12px;white-space:nowrap;">
                        {{ $item->created_at->format('d M, g:ia') }}
                    </td>

                    <td>
                        <div class="cl-name">{{ $item->person_name }}</div>
                        @if($item->patient)
                            <a href="{{ route('patients.show', $item->patient_id) }}" class="cl-patient-link">
                                #{{ $item->patient_id }} · {{ $item->patient->first_name }}
                            </a>
                        @endif
                    </td>

                    <td style="font-size:13px;white-space:nowrap;">{{ $item->phone }}</td>

                    <td>
                        <span style="font-size:15px;">{{ $item->channel_icon }}</span>
                        <span style="font-size:11px;color:#64748b;"> {{ $item->channel_label }}</span>
                    </td>

                    <td>
                        <span class="cl-badge cl-badge--secondary">{{ $item->comm_type_label }}</span>
                    </td>

                    <td>
                        @if($item->next_action)
                            <span class="cl-badge cl-badge--info">{{ $item->next_action_label }}</span>
                        @else
                            <span style="color:#cbd5e1;font-size:12px;">—</span>
                        @endif
                    </td>

                    <td style="white-space:nowrap;">
                        @if($dueDate)
                            <span style="font-size:12px;color:{{ $item->is_overdue ? '#dc2626' : '#374151' }};font-weight:{{ $item->is_overdue ? 600 : 400 }};">
                                {{ \Carbon\Carbon::parse($dueDate)->format('d M') }}
                                @if($item->follow_up_time) · {{ $item->follow_up_time }}@endif
                            </span>
                            @if($item->is_overdue)
                                <div style="font-size:10px;color:#dc2626;">{{ $item->overdue_since }} ago</div>
                            @endif
                        @else
                            <span style="color:#cbd5e1;font-size:12px;">—</span>
                        @endif
                    </td>

                    <td>
                        @if($item->assigned_to)
                        <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;">
                            <span style="width:20px;height:20px;border-radius:50%;background:#6a0f70;color:#fff;font-size:9px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;">
                                {{ strtoupper(substr($item->assigned_to,0,1)) }}
                            </span>
                            {{ $item->assigned_to }}
                        </span>
                        @else
                            <span style="color:#cbd5e1;font-size:12px;">—</span>
                        @endif
                    </td>

                    <td>
                        <span class="cl-badge {{ $item->status_badge_class }}">{{ $item->status_label }}</span>
                    </td>

                    {{-- Row actions --}}
                    <td data-no-nav>
                        <div class="cl-actions">
                            <a href="{{ route('communication.manager.show', $item->id) }}" class="cl-action-btn" title="View">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </a>
                            <a href="{{ route('communication.manager.show', $item->id) }}?edit=1" class="cl-action-btn" title="Edit">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>

                            {{-- Move dropdown --}}
                            <div x-data="{ open:false }" style="position:relative;">
                                <button type="button" class="cl-action-btn" title="Move" @click.stop="open=!open">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="5 9 2 12 5 15"/><polyline points="9 5 12 2 15 5"/><line x1="2" y1="12" x2="22" y2="12"/><line x1="12" y1="2" x2="12" y2="22"/></svg>
                                </button>
                                <div x-show="open" @click.away="open=false" x-transition
                                     style="position:absolute;right:0;top:100%;background:#fff;border:1px solid #e2e8f0;border-radius:6px;min-width:158px;box-shadow:0 4px 16px rgba(0,0,0,.1);z-index:50;padding:4px 0;">
                                    <form method="POST" action="{{ route('communication.manager.move', $item->id) }}">
                                        @csrf
                                        @foreach(['prm_pipeline'=>'PRM Pipeline','follow_ups'=>'Follow-ups','calendar'=>'Calendar/Appointment','task'=>'Create Task','archive'=>'Archive'] as $d => $dl)
                                        <button type="submit" name="move_to" value="{{ $d }}"
                                            class="cl-dropdown-item" style="display:block;width:100%;text-align:left;padding:7px 14px;font-size:12px;background:none;border:none;cursor:pointer;color:#374151;"
                                            onmouseover="this.style.background='#f8f4fa'" onmouseout="this.style.background='none'">
                                            {{ $dl }}
                                        </button>
                                        @endforeach
                                    </form>
                                </div>
                            </div>

                            {{-- Assign dropdown --}}
                            <div x-data="{ open:false }" style="position:relative;">
                                <button type="button" class="cl-action-btn" title="Assign" @click.stop="open=!open">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                </button>
                                <div x-show="open" @click.away="open=false" x-transition
                                     style="position:absolute;right:0;top:100%;background:#fff;border:1px solid #e2e8f0;border-radius:6px;min-width:148px;box-shadow:0 4px 16px rgba(0,0,0,.1);z-index:50;padding:4px 0;">
                                    <form method="POST" action="{{ route('communication.manager.assign', $item->id) }}">
                                        @csrf
                                        @foreach($users as $u)
                                        <button type="submit" name="assigned_to" value="{{ $u->name }}"
                                            style="display:block;width:100%;text-align:left;padding:7px 14px;font-size:12px;background:none;border:none;cursor:pointer;color:#374151;"
                                            onmouseover="this.style.background='#f8f4fa'" onmouseout="this.style.background='none'">
                                            {{ $u->name }}
                                        </button>
                                        @endforeach
                                    </form>
                                </div>
                            </div>

                            {{-- Mark Closed --}}
                            @if($item->status !== 'closed')
                            <form method="POST" action="{{ route('communication.manager.close', $item->id) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="cl-action-btn cl-action-btn--ok" title="Mark Closed"
                                    onclick="return confirm('Mark as closed?')">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- ── Bulk bar ── --}}
    <div class="cl-bulk-bar" x-show="selected.length > 0" x-transition style="display:none;">
        <span class="cl-bulk-bar__count" x-text="selected.length + ' selected'"></span>

        <form method="POST" action="{{ route('communication.manager.bulk') }}" id="cl-bulk-form" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            @csrf
            <template x-for="id in selected" :key="id">
                <input type="hidden" name="comm_ids[]" :value="id">
            </template>

            <div x-show="showBulkAssign" style="display:none;" x-transition>
                <select name="assign_to" class="cl-filter-select" style="font-size:12px;padding:4px 8px;background:#2d3f55;border-color:#4a6080;color:#fff;">
                    <option value="">Pick person…</option>
                    @foreach($users as $u)
                        <option value="{{ $u->name }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="button" class="cl-bulk-btn" @click="showBulkAssign=!showBulkAssign">Assign…</button>
            <button type="submit" name="action" value="assign" class="cl-bulk-btn" x-show="showBulkAssign">Apply Assign</button>
            <button type="submit" name="action" value="move_prm" class="cl-bulk-btn">→ PRM Pipeline</button>
            <button type="submit" name="action" value="move_followups" class="cl-bulk-btn">→ Follow-ups</button>
            <button type="submit" name="action" value="mark_closed" class="cl-bulk-btn">Mark Closed</button>
            <button type="submit" name="action" value="archive" class="cl-bulk-btn cl-bulk-btn--danger"
                onclick="return confirm('Archive selected?')">Archive</button>
        </form>

        <button @click="selected=[];document.querySelectorAll('.cl-row-check,thead input[type=checkbox]').forEach(b=>b.checked=false);showBulkAssign=false;"
            style="margin-left:auto;background:none;border:none;color:#94a3b8;cursor:pointer;font-size:12px;">
            ✕ Clear
        </button>
    </div>

</div>
@endsection
