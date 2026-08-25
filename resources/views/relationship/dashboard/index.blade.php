{{--
|==========================================================================
| PRE — Relationship Dashboard
| Route: GET /relationship/dashboard   [relationship.dashboard]
|
| VISUAL REDEVELOPMENT — reference-faithful pass (2026-08-25).
| The approved reference screenshot is the VISUAL source of truth (card
| anatomy, proportions, row structure, typography scale, spacing). The
| existing PRE controller is the DATA source of truth. Nothing here invents
| a metric, patient, activity row, count or category.
|
| Layout was verified by rendering this exact CSS in headless Chromium and
| comparing it against the reference before commit.
|
| PRESENTATION ONLY — no controller, service, model, route, query, engine,
| flag, permission or business-rule change.
|
| Variables from DashboardController@index:
|   $stats             relationships | patients | active_leads | open_opportunities
|   $journeys          lead | opportunity        (shadow journeys — not rendered)
|   $recent            12 latest Relationship rows
|   $highPriorityToday int, from the shared Today's Actions projection
|   $openRecalls       int
|   $actionSummary     total | by_category | by_priority | generated_at
|
| COUNT SEMANTICS: $actionSummary reads the `today_actions` projection, which
| spans open work INCLUDING overdue. Today's Actions (post-Sprint A) shows only
| work due TODAY. Labels here therefore say "Open", never "Today", so the words
| always match the number beside them.
|==========================================================================
--}}
@extends('relationship.layouts.app')

@section('page-title', 'Dashboard')

@section('relationship-content')
<style>
    #df-content-inner { padding: 0 !important; background: #F9FAFB; }
    .dfd { max-width:1400px; margin:0 auto; padding:14px 22px 16px; }

    /* ── Header ─────────────────────────────────────────────── */
    .dfd-bar { display:flex; align-items:flex-start; justify-content:space-between; gap:20px; flex-wrap:wrap; margin-bottom:12px; }
    .dfd-h1 { margin:0; font-size:20px; font-weight:700; color:#111827; letter-spacing:-.02em; }
    .dfd-h1s { margin:3px 0 0; font-size:12px; color:#9CA3AF; }
    .dfd-bar-r { display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
    .dfd-srch { position:relative; width:300px; }
    .dfd-srch input { width:100%; box-sizing:border-box; padding:9px 12px 9px 36px; border:1px solid #E5E7EB;
        border-radius:9px; font-size:13px; color:#111827; background:#fff; outline:none; }
    .dfd-srch input:focus { border-color:#534AB7; box-shadow:0 0 0 3px rgba(83,74,183,.10); }
    .dfd-srch svg { position:absolute; left:12px; top:50%; transform:translateY(-50%); }
    .dfd-ghost { display:inline-flex; align-items:center; gap:7px; background:#fff; border:1px solid #E5E7EB;
        border-radius:9px; padding:9px 15px; font-size:13px; font-weight:600; color:#374151; text-decoration:none; white-space:nowrap; }
    .dfd-ghost:hover { background:#FAFAFB; border-color:#D1D5DB; }
    .dfd-stamp { font-size:12.5px; color:#9CA3AF; white-space:nowrap; }

    /* ── KPI row ────────────────────────────────────────────── */
    .dfd-kpis { display:grid; grid-template-columns:repeat(6,1fr); gap:10px; margin-bottom:12px; }
    @media (max-width:1300px){ .dfd-kpis{ grid-template-columns:repeat(3,1fr);} }
    @media (max-width:760px){ .dfd-kpis{ grid-template-columns:repeat(2,1fr);} }
    .dfd-kpi { background:#fff; border:1px solid #E5E7EB; border-radius:10px; padding:10px 12px 9px; text-decoration:none;
        display:flex; flex-direction:column; box-shadow:0 1px 2px rgba(16,24,40,.04);
        transition:box-shadow .15s, border-color .15s; }
    .dfd-kpi:hover { box-shadow:0 4px 12px rgba(16,24,40,.08); border-color:#D1D5DB; }
    .dfd-kpi-top { display:flex; align-items:center; gap:8px; margin-bottom:7px; }
    .dfd-ico { width:26px; height:26px; border-radius:7px; flex:none; display:flex; align-items:center; justify-content:center; }
    .dfd-kpi-lbl { font-size:12px; font-weight:500; color:#4B5563; line-height:1.25; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .dfd-kpi-foot { display:flex; align-items:baseline; justify-content:space-between; gap:8px; margin-top:6px; }
    .dfd-kpi-num { font-size:20px; font-weight:700; color:#111827; line-height:1; letter-spacing:-.02em; }
    .dfd-kpi-sub { font-size:11px; color:#9CA3AF; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .dfd-kpi-cta { font-size:11px; font-weight:500; color:#534AB7; flex:none; opacity:.9; }

    /* ── Panels ─────────────────────────────────────────────── */
    .dfd-cols { display:grid; grid-template-columns:1.08fr 1fr; gap:14px; margin-bottom:12px; align-items:stretch; }
    @media (max-width:1150px){ .dfd-cols{ grid-template-columns:1fr; } }
    .dfd-panel { background:#fff; border:1px solid #E5E7EB; border-radius:11px; box-shadow:0 1px 2px rgba(16,24,40,.04); display:flex; flex-direction:column; }
    .dfd-hd { display:flex; align-items:center; justify-content:space-between; padding:14px 18px 11px; gap:12px; }
    .dfd-hd h2 { font-size:15px; font-weight:600; color:#111827; margin:0; }
    .dfd-hd a { font-size:13px; font-weight:500; color:#534AB7; text-decoration:none; white-space:nowrap; }
    .dfd-hd a:hover { text-decoration:underline; }
    .dfd-scroll { flex:1 1 auto; max-height:clamp(126px, calc(100vh - 516px), 384px); overflow-y:auto; }

    /* ── Category rows (48px × 8 = 384) ─────────────────────── */
    .dfd-r { display:flex; align-items:center; gap:12px; padding:0 18px; height:42px; border-top:1px solid #F3F4F6; }
    .dfd-r:hover { background:#FCFBFE; }
    .dfd-r-ico { width:36px; height:36px; border-radius:9px; flex:none; display:flex; align-items:center; justify-content:center; }
    .dfd-r-name { font-size:14px; font-weight:500; color:#111827; flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .dfd-r-num { font-size:14px; font-weight:500; color:#111827; width:40px; text-align:right; flex:none; font-variant-numeric:tabular-nums; }
    .dfd-r-st { font-size:13px; width:120px; flex:none; padding-left:26px; }
    .dfd-r-dot { display:inline-block; width:5px; height:5px; border-radius:50%; margin-right:7px; vertical-align:middle; }
    .dfd-r-v { font-size:13px; font-weight:500; color:#534AB7; text-decoration:none; width:38px; text-align:right; flex:none; }
    .dfd-r-v:hover { text-decoration:underline; }

    /* ── Recent rows (64px × 6 = 384 → panels end level) ────── */
    .dfd-a { display:flex; align-items:center; gap:12px; padding:0 18px; height:56px; border-top:1px solid #F3F4F6; text-decoration:none; }
    .dfd-a:hover { background:#FCFBFE; }
    .dfd-a-av { width:34px; height:34px; border-radius:50%; flex:none; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:600; }
    .dfd-a-m { flex:1; min-width:0; }
    .dfd-a-t { font-size:14px; font-weight:600; color:#111827; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .dfd-a-s { font-size:13px; color:#6B7280; margin-top:3px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .dfd-a-r { text-align:right; flex:none; }
    .dfd-a-1 { font-size:12.5px; color:#6B7280; }
    .dfd-a-2 { font-size:12.5px; margin-top:4px; }

    /* ── Needs Attention ────────────────────────────────────── */
    .dfd-rw { background:#fff; border:1px solid #E5E7EB; border-radius:11px; padding:13px 16px 14px; box-shadow:0 1px 2px rgba(16,24,40,.04); }
    .dfd-rw h2 { font-size:14px; font-weight:600; color:#111827; margin:0 0 10px; }
    .dfd-rg { display:grid; grid-template-columns:repeat(5,1fr); gap:11px; }
    @media (max-width:1300px){ .dfd-rg{ grid-template-columns:repeat(3,1fr);} }
    @media (max-width:760px){ .dfd-rg{ grid-template-columns:repeat(2,1fr);} }
    .dfd-risk { border:1px solid #E5E7EB; border-radius:9px; padding:11px 12px; text-decoration:none; display:block; background:#fff; }
    .dfd-risk:hover { border-color:#D1D5DB; background:#FCFCFD; }
    .dfd-risk-top { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; }
    .dfd-risk-l { font-size:12px; font-weight:500; color:#4B5563; line-height:1.25; }
    .dfd-risk-i { width:26px; height:26px; border-radius:50%; flex:none; display:flex; align-items:center; justify-content:center; }
    .dfd-risk-n { font-size:19px; font-weight:700; color:#111827; margin-top:8px; line-height:1; letter-spacing:-.02em; }
    .dfd-risk-s { font-size:11px; color:#9CA3AF; margin-top:5px; }

    .dfd-empty { padding:34px 22px; text-align:center; color:#9CA3AF; font-size:13px; border-top:1px solid #F3F4F6; }
</style>

@php
    /* ── Line-icon library (presentation only) ──────────────── */
    $ICO = [
        'cal'     => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'phone'   => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'rupee'   => '<path d="M6 3h12"/><path d="M6 8h12"/><path d="M6 13h4a5 5 0 0 0 0-10"/><path d="M6 13l7 8"/>',
        'refresh' => '<path d="M4 4v5h.582m15.356 2A8.001 8.001 0 0 0 4.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 0 1-15.357-2m15.357 2H15"/>',
        'check'   => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'flask'   => '<path d="M9 2v6l-5 9a2 2 0 0 0 1.7 3h12.6a2 2 0 0 0 1.7-3l-5-9V2"/><line x1="8" y1="2" x2="16" y2="2"/>',
        'doc'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
        'user'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>',
        'alert'   => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'star'    => '<path d="M12 2l3 7h7l-5.5 4.5L18.5 21 12 16.5 5.5 21 7.5 13.5 2 9h7z"/>',
        'people'  => '<path d="M20 21v-2a4 4 0 0 0-3-3.87"/><path d="M4 21v-2a4 4 0 0 1 3-3.87"/><circle cx="12" cy="7" r="4"/>',
        'info'    => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
    ];

    /* ── Category presentation map — labels mirror
         TodayController::CATEGORY_LABELS verbatim.
         [label, route slug, tone, icon]  — view layer only.        */
    $catMeta = [
        'appointment_reminders_today'   => ["Today's Appointments — Confirm",  'today',         'urgent',  'cal'],
        'follow_up_calls'               => ['Follow-up Calls',                 'today',         'urgent',  'phone'],
        'missed_calls_yesterday'        => ["Yesterday's Missed Calls",        'today',         'urgent',  'phone'],
        'missed_appointments_yesterday' => ["Yesterday's Missed Appointments", 'today',         'urgent',  'cal'],
        'new_enquiries'                 => ['New Enquiries',                   'pipeline',      'urgent',  'user'],
        'payment_reminders'             => ['Payment Reminders',               'today',         'urgent',  'rupee'],
        'wellness_check_yesterday'      => ["Yesterday's Treated Patients",    'today',         'due',     'check'],
        'recall_calls'                  => ['Recall Calls',                    'recalls',       'due',     'refresh'],
        'lab_ready'                     => ['Lab Work Ready',                  'today',         'due',     'flask'],
        'lead_followups'                => ['Lead Follow-ups',                 'pipeline',      'due',     'user'],
        'opportunities'                 => ['Treatment Opportunities',         'opportunities', 'due',     'star'],
        'pending_estimates'             => ['Pending Estimates',               'opportunities', 'due',     'doc'],
        'tasks'                         => ['Follow-up Tasks',                 'today',         'routine', 'check'],
        'logged_communications'         => ['Other Calls',                     'today',         'routine', 'phone'],
        'membership_renewals'           => ['Membership Renewals',             'today',         'routine', 'star'],
        'appointment_reminders_tomorrow'=> ["Tomorrow Morning's Appointments", 'today',         'routine', 'cal'],
        'appointment_reminders'         => ['Appointment Reminders',           'today',         'routine', 'cal'],
    ];

    // [text colour, tint, status word] — display convention, not stored priority.
    $tones = [
        'urgent'  => ['#D92D20', '#FEF3F2', 'Needs action'],
        'due'     => ['#B54708', '#FFF6ED', 'Open'],
        'routine' => ['#6B7280', '#F3F4F6', 'Scheduled'],
    ];

    $summary    = $actionSummary ?? ['total'=>null,'by_category'=>[],'by_priority'=>[],'generated_at'=>null];
    // Zero-count categories are dropped — the reference does not render empty rows.
    $byCategory = collect($summary['by_category'] ?? [])->filter(fn ($n) => $n > 0);

    $rankedCats = $byCategory->sortBy(function ($n, $key) use ($catMeta) {
        $tone = $catMeta[$key][2] ?? 'routine';
        $rank = ['urgent'=>0,'due'=>1,'routine'=>2][$tone] ?? 3;
        return sprintf('%d-%09d', $rank, 1000000 - $n);
    });

    $totalOpen = $summary['total'] ?? $byCategory->sum();

    /* KPI row — six existing PRE metrics. [label, value, subtitle, route, tint, colour, icon] */
    $kpis = [
        ['Open Actions',  $totalOpen,                   'Open now',        'relationship.today',         '#EFF4FF','#3538CD','cal'],
        ['High Priority', $highPriorityToday,           'Call first',      'relationship.today',         '#FEF3F2','#D92D20','alert'],
        ['Open Recalls',  $openRecalls,                 'To bring back',   'relationship.recalls',       '#FFF6ED','#B54708','refresh'],
        ['Active Leads',  $stats['active_leads'],       'In pipeline',     'relationship.pipeline',      '#ECFDF3','#067647','user'],
        ['Opportunities', $stats['open_opportunities'], 'Active',          'relationship.opportunities', '#F4F3FF','#5925DC','star'],
        ['Relationships', $stats['relationships'],      number_format($stats['patients']).' patients', 'relationship.list', '#FFFAEB','#B54708','people'],
    ];

    /* Needs Attention — every figure already reported by the projection, plus
       $openRecalls. No new metric. [label, value, subtitle, tint, colour, route] */
    $risks = [
        ['Missed Appointments', $byCategory->get('missed_appointments_yesterday', 0), 'yesterday',             '#FEF3F2','#D92D20','relationship.today'],
        ['Overdue Payments',    $byCategory->get('payment_reminders', 0),             'awaiting collection',   '#FEF3F2','#D92D20','relationship.today'],
        ['Pending Estimates',   $byCategory->get('pending_estimates', 0),             'awaiting decision',     '#FFF6ED','#B54708','relationship.opportunities'],
        ['Lab Work Waiting',    $byCategory->get('lab_ready', 0),                     'no appointment booked', '#FFF6ED','#B54708','relationship.today'],
        ['Patients to Recall',  $openRecalls,                                         'open recall queue',     '#EFF4FF','#3538CD','relationship.recalls'],
    ];
@endphp

<div class="dfd">

    {{-- ══ A. Header ═══════════════════════════════════════════ --}}
    <div class="dfd-bar">
        <div>
            <h1 class="dfd-h1">Dashboard</h1>
            <p class="dfd-h1s">{{ now()->format('l, d F Y') }}</p>
        </div>
        <div class="dfd-bar-r">
            <form method="GET" action="{{ route('relationship.list') }}" class="dfd-srch">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" name="q" placeholder="Search patient, phone or email…">
            </form>
            <a href="{{ route('relationship.dashboard') }}" class="dfd-ghost">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M23 4v6h-6"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                Refresh
            </a>
            @if(!empty($summary['generated_at']))
                <span class="dfd-stamp">Last updated {{ \Carbon\Carbon::parse($summary['generated_at'])->format('g:i A') }}</span>
            @endif
        </div>
    </div>

    {{-- ══ B. KPI row ══════════════════════════════════════════ --}}
    <div class="dfd-kpis">
        @foreach ($kpis as [$lbl, $val, $sub, $rt, $bg, $fg, $ik])
            <a href="{{ route($rt) }}" class="dfd-kpi">
                <div class="dfd-kpi-top">
                    <span class="dfd-ico" style="background:{{ $bg }};">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                             stroke="{{ $fg }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $ICO[$ik] !!}</svg>
                    </span>
                    <span class="dfd-kpi-lbl">{{ $lbl }}</span>
                </div>
                <div class="dfd-kpi-num">{{ $val === null ? '—' : number_format($val) }}</div>
                <div class="dfd-kpi-foot">
                    <span class="dfd-kpi-sub">{{ $sub }}</span>
                    <span class="dfd-kpi-cta">View all ›</span>
                </div>
            </a>
        @endforeach
    </div>

    {{-- ══ C. Two-column content ═══════════════════════════════ --}}
    <div class="dfd-cols">

        <div class="dfd-panel">
            <div class="dfd-hd">
                <h2>Open Actions by Category</h2>
                <a href="{{ route('relationship.today') }}">View all ({{ number_format($totalOpen) }}) ›</a>
            </div>

            @if ($rankedCats->isEmpty())
                <div class="dfd-empty">Nothing open right now — the board is clear.</div>
            @else
                <div class="dfd-scroll">
                    @foreach ($rankedCats as $key => $count)
                        @php
                            $m    = $catMeta[$key] ?? [ucwords(str_replace('_',' ',$key)), 'today', 'routine', 'cal'];
                            $t    = $tones[$m[2]] ?? $tones['routine'];
                            $href = route('relationship.' . $m[1]);
                        @endphp
                        <div class="dfd-r">
                            <span class="dfd-r-ico" style="background:{{ $t[1] }};">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                     stroke="{{ $t[0] }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $ICO[$m[3]] ?? $ICO['cal'] !!}</svg>
                            </span>
                            <span class="dfd-r-name">{{ $m[0] }}</span>
                            <span class="dfd-r-num">{{ number_format($count) }}</span>
                            <span class="dfd-r-st" style="color:{{ $t[0] }};">
                                <span class="dfd-r-dot" style="background:{{ $t[0] }};"></span>{{ $t[2] }}
                            </span>
                            <a href="{{ $href }}" class="dfd-r-v">View</a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Recent Relationships. Heading is deliberately NOT "Recent Activity":
             the controller supplies relationships, not the Activity ledger. --}}
        <div class="dfd-panel">
            <div class="dfd-hd">
                <h2>Recent Relationships</h2>
                <a href="{{ route('relationship.list') }}">View all ›</a>
            </div>

            @if ($recent->isEmpty())
                <div class="dfd-empty">No relationships yet.</div>
            @else
                <div class="dfd-scroll">
                    @foreach ($recent as $r)
                        @php
                            $ini = collect(explode(' ', trim($r->name ?? '?')))->filter()->take(2)
                                    ->map(fn ($x) => mb_strtoupper(mb_substr($x,0,1)))->implode('');
                            $act = ($r->status ?? 'active') === 'active';
                        @endphp
                        <a href="{{ route('relationship.profile', $r->id) }}" class="dfd-a">
                            <span class="dfd-a-av" style="background:{{ $act ? '#ECFDF3' : '#F3F4F6' }};color:{{ $act ? '#067647' : '#6B7280' }};">{{ $ini ?: '?' }}</span>
                            <span class="dfd-a-m">
                                <span class="dfd-a-t" style="display:block;">{{ $r->name }}</span>
                                <span class="dfd-a-s" style="display:block;">{{ $r->phone ?: 'No number on file' }}</span>
                            </span>
                            <span class="dfd-a-r">
                                <span class="dfd-a-1" style="display:block;">{{ optional($r->relationship_since)->format('d M Y') ?? '—' }}</span>
                                <span class="dfd-a-2" style="display:block;color:{{ $act ? '#067647' : '#9CA3AF' }};">{{ ucfirst($r->status ?? 'active') }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ══ D. Needs Attention ══════════════════════════════════ --}}
    <div class="dfd-rw">
        <h2>Needs Attention</h2>
        <div class="dfd-rg">
            @foreach ($risks as [$lbl, $val, $sub, $bg, $fg, $rt])
                <a href="{{ route($rt) }}" class="dfd-risk">
                    <div class="dfd-risk-top">
                        <span class="dfd-risk-l">{{ $lbl }}</span>
                        <span class="dfd-risk-i" style="background:{{ $val > 0 ? $bg : '#F3F4F6' }};">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                                 stroke="{{ $val > 0 ? $fg : '#9CA3AF' }}" stroke-width="2" stroke-linecap="round">{!! $ICO['info'] !!}</svg>
                        </span>
                    </div>
                    <div class="dfd-risk-n">{{ number_format($val) }}</div>
                    <div class="dfd-risk-s">{{ $sub }}</div>
                </a>
            @endforeach
        </div>
    </div>

</div>
@endsection
