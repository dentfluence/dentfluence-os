{{--
|==========================================================================
| Marketing — Campaigns Index
| File: resources/views/marketing/campaigns/index.blade.php
|
| Views: Kanban (default) + List — toggled via Alpine.js
| Data:  $campaigns (Collection) passed from CampaignController@index
|==========================================================================
--}}
@extends('marketing.layouts.app')

@php $marketingPageTitle = 'Campaigns'; @endphp
@section('page-title', 'Marketing — Campaigns')

@section('marketing-content')

{{-- ══════════════════════════════════════════════════════════════
     ROOT ALPINE COMPONENT
     x-view: 'kanban' | 'list'
     x-search, x-status, x-treatment, x-date — filter state
     NOTE: Data defined in <script> block below to avoid HTML-attribute
     breakage when JSON contains '>' characters.
═══════════════════════════════════════════════════════════════ --}}
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('campaignApp', () => ({
            view:      'kanban',
            search:    '',
            status:    '',
            treatment: '',
            dateRange: '',

            /* All campaigns injected from PHP — safe inside <script> */
            all: @json($campaigns->values()),

            /* Filtered subset used by both views */
            get filtered() {
                return this.all.filter(c => {
                    const q = this.search.toLowerCase();
                    if (q && !c.name.toLowerCase().includes(q) && !c.treatment.toLowerCase().includes(q)) return false;
                    if (this.status    && c.status    !== this.status)    return false;
                    if (this.treatment && c.treatment !== this.treatment)  return false;
                    return true;
                });
            },

            /* Campaigns for a given status column */
            byStatus(s) {
                return this.filtered.filter(c => c.status === s);
            },

            /* Rupee formatter: 320000 → Rs. 3.2L */
            fmt(n) {
                if (n >= 100000) return 'Rs. ' + (n / 100000).toFixed(1) + 'L';
                if (n >= 1000)   return 'Rs. ' + (n / 1000).toFixed(0)   + 'K';
                return 'Rs. ' + n;
            }
        }));
    });
</script>

<div x-data="campaignApp">

{{-- ── PAGE HEADER ──────────────────────────────────────────────── --}}
<div style="
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
">
    <div>
        <h1 style="
            font-family: 'Cormorant Garamond', serif;
            font-size: 26px;
            font-weight: 700;
            color: #1e0a2c;
            margin: 0 0 3px;
            line-height: 1.15;
        ">Campaigns</h1>
        <p style="
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 300;
            color: #7a6884;
            margin: 0;
        ">Build, launch, and track multi-channel marketing campaigns.</p>
    </div>

    {{-- + New Campaign button --}}
    <button
        onclick="alert('New Campaign — coming in Phase 2.3-B')"
        style="
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0 18px;
            height: 36px;
            background: #6a0f70;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: background 150ms;
            flex-shrink: 0;
        "
        onmouseover="this.style.background='#52085a'"
        onmouseout="this.style.background='#6a0f70'"
    >
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        New Campaign
    </button>
</div>

{{-- ── TOP FILTER BAR ───────────────────────────────────────────── --}}
<div style="
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 24px;
    flex-wrap: wrap;
">

    {{-- Search --}}
    <div style="
        display: flex;
        align-items: center;
        gap: 8px;
        background: #fff;
        border: 1px solid rgba(185,92,183,0.22);
        border-radius: 4px;
        padding: 0 11px;
        height: 36px;
        min-width: 220px;
        flex: 1;
        max-width: 300px;
    ">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input
            type="search"
            placeholder="Search campaigns…"
            x-model="search"
            style="
                border: none;
                background: transparent;
                outline: none;
                font-family: 'Inter', sans-serif;
                font-size: 13px;
                color: #1e0a2c;
                width: 100%;
            "
            aria-label="Search campaigns"
        >
    </div>

    {{-- Status filter --}}
    <select
        x-model="status"
        style="
            height: 36px;
            padding: 0 30px 0 11px;
            border: 1px solid rgba(185,92,183,0.22);
            border-radius: 4px;
            background: #fff url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2210%22 height=%226%22 viewBox=%220 0 10 6%22><path d=%22M0 0l5 6 5-6z%22 fill=%22%239b6aad%22/></svg>') no-repeat right 10px center;
            appearance: none;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            color: #1e0a2c;
            cursor: pointer;
            outline: none;
        "
        aria-label="Filter by status"
    >
        <option value="">All Statuses</option>
        <option value="Draft">Draft</option>
        <option value="Active">Active</option>
        <option value="Paused">Paused</option>
        <option value="Completed">Completed</option>
    </select>

    {{-- Treatment filter --}}
    <select
        x-model="treatment"
        style="
            height: 36px;
            padding: 0 30px 0 11px;
            border: 1px solid rgba(185,92,183,0.22);
            border-radius: 4px;
            background: #fff url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2210%22 height=%226%22 viewBox=%220 0 10 6%22><path d=%22M0 0l5 6 5-6z%22 fill=%22%239b6aad%22/></svg>') no-repeat right 10px center;
            appearance: none;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            color: #1e0a2c;
            cursor: pointer;
            outline: none;
        "
        aria-label="Filter by treatment"
    >
        <option value="">All Treatments</option>
        <option value="Dental Implants">Dental Implants</option>
        <option value="Cosmetic">Cosmetic</option>
        <option value="Smile Design">Smile Design</option>
        <option value="Preventive">Preventive</option>
        <option value="Orthodontics">Orthodontics</option>
    </select>

    {{-- Date Range filter --}}
    <input
        type="month"
        x-model="dateRange"
        title="Filter by month"
        style="
            height: 36px;
            padding: 0 11px;
            border: 1px solid rgba(185,92,183,0.22);
            border-radius: 4px;
            background: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            color: #1e0a2c;
            outline: none;
            cursor: pointer;
        "
        aria-label="Filter by date range"
    >

    {{-- Spacer --}}
    <div style="flex: 1;"></div>

    {{-- Kanban / List toggle --}}
    <div style="
        display: flex;
        align-items: center;
        background: #f4ecf5;
        border: 1px solid rgba(185,92,183,0.18);
        border-radius: 4px;
        padding: 3px;
        gap: 2px;
        flex-shrink: 0;
    ">
        {{-- Kanban toggle button --}}
        <button
            @click="view = 'kanban'"
            title="Kanban view"
            :style="view === 'kanban'
                ? 'background:#fff; box-shadow:0 1px 3px rgba(106,15,112,0.12); color:#6a0f70;'
                : 'background:transparent; color:#9b6aad;'"
            style="
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 5px;
                padding: 0 11px;
                height: 28px;
                border: none;
                border-radius: 3px;
                font-family: 'Inter', sans-serif;
                font-size: 12.5px;
                font-weight: 500;
                cursor: pointer;
                transition: background 150ms, color 150ms;
            "
        >
            {{-- Kanban (columns) icon --}}
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="5" height="18" rx="1"/>
                <rect x="10" y="3" width="5" height="12" rx="1"/>
                <rect x="17" y="3" width="5" height="15" rx="1"/>
            </svg>
            Kanban
        </button>

        {{-- List toggle button --}}
        <button
            @click="view = 'list'"
            title="List view"
            :style="view === 'list'
                ? 'background:#fff; box-shadow:0 1px 3px rgba(106,15,112,0.12); color:#6a0f70;'
                : 'background:transparent; color:#9b6aad;'"
            style="
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 5px;
                padding: 0 11px;
                height: 28px;
                border: none;
                border-radius: 3px;
                font-family: 'Inter', sans-serif;
                font-size: 12.5px;
                font-weight: 500;
                cursor: pointer;
                transition: background 150ms, color 150ms;
            "
        >
            {{-- List (rows) icon --}}
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="8" y1="6" x2="21" y2="6"/>
                <line x1="8" y1="12" x2="21" y2="12"/>
                <line x1="8" y1="18" x2="21" y2="18"/>
                <line x1="3" y1="6" x2="3.01" y2="6"/>
                <line x1="3" y1="12" x2="3.01" y2="12"/>
                <line x1="3" y1="18" x2="3.01" y2="18"/>
            </svg>
            List
        </button>
    </div>

</div>
{{-- /top filter bar --}}


{{-- ══════════════════════════════════════════════════════════════
     KANBAN VIEW  (Part B)
     4 columns: Draft | Active | Paused | Completed
     Cards driven by Alpine x-for over byStatus(status)
═══════════════════════════════════════════════════════════════ --}}
<div x-show="view === 'kanban'" x-cloak>
<div style="
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    align-items: start;
">

{{-- ─────────────────────────────────────────────────────────────
     COLUMN MACRO (repeated 4×, accent color varies)
     Structure per column:
       [header: name · count badge · + add]
       [x-for card loop]
       [empty state]
───────────────────────────────────────────────────────────── --}}

{{-- ══ COL 1 — DRAFT (#94a3b8 slate) ══════════════════════════ --}}
<div style="
    background: #f9f3fa;
    border: 1px solid rgba(185,92,183,0.13);
    border-radius: 8px;
    overflow: hidden;
">
    {{-- Column header --}}
    <div style="
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 14px;
        border-bottom: 2px solid #94a3b8;
        background: #fff;
    ">
        <span style="
            font-family:'Inter',sans-serif;
            font-size:13px;
            font-weight:600;
            color:#1e0a2c;
            flex:1;
        ">Draft</span>
        <span x-text="byStatus('Draft').length" style="
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-width:20px;
            height:20px;
            padding:0 6px;
            background:#e2e8f0;
            color:#64748b;
            font-family:'Inter',sans-serif;
            font-size:11px;
            font-weight:600;
            border-radius:10px;
        "></span>
        <button title="Add draft campaign" style="
            display:inline-flex;
            align-items:center;
            justify-content:center;
            width:24px; height:24px;
            border:1px solid rgba(148,163,184,0.45);
            border-radius:4px;
            background:transparent;
            color:#94a3b8;
            cursor:pointer;
            padding:0;
        "
        onmouseover="this.style.background='#f1f5f9'"
        onmouseout="this.style.background='transparent'"
        >
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
    </div>

    {{-- Cards --}}
    <div style="padding:10px; display:flex; flex-direction:column; gap:10px; min-height:120px;">

        <template x-for="c in byStatus('Draft')" :key="c.id">
            <div style="
                background:#fff;
                border:1px solid rgba(185,92,183,0.13);
                border-radius:7px;
                padding:13px 13px 11px;
                cursor:pointer;
                transition:box-shadow 150ms, border-color 150ms;
            "
            onmouseover="this.style.boxShadow='0 3px 10px rgba(106,15,112,0.10)';this.style.borderColor='rgba(185,92,183,0.28)'"
            onmouseout="this.style.boxShadow='none';this.style.borderColor='rgba(185,92,183,0.13)'"
            >
                {{-- Name + menu --}}
                <div style="display:flex;align-items:flex-start;gap:6px;margin-bottom:8px;">
                    <span x-text="c.name" style="
                        font-family:'Inter',sans-serif;
                        font-size:13px;
                        font-weight:600;
                        color:#1e0a2c;
                        line-height:1.3;
                        flex:1;
                    "></span>
                    <button style="
                        flex-shrink:0;
                        border:none;background:transparent;
                        color:#9b6aad;cursor:pointer;
                        font-size:16px;line-height:1;
                        padding:0 2px;margin-top:-2px;
                        border-radius:3px;
                    "
                    onmouseover="this.style.background='#f4ecf5'"
                    onmouseout="this.style.background='transparent'"
                    title="More options"
                    >···</button>
                </div>

                {{-- Treatment chip --}}
                <div style="margin-bottom:7px;">
                    <span x-text="c.treatment" style="
                        display:inline-block;
                        padding:2px 8px;
                        background:#f0e8f1;
                        color:#6a0f70;
                        font-family:'Inter',sans-serif;
                        font-size:11px;
                        font-weight:500;
                        border-radius:20px;
                        border:1px solid rgba(106,15,112,0.12);
                    "></span>
                </div>

                {{-- Date range --}}
                <div style="
                    display:flex;align-items:center;gap:5px;
                    margin-bottom:10px;
                ">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <span style="font-family:'Inter',sans-serif;font-size:11.5px;color:#7a6884;">
                        <span x-text="c.start_date"></span>
                        <span style="color:#bbb;margin:0 2px;">—</span>
                        <span x-text="c.end_date"></span>
                    </span>
                </div>

                {{-- Progress bar --}}
                <div style="margin-bottom:3px;">
                    <div style="height:5px;background:#e2e8f0;border-radius:3px;overflow:hidden;">
                        <div :style="'width:' + c.completion_pct + '%;height:100%;background:#94a3b8;border-radius:3px;transition:width 400ms;'"></div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;margin-top:3px;">
                        <span x-text="c.completion_pct + '%'" style="font-family:'Inter',sans-serif;font-size:10.5px;color:#94a3b8;font-weight:500;"></span>
                    </div>
                </div>

                {{-- KPI pills --}}
                <div style="display:flex;gap:5px;margin-bottom:10px;flex-wrap:wrap;">
                    <div style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:#f4ecf5;border-radius:20px;border:1px solid rgba(185,92,183,0.12);">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span x-text="c.leads" style="font-family:'Inter',sans-serif;font-size:11px;font-weight:600;color:#1e0a2c;"></span>
                        <span style="font-family:'Inter',sans-serif;font-size:11px;color:#7a6884;">Leads</span>
                    </div>
                    <div style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:#f4ecf5;border-radius:20px;border:1px solid rgba(185,92,183,0.12);">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <span x-text="c.appointments" style="font-family:'Inter',sans-serif;font-size:11px;font-weight:600;color:#1e0a2c;"></span>
                        <span style="font-family:'Inter',sans-serif;font-size:11px;color:#7a6884;">Appts</span>
                    </div>
                    <div style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:#f4ecf5;border-radius:20px;border:1px solid rgba(185,92,183,0.12);">
                        <span x-text="fmt(c.revenue)" style="font-family:'Inter',sans-serif;font-size:11px;font-weight:600;color:#1e0a2c;"></span>
                    </div>
                </div>

                {{-- Bottom: avatars + days remaining --}}
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    {{-- Stacked avatars --}}
                    <div style="display:flex;align-items:center;">
                        <template x-for="(av, i) in c.team_avatars" :key="i">
                            <div :style="'margin-left:' + (i > 0 ? '-7px' : '0') + ';z-index:' + (3 - i)" style="
                                width:24px;height:24px;border-radius:50%;
                                background:linear-gradient(135deg,#6a0f70 0%,#b95cb7 100%);
                                border:2px solid #fff;
                                display:flex;align-items:center;justify-content:center;
                                font-family:'Inter',sans-serif;font-size:9px;font-weight:700;color:#fff;
                                position:relative;
                            ">
                                <span x-text="av"></span>
                            </div>
                        </template>
                    </div>
                    {{-- Days remaining --}}
                    <span x-text="c.days_remaining + ' days left'"
                        :style="c.days_remaining <= 2 ? 'color:#ef4444;font-weight:600;' : 'color:#7a6884;'"
                        style="font-family:'Inter',sans-serif;font-size:11px;">
                    </span>
                </div>
            </div>
        </template>

        {{-- Empty state --}}
        <div x-show="byStatus('Draft').length === 0" style="
            border:1.5px dashed rgba(148,163,184,0.45);
            border-radius:7px;
            padding:24px 12px;
            text-align:center;
        ">
            <p style="font-family:'Inter',sans-serif;font-size:12px;color:#94a3b8;margin:0;">Drop campaign here</p>
        </div>

    </div>{{-- /cards --}}
</div>{{-- /col Draft --}}


{{-- ══ COL 2 — ACTIVE (#22c55e green) ════════════════════════ --}}
<div style="
    background: #f9f3fa;
    border: 1px solid rgba(185,92,183,0.13);
    border-radius: 8px;
    overflow: hidden;
">
    <div style="
        display: flex;align-items: center;gap: 8px;
        padding: 12px 14px;
        border-bottom: 2px solid #22c55e;
        background: #fff;
    ">
        <span style="font-family:'Inter',sans-serif;font-size:13px;font-weight:600;color:#1e0a2c;flex:1;">Active</span>
        <span x-text="byStatus('Active').length" style="
            display:inline-flex;align-items:center;justify-content:center;
            min-width:20px;height:20px;padding:0 6px;
            background:#dcfce7;color:#16a34a;
            font-family:'Inter',sans-serif;font-size:11px;font-weight:600;border-radius:10px;
        "></span>
        <button title="Add active campaign" style="
            display:inline-flex;align-items:center;justify-content:center;
            width:24px;height:24px;border:1px solid rgba(34,197,94,0.35);
            border-radius:4px;background:transparent;color:#22c55e;cursor:pointer;padding:0;
        "
        onmouseover="this.style.background='#f0fdf4'"
        onmouseout="this.style.background='transparent'"
        >
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
    </div>

    <div style="padding:10px;display:flex;flex-direction:column;gap:10px;min-height:120px;">

        <template x-for="c in byStatus('Active')" :key="c.id">
            <div style="
                background:#fff;border:1px solid rgba(185,92,183,0.13);
                border-radius:7px;padding:13px 13px 11px;cursor:pointer;
                transition:box-shadow 150ms,border-color 150ms;
            "
            onmouseover="this.style.boxShadow='0 3px 10px rgba(106,15,112,0.10)';this.style.borderColor='rgba(185,92,183,0.28)'"
            onmouseout="this.style.boxShadow='none';this.style.borderColor='rgba(185,92,183,0.13)'"
            >
                <div style="display:flex;align-items:flex-start;gap:6px;margin-bottom:8px;">
                    <span x-text="c.name" style="font-family:'Inter',sans-serif;font-size:13px;font-weight:600;color:#1e0a2c;line-height:1.3;flex:1;"></span>
                    <button style="flex-shrink:0;border:none;background:transparent;color:#9b6aad;cursor:pointer;font-size:16px;line-height:1;padding:0 2px;margin-top:-2px;border-radius:3px;"
                    onmouseover="this.style.background='#f4ecf5'"
                    onmouseout="this.style.background='transparent'"
                    title="More options">···</button>
                </div>
                <div style="margin-bottom:7px;">
                    <span x-text="c.treatment" style="display:inline-block;padding:2px 8px;background:#f0e8f1;color:#6a0f70;font-family:'Inter',sans-serif;font-size:11px;font-weight:500;border-radius:20px;border:1px solid rgba(106,15,112,0.12);"></span>
                </div>
                <div style="display:flex;align-items:center;gap:5px;margin-bottom:10px;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <span style="font-family:'Inter',sans-serif;font-size:11.5px;color:#7a6884;">
                        <span x-text="c.start_date"></span>
                        <span style="color:#bbb;margin:0 2px;">—</span>
                        <span x-text="c.end_date"></span>
                    </span>
                </div>
                <div style="margin-bottom:3px;">
                    <div style="height:5px;background:#dcfce7;border-radius:3px;overflow:hidden;">
                        <div :style="'width:' + c.completion_pct + '%;height:100%;background:#22c55e;border-radius:3px;transition:width 400ms;'"></div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;margin-top:3px;">
                        <span x-text="c.completion_pct + '%'" style="font-family:'Inter',sans-serif;font-size:10.5px;color:#22c55e;font-weight:500;"></span>
                    </div>
                </div>
                <div style="display:flex;gap:5px;margin-bottom:10px;flex-wrap:wrap;">
                    <div style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:#f4ecf5;border-radius:20px;border:1px solid rgba(185,92,183,0.12);">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span x-text="c.leads" style="font-family:'Inter',sans-serif;font-size:11px;font-weight:600;color:#1e0a2c;"></span>
                        <span style="font-family:'Inter',sans-serif;font-size:11px;color:#7a6884;">Leads</span>
                    </div>
                    <div style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:#f4ecf5;border-radius:20px;border:1px solid rgba(185,92,183,0.12);">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <span x-text="c.appointments" style="font-family:'Inter',sans-serif;font-size:11px;font-weight:600;color:#1e0a2c;"></span>
                        <span style="font-family:'Inter',sans-serif;font-size:11px;color:#7a6884;">Appts</span>
                    </div>
                    <div style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:#f4ecf5;border-radius:20px;border:1px solid rgba(185,92,183,0.12);">
                        <span x-text="fmt(c.revenue)" style="font-family:'Inter',sans-serif;font-size:11px;font-weight:600;color:#1e0a2c;"></span>
                    </div>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;">
                        <template x-for="(av, i) in c.team_avatars" :key="i">
                            <div :style="'margin-left:' + (i > 0 ? '-7px' : '0') + ';z-index:' + (3 - i)" style="width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,#6a0f70 0%,#b95cb7 100%);border:2px solid #fff;display:flex;align-items:center;justify-content:center;font-family:'Inter',sans-serif;font-size:9px;font-weight:700;color:#fff;position:relative;">
                                <span x-text="av"></span>
                            </div>
                        </template>
                    </div>
                    <span x-text="c.days_remaining + ' days left'"
                        :style="c.days_remaining <= 2 ? 'color:#ef4444;font-weight:600;' : 'color:#7a6884;'"
                        style="font-family:'Inter',sans-serif;font-size:11px;">
                    </span>
                </div>
            </div>
        </template>

        <div x-show="byStatus('Active').length === 0" style="border:1.5px dashed rgba(34,197,94,0.30);border-radius:7px;padding:24px 12px;text-align:center;">
            <p style="font-family:'Inter',sans-serif;font-size:12px;color:#86efac;margin:0;">Drop campaign here</p>
        </div>

    </div>
</div>{{-- /col Active --}}


{{-- ══ COL 3 — PAUSED (#f97316 orange) ═══════════════════════ --}}
<div style="
    background: #f9f3fa;
    border: 1px solid rgba(185,92,183,0.13);
    border-radius: 8px;
    overflow: hidden;
">
    <div style="
        display:flex;align-items:center;gap:8px;
        padding:12px 14px;
        border-bottom:2px solid #f97316;
        background:#fff;
    ">
        <span style="font-family:'Inter',sans-serif;font-size:13px;font-weight:600;color:#1e0a2c;flex:1;">Paused</span>
        <span x-text="byStatus('Paused').length" style="
            display:inline-flex;align-items:center;justify-content:center;
            min-width:20px;height:20px;padding:0 6px;
            background:#fff7ed;color:#ea580c;
            font-family:'Inter',sans-serif;font-size:11px;font-weight:600;border-radius:10px;
        "></span>
        <button title="Add paused campaign" style="
            display:inline-flex;align-items:center;justify-content:center;
            width:24px;height:24px;border:1px solid rgba(249,115,22,0.35);
            border-radius:4px;background:transparent;color:#f97316;cursor:pointer;padding:0;
        "
        onmouseover="this.style.background='#fff7ed'"
        onmouseout="this.style.background='transparent'"
        >
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
    </div>

    <div style="padding:10px;display:flex;flex-direction:column;gap:10px;min-height:120px;">

        <template x-for="c in byStatus('Paused')" :key="c.id">
            <div style="
                background:#fff;border:1px solid rgba(185,92,183,0.13);
                border-radius:7px;padding:13px 13px 11px;cursor:pointer;
                transition:box-shadow 150ms,border-color 150ms;
            "
            onmouseover="this.style.boxShadow='0 3px 10px rgba(106,15,112,0.10)';this.style.borderColor='rgba(185,92,183,0.28)'"
            onmouseout="this.style.boxShadow='none';this.style.borderColor='rgba(185,92,183,0.13)'"
            >
                <div style="display:flex;align-items:flex-start;gap:6px;margin-bottom:8px;">
                    <span x-text="c.name" style="font-family:'Inter',sans-serif;font-size:13px;font-weight:600;color:#1e0a2c;line-height:1.3;flex:1;"></span>
                    <button style="flex-shrink:0;border:none;background:transparent;color:#9b6aad;cursor:pointer;font-size:16px;line-height:1;padding:0 2px;margin-top:-2px;border-radius:3px;"
                    onmouseover="this.style.background='#f4ecf5'"
                    onmouseout="this.style.background='transparent'"
                    title="More options">···</button>
                </div>
                <div style="margin-bottom:7px;">
                    <span x-text="c.treatment" style="display:inline-block;padding:2px 8px;background:#f0e8f1;color:#6a0f70;font-family:'Inter',sans-serif;font-size:11px;font-weight:500;border-radius:20px;border:1px solid rgba(106,15,112,0.12);"></span>
                </div>
                <div style="display:flex;align-items:center;gap:5px;margin-bottom:10px;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <span style="font-family:'Inter',sans-serif;font-size:11.5px;color:#7a6884;">
                        <span x-text="c.start_date"></span>
                        <span style="color:#bbb;margin:0 2px;">—</span>
                        <span x-text="c.end_date"></span>
                    </span>
                </div>
                <div style="margin-bottom:3px;">
                    <div style="height:5px;background:#fed7aa;border-radius:3px;overflow:hidden;">
                        <div :style="'width:' + c.completion_pct + '%;height:100%;background:#f97316;border-radius:3px;transition:width 400ms;'"></div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;margin-top:3px;">
                        <span x-text="c.completion_pct + '%'" style="font-family:'Inter',sans-serif;font-size:10.5px;color:#f97316;font-weight:500;"></span>
                    </div>
                </div>
                <div style="display:flex;gap:5px;margin-bottom:10px;flex-wrap:wrap;">
                    <div style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:#f4ecf5;border-radius:20px;border:1px solid rgba(185,92,183,0.12);">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span x-text="c.leads" style="font-family:'Inter',sans-serif;font-size:11px;font-weight:600;color:#1e0a2c;"></span>
                        <span style="font-family:'Inter',sans-serif;font-size:11px;color:#7a6884;">Leads</span>
                    </div>
                    <div style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:#f4ecf5;border-radius:20px;border:1px solid rgba(185,92,183,0.12);">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <span x-text="c.appointments" style="font-family:'Inter',sans-serif;font-size:11px;font-weight:600;color:#1e0a2c;"></span>
                        <span style="font-family:'Inter',sans-serif;font-size:11px;color:#7a6884;">Appts</span>
                    </div>
                    <div style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:#f4ecf5;border-radius:20px;border:1px solid rgba(185,92,183,0.12);">
                        <span x-text="fmt(c.revenue)" style="font-family:'Inter',sans-serif;font-size:11px;font-weight:600;color:#1e0a2c;"></span>
                    </div>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;">
                        <template x-for="(av, i) in c.team_avatars" :key="i">
                            <div :style="'margin-left:' + (i > 0 ? '-7px' : '0') + ';z-index:' + (3 - i)" style="width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,#6a0f70 0%,#b95cb7 100%);border:2px solid #fff;display:flex;align-items:center;justify-content:center;font-family:'Inter',sans-serif;font-size:9px;font-weight:700;color:#fff;position:relative;">
                                <span x-text="av"></span>
                            </div>
                        </template>
                    </div>
                    <span x-text="c.days_remaining + ' days left'"
                        :style="c.days_remaining <= 2 ? 'color:#ef4444;font-weight:600;' : 'color:#7a6884;'"
                        style="font-family:'Inter',sans-serif;font-size:11px;">
                    </span>
                </div>
            </div>
        </template>

        <div x-show="byStatus('Paused').length === 0" style="border:1.5px dashed rgba(249,115,22,0.30);border-radius:7px;padding:24px 12px;text-align:center;">
            <p style="font-family:'Inter',sans-serif;font-size:12px;color:#fdba74;margin:0;">Drop campaign here</p>
        </div>

    </div>
</div>{{-- /col Paused --}}


{{-- ══ COL 4 — COMPLETED (#6366f1 indigo) ════════════════════ --}}
<div style="
    background: #f9f3fa;
    border: 1px solid rgba(185,92,183,0.13);
    border-radius: 8px;
    overflow: hidden;
">
    <div style="
        display:flex;align-items:center;gap:8px;
        padding:12px 14px;
        border-bottom:2px solid #6366f1;
        background:#fff;
    ">
        <span style="font-family:'Inter',sans-serif;font-size:13px;font-weight:600;color:#1e0a2c;flex:1;">Completed</span>
        <span x-text="byStatus('Completed').length" style="
            display:inline-flex;align-items:center;justify-content:center;
            min-width:20px;height:20px;padding:0 6px;
            background:#ede9fe;color:#6366f1;
            font-family:'Inter',sans-serif;font-size:11px;font-weight:600;border-radius:10px;
        "></span>
        <button title="Add completed campaign" style="
            display:inline-flex;align-items:center;justify-content:center;
            width:24px;height:24px;border:1px solid rgba(99,102,241,0.35);
            border-radius:4px;background:transparent;color:#6366f1;cursor:pointer;padding:0;
        "
        onmouseover="this.style.background='#ede9fe'"
        onmouseout="this.style.background='transparent'"
        >
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
    </div>

    <div style="padding:10px;display:flex;flex-direction:column;gap:10px;min-height:120px;">

        <template x-for="c in byStatus('Completed')" :key="c.id">
            <div style="
                background:#fff;border:1px solid rgba(185,92,183,0.13);
                border-radius:7px;padding:13px 13px 11px;cursor:pointer;
                transition:box-shadow 150ms,border-color 150ms;
            "
            onmouseover="this.style.boxShadow='0 3px 10px rgba(106,15,112,0.10)';this.style.borderColor='rgba(185,92,183,0.28)'"
            onmouseout="this.style.boxShadow='none';this.style.borderColor='rgba(185,92,183,0.13)'"
            >
                <div style="display:flex;align-items:flex-start;gap:6px;margin-bottom:8px;">
                    <span x-text="c.name" style="font-family:'Inter',sans-serif;font-size:13px;font-weight:600;color:#1e0a2c;line-height:1.3;flex:1;"></span>
                    <button style="flex-shrink:0;border:none;background:transparent;color:#9b6aad;cursor:pointer;font-size:16px;line-height:1;padding:0 2px;margin-top:-2px;border-radius:3px;"
                    onmouseover="this.style.background='#f4ecf5'"
                    onmouseout="this.style.background='transparent'"
                    title="More options">···</button>
                </div>
                <div style="margin-bottom:7px;">
                    <span x-text="c.treatment" style="display:inline-block;padding:2px 8px;background:#f0e8f1;color:#6a0f70;font-family:'Inter',sans-serif;font-size:11px;font-weight:500;border-radius:20px;border:1px solid rgba(106,15,112,0.12);"></span>
                </div>
                <div style="display:flex;align-items:center;gap:5px;margin-bottom:10px;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <span style="font-family:'Inter',sans-serif;font-size:11.5px;color:#7a6884;">
                        <span x-text="c.start_date"></span>
                        <span style="color:#bbb;margin:0 2px;">—</span>
                        <span x-text="c.end_date"></span>
                    </span>
                </div>
                <div style="margin-bottom:3px;">
                    <div style="height:5px;background:#ede9fe;border-radius:3px;overflow:hidden;">
                        <div :style="'width:' + c.completion_pct + '%;height:100%;background:#6366f1;border-radius:3px;transition:width 400ms;'"></div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;margin-top:3px;">
                        <span x-text="c.completion_pct + '%'" style="font-family:'Inter',sans-serif;font-size:10.5px;color:#6366f1;font-weight:500;"></span>
                    </div>
                </div>
                <div style="display:flex;gap:5px;margin-bottom:10px;flex-wrap:wrap;">
                    <div style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:#f4ecf5;border-radius:20px;border:1px solid rgba(185,92,183,0.12);">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span x-text="c.leads" style="font-family:'Inter',sans-serif;font-size:11px;font-weight:600;color:#1e0a2c;"></span>
                        <span style="font-family:'Inter',sans-serif;font-size:11px;color:#7a6884;">Leads</span>
                    </div>
                    <div style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:#f4ecf5;border-radius:20px;border:1px solid rgba(185,92,183,0.12);">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <span x-text="c.appointments" style="font-family:'Inter',sans-serif;font-size:11px;font-weight:600;color:#1e0a2c;"></span>
                        <span style="font-family:'Inter',sans-serif;font-size:11px;color:#7a6884;">Appts</span>
                    </div>
                    <div style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:#f4ecf5;border-radius:20px;border:1px solid rgba(185,92,183,0.12);">
                        <span x-text="fmt(c.revenue)" style="font-family:'Inter',sans-serif;font-size:11px;font-weight:600;color:#1e0a2c;"></span>
                    </div>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;">
                        <template x-for="(av, i) in c.team_avatars" :key="i">
                            <div :style="'margin-left:' + (i > 0 ? '-7px' : '0') + ';z-index:' + (3 - i)" style="width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,#6a0f70 0%,#b95cb7 100%);border:2px solid #fff;display:flex;align-items:center;justify-content:center;font-family:'Inter',sans-serif;font-size:9px;font-weight:700;color:#fff;position:relative;">
                                <span x-text="av"></span>
                            </div>
                        </template>
                    </div>
                    <span style="font-family:'Inter',sans-serif;font-size:11px;color:#6366f1;font-weight:500;">✓ Done</span>
                </div>
            </div>
        </template>

        <div x-show="byStatus('Completed').length === 0" style="border:1.5px dashed rgba(99,102,241,0.30);border-radius:7px;padding:24px 12px;text-align:center;">
            <p style="font-family:'Inter',sans-serif;font-size:12px;color:#a5b4fc;margin:0;">Drop campaign here</p>
        </div>

    </div>
</div>{{-- /col Completed --}}


</div>{{-- /grid --}}
</div>{{-- /kanban view --}}


{{-- ══════════════════════════════════════════════════════════════
     LIST VIEW  (Part C)
     Columns: Campaign · Status · Treatment · Duration · Budget
              · Leads · Appts · Revenue · Completion · Actions
     Driven by Alpine `filtered` computed getter (search + filters).
═══════════════════════════════════════════════════════════════ --}}
<div x-show="view === 'list'" x-cloak>

    {{-- ── Outer card ───────────────────────────────────────── --}}
    <div style="
        background: #fff;
        border: 1px solid rgba(185,92,183,0.13);
        border-radius: 8px;
        overflow: hidden;
    ">

        {{-- ── Result count bar ────────────────────────────── --}}
        <div style="
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 11px 18px;
            border-bottom: 1px solid rgba(185,92,183,0.10);
            background: #faf5fb;
        ">
            <span style="font-family:'Inter',sans-serif;font-size:12.5px;color:#7a6884;">
                Showing <strong x-text="filtered.length" style="color:#1e0a2c;"></strong> campaign<span x-show="filtered.length !== 1">s</span>
            </span>
            <span style="font-family:'Inter',sans-serif;font-size:11.5px;color:#9b6aad;">
                Click a row to view details
            </span>
        </div>

        {{-- ── Table (overflow-x for narrow screens) ──────── --}}
        <div style="overflow-x: auto;">
        <table style="
            width: 100%;
            border-collapse: collapse;
            min-width: 960px;
        ">

            {{-- ── THead ──────────────────────────────────── --}}
            <thead>
                <tr style="border-bottom: 1px solid rgba(185,92,183,0.10);">

                    {{-- Campaign --}}
                    <th style="
                        padding: 10px 18px;
                        text-align: left;
                        font-family:'Inter',sans-serif;
                        font-size: 11.5px;
                        font-weight: 600;
                        color: #7a6884;
                        text-transform: uppercase;
                        letter-spacing: 0.06em;
                        background: #f9f3fa;
                        white-space: nowrap;
                        min-width: 200px;
                    ">Campaign</th>

                    {{-- Status --}}
                    <th style="
                        padding: 10px 14px;
                        text-align: left;
                        font-family:'Inter',sans-serif;
                        font-size: 11.5px;font-weight:600;
                        color:#7a6884;text-transform:uppercase;letter-spacing:0.06em;
                        background:#f9f3fa;white-space:nowrap;
                    ">Status</th>

                    {{-- Treatment --}}
                    <th style="
                        padding:10px 14px;text-align:left;
                        font-family:'Inter',sans-serif;font-size:11.5px;font-weight:600;
                        color:#7a6884;text-transform:uppercase;letter-spacing:0.06em;
                        background:#f9f3fa;white-space:nowrap;
                    ">Treatment</th>

                    {{-- Duration --}}
                    <th style="
                        padding:10px 14px;text-align:left;
                        font-family:'Inter',sans-serif;font-size:11.5px;font-weight:600;
                        color:#7a6884;text-transform:uppercase;letter-spacing:0.06em;
                        background:#f9f3fa;white-space:nowrap;
                    ">Duration</th>

                    {{-- Budget --}}
                    <th style="
                        padding:10px 14px;text-align:right;
                        font-family:'Inter',sans-serif;font-size:11.5px;font-weight:600;
                        color:#7a6884;text-transform:uppercase;letter-spacing:0.06em;
                        background:#f9f3fa;white-space:nowrap;
                    ">Budget</th>

                    {{-- Leads --}}
                    <th style="
                        padding:10px 14px;text-align:right;
                        font-family:'Inter',sans-serif;font-size:11.5px;font-weight:600;
                        color:#7a6884;text-transform:uppercase;letter-spacing:0.06em;
                        background:#f9f3fa;white-space:nowrap;
                    ">Leads</th>

                    {{-- Appts --}}
                    <th style="
                        padding:10px 14px;text-align:right;
                        font-family:'Inter',sans-serif;font-size:11.5px;font-weight:600;
                        color:#7a6884;text-transform:uppercase;letter-spacing:0.06em;
                        background:#f9f3fa;white-space:nowrap;
                    ">Appts</th>

                    {{-- Revenue --}}
                    <th style="
                        padding:10px 14px;text-align:right;
                        font-family:'Inter',sans-serif;font-size:11.5px;font-weight:600;
                        color:#7a6884;text-transform:uppercase;letter-spacing:0.06em;
                        background:#f9f3fa;white-space:nowrap;
                    ">Revenue</th>

                    {{-- Completion --}}
                    <th style="
                        padding:10px 14px;text-align:left;
                        font-family:'Inter',sans-serif;font-size:11.5px;font-weight:600;
                        color:#7a6884;text-transform:uppercase;letter-spacing:0.06em;
                        background:#f9f3fa;white-space:nowrap;min-width:120px;
                    ">Completion</th>

                    {{-- Actions --}}
                    <th style="
                        padding:10px 18px;text-align:center;
                        font-family:'Inter',sans-serif;font-size:11.5px;font-weight:600;
                        color:#7a6884;text-transform:uppercase;letter-spacing:0.06em;
                        background:#f9f3fa;white-space:nowrap;
                    ">Actions</th>

                </tr>
            </thead>

            {{-- ── TBody ──────────────────────────────────── --}}
            <tbody>

                {{-- Data rows --}}
                <template x-for="c in filtered" :key="c.id">
                    <tr
                        style="border-bottom:1px solid rgba(185,92,183,0.07);cursor:pointer;transition:background 120ms;"
                        onmouseover="this.style.background='#faf5fb'"
                        onmouseout="this.style.background='transparent'"
                    >

                        {{-- Campaign name + treatment sub-line --}}
                        <td style="padding:13px 18px;vertical-align:middle;">
                            <div style="font-family:'Inter',sans-serif;font-size:13px;font-weight:600;color:#1e0a2c;line-height:1.3;" x-text="c.name"></div>
                            <div style="font-family:'Inter',sans-serif;font-size:11px;color:#9b6aad;margin-top:2px;display:flex;align-items:center;gap:4px;">
                                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <span x-text="c.team_avatars.join(' · ')"></span>
                            </div>
                        </td>

                        {{-- Status badge --}}
                        <td style="padding:13px 14px;vertical-align:middle;white-space:nowrap;">
                            <span
                                x-text="c.status"
                                :style="
                                    c.status === 'Active'    ? 'background:#dcfce7;color:#16a34a;border-color:#bbf7d0;' :
                                    c.status === 'Paused'    ? 'background:#fff7ed;color:#ea580c;border-color:#fed7aa;' :
                                    c.status === 'Completed' ? 'background:#ede9fe;color:#6366f1;border-color:#c4b5fd;' :
                                                               'background:#f1f5f9;color:#64748b;border-color:#e2e8f0;'
                                "
                                style="
                                    display:inline-flex;align-items:center;
                                    padding:3px 9px;
                                    border:1px solid;
                                    border-radius:20px;
                                    font-family:'Inter',sans-serif;
                                    font-size:11.5px;
                                    font-weight:600;
                                "
                            ></span>
                        </td>

                        {{-- Treatment chip --}}
                        <td style="padding:13px 14px;vertical-align:middle;white-space:nowrap;">
                            <span
                                x-text="c.treatment"
                                style="
                                    display:inline-block;
                                    padding:2px 8px;
                                    background:#f0e8f1;
                                    color:#6a0f70;
                                    border:1px solid rgba(106,15,112,0.12);
                                    border-radius:20px;
                                    font-family:'Inter',sans-serif;
                                    font-size:11px;
                                    font-weight:500;
                                "
                            ></span>
                        </td>

                        {{-- Duration --}}
                        <td style="padding:13px 14px;vertical-align:middle;white-space:nowrap;">
                            <span style="font-family:'Inter',sans-serif;font-size:12.5px;color:#1e0a2c;" x-text="c.start_date"></span>
                            <span style="font-family:'Inter',sans-serif;font-size:12px;color:#bbb;margin:0 4px;">—</span>
                            <span style="font-family:'Inter',sans-serif;font-size:12.5px;color:#1e0a2c;" x-text="c.end_date"></span>
                        </td>

                        {{-- Budget --}}
                        <td style="padding:13px 14px;vertical-align:middle;text-align:right;">
                            <span x-text="fmt(c.budget)" style="font-family:'Inter',sans-serif;font-size:13px;font-weight:500;color:#1e0a2c;"></span>
                        </td>

                        {{-- Leads --}}
                        <td style="padding:13px 14px;vertical-align:middle;text-align:right;">
                            <span x-text="c.leads" style="font-family:'Inter',sans-serif;font-size:13px;font-weight:600;color:#1e0a2c;"></span>
                        </td>

                        {{-- Appts --}}
                        <td style="padding:13px 14px;vertical-align:middle;text-align:right;">
                            <span x-text="c.appointments" style="font-family:'Inter',sans-serif;font-size:13px;font-weight:600;color:#1e0a2c;"></span>
                        </td>

                        {{-- Revenue --}}
                        <td style="padding:13px 14px;vertical-align:middle;text-align:right;">
                            <span x-text="fmt(c.revenue)" style="font-family:'Inter',sans-serif;font-size:13px;font-weight:600;color:#1e0a2c;"></span>
                        </td>

                        {{-- Completion: mini progress bar + % --}}
                        <td style="padding:13px 14px;vertical-align:middle;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="flex:1;height:6px;background:#f0e8f1;border-radius:3px;overflow:hidden;min-width:60px;">
                                    <div
                                        :style="
                                            'width:' + c.completion_pct + '%;height:100%;border-radius:3px;transition:width 400ms;' +
                                            (c.status === 'Active'    ? 'background:#22c55e;' :
                                             c.status === 'Paused'    ? 'background:#f97316;' :
                                             c.status === 'Completed' ? 'background:#6366f1;' :
                                                                        'background:#94a3b8;')
                                        "
                                    ></div>
                                </div>
                                <span
                                    x-text="c.completion_pct + '%'"
                                    style="font-family:'Inter',sans-serif;font-size:11.5px;font-weight:500;color:#7a6884;flex-shrink:0;min-width:32px;text-align:right;"
                                ></span>
                            </div>
                        </td>

                        {{-- Actions --}}
                        <td style="padding:13px 18px;vertical-align:middle;">
                            <div style="display:flex;align-items:center;justify-content:center;gap:4px;">

                                {{-- View --}}
                                <button
                                    title="View campaign"
                                    style="
                                        display:inline-flex;align-items:center;justify-content:center;
                                        width:28px;height:28px;border-radius:4px;
                                        border:1px solid rgba(185,92,183,0.18);
                                        background:transparent;color:#9b6aad;cursor:pointer;
                                    "
                                    onmouseover="this.style.background='#f4ecf5';this.style.color='#6a0f70'"
                                    onmouseout="this.style.background='transparent';this.style.color='#9b6aad'"
                                >
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </button>

                                {{-- Edit --}}
                                <button
                                    title="Edit campaign"
                                    style="
                                        display:inline-flex;align-items:center;justify-content:center;
                                        width:28px;height:28px;border-radius:4px;
                                        border:1px solid rgba(185,92,183,0.18);
                                        background:transparent;color:#9b6aad;cursor:pointer;
                                    "
                                    onmouseover="this.style.background='#f4ecf5';this.style.color='#6a0f70'"
                                    onmouseout="this.style.background='transparent';this.style.color='#9b6aad'"
                                >
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>

                                {{-- More (···) --}}
                                <button
                                    title="More options"
                                    style="
                                        display:inline-flex;align-items:center;justify-content:center;
                                        width:28px;height:28px;border-radius:4px;
                                        border:1px solid rgba(185,92,183,0.18);
                                        background:transparent;color:#9b6aad;cursor:pointer;
                                        font-size:16px;line-height:1;letter-spacing:1px;
                                    "
                                    onmouseover="this.style.background='#f4ecf5';this.style.color='#6a0f70'"
                                    onmouseout="this.style.background='transparent';this.style.color='#9b6aad'"
                                >···</button>

                            </div>
                        </td>

                    </tr>
                </template>

                {{-- Empty state row --}}
                <tr x-show="filtered.length === 0">
                    <td colspan="10" style="padding:56px 24px;text-align:center;">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="rgba(185,92,183,0.30)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto 14px;display:block;">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                        </svg>
                        <p style="font-family:'Cormorant Garamond',serif;font-size:18px;font-weight:600;color:#1e0a2c;margin:0 0 6px;">No campaigns found</p>
                        <p style="font-family:'Inter',sans-serif;font-size:13px;font-weight:300;color:#7a6884;margin:0;">
                            Try adjusting your search or filters.
                        </p>
                    </td>
                </tr>

            </tbody>
        </table>
        </div>{{-- /overflow wrapper --}}

        {{-- ── Table footer ────────────────────────────────── --}}
        <div style="
            padding: 11px 18px;
            border-top: 1px solid rgba(185,92,183,0.10);
            background: #faf5fb;
            display: flex;
            align-items: center;
            justify-content: space-between;
        ">
            <span style="font-family:'Inter',sans-serif;font-size:12px;color:#9b6aad;">
                <span x-text="filtered.length"></span> of <span x-text="all.length"></span> campaigns
            </span>
            <span style="font-family:'Inter',sans-serif;font-size:12px;color:#bbb;">
                Pagination coming soon
            </span>
        </div>

    </div>{{-- /outer card --}}

</div>{{-- /list view --}}


</div>{{-- /x-data root --}}

@endsection
