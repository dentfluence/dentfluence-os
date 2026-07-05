{{--
    communication/opportunities/_detail-card.blade.php

    Shared partial rendering full detail for one TreatmentOpportunity:
    patient header, stage progress bar, details grid, notes, quick stage-move
    buttons, and linked treatment plan.

    Used by:
      - communication/opportunities/detail.blade.php (full-page fallback / deep link)
      - OpportunityController::detailModal() — AJAX response injected into the
        "Opportunity Detail" popup on the board/list view (index.blade.php),
        so clicking a card opens a modal instead of navigating away.

    Required variable: $opportunity (with patient, assignedStaff, author, treatmentPlan loaded)
--}}
@php
    $stages         = \App\Models\TreatmentOpportunity::STAGES;
    $priorityColors = \App\Models\TreatmentOpportunity::PRIORITY_COLORS;
    $priorityLabels = \App\Models\TreatmentOpportunity::PRIORITY_LABELS;
    $stageInfo      = $stages[$opportunity->status] ?? ['label' => $opportunity->status, 'color' => '#6b7280', 'bg' => '#f3f4f6'];
    $pc             = $priorityColors[$opportunity->priority] ?? ['bg' => '#f3f4f6', 'text' => '#6b7280'];
    $pl             = $priorityLabels[$opportunity->priority] ?? $opportunity->priority;
    $initials       = collect(explode(' ', $opportunity->patient->name ?? 'U'))
                          ->map(fn($w) => strtoupper($w[0] ?? ''))
                          ->take(2)->implode('');
@endphp

{{-- Patient header --}}
<div class="opp-detail-header" style="display:flex;align-items:center;gap:14px;padding:20px 24px">
    <div class="opp-card-avatar" style="width:44px;height:44px;font-size:15px;flex-shrink:0">{{ $initials }}</div>
    <div style="flex:1;min-width:0">
        <h2 style="font-size:17px;font-weight:700;color:#111827;margin:0">
            {{ $opportunity->patient->name ?? 'Unknown' }}
        </h2>
        <span style="font-size:13px;color:#6b7280">
            {{ $opportunity->patient->phone ?? '' }}
            @if($opportunity->patient->phone && $opportunity->display_label) · @endif
            {{ $opportunity->display_label }}
        </span>
    </div>
    <span class="opp-intent-tag" style="background:{{ $pc['bg'] }};color:{{ $pc['text'] }};white-space:nowrap">{{ $pl }}</span>
    @if(!in_array($opportunity->status, ['completed', 'declined']))
    <button class="btn-primary-prm" style="margin-left:6px;white-space:nowrap"
            onclick="openConvertModal({{ $opportunity->id }}, '{{ addslashes($opportunity->patient->name ?? '') }}', '{{ addslashes($opportunity->display_label) }}')">
        Convert to Lead
    </button>
    @else
    <span class="opp-stage-chip" style="background:{{ $stageInfo['bg'] }};color:{{ $stageInfo['color'] }};padding:6px 12px;border-radius:20px;font-size:13px;margin-left:6px;white-space:nowrap">
        ✓ {{ $stageInfo['label'] }}
    </span>
    @endif
</div>

{{-- Stage progress bar --}}
<div style="padding:20px 24px;border-top:1px solid #f3f4f6;border-bottom:1px solid #f3f4f6">
    <div style="display:flex;gap:4px;align-items:center">
        @foreach(array_filter($stages, fn($k) => $k !== 'declined', ARRAY_FILTER_USE_KEY) as $sKey => $sInfo)
        @php $isActive = $sKey === $opportunity->status; $isPast = array_search($sKey, array_keys($stages)) < array_search($opportunity->status, array_keys($stages)); @endphp
        <div style="flex:1;height:6px;border-radius:4px;background:{{ $isActive || $isPast ? $sInfo['color'] : '#e5e7eb' }};transition:background .2s" title="{{ $sInfo['label'] }}"></div>
        @endforeach
    </div>
    <div style="display:flex;justify-content:space-between;margin-top:6px">
        @foreach(array_filter($stages, fn($k) => $k !== 'declined', ARRAY_FILTER_USE_KEY) as $sKey => $sInfo)
        <span style="font-size:11px;color:{{ $sKey === $opportunity->status ? $sInfo['color'] : '#9ca3af' }};font-weight:{{ $sKey === $opportunity->status ? '600' : '400' }}">
            {{ $sInfo['label'] }}
        </span>
        @endforeach
    </div>
</div>

{{-- Details grid --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:0;padding:0">
    @php
        $fields = [
            ['label' => 'Status',        'value' => $stageInfo['label']],
            ['label' => 'Priority',       'value' => $pl],
            ['label' => 'Estimated Value','value' => $opportunity->estimated_value ? 'Rs. '.number_format($opportunity->estimated_value) : '—'],
            ['label' => 'Follow-up Date', 'value' => $opportunity->follow_up_date ? $opportunity->follow_up_date->format('d M Y').($opportunity->follow_up_time ? ' at '.date('g:i A', strtotime($opportunity->follow_up_time)) : '') : '—'],
            ['label' => 'Assigned To',    'value' => $opportunity->assignedStaff?->name ?? '—'],
            ['label' => 'Created By',     'value' => $opportunity->author?->name ?? '—'],
            ['label' => 'Created',        'value' => $opportunity->created_at->format('d M Y, g:i A')],
            ['label' => 'Last Updated',   'value' => $opportunity->updated_at->format('d M Y, g:i A')],
        ];
    @endphp
    @foreach($fields as $i => $field)
    <div style="padding:14px 24px;border-bottom:1px solid #f9fafb;{{ $i % 2 === 0 ? 'border-right:1px solid #f9fafb' : '' }}">
        <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">{{ $field['label'] }}</div>
        <div style="font-size:14px;color:#111827;font-weight:500">{{ $field['value'] }}</div>
    </div>
    @endforeach
</div>

{{-- Notes --}}
@if($opportunity->notes)
<div style="padding:20px 24px;border-top:1px solid #f3f4f6">
    <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Notes</div>
    <p style="font-size:14px;color:#374151;line-height:1.6;margin:0">{{ $opportunity->notes }}</p>
</div>
@endif

{{-- Stage change (quick action) --}}
@if(!in_array($opportunity->status, ['completed', 'declined']))
<div style="padding:16px 24px;border-top:1px solid #f3f4f6;background:#fafafa">
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <span style="font-size:13px;color:#6b7280;font-weight:500">Move stage:</span>
        @foreach($stages as $sKey => $sInfo)
        @if($sKey !== $opportunity->status)
        <button onclick="moveStage({{ $opportunity->id }}, '{{ $sKey }}')"
                style="padding:5px 12px;border:1px solid {{ $sInfo['color'] }};border-radius:20px;font-size:12px;color:{{ $sInfo['color'] }};background:{{ $sInfo['bg'] }};cursor:pointer;transition:opacity .15s"
                onmouseover="this.style.opacity='.75'" onmouseout="this.style.opacity='1'">
            {{ $sInfo['label'] }}
        </button>
        @endif
        @endforeach
    </div>
</div>
@endif

{{-- Linked Treatment Plan --}}
@if($opportunity->treatmentPlan)
<div style="padding:16px 24px;border-top:1px solid #f3f4f6">
    <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Linked Treatment Plan</div>
    <a href="{{ route('treatment-plans.show', $opportunity->treatmentPlan) }}" style="font-size:14px;color:#6366f1;text-decoration:none">
        {{ $opportunity->treatmentPlan->plan_name ?? 'Treatment Plan #'.$opportunity->treatment_plan_id }}
    </a>
    <span style="font-size:13px;color:#9ca3af;margin-left:8px">Rs. {{ number_format($opportunity->treatmentPlan->total ?? 0) }}</span>
</div>
@endif
