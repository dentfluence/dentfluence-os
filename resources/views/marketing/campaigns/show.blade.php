{{--
|==========================================================================
| Marketing — Campaign Show Page (Phase 2.3-B, Part A)
| File: resources/views/marketing/campaigns/show.blade.php
|
| Sections:
|   1. Campaign Header  — breadcrumb, name, status, meta pills, action buttons
|   2. Campaign Sub-tabs — 7 tabs (Overview active by default)
|   3. Two-column layout — main content (flex:1) + right sidebar (280px)
|
| Overview tab content lives in:
|   partials/_overview-tab.blade.php  (built in Part B)
| Right sidebar content:
|   partials/_sidebar.blade.php       (built in Part B)
|==========================================================================
--}}
@extends('marketing.layouts.app')

@php $marketingPageTitle = $campaign['name']; @endphp
@section('page-title', 'Marketing — ' . $campaign['name'])

@section('marketing-content')

{{-- Alpine.js scope: activeTab drives all tab panels --}}
<div x-data="{ activeTab: 'overview', showDropdown: false }">

{{-- ══════════════════════════════════════════════════════════════
     1. CAMPAIGN HEADER CARD
     Sits flush at the top, no outer card wrapper (uses its own
     white block so we can have the accent left-border look).
══════════════════════════════════════════════════════════════ --}}
<div style="
    background: #ffffff;
    border: 1px solid rgba(185,92,183,0.14);
    border-radius: 10px;
    padding: 22px 26px 18px;
    margin-bottom: 20px;
    box-shadow: 0 1px 4px rgba(106,15,112,0.05);
">

    {{-- ── BREADCRUMB ─────────────────────────────────────────── --}}
    <nav style="display:flex; align-items:center; gap:6px; margin-bottom:14px;">
        <a href="{{ route('marketing.campaigns.index') }}" style="
            font-family:'Inter',sans-serif; font-size:12px; color:#9b6aad;
            text-decoration:none; display:inline-flex; align-items:center; gap:4px;
        ">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
            </svg>
            Campaigns
        </a>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#c9b0d4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="9 18 15 12 9 6"/>
        </svg>
        <span style="font-family:'Inter',sans-serif; font-size:12px; color:#5a4868; font-weight:500;">
            {{ $campaign['name'] }}
        </span>
    </nav>

    {{-- ── ROW 1: NAME + STATUS BADGE + ACTION BUTTONS ──────── --}}
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:6px;">

        {{-- Left: name + badge --}}
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <h1 style="
                font-family:'Cormorant Garamond',serif;
                font-size:26px; font-weight:700;
                color:#1e0a2c; margin:0; line-height:1.2;
            ">{{ $campaign['name'] }}</h1>

            {{-- Status badge — Running = green --}}
            @php
                $statusColors = [
                    'Running' => ['bg' => '#e6f9ee', 'text' => '#1a7a3c', 'dot' => '#2ecc71'],
                    'Active'  => ['bg' => '#e6f9ee', 'text' => '#1a7a3c', 'dot' => '#2ecc71'],
                    'Paused'  => ['bg' => '#fff7e6', 'text' => '#8a5c00', 'dot' => '#f39c12'],
                    'Draft'   => ['bg' => '#f2f2f2', 'text' => '#555555', 'dot' => '#aaaaaa'],
                    'Ended'   => ['bg' => '#fdecea', 'text' => '#8a1a1a', 'dot' => '#e74c3c'],
                ];
                $sc = $statusColors[$campaign['status']] ?? $statusColors['Draft'];
            @endphp
            <span style="
                display:inline-flex; align-items:center; gap:5px;
                background:{{ $sc['bg'] }}; color:{{ $sc['text'] }};
                font-family:'Inter',sans-serif; font-size:11.5px; font-weight:600;
                padding:3px 10px; border-radius:20px; letter-spacing:0.02em;
            ">
                <span style="width:6px;height:6px;border-radius:50%;background:{{ $sc['dot'] }};display:inline-block;"></span>
                {{ $campaign['status'] }}
            </span>
        </div>

        {{-- Right: action buttons --}}
        <div style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
            {{-- Share --}}
            <button style="
                display:inline-flex; align-items:center; gap:6px;
                padding:7px 14px; border-radius:6px;
                background:#f9f3fa; border:1px solid rgba(185,92,183,0.22);
                font-family:'Inter',sans-serif; font-size:12.5px; font-weight:500;
                color:#6a0f70; cursor:pointer;
            "
            onmouseover="this.style.background='#f0e4f4'"
            onmouseout="this.style.background='#f9f3fa'"
            >
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
                    <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                </svg>
                Share
            </button>

            {{-- Export Report --}}
            <button style="
                display:inline-flex; align-items:center; gap:6px;
                padding:7px 14px; border-radius:6px;
                background:linear-gradient(135deg,#6a0f70 0%,#b95cb7 100%);
                border:none;
                font-family:'Inter',sans-serif; font-size:12.5px; font-weight:500;
                color:#ffffff; cursor:pointer;
            "
            onmouseover="this.style.opacity='0.88'"
            onmouseout="this.style.opacity='1'"
            >
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Export Report
            </button>

            {{-- More (…) --}}
            <div style="position:relative;">
                <button
                    @click="showDropdown = !showDropdown"
                    style="
                        display:inline-flex; align-items:center; justify-content:center;
                        width:34px; height:34px; border-radius:6px;
                        background:#f9f3fa; border:1px solid rgba(185,92,183,0.22);
                        cursor:pointer; color:#6a0f70;
                    "
                    onmouseover="this.style.background='#f0e4f4'"
                    onmouseout="this.style.background='#f9f3fa'"
                >
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor">
                        <circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/>
                    </svg>
                </button>
                <div
                    x-show="showDropdown"
                    @click.away="showDropdown = false"
                    x-transition
                    style="
                        position:absolute; top:calc(100% + 6px); right:0; z-index:50;
                        background:#fff; border:1px solid rgba(185,92,183,0.15);
                        border-radius:8px; box-shadow:0 4px 16px rgba(106,15,112,0.12);
                        min-width:160px; padding:6px 0;
                    "
                >
                    @foreach(['Duplicate Campaign','Pause Campaign','Archive','Delete'] as $action)
                    <button style="
                        display:block; width:100%; text-align:left;
                        padding:8px 16px; border:none; background:transparent;
                        font-family:'Inter',sans-serif; font-size:13px;
                        color:{{ $action === 'Delete' ? '#c0392b' : '#1e0a2c' }}; cursor:pointer;
                    "
                    onmouseover="this.style.background='#f9f3fa'"
                    onmouseout="this.style.background='transparent'"
                    >{{ $action }}</button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ── ROW 2: DESCRIPTION ──────────────────────────────── --}}
    <p style="
        font-family:'Inter',sans-serif; font-size:13px; font-weight:300;
        color:#7a6884; margin:0 0 18px; line-height:1.55;
    ">{{ $campaign['description'] }}</p>

    {{-- ── ROW 3: META PILLS ───────────────────────────────── --}}
    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">

        {{-- Pill helper styles (reused inline for each pill) --}}

        {{-- 1. Campaign Owner --}}
        <div style="
            display:inline-flex; align-items:center; gap:7px;
            background:#f9f3fa; border:1px solid rgba(185,92,183,0.18);
            border-radius:20px; padding:5px 12px 5px 6px;
            font-family:'Inter',sans-serif; font-size:12.5px; color:#1e0a2c;
        ">
            <div style="
                width:22px; height:22px; border-radius:50%;
                background:linear-gradient(135deg,#6a0f70 0%,#b95cb7 100%);
                display:flex; align-items:center; justify-content:center;
                font-size:9px; font-weight:700; color:#fff; flex-shrink:0;
            ">{{ $campaign['owner']['initials'] }}</div>
            <span style="color:#5a4868; font-weight:300; margin-right:3px;">Owner</span>
            <span style="font-weight:500;">{{ $campaign['owner']['name'] }}</span>
        </div>

        {{-- 2. Duration --}}
        <div style="
            display:inline-flex; align-items:center; gap:6px;
            background:#f9f3fa; border:1px solid rgba(185,92,183,0.18);
            border-radius:20px; padding:5px 12px;
            font-family:'Inter',sans-serif; font-size:12.5px; color:#1e0a2c;
        ">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            <span>{{ $campaign['start_date'] }} – {{ $campaign['end_date'] }}</span>
            <span style="
                background:rgba(46,204,113,0.12); color:#1a7a3c;
                border-radius:10px; padding:1px 7px; font-size:11px; font-weight:600;
            ">{{ $campaign['days_remaining'] }} days left</span>
        </div>

        {{-- 3. Budget --}}
        <div style="
            display:inline-flex; align-items:center; gap:6px;
            background:#f9f3fa; border:1px solid rgba(185,92,183,0.18);
            border-radius:20px; padding:5px 12px;
            font-family:'Inter',sans-serif; font-size:12.5px; color:#1e0a2c;
        ">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="1" x2="12" y2="23"/>
                <path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
            </svg>
            <span style="color:#5a4868; font-weight:300;">Budget</span>
            <span style="font-weight:600; color:#6a0f70;">Rs. {{ number_format($campaign['budget']) }}</span>
            <span style="font-size:11px; color:#9b6aad;">Planned</span>
        </div>

        {{-- 4. Target Audience --}}
        <div style="
            display:inline-flex; align-items:center; gap:6px;
            background:#f9f3fa; border:1px solid rgba(185,92,183,0.18);
            border-radius:20px; padding:5px 12px;
            font-family:'Inter',sans-serif; font-size:12.5px; color:#1e0a2c;
        ">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
            </svg>
            <span style="color:#5a4868; font-weight:300;">Audience</span>
            <span style="font-weight:500;">{{ $campaign['audience'] }}</span>
        </div>

        {{-- 5. Channels (icons + overflow) --}}
        <div style="
            display:inline-flex; align-items:center; gap:6px;
            background:#f9f3fa; border:1px solid rgba(185,92,183,0.18);
            border-radius:20px; padding:5px 12px;
            font-family:'Inter',sans-serif; font-size:12.5px; color:#1e0a2c;
        ">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8h1a4 4 0 010 8h-1"/><path d="M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z"/>
                <line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/>
            </svg>
            <span style="color:#5a4868; font-weight:300;">Channels</span>

            {{-- Platform icon bubbles --}}
            <div style="display:flex; align-items:center; gap:3px;">
                @foreach($campaign['channels'] as $ch)
                    @php
                        $chColors = [
                            'instagram' => ['bg' => 'linear-gradient(135deg,#f09433 0%,#e6683c 25%,#dc2743 50%,#cc2366 75%,#bc1888 100%)', 'label' => 'IG'],
                            'facebook'  => ['bg' => '#1877F2', 'label' => 'f'],
                            'google'    => ['bg' => '#4285F4', 'label' => 'G'],
                            'wordpress' => ['bg' => '#21759b', 'label' => 'W'],
                            'twitter'   => ['bg' => '#1da1f2', 'label' => 'X'],
                            'linkedin'  => ['bg' => '#0A66C2', 'label' => 'in'],
                        ];
                        $ci = $chColors[$ch['key']] ?? ['bg' => '#888', 'label' => '?'];
                    @endphp
                    <span
                        title="{{ $ch['label'] }}"
                        style="
                            width:20px; height:20px; border-radius:50%;
                            background:{{ $ci['bg'] }};
                            display:inline-flex; align-items:center; justify-content:center;
                            font-size:9px; font-weight:700; color:#fff;
                        "
                    >{{ $ci['label'] }}</span>
                @endforeach

                @if($campaign['channels_overflow'] > 0)
                <span style="
                    width:20px; height:20px; border-radius:50%;
                    background:#e8dced; color:#6a0f70;
                    display:inline-flex; align-items:center; justify-content:center;
                    font-size:9px; font-weight:700;
                ">+{{ $campaign['channels_overflow'] }}</span>
                @endif
            </div>
        </div>

    </div>{{-- /meta pills --}}
</div>{{-- /header card --}}


{{-- ══════════════════════════════════════════════════════════════
     2. CAMPAIGN SUB-TAB NAVIGATION
     Sits below the header card, above the content area.
     Uses same visual style as the marketing module nav.
══════════════════════════════════════════════════════════════ --}}
@php
    $tabs = [
        'overview'     => ['label' => 'Overview',            'icon' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>'],
        'content-plan' => ['label' => 'Content Plan',        'icon' => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>'],
        'assets'       => ['label' => 'Assets',              'icon' => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>'],
        'leads'        => ['label' => 'Leads & Appointments','icon' => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>'],
        'performance'  => ['label' => 'Performance',         'icon' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
        'team'         => ['label' => 'Team',                'icon' => '<path d="M12 2a4 4 0 100 8 4 4 0 000-8zm0 14c-6 0-8 2-8 2v2h16v-2s-2-2-8-2z"/>'],
        'settings'     => ['label' => 'Settings',            'icon' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>'],
    ];
@endphp

<div style="
    background:#ffffff;
    border:1px solid rgba(185,92,183,0.14);
    border-radius:10px;
    margin-bottom:20px;
    overflow:hidden;
">
    <div style="
        display:flex; align-items:stretch; gap:0;
        overflow-x:auto; scrollbar-width:none;
        border-bottom:1px solid rgba(185,92,183,0.1);
        padding:0 4px;
    ">
        @foreach($tabs as $key => $tab)
        <button
            @click="activeTab = '{{ $key }}'"
            style="
                display:inline-flex; align-items:center; gap:5px;
                padding:0 14px; height:44px; border:none; background:transparent;
                font-family:'Inter',sans-serif; font-size:12.5px; cursor:pointer;
                white-space:nowrap; flex-shrink:0; border-bottom:2px solid transparent;
                transition:color 150ms, border-color 150ms;
            "
            :style="activeTab === '{{ $key }}'
                ? 'color:#6a0f70; font-weight:600; border-bottom-color:#6a0f70;'
                : 'color:#5a4868; font-weight:400; border-bottom-color:transparent;'"
            onmouseover="if(this.style.color !== 'rgb(106, 15, 112)') this.style.color='#1e0a2c'"
            onmouseout="if(this.style.color !== 'rgb(106, 15, 112)') this.style.color='#5a4868'"
        >
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                {!! $tab['icon'] !!}
            </svg>
            {{ $tab['label'] }}
        </button>
        @endforeach
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════════
     3. TWO-COLUMN LAYOUT — main content (flex:1) + sidebar (280px)
══════════════════════════════════════════════════════════════ --}}
<div style="display:flex; gap:20px; align-items:flex-start;">

    {{-- ── MAIN CONTENT AREA ──────────────────────────────── --}}
    <div style="flex:1; min-width:0;">

        {{-- OVERVIEW TAB --}}
        <div x-show="activeTab === 'overview'" x-cloak>
            @include('marketing.campaigns.partials._overview-tab', ['campaign' => $campaign])
        </div>

        {{-- CONTENT PLAN TAB --}}
        <div x-show="activeTab === 'content-plan'" x-cloak>
            @include('marketing.campaigns.partials._content-plan-tab', ['campaign' => $campaign])
        </div>

        {{-- ASSETS TAB --}}
        <div x-show="activeTab === 'assets'" x-cloak>
            @include('marketing.campaigns.partials._assets-tab', ['campaign' => $campaign])
        </div>

        {{-- LEADS & APPOINTMENTS TAB --}}
        <div x-show="activeTab === 'leads'" x-cloak>
            @include('marketing.campaigns.partials._leads-tab', ['campaign' => $campaign])
        </div>

        {{-- PERFORMANCE TAB --}}
        <div x-show="activeTab === 'performance'" x-cloak>
            @include('marketing.campaigns.partials._performance-tab', ['campaign' => $campaign])
        </div>

        {{-- TEAM TAB --}}
        <div x-show="activeTab === 'team'" x-cloak>
            @include('marketing.campaigns.partials._team-tab', ['campaign' => $campaign])
        </div>

        {{-- SETTINGS TAB --}}
        <div x-show="activeTab === 'settings'" x-cloak>
            @include('marketing.campaigns.partials._settings-tab', ['campaign' => $campaign])
        </div>

    </div>{{-- /main content --}}

    {{-- ── RIGHT SIDEBAR (280px) ──────────────────────────── --}}
    <div style="width:280px; flex-shrink:0; position:sticky; top:80px;">
        @include('marketing.campaigns.partials._sidebar', ['campaign' => $campaign])
    </div>

</div>{{-- /two-column layout --}}

</div>{{-- /x-data --}}

{{-- x-cloak: hide Alpine elements before JS loads --}}
<style>[x-cloak]{display:none!important;}</style>

@endsection
