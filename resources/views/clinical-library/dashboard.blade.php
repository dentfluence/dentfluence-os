{{--
|==========================================================================
| Clinical Library — Workspace Dashboard
| File: resources/views/clinical-library/dashboard.blade.php
| Route: GET /clinical-library  →  cms.dashboard
| Phase: 3 (UI prototype — all data is static placeholder)
|
| Sections:
|   1. Page Header (title + actions)
|   2. Stat Chips  (4 small muted chips — supporting info only)
|   3. Resume Work (6 recent patient cards — most prominent)
|   4. Two-column  (Recent Uploads | Needs Attention)
|   5. Quick Actions (3 action cards)
|==========================================================================
--}}
@extends('layouts.app')

@section('page-title', 'Clinical Library')

@section('content')

<div x-data="{
    searchOpen: false,
    uploadOpen: false
}">

{{-- ══════════════════════════════════════════════════════════════════════
     SECTION 1 — PAGE HEADER
══════════════════════════════════════════════════════════════════════ --}}
<div class="df-page-header">
    <div>
        <h1 class="df-page-title">Clinical Library</h1>
        <p class="df-page-subtitle">Upload, organise and track clinical files for each patient</p>
    </div>
    <div class="df-page-actions">
        {{-- Search button --}}
        <button
            @click="searchOpen = true"
            type="button"
            style="display:inline-flex;align-items:center;gap:7px;padding:8px 14px;font-size:13px;font-weight:500;color:#6a0f70;background:#f9f3fa;border:1px solid rgba(106,15,112,0.20);border-radius:3px;cursor:pointer;transition:background 0.15s;"
            onmouseover="this.style.background='#f3e8f4'"
            onmouseout="this.style.background='#f9f3fa'"
        >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Search Files
        </button>
        {{-- Upload button --}}
        <button
            @click="uploadOpen = true"
            type="button"
            style="display:inline-flex;align-items:center;gap:7px;padding:8px 16px;font-size:13px;font-weight:600;color:#ffffff;background:#6a0f70;border:1px solid #6a0f70;border-radius:3px;cursor:pointer;transition:background 0.15s;"
            onmouseover="this.style.background='#4e0a53'"
            onmouseout="this.style.background='#6a0f70'"
        >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Upload Files
        </button>
        {{-- Settings button --}}
        <a
            href="{{ route('settings.clinical-library') }}"
            title="Clinical Library Settings"
            style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;background:#f9f3fa;border:1px solid rgba(106,15,112,0.20);border-radius:3px;color:#6a0f70;text-decoration:none;transition:background 0.15s;"
            onmouseover="this.style.background='#f3e8f4'"
            onmouseout="this.style.background='#f9f3fa'"
        >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        </a>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     MODULE NAV BAR — orient the user within the Clinical Library module
══════════════════════════════════════════════════════════════════════ --}}
<div style="display:flex;align-items:center;gap:4px;margin-bottom:20px;padding:4px;background:#f9f3fa;border:1px solid rgba(185,92,183,0.12);border-radius:4px;width:fit-content;">

    @php
    $moduleNavItems = [
        ['label' => 'Dashboard',       'href' => route('cms.dashboard'),          'icon' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',  'active' => true],
        ['label' => 'Content Manager',  'href' => route('cms.index'),              'icon' => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',                         'active' => false],
        ['label' => 'Settings',        'href' => route('settings.clinical-library'), 'icon' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>', 'active' => false],
    ];
    @endphp

    @foreach($moduleNavItems as $nav)
    <a href="{{ $nav['href'] }}"
       style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;font-size:12.5px;font-weight:{{ $nav['active'] ? '600' : '500' }};color:{{ $nav['active'] ? '#6a0f70' : '#7a6080' }};background:{{ $nav['active'] ? '#ffffff' : 'transparent' }};border-radius:3px;text-decoration:none;transition:background 0.15s,color 0.15s;box-shadow:{{ $nav['active'] ? '0 1px 3px rgba(106,15,112,0.10)' : 'none' }};"
       onmouseover="if(!this.dataset.active){ this.style.background='rgba(255,255,255,0.6)'; this.style.color='#6a0f70'; }"
       onmouseout="if(!this.dataset.active){ this.style.background='transparent'; this.style.color='#7a6080'; }"
       data-active="{{ $nav['active'] ? '1' : '' }}"
    >
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $nav['icon'] !!}</svg>
        {{ $nav['label'] }}
    </a>
    @endforeach

</div>


{{-- ══════════════════════════════════════════════════════════════════════
     SECTION 2 — STAT CARDS (at-a-glance numbers)
══════════════════════════════════════════════════════════════════════ --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px;">

    @php
    /* Phase 9: real values from ClinicalLibraryController::dashboard() */
    $statCards = [
        ['label' => 'Total Files',        'value' => number_format($totalFiles),     'hint' => 'All clinical documents uploaded',  'color' => '#6a0f70', 'bg' => '#f9f3fa', 'icon' => '<path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/>'],
        ['label' => 'Patients with Files','value' => number_format($totalPatients),  'hint' => 'Patients who have at least 1 file',  'color' => '#1a5ea8', 'bg' => '#eef4fd', 'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>'],
        ['label' => 'Uploaded This Month','value' => number_format($filesThisMonth), 'hint' => 'New files added in ' . now()->format('M Y'), 'color' => '#065f46', 'bg' => '#ecfdf5', 'icon' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
        ['label' => 'Pending Review',     'value' => number_format($pendingReview),  'hint' => 'Files awaiting doctor sign-off',     'color' => '#92400e', 'bg' => '#fffbeb', 'icon' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'],
    ];
    @endphp

    @foreach($statCards as $card)
    <div style="background:{{ $card['bg'] }};border:1px solid rgba(0,0,0,0.06);border-radius:6px;padding:14px 16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <span style="font-size:11.5px;font-weight:600;color:#7a6080;letter-spacing:0.01em;">{{ $card['label'] }}</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="{{ $card['color'] }}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.7;">{!! $card['icon'] !!}</svg>
        </div>
        <div style="font-size:26px;font-weight:700;color:#1e0a2c;line-height:1;margin-bottom:4px;">{{ $card['value'] }}</div>
        <div style="font-size:11px;color:#9b8aaa;">{{ $card['hint'] }}</div>
    </div>
    @endforeach

</div>


{{-- ══════════════════════════════════════════════════════════════════════
     SECTION 3 — RESUME WORK (most prominent — 6 recent patient cards)
══════════════════════════════════════════════════════════════════════ --}}
<div class="df-card" style="margin-bottom:20px;">

    {{-- Section header --}}
    <div class="df-card-header">
        <div style="display:flex;align-items:center;gap:8px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            <div>
                <span style="font-size:13px;font-weight:600;color:#1e0a2c;">Continue Where You Left Off</span>
                <span style="font-size:11.5px;color:#9b8aaa;margin-left:8px;">Patients with recent file activity — click to open their files</span>
            </div>
        </div>
        <a href="#" style="font-size:12px;color:#6a0f70;text-decoration:none;font-weight:500;">View all patients →</a>
    </div>

    <div class="df-card-body" style="padding:16px 20px;">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px;">

            @if($recentPatients->isEmpty())
            <div style="padding:32px;text-align:center;color:#9b8aaa;font-size:13px;">
                No recent activity. Start by uploading clinical files for a patient.
            </div>
            @else
            @foreach($recentPatients as $p)
            <a href="{{ route('patients.show', $p['patient_id']) }}#documents"
               style="display:block;text-decoration:none;border:1px solid rgba(185,92,183,0.12);border-radius:3px;overflow:hidden;background:#ffffff;transition:box-shadow 0.15s,border-color 0.15s;"
               onmouseover="this.style.borderColor='rgba(106,15,112,0.30)';this.style.boxShadow='0 2px 8px rgba(106,15,112,0.08)'"
               onmouseout="this.style.borderColor='rgba(185,92,183,0.12)';this.style.boxShadow='none'">

                {{-- Thumbnail strip --}}
                <div style="height:64px;background:#f3e8f4;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;">
                    @if(!empty($p['thumb_url']))
                        <img src="{{ $p['thumb_url'] }}" alt="Thumbnail"
                             style="width:100%;height:100%;object-fit:cover;">
                    @else
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#9b59b6" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.5;"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    @endif
                    <span style="position:absolute;top:6px;right:6px;background:rgba(0,0,0,0.45);color:#fff;font-size:10px;font-weight:600;padding:2px 6px;border-radius:10px;">{{ $p['file_count'] }} files</span>
                </div>

                {{-- Patient info --}}
                <div style="padding:10px 12px;">
                    <div style="display:flex;align-items:center;gap:5px;margin-bottom:2px;">
                        <span style="width:6px;height:6px;border-radius:50%;background:#6a0f70;flex-shrink:0;"></span>
                        <span style="font-size:13px;font-weight:600;color:#1e0a2c;">{{ $p['name'] }}</span>
                    </div>
                    <div style="font-size:11px;color:#b0a0be;">Last upload · {{ $p['last_upload'] }}</div>
                </div>
            </a>
            @endforeach
            @endif

        </div>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════════════════
     SECTION 4 — TWO-COLUMN: Recent Uploads | Needs Attention
══════════════════════════════════════════════════════════════════════ --}}
{{-- Section label --}}
<div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
    <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#9b8aaa;">Activity &amp; Attention</span>
    <div style="flex:1;height:1px;background:rgba(185,92,183,0.10);"></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">

    {{-- ── LEFT: Recent Uploads ── --}}
    <div class="df-card">
        <div class="df-card-header">
            <div style="display:flex;align-items:center;gap:8px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
                <span style="font-size:13px;font-weight:600;color:#1e0a2c;">Recent Uploads</span>
            </div>
            <a href="#" style="font-size:12px;color:#6a0f70;text-decoration:none;font-weight:500;">View all →</a>
        </div>

        <div style="padding:0;">
            {{-- Phase 9: $recentUploads from ClinicalLibraryController::dashboard() --}}
            @forelse($recentUploads as $f)
            <div style="display:flex;align-items:center;gap:10px;padding:9px 16px;border-bottom:1px solid rgba(185,92,183,0.06);transition:background 0.1s;cursor:pointer;"
                 onmouseover="this.style.background='#faf5fb'"
                 onmouseout="this.style.background='transparent'"
                 onclick="window.dispatchEvent(new CustomEvent('open-file-viewer', { detail: { id: {{ $f->id }}, patientId: {{ $f->patient_id }} } }))">

                {{-- Thumbnail --}}
                <div style="width:32px;height:32px;border-radius:2px;background:#f3e8f4;flex-shrink:0;overflow:hidden;display:flex;align-items:center;justify-content:center;">
                    @if($f->isImage())
                        <img src="{{ $f->thumbnail_url }}" alt="Thumb"
                             style="width:100%;height:100%;object-fit:cover;">
                    @else
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9b59b6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
                    @endif
                </div>

                {{-- Info --}}
                <div style="flex:1;min-width:0;">
                    <div style="font-size:12px;font-weight:500;color:#1e0a2c;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        {{ $f->title ?? $f->original_filename }}
                    </div>
                    <div style="font-size:11px;color:#9b8aaa;margin-top:1px;">
                        {{ $f->patient?->name ?? 'Unknown' }} · {{ $f->procedure ?? '—' }}
                    </div>
                </div>

                {{-- Right: type badge + time --}}
                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:3px;flex-shrink:0;">
                    <span style="font-size:10px;font-weight:600;color:#fff;background:#6a0f70;padding:1px 6px;border-radius:10px;letter-spacing:0.02em;">
                        {{ $f->file_type_label }}
                    </span>
                    <span style="font-size:10px;color:#b0a0be;">{{ $f->created_at->diffForHumans() }}</span>
                </div>
            </div>
            @empty
            <div style="padding:20px 16px;text-align:center;font-size:12px;color:#9b8aaa;">No uploads yet.</div>
            @endforelse
        </div>
    </div>

    {{-- ── RIGHT: Needs Attention ── --}}
    <div style="display:flex;flex-direction:column;gap:12px;">

        {{-- Incomplete documentation --}}
        <div class="df-card" style="flex:1;">
            <div class="df-card-header">
                <div style="display:flex;align-items:center;gap:8px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#a05c00" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span style="font-size:13px;font-weight:600;color:#1e0a2c;">Incomplete Documentation</span>
                </div>
                <a href="#" style="font-size:12px;color:#6a0f70;text-decoration:none;font-weight:500;">View all →</a>
            </div>
            <div>
                {{-- Phase 9: $visitsWithNoFiles from ClinicalLibraryController::dashboard() --}}
                @forelse($visitsWithNoFiles as $visit)
                <a href="{{ route('patients.show', $visit->patient_id) }}"
                   style="display:flex;align-items:center;gap:10px;padding:9px 16px;border-bottom:1px solid rgba(185,92,183,0.06);cursor:pointer;transition:background 0.1s;text-decoration:none;"
                   onmouseover="this.style.background='#fff4e0'"
                   onmouseout="this.style.background='transparent'">
                    <div style="width:7px;height:7px;border-radius:50%;background:#a05c00;flex-shrink:0;"></div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:12px;font-weight:500;color:#1e0a2c;">{{ $visit->patient?->name ?? 'Unknown Patient' }}</div>
                        <div style="font-size:11px;color:#9b8aaa;margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            {{ $visit->treatment_name ?? 'Visit' }} · {{ \Carbon\Carbon::parse($visit->visit_date)->format('d M Y') }}
                        </div>
                    </div>
                    <span style="font-size:10px;color:#a05c00;font-weight:500;white-space:nowrap;">0 files</span>
                </a>
                @empty
                <div style="padding:16px;text-align:center;font-size:12px;color:#9b8aaa;">All recent visits documented ✓</div>
                @endforelse
            </div>
        </div>

        {{-- Pending marketing approval --}}
        <div class="df-card" style="flex:1;">
            <div class="df-card-header">
                <div style="display:flex;align-items:center;gap:8px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#1a5ea8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <span style="font-size:13px;font-weight:600;color:#1e0a2c;">Pending Marketing Approval</span>
                </div>
                <a href="{{ route('cms.marketing') }}" style="font-size:12px;color:#6a0f70;text-decoration:none;font-weight:500;">Review →</a>
            </div>
            <div>
                {{-- Phase 9: $pendingApproval from ClinicalLibraryController::dashboard() --}}
                @forelse($pendingApproval as $f)
                <div style="display:flex;align-items:center;gap:10px;padding:9px 16px;border-bottom:1px solid rgba(185,92,183,0.06);cursor:pointer;transition:background 0.1s;"
                     onmouseover="this.style.background='#e6f0fb'"
                     onmouseout="this.style.background='transparent'"
                     onclick="window.location='{{ route('cms.marketing') }}'">
                    <div style="width:7px;height:7px;border-radius:50%;background:#1a5ea8;flex-shrink:0;"></div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:12px;font-weight:500;color:#1e0a2c;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            {{ $f->title ?? $f->original_filename }}
                        </div>
                        <div style="font-size:11px;color:#9b8aaa;margin-top:1px;">
                            {{ $f->patient?->name ?? '—' }} · {{ $f->procedure ?? '—' }}
                        </div>
                    </div>
                    <span style="font-size:10px;color:#a05c00;font-weight:500;white-space:nowrap;background:#fff4e0;padding:2px 7px;border-radius:10px;">
                        {{ $f->created_at->diffForHumans() }}
                    </span>
                </div>
                @empty
                <div style="padding:16px;text-align:center;font-size:12px;color:#9b8aaa;">No files pending approval ✓</div>
                @endforelse
            </div>
        </div>

    </div>{{-- /right column --}}

</div>{{-- /two-column --}}


{{-- ══════════════════════════════════════════════════════════════════════
     SECTION 5 — QUICK ACTIONS
══════════════════════════════════════════════════════════════════════ --}}
{{-- Section label --}}
<div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
    <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#9b8aaa;">What do you want to do?</span>
    <div style="flex:1;height:1px;background:rgba(185,92,183,0.10);"></div>
</div>

<div class="df-card">
    <div class="df-card-header">
        <div style="display:flex;align-items:center;gap:8px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            <span style="font-size:13px;font-weight:600;color:#1e0a2c;">Quick Actions</span>
        </div>
    </div>

    <div class="df-card-body" style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;">

        {{-- Action 1: Upload for a patient --}}
        <button
            type="button"
            @click="uploadOpen = true"
            style="display:flex;flex-direction:column;align-items:flex-start;gap:8px;padding:16px;border:1px solid rgba(185,92,183,0.15);border-radius:3px;background:#faf5fb;cursor:pointer;text-align:left;transition:border-color 0.15s,background 0.15s;width:100%;"
            onmouseover="this.style.borderColor='rgba(106,15,112,0.35)';this.style.background='#f3e8f4'"
            onmouseout="this.style.borderColor='rgba(185,92,183,0.15)';this.style.background='#faf5fb'"
        >
            <div style="width:36px;height:36px;border-radius:3px;background:#6a0f70;display:flex;align-items:center;justify-content:center;">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
            </div>
            <div>
                <div style="font-size:13px;font-weight:600;color:#1e0a2c;margin-bottom:3px;">Upload for a Patient</div>
                <div style="font-size:12px;color:#9b8aaa;line-height:1.4;">Search patient, then attach clinical files to their visit</div>
            </div>
        </button>

        {{-- Action 2: Content Manager --}}
        <a
            href="{{ route('cms.index') }}"
            style="display:flex;flex-direction:column;align-items:flex-start;gap:8px;padding:16px;border:1px solid rgba(185,92,183,0.15);border-radius:3px;background:#faf5fb;text-decoration:none;transition:border-color 0.15s,background 0.15s;"
            onmouseover="this.style.borderColor='rgba(106,15,112,0.35)';this.style.background='#f3e8f4'"
            onmouseout="this.style.borderColor='rgba(185,92,183,0.15)';this.style.background='#faf5fb'"
        >
            <div style="width:36px;height:36px;border-radius:3px;background:#1a5ea8;display:flex;align-items:center;justify-content:center;">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            </div>
            <div>
                <div style="font-size:13px;font-weight:600;color:#1e0a2c;margin-bottom:3px;">Content Manager</div>
                <div style="font-size:12px;color:#9b8aaa;line-height:1.4;">Review marketing, education and case library content</div>
            </div>
        </a>

    </div>
</div>{{-- /quick actions --}}


{{-- ══════════════════════════════════════════════════════════════════════
     UPLOAD MODAL
══════════════════════════════════════════════════════════════════════ --}}
<div
    x-show="uploadOpen"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @keydown.escape.window="uploadOpen = false"
    style="position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;background:rgba(14,1,24,0.55);"
    @click.self="uploadOpen = false"
>
    {{-- Centering wrapper (separate from x-show so flex always applies) --}}
    <div style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;padding:16px;box-sizing:border-box;">
    <div style="background:#ffffff;border-radius:6px;width:540px;max-width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(14,1,24,0.3);">

        {{-- Header --}}
        <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px 16px;border-bottom:1px solid rgba(185,92,183,0.12);">
            <div>
                <div style="font-size:16px;font-weight:700;color:#1e0a2c;">Upload Clinical Files</div>
                <div style="font-size:12px;color:#9b8aaa;margin-top:2px;">Attach photos, X-rays, PDFs or any clinical document</div>
            </div>
            <button @click="uploadOpen = false" style="background:none;border:none;cursor:pointer;color:#9b8aaa;padding:4px;line-height:0;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        {{-- Flash success (shown after redirect) --}}
        @if(session('success'))
        <div style="margin:16px 24px 0;padding:10px 14px;background:#f0fdf4;border:1px solid #86efac;border-radius:4px;font-size:13px;color:#16a34a;">
            {{ session('success') }}
        </div>
        @endif

        {{-- Form --}}
        <form
            method="POST"
            action="{{ route('cms.files.store') }}"
            enctype="multipart/form-data"
            style="padding:20px 24px 24px;"
        >
            @csrf

            {{-- Patient --}}
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#4b3060;margin-bottom:6px;">Patient <span style="color:#dc2626;">*</span></label>
                <select name="patient_id" required
                    style="width:100%;padding:9px 12px;border:1px solid rgba(185,92,183,0.30);border-radius:4px;font-size:13px;color:#1e0a2c;background:#fff;outline:none;"
                >
                    <option value="">— Select patient —</option>
                    @foreach($patients as $p)
                        <option value="{{ $p->id }}" {{ old('patient_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                    @endforeach
                </select>
                @error('patient_id')<div style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</div>@enderror
            </div>

            {{-- Files --}}
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#4b3060;margin-bottom:6px;">Files <span style="color:#dc2626;">*</span></label>
                <input type="file" name="files[]" multiple required
                    style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.30);border-radius:4px;font-size:13px;color:#1e0a2c;background:#faf5fb;cursor:pointer;"
                >
                <div style="font-size:11px;color:#b0a0be;margin-top:4px;">Select one or more files. Max 50 MB each.</div>
                @error('files')<div style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</div>@enderror
            </div>

            {{-- Row: File Type + Stage --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#4b3060;margin-bottom:6px;">File Type</label>
                    <select name="file_type" style="width:100%;padding:9px 12px;border:1px solid rgba(185,92,183,0.30);border-radius:4px;font-size:13px;color:#1e0a2c;background:#fff;outline:none;">
                        <option value="">— Auto-detect —</option>
                        <option value="photo">Photo</option>
                        <option value="video">Video</option>
                        <option value="xray">X-ray</option>
                        <option value="opg">OPG</option>
                        <option value="cbct">CBCT</option>
                        <option value="stl">STL</option>
                        <option value="intraoral_scan">Intraoral Scan</option>
                        <option value="pdf">PDF</option>
                        <option value="consent">Consent</option>
                        <option value="estimate">Estimate</option>
                        <option value="invoice">Invoice</option>
                        <option value="lab_slip">Lab Slip</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#4b3060;margin-bottom:6px;">Stage</label>
                    <select name="stage" style="width:100%;padding:9px 12px;border:1px solid rgba(185,92,183,0.30);border-radius:4px;font-size:13px;color:#1e0a2c;background:#fff;outline:none;">
                        <option value="general">General</option>
                        <option value="before">Before</option>
                        <option value="during">During</option>
                        <option value="after">After</option>
                        <option value="followup">Follow-up</option>
                    </select>
                </div>
            </div>

            {{-- Row: Procedure + Tooth --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#4b3060;margin-bottom:6px;">Procedure</label>
                    <input type="text" name="procedure" value="{{ old('procedure') }}" placeholder="e.g. Extraction, Crown…"
                        style="width:100%;padding:9px 12px;border:1px solid rgba(185,92,183,0.30);border-radius:4px;font-size:13px;color:#1e0a2c;background:#fff;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#4b3060;margin-bottom:6px;">Tooth #</label>
                    <input type="text" name="tooth_number" value="{{ old('tooth_number') }}" placeholder="e.g. 36"
                        style="width:100%;padding:9px 12px;border:1px solid rgba(185,92,183,0.30);border-radius:4px;font-size:13px;color:#1e0a2c;background:#fff;outline:none;box-sizing:border-box;">
                </div>
            </div>

            {{-- Notes --}}
            <div style="margin-bottom:22px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#4b3060;margin-bottom:6px;">Notes</label>
                <textarea name="notes" rows="2" placeholder="Optional clinical notes…"
                    style="width:100%;padding:9px 12px;border:1px solid rgba(185,92,183,0.30);border-radius:4px;font-size:13px;color:#1e0a2c;background:#fff;outline:none;resize:vertical;box-sizing:border-box;">{{ old('notes') }}</textarea>
            </div>

            {{-- Actions --}}
            <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;">
                <button type="button" @click="uploadOpen = false"
                    style="padding:9px 18px;border:1px solid rgba(185,92,183,0.25);border-radius:4px;background:#fff;font-size:13px;font-weight:500;color:#6a0f70;cursor:pointer;">
                    Cancel
                </button>
                <button type="submit"
                    style="padding:9px 22px;border:none;border-radius:4px;background:#6a0f70;font-size:13px;font-weight:600;color:#fff;cursor:pointer;">
                    Upload Files
                </button>
            </div>
        </form>

    </div>
    </div>{{-- /centering wrapper --}}
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     SEARCH DRAWER PLACEHOLDER (Alpine toggle — no functionality yet)
══════════════════════════════════════════════════════════════════════ --}}
<div
    x-show="searchOpen"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @keydown.escape.window="searchOpen = false"
    style="position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;background:rgba(14,1,24,0.50);"
    @click.self="searchOpen = false"
>
    <div style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;padding:16px;box-sizing:border-box;">
    <div style="background:#ffffff;border-radius:4px;width:560px;max-width:100%;padding:24px;box-shadow:0 20px 60px rgba(14,1,24,0.25);">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:0;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9b8aaa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" placeholder="Search clinical files, patients, procedures…" style="flex:1;border:none;outline:none;font-size:15px;color:#1e0a2c;font-family:'Inter',sans-serif;" autofocus>
            <button @click="searchOpen = false" style="background:none;border:none;cursor:pointer;color:#9b8aaa;padding:2px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(185,92,183,0.10);font-size:12px;color:#b0a0be;text-align:center;">
            Global search — Phase 3 placeholder. Full search built in Phase 9.
        </div>
    </div>
    </div>{{-- /centering wrapper --}}
</div>

</div>{{-- /x-data wrapper --}}


{{-- ══════════════════════════════════════════════════════════════════════
     RESPONSIVE STYLES
══════════════════════════════════════════════════════════════════════ --}}
@push('styles')
<style>
    /* Two-column → single column on smaller screens */
    @media (max-width: 900px) {
        .cl-two-col { grid-template-columns: 1fr !important; }
    }
    /* Quick actions → 1 column on mobile */
    @media (max-width: 640px) {
        .cl-quick-actions { grid-template-columns: 1fr !important; }
    }
</style>
@endpush

@endsection
