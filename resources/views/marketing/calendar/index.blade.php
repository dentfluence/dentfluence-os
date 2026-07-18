{{--
|==========================================================================
| Marketing — Content Calendar
| File: resources/views/marketing/calendar/index.blade.php
|
| Phase 2.5-A: Month view, left sidebar, legend bar.
| Data: Mock posts from CalendarController@index.
|==========================================================================
--}}
@extends('marketing.layouts.app')

@php
    $marketingPageTitle = 'Calendar';

    // ── Month grid helpers — driven by CalendarController ($month / $year) ──
    // (Was hardcoded to a June-2026 demo; now honours the controller's month
    // so real scheduled/published social + blog posts land on their true
    // dates. Week/List views below remain illustrative mock data.)
    $year        = (int) ($year  ?? now()->year);
    $month       = (int) ($month ?? now()->month);
    $gridFirst   = \Carbon\Carbon::create($year, $month, 1);
    $monthLabel  = $gridFirst->format('F Y');
    $daysInMonth = $gridFirst->daysInMonth;

    // Leading blank cells before day 1 (Sun=0, Mon=1 … Sat=6).
    $startOffset = $gridFirst->dayOfWeek;

    // "Missed posts" warning zone was demo-only — no longer applied.
    $warnDays = [];

    // Highlight today only when the current month is on screen.
    $todayDay = ($year === (int) now()->year && $month === (int) now()->month)
        ? (int) now()->day
        : null;

    // Platform meta (color, icon SVG path(s))
    $platformMeta = [
        'instagram' => [
            'color' => '#e1306c',
            'label' => 'Instagram',
            'icon'  => '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>',
        ],
        'facebook' => [
            'color' => '#1877f2',
            'label' => 'Facebook',
            'icon'  => '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>',
        ],
        'google' => [
            'color' => '#4285f4',
            'label' => 'Google',
            'icon'  => '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 010 20M12 2a15.3 15.3 0 000 20"/></svg>',
        ],
        'blog' => [
            'color' => '#6366f1',
            'label' => 'Blog',
            'icon'  => '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>',
        ],
        'whatsapp' => [
            'color' => '#25d366',
            'label' => 'WhatsApp',
            'icon'  => '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>',
        ],
    ];

    // Status meta
    $statusMeta = [
        'published'       => ['color' => '#16a34a', 'label' => 'Published'],
        'scheduled'       => ['color' => '#0ea5e9', 'label' => 'Scheduled'],
        'draft'           => ['color' => '#94a3b8', 'label' => 'Draft'],
        'pending_approval'=> ['color' => '#f59e0b', 'label' => 'Pending Approval'],
        'failed'          => ['color' => '#ef4444', 'label' => 'Failed'],
    ];

    // Content type meta
    $contentTypeMeta = [
        'reel'     => ['icon' => '', 'label' => 'Reel',     'count' => 3],
        'post'     => ['icon' => '',  'label' => 'Post',     'count' => 2],
        'carousel' => ['icon' => '', 'label' => 'Carousel', 'count' => 2],
        'story'    => ['icon' => '', 'label' => 'Story',    'count' => 1],
        'blog'     => ['icon' => '', 'label' => 'Blog',     'count' => 2],
    ];
@endphp

@section('page-title', 'Marketing — Calendar')

@section('marketing-content')

{{-- Transition & cloak styles --}}
<style>
  [x-cloak]           { display: none !important; }
  .df-fade-t          { transition: opacity .18s ease; }
  .df-fade-hidden     { opacity: 0 !important; }
  .df-slide-t         { transition: transform .22s ease, opacity .22s ease; }
  .df-slide-hidden    { transform: translateX(20px) !important; opacity: 0 !important; }
</style>

<div x-data="calendarApp()" @keydown.escape.window="closePanel()">

{{-- ══════════════════════════════════════════════════════
     PAGE HEADER
════════════════════════════════════════════════════════ --}}
<div style="
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
    flex-wrap: wrap;
    gap: 12px;
">
    <div>
        <h1 style="
            font-family: 'Cormorant Garamond', serif;
            font-size: 26px;
            font-weight: 700;
            color: #1e0a2c;
            margin: 0 0 3px;
            line-height: 1.1;
        ">Content Calendar</h1>
        <p style="
            font-family: 'Inter', sans-serif;
            font-size: 12.5px;
            font-weight: 300;
            color: #7a6884;
            margin: 0;
        ">Plan, schedule, and publish across all platforms.</p>
    </div>
    <div style="display:flex; align-items:center; gap:10px;">
        {{-- Import / Export --}}
        <a href="#" style="
            font-family: 'Inter', sans-serif;
            font-size: 12.5px;
            color: #6a0f70;
            text-decoration: none;
            font-weight: 500;
        ">Import / Export</a>

        {{-- + New Post --}}
        <a href="#" style="
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #6a0f70 0%, #b95cb7 100%);
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 500;
            padding: 8px 18px;
            border-radius: 6px;
            text-decoration: none;
            box-shadow: 0 2px 8px rgba(106,15,112,0.22);
            transition: opacity 150ms;
        " onmouseover="this.style.opacity='0.88'" onmouseout="this.style.opacity='1'">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            New Post
        </a>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     TOP BAR: Navigator | View toggle | Platform pills | Filters
════════════════════════════════════════════════════════ --}}
<div style="
    display: flex;
    align-items: center;
    gap: 10px;
    background: #fff;
    border: 1px solid rgba(185,92,183,0.14);
    border-radius: 8px;
    padding: 10px 16px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    box-shadow: 0 1px 4px rgba(106,15,112,0.06);
">
    {{-- ← Month → navigator --}}
    <div style="display:flex; align-items:center; gap:6px; flex-shrink:0;">
        <button onclick="return false" title="Previous month" style="
            width: 28px; height: 28px;
            border: 1px solid rgba(185,92,183,0.2);
            background: #f9f3fa;
            border-radius: 5px;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            color: #6a0f70;
        ">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
        </button>
        <span style="
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 600;
            color: #1e0a2c;
            min-width: 96px;
            text-align: center;
        ">{{ $monthLabel }}</span>
        <button onclick="return false" title="Next month" style="
            width: 28px; height: 28px;
            border: 1px solid rgba(185,92,183,0.2);
            background: #f9f3fa;
            border-radius: 5px;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            color: #6a0f70;
        ">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="9 18 15 12 9 6"/>
            </svg>
        </button>
    </div>

    {{-- Today button --}}
    <button onclick="return false" style="
        padding: 5px 12px;
        font-family: 'Inter', sans-serif;
        font-size: 12.5px;
        font-weight: 500;
        color: #6a0f70;
        background: #f9f3fa;
        border: 1px solid rgba(185,92,183,0.2);
        border-radius: 5px;
        cursor: pointer;
        flex-shrink: 0;
    ">Today</button>

    {{-- Divider --}}
    <div style="width:1px; height:24px; background:rgba(185,92,183,0.15); flex-shrink:0;"></div>

    {{-- Month / Week / List toggle --}}
    <div style="
        display: flex;
        border: 1px solid rgba(185,92,183,0.2);
        border-radius: 5px;
        overflow: hidden;
        flex-shrink: 0;
    ">
        <button @click="activeView='month'"
                :style="activeView==='month' ? 'padding:7px 20px;font-weight:600;color:#fff;background:linear-gradient(135deg,#6a0f70,#b95cb7);' : 'padding:7px 20px;font-weight:400;color:#5a4868;background:#fff;'"
                style="padding:7px 20px;font-family:'Inter',sans-serif;font-size:12.5px;letter-spacing:.01em;border:none;cursor:pointer;border-right:1px solid rgba(185,92,183,0.2);">Month</button>
        <button @click="activeView='week'"
                :style="activeView==='week' ? 'padding:7px 20px;font-weight:600;color:#fff;background:linear-gradient(135deg,#6a0f70,#b95cb7);' : 'padding:7px 20px;font-weight:400;color:#5a4868;background:#fff;'"
                style="padding:7px 20px;font-family:'Inter',sans-serif;font-size:12.5px;letter-spacing:.01em;border:none;cursor:pointer;border-right:1px solid rgba(185,92,183,0.2);">Week</button>
        <button @click="activeView='list'"
                :style="activeView==='list' ? 'padding:7px 20px;font-weight:600;color:#fff;background:linear-gradient(135deg,#6a0f70,#b95cb7);' : 'padding:7px 20px;font-weight:400;color:#5a4868;background:#fff;'"
                style="padding:7px 20px;font-family:'Inter',sans-serif;font-size:12.5px;letter-spacing:.01em;border:none;cursor:pointer;">List</button>
    </div>

    {{-- Divider --}}
    <div style="width:1px; height:24px; background:rgba(185,92,183,0.15); flex-shrink:0;"></div>

    {{-- Spacer --}}
    <div style="flex:1;"></div>

    {{-- ── Filters — collapsed into a single popover ──────────────
         Slice 4: platform pills + the checklist filters (below in the
         sidebar) used to sit permanently on-screen. Folded behind one
         toggle instead of removed; nothing here was deleted. --}}
    <div style="position:relative;" x-data="{ filtersOpen: false }" @click.outside="filtersOpen = false">
        <button @click="filtersOpen = !filtersOpen" type="button" style="
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 13px;
            font-family: 'Inter', sans-serif;
            font-size: 12.5px;
            font-weight: 500;
            color: #5a4868;
            background: #fff;
            border: 1px solid rgba(185,92,183,0.2);
            border-radius: 5px;
            cursor: pointer;
            flex-shrink: 0;
        ">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/>
            </svg>
            Filters
        </button>

        <div x-show="filtersOpen" x-cloak x-transition style="
            position:absolute; right:0; top:calc(100% + 6px); z-index:30;
            width:240px; background:#fff; border:1px solid rgba(185,92,183,0.18);
            border-radius:8px; box-shadow:0 4px 16px rgba(30,10,44,0.1);
            padding:14px;
        ">
            <p style="font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:#9b6aad; text-transform:uppercase; letter-spacing:.5px; margin:0 0 8px;">Platform</p>
            <div style="display:flex; align-items:center; gap:5px; flex-wrap:wrap; margin-bottom:12px;">
                @php
                    $pills = [
                        'all'       => ['label'=>'All',       'color'=>'#6a0f70',  'active'=>true],
                        'instagram' => ['label'=>'Instagram', 'color'=>'#e1306c',  'active'=>false],
                        'facebook'  => ['label'=>'Facebook',  'color'=>'#1877f2',  'active'=>false],
                        'google'    => ['label'=>'Google',    'color'=>'#4285f4',  'active'=>false],
                        'blog'      => ['label'=>'Blog',      'color'=>'#6366f1',  'active'=>false],
                        'whatsapp'  => ['label'=>'WhatsApp',  'color'=>'#25d366',  'active'=>false],
                    ];
                @endphp
                @foreach($pills as $key => $pill)
                <button onclick="return false" style="
                    padding: 4px 11px;
                    font-family: 'Inter', sans-serif;
                    font-size: 11.5px;
                    font-weight: {{ $pill['active'] ? '600' : '400' }};
                    color: {{ $pill['active'] ? '#fff' : $pill['color'] }};
                    background: {{ $pill['active'] ? $pill['color'] : 'transparent' }};
                    border: 1.5px solid {{ $pill['color'] }};
                    border-radius: 20px;
                    cursor: pointer;
                    transition: all 120ms;
                ">{{ $pill['label'] }}</button>
                @endforeach
            </div>
            <p style="font-family:'Inter',sans-serif; font-size:11px; color:#9ca3af; margin:0;">Status and content-type filters are in the sidebar.</p>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     BODY: LEFT SIDEBAR + MONTH GRID
════════════════════════════════════════════════════════ --}}
<div style="display:flex; align-items:flex-start; gap:18px;">

    {{-- ════════════════════════════════════════════════
         LEFT SIDEBAR
    ════════════════════════════════════════════════ --}}
    <div style="
        width: 220px;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        gap: 18px;
    ">

        {{-- ── Mini Month Calendar ──────────────────── --}}
        <div style="
            background: #fff;
            border: 1px solid rgba(185,92,183,0.14);
            border-radius: 8px;
            padding: 14px 12px;
            box-shadow: 0 1px 4px rgba(106,15,112,0.05);
        ">
            {{-- Mini calendar header --}}
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                <button onclick="return false" style="
                    width:22px; height:22px; border:none; background:none;
                    cursor:pointer; color:#6a0f70; display:flex; align-items:center; justify-content:center; padding:0;
                ">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>
                </button>
                <span style="
                    font-family:'Inter',sans-serif; font-size:12px;
                    font-weight:600; color:#1e0a2c;
                ">{{ $monthLabel }}</span>
                <button onclick="return false" style="
                    width:22px; height:22px; border:none; background:none;
                    cursor:pointer; color:#6a0f70; display:flex; align-items:center; justify-content:center; padding:0;
                ">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </button>
            </div>

            {{-- Day-of-week headers --}}
            <div style="display:grid; grid-template-columns:repeat(7,1fr); gap:1px; margin-bottom:4px;">
                @foreach(['S','M','T','W','T','F','S'] as $d)
                <div style="text-align:center; font-family:'Inter',sans-serif; font-size:10px; font-weight:600; color:#9b6aad;">{{ $d }}</div>
                @endforeach
            </div>

            {{-- Mini grid --}}
            @php
                // Days with posts (from mock data)
                $daysWithPosts = $postsByDate->keys()->map(fn($d) => (int) substr($d, 8, 2))->toArray();
                // Generate 6×7 grid
                $miniCells = array_merge(
                    array_fill(0, $startOffset, null),     // leading blanks
                    range(1, $daysInMonth),
                    array_fill(0, 42 - $daysInMonth - $startOffset, null) // trailing blanks
                );
            @endphp

            <div style="display:grid; grid-template-columns:repeat(7,1fr); gap:1px;">
                @foreach($miniCells as $miniDay)
                <div style="
                    display:flex; align-items:center; justify-content:center;
                    height:24px; position:relative;
                ">
                    @if($miniDay !== null)
                    <span style="
                        width: 22px; height: 22px;
                        display: flex; align-items: center; justify-content: center;
                        font-family: 'Inter', sans-serif;
                        font-size: 10.5px;
                        font-weight: {{ $miniDay === $todayDay ? '700' : '400' }};
                        color: {{ $miniDay === $todayDay ? '#fff' : '#3d2450' }};
                        background: {{ $miniDay === $todayDay ? 'linear-gradient(135deg,#6a0f70,#b95cb7)' : 'transparent' }};
                        border-radius: 50%;
                        cursor: pointer;
                        position: relative;
                    ">{{ $miniDay }}</span>
                    @if(in_array($miniDay, $daysWithPosts) && $miniDay !== $todayDay)
                    {{-- Dot below day number --}}
                    <span style="
                        position:absolute; bottom:1px;
                        left:50%; transform:translateX(-50%);
                        width:4px; height:4px;
                        border-radius:50%;
                        background:#b95cb7;
                    "></span>
                    @endif
                    @endif
                </div>
                @endforeach
            </div>
        </div>{{-- /mini calendar --}}

        {{-- ── Sidebar filters — collapsed by default ──────────────
             Slice 4: Status + Content Type checklists used to always
             take up the whole sidebar. Folded behind one disclosure
             toggle instead of removed; nothing here was deleted. --}}
        <div x-data="{ sidebarFiltersOpen: false }">
        <button type="button" @click="sidebarFiltersOpen = !sidebarFiltersOpen" style="
            width:100%; display:flex; align-items:center; justify-content:space-between;
            padding:10px 14px; background:#fff;
            border:1px solid rgba(185,92,183,0.14); border-radius:8px;
            font-family:'Inter',sans-serif; font-size:12.5px; font-weight:500; color:#5a4868;
            cursor:pointer; margin-bottom:10px;
        ">
            <span>More filters</span>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :style="sidebarFiltersOpen ? 'transform:rotate(180deg);' : ''" style="transition:transform 150ms;">
                <polyline points="6 9 12 15 18 9"/>
            </svg>
        </button>
        <div x-show="sidebarFiltersOpen" x-cloak x-transition style="display:flex; flex-direction:column; gap:18px;">

        {{-- ── Filter by Status ────────────────────── --}}
        <div style="
            background:#fff;
            border:1px solid rgba(185,92,183,0.14);
            border-radius:8px;
            padding:14px 14px;
            box-shadow:0 1px 4px rgba(106,15,112,0.05);
        ">
            <div style="
                font-family:'Inter',sans-serif; font-size:11px;
                font-weight:700; color:#9b6aad; text-transform:uppercase;
                letter-spacing:.06em; margin-bottom:10px;
            ">Filter by Status</div>

            @php
                $statusFilters = [
                    ['key'=>'all',             'label'=>'All Status',       'count'=>24, 'color'=>'#6a0f70'],
                    ['key'=>'scheduled',       'label'=>'Scheduled',        'count'=>10, 'color'=>'#0ea5e9'],
                    ['key'=>'published',       'label'=>'Published',        'count'=>8,  'color'=>'#16a34a'],
                    ['key'=>'draft',           'label'=>'Draft',            'count'=>3,  'color'=>'#94a3b8'],
                    ['key'=>'pending_approval','label'=>'Pending Approval', 'count'=>2,  'color'=>'#f59e0b'],
                    ['key'=>'failed',          'label'=>'Failed',           'count'=>1,  'color'=>'#ef4444'],
                ];
            @endphp

            <div style="display:flex; flex-direction:column; gap:7px;">
                @foreach($statusFilters as $sf)
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" {{ $sf['key'] === 'all' ? 'checked' : '' }}
                        style="accent-color:{{ $sf['color'] }}; width:13px; height:13px; cursor:pointer; flex-shrink:0;">
                    <span style="
                        display:flex; align-items:center;
                        width:8px; height:8px;
                        background:{{ $sf['color'] }};
                        border-radius:50%; flex-shrink:0;
                    "></span>
                    <span style="
                        font-family:'Inter',sans-serif; font-size:12.5px;
                        color:#3d2450; flex:1;
                    ">{{ $sf['label'] }}</span>
                    <span style="
                        font-family:'Inter',sans-serif; font-size:11.5px;
                        font-weight:600; color:#9b6aad;
                        background:#f5eef9; border-radius:10px;
                        padding:1px 7px;
                    ">{{ $sf['count'] }}</span>
                </label>
                @endforeach
            </div>
        </div>{{-- /status filters --}}

        {{-- ── Content Type ─────────────────────────── --}}
        <div style="
            background:#fff;
            border:1px solid rgba(185,92,183,0.14);
            border-radius:8px;
            padding:14px 14px;
            box-shadow:0 1px 4px rgba(106,15,112,0.05);
        ">
            <div style="
                font-family:'Inter',sans-serif; font-size:11px;
                font-weight:700; color:#9b6aad; text-transform:uppercase;
                letter-spacing:.06em; margin-bottom:10px;
            ">Content Type</div>

            @php
                $contentFilters = [
                    ['icon'=>'','label'=>'Reel',     'count'=>8],
                    ['icon'=>'', 'label'=>'Post',     'count'=>7],
                    ['icon'=>'','label'=>'Carousel', 'count'=>4],
                    ['icon'=>'','label'=>'Story',    'count'=>3],
                    ['icon'=>'','label'=>'Blog',     'count'=>2],
                ];
            @endphp

            <div style="display:flex; flex-direction:column; gap:6px;">
                @foreach($contentFilters as $cf)
                <label style="
                    display:flex; align-items:center; gap:8px;
                    cursor:pointer;
                    padding:5px 8px;
                    border-radius:6px;
                    transition:background 120ms;
                " onmouseover="this.style.background='#f9f3fa'" onmouseout="this.style.background='transparent'">
                    <span style="font-size:14px; flex-shrink:0;">{{ $cf['icon'] }}</span>
                    <span style="
                        font-family:'Inter',sans-serif; font-size:12.5px;
                        color:#3d2450; flex:1;
                    ">{{ $cf['label'] }}</span>
                    <span style="
                        font-family:'Inter',sans-serif; font-size:11.5px;
                        font-weight:600; color:#9b6aad;
                        background:#f5eef9; border-radius:10px;
                        padding:1px 7px;
                    ">{{ $cf['count'] }}</span>
                </label>
                @endforeach
            </div>

            {{-- Clear filters link --}}
            <div style="margin-top:12px; padding-top:10px; border-top:1px solid rgba(185,92,183,0.1);">
                <a href="#" style="
                    font-family:'Inter',sans-serif; font-size:12px;
                    color:#b95cb7; text-decoration:none; font-weight:500;
                " onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                    Clear Filters
                </a>
            </div>
        </div>{{-- /content type --}}

        </div>{{-- /sidebarFiltersOpen x-show --}}
        </div>{{-- /sidebarFiltersOpen x-data --}}

    </div>{{-- /LEFT SIDEBAR --}}

    {{-- ════════════════════════════════════════════════
         MONTH GRID (main area)
    ════════════════════════════════════════════════ --}}
    <div style="flex:1; min-width:0;">

        {{-- ══ MONTH VIEW ══ --}}
        <div x-show="activeView === 'month'" style="
            background:#fff;
            border:1px solid rgba(185,92,183,0.14);
            border-radius:8px;
            overflow:hidden;
            box-shadow:0 1px 4px rgba(106,15,112,0.06);
        ">

            {{-- Day-of-week header row --}}
            <div style="
                display:grid;
                grid-template-columns: repeat(7, 1fr);
                border-bottom:1px solid rgba(185,92,183,0.1);
                background:#faf5fb;
            ">
                @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow)
                <div style="
                    padding:8px 0;
                    text-align:center;
                    font-family:'Inter',sans-serif;
                    font-size:11.5px;
                    font-weight:600;
                    color:#9b6aad;
                    letter-spacing:.04em;
                    text-transform:uppercase;
                    {{ !$loop->last ? 'border-right:1px solid rgba(185,92,183,0.08);' : '' }}
                ">{{ $dow }}</div>
                @endforeach
            </div>

            {{-- Calendar day cells --}}
            @php
                // Build 42-cell array: null for blank, int for day number
                $cells = array_merge(
                    array_fill(0, $startOffset, null),
                    range(1, $daysInMonth),
                    array_fill(0, 42 - $daysInMonth - $startOffset, null)
                );
                // Group cells into weeks (rows of 7)
                $weeks = array_chunk($cells, 7);
            @endphp

            @foreach($weeks as $weekIndex => $week)
            <div style="
                display:grid;
                grid-template-columns:repeat(7,1fr);
                {{ !$loop->last ? 'border-bottom:1px solid rgba(185,92,183,0.08);' : '' }}
                min-height: 110px;
            ">
                @foreach($week as $colIndex => $day)
                @php
                    $isToday    = ($day === $todayDay);
                    $isWarnDay  = ($day !== null && in_array($day, $warnDays));
                    $dateKey    = $day ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
                    $dayPosts   = ($dateKey && isset($postsByDate[$dateKey])) ? $postsByDate[$dateKey] : collect();
                    $isBlank    = $day === null;
                    $isSunday   = ($colIndex === 0);
                    $isSaturday = ($colIndex === 6);
                @endphp
                <div
                    class="cal-day-cell"
                    style="
                        min-height: 110px;
                        padding: 6px 6px 4px;
                        position: relative;
                        background: {{ $isBlank ? '#faf5fb' : ($isToday ? '#fdf8ff' : '#fff') }};
                        {{ !$loop->last ? 'border-right:1px solid rgba(185,92,183,0.08);' : '' }}
                        {{ ($isSunday || $isSaturday) && !$isBlank ? 'background:#faf9fc;' : '' }}
                        cursor: {{ $isBlank ? 'default' : 'pointer' }};
                        overflow: hidden;
                    "
                    @if(!$isBlank)
                    onmouseover="this.querySelector('.create-btn') && (this.querySelector('.create-btn').style.opacity='1')"
                    onmouseout="this.querySelector('.create-btn') && (this.querySelector('.create-btn').style.opacity='0')"
                    @endif
                >
                    @if(!$isBlank)

                    {{-- Day number + optional warning --}}
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:4px;">
                        {{-- Day number --}}
                        <span style="
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            width: {{ $isToday ? '22px' : 'auto' }};
                            height: {{ $isToday ? '22px' : 'auto' }};
                            background: {{ $isToday ? 'linear-gradient(135deg,#6a0f70,#b95cb7)' : 'transparent' }};
                            border-radius: {{ $isToday ? '50%' : '0' }};
                            font-family: 'Inter', sans-serif;
                            font-size: 12px;
                            font-weight: {{ $isToday ? '700' : '500' }};
                            color: {{ $isToday ? '#fff' : (($isSunday||$isSaturday) ? '#b95cb7' : '#1e0a2c') }};
                        ">{{ $day }}</span>

                        {{-- Warning triangle for "missed posts" days --}}
                        @if($isWarnDay)
                        <span title="Posts may have been missed this week" style="
                            font-size:12px;
                            color:#f59e0b;
                            line-height:1;
                        "></span>
                        @endif
                    </div>

                    {{-- Post chips --}}
                    @foreach($dayPosts->take(3) as $post)
                    @php
                        $pMeta = $platformMeta[$post['platform']] ?? ['color'=>'#999','icon'=>'','label'=>$post['platform']];
                        $sMeta = $statusMeta[$post['status']]     ?? ['color'=>'#999','label'=>$post['status']];
                        // Blog entries (from CalendarController) carry a `url` and
                        // render as a link straight to the editor; social chips
                        // stay as plain (non-navigating) divs.
                        $chipUrl = $post['url'] ?? null;
                        $chipTag = $chipUrl ? 'a' : 'div';
                    @endphp
                    <{{ $chipTag }} @if($chipUrl) href="{{ $chipUrl }}" @endif style="
                        display: flex;
                        align-items: center;
                        gap: 5px;
                        background: #f9f3fa;
                        border-left: 3px solid {{ $pMeta['color'] }};
                        border-radius: 0 4px 4px 0;
                        padding: 3px 6px 3px 5px;
                        margin-bottom: 3px;
                        cursor: pointer;
                        text-decoration: none;
                        transition: background 100ms;
                        overflow: hidden;
                    " title="{{ ($pMeta['label'] ?? '') }} · {{ $post['title'] }} — {{ $post['time'] }}"
                       onmouseover="this.style.background='#f0e4f7'"
                       onmouseout="this.style.background='#f9f3fa'">
                        {{-- Platform icon (blog uses its own colour/icon — a distinct chip) --}}
                        <span style="color:{{ $pMeta['color'] }}; flex-shrink:0;">{!! $pMeta['icon'] !!}</span>
                        {{-- Title truncated --}}
                        <span style="
                            font-family:'Inter',sans-serif; font-size:10.5px;
                            font-weight:500; color:#1e0a2c;
                            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
                            flex:1;
                        ">{{ $post['title'] }}</span>
                        {{-- Time --}}
                        <span style="
                            font-family:'Inter',sans-serif; font-size:9.5px;
                            color:#9b6aad; flex-shrink:0;
                        ">{{ $post['time'] }}</span>
                    </{{ $chipTag }}>
                    @endforeach

                    {{-- "+N more" if more than 3 --}}
                    @if($dayPosts->count() > 3)
                    <div style="
                        font-family:'Inter',sans-serif; font-size:10px;
                        color:#9b6aad; padding:1px 5px; cursor:pointer;
                    ">+{{ $dayPosts->count() - 3 }} more</div>
                    @endif

                    {{-- Hover "+ Create" button on empty days --}}
                    @if($dayPosts->isEmpty())
                    <div class="create-btn" style="
                        opacity:0;
                        transition:opacity 150ms;
                        position:absolute;
                        bottom:6px; left:50%; transform:translateX(-50%);
                        font-family:'Inter',sans-serif; font-size:10.5px;
                        font-weight:600; color:#6a0f70;
                        background:#f5eef9;
                        border:1px dashed rgba(185,92,183,0.4);
                        border-radius:4px;
                        padding:3px 8px;
                        white-space:nowrap;
                        pointer-events:none;
                    ">+ Create</div>
                    @endif

                    @endif {{-- /!isBlank --}}
                </div>
                @endforeach
            </div>
            @endforeach

        </div>{{-- /month grid card --}}


        {{-- ══════════════════════════════════════════════
             WEEK VIEW
        ══════════════════════════════════════════════ --}}
        <div x-show="activeView === 'week'" x-cloak
             style="background:#fff; border:1px solid rgba(185,92,183,0.14); border-radius:8px; overflow:hidden; box-shadow:0 1px 4px rgba(106,15,112,0.06);">

            {{-- Sticky day-column headers --}}
            <div style="display:flex; border-bottom:2px solid rgba(185,92,183,0.12); background:#faf5fb; position:sticky; top:0; z-index:3;">
                <div style="width:58px; flex-shrink:0; border-right:1px solid rgba(185,92,183,0.08); padding:12px 0;"></div>
                <template x-for="(day, idx) in weekDays" :key="idx">
                    <div :style="{'flex':'1','padding':'10px 6px','text-align':'center','border-right': idx < 6 ? '1px solid rgba(185,92,183,0.08)' : 'none'}">
                        <div style="font-family:'Inter',sans-serif; font-size:10.5px; font-weight:600; color:#9b6aad; text-transform:uppercase; letter-spacing:.04em;" x-text="day.name"></div>
                        <div :style="{'margin':'4px auto 0','width':'30px','height':'30px','display':'flex','align-items':'center','justify-content':'center','border-radius':'50%','font-size':'16px','font-weight':'700','background': day.isToday ? 'linear-gradient(135deg,#6a0f70,#b95cb7)' : 'transparent','color': day.isToday ? '#fff' : '#1e0a2c'}"
                             x-text="day.date"></div>
                    </div>
                </template>
            </div>

            {{-- Scrollable time body --}}
            <div style="overflow-y:auto; max-height:560px;">
                <div style="display:flex;">

                    {{-- Time gutter --}}
                    <div style="width:58px; flex-shrink:0; border-right:1px solid rgba(185,92,183,0.08); background:#fdf9ff;">
                        <template x-for="hour in timeSlots" :key="hour">
                            <div style="height:60px; border-bottom:1px solid rgba(185,92,183,0.06); display:flex; align-items:flex-start; padding:5px 8px 0; box-sizing:border-box;">
                                <span style="font-family:'Inter',sans-serif; font-size:10px; color:#b695c8; white-space:nowrap;" x-text="formatHour(hour)"></span>
                            </div>
                        </template>
                    </div>

                    {{-- 7 day columns --}}
                    <div style="flex:1; display:grid; grid-template-columns:repeat(7,1fr);">
                        <template x-for="(day, dayIdx) in weekDays" :key="dayIdx">
                            {{-- column: keep position:relative inside :style object so Alpine doesn't wipe it --}}
                            <div :style="{'position':'relative','border-right': dayIdx < 6 ? '1px solid rgba(185,92,183,0.08)' : 'none','background': day.isToday ? 'rgba(106,15,112,0.018)' : 'transparent'}">

                                {{-- Hour lines via CSS background --}}
                                <div :style="`height:${timeSlots.length * 60}px; position:absolute; top:0; left:0; right:0; pointer-events:none; background-image:repeating-linear-gradient(to bottom, transparent, transparent 59px, rgba(185,92,183,0.07) 59px, rgba(185,92,183,0.07) 60px);`"></div>

                                {{-- Spacer for column height --}}
                                <div :style="`height:${timeSlots.length * 60}px;`"></div>

                                {{-- Current time indicator (today only) --}}
                                <div x-show="day.isToday"
                                     :style="{'position':'absolute','left':'0','right':'0','top': currentTimeTop + 'px','z-index':'4','pointer-events':'none'}">
                                    <div style="height:2px; background:#ef4444; position:relative;">
                                        <div style="width:8px; height:8px; background:#ef4444; border-radius:50%; position:absolute; left:-4px; top:-3px;"></div>
                                    </div>
                                </div>

                                {{-- Post blocks --}}
                                <template x-for="post in getPostsForDay(dayIdx)" :key="post.id">
                                    <div @click="openPanel(post)"
                                         :style="{'position':'absolute','top':(post.hour-8)*60+post.min+'px','background':post.color+'20','border-left':'3px solid '+post.color,'left':'2px','right':'2px','border-radius':'0 4px 4px 0','padding':'4px 6px','cursor':'pointer','z-index':'2','box-sizing':'border-box','min-height':'26px'}"
                                         onmouseover="this.style.filter='brightness(0.9)'"
                                         onmouseout="this.style.filter='brightness(1)'">
                                        <div :style="`color:${post.color}; font-size:9.5px; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;`" x-text="post.title"></div>
                                        <div style="font-size:9px; color:#7a6884;" x-text="formatPostTime(post)"></div>
                                    </div>
                                </template>

                            </div>
                        </template>
                    </div>

                </div>
            </div>
        </div>{{-- /week view --}}


        {{-- ══════════════════════════════════════════════
             LIST VIEW
        ══════════════════════════════════════════════ --}}
        <div x-show="activeView === 'list'" x-cloak
             style="background:#fff; border:1px solid rgba(185,92,183,0.14); border-radius:8px; overflow:hidden; box-shadow:0 1px 4px rgba(106,15,112,0.06); overflow-x:auto;">

            {{-- Column header bar --}}
            <div style="display:grid; grid-template-columns:130px 82px 116px 110px 1fr 128px 114px 92px; background:#faf5fb; border-bottom:2px solid rgba(185,92,183,0.12); min-width:860px;">
                @foreach(['Date','Time','Platform','Type','Title','Campaign','Status','Actions'] as $col)
                <div style="padding:10px 12px; font-family:'Inter',sans-serif; font-size:10.5px; font-weight:700; color:#9b6aad; text-transform:uppercase; letter-spacing:.05em; border-right:1px solid rgba(185,92,183,0.07);">{{ $col }}</div>
                @endforeach
            </div>

            {{-- Week groups --}}
            <template x-for="(week, wIdx) in listWeeks" :key="wIdx">
                <div style="min-width:860px;">

                    {{-- Week expander header --}}
                    <div @click="week.expanded = !week.expanded"
                         style="display:flex; align-items:center; gap:8px; padding:9px 14px; background:#f5eef9; border-bottom:1px solid rgba(185,92,183,0.1); cursor:pointer; user-select:none;"
                         onmouseover="this.style.background='#eeddf6'" onmouseout="this.style.background='#f5eef9'">
                        <svg :style="{'width':'12px','height':'12px','flex-shrink':'0','transition':'transform 150ms','transform': week.expanded ? 'rotate(90deg)' : 'none'}"
                             viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"/>
                        </svg>
                        <span style="font-family:'Inter',sans-serif; font-size:12.5px; font-weight:700; color:#3d2450;" x-text="week.label"></span>
                        <span style="font-family:'Inter',sans-serif; font-size:11px; color:#9b6aad; background:#e8d5f5; border-radius:10px; padding:2px 8px; margin-left:2px;" x-text="week.posts.length + ' posts'"></span>
                    </div>

                    {{-- Post rows (visible when expanded) --}}
                    <template x-if="week.expanded">
                        <div>
                            <template x-for="(post, pIdx) in week.posts" :key="post.id">
                                <div x-data="{hovered:false}"
                                     @mouseenter="hovered=true" @mouseleave="hovered=false"
                                     :style="{'display':'grid','grid-template-columns':'130px 82px 116px 110px 1fr 128px 114px 92px','border-bottom':'1px solid rgba(185,92,183,0.06)','align-items':'center','transition':'background 80ms','background': hovered ? '#fdf8ff' : (pIdx % 2 === 0 ? '#fff' : '#fdfbff')}">

                                    {{-- Date --}}
                                    <div style="padding:10px 12px; font-family:'Inter',sans-serif; font-size:12px; color:#3d2450; font-weight:500; border-right:1px solid rgba(185,92,183,0.05); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" x-text="post.dateLabel"></div>

                                    {{-- Time --}}
                                    <div style="padding:10px 12px; font-family:'Inter',sans-serif; font-size:12px; color:#5a4868; border-right:1px solid rgba(185,92,183,0.05); white-space:nowrap;" x-text="post.time"></div>

                                    {{-- Platform badge --}}
                                    <div style="padding:10px 12px; border-right:1px solid rgba(185,92,183,0.05);">
                                        <span :style="{'background': post.platformColor+'18','color': post.platformColor,'border': '1.5px solid '+post.platformColor+'55'}"
                                              style="font-family:'Inter',sans-serif; font-size:10.5px; font-weight:600; padding:3px 9px; border-radius:20px; white-space:nowrap;" x-text="post.platform"></span>
                                    </div>

                                    {{-- Content type badge --}}
                                    <div style="padding:10px 12px; border-right:1px solid rgba(185,92,183,0.05);">
                                        <span style="background:#f5eef9; color:#6a0f70; border:1.5px solid rgba(185,92,183,0.3); font-family:'Inter',sans-serif; font-size:10.5px; font-weight:500; padding:3px 9px; border-radius:20px; white-space:nowrap;" x-text="post.contentType"></span>
                                    </div>

                                    {{-- Title --}}
                                    <div style="padding:10px 12px; font-family:'Inter',sans-serif; font-size:12.5px; color:#1e0a2c; font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; border-right:1px solid rgba(185,92,183,0.05);" x-text="post.title"></div>

                                    {{-- Campaign --}}
                                    <div style="padding:10px 12px; font-family:'Inter',sans-serif; font-size:12px; color:#5a4868; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; border-right:1px solid rgba(185,92,183,0.05);" x-text="post.campaign"></div>

                                    {{-- Status badge --}}
                                    <div style="padding:10px 12px; border-right:1px solid rgba(185,92,183,0.05);">
                                        <span :style="{'background': getStatusColor(post.status)+'18','color': getStatusColor(post.status),'border': '1.5px solid '+getStatusColor(post.status)+'55'}"
                                              style="font-family:'Inter',sans-serif; font-size:10.5px; font-weight:600; padding:3px 9px; border-radius:20px; white-space:nowrap;"
                                              x-text="post.status === 'pending_approval' ? 'Pending' : (post.status.charAt(0).toUpperCase() + post.status.slice(1))"></span>
                                    </div>

                                    {{-- Actions (fade in on row hover) --}}
                                    <div style="padding:10px 12px; display:flex; align-items:center; gap:4px;">
                                        {{-- View --}}
                                        <button @click="openListPostPanel(post)" title="View"
                                                :style="{'opacity': hovered ? 1 : 0.25}"
                                                style="border:none; background:none; cursor:pointer; color:#6a0f70; transition:opacity 100ms; padding:4px; border-radius:4px;"
                                                onmouseover="this.style.background='#f5eef9'" onmouseout="this.style.background='none'">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </button>
                                        {{-- Edit --}}
                                        <button title="Edit"
                                                :style="{'opacity': hovered ? 1 : 0.25}"
                                                style="border:none; background:none; cursor:pointer; color:#0ea5e9; transition:opacity 100ms; padding:4px; border-radius:4px;"
                                                onmouseover="this.style.background='#f0f9ff'" onmouseout="this.style.background='none'">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </button>
                                        {{-- Delete --}}
                                        <button title="Delete"
                                                :style="{'opacity': hovered ? 1 : 0.25}"
                                                style="border:none; background:none; cursor:pointer; color:#ef4444; transition:opacity 100ms; padding:4px; border-radius:4px;"
                                                onmouseover="this.style.background='#fff5f5'" onmouseout="this.style.background='none'">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                                        </button>
                                    </div>

                                </div>
                            </template>
                        </div>
                    </template>

                </div>
            </template>

        </div>{{-- /list view --}}


        {{-- ════════════════════════════════════════════
             LEGEND BAR
        ════════════════════════════════════════════ --}}
        <div style="
            display: flex;
            align-items: center;
            gap: 18px;
            background: #fff;
            border: 1px solid rgba(185,92,183,0.12);
            border-radius: 8px;
            padding: 10px 18px;
            margin-top: 12px;
            flex-wrap: wrap;
            box-shadow: 0 1px 4px rgba(106,15,112,0.04);
        ">
            <span style="
                font-family:'Inter',sans-serif; font-size:11px;
                font-weight:700; color:#9b6aad; text-transform:uppercase;
                letter-spacing:.06em; flex-shrink:0;
            ">Legend:</span>

            @php
                $legendItems = [
                    ['color'=>'#0ea5e9', 'label'=>'Scheduled',       'filled'=>true],
                    ['color'=>'#16a34a', 'label'=>'Published',        'filled'=>true],
                    ['color'=>'#94a3b8', 'label'=>'Draft',            'filled'=>true],
                    ['color'=>'#f59e0b', 'label'=>'Pending Approval', 'filled'=>true],
                    ['color'=>'#ef4444', 'label'=>'Failed',           'filled'=>true],
                    ['color'=>'#6366f1', 'label'=>'Blog',             'filled'=>true],
                    ['color'=>'#d8c4e0', 'label'=>'No Content',       'filled'=>false],
                ];
            @endphp

            @foreach($legendItems as $item)
            <div style="display:flex; align-items:center; gap:5px; flex-shrink:0;">
                @if($item['filled'])
                <span style="
                    width:8px; height:8px; border-radius:50%;
                    background:{{ $item['color'] }}; flex-shrink:0;
                "></span>
                @else
                <span style="
                    width:8px; height:8px; border-radius:50%;
                    border:1.5px solid {{ $item['color'] }}; flex-shrink:0;
                "></span>
                @endif
                <span style="
                    font-family:'Inter',sans-serif; font-size:12px;
                    color:#5a4868;
                ">{{ $item['label'] }}</span>
            </div>
            @endforeach
        </div>{{-- /legend --}}

    </div>{{-- /main area --}}

</div>{{-- /body flex --}}


{{-- ══════════════════════════════════════════════════════
     POST DETAIL SLIDE-OUT PANEL
════════════════════════════════════════════════════════ --}}

{{-- Dim overlay --}}
<div x-show="panel.open"
     @click="closePanel()"
     x-transition:enter="df-fade-t"
     x-transition:enter-start="df-fade-hidden"
     x-transition:enter-end=""
     x-transition:leave="df-fade-t"
     x-transition:leave-start=""
     x-transition:leave-end="df-fade-hidden"
     style="position:fixed; inset:0; z-index:40; background:rgba(30,10,44,0.28);">
</div>

{{-- Panel drawer --}}
<div x-show="panel.open"
     @click.stop
     x-transition:enter="df-slide-t"
     x-transition:enter-start="df-slide-hidden"
     x-transition:enter-end=""
     x-transition:leave="df-slide-t"
     x-transition:leave-start=""
     x-transition:leave-end="df-slide-hidden"
     style="position:fixed; right:0; top:0; height:100vh; width:388px; background:#fff; z-index:41; box-shadow:-4px 0 32px rgba(106,15,112,0.16); overflow-y:auto; padding:24px; box-sizing:border-box;">

    {{-- Header row: badges + close button --}}
    <div style="display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:18px;">
        <div style="display:flex; gap:6px; flex-wrap:wrap; padding-top:2px;">
            {{-- Platform badge --}}
            <span :style="{'background': (panel.post?.color||'#999')+'18','color': panel.post?.color||'#999','border': '1.5px solid '+(panel.post?.color||'#999')+'55'}"
                  style="font-family:'Inter',sans-serif; font-size:11px; font-weight:600; padding:3px 10px; border-radius:20px; text-transform:capitalize;"
                  x-text="panel.post?.platform || ''"></span>
            {{-- Content type badge --}}
            <span style="background:#f5eef9; color:#6a0f70; border:1.5px solid rgba(185,92,183,0.3); font-family:'Inter',sans-serif; font-size:11px; font-weight:500; padding:3px 10px; border-radius:20px;"
                  x-text="panel.post?.contentType || ''"></span>
            {{-- Status badge --}}
            <span :style="{'background': getStatusColor(panel.post?.status)+'18','color': getStatusColor(panel.post?.status),'border': '1.5px solid '+getStatusColor(panel.post?.status)+'55'}"
                  style="font-family:'Inter',sans-serif; font-size:11px; font-weight:600; padding:3px 10px; border-radius:20px;"
                  x-text="panel.post?.status ? (panel.post.status === 'pending_approval' ? 'Pending Approval' : panel.post.status.charAt(0).toUpperCase() + panel.post.status.slice(1)) : ''"></span>
        </div>
        {{-- × close --}}
        <button @click="closePanel()"
                style="border:none; background:#f5eef9; cursor:pointer; color:#6a0f70; width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:18px; line-height:1; flex-shrink:0;"
                onmouseover="this.style.background='#eddcf5'" onmouseout="this.style.background='#f5eef9'">×</button>
    </div>

    {{-- Title --}}
    <h3 style="font-family:'Inter',sans-serif; font-size:17px; font-weight:700; color:#1e0a2c; margin:0 0 16px; line-height:1.3;" x-text="panel.post?.title || ''"></h3>

    {{-- Content preview --}}
    <div style="background:#faf5fb; border:1px solid rgba(185,92,183,0.12); border-radius:8px; padding:14px; margin-bottom:20px;">
        <div style="font-family:'Inter',sans-serif; font-size:10.5px; font-weight:700; color:#9b6aad; text-transform:uppercase; letter-spacing:.06em; margin-bottom:8px;">Content Preview</div>
        <p style="font-family:'Inter',sans-serif; font-size:12.5px; color:#3d2450; margin:0; line-height:1.6;"
           x-text="panel.post?.preview ? panel.post.preview.substring(0, 160) + (panel.post.preview.length > 160 ? '…' : '') : 'No preview available.'"></p>
    </div>

    {{-- Meta rows --}}
    <div style="display:flex; flex-direction:column; gap:15px; margin-bottom:22px;">

        {{-- Scheduled --}}
        <div style="display:flex; align-items:flex-start; gap:12px;">
            <div style="width:32px; height:32px; background:#f0e8fa; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div>
                <div style="font-family:'Inter',sans-serif; font-size:11px; color:#9b6aad; margin-bottom:2px;">Scheduled</div>
                <div style="font-family:'Inter',sans-serif; font-size:13px; font-weight:500; color:#1e0a2c;" x-text="panel.post?.scheduledLabel || '—'"></div>
            </div>
        </div>

        {{-- Campaign --}}
        <div style="display:flex; align-items:flex-start; gap:12px;">
            <div style="width:32px; height:32px; background:#f0e8fa; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
            <div>
                <div style="font-family:'Inter',sans-serif; font-size:11px; color:#9b6aad; margin-bottom:4px;">Campaign</div>
                <div style="display:flex; align-items:center; gap:6px;">
                    <span :style="{'background': panel.post?.color || '#6a0f70'}" style="width:8px; height:8px; border-radius:50%; flex-shrink:0;"></span>
                    <span style="font-family:'Inter',sans-serif; font-size:13px; font-weight:500; color:#1e0a2c;" x-text="panel.post?.campaign || '—'"></span>
                </div>
            </div>
        </div>

        {{-- Created by --}}
        <div style="display:flex; align-items:flex-start; gap:12px;">
            <div style="width:32px; height:32px; background:#f0e8fa; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <div>
                <div style="font-family:'Inter',sans-serif; font-size:11px; color:#9b6aad; margin-bottom:4px;">Created by</div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <div style="width:26px; height:26px; background:linear-gradient(135deg,#6a0f70,#b95cb7); border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-family:'Inter',sans-serif; font-size:11px; font-weight:700; flex-shrink:0;">S</div>
                    <span style="font-family:'Inter',sans-serif; font-size:13px; font-weight:500; color:#1e0a2c;">Sumit</span>
                </div>
            </div>
        </div>

    </div>

    {{-- Divider --}}
    <div style="border-top:1px solid rgba(185,92,183,0.12); margin-bottom:18px;"></div>

    {{-- Action buttons --}}
    <div style="display:flex; flex-direction:column; gap:8px;">
        {{-- Edit --}}
        <button style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:10px;background:linear-gradient(135deg,#6a0f70,#b95cb7);color:#fff;font-family:'Inter',sans-serif;font-size:13px;font-weight:600;border:none;border-radius:7px;cursor:pointer;"
                onmouseover="this.style.opacity='0.88'" onmouseout="this.style.opacity='1'">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit Post
        </button>
        {{-- Reschedule --}}
        <button style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:10px;background:#fff;color:#6a0f70;font-family:'Inter',sans-serif;font-size:13px;font-weight:600;border:1.5px solid rgba(185,92,183,0.35);border-radius:7px;cursor:pointer;"
                onmouseover="this.style.background='#faf3fb'" onmouseout="this.style.background='#fff'">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>
            Reschedule
        </button>
        {{-- Publish Now --}}
        <button style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:10px;background:#f0faf4;color:#16a34a;font-family:'Inter',sans-serif;font-size:13px;font-weight:600;border:1.5px solid rgba(22,163,74,0.3);border-radius:7px;cursor:pointer;"
                onmouseover="this.style.background='#dcf5e5'" onmouseout="this.style.background='#f0faf4'">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            Publish Now
        </button>
        {{-- Delete --}}
        <button style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:10px;background:#fff5f5;color:#ef4444;font-family:'Inter',sans-serif;font-size:13px;font-weight:600;border:1.5px solid rgba(239,68,68,0.25);border-radius:7px;cursor:pointer;"
                onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fff5f5'">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
            Delete
        </button>
    </div>

</div>{{-- /detail panel --}}

</div>{{-- /calendarApp Alpine wrapper --}}


<script>
function calendarApp() {
    return {
        activeView: 'month',

        panel: { open: false, post: null },

        // ── Week view data ─────────────────────────────────────
        weekDays: [
            { name:'Sun', date:14, isToday:true  },
            { name:'Mon', date:15, isToday:false },
            { name:'Tue', date:16, isToday:false },
            { name:'Wed', date:17, isToday:false },
            { name:'Thu', date:18, isToday:false },
            { name:'Fri', date:19, isToday:false },
            { name:'Sat', date:20, isToday:false },
        ],

        // Hours rendered: 8 AM → 10 PM
        timeSlots: [8,9,10,11,12,13,14,15,16,17,18,19,20,21,22],

        // Pixels from top of time grid for red current-time line
        currentTimeTop: 0,

        // Mock posts for week view
        weekPosts: [
            { id:1,  day:0, hour:9,  min:0,  platform:'Instagram', contentType:'Reel',     status:'scheduled',        color:'#e1306c', title:'Summer Smile Campaign',        campaign:'Summer Promo',  scheduledDate:'Jun 14, 2026', scheduledTime:'9:00 AM',  preview:'Get ready for summer with our amazing smile makeover deals! Book your consultation today and save 20% on all cosmetic procedures this month.' },
            { id:2,  day:0, hour:14, min:0,  platform:'Facebook',  contentType:'Post',     status:'published',        color:'#1877f2', title:'Patient Testimonial: Dr. Mehta', campaign:'Brand Trust', scheduledDate:'Jun 14, 2026', scheduledTime:'2:00 PM',  preview:'\"I was terrified of dentists until I visited Dentfluence. Dr. Mehta made me feel completely at ease and my smile has never looked better!\"' },
            { id:3,  day:1, hour:10, min:30, platform:'Instagram', contentType:'Story',    status:'scheduled',        color:'#e1306c', title:'Behind the Scenes: Lab Tour',  campaign:'Clinic Life',   scheduledDate:'Jun 15, 2026', scheduledTime:'10:30 AM', preview:'Take a peek inside our state-of-the-art dental laboratory! See how your custom veneers and crowns are crafted with precision.' },
            { id:4,  day:2, hour:9,  min:0,  platform:'Blog',      contentType:'Blog',     status:'draft',            color:'#6366f1', title:'Top 5 Tips for Cavity Prevention', campaign:'Education', scheduledDate:'Jun 16, 2026', scheduledTime:'9:00 AM',  preview:'Cavities are one of the most common dental problems, but they are largely preventable. Here are our top five evidence-based tips to keep...' },
            { id:5,  day:2, hour:16, min:0,  platform:'Google',    contentType:'Post',     status:'scheduled',        color:'#4285f4', title:'GMB Update: New Services',     campaign:'Local SEO',     scheduledDate:'Jun 16, 2026', scheduledTime:'4:00 PM',  preview:'Exciting news! Dentfluence now offers same-day crowns using our CEREC technology. Walk in with a damaged tooth and leave with a permanent crown.' },
            { id:6,  day:3, hour:11, min:0,  platform:'WhatsApp',  contentType:'Post',     status:'pending_approval', color:'#25d366', title:'Appointment Reminder Blast',   campaign:'Recall',        scheduledDate:'Jun 17, 2026', scheduledTime:'11:00 AM', preview:'Dear patient, this is a friendly reminder that your dental check-up is due. Please confirm your appointment or call us to reschedule.' },
            { id:7,  day:3, hour:18, min:0,  platform:'Instagram', contentType:'Carousel', status:'scheduled',        color:'#e1306c', title:'Whitening Wednesday Offer',    campaign:'Offers',        scheduledDate:'Jun 17, 2026', scheduledTime:'6:00 PM',  preview:'✨ WHITENING WEDNESDAY is here! Swipe to see our before & after gallery. For this week only, professional teeth whitening at a special price.' },
            { id:8,  day:4, hour:8,  min:0,  platform:'Facebook',  contentType:'Post',     status:'scheduled',        color:'#1877f2', title:'New Patient Welcome Post',     campaign:'Acquisition',   scheduledDate:'Jun 18, 2026', scheduledTime:'8:00 AM',  preview:'Welcome to the Dentfluence family! Whether you\'re new to the area or looking for a fresh start with your dental care, we\'re here for you.' },
            { id:9,  day:5, hour:10, min:0,  platform:'Instagram', contentType:'Reel',     status:'scheduled',        color:'#e1306c', title:'Friday Feel-Good: Patient Smile', campaign:'Brand Trust', scheduledDate:'Jun 19, 2026', scheduledTime:'10:00 AM', preview:'Nothing makes our Friday better than seeing this incredible smile transformation! Watch our full patient journey video on the link in bio.' },
            { id:10, day:6, hour:12, min:0,  platform:'Blog',      contentType:'Blog',     status:'draft',            color:'#6366f1', title:'Understanding Dental X-Rays',  campaign:'Education',     scheduledDate:'Jun 20, 2026', scheduledTime:'12:00 PM', preview:'Many patients wonder what dental X-rays show and whether they\'re safe. In this comprehensive guide, we explain the different types and their purpose.' },
        ],

        // ── List view data ─────────────────────────────────────
        listWeeks: [
            {
                label: 'Week of Jun 1 – 7',
                expanded: false,
                posts: [
                    { id:101, dateLabel:'Mon, Jun 1', time:'9:00 AM',  platform:'Instagram', platformColor:'#e1306c', contentType:'Reel',     title:'Clinic Opening Announcement',  campaign:'Brand Launch', status:'published' },
                    { id:102, dateLabel:'Tue, Jun 2', time:'2:00 PM',  platform:'Facebook',  platformColor:'#1877f2', contentType:'Post',     title:'Meet Our Team',                campaign:'Brand Trust',  status:'published' },
                    { id:103, dateLabel:'Thu, Jun 4', time:'10:00 AM', platform:'Instagram', platformColor:'#e1306c', contentType:'Carousel', title:'Before & After Gallery',       campaign:'Results',      status:'published' },
                    { id:104, dateLabel:'Fri, Jun 5', time:'9:00 AM',  platform:'Blog',      platformColor:'#6366f1', contentType:'Blog',     title:'Welcome to Dentfluence Blog',  campaign:'Education',    status:'published' },
                ],
            },
            {
                label: 'Week of Jun 8 – 13',
                expanded: false,
                posts: [
                    { id:105, dateLabel:'Mon, Jun 8',  time:'9:00 AM',  platform:'Instagram', platformColor:'#e1306c', contentType:'Reel',  title:'Monday Motivation: Smile Goals', campaign:'Brand Trust', status:'published'        },
                    { id:106, dateLabel:'Tue, Jun 9',  time:'11:00 AM', platform:'Google',    platformColor:'#4285f4', contentType:'Post',  title:'Extended Hours Announcement',    campaign:'Local SEO',   status:'published'        },
                    { id:107, dateLabel:'Wed, Jun 10', time:'3:00 PM',  platform:'WhatsApp',  platformColor:'#25d366', contentType:'Post',  title:'June Check-up Reminder',         campaign:'Recall',      status:'failed'           },
                    { id:108, dateLabel:'Thu, Jun 11', time:'10:00 AM', platform:'Facebook',  platformColor:'#1877f2', contentType:'Post',  title:'Patient of the Month Feature',   campaign:'Brand Trust', status:'published'        },
                    { id:109, dateLabel:'Fri, Jun 12', time:'9:00 AM',  platform:'Instagram', platformColor:'#e1306c', contentType:'Reel',  title:'Weekend Smile Tips Reel',        campaign:'Education',   status:'scheduled'        },
                ],
            },
            {
                label: 'Week of Jun 14 – 20',
                expanded: true,
                posts: [
                    { id:110, dateLabel:'Sun, Jun 14', time:'9:00 AM',  platform:'Instagram', platformColor:'#e1306c', contentType:'Reel',     title:'Summer Smile Campaign',          campaign:'Summer Promo', status:'scheduled'        },
                    { id:111, dateLabel:'Sun, Jun 14', time:'2:00 PM',  platform:'Facebook',  platformColor:'#1877f2', contentType:'Post',     title:'Patient Testimonial: Dr. Mehta', campaign:'Brand Trust',  status:'published'        },
                    { id:112, dateLabel:'Mon, Jun 15', time:'10:30 AM', platform:'Instagram', platformColor:'#e1306c', contentType:'Story',    title:'Behind the Scenes: Lab Tour',    campaign:'Clinic Life',  status:'scheduled'        },
                    { id:113, dateLabel:'Tue, Jun 16', time:'9:00 AM',  platform:'Blog',      platformColor:'#6366f1', contentType:'Blog',     title:'Top 5 Tips for Cavity Prevention', campaign:'Education', status:'draft'            },
                    { id:114, dateLabel:'Tue, Jun 16', time:'4:00 PM',  platform:'Google',    platformColor:'#4285f4', contentType:'Post',     title:'GMB Update: New Services',       campaign:'Local SEO',    status:'scheduled'        },
                    { id:115, dateLabel:'Wed, Jun 17', time:'11:00 AM', platform:'WhatsApp',  platformColor:'#25d366', contentType:'Post',     title:'Appointment Reminder Blast',     campaign:'Recall',       status:'pending_approval' },
                    { id:116, dateLabel:'Wed, Jun 17', time:'6:00 PM',  platform:'Instagram', platformColor:'#e1306c', contentType:'Carousel', title:'Whitening Wednesday Offer',      campaign:'Offers',       status:'scheduled'        },
                    { id:117, dateLabel:'Thu, Jun 18', time:'8:00 AM',  platform:'Facebook',  platformColor:'#1877f2', contentType:'Post',     title:'New Patient Welcome Post',       campaign:'Acquisition',  status:'scheduled'        },
                    { id:118, dateLabel:'Fri, Jun 19', time:'10:00 AM', platform:'Instagram', platformColor:'#e1306c', contentType:'Reel',     title:'Friday Feel-Good: Patient Smile', campaign:'Brand Trust', status:'scheduled'       },
                    { id:119, dateLabel:'Sat, Jun 20', time:'12:00 PM', platform:'Blog',      platformColor:'#6366f1', contentType:'Blog',     title:'Understanding Dental X-Rays',    campaign:'Education',    status:'draft'            },
                ],
            },
            {
                label: 'Week of Jun 21 – 27',
                expanded: false,
                posts: [
                    { id:120, dateLabel:'Mon, Jun 23', time:'9:00 AM',  platform:'Instagram', platformColor:'#e1306c', contentType:'Reel',     title:'Summer Campaign Continues',  campaign:'Summer Promo', status:'scheduled' },
                    { id:121, dateLabel:'Wed, Jun 25', time:'2:00 PM',  platform:'Facebook',  platformColor:'#1877f2', contentType:'Carousel', title:'Top Treatment FAQs',         campaign:'Education',    status:'draft'     },
                    { id:122, dateLabel:'Fri, Jun 27', time:'10:00 AM', platform:'Instagram', platformColor:'#e1306c', contentType:'Post',     title:'End of Week Smile Check',    campaign:'Brand Trust',  status:'draft'     },
                ],
            },
        ],

        // ── Status colour map ─────────────────────────────────
        statusColors: {
            'published':        '#16a34a',
            'scheduled':        '#0ea5e9',
            'draft':            '#94a3b8',
            'pending_approval': '#f59e0b',
            'failed':           '#ef4444',
        },

        // ── Lifecycle ─────────────────────────────────────────
        init() {
            this.computeCurrentTimeTop();
            // Refresh every minute
            setInterval(() => this.computeCurrentTimeTop(), 60000);
        },

        computeCurrentTimeTop() {
            const now  = new Date();
            const h    = now.getHours();
            const m    = now.getMinutes();
            // Grid starts at 8 AM. If outside range, park indicator off-screen.
            this.currentTimeTop = (h >= 8 && h <= 22) ? (h - 8) * 60 + m : -20;
        },

        // ── Helpers ───────────────────────────────────────────
        getPostsForDay(dayIdx) {
            return this.weekPosts.filter(p => p.day === dayIdx);
        },

        formatHour(h) {
            if (h === 12) return '12 PM';
            return h < 12 ? h + ' AM' : (h - 12) + ' PM';
        },

        formatPostTime(post) {
            if (!post) return '';
            const h     = post.hour;
            const m     = post.min;
            const label = h >= 12 ? 'PM' : 'AM';
            const dh    = h > 12 ? h - 12 : (h === 0 ? 12 : h);
            return dh + ':' + (m < 10 ? '0' + m : m) + ' ' + label;
        },

        getStatusColor(status) {
            return this.statusColors[status] || '#94a3b8';
        },

        // ── Panel ─────────────────────────────────────────────
        openPanel(post) {
            this.panel.post = {
                ...post,
                scheduledLabel: (post.scheduledDate || '') + ' at ' + (post.scheduledTime || this.formatPostTime(post)) + ' IST',
            };
            this.panel.open = true;
        },

        openListPostPanel(post) {
            this.panel.post = {
                ...post,
                color:          post.platformColor,
                contentType:    post.contentType,
                scheduledLabel: post.dateLabel + ' at ' + post.time + ' IST',
                preview:        'No content preview available in list view. Open the post editor to see the full draft.',
            };
            this.panel.open = true;
        },

        closePanel() {
            this.panel.open = false;
        },
    };
}
</script>

@endsection
