@extends('layouts.app')

@section('page-title', 'Clinical Library')

@section('head-extra')
<style>
    #df-content-inner { padding: 0 !important; max-width: 100% !important; }
    #df-content-area  { background: #f3f4f8 !important; }
    * { box-sizing: border-box; }
    [x-cloak] { display: none !important; }

    /* ── Page header ── */
    .cms-header {
        background: white;
        border-bottom: 1px solid #e5e7eb;
        padding: 18px 28px 0;
        display: flex;
        flex-direction: column;
        gap: 0;
    }
    .cms-header-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 12px;
    }
    .cms-title { font-size: 22px; font-weight: 800; color: #111827; font-family: 'Cormorant Garamond', serif; letter-spacing: -.3px; }
    .cms-subtitle { font-size: 12px; color: #9ca3af; margin-top: 2px; }
    .cms-header-actions { display: flex; gap: 8px; }

    /* ── Tabs ── */
    .cms-tabs { display: flex; gap: 0; border-bottom: none; }
    .cms-tab {
        padding: 8px 20px;
        font-size: 13px;
        font-weight: 600;
        color: #6b7280;
        border-bottom: 2.5px solid transparent;
        cursor: pointer;
        transition: all .15s;
        background: none;
        border-top: none;
        border-left: none;
        border-right: none;
        font-family: 'Inter', sans-serif;
    }
    .cms-tab:hover { color: #6a0f70; }
    .cms-tab.active { color: #6a0f70; border-bottom-color: #6a0f70; }

    /* ── Btn styles ── */
    .btn-cms-outline {
        display: flex; align-items: center; gap: 6px;
        padding: 7px 14px; font-size: 12px; font-weight: 600;
        border: 1px solid #e5e7eb; border-radius: 5px; background: white;
        color: #374151; cursor: pointer; transition: all .15s;
    }
    .btn-cms-outline:hover { border-color: #6a0f70; color: #6a0f70; }
    .btn-cms-primary {
        display: flex; align-items: center; gap: 6px;
        padding: 7px 16px; font-size: 12px; font-weight: 600;
        background: #6a0f70; color: white; border: none; border-radius: 5px;
        cursor: pointer; transition: background .15s;
    }
    .btn-cms-primary:hover { background: #380740; }

    /* ── Filter bar ── */
    .cms-filter-bar {
        background: white;
        border-bottom: 1px solid #e5e7eb;
        padding: 14px 28px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .filter-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .filter-group { display: flex; flex-direction: column; gap: 3px; }
    .filter-label { font-size: 10px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .04em; }

    .df-select {
        border: 1px solid #e5e7eb; border-radius: 5px; padding: 6px 10px;
        font-size: 12px; color: #374151; font-family: 'Inter', sans-serif;
        background: white; outline: none; min-width: 140px; cursor: pointer;
        transition: border-color .15s;
    }
    .df-select:focus { border-color: #6a0f70; }

    .search-input-wrap { position: relative; flex: 1; max-width: 280px; }
    .search-input-wrap svg { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #9ca3af; pointer-events: none; }
    .search-input {
        width: 100%; padding: 7px 10px 7px 32px;
        border: 1px solid #e5e7eb; border-radius: 5px; font-size: 12px;
        font-family: 'Inter', sans-serif; color: #374151; outline: none;
        transition: border-color .15s;
    }
    .search-input:focus { border-color: #6a0f70; box-shadow: 0 0 0 3px rgba(106,15,112,.07); }

    /* ── Active filter pills ── */
    .active-filters { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; padding: 8px 28px; background: white; border-bottom: 1px solid #f3f4f6; }
    .filter-pill { display: flex; align-items: center; gap: 4px; padding: 3px 10px; background: #f3e8ff; border: 1px solid #d8b4fe; border-radius: 99px; font-size: 11px; font-weight: 600; color: #6a0f70; }
    .filter-pill button { background: none; border: none; cursor: pointer; color: #9ca3af; padding: 0; font-size: 13px; line-height: 1; margin-left: 2px; }
    .filter-pill button:hover { color: #6a0f70; }

    /* ── Results bar ── */
    .results-bar { display: flex; align-items: center; justify-content: space-between; padding: 10px 28px; }
    .results-count { font-size: 12px; color: #6b7280; }
    .results-count strong { color: #111827; font-weight: 700; }
    .sort-wrap { display: flex; align-items: center; gap: 6px; font-size: 11px; color: #9ca3af; }

    /* ── Results table ── */
    .cms-table { width: 100%; border-collapse: collapse; }
    .cms-table thead th {
        text-align: left; padding: 10px 14px; font-size: 10px; font-weight: 700;
        color: #9ca3af; text-transform: uppercase; letter-spacing: .05em;
        border-bottom: 1px solid #e5e7eb; background: #fafafa; white-space: nowrap;
    }
    .cms-table tbody tr { border-bottom: 1px solid #f3f4f6; cursor: pointer; transition: background .1s; }
    .cms-table tbody tr:hover { background: #faf5fb; }
    .cms-table tbody tr.selected { background: #f5f0ff; border-left: 3px solid #6a0f70; }
    .cms-table td { padding: 10px 14px; font-size: 12px; color: #374151; vertical-align: middle; }

    .patient-cell { display: flex; align-items: center; gap: 9px; }
    .pt-avatar { width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg,#6a0f70,#380740); display: flex; align-items: center; justify-content: center; color: white; font-size: 12px; font-weight: 700; flex-shrink: 0; font-family: 'Cormorant Garamond', serif; }
    .pt-name { font-weight: 700; color: #111827; font-size: 13px; }
    .pt-sub { font-size: 10px; color: #9ca3af; }

    .tx-icon { width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-right: 5px; flex-shrink: 0; }

    .status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 99px; font-size: 10px; font-weight: 700; }
    .status-completed { background: #dcfce7; color: #16a34a; }
    .status-ongoing   { background: #fef3c7; color: #d97706; }
    .status-paused    { background: #f3f4f6; color: #6b7280; }
    .status-cancelled { background: #fee2e2; color: #dc2626; }

    .media-count { display: flex; align-items: center; gap: 4px; font-size: 12px; color: #6b7280; }
    .media-count svg { color: #9ca3af; }

    /* ── Pagination ── */
    .cms-pagination { display: flex; align-items: center; justify-content: space-between; padding: 12px 28px; border-top: 1px solid #f3f4f6; background: white; }
    .pg-btns { display: flex; align-items: center; gap: 3px; }
    .pg-btn { width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border: 1px solid #e5e7eb; border-radius: 5px; font-size: 12px; font-weight: 600; cursor: pointer; color: #6b7280; background: white; transition: all .12s; text-decoration: none; }
    .pg-btn:hover, .pg-btn.active { background: #6a0f70; color: white; border-color: #6a0f70; }
    .pg-btn.disabled { opacity: .4; cursor: not-allowed; pointer-events: none; }

    /* ── Main layout (table + drawer) ── */
    .cms-body { display: flex; min-height: calc(100vh - 160px); }
    .cms-table-wrap { flex: 1; overflow: auto; min-width: 0; display: flex; flex-direction: column; }
    .cms-drawer {
        width: 380px;
        flex-shrink: 0;
        border-left: 1px solid #e5e7eb;
        background: white;
        display: flex;
        flex-direction: column;
        overflow-y: auto;
        transition: width .25s, opacity .2s;
    }
    .cms-drawer.hidden { width: 0; opacity: 0; overflow: hidden; pointer-events: none; }

    /* ── Case drawer ── */
    .drawer-header { padding: 14px 16px 10px; border-bottom: 1px solid #f3f4f6; }
    .drawer-name { font-size: 16px; font-weight: 800; color: #111827; font-family: 'Cormorant Garamond', serif; }
    .drawer-meta { font-size: 11px; color: #9ca3af; margin-top: 2px; display: flex; align-items: center; gap: 6px; }

    .drawer-tabs { display: flex; gap: 0; border-bottom: 1px solid #e5e7eb; padding: 0 16px; }
    .drawer-tab { padding: 8px 12px; font-size: 11px; font-weight: 600; color: #9ca3af; border-bottom: 2px solid transparent; cursor: pointer; transition: all .12s; background: none; border-top: none; border-left: none; border-right: none; font-family: 'Inter', sans-serif; }
    .drawer-tab.active { color: #6a0f70; border-bottom-color: #6a0f70; }

    .drawer-section { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; }
    .drawer-section-title { font-size: 10px; font-weight: 700; color: #6a0f70; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 8px; }

    .case-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 12px; }
    .case-detail-row { display: flex; flex-direction: column; gap: 1px; }
    .cdl { font-size: 10px; color: #9ca3af; font-weight: 500; }
    .cdv { font-size: 12px; color: #111827; font-weight: 600; }
    .cdv a { color: #6a0f70; text-decoration: none; }
    .cdv a:hover { text-decoration: underline; }

    .tag-pill { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 10px; font-weight: 600; background: #f3e8ff; color: #6a0f70; border: 1px solid #e9d5ff; margin: 2px 2px 2px 0; }

    /* ── Media gallery ── */
    .media-type-tabs { display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 10px; }
    .mt-tab { padding: 3px 10px; border: 1.5px solid #e5e7eb; border-radius: 99px; font-size: 10px; font-weight: 700; cursor: pointer; color: #6b7280; background: white; transition: all .12s; }
    .mt-tab.active { background: #6a0f70; border-color: #6a0f70; color: white; }

    .media-stage-group { margin-bottom: 12px; }
    .media-stage-label { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
    .msl-text { font-size: 11px; font-weight: 700; color: #374151; }
    .msl-date { font-size: 10px; color: #9ca3af; }

    .media-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; }
    .media-thumb {
        aspect-ratio: 1;
        border-radius: 5px;
        overflow: hidden;
        position: relative;
        background: #f3f4f6;
        cursor: pointer;
    }
    .media-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .media-thumb-more {
        position: absolute; inset: 0; background: rgba(0,0,0,.5);
        display: flex; align-items: center; justify-content: center;
        color: white; font-size: 14px; font-weight: 700;
    }
    .media-type-badge {
        position: absolute; top: 4px; left: 4px;
        padding: 1px 5px; border-radius: 3px; font-size: 9px; font-weight: 700;
        background: rgba(0,0,0,.55); color: white;
    }

    /* ── Watermark preview ── */
    .wm-preview {
        margin: 12px 16px;
        background: #1f2937;
        border-radius: 8px;
        padding: 10px 12px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .wm-icon { width: 32px; height: 32px; background: rgba(255,255,255,.1); border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .wm-text { flex: 1; }
    .wm-clinic { font-size: 11px; font-weight: 700; color: white; }
    .wm-sub { font-size: 9px; color: rgba(255,255,255,.5); margin-top: 1px; }

    /* ── Generic Education Library ── */
    .edu-wrap { padding: 24px 28px; }
    .edu-cat-title { font-size: 15px; font-weight: 800; color: #111827; margin-bottom: 14px; font-family: 'Cormorant Garamond', serif; }
    .edu-cats { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 28px; }
    .edu-cat-card {
        display: flex; align-items: center; gap: 10px;
        padding: 12px 16px; border: 1.5px solid #e5e7eb;
        border-radius: 10px; cursor: pointer; transition: all .15s;
        background: white; min-width: 130px;
    }
    .edu-cat-card:hover { border-color: #b95cb7; background: #faf5fb; }
    .edu-cat-card.active { border-color: #6a0f70; background: #f5f0ff; }
    .edu-cat-icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .edu-cat-name { font-size: 12px; font-weight: 700; color: #111827; }
    .edu-cat-count { font-size: 10px; color: #9ca3af; }

    .edu-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; }
    @media (max-width: 1200px) { .edu-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 900px)  { .edu-grid { grid-template-columns: repeat(2, 1fr); } }

    .edu-card { border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; background: white; transition: all .15s; cursor: pointer; }
    .edu-card:hover { border-color: #b95cb7; box-shadow: 0 4px 16px rgba(106,15,112,.1); transform: translateY(-1px); }
    .edu-card-thumb { position: relative; aspect-ratio: 16/9; background: #f3f4f6; overflow: hidden; }
    .edu-card-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .edu-card-type-badge { position: absolute; top: 8px; left: 8px; padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; }
    .badge-video  { background: #1f2937; color: white; }
    .badge-photos { background: #16a34a; color: white; }
    .badge-xray   { background: #2563eb; color: white; }
    .badge-pdf    { background: #dc2626; color: white; }
    .edu-card-body { padding: 12px; }
    .edu-card-title { font-size: 13px; font-weight: 700; color: #111827; margin-bottom: 4px; }
    .edu-card-desc { font-size: 11px; color: #6b7280; margin-bottom: 10px; line-height: 1.5; }
    .edu-card-stats { display: flex; align-items: center; gap: 10px; font-size: 11px; color: #9ca3af; }
    .edu-stat { display: flex; flex-direction: column; align-items: center; }
    .edu-stat strong { font-size: 13px; font-weight: 800; color: #374151; line-height: 1.1; }
    .edu-card-footer { display: flex; align-items: center; justify-content: flex-end; padding: 8px 12px; border-top: 1px solid #f3f4f6; }

    /* ── Duration badge ── */
    .duration-badge { position: absolute; bottom: 6px; right: 6px; background: rgba(0,0,0,.7); color: white; font-size: 9px; font-weight: 700; padding: 2px 6px; border-radius: 3px; }

    /* Play button overlay */
    .play-btn { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,.25); }
    .play-circle { width: 40px; height: 40px; border-radius: 50%; background: rgba(255,255,255,.9); display: flex; align-items: center; justify-content: center; }

    /* ── Footer note ── */
    .cms-footer-note { padding: 12px 28px; font-size: 11px; color: #9ca3af; display: flex; align-items: center; gap: 6px; border-top: 1px solid #f3f4f6; background: white; }
</style>
@endsection

@section('content')
<div x-data="cmsApp()" x-init="init()" style="background:#f3f4f8;min-height:100vh;">

    {{-- ══ PAGE HEADER ══ --}}
    <div class="cms-header">
        <div class="cms-header-top">
            <div>
                <div class="cms-title">Clinical Library</div>
                <div class="cms-subtitle" x-text="activeTab==='clinical' ? 'Search and explore clinical cases and educational content' : 'Explore educational content and treatment resources'"></div>
            </div>
            <div class="cms-header-actions">
                <button type="button" class="btn-cms-outline" onclick="document.getElementById('wm-settings-modal').style.display='flex'">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M16.24 7.76a6 6 0 0 1 0 8.49M4.93 4.93a10 10 0 0 0 0 14.14M7.76 7.76a6 6 0 0 0 0 8.49"/></svg>
                    Watermark Settings
                </button>
                <button type="button" class="btn-cms-outline">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export
                </button>
            </div>
        </div>
        <div class="cms-tabs">
            <button type="button" class="cms-tab" :class="activeTab==='clinical'?'active':''" @click="activeTab='clinical'">Patient Clinical Data</button>
            <button type="button" class="cms-tab" :class="activeTab==='education'?'active':''" @click="activeTab='education';loadEdu()">Generic Education Library</button>
        </div>
    </div>

    {{-- ══ PATIENT CLINICAL DATA ══ --}}
    <div x-show="activeTab==='clinical'">

        {{-- Filter bar --}}
        <div class="cms-filter-bar">
            <div class="filter-row">
                {{-- Search --}}
                <div class="search-input-wrap">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    <input type="text" class="search-input" placeholder="Search anything…"
                           x-model="filters.search" @input.debounce.400ms="doSearch()">
                </div>

                {{-- Patient --}}
                <div class="filter-group">
                    <div class="filter-label">Patient Name</div>
                    <select class="df-select" x-model="filters.patient_id" @change="doSearch()">
                        <option value="">All Patients</option>
                        @foreach($cases->pluck('patient')->unique('id')->filter() as $pt)
                        <option value="{{ $pt->id }}">{{ $pt->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Tooth --}}
                <div class="filter-group">
                    <div class="filter-label">Tooth No.</div>
                    <select class="df-select" x-model="filters.tooth" @change="doSearch()">
                        <option value="">All Teeth</option>
                        @foreach($teeth as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Treatment --}}
                <div class="filter-group">
                    <div class="filter-label">Treatment</div>
                    <select class="df-select" x-model="filters.treatment" @change="doSearch()">
                        <option value="">All Treatments</option>
                        @foreach($treatments as $tx)
                        <option value="{{ $tx }}">{{ $tx }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="button" class="btn-cms-outline" style="margin-top:16px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                    More Filters
                </button>
            </div>

            <div class="filter-row">
                {{-- Date --}}
                <div class="filter-group">
                    <div class="filter-label">Date Range</div>
                    <div style="display:flex;align-items:center;gap:6px;border:1px solid #e5e7eb;border-radius:5px;padding:5px 10px;background:white;min-width:180px;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <input type="date" style="border:none;outline:none;font-size:11px;color:#374151;font-family:'Inter',sans-serif;" x-model="filters.date_from" @change="doSearch()">
                    </div>
                </div>

                {{-- Doctor --}}
                <div class="filter-group">
                    <div class="filter-label">Doctor</div>
                    <select class="df-select" x-model="filters.doctor_id" @change="doSearch()">
                        <option value="">All Doctors</option>
                        @foreach($doctors as $doc)
                        <option value="{{ $doc->id }}">{{ $doc->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Tags --}}
                <div class="filter-group">
                    <div class="filter-label">Tags</div>
                    <select class="df-select" x-model="filters.tag" @change="doSearch()">
                        <option value="">All Tags</option>
                        <option>Implant</option><option>RCT</option><option>Crown</option>
                        <option>Veneers</option><option>Molar</option><option>Anterior</option>
                    </select>
                </div>

                <button type="button" class="btn-cms-outline" style="margin-top:16px;" @click="resetFilters()">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 19h5v-5"/></svg>
                    Reset
                </button>
                <button type="button" class="btn-cms-primary" style="margin-top:16px;" @click="doSearch()">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    Search
                </button>
            </div>
        </div>

        {{-- Active filter pills --}}
        <div class="active-filters" x-show="activeFilterPills.length > 0">
            <template x-for="(pill, i) in activeFilterPills" :key="i">
                <div class="filter-pill">
                    <span x-text="pill.label"></span>
                    <button type="button" @click="removePill(pill.key)">×</button>
                </div>
            </template>
            <button type="button" @click="resetFilters()" style="font-size:11px;font-weight:600;color:#9ca3af;background:none;border:none;cursor:pointer;margin-left:4px;">Clear All</button>
        </div>

        {{-- Results + table --}}
        <div class="cms-body">
            <div class="cms-table-wrap">

                <div class="results-bar">
                    <div class="results-count">
                        Showing <strong>{{ $cases->firstItem() }}–{{ $cases->lastItem() }}</strong> of <strong>{{ $cases->total() }}</strong> results
                    </div>
                    <div class="sort-wrap">
                        Sort by
                        <select class="df-select" style="min-width:160px;" x-model="filters.sort" @change="doSearch()">
                            <option value="start_date_desc">Start Date (Newest)</option>
                            <option value="start_date_asc">Start Date (Oldest)</option>
                            <option value="completion_desc">Completion Date</option>
                        </select>
                    </div>
                </div>

                <div style="overflow-x:auto;flex:1;">
                    <table class="cms-table">
                        <thead>
                            <tr>
                                <th>Patient Name</th>
                                <th>Treatment</th>
                                <th>Tooth No.</th>
                                <th>Start Date</th>
                                <th>Completion Date</th>
                                <th>Last Follow-up</th>
                                <th>Media</th>
                                <th>Status</th>
                                <th style="width:28px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cases as $case)
                            <tr :class="selectedCaseId === {{ $case->id }} ? 'selected' : ''"
                                @click="openCase({{ $case->id }})">
                                <td>
                                    <div class="patient-cell">
                                        <div class="pt-avatar">{{ $case->patient?->initials ?? substr($case->patient?->name ?? '?', 0, 2) }}</div>
                                        <div>
                                            <div class="pt-name">{{ $case->patient?->name ?? '—' }}</div>
                                            <div class="pt-sub">{{ $case->patient?->age ? $case->patient->age.'Y' : '' }}{{ $case->patient?->gender ? ' / '.ucfirst($case->patient->gender) : '' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:6px;">
                                        @php $txIcons=['Implant'=>'#6a0f70','RCT'=>'#dc2626','Crown'=>'#d97706','Veneers'=>'#db2777','Aligner'=>'#2563eb','Extraction'=>'#374151']; $txColor = $txIcons[$case->treatment_name] ?? '#6a0f70'; @endphp
                                        <div class="tx-icon" style="background:{{ $txColor }}18;">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="{{ $txColor }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                        </div>
                                        <span style="font-weight:600;">{{ $case->treatment_name }}</span>
                                    </div>
                                </td>
                                <td>{{ $case->tooth_no ?? '—' }}</td>
                                <td>{{ $case->start_date?->format('d M Y') ?? '—' }}</td>
                                <td>{{ $case->completion_date?->format('d M Y') ?? '—' }}</td>
                                <td>{{ $case->last_followup_date?->format('d M Y') ?? '—' }}</td>
                                <td>
                                    <div class="media-count">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                                        {{ $case->media_count }}
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-{{ $case->status }}">{{ ucfirst($case->status) }}</span>
                                </td>
                                <td>
                                    <button type="button" style="background:none;border:none;color:#9ca3af;cursor:pointer;padding:2px;">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" style="text-align:center;padding:40px;color:#9ca3af;">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto 10px;display:block;"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                                    No cases found matching your filters.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($cases->hasPages())
                <div class="cms-pagination">
                    <div style="font-size:11px;color:#9ca3af;">
                        Rows per page:
                        <select style="border:1px solid #e5e7eb;border-radius:4px;padding:3px 6px;font-size:11px;margin-left:4px;" onchange="window.location.href='?'+new URLSearchParams({...Object.fromEntries(new URLSearchParams(location.search)),...{per_page:this.value}})">
                            <option value="10" {{ request('per_page',10)==10?'selected':'' }}>10</option>
                            <option value="25" {{ request('per_page',10)==25?'selected':'' }}>25</option>
                            <option value="50" {{ request('per_page',10)==50?'selected':'' }}>50</option>
                        </select>
                    </div>
                    <div class="pg-btns">
                        <a href="{{ $cases->previousPageUrl() }}" class="pg-btn {{ !$cases->onFirstPage() ?: 'disabled' }}">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        </a>
                        @foreach($cases->getUrlRange(max(1,$cases->currentPage()-2), min($cases->lastPage(),$cases->currentPage()+4)) as $page => $url)
                        <a href="{{ $url }}" class="pg-btn {{ $page === $cases->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                        @endforeach
                        @if($cases->lastPage() > $cases->currentPage()+4)
                        <span class="pg-btn" style="cursor:default;">…</span>
                        <a href="{{ $cases->url($cases->lastPage()) }}" class="pg-btn">{{ $cases->lastPage() }}</a>
                        @endif
                        <a href="{{ $cases->nextPageUrl() }}" class="pg-btn {{ $cases->hasMorePages() ?: 'disabled' }}">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </a>
                    </div>
                </div>
                @endif

                {{-- Footer note --}}
                <div class="cms-footer-note">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                    All media are automatically watermarked with clinic name, doctor name and patient name for security and branding.
                </div>
            </div>

            {{-- ══ CASE DRAWER ══ --}}
            <div class="cms-drawer" :class="selectedCaseId ? '' : 'hidden'" id="cms-drawer">
                <template x-if="selectedCaseId && caseData">
                    <div>
                        {{-- Drawer header --}}
                        <div class="drawer-header">
                            <div style="display:flex;align-items:flex-start;justify-content:space-between;">
                                <div>
                                    <div class="drawer-name" x-text="caseData.patient?.name ?? ''"></div>
                                    <div class="drawer-meta">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                        <span x-text="caseData.case?.treatment_name ?? ''"></span>
                                        <span>|</span>
                                        <span>Tooth <span x-text="caseData.case?.tooth_no ?? '—'"></span></span>
                                    </div>
                                    <div style="margin-top:5px;">
                                        <span class="status-badge" :class="'status-'+(caseData.case?.status ?? 'ongoing')" x-text="caseData.case?.status ? (caseData.case.status.charAt(0).toUpperCase()+caseData.case.status.slice(1)) : ''"></span>
                                    </div>
                                </div>
                                <button type="button" @click="selectedCaseId=null;caseData=null" style="background:none;border:none;color:#9ca3af;cursor:pointer;font-size:20px;line-height:1;padding:2px;">×</button>
                            </div>
                        </div>

                        {{-- Drawer tabs --}}
                        <div class="drawer-tabs">
                            <button type="button" class="drawer-tab" :class="drawerTab==='overview'?'active':''" @click="drawerTab='overview'">Case Overview</button>
                            <button type="button" class="drawer-tab" :class="drawerTab==='timeline'?'active':''" @click="drawerTab='timeline'">Timeline</button>
                            <button type="button" class="drawer-tab" :class="drawerTab==='visits'?'active':''" @click="drawerTab='visits'">Visit History</button>
                            <button type="button" class="drawer-tab" :class="drawerTab==='notes'?'active':''" @click="drawerTab='notes'">Notes</button>
                        </div>

                        {{-- Case Overview Tab --}}
                        <div x-show="drawerTab==='overview'">
                            <div class="drawer-section">
                                <div class="drawer-section-title">Case Details</div>
                                <div class="case-detail-grid">
                                    <div class="case-detail-row"><div class="cdl">Start Date</div><div class="cdv" x-text="caseData.case?.start_date ?? '—'"></div></div>
                                    <div class="case-detail-row"><div class="cdl">Completion Date</div><div class="cdv" x-text="caseData.case?.completion_date ?? '—'"></div></div>
                                    <div class="case-detail-row"><div class="cdl">Last Follow-up</div><div class="cdv" x-text="caseData.case?.last_followup_date ?? '—'"></div></div>
                                    <div class="case-detail-row"><div class="cdl">Total Visits</div><div class="cdv" x-text="caseData.timeline?.length ?? '—'"></div></div>
                                    <div class="case-detail-row" style="grid-column:span 2;"><div class="cdl">Doctor</div><div class="cdv"><a href="#" x-text="caseData.doctor?.name ?? '—'" style="color:#6a0f70;"></a></div></div>
                                </div>
                            </div>

                            <div class="drawer-section">
                                <div class="drawer-section-title">Tags</div>
                                <template x-for="tag in (caseData.case?.tags ?? [])" :key="tag">
                                    <span class="tag-pill" x-text="tag"></span>
                                </template>
                                <span x-show="!(caseData.case?.tags?.length)" style="font-size:11px;color:#9ca3af;">No tags assigned</span>
                            </div>

                            {{-- Media Gallery --}}
                            <div class="drawer-section">
                                <div class="drawer-section-title">Media Gallery</div>
                                <div class="media-type-tabs">
                                    <button type="button" class="mt-tab" :class="mediaTypeFilter==='all'?'active':''" @click="mediaTypeFilter='all';filterMedia()">
                                        All (<span x-text="caseData.all_media?.length ?? 0"></span>)
                                    </button>
                                    <template x-for="[type, cnt] in Object.entries(caseData.media_by_type ?? {})" :key="type">
                                        <button type="button" class="mt-tab" :class="mediaTypeFilter===type?'active':''" @click="mediaTypeFilter=type;filterMedia()">
                                            <span x-text="type.charAt(0).toUpperCase()+type.slice(1)+'s ('+cnt+')'"></span>
                                        </button>
                                    </template>
                                </div>

                                {{-- Media grouped by stage --}}
                                <template x-for="group in mediaGroups" :key="group.stage">
                                    <div class="media-stage-group">
                                        <div class="media-stage-label">
                                            <span class="msl-text" x-text="group.stage_label"></span>
                                            <span class="msl-date" x-text="group.date"></span>
                                        </div>
                                        <div class="media-grid-3">
                                            <template x-for="(m, mi) in group.media.slice(0, 3)" :key="m.id">
                                                <div class="media-thumb">
                                                    <img :src="m.url" :alt="m.type" loading="lazy" onerror="this.style.display='none'">
                                                    <template x-if="mi === 2 && group.media.length > 3">
                                                        <div class="media-thumb-more">+<span x-text="group.media.length - 3"></span></div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            {{-- View full profile --}}
                            <div style="padding:12px 16px;">
                                <a :href="'/patients/'+caseData.case?.patient_id"
                                   style="display:flex;align-items:center;justify-content:center;gap:6px;padding:9px;border:1px solid #e5e7eb;border-radius:7px;font-size:12px;font-weight:600;color:#374151;text-decoration:none;transition:all .15s;"
                                   onmouseover="this.style.borderColor='#6a0f70';this.style.color='#6a0f70'"
                                   onmouseout="this.style.borderColor='#e5e7eb';this.style.color='#374151'">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    View Full Patient Profile
                                </a>
                            </div>

                            {{-- Watermark preview --}}
                            <div class="wm-preview">
                                <div class="wm-icon">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                </div>
                                <div class="wm-text">
                                    <div class="wm-clinic">Watermark Preview</div>
                                    <div class="wm-sub" id="wm-clinic-preview">{{ config('app.name', 'Tulip Dental') }}</div>
                                </div>
                                <div style="text-align:right;">
                                    <div class="wm-sub" x-text="caseData.doctor?.name ?? ''"></div>
                                    <div class="wm-sub" x-text="caseData.patient?.name ?? ''"></div>
                                    <div class="wm-sub">{{ now()->format('d M Y') }}</div>
                                </div>
                            </div>
                        </div>

                        {{-- Timeline Tab --}}
                        <div x-show="drawerTab==='timeline'" style="padding:14px 16px;">
                            <template x-if="caseData.timeline && caseData.timeline.length > 0">
                                <div>
                                    <template x-for="(entry, ei) in caseData.timeline" :key="entry.key">
                                        <div style="display:flex;gap:10px;margin-bottom:16px;">
                                            <div style="display:flex;flex-direction:column;align-items:center;gap:0;">
                                                <div style="width:28px;height:28px;border-radius:50%;background:#6a0f70;color:white;font-size:9px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;">D<span x-text="entry.day_num"></span></div>
                                                <div style="flex:1;width:1.5px;background:#e5e7eb;min-height:20px;"></div>
                                            </div>
                                            <div style="flex:1;padding-bottom:4px;">
                                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                                                    <span style="font-size:11px;font-weight:700;color:#374151;" x-text="entry.stage_label"></span>
                                                    <span style="font-size:10px;color:#9ca3af;" x-text="entry.date"></span>
                                                </div>
                                                <div class="media-grid-3" style="grid-template-columns:repeat(3,1fr);">
                                                    <template x-for="(m, mi) in entry.media.slice(0,3)" :key="m.id">
                                                        <div class="media-thumb" style="border-radius:4px;">
                                                            <img :src="m.url" loading="lazy" onerror="this.parentElement.style.background='#f3f4f6'">
                                                        </div>
                                                    </template>
                                                </div>
                                                <div x-show="entry.media.length > 3" style="font-size:10px;color:#9ca3af;margin-top:4px;" x-text="'+ '+(entry.media.length-3)+' more files'"></div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <template x-if="!caseData.timeline || caseData.timeline.length === 0">
                                <div style="text-align:center;padding:30px;color:#9ca3af;font-size:12px;">No timeline entries yet.</div>
                            </template>
                        </div>

                        {{-- Visit History / Notes stubs --}}
                        <div x-show="drawerTab==='visits'" style="padding:16px;font-size:12px;color:#9ca3af;text-align:center;">Visit history from consultation records will appear here.</div>
                        <div x-show="drawerTab==='notes'" style="padding:16px;">
                            <textarea style="width:100%;border:1px solid #e5e7eb;border-radius:6px;padding:10px;font-size:12px;font-family:'Inter',sans-serif;color:#374151;resize:vertical;outline:none;min-height:120px;" placeholder="Add case notes…"></textarea>
                        </div>
                    </div>
                </template>

                {{-- Empty state --}}
                <template x-if="!selectedCaseId">
                    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;flex:1;padding:40px;text-align:center;">
                        <div style="font-size:13px;font-weight:600;color:#374151;">Select a case</div>
                        <div style="font-size:11px;color:#9ca3af;margin-top:4px;">Click any row to view case details and media</div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- ══ GENERIC EDUCATION LIBRARY ══ --}}
    <div x-show="activeTab==='education'" x-cloak>
        <div class="edu-wrap">
            <div class="edu-cat-title">Browse by Category</div>
            <div class="edu-cats" x-show="eduData.categories">
                <div class="edu-cat-card" :class="eduCatFilter===null?'active':''" @click="eduCatFilter=null;filterEdu()">
                    <div class="edu-cat-icon" style="background:#f5f0ff;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    </div>
                    <div>
                        <div class="edu-cat-name">All Categories</div>
                        <div class="edu-cat-count" x-text="(eduData.categories?.reduce((s,c)=>s+(c.items_count||0),0) ?? 0)+' items'"></div>
                    </div>
                </div>
                <template x-for="cat in eduData.categories" :key="cat.id">
                    <div class="edu-cat-card" :class="eduCatFilter===cat.id?'active':''" @click="eduCatFilter=cat.id;filterEdu()">
                        <div class="edu-cat-icon" :style="'background:'+(cat.color||'#f5f0ff')+'22;'">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" :stroke="cat.color||'#6a0f70'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </div>
                        <div>
                            <div class="edu-cat-name" x-text="cat.name"></div>
                            <div class="edu-cat-count" x-text="(cat.items_count||0)+' Treatments'"></div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="edu-cat-title" x-text="eduCatFilter ? (eduData.categories?.find(c=>c.id===eduCatFilter)?.name+' Treatments') : 'All Treatments'"></div>
            <div class="edu-grid" x-show="eduItems.length > 0">
                <template x-for="item in eduItems" :key="item.id">
                    <div class="edu-card">
                        <div class="edu-card-thumb">
                            <img :src="item.thumbnail ?? 'https://placehold.co/400x225/f3f4f6/9ca3af?text='+encodeURIComponent(item.title)" :alt="item.title" loading="lazy">
                            <div :class="'edu-card-type-badge badge-'+(item.media_type==='xray'?'xray':item.media_type)" x-show="item.media_type">
                                <span x-text="item.media_type === 'xray' ? 'X-Ray' : (item.media_type?.charAt(0).toUpperCase()+item.media_type?.slice(1))"></span>
                            </div>
                            <template x-if="item.media_type === 'video'">
                                <div class="play-btn">
                                    <div class="play-circle">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="#374151" stroke="none"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                    </div>
                                </div>
                            </template>
                            <template x-if="item.duration">
                                <div class="duration-badge" x-text="Math.floor(item.duration/60)+':'+(String(item.duration%60).padStart(2,'0'))"></div>
                            </template>
                            <template x-if="!item.duration && (item.photo_count + item.xray_count + item.video_count) > 0">
                                <div class="duration-badge">
                                    <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" style="display:inline;margin-right:2px;"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                                    <span x-text="item.photo_count + item.xray_count + item.video_count"></span>
                                </div>
                            </template>
                        </div>
                        <div class="edu-card-body">
                            <div class="edu-card-title" x-text="item.title"></div>
                            <div class="edu-card-desc" x-text="item.description"></div>
                            <div class="edu-card-stats">
                                <div class="edu-stat" x-show="item.photo_count > 0"><strong x-text="item.photo_count"></strong><span>Photos</span></div>
                                <div class="edu-stat" x-show="item.xray_count > 0"><strong x-text="item.xray_count"></strong><span>X-Rays</span></div>
                                <div class="edu-stat" x-show="item.video_count > 0"><strong x-text="item.video_count"></strong><span>Videos</span></div>
                            </div>
                        </div>
                        <div class="edu-card-footer">
                            <button type="button" class="btn-cms-outline" style="padding:5px 14px;font-size:11px;">View</button>
                        </div>
                    </div>
                </template>
            </div>

            <div x-show="eduItems.length === 0 && activeTab==='education'" style="text-align:center;padding:60px;color:#9ca3af;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto 12px;display:block;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                No educational content yet. Add content from Settings → Clinical Library.
            </div>

            <div class="cms-footer-note" style="margin-top:24px;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                This library contains educational and reference content for clinical learning and patient education. All content is generic and not linked to individual patients.
            </div>
        </div>
    </div>

</div>

{{-- ══ WATERMARK SETTINGS MODAL ══ --}}
<div id="wm-settings-modal" style="display:none;position:fixed;inset:0;z-index:300;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
    <div style="background:white;border-radius:12px;width:520px;max-width:96vw;box-shadow:0 24px 64px rgba(0,0,0,.22);overflow:hidden;">
        <div style="padding:16px 20px;background:linear-gradient(135deg,#6a0f70,#380740);display:flex;align-items:center;justify-content:space-between;">
            <div style="font-size:14px;font-weight:700;color:white;">Watermark Settings</div>
            <button onclick="document.getElementById('wm-settings-modal').style.display='none'" style="background:rgba(255,255,255,.15);border:none;color:white;width:28px;height:28px;border-radius:50%;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;">×</button>
        </div>
        <form id="wm-settings-form" enctype="multipart/form-data">
            @csrf
            <div style="padding:20px;display:flex;flex-direction:column;gap:16px;">

                {{-- Logo upload --}}
                <div>
                    <label style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:6px;">Clinic Logo</label>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div id="wm-logo-preview" style="width:64px;height:64px;border:1.5px dashed #d1d5db;border-radius:8px;display:flex;align-items:center;justify-content:center;background:#fafafa;overflow:hidden;flex-shrink:0;">
                            @if(file_exists(storage_path('app/public/settings/watermark_logo.png')))
                                <img src="{{ asset('storage/settings/watermark_logo.png') }}?{{ time() }}" style="width:100%;height:100%;object-fit:contain;">
                            @else
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                            @endif
                        </div>
                        <div style="flex:1;">
                            <label style="display:flex;align-items:center;gap:6px;padding:7px 14px;border:1.5px dashed #d1d5db;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;color:#6b7280;transition:all .15s;background:white;"
                                   onmouseover="this.style.borderColor='#6a0f70';this.style.color='#6a0f70'"
                                   onmouseout="this.style.borderColor='#d1d5db';this.style.color='#6b7280'">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                Upload Logo (PNG recommended)
                                <input type="file" name="watermark_logo" id="wm-logo-input" accept="image/*" style="display:none;" onchange="previewWmLogo(this)">
                            </label>
                            <div style="font-size:10px;color:#9ca3af;margin-top:4px;">PNG with transparent background works best. Max 2MB.</div>
                        </div>
                    </div>
                </div>

                {{-- Text fields --}}
                @foreach(['clinic_name' => 'Clinic Name', 'doctor_name' => 'Doctor Name', 'patient_name' => 'Patient Name (optional)'] as $key => $label)
                <div>
                    <label style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:4px;">{{ $label }}</label>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="wm_show_{{ $key }}" id="wm_show_{{ $key }}" checked
                               style="accent-color:#6a0f70;width:13px;height:13px;cursor:pointer;flex-shrink:0;">
                        <input type="text" name="wm_{{ $key }}" id="wm_{{ $key }}"
                               placeholder="{{ $label }}"
                               value="{{ \App\Models\WatermarkSetting::get($key) }}"
                               style="flex:1;border:1px solid #e5e7eb;border-radius:5px;padding:7px 10px;font-size:12px;font-family:'Inter',sans-serif;outline:none;transition:border-color .15s;"
                               onfocus="this.style.borderColor='#6a0f70'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>
                </div>
                @endforeach

                {{-- Position --}}
                <div>
                    <label style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:6px;">Position</label>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        @foreach(['bottom-right' => 'Bottom Right', 'bottom-left' => 'Bottom Left', 'top-right' => 'Top Right', 'top-left' => 'Top Left'] as $val => $lbl)
                        <button type="button" onclick="setWmPosition('{{ $val }}', this)"
                                data-position="{{ $val }}"
                                style="padding:5px 12px;border:1.5px solid {{ $val==='bottom-right' ? '#6a0f70' : '#e5e7eb' }};border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;background:{{ $val==='bottom-right' ? '#6a0f70' : 'white' }};color:{{ $val==='bottom-right' ? 'white' : '#6b7280' }};transition:all .15s;">
                            {{ $lbl }}
                        </button>
                        @endforeach
                        <input type="hidden" name="wm_position" id="wm_position" value="bottom-right">
                    </div>
                </div>

                {{-- Opacity --}}
                <div>
                    <label style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:6px;">
                        Opacity — <span id="wm-opacity-val" style="color:#6a0f70;font-weight:800;">60%</span>
                    </label>
                    <input type="range" name="wm_opacity" id="wm_opacity" min="10" max="90" value="60"
                           style="width:100%;accent-color:#6a0f70;"
                           oninput="document.getElementById('wm-opacity-val').textContent=this.value+'%'">
                    <div style="display:flex;justify-content:space-between;font-size:9px;color:#d1d5db;margin-top:2px;"><span>Subtle</span><span>Strong</span></div>
                </div>

                {{-- Live preview --}}
                <div style="background:#1f2937;border-radius:8px;padding:12px 14px;">
                    <div style="font-size:9px;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px;">Live Preview</div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div id="wm-prev-logo" style="width:32px;height:32px;background:rgba(255,255,255,.1);border-radius:6px;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
                            @if(file_exists(storage_path('app/public/settings/watermark_logo.png')))
                                <img src="{{ asset('storage/settings/watermark_logo.png') }}?{{ time() }}" style="width:100%;height:100%;object-fit:contain;">
                            @else
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.4)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            @endif
                        </div>
                        <div>
                            <div id="wm-prev-clinic" style="font-size:11px;font-weight:700;color:white;">{{ \App\Models\WatermarkSetting::get('clinic_name') ?: config('app.name') }}</div>
                            <div id="wm-prev-doctor" style="font-size:9px;color:rgba(255,255,255,.5);margin-top:1px;">{{ \App\Models\WatermarkSetting::get('doctor_name') ?: 'Doctor Name' }}</div>
                            <div style="font-size:9px;color:rgba(255,255,255,.4);">{{ now()->format('d M Y') }}</div>
                        </div>
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;padding-top:4px;">
                    <button type="button" onclick="document.getElementById('wm-settings-modal').style.display='none'" class="btn-cms-outline">Cancel</button>
                    <button type="button" onclick="saveWmSettings()" class="btn-cms-primary">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Save Settings
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function cmsApp() {
    return {
        activeTab: 'clinical',
        selectedCaseId: null,
        caseData: null,
        drawerTab: 'overview',
        mediaTypeFilter: 'all',
        mediaGroups: [],
        filters: {
            search: '{{ request("search","") }}',
            patient_id: '{{ request("patient_id","") }}',
            tooth: '{{ request("tooth","") }}',
            treatment: '{{ request("treatment","") }}',
            doctor_id: '{{ request("doctor_id","") }}',
            date_from: '{{ request("date_from","") }}',
            tag: '{{ request("tag","") }}',
            sort: '{{ request("sort","start_date_desc") }}',
        },
        eduData: { categories: [], items: [] },
        eduItems: [],
        eduCatFilter: null,

        init() {
            // If URL has filters already set, show their pills
        },

        get activeFilterPills() {
            const pills = [];
            if (this.filters.tooth)      pills.push({ key:'tooth',     label:'Tooth No.: '+this.filters.tooth });
            if (this.filters.treatment)  pills.push({ key:'treatment', label:'Treatment: '+this.filters.treatment });
            if (this.filters.patient_id) pills.push({ key:'patient_id',label:'Patient filter active' });
            if (this.filters.date_from)  pills.push({ key:'date_from', label:'Date: from '+this.filters.date_from });
            if (this.filters.tag)        pills.push({ key:'tag',       label:'Tag: '+this.filters.tag });
            return pills;
        },

        removePill(key) {
            this.filters[key] = '';
            this.doSearch();
        },

        resetFilters() {
            Object.keys(this.filters).forEach(k => { if(k !== 'sort') this.filters[k] = ''; });
            this.doSearch();
        },

        doSearch() {
            const params = new URLSearchParams();
            Object.entries(this.filters).forEach(([k,v]) => { if(v) params.set(k,v); });
            window.location.href = '{{ route("cms.index") }}?' + params.toString();
        },

        async openCase(id) {
            this.selectedCaseId = id;
            this.drawerTab = 'overview';
            this.caseData = null;
            try {
                const r = await fetch('/cms/case/' + id, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
                this.caseData = await r.json();
                this.buildMediaGroups();
            } catch(e) {
                console.error(e);
            }
        },

        buildMediaGroups() {
            if (!this.caseData?.timeline) return;
            this.mediaGroups = this.caseData.timeline.map(entry => ({
                ...entry,
            }));
        },

        filterMedia() {
            if (!this.caseData) return;
            if (this.mediaTypeFilter === 'all') {
                this.buildMediaGroups();
            } else {
                this.mediaGroups = (this.caseData.timeline ?? []).map(entry => ({
                    ...entry,
                    media: (entry.media ?? []).filter(m => m.media_type === this.mediaTypeFilter || m.type === this.mediaTypeFilter),
                })).filter(g => g.media.length > 0);
            }
        },

        async loadEdu() {
            if (this.eduData.categories.length > 0) return;
            try {
                const r = await fetch('/cms/education', { headers: { 'Accept': 'application/json' } });
                this.eduData = await r.json();
                this.eduItems = this.eduData.items ?? [];
            } catch(e) {
                // Show empty state
            }
        },

        filterEdu() {
            if (!this.eduCatFilter) {
                this.eduItems = this.eduData.items ?? [];
            } else {
                this.eduItems = (this.eduData.items ?? []).filter(i => i.category_id === this.eduCatFilter);
            }
        },
    }
}

// ── Watermark Settings ──
function previewWmLogo(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const src = e.target.result;
        document.getElementById('wm-logo-preview').innerHTML = '<img src="'+src+'" style="width:100%;height:100%;object-fit:contain;">';
        document.getElementById('wm-prev-logo').innerHTML    = '<img src="'+src+'" style="width:100%;height:100%;object-fit:contain;">';
    };
    reader.readAsDataURL(input.files[0]);
}

function setWmPosition(val, btn) {
    document.getElementById('wm_position').value = val;
    document.querySelectorAll('[data-position]').forEach(b => {
        b.style.background  = 'white';
        b.style.color       = '#6b7280';
        b.style.borderColor = '#e5e7eb';
    });
    btn.style.background  = '#6a0f70';
    btn.style.color       = 'white';
    btn.style.borderColor = '#6a0f70';
}

// Live preview — update text as user types
['wm_clinic_name', 'wm_doctor_name'].forEach(id => {
    document.addEventListener('DOMContentLoaded', () => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', () => {
            if (id === 'wm_clinic_name') document.getElementById('wm-prev-clinic').textContent = el.value || '{{ config("app.name") }}';
            if (id === 'wm_doctor_name') document.getElementById('wm-prev-doctor').textContent = el.value || 'Doctor Name';
        });
    });
});

async function saveWmSettings() {
    const form = document.getElementById('wm-settings-form');
    const fd   = new FormData(form);
    try {
        const r = await fetch('{{ route("cms.watermark.save") }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: fd,
        });
        const d = await r.json();
        if (d.success) {
            document.getElementById('wm-settings-modal').style.display = 'none';
            window.DFLayout?.toast('Watermark settings saved.', 'success');
        }
    } catch(e) {
        window.DFLayout?.toast('Error saving settings.', 'error');
    }
}
</script>
@endpush