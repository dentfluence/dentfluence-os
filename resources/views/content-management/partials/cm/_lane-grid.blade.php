{{--
    A plain grid for the lanes that have no special layout of their own
    (Teaching, Research). $files is the paginated, filtered result for the
    active tab — the same data every other tab is built from, so these can
    never drift back into showing invented numbers.
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

@if($files->isEmpty())
<div class="cm-empty">
    <div class="cm-empty-icon">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
    </div>
    <div class="cm-empty-title">Nothing in {{ $lane }} yet</div>
    <div class="cm-empty-sub">{{ $emptyHint }}</div>
</div>
@else
<div class="cm-photo-grid">
    @foreach($files as $file)
    @php
        $stageColor = $stageColors[$file->stage] ?? '#9ca3af';
        $fileId     = (string) $file->id;
    @endphp
    <div class="cm-card"
         data-id="{{ $fileId }}"
         :class="isSelected('{{ $fileId }}') ? 'selected' : ''"
         @click="toggleSelect('{{ $fileId }}')"
         title="{{ $file->procedure ?? 'File' }} · {{ $file->stage_label }}">

        @if($file->isImage())
            <img src="{{ $file->thumbnail_url }}" alt="{{ $file->title ?? $file->original_filename }}"
                 loading="lazy" style="width:100%;height:100%;object-fit:cover;">
        @else
            <div style="width:100%;height:100%;background:#1c1c2e;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:4px;padding:8px;text-align:center;">
                <span style="font-size:10px;color:rgba(255,255,255,.55);text-transform:uppercase;font-weight:700;letter-spacing:.06em;">{{ $file->file_type_label }}</span>
                <span style="font-size:9px;color:rgba(255,255,255,.35);line-height:1.3;">{{ $file->no_preview_reason }}</span>
            </div>
        @endif

        <div class="cm-card-overlay">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;">
                <div class="cm-card-check">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"
                         :style="isSelected('{{ $fileId }}') ? 'opacity:1' : 'opacity:0'">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
            </div>
            <div class="cm-card-footer">
                <div class="cm-card-treatment">{{ $file->procedure ?? $file->file_type_label }}</div>
                <div class="cm-card-stage">
                    <span style="width:5px;height:5px;border-radius:50%;background:{{ $stageColor }};display:inline-block;flex-shrink:0;"></span>
                    {{ $file->stage_label }}{{ $file->tooth_number ? ' · ' . $file->tooth_number : '' }}
                </div>
            </div>
        </div>

        <div class="cm-card-actions" @click.stop="">
            <button class="cm-card-action-btn" title="View"
                    onclick="window.dispatchEvent(new CustomEvent('open-file-viewer', { detail: { id: {{ $file->id }}, patientId: {{ $file->patient_id }} } }))">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
        </div>
    </div>
    @endforeach
</div>
@endif
