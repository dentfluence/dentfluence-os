{{--
    ══════════════════════════════════════════════════════
    CONTENT MANAGER — EDUCATION TAB
    Two sections:
      1. From Clinical Files (is_education_eligible = true)
      2. Library Resources (generic educational content, future)
    ══════════════════════════════════════════════════════
--}}

@php
/**
 * Phase 9: $educationFiles is a collection of ClinicalFile where is_education_eligible = true.
 * Passed from ClinicalLibraryController::index().
 *
 * Section 2 ($libraryResources) remains static — generic educational content is a future feature.
 */
$stageColors = [
    'before'   => '#2563eb',
    'during'   => '#d97706',
    'after'    => '#16a34a',
    'followup' => '#7c3aed',
    'general'  => '#9ca3af',
];

// Generic library resources — static placeholder (not patient-linked, future feature)
$libraryResources = [
    ['id'=>'lr1', 'icon'=>'', 'title'=>'Tooth Anatomy — Patient Explainer', 'type'=>'PDF',   'size'=>'2.1 MB', 'added'=>'Jan 2024', 'downloads'=>42],
    ['id'=>'lr2', 'icon'=>'', 'title'=>'How Root Canal Works — Animation',  'type'=>'Video', 'size'=>'18 MB',  'added'=>'Feb 2024', 'downloads'=>67],
    ['id'=>'lr3', 'icon'=>'', 'title'=>'Implant Care After Procedure',       'type'=>'PDF',   'size'=>'1.4 MB', 'added'=>'Dec 2023', 'downloads'=>31],
    ['id'=>'lr4', 'icon'=>'',  'title'=>'Stages of Orthodontic Treatment',   'type'=>'Image', 'size'=>'3.8 MB', 'added'=>'Mar 2024', 'downloads'=>19],
    ['id'=>'lr5', 'icon'=>'', 'title'=>'Post-Extraction Care Instructions',  'type'=>'PDF',   'size'=>'0.9 MB', 'added'=>'Jan 2024', 'downloads'=>88],
];
@endphp

{{-- ═══════════════════════════════════════════════════ --}}
{{-- SECTION 1: FROM CLINICAL FILES                      --}}
{{-- Source: $educationFiles from ClinicalLibraryController --}}
{{-- ═══════════════════════════════════════════════════ --}}

<div class="edu-section-title">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
    From Clinical Files
    <span class="edu-section-badge" style="background:#f5f3ff;color:#6a0f70;">{{ $educationFiles->count() }} files</span>
    <span style="font-size:11px;font-weight:400;color:#9ca3af;margin-left:4px;">Tagged as education-eligible in Clinical Library</span>
</div>

@if($educationFiles->isEmpty())
<div class="cm-empty" style="margin-bottom:32px;">
    <div class="cm-empty-icon">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
    </div>
    <div class="cm-empty-title">No education files yet</div>
    <div class="cm-empty-sub">Tag clinical files as <strong>Education Eligible</strong> to populate this section.</div>
</div>
@else
<div class="cm-photo-grid" style="margin-bottom:32px;">
    @foreach($educationFiles as $file)
    @php
        $stageColor = $stageColors[$file->stage] ?? '#9ca3af';
        $fileId     = (string) $file->id;
    @endphp

    <div class="cm-card" data-id="{{ $fileId }}"
         :class="isSelected('{{ $fileId }}') ? 'selected' : ''"
         @click="toggleSelect('{{ $fileId }}')">

        {{-- Thumbnail --}}
        @if($file->isImage())
            <img src="{{ $file->display_url }}"
                 alt="{{ $file->title ?? $file->original_filename }}"
                 loading="lazy"
                 style="width:100%;height:100%;object-fit:cover;">
        @else
            <div style="width:100%;height:100%;background:#1c1c2e;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:4px;">
                <span style="font-size:20px;opacity:.6;">{{ $file->isVideo() ? '' : '' }}</span>
                <span style="font-size:8px;color:rgba(255,255,255,.35);font-weight:700;text-transform:uppercase;letter-spacing:.06em;">{{ $file->file_type_label }}</span>
            </div>
        @endif

        {{-- Overlay --}}
        <div class="cm-card-overlay">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;">
                <div class="cm-card-check">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"
                         stroke-linecap="round" stroke-linejoin="round"
                         :style="isSelected('{{ $fileId }}') ? 'opacity:1' : 'opacity:0'">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
                <span style="padding:2px 7px;border-radius:99px;font-size:9px;font-weight:800;backdrop-filter:blur(4px);background:{{ $stageColor }}cc;color:white;">
                    {{ $file->stage_label }}
                </span>
            </div>
            <div class="cm-card-footer">
                <div class="cm-card-treatment">{{ $file->title ?? $file->procedure ?? $file->file_type_label }}</div>
                <div class="cm-card-stage">
                    <span style="width:5px;height:5px;border-radius:50%;background:{{ $stageColor }};display:inline-block;flex-shrink:0;"></span>
                    {{ $file->procedure ?? '—' }}
                </div>
            </div>
        </div>

        {{-- Quick actions --}}
        <div class="cm-card-actions" @click.stop="">
            <button class="cm-card-action-btn" title="View"
                    onclick="window.dispatchEvent(new CustomEvent('open-file-viewer', { detail: { id: {{ $file->id }} } }))">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
            <a class="cm-card-action-btn" title="Download" href="{{ $file->display_url }}" download="{{ $file->original_filename }}">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            </a>
        </div>

    </div>
    @endforeach
</div>
@endif

{{-- ═══════════════════════════════════════════════════ --}}
{{-- SECTION 2: LIBRARY RESOURCES                        --}}
{{-- Generic educational content — not patient-specific  --}}
{{-- ═══════════════════════════════════════════════════ --}}

<div class="edu-section-title">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
    Library Resources
    <span class="edu-section-badge" style="background:#eff6ff;color:#2563eb;">{{ count($libraryResources) }} resources</span>
    <span style="font-size:11px;font-weight:400;color:#9ca3af;margin-left:4px;">Generic educational content not tied to patient records</span>
</div>

{{-- Resource list --}}
<div style="display:flex;flex-direction:column;gap:8px;margin-bottom:24px;">
    @foreach($libraryResources as $res)
    <div style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:white;border:1px solid #e5e7eb;border-radius:8px;transition:all .15s;cursor:pointer;"
         onmouseover="this.style.borderColor='#d8b4fe';this.style.boxShadow='0 2px 8px rgba(106,15,112,.08)'"
         onmouseout="this.style.borderColor='#e5e7eb';this.style.boxShadow='none'">

        {{-- File icon --}}
        <div style="width:38px;height:38px;border-radius:8px;background:#f5f3ff;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;">
            {{ $res['icon'] }}
        </div>

        {{-- Info --}}
        <div style="flex:1;min-width:0;">
            <div style="font-size:13px;font-weight:700;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $res['title'] }}</div>
            <div style="display:flex;align-items:center;gap:6px;margin-top:2px;">
                <span style="font-size:10px;font-weight:700;padding:1px 6px;background:#eff6ff;color:#2563eb;border-radius:99px;">{{ $res['type'] }}</span>
                <span style="font-size:10px;color:#9ca3af;">{{ $res['size'] }}</span>
                <span style="width:3px;height:3px;background:#d1d5db;border-radius:50%;display:inline-block;"></span>
                <span style="font-size:10px;color:#9ca3af;">Added {{ $res['added'] }}</span>
                <span style="width:3px;height:3px;background:#d1d5db;border-radius:50%;display:inline-block;"></span>
                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span style="font-size:10px;color:#9ca3af;">{{ $res['downloads'] }} downloads</span>
            </div>
        </div>

        {{-- Actions --}}
        <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
            <button style="padding:5px 12px;border-radius:5px;border:1px solid #e5e7eb;background:white;font-size:11px;font-weight:600;color:#374151;cursor:pointer;transition:all .15s;"
                    onmouseover="this.style.borderColor='#6a0f70';this.style.color='#6a0f70'"
                    onmouseout="this.style.borderColor='#e5e7eb';this.style.color='#374151'">
                View
            </button>
            <button style="padding:5px 12px;border-radius:5px;border:none;background:#6a0f70;font-size:11px;font-weight:600;color:white;cursor:pointer;transition:background .15s;"
                    onmouseover="this.style.background='#380740'" onmouseout="this.style.background='#6a0f70'">
                Download
            </button>
        </div>

    </div>
    @endforeach
</div>

{{-- Add resource button --}}
<div style="display:flex;justify-content:center;padding:24px 0 8px;">
    <button style="display:flex;align-items:center;gap:6px;padding:9px 20px;border-radius:7px;border:1.5px dashed #d8b4fe;background:#faf5fb;font-size:12px;font-weight:700;color:#6a0f70;cursor:pointer;transition:all .15s;"
            onmouseover="this.style.background='#f5f3ff';this.style.borderColor='#a855f7'"
            onmouseout="this.style.background='#faf5fb';this.style.borderColor='#d8b4fe'">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Upload Library Resource
    </button>
</div>
