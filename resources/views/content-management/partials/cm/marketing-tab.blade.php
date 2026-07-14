{{--
    ══════════════════════════════════════════════════════
    CONTENT MANAGER — MARKETING TAB
    Google Photos-style grid. Approval badges. Batch select.
    Phase 9: wired to real clinical_files (is_marketing_eligible = true).
    Data source: $marketingByMonth (grouped by calendar month)
                 $marketingFiles (paginator — for pagination links)
    ══════════════════════════════════════════════════════
--}}

@php
$stageColors = [
    'before'   => '#2563eb',
    'during'   => '#d97706',
    'after'    => '#16a34a',
    'followup' => '#7c3aed',
    'general'  => '#9ca3af',
];
@endphp

{{-- ── EMPTY STATE ── --}}
@if($marketingFiles->isEmpty())
<div class="cm-empty">
    <div class="cm-empty-icon">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
    </div>
    <div class="cm-empty-title">No marketing files yet</div>
    <div class="cm-empty-sub">
        Tag clinical files as <strong>Marketing Eligible</strong> when uploading or from the file detail panel.
    </div>
</div>
@else

{{-- ── GROUPS BY MONTH ── --}}
@foreach($marketingByMonth as $monthLabel => $monthFiles)

@php
    $approvedCount = $monthFiles->where('marketing_status', 'approved')->count();
    $pendingCount  = $monthFiles->where('marketing_status', 'pending')->count();
@endphp

<div class="cm-group-header">
    <span class="cm-group-label">{{ $monthLabel }}</span>
    <span class="cm-group-count">{{ $monthFiles->count() }} files</span>
    <span style="margin-left:auto;display:flex;gap:6px;">
        @if($approvedCount)
        <span style="display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:600;color:#16a34a;">
            <span style="width:6px;height:6px;background:#16a34a;border-radius:50%;display:inline-block;"></span>
            {{ $approvedCount }} approved
        </span>
        @endif
        @if($pendingCount)
        <span style="display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:600;color:#d97706;">
            <span style="width:6px;height:6px;background:#d97706;border-radius:50%;display:inline-block;"></span>
            {{ $pendingCount }} pending
        </span>
        @endif
    </span>
</div>

<div class="cm-photo-grid" style="margin-bottom:24px;">
    @foreach($monthFiles as $file)
    @php
        $stageColor  = $stageColors[$file->stage] ?? '#9ca3af';
        $approval    = $file->marketing_status ?? 'pending';
        $consentGiven= $file->consent_status === 'given';
        $fileId      = (string) $file->id;
    @endphp

    {{-- ── MARKETING CARD ── --}}
    <div class="cm-card"
         data-id="{{ $fileId }}"
         :class="isSelected('{{ $fileId }}') ? 'selected' : ''"
         @click="toggleSelect('{{ $fileId }}')"
         title="{{ $file->procedure ?? 'File' }} · {{ $file->stage_label }}">

        {{-- Thumbnail — real image if available, else icon placeholder --}}
        @if($file->isImage())
            <img src="{{ $file->display_url }}"
                 alt="{{ $file->title ?? $file->original_filename }}"
                 loading="lazy"
                 style="width:100%;height:100%;object-fit:cover;">
        @else
            <div style="width:100%;height:100%;background:#1c1c2e;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:4px;">
                <span style="font-size:22px;">
                    @if($file->isVideo())
                    @elseif($file->isPdf())
                    @else
                    @endif
                </span>
                <span style="font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase;font-weight:700;letter-spacing:.06em;">
                    {{ $file->file_type_label }}
                </span>
            </div>
        @endif

        {{-- Overlay (hover / select) --}}
        <div class="cm-card-overlay">

            {{-- TOP ROW: checkbox + approval badge --}}
            <div style="display:flex;align-items:flex-start;justify-content:space-between;">

                <div class="cm-card-check">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"
                         :style="isSelected('{{ $fileId }}') ? 'opacity:1' : 'opacity:0'">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>

                <span class="cm-approval-badge
                    @if($approval === 'approved') badge-approved
                    @elseif($approval === 'rejected') badge-rejected
                    @else badge-pending @endif">
                    @if($approval === 'approved') ✓ Approved
                    @elseif($approval === 'rejected') ✕ Rejected
                    @else Pending @endif
                </span>

            </div>

            {{-- Consent chip --}}
            <span class="cm-consent-chip {{ $consentGiven ? 'consent-given' : 'consent-pending' }}">
                {{ $consentGiven ? '✓ Consent' : 'No Consent' }}
            </span>

            {{-- BOTTOM ROW: procedure + stage --}}
            <div class="cm-card-footer">
                <div class="cm-card-treatment">{{ $file->procedure ?? $file->file_type_label }}</div>
                <div class="cm-card-stage">
                    <span style="width:5px;height:5px;border-radius:50%;background:{{ $stageColor }};display:inline-block;flex-shrink:0;"></span>
                    {{ $file->stage_label }}
                </div>
            </div>

        </div>

        {{-- Quick-action buttons --}}
        <div class="cm-card-actions" @click.stop="">

            {{-- View --}}
            <button class="cm-card-action-btn" title="View"
                    onclick="window.dispatchEvent(new CustomEvent('open-file-viewer', { detail: { id: {{ $file->id }}, patientId: {{ $file->patient_id }} } }))">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>

            {{-- Download --}}
            <a class="cm-card-action-btn" title="Download" href="{{ $file->display_url }}" download="{{ $file->original_filename }}">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            </a>

            {{-- Approve button (only shown if pending) --}}
            @if($approval === 'pending')
            <button class="cm-card-action-btn" title="Approve"
                    style="background:rgba(22,163,74,.5);"
                    onclick="approveFile({{ $file->id }}, this)">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </button>
            @endif

        </div>

    </div>
    @endforeach
</div>

@endforeach

{{-- ── PAGINATION LINKS ── --}}
@if($marketingFiles->hasPages())
<div style="display:flex;justify-content:center;padding:20px 0;">
    {{ $marketingFiles->links() }}
</div>
@endif

@endif {{-- /empty state --}}

{{-- ── Approve/Reject JS helpers ──────────────────────────────────────────── --}}
<script>
/**
 * Approve a single file via PUT /clinical-library/files/{id}/approve.
 * Updates the card's approval badge inline without a page reload.
 */
function approveFile(fileId, btn) {
    fetch(`/clinical-library/files/${fileId}/approve`, {
        method: 'PUT',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Update the badge in the card overlay
            const card  = btn.closest('.cm-card');
            const badge = card.querySelector('.cm-approval-badge');
            if (badge) {
                badge.className = 'cm-approval-badge badge-approved';
                badge.textContent = '✓ Approved';
            }
            btn.remove();
        }
    })
    .catch(console.error);
}

function rejectFile(fileId, btn) {
    fetch(`/clinical-library/files/${fileId}/reject`, {
        method: 'PUT',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const card  = btn.closest('.cm-card');
            const badge = card.querySelector('.cm-approval-badge');
            if (badge) {
                badge.className = 'cm-approval-badge badge-rejected';
                badge.textContent = '✕ Rejected';
            }
        }
    })
    .catch(console.error);
}
</script>
