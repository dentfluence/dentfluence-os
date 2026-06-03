@extends('layouts.app')

@section('page-title', 'Clinical Library')

@section('head-extra')
<style>
    * { box-sizing: border-box; }
    [x-cloak] { display: none !important; }

    /* ── Page shell ── */
    #cms-shell { display: flex; flex-direction: column; height: 100%; min-height: 100vh; background: #f8f9fb; }

    /* ── Page header ── */
    #cms-header {
        background: white;
        border-bottom: 1px solid #e5e7eb;
        padding: 16px 24px 0;
        flex-shrink: 0;
    }
    .cms-header-top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 14px; }
    .cms-title { font-size: 22px; font-weight: 800; color: #111827; letter-spacing: -.03em; }
    .cms-subtitle { font-size: 12px; color: #9ca3af; margin-top: 2px; }
    .cms-header-actions { display: flex; align-items: center; gap: 8px; }
    .cms-btn-outline {
        display: flex; align-items: center; gap: 6px;
        padding: 7px 14px; font-size: 12px; font-weight: 600;
        border: 1px solid #e5e7eb; background: white; color: #374151;
        border-radius: 6px; cursor: pointer; transition: all .15s;
    }
    .cms-btn-outline:hover { border-color: #6a0f70; color: #6a0f70; background: #faf5fb; }

    /* ── Tabs ── */
    .cms-tabs { display: flex; gap: 0; border-bottom: none; }
    .cms-tab {
        padding: 10px 20px; font-size: 13px; font-weight: 600; color: #9ca3af;
        border-bottom: 2px solid transparent; cursor: pointer; text-decoration: none;
        transition: all .15s; white-space: nowrap;
    }
    .cms-tab:hover { color: #6a0f70; }
    .cms-tab.active { color: #6a0f70; border-bottom-color: #6a0f70; }

    /* ── Filter card ── */
    #filter-card {
        background: white; border: 1px solid #e5e7eb; border-radius: 10px;
        padding: 16px 18px; margin: 18px 20px 0;
    }
    .filter-row { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap; }
    .filter-group { display: flex; flex-direction: column; gap: 4px; }
    .filter-label { font-size: 10px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .05em; }
    .filter-select, .filter-input {
        border: 1px solid #e5e7eb; border-radius: 6px; padding: 7px 10px; font-size: 12px;
        color: #374151; background: white; outline: none; transition: border-color .15s;
        font-family: inherit;
    }
    .filter-select:focus, .filter-input:focus { border-color: #6a0f70; }
    .filter-select { min-width: 160px; }
    .filter-input { width: 220px; }
    .filter-divider { width: 1px; height: 36px; background: #f3f4f6; flex-shrink: 0; align-self: flex-end; }
    .cms-btn-primary {
        display: flex; align-items: center; gap: 6px;
        padding: 8px 18px; font-size: 12px; font-weight: 700;
        background: #6a0f70; color: white; border: none; border-radius: 6px;
        cursor: pointer; transition: background .15s; white-space: nowrap;
    }
    .cms-btn-primary:hover { background: #380740; }
    .cms-btn-reset {
        padding: 8px 14px; font-size: 12px; font-weight: 600;
        border: 1px solid #e5e7eb; background: white; color: #6b7280;
        border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 5px;
        transition: all .15s;
    }
    .cms-btn-reset:hover { border-color: #fca5a5; color: #dc2626; }

    /* More filters row */
    .more-filters-row { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap; margin-top: 10px; padding-top: 10px; border-top: 1px solid #f3f4f6; }

    /* Active filter chips */
    #filter-chips { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin: 10px 20px 0; }
    .filter-chip {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 4px 10px; background: #f5f3ff; border: 1px solid #e9d5ff;
        border-radius: 99px; font-size: 11px; font-weight: 600; color: #6a0f70;
    }
    .filter-chip button { background: none; border: none; color: #9ca3af; cursor: pointer; font-size: 13px; line-height: 1; padding: 0 0 0 2px; }
    .filter-chip button:hover { color: #dc2626; }
    .chip-clear-all { font-size: 11px; font-weight: 600; color: #dc2626; background: none; border: none; cursor: pointer; padding: 4px 8px; }
    .chip-clear-all:hover { text-decoration: underline; }

    /* ── Results area ── */
    #results-area { padding: 14px 20px 20px; flex: 1; }
    .results-meta { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
    .results-count { font-size: 12px; color: #9ca3af; }
    .results-count span { font-weight: 700; color: #374151; }
    .sort-row { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #9ca3af; }

    /* ── Cases table ── */
    .cms-table-wrap { background: white; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
    .cms-table { width: 100%; border-collapse: collapse; }
    .cms-table th {
        text-align: left; padding: 10px 14px; font-size: 10px; font-weight: 700;
        color: #9ca3af; text-transform: uppercase; letter-spacing: .05em;
        background: #f9fafb; border-bottom: 1px solid #e5e7eb;
    }
    .cms-table td { padding: 10px 14px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; font-size: 13px; color: #374151; }
    .cms-table tbody tr { cursor: pointer; transition: background .1s; }
    .cms-table tbody tr:hover td { background: #faf5fb; }
    .cms-table tbody tr.active td { background: #f5f3ff; }
    .cms-table tbody tr:last-child td { border-bottom: none; }

    .pt-avatar {
        width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #6a0f70, #380740);
        color: white; font-size: 13px; font-weight: 700; display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; font-family: 'Cormorant Garamond', serif;
    }
    .pt-name { font-weight: 700; color: #111827; font-size: 13px; }
    .pt-sub { font-size: 10px; color: #9ca3af; margin-top: 1px; }

    .status-badge { padding: 3px 9px; border-radius: 99px; font-size: 10px; font-weight: 700; white-space: nowrap; }
    .status-completed { background: #dcfce7; color: #16a34a; }
    .status-ongoing   { background: #fff7ed; color: #d97706; }
    .status-planned   { background: #eff6ff; color: #2563eb; }

    .media-count { display: flex; align-items: center; gap: 4px; font-size: 12px; color: #6b7280; font-weight: 600; }

    /* Treatment icon */
    .tx-icon { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; margin-right: 6px; flex-shrink: 0; }

    /* ── Pagination ── */
    .cms-pagination { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-top: 1px solid #f3f4f6; background: white; }
    .pag-info { font-size: 11px; color: #9ca3af; }
    .pag-btns { display: flex; align-items: center; gap: 3px; }
    .pag-btn {
        width: 30px; height: 30px; border-radius: 5px; border: 1px solid #e5e7eb;
        background: white; color: #374151; font-size: 12px; font-weight: 600;
        display: flex; align-items: center; justify-content: center; cursor: pointer;
        transition: all .12s; text-decoration: none;
    }
    .pag-btn:hover { border-color: #6a0f70; color: #6a0f70; }
    .pag-btn.active { background: #6a0f70; border-color: #6a0f70; color: white; }
    .pag-btn.disabled { opacity: .35; pointer-events: none; }
    .pag-per-page { display: flex; align-items: center; gap: 6px; font-size: 11px; color: #9ca3af; }

    /* ── Case Viewer Slide Panel ── */
    #case-viewer-overlay {
        position: fixed; inset: 0; z-index: 100; pointer-events: none;
        background: rgba(0,0,0,0); transition: background .25s;
    }
    #case-viewer-overlay.open { background: rgba(14,1,24,.35); pointer-events: all; }

    #case-viewer-panel {
        position: fixed; top: 0; right: -520px; bottom: 0; width: 520px; max-width: 96vw;
        background: white; box-shadow: -8px 0 40px rgba(0,0,0,.18);
        z-index: 101; display: flex; flex-direction: column;
        transition: right .3s cubic-bezier(.4,0,.2,1);
        overflow: hidden;
    }
    #case-viewer-panel.open { right: 0; }

    .cv-header {
        padding: 16px 20px 12px; border-bottom: 1px solid #e5e7eb;
        background: white; flex-shrink: 0;
    }
    .cv-title-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
    .cv-patient-name { font-size: 17px; font-weight: 800; color: #111827; }
    .cv-meta { display: flex; align-items: center; gap: 8px; margin-top: 4px; flex-wrap: wrap; }
    .cv-meta span { font-size: 11px; color: #9ca3af; }
    .cv-meta .cv-sep { width: 3px; height: 3px; background: #d1d5db; border-radius: 50%; }
    .cv-close {
        width: 28px; height: 28px; border-radius: 50%; background: #f3f4f6; border: none;
        cursor: pointer; color: #9ca3af; display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; transition: all .12s;
    }
    .cv-close:hover { background: #fee2e2; color: #dc2626; }

    /* Viewer tabs */
    .cv-tabs { display: flex; border-bottom: 1px solid #f3f4f6; padding: 0 20px; margin-top: 4px; }
    .cv-tab {
        padding: 8px 14px; font-size: 12px; font-weight: 600; color: #9ca3af;
        border-bottom: 2px solid transparent; cursor: pointer; transition: all .15s;
    }
    .cv-tab:hover { color: #6a0f70; }
    .cv-tab.active { color: #6a0f70; border-bottom-color: #6a0f70; }

    .cv-body { flex: 1; overflow-y: auto; padding: 16px 20px; }

    /* Case details table */
    .case-detail-row { display: flex; padding: 6px 0; border-bottom: 1px solid #f9fafb; }
    .case-detail-row:last-child { border-bottom: none; }
    .case-detail-label { width: 130px; flex-shrink: 0; font-size: 11px; color: #9ca3af; }
    .case-detail-val { font-size: 12px; font-weight: 600; color: #374151; }
    .case-detail-val a { color: #6a0f70; text-decoration: none; font-weight: 700; }
    .case-detail-val a:hover { text-decoration: underline; }

    /* Tag pills */
    .tag-pill { display: inline-flex; align-items: center; padding: 2px 8px; background: #f5f3ff; border: 1px solid #e9d5ff; border-radius: 99px; font-size: 10px; font-weight: 600; color: #6a0f70; margin: 2px; }

    /* Media gallery */
    .gallery-section { margin-bottom: 16px; }
    .gallery-section-head {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 8px; padding-bottom: 5px; border-bottom: 1px solid #f3f4f6;
    }
    .gallery-section-label { font-size: 11px; font-weight: 700; color: #374151; display: flex; align-items: center; gap: 6px; }
    .gallery-section-date { font-size: 10px; color: #9ca3af; }
    .gallery-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
    .gallery-thumb {
        aspect-ratio: 1; border-radius: 6px; overflow: hidden; position: relative;
        background: #f3f4f6; cursor: pointer;
    }
    .gallery-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .gallery-thumb-overlay {
        position: absolute; inset: 0; background: rgba(0,0,0,.45);
        display: flex; align-items: center; justify-content: center;
        opacity: 0; transition: opacity .15s; border-radius: 6px;
    }
    .gallery-thumb:hover .gallery-thumb-overlay { opacity: 1; }
    .gallery-thumb-more {
        position: absolute; inset: 0; background: rgba(14,1,24,.6);
        display: flex; align-items: center; justify-content: center;
        font-size: 16px; font-weight: 800; color: white; border-radius: 6px;
    }

    /* Media type tabs in gallery */
    .media-type-tabs { display: flex; gap: 4px; margin-bottom: 12px; flex-wrap: wrap; }
    .media-type-tab {
        padding: 4px 12px; border-radius: 99px; font-size: 11px; font-weight: 600;
        border: 1.5px solid #e5e7eb; background: white; color: #6b7280; cursor: pointer;
        transition: all .12s;
    }
    .media-type-tab:hover { border-color: #b95cb7; color: #6a0f70; }
    .media-type-tab.active { background: #6a0f70; border-color: #380740; color: white; }

    /* Timeline entries */
    .timeline-entry { display: flex; gap: 12px; margin-bottom: 14px; }
    .timeline-dot-col { display: flex; flex-direction: column; align-items: center; }
    .timeline-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; margin-top: 3px; }
    .timeline-line { flex: 1; width: 1px; background: #f3f4f6; margin: 3px 0; }
    .timeline-content { flex: 1; padding-bottom: 6px; }
    .timeline-day { font-size: 11px; font-weight: 800; color: #374151; }
    .timeline-date { font-size: 10px; color: #9ca3af; }
    .timeline-stage-badge { display: inline-block; padding: 1px 8px; border-radius: 99px; font-size: 9px; font-weight: 700; margin-left: 6px; }

    /* Watermark footer */
    .cv-footer {
        border-top: 1px solid #f3f4f6; padding: 10px 20px;
        display: flex; align-items: center; justify-content: space-between;
        background: white; flex-shrink: 0;
    }
    .wm-preview {
        display: flex; align-items: center; gap: 8px; padding: 7px 12px;
        background: #1c1c2e; border-radius: 6px; min-width: 200px;
    }
    .wm-preview-icon { width: 24px; height: 24px; color: white; flex-shrink: 0; }
    .wm-preview-text { color: white; }
    .wm-clinic { font-size: 11px; font-weight: 700; }
    .wm-sub { font-size: 9px; opacity: .7; }

    /* Loading state */
    .cv-loading { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 200px; gap: 10px; }
    .cv-spinner { width: 32px; height: 32px; border: 3px solid #e9d5ff; border-top-color: #6a0f70; border-radius: 50%; animation: spin .8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Empty state */
    .cms-empty { text-align: center; padding: 48px 24px; }
    .cms-empty-icon { width: 56px; height: 56px; margin: 0 auto 14px; background: #f5f3ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; }

    /* Responsive */
    @media (max-width: 900px) {
        .filter-row { flex-direction: column; }
        .filter-select { min-width: 100%; }
        #case-viewer-panel { width: 100%; }
    }
</style>
@endsection

@section('content')
<div id="cms-shell" x-data="cmsApp()" x-init="init()">

    {{-- ══ PAGE HEADER ══ --}}
    <div id="cms-header">
        <div class="cms-header-top">
            <div>
                <div class="cms-title">Clinical Library</div>
                <div class="cms-subtitle">Search and explore clinical cases and educational content</div>
            </div>
            <div class="cms-header-actions">
                <button class="cms-btn-outline" onclick="document.getElementById('watermark-settings-modal').style.display='flex'">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/></svg>
                    Watermark Settings
                </button>
                <button class="cms-btn-outline">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export
                </button>
            </div>
        </div>
        <div class="cms-tabs">
            <a href="{{ route('cms.index') }}" class="cms-tab active">Patient Clinical Data</a>
           <a href="{{ route('cms.education') }}" class="cms-tab">Generic Education Library</a>
<a href="{{ route('cms.education.manage') }}" class="cms-tab">Manage Content</a>
        </div>
    </div>

    {{-- ══ FILTER CARD ══ --}}
    <div id="filter-card">
        <form method="GET" action="{{ route('cms.index') }}" id="filter-form">
            {{-- Search bar --}}
            <div style="position:relative;margin-bottom:12px;">
                <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9ca3af;" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
                       class="filter-input" style="width:100%;padding-left:32px;" placeholder="Search anything — patient name, treatment, tooth no., tags…"
                       autocomplete="off">
            </div>

            {{-- Main filter row --}}
            <div class="filter-row">
                <div class="filter-group">
                    <span class="filter-label">Patient Name</span>
                    <select name="patient_id" class="filter-select">
                        <option value="">All Patients</option>
                        @foreach($patients as $p)
                        <option value="{{ $p->id }}" {{ ($filters['patient_id'] ?? '') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-group">
                    <span class="filter-label">Tooth No.</span>
                    <select name="tooth" class="filter-select" style="min-width:130px;">
                        <option value="">All Teeth</option>
                        @foreach($toothOptions as $t)
                        <option value="{{ $t }}" {{ ($filters['tooth'] ?? '') == $t ? 'selected' : '' }}>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-group">
                    <span class="filter-label">Treatment</span>
                    <select name="treatment" class="filter-select">
                        <option value="">All Treatments</option>
                        @foreach($treatmentOptions as $t)
                        <option value="{{ $t }}" {{ ($filters['treatment'] ?? '') == $t ? 'selected' : '' }}>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-divider"></div>
                <button type="button" class="cms-btn-outline" style="align-self:flex-end;"
                        @click="showMoreFilters=!showMoreFilters">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/></svg>
                    More Filters
                    <span x-show="activeMoreFiltersCount > 0" style="background:#6a0f70;color:white;border-radius:99px;padding:0 5px;font-size:10px;" x-text="activeMoreFiltersCount"></span>
                </button>
                <div style="margin-left:auto;display:flex;gap:6px;align-self:flex-end;">
                    <button type="button" class="cms-btn-reset" onclick="window.location='{{ route('cms.index') }}'">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.95"/></svg>
                        Reset
                    </button>
                    <button type="submit" class="cms-btn-primary">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        Search
                    </button>
                </div>
            </div>

            {{-- More Filters ── --}}
            <div class="more-filters-row" x-show="showMoreFilters" x-cloak>
                <div class="filter-group">
                    <span class="filter-label">Date Range</span>
                    <select name="date_range" class="filter-select" style="min-width:160px;" x-model="dateRange">
                        <option value="">All Time</option>
                        <option value="30d">Last 30 Days</option>
                        <option value="90d">Last 3 Months</option>
                        <option value="6m">Last 6 Months</option>
                        <option value="1y">Last 1 Year</option>
                        <option value="2y">Last 2 Years</option>
                    </select>
                </div>
                <div class="filter-group">
                    <span class="filter-label">Doctor</span>
                    <select name="doctor_id" class="filter-select">
                        <option value="">All Doctors</option>
                        @foreach($doctors as $d)
                        <option value="{{ $d->id }}" {{ ($filters['doctor_id'] ?? '') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-group">
                    <span class="filter-label">Tags</span>
                    <select name="tag" class="filter-select">
                        <option value="">All Tags</option>
                        @foreach($tagOptions as $tag)
                        <option value="{{ $tag }}" {{ ($filters['tag'] ?? '') == $tag ? 'selected' : '' }}>{{ $tag }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </form>
    </div>

    {{-- ══ ACTIVE FILTER CHIPS ══ --}}
    @php
    $activeChips = [];
    if (!empty($filters['tooth']))      $activeChips[] = ['label' => 'Tooth No.: '.$filters['tooth'],    'param' => 'tooth'];
    if (!empty($filters['treatment']))  $activeChips[] = ['label' => 'Treatment: '.$filters['treatment'],'param' => 'treatment'];
    if (!empty($filters['date_range'])) $activeChips[] = ['label' => 'Date: '.match($filters['date_range']){'30d'=>'Last 30 Days','90d'=>'Last 3 Months','6m'=>'Last 6 Months','1y'=>'Last 1 Year','2y'=>'Last 2 Years',default=>$filters['date_range']}, 'param' => 'date_range'];
    if (!empty($filters['doctor_id']))  $activeChips[] = ['label' => 'Doctor: '.($doctors->firstWhere('id',$filters['doctor_id'])->name ?? ''), 'param' => 'doctor_id'];
    if (!empty($filters['tag']))        $activeChips[] = ['label' => 'Tag: '.$filters['tag'], 'param' => 'tag'];
    if (!empty($filters['patient_id'])) $activeChips[] = ['label' => 'Patient: '.($patients->firstWhere('id',$filters['patient_id'])->name ?? ''), 'param' => 'patient_id'];
    @endphp
    @if(count($activeChips) > 0)
    <div id="filter-chips">
        @foreach($activeChips as $chip)
        <div class="filter-chip">
            {{ $chip['label'] }}
            <a href="{{ request()->fullUrlWithoutQuery($chip['param']) }}" style="color:#9ca3af;line-height:1;text-decoration:none;font-size:14px;padding-left:3px;">×</a>
        </div>
        @endforeach
        <a href="{{ route('cms.index') }}" class="chip-clear-all">Clear All</a>
    </div>
    @endif

    {{-- ══ RESULTS ══ --}}
    <div id="results-area">
        <div class="results-meta">
            <div class="results-count">
                Showing <span>{{ $cases->firstItem() ?? 0 }}-{{ $cases->lastItem() ?? 0 }}</span>
                of <span>{{ $cases->total() }}</span> results
            </div>
            <div class="sort-row">
                Sort by
                <form method="GET" action="{{ route('cms.index') }}" id="sort-form" style="display:inline;">
                    @foreach(request()->except('sort') as $k => $v)
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endforeach
                    <select name="sort" onchange="document.getElementById('sort-form').submit()"
                            style="border:1px solid #e5e7eb;border-radius:5px;padding:4px 8px;font-size:12px;color:#374151;background:white;outline:none;cursor:pointer;">
                        <option value="start_date_desc" {{ ($filters['sort'] ?? '') === 'start_date_desc' ? 'selected' : '' }}>Start Date (Newest)</option>
                        <option value="start_date_asc"  {{ ($filters['sort'] ?? '') === 'start_date_asc'  ? 'selected' : '' }}>Start Date (Oldest)</option>
                        <option value="patient_asc"     {{ ($filters['sort'] ?? '') === 'patient_asc'     ? 'selected' : '' }}>Patient A-Z</option>
                        <option value="media_count_desc"{{ ($filters['sort'] ?? '') === 'media_count_desc'? 'selected' : '' }}>Most Media</option>
                    </select>
                </form>
            </div>
        </div>

        @if($cases->isEmpty())
        <div class="cms-table-wrap">
            <div class="cms-empty">
                <div class="cms-empty-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                </div>
                <div style="font-size:14px;font-weight:700;color:#374151;margin-bottom:4px;">No cases found</div>
                <div style="font-size:12px;color:#9ca3af;">Try adjusting your search filters or <a href="{{ route('cms.index') }}" style="color:#6a0f70;font-weight:600;">clear all filters</a></div>
            </div>
        </div>
        @else
        <div class="cms-table-wrap">
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
                        <th style="width:40px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cases as $case)
                    @php
                    $initials = collect(explode(' ', $case->patient_name))->map(fn($w)=>strtoupper(substr($w,0,1)))->take(2)->implode('');
                    $status = $case->completion_date ? 'completed' : ($case->last_date ? 'ongoing' : 'planned');
                    @endphp
                    <tr @click="openCaseViewer({{ $case->patient_id }}, '{{ addslashes($case->treatment_name) }}', '{{ $case->tooth_no }}')"
                        :class="activeCase?.patient_id === {{ $case->patient_id }} && activeCase?.treatment === '{{ addslashes($case->treatment_name) }}' ? 'active' : ''">
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div class="pt-avatar">{{ $initials }}</div>
                                <div>
                                    <div class="pt-name">{{ $case->patient_name }}</div>
                                    <div class="pt-sub">{{ $case->age ? $case->age.' Y' : '' }}{{ $case->age && $case->gender ? ' / ' : '' }}{{ $case->gender ? ucfirst($case->gender) : '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:6px;">
                                <span style="font-size:14px;">🦷</span>
                                <span style="font-weight:600;">{{ $case->treatment_name ?? '—' }}</span>
                            </div>
                        </td>
                        <td style="font-weight:700;color:#374151;">{{ $case->tooth_no ?? '—' }}</td>
                        <td style="color:#6b7280;">{{ $case->start_date ? \Carbon\Carbon::parse($case->start_date)->format('d M Y') : '—' }}</td>
                        <td style="color:#6b7280;">{{ $case->completion_date ? \Carbon\Carbon::parse($case->completion_date)->format('d M Y') : '—' }}</td>
                        <td style="color:#6b7280;">{{ $case->last_followup_date ? \Carbon\Carbon::parse($case->last_followup_date)->format('d M Y') : '—' }}</td>
                        <td>
                            <div class="media-count">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                                {{ $case->media_count }}
                            </div>
                        </td>
                        <td>
                            <span class="status-badge status-{{ $status }}">{{ ucfirst($status) }}</span>
                        </td>
                        <td @click.stop="">
                            <button style="background:none;border:none;color:#d1d5db;cursor:pointer;padding:4px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Pagination --}}
            <div class="cms-pagination">
                <div class="pag-info">{{ $cases->firstItem() }}–{{ $cases->lastItem() }} of {{ $cases->total() }} results</div>
                <div class="pag-btns">
                    @if($cases->onFirstPage())
                    <span class="pag-btn disabled">‹</span>
                    @else
                    <a href="{{ $cases->previousPageUrl() }}" class="pag-btn">‹</a>
                    @endif

                    @foreach($cases->getUrlRange(max(1,$cases->currentPage()-2), min($cases->lastPage(),$cases->currentPage()+2)) as $page => $url)
                    <a href="{{ $url }}" class="pag-btn {{ $page == $cases->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endforeach

                    @if($cases->hasMorePages())
                    <a href="{{ $cases->nextPageUrl() }}" class="pag-btn">›</a>
                    @else
                    <span class="pag-btn disabled">›</span>
                    @endif
                </div>
                <div class="pag-per-page">
                    Rows per page:
                    <form method="GET" action="{{ route('cms.index') }}" style="display:inline;">
                        @foreach(request()->except('per_page') as $k => $v)
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endforeach
                        <select name="per_page" onchange="this.closest('form').submit()"
                                style="border:1px solid #e5e7eb;border-radius:4px;padding:3px 6px;font-size:11px;background:white;outline:none;">
                            @foreach([10,25,50,100] as $n)
                            <option value="{{ $n }}" {{ $cases->perPage() == $n ? 'selected' : '' }}>{{ $n }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </div>
        </div>
        @endif

        {{-- Watermark notice --}}
        <div style="margin-top:10px;display:flex;align-items:center;gap:6px;font-size:11px;color:#9ca3af;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            All media are automatically watermarked with clinic name, doctor name and patient name for security and branding.
        </div>
    </div>

    {{-- ══ CASE VIEWER OVERLAY ══ --}}
    <div id="case-viewer-overlay" :class="caseViewerOpen ? 'open' : ''" @click.self="closeCaseViewer()"></div>

    {{-- ══ CASE VIEWER PANEL ══ --}}
    <div id="case-viewer-panel" :class="caseViewerOpen ? 'open' : ''">
        {{-- Loading state --}}
        <template x-if="caseViewerLoading">
            <div style="display:flex;flex-direction:column;height:100%;">
                <div class="cv-header" style="border-bottom:1px solid #e5e7eb;">
                    <div class="cv-title-row">
                        <div style="width:150px;height:18px;background:#f3f4f6;border-radius:4px;"></div>
                        <button class="cv-close" @click="closeCaseViewer()">×</button>
                    </div>
                </div>
                <div class="cv-loading">
                    <div class="cv-spinner"></div>
                    <span style="font-size:12px;color:#9ca3af;">Loading case data…</span>
                </div>
            </div>
        </template>

        {{-- Loaded state --}}
        <template x-if="!caseViewerLoading && caseData">
            <div style="display:flex;flex-direction:column;height:100%;">
                {{-- Header --}}
                <div class="cv-header">
                    <div class="cv-title-row">
                        <div>
                            <div class="cv-patient-name" x-text="caseData.patient?.name"></div>
                            <div class="cv-meta">
                                <span x-text="caseData.patient?.age ? caseData.patient.age + ' Y' : ''"></span>
                                <div class="cv-sep" x-show="caseData.patient?.age && caseData.patient?.gender"></div>
                                <span x-text="caseData.patient?.gender ? caseData.patient.gender.charAt(0).toUpperCase()+caseData.patient.gender.slice(1) : ''"></span>
                                <div class="cv-sep" x-show="caseData.treatment"></div>
                                <span style="color:#374151;font-weight:600;" x-text="caseData.treatment"></span>
                                <span x-show="caseData.tooth">
                                    <div class="cv-sep" style="display:inline-block;"></div>
                                    Tooth <span x-text="caseData.tooth" style="font-weight:700;color:#6a0f70;"></span>
                                </span>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <span class="status-badge status-completed" x-show="caseData.completion_date">Completed</span>
                            <span class="status-badge status-ongoing"   x-show="!caseData.completion_date">Ongoing</span>
                            <button class="cv-close" @click="closeCaseViewer()">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="cv-tabs">
                        @foreach(['overview'=>'Case Overview','timeline'=>'Timeline','visits'=>'Visit History','notes'=>'Notes'] as $tabKey=>$tabLabel)
                        <div class="cv-tab" :class="cvTab==='{{ $tabKey }}' ? 'active' : ''" @click="cvTab='{{ $tabKey }}'">{{ $tabLabel }}</div>
                        @endforeach
                    </div>
                </div>

                {{-- Body --}}
                <div class="cv-body">

                    {{-- ── Case Overview tab ── --}}
                    <div x-show="cvTab==='overview'">
                        {{-- Case Details --}}
                        <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em;">Case Details</div>
                        <div style="background:#f9fafb;border-radius:8px;padding:10px 14px;margin-bottom:14px;">
                            <div class="case-detail-row">
                                <span class="case-detail-label">Start Date</span>
                                <span class="case-detail-val" x-text="caseData.start_date ? formatDate(caseData.start_date) : '—'"></span>
                            </div>
                            <div class="case-detail-row">
                                <span class="case-detail-label">Completion Date</span>
                                <span class="case-detail-val" x-text="caseData.completion_date ? formatDate(caseData.completion_date) : '—'"></span>
                            </div>
                            <div class="case-detail-row">
                                <span class="case-detail-label">Last Follow-up</span>
                                <span class="case-detail-val" x-text="caseData.last_followup ? formatDate(caseData.last_followup) : '—'"></span>
                            </div>
                            <div class="case-detail-row">
                                <span class="case-detail-label">Doctor</span>
                                <span class="case-detail-val" x-text="caseData.doctor_name || '—'"></span>
                            </div>
                            <div class="case-detail-row" x-show="caseData.visit_history?.length">
                                <span class="case-detail-label">Total Visits</span>
                                <span class="case-detail-val" x-text="caseData.visit_history?.length || '—'"></span>
                            </div>
                            <div class="case-detail-row" x-show="caseData.tags?.length">
                                <span class="case-detail-label">Tags</span>
                                <span class="case-detail-val">
                                    <template x-for="tag in caseData.tags" :key="tag">
                                        <span class="tag-pill" x-text="tag"></span>
                                    </template>
                                </span>
                            </div>
                        </div>

                        {{-- Media Gallery --}}
                        <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em;">Media Gallery</div>

                        {{-- Type filter tabs --}}
                        <div class="media-type-tabs">
                            <button class="media-type-tab" :class="galleryFilter==='all'?'active':''" @click="galleryFilter='all'">
                                All (<span x-text="caseData.media_count"></span>)
                            </button>
                            <button class="media-type-tab" :class="galleryFilter==='photos'?'active':''" @click="galleryFilter='photos'"
                                    x-show="caseData.counts?.photos > 0">
                                Photos (<span x-text="caseData.counts?.photos"></span>)
                            </button>
                            <button class="media-type-tab" :class="galleryFilter==='xrays'?'active':''" @click="galleryFilter='xrays'"
                                    x-show="caseData.counts?.xrays > 0">
                                X-Rays (<span x-text="caseData.counts?.xrays"></span>)
                            </button>
                            <button class="media-type-tab" :class="galleryFilter==='scans'?'active':''" @click="galleryFilter='scans'"
                                    x-show="caseData.counts?.scans > 0">
                                Scans (<span x-text="caseData.counts?.scans"></span>)
                            </button>
                            <button class="media-type-tab" :class="galleryFilter==='videos'?'active':''" @click="galleryFilter='videos'"
                                    x-show="caseData.counts?.videos > 0">
                                Videos (<span x-text="caseData.counts?.videos"></span>)
                            </button>
                        </div>

                        {{-- Staged gallery sections --}}
                        <template x-for="(stageItems, stage) in caseData.stages" :key="stage">
                            <div class="gallery-section" x-show="stageItems.length > 0">
                                <div class="gallery-section-head">
                                    <div class="gallery-section-label">
                                        <span :style="'width:8px;height:8px;border-radius:50%;background:'+stageColor(stage)+';display:inline-block;'"></span>
                                        <span x-text="stageLabel(stage)"></span>
                                    </div>
                                    <div class="gallery-section-date" x-text="stageItems[0]?.upload_date ?? ''"></div>
                                </div>
                                <div class="gallery-grid">
                                    <template x-for="(item, idx) in stageItems.slice(0, 3)" :key="item.id">
                                        <div class="gallery-thumb">
                                            <img :src="item.thumbnail_url || item.display_url" :alt="item.stage_label" loading="lazy">
                                            <div class="gallery-thumb-overlay">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                                            </div>
                                            <template x-if="idx === 2 && stageItems.length > 3">
                                                <div class="gallery-thumb-more">+<span x-text="stageItems.length - 3"></span></div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- ── Timeline tab ── --}}
                    <div x-show="cvTab==='timeline'">
                        <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:12px;text-transform:uppercase;letter-spacing:.05em;">Treatment Timeline</div>
                        <template x-for="(entry, i) in caseData.timeline" :key="i">
                            <div class="timeline-entry">
                                <div class="timeline-dot-col">
                                    <div class="timeline-dot" :style="'background:'+stageColor(entry.stage)"></div>
                                    <div class="timeline-line" x-show="i < caseData.timeline.length-1"></div>
                                </div>
                                <div class="timeline-content">
                                    <div style="display:flex;align-items:center;gap:4px;margin-bottom:3px;">
                                        <span class="timeline-day" x-text="entry.day_label"></span>
                                        <span class="timeline-stage-badge"
                                              :style="'background:'+stageColor(entry.stage)+'20;color:'+stageColor(entry.stage)+';'"
                                              x-text="entry.stage_label"></span>
                                    </div>
                                    <div class="timeline-date" x-text="entry.display_date"></div>
                                    <div style="font-size:10px;color:#9ca3af;margin-top:3px;"
                                         x-text="(entry.counts?.photos > 0 ? entry.counts.photos+' photo(s)' : '') + (entry.counts?.videos > 0 ? ' · '+entry.counts.videos+' video(s)' : '')"></div>
                                </div>
                            </div>
                        </template>
                        <div x-show="!caseData.timeline?.length" style="font-size:12px;color:#9ca3af;text-align:center;padding:24px;">
                            No timeline data available.
                        </div>
                    </div>

                    {{-- ── Visit History tab ── --}}
                    <div x-show="cvTab==='visits'">
                        <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:12px;text-transform:uppercase;letter-spacing:.05em;">Visit History</div>
                        <template x-for="(visit, i) in caseData.visit_history" :key="i">
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:#f9fafb;border-radius:7px;margin-bottom:6px;">
                                <div>
                                    <div style="font-size:12px;font-weight:700;color:#374151;" x-text="visit.date ? formatDate(visit.date) : 'Visit '+(i+1)"></div>
                                    <div style="font-size:10px;color:#9ca3af;" x-text="stageLabel(visit.stage)"></div>
                                </div>
                                <div style="font-size:11px;color:#6b7280;font-weight:600;" x-text="visit.media_count+' media'"></div>
                            </div>
                        </template>
                        <div x-show="!caseData.visit_history?.length" style="font-size:12px;color:#9ca3af;text-align:center;padding:24px;">
                            No visit history linked.
                        </div>
                    </div>

                    {{-- ── Notes tab ── --}}
                    <div x-show="cvTab==='notes'">
                        <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em;">Notes</div>
                        <div style="font-size:12px;color:#9ca3af;padding:24px;text-align:center;">
                            No notes on this case yet.
                        </div>
                    </div>

                </div>{{-- /cv-body --}}

                {{-- Footer with View Patient link + Watermark preview --}}
                <div class="cv-footer">
                    <a :href="'/patients/' + caseData.patient?.id"
                       style="display:flex;align-items:center;gap:6px;padding:8px 16px;border:1px solid #6a0f70;color:#6a0f70;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;transition:all .15s;"
                       onmouseover="this.style.background='#faf5fb'" onmouseout="this.style.background='white'">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        View Full Patient Profile
                    </a>
                    <div class="wm-preview">
                        <svg class="wm-preview-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="4"/><path d="m5.7 6.3-2.3-2.3"/><path d="m18.3 6.3 2.3-2.3"/><path d="m11 2v3"/><path d="m11 19v3"/><path d="M2 11h3"/><path d="M19 11h3"/></svg>
                        <div class="wm-preview-text">
                            <div class="wm-clinic">{{ config('cms.watermark.clinic_name', 'Tulip Dental') }}</div>
                            <div class="wm-sub" x-text="(caseData.doctor_name ? 'Dr. '+caseData.doctor_name : '') + (caseData.patient?.name ? ' | '+caseData.patient.name : '')"></div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>{{-- /case-viewer-panel --}}

</div>{{-- /cms-shell --}}
@endsection

@push('scripts')
<script>
function cmsApp() {
    return {
        showMoreFilters: {{ count(array_filter([$filters['date_range'] ?? '', $filters['doctor_id'] ?? '', $filters['tag'] ?? ''])) > 0 ? 'true' : 'false' }},
        dateRange: '{{ $filters['date_range'] ?? '' }}',
        caseViewerOpen: false,
        caseViewerLoading: false,
        caseData: null,
        activeCase: null,
        cvTab: 'overview',
        galleryFilter: 'all',

        get activeMoreFiltersCount() {
            let n = 0;
            if (this.dateRange) n++;
            return n;
        },

        init() {},

        async openCaseViewer(patientId, treatment, tooth) {
            this.activeCase = { patient_id: patientId, treatment, tooth };
            this.caseViewerOpen   = true;
            this.caseViewerLoading = true;
            this.caseData = null;
            this.cvTab = 'overview';
            this.galleryFilter = 'all';

            try {
                const url = new URL('{{ route("cms.case-viewer") }}', window.location.origin);
                url.searchParams.set('patient_id', patientId);
                if (treatment) url.searchParams.set('treatment', treatment);
                if (tooth)     url.searchParams.set('tooth', tooth);

                const res  = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                this.caseData = await res.json();
            } catch (e) {
                console.error('Case viewer error:', e);
            } finally {
                this.caseViewerLoading = false;
            }
        },

        closeCaseViewer() {
            this.caseViewerOpen = false;
            this.activeCase = null;
            setTimeout(() => { this.caseData = null; }, 300);
        },

        stageColor(stage) {
            const map = { before: '#2563eb', during: '#d97706', after: '#16a34a', followup: '#7c3aed' };
            return map[stage] || '#9ca3af';
        },

        stageLabel(stage) {
            const map = { before: 'Before Treatment', during: 'During Treatment', after: 'After Treatment', followup: 'Follow-up' };
            return map[stage] || (stage ? stage.charAt(0).toUpperCase()+stage.slice(1) : 'Unknown');
        },

        formatDate(dateStr) {
            if (!dateStr) return '—';
            try {
                return new Date(dateStr).toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'numeric' });
            } catch { return dateStr; }
        },
    };
}
</script>
@endpush
