{{--
|==========================================================================
| Missed Calls — full backlog list
| /relationship/today/missed-calls
|
| Variables from MissedCallsController::index():
|   $items           — LengthAwarePaginator of CommunicationQueue (with patient)
|   $filters         — array: search, purpose, priority
|   $purposeOptions  — CommunicationQueue::PURPOSES lookup table
|   $showIgnored     — bool, whether ignored items are included
|
| The dashboard widget at /relationship/today only ever samples up to
| max_per_category rows for exactly "yesterday". This page is the full
| backlog (yesterday-or-older, still pending) behind that count badge.
|==========================================================================
--}}
@extends('relationship.layouts.app')

@section('page-title', 'Missed Calls')

@section('head-extra')
@unless(app()->has('tabler_icons_loaded'))
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
@endunless
<style>
    /* ── Missed Calls list — matches Today's Actions palette ── */
    .mc-page-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 18px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .mc-page-title {
        font-family: 'Cormorant Garamond', Georgia, serif;
        font-size: 26px;
        font-weight: 600;
        color: #1a0320;
        margin: 0 0 4px;
    }

    .mc-page-sub { font-size: 13px; color: #9a7aaa; margin: 0; }

    .mc-back-link {
        font-size: 12px;
        color: #6a0f70;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-bottom: 8px;
    }
    .mc-back-link:hover { text-decoration: underline; }

    .mc-total-badge {
        font-size: 13px;
        font-weight: 600;
        color: #6a0f70;
        background: #f3e8f4;
        border: 1px solid #dfc5e1;
        border-radius: 99px;
        padding: 4px 14px;
        white-space: nowrap;
    }

    /* ── Filter bar ── */
    .mc-filter-bar {
        display: flex;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: 10px;
        background: #faf5fc;
        border: 1px solid #e8dff0;
        border-radius: 12px;
        padding: 12px 14px;
        margin-bottom: 14px;
    }

    .mc-filter-group { display: flex; flex-direction: column; gap: 3px; }

    .mc-filter-label {
        font-size: 10px;
        font-weight: 700;
        color: #9a7aaa;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .mc-filter-input, .mc-filter-select {
        border: 1px solid #dfc5e1;
        border-radius: 7px;
        padding: 6px 10px;
        font-size: 12.5px;
        color: #1a0320;
        background: #fff;
        outline: none;
        min-width: 140px;
    }

    .mc-filter-input:focus, .mc-filter-select:focus { border-color: #6a0f70; }

    .mc-filter-check {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12.5px;
        color: #4e0a53;
        padding-bottom: 6px;
    }

    .mc-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 12.5px;
        font-weight: 600;
        padding: 7px 14px;
        border-radius: 7px;
        border: 1px solid #dfc5e1;
        background: #fff;
        color: #6a0f70;
        cursor: pointer;
        text-decoration: none;
    }
    .mc-btn:hover { background: #f3e8f4; }
    .mc-btn--primary { background: #6a0f70; color: #fff; border-color: #6a0f70; }
    .mc-btn--primary:hover { background: #4e0a53; }

    /* ── Table ── */
    .mc-table-wrap {
        background: #fff;
        border: 1px solid #e8dff0;
        border-radius: 14px;
        overflow: hidden;
    }

    .mc-table { width: 100%; border-collapse: collapse; font-size: 13px; }

    .mc-table thead th {
        padding: 10px 12px;
        font-size: 10.5px;
        font-weight: 700;
        color: #9a7aaa;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px solid #e8dff0;
        text-align: left;
        white-space: nowrap;
        background: #faf5fc;
    }

    .mc-table tbody tr { border-bottom: 1px solid #f8f4fc; }
    .mc-table tbody tr:hover { background: #fdf9ff; }
    .mc-table tbody tr.mc-row--ignored { opacity: 0.5; }
    .mc-table tbody td { padding: 10px 12px; vertical-align: middle; }
    .mc-table input[type=checkbox] { cursor: pointer; width: 14px; height: 14px; accent-color: #6a0f70; }

    .mc-name { font-weight: 600; color: #1a0320; font-size: 13px; }
    .mc-reason { font-size: 12px; color: #6a5a76; margin-top: 1px; }
    .mc-phone-link { font-size: 12px; color: #6a0f70; text-decoration: none; }
    .mc-phone-link:hover { text-decoration: underline; }

    .mc-priority {
        font-size: 10px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 99px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .mc-priority--high   { background: #fdeaea; color: #b52020; }
    .mc-priority--medium { background: #fff4e0; color: #a05c00; }
    .mc-priority--low    { background: #e8f7ef; color: #1a7a45; }

    .mc-actions { display: flex; align-items: center; gap: 4px; }

    .mc-action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 27px;
        height: 27px;
        border-radius: 6px;
        border: 1px solid #dfc5e1;
        background: #fff;
        color: #6a0f70;
        cursor: pointer;
        text-decoration: none;
        font-size: 12px;
    }
    .mc-action-btn:hover { background: #f3e8f4; }
    .mc-action-btn--muted { color: #9a7aaa; }

    /* ── Bulk bar ── */
    .mc-bulk-bar {
        position: sticky;
        bottom: 0;
        background: #2d0538;
        color: #fff;
        padding: 10px 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        border-radius: 0 0 14px 14px;
    }

    .mc-bulk-count { font-size: 13px; font-weight: 600; }

    .mc-bulk-btn {
        padding: 6px 14px;
        font-size: 12.5px;
        font-weight: 600;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        background: rgba(255,255,255,0.14);
        color: #fff;
    }
    .mc-bulk-btn:hover { background: rgba(255,255,255,0.24); }
    .mc-bulk-btn--danger { background: rgba(239,68,68,0.28); }
    .mc-bulk-btn--danger:hover { background: rgba(239,68,68,0.42); }

    .mc-empty { padding: 48px 24px; text-align: center; color: #b3a0b8; font-size: 13px; }
    .mc-empty-icon { font-size: 30px; color: #dfc5e1; margin-bottom: 8px; }

    .mc-flash {
        margin-bottom: 12px;
        padding: 9px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        background: #e8f7ef;
        border: 1px solid #b8e0ca;
        color: #1a7a45;
    }

    #df-content-inner { padding: 10px 24px 24px !important; }
</style>
@endsection

@section('relationship-content')

<div x-data="missedCalls()">

    <a href="{{ route('relationship.today') }}" class="mc-back-link">
        <i class="ti ti-arrow-left"></i> Back to Today's Actions
    </a>

    <div class="mc-page-header">
        <div>
            <h1 class="mc-page-title">Missed Calls</h1>
            <p class="mc-page-sub">Every pending call due yesterday or earlier — the full backlog behind the dashboard count.</p>
        </div>
        <span class="mc-total-badge">
            <i class="ti ti-phone-x"></i> {{ $items->total() }} {{ Str::plural('call', $items->total()) }}
        </span>
    </div>

    @if(session('success'))
        <div class="mc-flash">{{ session('success') }}</div>
    @endif

    {{-- ── Filters ─────────────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('relationship.today.missed-calls') }}" class="mc-filter-bar">
        <div class="mc-filter-group">
            <label class="mc-filter-label">Search</label>
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                   placeholder="Name or phone" class="mc-filter-input">
        </div>

        <div class="mc-filter-group">
            <label class="mc-filter-label">Purpose</label>
            <select name="purpose" class="mc-filter-select">
                <option value="">All</option>
                @foreach($purposeOptions as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['purpose'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="mc-filter-group">
            <label class="mc-filter-label">Priority</label>
            <select name="priority" class="mc-filter-select">
                <option value="">All</option>
                <option value="high" @selected(($filters['priority'] ?? '') === 'high')>High</option>
                <option value="medium" @selected(($filters['priority'] ?? '') === 'medium')>Medium</option>
                <option value="low" @selected(($filters['priority'] ?? '') === 'low')>Low</option>
            </select>
        </div>

        <label class="mc-filter-check">
            <input type="checkbox" name="show_ignored" value="1" @checked($showIgnored)
                   onchange="this.form.submit()">
            Show ignored
        </label>

        <button type="submit" class="mc-btn mc-btn--primary"><i class="ti ti-filter"></i> Apply</button>
        @if(!empty(array_filter($filters)) || $showIgnored)
            <a href="{{ route('relationship.today.missed-calls') }}" class="mc-btn">Clear</a>
        @endif
    </form>

    {{-- ── Table ───────────────────────────────────────────────────────── --}}
    <div class="mc-table-wrap">
        @if($items->isEmpty())
            <div class="mc-empty">
                <div class="mc-empty-icon"><i class="ti ti-circle-check"></i></div>
                Nothing here — no missed calls match this filter.
            </div>
        @else
            <table class="mc-table">
                <thead>
                    <tr>
                        <th style="width:34px;">
                            <input type="checkbox" @change="toggleAll($event)">
                        </th>
                        <th>Patient</th>
                        <th>Reason</th>
                        <th>Priority</th>
                        <th>Due</th>
                        <th style="width:160px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                    @php
                        $patient = $item->patient;
                        $phone   = $patient?->phone ?? $item->phone;
                        $due     = $item->follow_up_date ?? $item->due_at;
                    @endphp
                    <tr class="{{ $item->ignored_at ? 'mc-row--ignored' : '' }}">
                        <td>
                            <input type="checkbox" class="mc-row-check" value="{{ $item->id }}"
                                   @change="toggleRow({{ $item->id }})">
                        </td>
                        <td>
                            <div class="mc-name">{{ $patient?->name ?? $item->person_name ?? 'Unknown' }}</div>
                            @if($phone)
                                <a href="tel:{{ $phone }}" class="mc-phone-link">{{ $phone }}</a>
                            @endif
                        </td>
                        <td>
                            <div class="mc-reason">
                                {{ $item->note ?: ($item->purpose_label ?? $item->purpose ?? 'Follow-up') }}
                            </div>
                        </td>
                        <td>
                            <span class="mc-priority mc-priority--{{ $item->priority ?? 'medium' }}">
                                {{ ucfirst($item->priority ?? 'medium') }}
                            </span>
                        </td>
                        <td style="white-space:nowrap;color:#6a5a76;font-size:12px;">
                            {{ $due ? \Illuminate\Support\Carbon::parse($due)->format('d M Y') : '—' }}
                        </td>
                        <td>
                            <div class="mc-actions">
                                @if($patient)
                                <a href="{{ route('patients.show', $patient->id) }}" class="mc-action-btn" title="Open record">
                                    <i class="ti ti-external-link"></i>
                                </a>
                                @endif

                                @if(!$item->ignored_at)
                                <form method="POST" action="{{ route('relationship.today.missed-calls.ignore', $item->id) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="mc-action-btn mc-action-btn--muted" title="Ignore — exclude this item from the queue"
                                            onclick="return confirm('Ignore this missed call? It will be hidden from this list and the dashboard until restored.')">
                                        <i class="ti ti-eye-off"></i>
                                    </button>
                                </form>
                                @else
                                <form method="POST" action="{{ route('relationship.today.missed-calls.unignore', $item->id) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="mc-action-btn" title="Restore to queue">
                                        <i class="ti ti-eye"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- ── Bulk bar — appears once >=1 row selected ─────────────── --}}
            <div class="mc-bulk-bar" x-show="selected.length > 0 || selectAllMatching" x-transition style="display:none;">
                <span class="mc-bulk-count" x-show="!selectAllMatching" x-text="selected.length + ' selected'"></span>
                <span class="mc-bulk-count" x-show="selectAllMatching">All {{ $items->total() }} matching this filter selected</span>

                {{-- Only offer "select all" once every row on the current page is checked
                     and there's more backlog than what's loaded — no point offering it
                     for a single 40-row page. --}}
                <template x-if="!selectAllMatching && selected.length === {{ $items->count() }} && {{ $items->total() }} > {{ $items->count() }}">
                    <button type="button" class="mc-bulk-btn" @click="selectAllMatching = true">
                        Select all {{ $items->total() }} matching this filter
                    </button>
                </template>
                <template x-if="selectAllMatching">
                    <button type="button" class="mc-bulk-btn" @click="selectAllMatching = false">
                        Just these {{ $items->count() }}
                    </button>
                </template>

                <form method="POST" action="{{ route('relationship.today.missed-calls.bulk-dismiss') }}" style="display:inline;"
                      onsubmit="return confirm('Dismiss the selected item(s)? They will be marked closed.')">
                    @csrf
                    <template x-if="selectAllMatching">
                        <div style="display:inline;">
                            <input type="hidden" name="select_all" value="1">
                            <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
                            <input type="hidden" name="purpose" value="{{ $filters['purpose'] ?? '' }}">
                            <input type="hidden" name="priority" value="{{ $filters['priority'] ?? '' }}">
                            <input type="hidden" name="show_ignored" value="{{ $showIgnored ? '1' : '' }}">
                        </div>
                    </template>
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="comm_ids[]" :value="id">
                    </template>
                    <button type="submit" class="mc-bulk-btn mc-bulk-btn--danger">
                        <i class="ti ti-check"></i>
                        <span x-text="selectAllMatching ? 'Dismiss all {{ $items->total() }}' : 'Bulk Dismiss'"></span>
                    </button>
                </form>

                <button type="button" @click="clearSelection()"
                        style="margin-left:auto;background:none;border:none;color:#c9a8d4;cursor:pointer;font-size:12px;">
                    ✕ Clear
                </button>
            </div>
        @endif
    </div>

    {{-- ── Pagination ──────────────────────────────────────────────────── --}}
    @if($items->hasPages())
        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:14px;font-size:12px;color:#9a7aaa;">
            <span>Showing {{ $items->firstItem() }}–{{ $items->lastItem() }} of {{ $items->total() }}</span>
            <div style="display:flex;gap:6px;">
                @if($items->onFirstPage())
                    <span class="mc-btn" style="opacity:0.4;cursor:not-allowed;">&larr; Prev</span>
                @else
                    <a href="{{ $items->previousPageUrl() }}" class="mc-btn">&larr; Prev</a>
                @endif
                @if($items->hasMorePages())
                    <a href="{{ $items->nextPageUrl() }}" class="mc-btn">Next &rarr;</a>
                @else
                    <span class="mc-btn" style="opacity:0.4;cursor:not-allowed;">Next &rarr;</span>
                @endif
            </div>
        </div>
    @endif

</div>

<script>
function missedCalls() {
    return {
        selected: [],
        selectAllMatching: false,

        toggleAll(e) {
            const boxes = document.querySelectorAll('.mc-row-check');
            this.selected = e.target.checked ? Array.from(boxes).map(b => parseInt(b.value)) : [];
            boxes.forEach(b => b.checked = e.target.checked);
            if (!e.target.checked) this.selectAllMatching = false;
        },
        toggleRow(id) {
            this.selectAllMatching = false;
            const idx = this.selected.indexOf(id);
            idx === -1 ? this.selected.push(id) : this.selected.splice(idx, 1);
        },
        clearSelection() {
            this.selected = [];
            this.selectAllMatching = false;
            document.querySelectorAll('.mc-row-check, thead input[type=checkbox]').forEach(b => b.checked = false);
        },
    };
}
</script>

@endsection
