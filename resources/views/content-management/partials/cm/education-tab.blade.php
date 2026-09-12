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
 * Section 2 was an invented list of resources and is gone (P4); the real
 * education library lives at Content Manager → Education Library.
 */
$stageColors = [
    'before'   => '#2563eb',
    'during'   => '#d97706',
    'after'    => '#16a34a',
    'followup' => '#7c3aed',
    'general'  => '#9ca3af',
];

// The "Library Resources" block that used to sit below this section was a
// hardcoded list of five documents that do not exist, with invented download
// counts. Removed in P4 — generic patient-education material is a real feature
// with its own home (Content Manager → Education Library), not a decoration.
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
                    onclick="window.dispatchEvent(new CustomEvent('open-file-viewer', { detail: { id: {{ $file->id }}, patientId: {{ $file->patient_id }} } }))">
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

{{-- ═══════════════════════════════════════════════════
     SECTION 2 removed in P4.

     What stood here was a hardcoded list of five documents that do not exist —
     "Tooth Anatomy — Patient Explainer", "How Root Canal Works — Animation" and
     three more — each with an invented file size and download count, plus View
     and Download buttons wired to nothing and an "Add Resource" button that did
     nothing either.

     Generic patient-education material is a real feature with a real home:
     Content Manager → Education Library (EducationCategory / EducationTreatment
     / EducationMedia, reachable from cms.education.manage). A fake shelf beside
     the real one only teaches staff not to trust the screen.
═══════════════════════════════════════════════════ --}}
