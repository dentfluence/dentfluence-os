{{--
|==========================================================================
| PRE — Recalls (Phase 1 · Workstream D, slice 3; rebuilt 2026-07-06)
| Route: GET /relationship/recalls   [relationship.recalls]
|
| Was a read-only 4-column kanban ("Recall Pipeline") — dropped that framing
| since recalls don't move through funnel stages, they're a work queue you
| clear. Rebuilt as a flat, filterable, actionable list — same proven
| pattern as Missed Calls (resources/views/relationship/today/missed-calls.blade.php):
| filters, checkboxes, bulk dismiss/assign with a "select all matching
| filter" path. Added Convert-to-Opportunity for recalls that reveal a real
| treatment need.
|
| Variables from RecallPipelineController@index:
|   $recalls (paginator), $total, $openCount, $overdueCount, $showIgnored,
|   $filters, $staff, $statuses
|==========================================================================
--}}
@extends('relationship.layouts.app')

@section('page-title', 'Recalls')

@section('head-extra')
@unless(app()->has('tabler_icons_loaded'))
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
@endunless
<style>
    /* ══════════════════════════════════════════════════════════════════
       RECALLS — visual redevelopment 2026-08-25
       Density target: KPI strip + filters + chips fit above the fold and
       the table stays dense. Presentation only — every value on this page
       comes from the existing RecallPipelineController payload.
    ══════════════════════════════════════════════════════════════════ */
    #df-content-inner { padding:10px 20px 14px !important; }

    .rl { max-width:1560px; margin:0 auto; }

    /* ── Header + KPI strip ── */
    .rl-head { display:flex; align-items:flex-start; gap:16px; flex-wrap:wrap; margin-bottom:10px; }
    .rl-head-txt { min-width:240px; flex:1 1 auto; }
    .rl-page-title { font-family:'Cormorant Garamond', Georgia, serif; font-size:25px; font-weight:600; color:#1a0320; margin:0; line-height:1.15; }
    .rl-page-sub { font-size:12px; color:#9a7aaa; margin:3px 0 0; }

    .rl-kpis { display:grid; grid-template-columns:repeat(4, 158px); gap:10px; flex:0 0 auto; }
    .rl-kpi { display:flex; align-items:center; gap:10px; background:#fff; border:1px solid #ece2f1; border-radius:10px; padding:9px 12px; box-shadow:0 1px 2px rgba(26,3,32,.04); }
    .rl-kpi-ico { width:32px; height:32px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:16px; flex:0 0 auto; }
    .rl-kpi-ico--total { background:#f3e8f4; color:#6a0f70; }
    .rl-kpi-ico--open  { background:#fff4e0; color:#a05c00; }
    .rl-kpi-ico--over  { background:#fdeaea; color:#b52020; }
    .rl-kpi-ico--done  { background:#e8f7ef; color:#1a7a45; }
    .rl-kpi-lbl { font-size:10.5px; font-weight:600; color:#9a8aa2; letter-spacing:.02em; white-space:nowrap; }
    .rl-kpi-num { font-size:19px; font-weight:700; color:#1a0320; line-height:1.15; font-variant-numeric:tabular-nums; }

    /* ── Filter panel ── */
    .rl-filters { background:#fff; border:1px solid #ece2f1; border-radius:10px; padding:10px 12px; margin-bottom:8px; }
    .rl-filter-row { display:flex; align-items:flex-end; gap:9px; flex-wrap:wrap; }
    .rl-fg { display:flex; flex-direction:column; gap:3px; min-width:0; }
    .rl-fg--grow { flex:1 1 190px; }
    .rl-filter-label { font-size:9.5px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#a892b0; }
    .rl-filter-input, .rl-filter-select {
        padding:5px 9px; border:1px solid #e2d4e8; border-radius:7px; font-size:12px; color:#3a1140;
        background:#fff; font-family:inherit; height:30px; box-sizing:border-box; width:100%;
    }
    .rl-filter-input:focus, .rl-filter-select:focus { outline:none; border-color:#6a0f70; box-shadow:0 0 0 2px #f3e8f4; }
    .rl-search-wrap { position:relative; }
    .rl-search-wrap i { position:absolute; left:8px; top:50%; transform:translateY(-50%); font-size:13px; color:#b3a0b8; pointer-events:none; }
    .rl-search-wrap .rl-filter-input { padding-left:26px; }

    .rl-filter-foot { display:flex; align-items:center; gap:10px; margin-top:8px; padding-top:8px; border-top:1px solid #f4eef7; flex-wrap:wrap; }
    .rl-filter-check { display:inline-flex; align-items:center; gap:6px; font-size:12px; color:#5a4a62; cursor:pointer; }
    .rl-spacer { flex:1 1 auto; }

    .rl-btn { display:inline-flex; align-items:center; gap:5px; padding:5px 11px; border:1px solid #e2d4e8; border-radius:7px; background:#fff; color:#5a2a62; font-size:11.5px; font-weight:600; text-decoration:none; cursor:pointer; white-space:nowrap; font-family:inherit; line-height:1.4; }
    .rl-btn:hover { background:#faf5fc; }
    .rl-btn--primary { background:#6a0f70; border-color:#6a0f70; color:#fff; }
    .rl-btn--primary:hover { background:#4e0a53; }

    /* ── Recall-type chips ── */
    .rl-chips { display:flex; gap:6px; overflow-x:auto; padding-bottom:5px; margin-bottom:8px; }
    .rl-chips::-webkit-scrollbar { height:5px; }
    .rl-chips::-webkit-scrollbar-thumb { background:#e6dced; border-radius:3px; }
    .rl-chip { display:inline-flex; align-items:center; gap:7px; padding:6px 11px; border:1px solid #ece2f1; border-radius:9px; background:#fff; text-decoration:none; white-space:nowrap; min-width:0; }
    .rl-chip:hover { border-color:#cfb4d6; }
    .rl-chip--on { border-color:#6a0f70; box-shadow:0 0 0 1px #6a0f70 inset; }
    .rl-chip-ico { width:24px; height:24px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:13px; flex:0 0 auto; }
    .rl-chip-txt { display:flex; flex-direction:column; line-height:1.25; }
    .rl-chip-name { font-size:11.5px; font-weight:600; color:#3a1140; }
    .rl-chip-n { font-size:11px; font-weight:700; color:#8a6f92; font-variant-numeric:tabular-nums; }

    /* ── Table ── */
    .rl-table-wrap { background:#fff; border:1px solid #ece2f1; border-radius:10px; overflow:hidden; }
    .rl-scroll { overflow:auto; }
    table.rl-table { width:100%; border-collapse:separate; border-spacing:0; font-size:12px; }
    .rl-table thead th { position:sticky; top:0; z-index:2; background:#faf7fc; border-bottom:1px solid #ece2f1; padding:7px 10px; text-align:left; font-size:9.5px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#8a6f92; white-space:nowrap; }
    .rl-table tbody td { border-bottom:1px solid #f4eef7; padding:7px 10px; vertical-align:middle; color:#3a1140; }
    .rl-table tbody tr:last-child td { border-bottom:none; }
    .rl-table tbody tr:hover td { background:#fdfaff; }
    .rl-row--ignored { opacity:.5; }

    .rl-name { font-weight:600; color:#1a0320; white-space:nowrap; max-width:190px; overflow:hidden; text-overflow:ellipsis; }
    .rl-phone-link { font-size:11px; color:#8a7a92; text-decoration:none; }
    .rl-phone-link:hover { color:#6a0f70; text-decoration:underline; }
    .rl-type { display:inline-block; padding:2px 8px; border-radius:6px; font-size:10.5px; font-weight:600; white-space:nowrap; max-width:150px; overflow:hidden; text-overflow:ellipsis; }
    .rl-reason { color:#2c1033; max-width:330px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .rl-reason-sub { display:block; font-size:11px; color:#8a7a92; margin-top:1px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:330px; }
    .rl-due { white-space:nowrap; font-variant-numeric:tabular-nums; color:#3a1140; }
    .rl-due-sub { display:block; font-size:10.5px; margin-top:1px; font-weight:700; }
    .rl-due-sub--over { color:#b52020; }
    .rl-due-sub--soon { color:#a05c00; }
    .rl-due-sub--calm { color:#9a8aa2; font-weight:600; }
    .rl-assigned { white-space:nowrap; color:#3a1140; }
    .rl-muted { color:#b8a8c0; }

    .rl-pill { display:inline-flex; align-items:center; padding:2px 8px; border-radius:99px; font-size:10.5px; font-weight:700; white-space:nowrap; letter-spacing:.03em; }
    .rl-pill--caps { text-transform:uppercase; }
    .rl-priority--high   { background:#fdeaea; color:#b52020; }
    .rl-priority--medium { background:#fff4e0; color:#a05c00; }
    .rl-priority--low    { background:#e8f7ef; color:#1a7a45; }
    .rl-status--pending  { background:#eef2ff; color:#4338ca; }
    .rl-status--waiting_for_patient { background:#fff4e0; color:#a05c00; }
    .rl-status--overdue  { background:#fdeaea; color:#b52020; }
    .rl-status--closed   { background:#eef1ee; color:#5c6b60; }

    .rl-actions { display:flex; align-items:center; gap:4px; justify-content:flex-end; }
    .rl-action-btn { display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:7px; border:1px solid #e2d4e8; background:#fff; color:#6a0f70; font-size:13px; cursor:pointer; text-decoration:none; flex:0 0 auto; padding:0; font-family:inherit; }
    .rl-action-btn:hover { background:#f3e8f4; }
    .rl-action-btn--muted { color:#9a8aa2; }
    .rl-action-btn--muted:hover { background:#f4f1f6; color:#6a5a76; }

    /* ── Footer / pagination ── */
    .rl-foot { display:flex; align-items:center; gap:10px; padding:7px 10px; border-top:1px solid #f0e8f5; background:#fdfbfe; font-size:11.5px; color:#8a7a92; flex-wrap:wrap; }
    .rl-foot select { padding:3px 6px; border:1px solid #e2d4e8; border-radius:6px; font-size:11.5px; color:#3a1140; background:#fff; font-family:inherit; }
    .rl-pg { display:flex; align-items:center; gap:4px; margin-left:auto; }
    .rl-pgb { min-width:26px; height:26px; padding:0 8px; border:1px solid #e2d4e8; border-radius:7px; background:#fff; color:#5a2a62; font-size:11.5px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; font-variant-numeric:tabular-nums; }
    .rl-pgb:hover { background:#f3e8f4; }
    .rl-pgb--on { background:#6a0f70; border-color:#6a0f70; color:#fff; }
    .rl-pgb--off { opacity:.4; cursor:not-allowed; }
    .rl-pg-gap { color:#b3a0b8; padding:0 2px; }

    .rl-empty { padding:34px 16px; text-align:center; color:#9a8aa2; font-size:12.5px; }
    .rl-empty-icon { font-size:30px; color:#dfc5e1; margin-bottom:6px; }

    /* ── Bulk bar ── */
    .rl-bulk-bar { display:flex; align-items:center; gap:9px; padding:8px 12px; background:#2c1033; color:#fff; flex-wrap:wrap; }
    .rl-bulk-count { font-size:12px; font-weight:600; }
    .rl-bulk-btn { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border:1px solid rgba(255,255,255,.28); border-radius:7px; background:rgba(255,255,255,.08); color:#fff; font-size:11.5px; font-weight:600; cursor:pointer; font-family:inherit; }
    .rl-bulk-btn:hover { background:rgba(255,255,255,.18); }
    .rl-bulk-btn--danger { border-color:#e59b9b; color:#ffd9d9; }
    .rl-bulk-select { padding:4px 8px; border:1px solid rgba(255,255,255,.28); border-radius:7px; background:rgba(255,255,255,.08); color:#fff; font-size:11.5px; font-family:inherit; }
    .rl-bulk-select option { color:#3a1140; }

    .rl-flash { background:#e8f7ef; border:1px solid #b8e0ca; color:#1a7a45; border-radius:9px; padding:8px 12px; font-size:12.5px; margin-bottom:8px; }
    .rl-flash--error { background:#fdecec; border-color:#f5b5b5; color:#8a1f1f; }

    @media (max-width: 1100px) {
        .rl-head { flex-direction:column; }
        .rl-kpis { grid-template-columns:repeat(2, 1fr); width:100%; }
    }
</style>
@endsection

@section('relationship-content')

<div x-data="recallsList()">

    @php
        // ── Presentation map for the REAL recall purposes RecallEngineService
        // stamps (see its 6 triggers + createManual). Label/icon/colour only —
        // the keys are the stored `purpose` values, nothing invented.
        $typeMeta = [
            'recall_no_visit'      => ['Dormant / No-Visit', 'ti-user-off',        '#f3e8f4', '#6a0f70', 'No visit in 6+ months'],
            'recall_approved_plan' => ['Approved Plan',      'ti-clipboard-check', '#eef2ff', '#4338ca', 'Approved plan — no appointment booked'],
            'recall_post_op'       => ['Post-Op Follow-up',  'ti-heart-plus',      '#fdeaea', '#b52020', 'Post-op follow-up due'],
            'recall_lab_received'  => ['Lab Work Ready',     'ti-flask',           '#e8f7ef', '#1a7a45', 'Lab work ready — no appointment booked'],
            'recall_7day_followup' => ['7-Day Follow-up',    'ti-calendar-repeat', '#e0f2fe', '#0369a1', '7-day post-treatment follow-up'],
            'recall_birthday'      => ['Birthday Recall',    'ti-cake',            '#fff4e0', '#a05c00', 'Birthday re-engagement'],
            'recall_manual'        => ['Manual Recall',      'ti-pencil',          '#f4eef7', '#684a72', 'Manually added recall'],
            'recall_long_term'     => ['Long-term Recall',   'ti-hourglass',       '#ecfdf5', '#0f766e', 'Long-horizon preventive recall'],
            'recall_due'           => ['Recall Due',         'ti-bell',            '#f3e8f4', '#6a0f70', 'Recall due'],
            'recall'               => ['Recall',             'ti-bell',            '#f3e8f4', '#6a0f70', 'Recall due'],
        ];
        $typeOf = function (?string $purpose) use ($typeMeta) {
            $key = $purpose ?: 'recall';
            return $typeMeta[$key] ?? [
                ucwords(str_replace('_', ' ', $key)), 'ti-bell', '#f4eef7', '#684a72', 'Recall due',
            ];
        };

        $activeType = $filters['type'] ?? '';
        $carry      = collect(request()->except(['type', 'page']))->filter(fn ($v) => $v !== null && $v !== '')->all();
        $chipUrl    = fn (?string $key) => route('relationship.recalls', $key ? $carry + ['type' => $key] : $carry);

        // Chips are built from the live counts, so only types that actually
        // exist in the queue appear — busiest first.
        arsort($typeCounts);
    @endphp

    <div class="rl">

        {{-- ── Header + KPI strip ─────────────────────────────────────── --}}
        <div class="rl-head">
            <div class="rl-head-txt">
                <h1 class="rl-page-title">Recalls / Recall Engine</h1>
                <p class="rl-page-sub">Patients due to return — work the queue: dismiss, assign, or convert to an opportunity.</p>
            </div>

            <div class="rl-kpis">
                <div class="rl-kpi">
                    <div class="rl-kpi-ico rl-kpi-ico--total"><i class="ti ti-bell"></i></div>
                    <div>
                        <div class="rl-kpi-lbl">Total Recalls</div>
                        <div class="rl-kpi-num">{{ number_format($total) }}</div>
                    </div>
                </div>
                <div class="rl-kpi">
                    <div class="rl-kpi-ico rl-kpi-ico--open"><i class="ti ti-clock"></i></div>
                    <div>
                        <div class="rl-kpi-lbl">Open</div>
                        <div class="rl-kpi-num">{{ number_format($openCount) }}</div>
                    </div>
                </div>
                <div class="rl-kpi">
                    <div class="rl-kpi-ico rl-kpi-ico--over"><i class="ti ti-alert-circle"></i></div>
                    <div>
                        <div class="rl-kpi-lbl">Overdue</div>
                        <div class="rl-kpi-num" @if($overdueCount > 0) style="color:#b52020;" @endif>{{ number_format($overdueCount) }}</div>
                    </div>
                </div>
                <div class="rl-kpi">
                    <div class="rl-kpi-ico rl-kpi-ico--done"><i class="ti ti-circle-check"></i></div>
                    <div>
                        <div class="rl-kpi-lbl">Closed (This Month)</div>
                        <div class="rl-kpi-num">{{ number_format($closedThisMonth) }}</div>
                    </div>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="rl-flash">{{ session('success') }}</div>
        @endif
        @if ($errors->any() && !$errors->has('patient_id'))
            <div class="rl-flash rl-flash--error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        {{-- ── Filter panel — existing filters only ───────────────────── --}}
        <form method="GET" action="{{ route('relationship.recalls') }}" class="rl-filters">
            <div class="rl-filter-row">
                <div class="rl-fg rl-fg--grow">
                    <label class="rl-filter-label">Search</label>
                    <div class="rl-search-wrap">
                        <i class="ti ti-search"></i>
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                               placeholder="Name or phone…" class="rl-filter-input">
                    </div>
                </div>

                <div class="rl-fg" style="flex:0 0 175px;">
                    <label class="rl-filter-label">Recall Type</label>
                    <select name="type" class="rl-filter-select">
                        <option value="">All Types</option>
                        @foreach($typeCounts as $key => $count)
                            @php $m = $typeOf($key); @endphp
                            <option value="{{ $key }}" @selected($activeType === $key)>{{ $m[0] }} ({{ number_format($count) }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="rl-fg" style="flex:0 0 130px;">
                    <label class="rl-filter-label">Priority</label>
                    <select name="priority" class="rl-filter-select">
                        <option value="">All</option>
                        <option value="high"   @selected(($filters['priority'] ?? '') === 'high')>High</option>
                        <option value="medium" @selected(($filters['priority'] ?? '') === 'medium')>Medium</option>
                        <option value="low"    @selected(($filters['priority'] ?? '') === 'low')>Low</option>
                    </select>
                </div>

                <div class="rl-fg" style="flex:0 0 150px;">
                    <label class="rl-filter-label">Status</label>
                    <select name="status" class="rl-filter-select">
                        <option value="">All</option>
                        @foreach($statuses as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="rl-fg" style="flex:0 0 170px;">
                    <label class="rl-filter-label">Assigned To</label>
                    <select name="assigned_to" class="rl-filter-select">
                        <option value="">Anyone</option>
                        @foreach($staff as $member)
                            <option value="{{ $member->name }}" @selected(($filters['assigned_to'] ?? '') === $member->name)>{{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="rl-filter-foot">
                <label class="rl-filter-check">
                    <input type="checkbox" name="show_ignored" value="1" @checked($showIgnored) onchange="this.form.submit()">
                    Show ignored
                </label>

                <div class="rl-spacer"></div>

                <button type="button" onclick="rlOpenAddRecall()" class="rl-btn">
                    <i class="ti ti-plus"></i> Add Recall
                </button>
                @if(!empty(array_filter($filters)) || $showIgnored)
                    <a href="{{ route('relationship.recalls') }}" class="rl-btn"><i class="ti ti-refresh"></i> Clear Filters</a>
                @endif
                <button type="submit" class="rl-btn rl-btn--primary"><i class="ti ti-filter"></i> Apply Filters</button>
            </div>
        </form>

        {{-- ── Recall-type chips — live counts, existing filter path ──── --}}
        @if(!empty($typeCounts))
        <div class="rl-chips">
            <a href="{{ $chipUrl(null) }}" class="rl-chip {{ $activeType === '' ? 'rl-chip--on' : '' }}">
                <span class="rl-chip-ico" style="background:#f3e8f4;color:#6a0f70;"><i class="ti ti-layout-grid"></i></span>
                <span class="rl-chip-txt">
                    <span class="rl-chip-name">All Types</span>
                    <span class="rl-chip-n">{{ number_format(array_sum($typeCounts)) }}</span>
                </span>
            </a>
            @foreach($typeCounts as $key => $count)
                @php $m = $typeOf($key); @endphp
                <a href="{{ $chipUrl($key) }}" class="rl-chip {{ $activeType === $key ? 'rl-chip--on' : '' }}">
                    <span class="rl-chip-ico" style="background:{{ $m[2] }};color:{{ $m[3] }};"><i class="ti {{ $m[1] }}"></i></span>
                    <span class="rl-chip-txt">
                        <span class="rl-chip-name">{{ $m[0] }}</span>
                        <span class="rl-chip-n">{{ number_format($count) }}</span>
                    </span>
                </a>
            @endforeach
        </div>
        @endif

        {{-- ── Table ──────────────────────────────────────────────────── --}}
        <div class="rl-table-wrap">
            @if($recalls->isEmpty())
                <div class="rl-empty">
                    <div class="rl-empty-icon"><i class="ti ti-circle-check"></i></div>
                    Nothing here — no recalls match this filter.
                </div>
            @else
                <div class="rl-scroll">
                <table class="rl-table">
                    <thead>
                        <tr>
                            <th style="width:32px;"><input type="checkbox" @change="toggleAll($event)"></th>
                            <th style="width:190px;">Patient</th>
                            <th style="width:160px;">Recall Type</th>
                            <th>Reason</th>
                            <th style="width:92px;">Priority</th>
                            <th style="width:118px;">Due Date</th>
                            <th style="width:104px;">Status</th>
                            <th style="width:150px;">Assigned To</th>
                            <th style="width:110px;">Last Contact</th>
                            <th style="width:124px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recalls as $recall)
                        @php
                            $patient   = $recall->patient;
                            $phone     = $patient?->phone ?? $recall->phone;
                            $due       = $recall->follow_up_date ?? $recall->due_at;
                            $isOverdue = ($recall->is_overdue || $recall->status === 'overdue') && $recall->status !== 'closed';
                            $meta      = $typeOf($recall->purpose);
                            $dueC      = $due ? \Illuminate\Support\Carbon::parse($due) : null;
                            $lastC     = $recall->last_attempt_at;
                        @endphp
                        <tr class="{{ $recall->ignored_at ? 'rl-row--ignored' : '' }}">
                            <td><input type="checkbox" class="rl-row-check" value="{{ $recall->id }}" @change="toggleRow({{ $recall->id }})"></td>

                            <td>
                                <div class="rl-name" title="{{ $patient?->name ?? $recall->person_name }}">{{ $patient?->name ?? $recall->person_name ?: 'Unnamed' }}</div>
                                @if($phone)<a href="tel:{{ $phone }}" class="rl-phone-link">{{ $phone }}</a>@endif
                            </td>

                            <td>
                                <span class="rl-type" style="background:{{ $meta[2] }};color:{{ $meta[3] }};" title="{{ $meta[0] }}">{{ $meta[0] }}</span>
                            </td>

                            <td>
                                <div class="rl-reason" title="{{ $recall->note ?: $meta[4] }}">{{ $recall->note ?: $meta[4] }}</div>
                                @if($recall->note)
                                    <span class="rl-reason-sub">{{ $meta[4] }}</span>
                                @elseif($recall->created_at)
                                    <span class="rl-reason-sub">Queued {{ $recall->created_at->diffForHumans() }}</span>
                                @endif
                            </td>

                            <td><span class="rl-pill rl-priority--{{ $recall->priority ?? 'medium' }}">{{ ucfirst($recall->priority ?? 'medium') }}</span></td>

                            <td>
                                @if($dueC)
                                    <span class="rl-due">{{ $dueC->format('d M Y') }}</span>
                                    @if($isOverdue)
                                        <span class="rl-due-sub rl-due-sub--over">Overdue</span>
                                    @elseif($dueC->isToday())
                                        <span class="rl-due-sub rl-due-sub--soon">Today</span>
                                    @elseif($dueC->isTomorrow())
                                        <span class="rl-due-sub rl-due-sub--soon">Tomorrow</span>
                                    @else
                                        <span class="rl-due-sub rl-due-sub--calm">{{ $dueC->diffForHumans() }}</span>
                                    @endif
                                @else
                                    <span class="rl-muted">—</span>
                                @endif
                            </td>

                            <td><span class="rl-pill rl-pill--caps rl-status--{{ $recall->status }}">{{ $statuses[$recall->status] ?? $recall->status }}</span></td>

                            <td>
                                @if($recall->assigned_to)
                                    <span class="rl-assigned">{{ $recall->assigned_to }}</span>
                                @else
                                    <span class="rl-muted">Unassigned</span>
                                @endif
                            </td>

                            <td>
                                @if($lastC)
                                    <span class="rl-due">{{ $lastC->format('d M Y') }}</span>
                                @else
                                    <span class="rl-muted">—</span>
                                @endif
                            </td>

                            <td>
                                <div class="rl-actions">
                                    @if($patient)
                                    <a href="{{ route('patients.show', $patient->id) }}" class="rl-action-btn" title="Open record">
                                        <i class="ti ti-external-link"></i>
                                    </a>
                                    @endif

                                    @if($phone)
                                    <x-communication.whatsapp-button
                                        context="recall"
                                        :patient-id="$patient?->id"
                                        :number="$phone"
                                        class="rl-action-btn"
                                        title="Send recall reminder on WhatsApp">
                                        <i class="ti ti-brand-whatsapp"></i>
                                    </x-communication.whatsapp-button>
                                    @endif

                                    @if($patient && $recall->status !== 'closed')
                                    <button type="button" class="rl-action-btn" title="Convert to Opportunity"
                                            onclick="rlOpenConvert({{ $recall->id }}, '{{ addslashes($patient->name) }}')">
                                        <i class="ti ti-star"></i>
                                    </button>
                                    @endif

                                    @if(!$recall->ignored_at)
                                    <form method="POST" action="{{ route('relationship.recalls.ignore', $recall->id) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="rl-action-btn rl-action-btn--muted" title="Ignore — exclude this item from the queue"
                                                onclick="return confirm('Ignore this recall? It will be hidden from this list until restored.')">
                                            <i class="ti ti-eye-off"></i>
                                        </button>
                                    </form>
                                    @else
                                    <form method="POST" action="{{ route('relationship.recalls.unignore', $recall->id) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="rl-action-btn" title="Restore to queue"><i class="ti ti-eye"></i></button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>

                {{-- ── Bulk bar — unchanged behaviour, restyled ───────── --}}
                <div class="rl-bulk-bar" x-show="selected.length > 0 || selectAllMatching" x-transition style="display:none;">
                    <span class="rl-bulk-count" x-show="!selectAllMatching" x-text="selected.length + ' selected'"></span>
                    <span class="rl-bulk-count" x-show="selectAllMatching">All {{ $recalls->total() }} matching this filter selected</span>

                    <template x-if="!selectAllMatching && selected.length === {{ $recalls->count() }} && {{ $recalls->total() }} > {{ $recalls->count() }}">
                        <button type="button" class="rl-bulk-btn" @click="selectAllMatching = true">
                            Select all {{ $recalls->total() }} matching this filter
                        </button>
                    </template>
                    <template x-if="selectAllMatching">
                        <button type="button" class="rl-bulk-btn" @click="selectAllMatching = false">Just these {{ $recalls->count() }}</button>
                    </template>

                    {{-- Bulk assign --}}
                    <form method="POST" action="{{ route('relationship.recalls.bulk-assign') }}" style="display:flex;align-items:center;gap:6px;"
                          onsubmit="return confirm('Assign the selected recall(s) to this staff member?')">
                        @csrf
                        <template x-if="selectAllMatching">
                            <div style="display:inline;">
                                <input type="hidden" name="select_all" value="1">
                                <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
                                <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
                                <input type="hidden" name="priority" value="{{ $filters['priority'] ?? '' }}">
                                <input type="hidden" name="assigned_to" value="{{ $filters['assigned_to'] ?? '' }}">
                                <input type="hidden" name="type" value="{{ $filters['type'] ?? '' }}">
                                <input type="hidden" name="show_ignored" value="{{ $showIgnored ? '1' : '' }}">
                            </div>
                        </template>
                        <template x-for="id in selected" :key="'a'+id">
                            <input type="hidden" name="recall_ids[]" :value="id" x-show="!selectAllMatching">
                        </template>
                        <select name="assigned_to" class="rl-bulk-select" required>
                            <option value="">Assign to…</option>
                            @foreach($staff as $member)
                                <option value="{{ $member->id }}">{{ $member->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="rl-bulk-btn"><i class="ti ti-user-check"></i> Assign</button>
                    </form>

                    {{-- Bulk dismiss --}}
                    <form method="POST" action="{{ route('relationship.recalls.bulk-dismiss') }}" style="display:inline;"
                          onsubmit="return confirm('Dismiss the selected recall(s)? They will be marked closed.')">
                        @csrf
                        <template x-if="selectAllMatching">
                            <div style="display:inline;">
                                <input type="hidden" name="select_all" value="1">
                                <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
                                <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
                                <input type="hidden" name="priority" value="{{ $filters['priority'] ?? '' }}">
                                <input type="hidden" name="assigned_to" value="{{ $filters['assigned_to'] ?? '' }}">
                                <input type="hidden" name="type" value="{{ $filters['type'] ?? '' }}">
                                <input type="hidden" name="show_ignored" value="{{ $showIgnored ? '1' : '' }}">
                            </div>
                        </template>
                        <template x-for="id in selected" :key="'d'+id">
                            <input type="hidden" name="recall_ids[]" :value="id" x-show="!selectAllMatching">
                        </template>
                        <button type="submit" class="rl-bulk-btn rl-bulk-btn--danger">
                            <i class="ti ti-check"></i>
                            <span x-text="selectAllMatching ? 'Dismiss all {{ $recalls->total() }}' : 'Bulk Dismiss'"></span>
                        </button>
                    </form>

                    <button type="button" @click="clearSelection()" style="margin-left:auto;background:none;border:none;color:#c9a8d4;cursor:pointer;font-size:12px;">
                        ✕ Clear
                    </button>
                </div>

                {{-- ── Footer / pagination — existing paginator, restyled ── --}}
                @php
                    $cur  = $recalls->currentPage();
                    $last = $recalls->lastPage();
                    $from = max(1, $cur - 2);
                    $to   = min($last, $cur + 2);
                @endphp
                <div class="rl-foot">
                    <span>Showing <strong>{{ number_format($recalls->firstItem() ?? 0) }}</strong> to
                          <strong>{{ number_format($recalls->lastItem() ?? 0) }}</strong> of
                          <strong>{{ number_format($recalls->total()) }}</strong> recalls</span>
                    <span class="rl-muted">· {{ $recalls->perPage() }} per page</span>

                    @if($recalls->hasPages())
                    <div class="rl-pg">
                        @if($recalls->onFirstPage())
                            <span class="rl-pgb rl-pgb--off">← Previous</span>
                        @else
                            <a href="{{ $recalls->previousPageUrl() }}" class="rl-pgb">← Previous</a>
                        @endif

                        @if($from > 1)
                            <a href="{{ $recalls->url(1) }}" class="rl-pgb">1</a>
                            @if($from > 2)<span class="rl-pg-gap">…</span>@endif
                        @endif

                        @for($i = $from; $i <= $to; $i++)
                            @if($i === $cur)
                                <span class="rl-pgb rl-pgb--on">{{ $i }}</span>
                            @else
                                <a href="{{ $recalls->url($i) }}" class="rl-pgb">{{ $i }}</a>
                            @endif
                        @endfor

                        @if($to < $last)
                            @if($to < $last - 1)<span class="rl-pg-gap">…</span>@endif
                            <a href="{{ $recalls->url($last) }}" class="rl-pgb">{{ number_format($last) }}</a>
                        @endif

                        @if($recalls->hasMorePages())
                            <a href="{{ $recalls->nextPageUrl() }}" class="rl-pgb">Next →</a>
                        @else
                            <span class="rl-pgb rl-pgb--off">Next →</span>
                        @endif
                    </div>
                    @endif
                </div>
            @endif
        </div>

    </div>{{-- /.rl --}}

    {{-- ══════════════════════════════════════════════════════════════════
         Add Recall modal — unchanged from the original Recall Pipeline:
         patient search + priority + follow-up date + note. Posts to
         relationship.recalls.store → RecallEngineService::createManual().
    ══════════════════════════════════════════════════════════════════ --}}
    @php $addRecallFailed = $errors->has('patient_id'); @endphp
    <div id="rlAddRecallModal" style="display:{{ $addRecallFailed ? 'flex' : 'none' }};position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:210;align-items:center;justify-content:center;padding:20px;">
        <div style="background:#fff;border-radius:12px;padding:24px;width:440px;max-width:100%;max-height:90vh;overflow-y:auto;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                <h3 style="margin:0;font-size:18px;font-weight:700;color:#1f2937;font-family:'Cormorant Garamond',serif;">Add Recall</h3>
                <button type="button" onclick="rlCloseAddRecall()" style="border:none;background:none;font-size:18px;color:#9ca3af;cursor:pointer;line-height:1;">&times;</button>
            </div>
            <p style="color:#6b7280;font-size:13px;margin:0 0 18px;">Manually queue a patient for a recall call.</p>

            @if ($addRecallFailed)
                <div style="background:#FDECEC;border:1px solid #f5b5b5;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;color:#8A1F1F;">
                    @foreach ($errors->get('patient_id') as $error)
                        <div>• {{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('relationship.recalls.store') }}">
                @csrf
                <div style="margin-bottom:16px;position:relative;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Patient <span style="color:#c0392b;">*</span></label>
                    <input type="text" id="rlPatientSearch" placeholder="Search by name or phone…" autocomplete="off"
                           oninput="rlSearchPatients(this.value)"
                           style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;">
                    <input type="hidden" name="patient_id" id="rlPatientId" value="{{ old('patient_id') }}">
                    <div id="rlPatientResults" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #e5e7eb;border-radius:8px;margin-top:4px;max-height:220px;overflow-y:auto;z-index:5;box-shadow:0 4px 14px rgba(0,0,0,.08);"></div>
                    <div id="rlPatientSelected" style="display:none;margin-top:8px;padding:8px 10px;background:#EEEDFE;border-radius:8px;font-size:12.5px;color:#534AB7;">
                        <span id="rlPatientSelectedName"></span>
                        <button type="button" onclick="rlClearPatient()" style="float:right;background:none;border:none;color:#534AB7;cursor:pointer;font-weight:600;">Change</button>
                    </div>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Priority <span style="color:#c0392b;">*</span></label>
                    <select name="priority" required style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;background:#fff;">
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="low">Low</option>
                    </select>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Follow-up Date <span style="color:#c0392b;">*</span></label>
                    <input type="date" name="follow_up_date" required value="{{ old('follow_up_date', today()->toDateString()) }}"
                           style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;">
                </div>

                <div style="margin-bottom:20px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Note</label>
                    <textarea name="note" rows="3" placeholder="Why is this patient being recalled?"
                              style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;resize:vertical;">{{ old('note') }}</textarea>
                </div>

                <div style="display:flex;gap:8px;">
                    <button type="submit" style="flex:1;background:#534AB7;color:#fff;border:none;border-radius:8px;padding:12px;font-size:14px;font-weight:600;cursor:pointer;">Add Recall</button>
                    <button type="button" onclick="rlCloseAddRecall()" style="padding:12px 18px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;font-size:14px;color:#6b7280;cursor:pointer;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         Convert-to-Opportunity modal — per-row, for when the recall call
         reveals a real treatment need. Posts to relationship.recalls.convert.
    ══════════════════════════════════════════════════════════════════ --}}
    <div id="rlConvertModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:210;align-items:center;justify-content:center;padding:20px;">
        <div style="background:#fff;border-radius:12px;padding:24px;width:440px;max-width:100%;max-height:90vh;overflow-y:auto;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                <h3 style="margin:0;font-size:18px;font-weight:700;color:#1f2937;font-family:'Cormorant Garamond',serif;">Convert to Opportunity</h3>
                <button type="button" onclick="rlCloseConvert()" style="border:none;background:none;font-size:18px;color:#9ca3af;cursor:pointer;line-height:1;">&times;</button>
            </div>
            <p style="color:#6b7280;font-size:13px;margin:0 0 18px;">For <strong id="rlConvertPatientName"></strong> — this recall will be closed and a new opportunity opened.</p>

            <form id="rlConvertForm" method="POST" action="">
                @csrf
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Treatment <span style="color:#c0392b;">*</span></label>
                    <select name="type" required style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;background:#fff;">
                        <option value="">Select treatment</option>
                        @foreach(\App\Models\TreatmentOpportunity::TREATMENT_TYPES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="display:flex;gap:12px;margin-bottom:16px;">
                    <div style="flex:1;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Priority <span style="color:#c0392b;">*</span></label>
                        <select name="priority" required style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;background:#fff;">
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                    <div style="flex:1;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Est. Value (₹)</label>
                        <input type="number" name="estimated_value" min="0" placeholder="e.g. 15000"
                               style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;">
                    </div>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Follow-up Date <span style="color:#c0392b;">*</span></label>
                    <input type="date" name="follow_up_date" required value="{{ today()->toDateString() }}"
                           style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;">
                </div>

                <div style="margin-bottom:20px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Notes</label>
                    <textarea name="notes" rows="3" placeholder="What did the patient say?"
                              style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;resize:vertical;"></textarea>
                </div>

                <div style="display:flex;gap:8px;">
                    <button type="submit" style="flex:1;background:#534AB7;color:#fff;border:none;border-radius:8px;padding:12px;font-size:14px;font-weight:600;cursor:pointer;">Convert</button>
                    <button type="button" onclick="rlCloseConvert()" style="padding:12px 18px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;font-size:14px;color:#6b7280;cursor:pointer;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function recallsList() {
    return {
        selected: [],
        selectAllMatching: false,
        toggleAll(e) {
            const boxes = document.querySelectorAll('.rl-row-check');
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
            document.querySelectorAll('.rl-row-check, thead input[type=checkbox]').forEach(b => b.checked = false);
        },
    };
}

function rlOpenAddRecall() { document.getElementById('rlAddRecallModal').style.display = 'flex'; }
function rlCloseAddRecall() { document.getElementById('rlAddRecallModal').style.display = 'none'; }

function rlOpenConvert(recallId, patientName) {
    document.getElementById('rlConvertPatientName').textContent = patientName;
    document.getElementById('rlConvertForm').action = '{{ url('/relationship/recalls') }}/' + recallId + '/convert';
    document.getElementById('rlConvertModal').style.display = 'flex';
}
function rlCloseConvert() { document.getElementById('rlConvertModal').style.display = 'none'; }

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { rlCloseAddRecall(); rlCloseConvert(); }
});
document.getElementById('rlAddRecallModal')?.addEventListener('click', function (e) { if (e.target === this) this.style.display = 'none'; });
document.getElementById('rlConvertModal')?.addEventListener('click', function (e) { if (e.target === this) this.style.display = 'none'; });

function rlClearPatient() {
    document.getElementById('rlPatientId').value = '';
    document.getElementById('rlPatientSelected').style.display = 'none';
    document.getElementById('rlPatientSearch').style.display = 'block';
    document.getElementById('rlPatientSearch').value = '';
    document.getElementById('rlPatientSearch').focus();
}

let rlSearchTimer = null;
function rlSearchPatients(q) {
    clearTimeout(rlSearchTimer);
    const box = document.getElementById('rlPatientResults');
    if (q.trim().length < 3) { box.style.display = 'none'; box.innerHTML = ''; return; }

    rlSearchTimer = setTimeout(function () {
        fetch("{{ route('relationship.search') }}?q=" + encodeURIComponent(q))
            .then(r => r.json())
            .then(function (results) {
                const withPatient = results.filter(r => r.patient_id);
                box.innerHTML = '';
                if (withPatient.length === 0) {
                    box.innerHTML = '<div style="padding:10px 12px;font-size:12.5px;color:#9ca3af;">No matching patients found.</div>';
                    box.style.display = 'block';
                    return;
                }
                withPatient.forEach(function (r) {
                    const row = document.createElement('div');
                    row.style.cssText = 'padding:9px 12px;font-size:13px;cursor:pointer;border-bottom:1px solid #f3f4f6;';
                    row.innerHTML = '<div style="font-weight:600;color:#1f2937;">' + (r.name || 'Unnamed') + '</div>' +
                                    '<div style="font-size:11.5px;color:#9ca3af;">' + (r.meta || '') + '</div>';
                    row.onmouseenter = () => row.style.background = '#f7f8fa';
                    row.onmouseleave = () => row.style.background = '#fff';
                    row.onclick = function () { rlSelectPatient(r); };
                    box.appendChild(row);
                });
                box.style.display = 'block';
            })
            .catch(() => { box.style.display = 'none'; });
    }, 250);
}

function rlSelectPatient(r) {
    document.getElementById('rlPatientId').value = r.patient_id || '';
    document.getElementById('rlPatientSelectedName').textContent = r.name + (r.phone ? ' — ' + r.phone : '');
    document.getElementById('rlPatientSelected').style.display = 'block';
    document.getElementById('rlPatientSearch').style.display = 'none';
    document.getElementById('rlPatientResults').style.display = 'none';
    document.getElementById('rlPatientResults').innerHTML = '';
}
</script>
@endsection
