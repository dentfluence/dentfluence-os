@extends('layouts.app')

@section('page-title', 'Content Manager')

@section('head-extra')
<style>
    * { box-sizing: border-box; }
    [x-cloak] { display: none !important; }

    /* ══ PAGE SHELL ══ */
    #cm-shell {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        background: #f0f0f5;
    }

    /* ══ PAGE HEADER ══ */
    #cm-header {
        background: white;
        border-bottom: 1px solid #e5e7eb;
        padding: 16px 24px 0;
        flex-shrink: 0;
        position: sticky;
        top: 0;
        z-index: 50;
    }
    .cm-header-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 14px;
    }
    .cm-title { font-size: 22px; font-weight: 800; color: #111827; letter-spacing: -.03em; }
    .cm-subtitle { font-size: 12px; color: #9ca3af; margin-top: 2px; }
    .cm-header-actions { display: flex; align-items: center; gap: 8px; }

    /* ══ BUTTONS ══ */
    .cm-btn-outline {
        display: flex; align-items: center; gap: 6px;
        padding: 7px 14px; font-size: 12px; font-weight: 600;
        border: 1px solid #e5e7eb; background: white; color: #374151;
        border-radius: 6px; cursor: pointer; transition: all .15s;
    }
    .cm-btn-outline:hover { border-color: #6a0f70; color: #6a0f70; background: #faf5fb; }
    .cm-btn-primary {
        display: flex; align-items: center; gap: 6px;
        padding: 8px 18px; font-size: 12px; font-weight: 700;
        background: #6a0f70; color: white; border: none; border-radius: 6px;
        cursor: pointer; transition: background .15s; white-space: nowrap;
    }
    .cm-btn-primary:hover { background: #380740; }

    /* ══ SUB-NAV TABS ══ */
    .cm-tabs { display: flex; gap: 0; border-bottom: none; }
    .cm-tab {
        display: flex; align-items: center; gap: 6px;
        padding: 10px 20px; font-size: 13px; font-weight: 600; color: #9ca3af;
        border-bottom: 2px solid transparent; cursor: pointer;
        transition: all .15s; white-space: nowrap; user-select: none;
    }
    .cm-tab:hover { color: #6a0f70; }
    .cm-tab.active { color: #6a0f70; border-bottom-color: #6a0f70; }
    .cm-tab-badge {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 18px; height: 18px; padding: 0 5px;
        background: #f3f4f6; color: #6b7280;
        border-radius: 99px; font-size: 10px; font-weight: 700;
        transition: all .15s;
    }
    .cm-tab.active .cm-tab-badge { background: #f5f3ff; color: #6a0f70; }

    /* ══ STICKY FILTER BAR ══ */
    #cm-filter-bar {
        background: white;
        border-bottom: 1px solid #e5e7eb;
        padding: 10px 24px;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        position: sticky;
        top: 113px; /* below header */
        z-index: 40;
    }
    .filter-group { display: flex; flex-direction: column; gap: 3px; }
    .filter-label {
        font-size: 9px; font-weight: 700; color: #9ca3af;
        text-transform: uppercase; letter-spacing: .06em;
    }
    .filter-select, .filter-input {
        border: 1px solid #e5e7eb; border-radius: 6px;
        padding: 6px 10px; font-size: 12px; color: #374151;
        background: white; outline: none; transition: border-color .15s;
        font-family: inherit;
    }
    .filter-select:focus, .filter-input:focus { border-color: #6a0f70; }
    .filter-divider { width: 1px; height: 34px; background: #f3f4f6; flex-shrink: 0; align-self: flex-end; }

    /* Approval filter — only visible on Marketing tab */
    .approval-filter { transition: opacity .2s; }

    /* ══ CONTENT AREA ══ */
    #cm-content { flex: 1; padding: 20px 24px 40px; }

    /* ══ RESULTS META ══ */
    .cm-results-meta {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 14px;
    }
    .cm-results-count { font-size: 12px; color: #9ca3af; }
    .cm-results-count strong { color: #374151; }
    .cm-view-toggle { display: flex; align-items: center; gap: 4px; }
    .cm-view-btn {
        width: 30px; height: 30px; border-radius: 5px;
        border: 1px solid #e5e7eb; background: white;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; color: #9ca3af; transition: all .12s;
    }
    .cm-view-btn:hover { border-color: #6a0f70; color: #6a0f70; }
    .cm-view-btn.active { background: #6a0f70; border-color: #6a0f70; color: white; }

    /* ══ PHOTO GRID ══ */
    .cm-photo-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 3px;
    }

    /* ══ PHOTO CARD (Google Photos style) ══ */
    .cm-card {
        position: relative;
        aspect-ratio: 1;
        overflow: hidden;
        background: #1c1c2e;
        cursor: pointer;
        border-radius: 2px;
    }
    .cm-card img {
        width: 100%; height: 100%;
        object-fit: cover; display: block;
        transition: transform .3s ease;
    }
    .cm-card:hover img { transform: scale(1.04); }

    /* Card overlay (shown on hover OR when selected) */
    .cm-card-overlay {
        position: absolute; inset: 0;
        background: linear-gradient(to bottom, rgba(0,0,0,.55) 0%, transparent 40%, transparent 60%, rgba(0,0,0,.65) 100%);
        opacity: 0; transition: opacity .2s;
        display: flex; flex-direction: column; justify-content: space-between;
        padding: 8px;
    }
    .cm-card:hover .cm-card-overlay,
    .cm-card.selected .cm-card-overlay { opacity: 1; }

    /* Checkbox in top-left */
    .cm-card-check {
        width: 20px; height: 20px; border-radius: 50%;
        border: 2px solid white; background: transparent;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; transition: all .15s; flex-shrink: 0;
    }
    .cm-card.selected .cm-card-check {
        background: #6a0f70; border-color: #6a0f70;
    }

    /* Approval badge in top-right */
    .cm-approval-badge {
        padding: 2px 7px; border-radius: 99px;
        font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em;
        backdrop-filter: blur(4px);
    }
    .badge-pending  { background: rgba(217,119,6,.85); color: #fff; }
    .badge-approved { background: rgba(22,163,74,.85);  color: #fff; }
    .badge-rejected { background: rgba(220,38,38,.85);  color: #fff; }

    /* Card footer (treatment + stage) */
    .cm-card-footer { display: flex; flex-direction: column; gap: 2px; }
    .cm-card-treatment { font-size: 10px; font-weight: 700; color: white; line-height: 1.2; }
    .cm-card-stage {
        display: inline-flex; align-items: center; gap: 3px;
        font-size: 9px; font-weight: 600; color: rgba(255,255,255,.75);
    }

    /* Hover quick-actions row */
    .cm-card-actions {
        display: flex; align-items: center; gap: 4px;
        position: absolute; bottom: 8px; right: 8px;
        opacity: 0; transition: opacity .2s;
    }
    .cm-card:hover .cm-card-actions { opacity: 1; }
    .cm-card.selected .cm-card-actions { opacity: 1; }
    .cm-card-action-btn {
        width: 26px; height: 26px; border-radius: 50%;
        background: rgba(255,255,255,.2); backdrop-filter: blur(4px);
        border: none; cursor: pointer; color: white;
        display: flex; align-items: center; justify-content: center;
        transition: background .15s;
    }
    .cm-card-action-btn:hover { background: rgba(255,255,255,.4); }

    /* Consent badge */
    .cm-consent-chip {
        display: inline-flex; align-items: center; gap: 3px;
        padding: 2px 6px; border-radius: 99px;
        font-size: 8px; font-weight: 700; text-transform: uppercase;
        position: absolute; top: 30px; left: 8px;
        backdrop-filter: blur(4px);
    }
    .consent-given    { background: rgba(16,185,129,.8); color: white; }
    .consent-pending  { background: rgba(245,158,11,.8); color: white; }

    /* ══ BATCH ACTION BAR ══ */
    #cm-batch-bar {
        position: fixed; bottom: 0; left: 0; right: 0;
        background: #1c1c2e; color: white;
        padding: 12px 24px;
        display: flex; align-items: center; justify-content: space-between;
        z-index: 200;
        transform: translateY(100%);
        transition: transform .25s cubic-bezier(.4,0,.2,1);
        border-top: 1px solid rgba(255,255,255,.1);
    }
    #cm-batch-bar.visible { transform: translateY(0); }
    .batch-count { font-size: 14px; font-weight: 700; }
    .batch-count span { color: #c084fc; }
    .batch-actions { display: flex; align-items: center; gap: 8px; }
    .batch-action-btn {
        padding: 7px 16px; border-radius: 6px; font-size: 12px; font-weight: 700;
        cursor: pointer; border: none; transition: all .15s;
    }
    .batch-approve { background: #16a34a; color: white; }
    .batch-approve:hover { background: #15803d; }
    .batch-reject  { background: #dc2626; color: white; }
    .batch-reject:hover  { background: #b91c1c; }
    .batch-download { background: rgba(255,255,255,.1); color: white; border: 1px solid rgba(255,255,255,.2); }
    .batch-download:hover { background: rgba(255,255,255,.2); }
    .batch-cancel { background: transparent; color: rgba(255,255,255,.5); font-size: 12px; cursor: pointer; border: none; }
    .batch-cancel:hover { color: white; }

    /* ══ SECTION GROUP HEADER ══ */
    .cm-group-header {
        display: flex; align-items: center; gap: 10px;
        margin-bottom: 8px; margin-top: 20px;
        padding-bottom: 6px; border-bottom: 1px solid #e5e7eb;
    }
    .cm-group-header:first-child { margin-top: 0; }
    .cm-group-label { font-size: 13px; font-weight: 800; color: #374151; }
    .cm-group-count { font-size: 11px; color: #9ca3af; font-weight: 600; }

    /* ══ EMPTY STATE ══ */
    .cm-empty {
        display: flex; flex-direction: column; align-items: center;
        justify-content: center; padding: 72px 24px; text-align: center;
        background: white; border-radius: 12px; border: 1px dashed #e5e7eb;
    }
    .cm-empty-icon {
        width: 64px; height: 64px; border-radius: 50%;
        background: #f5f3ff;
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 16px;
    }
    .cm-empty-title { font-size: 15px; font-weight: 700; color: #374151; margin-bottom: 6px; }
    .cm-empty-sub { font-size: 12px; color: #9ca3af; max-width: 280px; line-height: 1.5; }

    /* ══ SLIDE PANEL (Case Viewer) ══ */
    #cv-overlay {
        position: fixed; inset: 0; z-index: 100; pointer-events: none;
        background: rgba(0,0,0,0); transition: background .25s;
    }
    #cv-overlay.open { background: rgba(14,1,24,.4); pointer-events: all; }
    #cv-panel {
        position: fixed; top: 0; right: -560px; bottom: 0;
        width: 560px; max-width: 96vw;
        background: white; z-index: 101;
        display: flex; flex-direction: column;
        box-shadow: -8px 0 40px rgba(0,0,0,.18);
        transition: right .3s cubic-bezier(.4,0,.2,1);
        overflow: hidden;
    }
    #cv-panel.open { right: 0; }

    /* ══ EDUCATION SECTION ══ */
    .edu-section-title {
        font-size: 14px; font-weight: 800; color: #111827;
        margin-bottom: 14px; padding-bottom: 8px;
        border-bottom: 2px solid #f3f4f6;
        display: flex; align-items: center; gap: 8px;
    }
    .edu-section-badge {
        padding: 2px 8px; border-radius: 99px;
        font-size: 10px; font-weight: 700;
    }

    /* ══ PLACEHOLDER TAB ══ */
    .cm-coming-soon {
        display: flex; flex-direction: column; align-items: center;
        justify-content: center; padding: 80px 24px; text-align: center;
    }
    .coming-soon-icon {
        width: 72px; height: 72px; border-radius: 50%;
        background: linear-gradient(135deg, #f5f3ff, #fdf4ff);
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 20px;
        border: 2px dashed #e9d5ff;
    }

    /* ══ RESPONSIVE ══ */
    @media (max-width: 900px) {
        .cm-photo-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
        #cv-panel { width: 100%; right: -100%; }
        #cm-filter-bar { flex-wrap: wrap; }
    }
    @media (max-width: 600px) {
        .cm-photo-grid { grid-template-columns: repeat(3, 1fr); gap: 2px; }
    }
</style>
@endsection

@section('content')
<div id="cm-shell" x-data="cmApp()" x-init="init()" x-cloak>

    {{-- ══ PAGE HEADER ══ --}}
    <div id="cm-header">
        <div class="cm-header-top">
            <div style="display:flex;align-items:flex-start;gap:14px;">
                {{-- Back to Clinical Library --}}
                <a href="{{ route('cms.dashboard') }}"
                   title="Back to Clinical Library"
                   style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1px solid #e5e7eb;border-radius:6px;color:#6b7280;text-decoration:none;flex-shrink:0;margin-top:4px;transition:border-color .15s,color .15s;"
                   onmouseover="this.style.borderColor='#6a0f70';this.style.color='#6a0f70';"
                   onmouseout="this.style.borderColor='#e5e7eb';this.style.color='#6b7280';">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                </a>
                <div>
                    <div style="font-size:11px;font-weight:600;color:#9ca3af;letter-spacing:.04em;text-transform:uppercase;margin-bottom:2px;">Clinical Library</div>
                    <div class="cm-title">Content Manager</div>
                    <div class="cm-subtitle">Filter. Select. Use. — Everything here comes from your Clinical Files.</div>
                </div>
            </div>
            <div class="cm-header-actions">
                <button class="cm-btn-outline" @click="selectAll()">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    Select All
                </button>
                <button class="cm-btn-outline">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export
                </button>
            </div>
        </div>

        {{-- ── SUB-NAV TABS ── --}}
        <div class="cm-tabs">

            {{-- Marketing --}}
            <div class="cm-tab" :class="activeTab==='marketing' ? 'active' : ''" @click="switchTab('marketing')">
                Marketing
                <span class="cm-tab-badge" x-text="tabCounts['marketing']">{{ $tabCounts['marketing'] }}</span>
            </div>

            {{-- Education --}}
            <div class="cm-tab" :class="activeTab==='education' ? 'active' : ''" @click="switchTab('education')">
                Education
                <span class="cm-tab-badge" x-text="tabCounts['education']">{{ $tabCounts['education'] }}</span>
            </div>

            {{-- Case Library --}}
            <div class="cm-tab" :class="activeTab==='case-library' ? 'active' : ''" @click="switchTab('case-library')">
                Case Library
                <span class="cm-tab-badge" x-text="tabCounts['case-library']">{{ $tabCounts['case-library'] }}</span>
            </div>

            {{-- Teaching --}}
            <div class="cm-tab" :class="activeTab==='teaching' ? 'active' : ''" @click="switchTab('teaching')">
                Teaching
                <span class="cm-tab-badge" x-text="tabCounts['teaching']">{{ $tabCounts['teaching'] }}</span>
            </div>

            {{-- Research --}}
            <div class="cm-tab" :class="activeTab==='research' ? 'active' : ''" @click="switchTab('research')">
                Research
                <span class="cm-tab-badge" x-text="tabCounts['research']">{{ $tabCounts['research'] }}</span>
            </div>

        </div>
    </div>

    {{-- ══ STICKY FILTER BAR ══ --}}
    <div id="cm-filter-bar">

        {{-- Treatment type --}}
        <div class="filter-group">
            <span class="filter-label">Treatment</span>
            <select class="filter-select" style="min-width:150px;">
                <option value="">All Treatments</option>
                <option>Root Canal</option>
                <option>Implant</option>
                <option>Crown & Bridge</option>
                <option>Extraction</option>
                <option>Aligners</option>
                <option>Scaling & Polishing</option>
                <option>Whitening</option>
            </select>
        </div>

        {{-- Stage --}}
        <div class="filter-group">
            <span class="filter-label">Stage</span>
            <select class="filter-select" style="min-width:130px;">
                <option value="">All Stages</option>
                <option>Before</option>
                <option>During</option>
                <option>After</option>
                <option>Follow-up</option>
                <option>General</option>
            </select>
        </div>

        {{-- Approval status — only on Marketing tab --}}
        <div class="filter-group approval-filter" x-show="activeTab==='marketing'">
            <span class="filter-label">Approval</span>
            <select class="filter-select" style="min-width:130px;">
                <option value="">All Status</option>
                <option>Pending</option>
                <option>Approved</option>
                <option>Rejected</option>
            </select>
        </div>

        <div class="filter-divider"></div>

        {{-- Date range --}}
        <div class="filter-group">
            <span class="filter-label">Date</span>
            <select class="filter-select" style="min-width:130px;">
                <option value="">All Time</option>
                <option>Last 30 Days</option>
                <option>Last 3 Months</option>
                <option>Last 6 Months</option>
                <option>Last Year</option>
            </select>
        </div>

        {{-- Tags --}}
        <div class="filter-group">
            <span class="filter-label">Tag</span>
            <input type="text" class="filter-input" placeholder="Search tags…" style="width:150px;">
        </div>

        {{-- Sort --}}
        <div class="filter-group" style="margin-left:auto;">
            <span class="filter-label">Sort by</span>
            <select class="filter-select" style="min-width:140px;">
                <option>Newest First</option>
                <option>Oldest First</option>
                <option>Rating</option>
                <option>Treatment</option>
            </select>
        </div>

        {{-- Reset --}}
        <button class="cm-btn-outline" style="align-self:flex-end;">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.95"/></svg>
            Reset
        </button>

    </div>

    {{-- ══ CONTENT AREA ══ --}}
    <div id="cm-content">

        {{-- RESULTS META ROW --}}
        <div class="cm-results-meta">
            <div class="cm-results-count">
                <strong x-text="tabCounts[activeTab]"></strong>
                <span x-text="' files in ' + tabLabels[activeTab]"></span>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <span x-show="selectedCount > 0" style="font-size:12px;color:#6a0f70;font-weight:700;"
                      x-text="selectedCount + ' selected'"></span>
                <div class="cm-view-toggle">
                    <button class="cm-view-btn active" title="Grid view">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                    </button>
                    <button class="cm-view-btn" title="List view">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- ── TAB: MARKETING ── --}}
        <div x-show="activeTab==='marketing'" x-cloak>
            @include('content-management.partials.cm.marketing-tab')
        </div>

        {{-- ── TAB: EDUCATION ── --}}
        <div x-show="activeTab==='education'" x-cloak>
            @include('content-management.partials.cm.education-tab')
        </div>

        {{-- ── TAB: CASE LIBRARY ── --}}
        <div x-show="activeTab==='case-library'" x-cloak>
            @include('content-management.partials.cm.case-library-tab')
        </div>

        {{-- ── TAB: TEACHING ── --}}
        <div x-show="activeTab==='teaching'" x-cloak>
            @include('content-management.partials.cm.teaching-tab')
        </div>

        {{-- ── TAB: RESEARCH ── --}}
        <div x-show="activeTab==='research'" x-cloak>
            @include('content-management.partials.cm.research-tab')
        </div>

    </div>

    {{-- ══ BATCH ACTION BAR (fixed bottom) ══ --}}
    <div id="cm-batch-bar" :class="selectedCount > 0 ? 'visible' : ''">
        <div class="batch-count">
            <span x-text="selectedCount"></span> file<span x-show="selectedCount !== 1">s</span> selected
        </div>
        <div class="batch-actions">
            <button class="batch-action-btn batch-download">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;margin-right:4px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download
            </button>
            <button class="batch-action-btn batch-reject" x-show="activeTab==='marketing'">
                Reject Selected
            </button>
            <button class="batch-action-btn batch-approve" x-show="activeTab==='marketing'">
                ✓ Approve Selected
            </button>
            <button class="batch-cancel" @click="clearSelection()">✕ Cancel</button>
        </div>
    </div>

    {{-- ══ CASE VIEWER OVERLAY ══ --}}
    <div id="cv-overlay" :class="caseViewerOpen ? 'open' : ''" @click.self="closeCaseViewer()"></div>
    {{-- Case viewer panel is included in case-library-tab partial --}}

</div>{{-- /cm-shell --}}
@endsection

@push('scripts')
<script>
function cmApp() {
    return {
        /* ── State ── */
        activeTab: 'marketing',
        selectedIds: [],

        /* Phase 9: real counts from ClinicalLibraryController::index() */
        tabCounts: {!! json_encode($tabCounts) !!},
        tabLabels: {
            marketing:    'Marketing',
            education:    'Education',
            'case-library': 'Case Library',
            teaching:     'Teaching',
            research:     'Research',
        },

        /* Case viewer */
        caseViewerOpen: false,
        activeCaseId: null,

        /* ── Computed ── */
        get selectedCount() { return this.selectedIds.length; },

        /* ── Lifecycle ── */
        init() {
            // Listen for card toggle events dispatched by partials
            window.addEventListener('cm-toggle-select', e => this.toggleSelect(e.detail.id));
            window.addEventListener('cm-open-case',     e => this.openCaseViewer(e.detail.id));
        },

        /* ── Methods ── */
        switchTab(tab) {
            this.activeTab = tab;
            this.clearSelection();
        },

        toggleSelect(id) {
            const idx = this.selectedIds.indexOf(id);
            if (idx === -1) this.selectedIds.push(id);
            else this.selectedIds.splice(idx, 1);
        },

        isSelected(id) { return this.selectedIds.includes(id); },

        selectAll() {
            // Collect all visible card IDs from the DOM
            const cards = document.querySelectorAll('.cm-card[data-id]');
            this.selectedIds = Array.from(cards).map(c => c.dataset.id);
        },

        clearSelection() { this.selectedIds = []; },

        openCaseViewer(id) {
            this.activeCaseId = id;
            this.caseViewerOpen = true;
        },

        closeCaseViewer() {
            this.caseViewerOpen = false;
            setTimeout(() => { this.activeCaseId = null; }, 300);
        },
    };
}
</script>
@endpush
