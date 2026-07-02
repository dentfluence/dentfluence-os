@extends('layouts.communication')

@section('title', 'Opportunity · ' . ($opportunity->patient->name ?? 'Detail'))

@section('communication-content')
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

<div class="opp-page" style="max-width:860px">

    {{-- Top Bar --}}
    <div class="opp-topbar">
        <div class="opp-topbar-left">
            <a href="{{ route('communication.opportunities.index') }}" class="back-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            </a>
            <div>
                <h1 class="opp-page-title">Opportunity Detail</h1>
                <p class="opp-page-sub">{{ $opportunity->patient->name ?? 'Unknown' }} · {{ $opportunity->display_label }}</p>
            </div>
        </div>
        <div class="opp-topbar-right">
            @if(!in_array($opportunity->status, ['completed', 'declined']))
            <button class="btn-primary-prm"
                    onclick="openConvertModal({{ $opportunity->id }}, '{{ addslashes($opportunity->patient->name ?? '') }}', '{{ addslashes($opportunity->display_label) }}')">
                Convert to Lead
            </button>
            @else
            <span class="opp-stage-chip" style="background:{{ $stageInfo['bg'] }};color:{{ $stageInfo['color'] }};padding:6px 12px;border-radius:20px;font-size:13px">
                ✓ {{ $stageInfo['label'] }}
            </span>
            @endif
        </div>
    </div>

    {{-- Main Detail Card --}}
    <div class="opp-detail-card">

        {{-- Patient header --}}
        <div class="opp-detail-header">
            <div class="opp-card-avatar" style="width:48px;height:48px;font-size:16px;flex-shrink:0">{{ $initials }}</div>
            <div style="flex:1;min-width:0">
                <h2 style="font-size:18px;font-weight:700;color:#111827;margin:0">
                    {{ $opportunity->patient->name ?? 'Unknown' }}
                </h2>
                <span style="font-size:13px;color:#6b7280">
                    {{ $opportunity->patient->phone ?? '' }}
                    @if($opportunity->patient->phone && $opportunity->display_label) · @endif
                    {{ $opportunity->display_label }}
                </span>
            </div>
            <span class="opp-intent-tag" style="background:{{ $pc['bg'] }};color:{{ $pc['text'] }};white-space:nowrap">{{ $pl }}</span>
        </div>

        {{-- Stage progress bar --}}
        <div style="padding:20px 24px;border-bottom:1px solid #f3f4f6">
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
                    ['label' => 'Estimated Value','value' => $opportunity->estimated_value ? 'Rs. '.number_format($opportunity->estimated_value) : '—'],
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
        <div style="padding:16px 24px;border-top:1px solid #f3f4f6;background:#fafafa;border-radius:0 0 12px 12px">
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
    </div>

    {{-- Linked Treatment Plan --}}
    @if($opportunity->treatmentPlan)
    <div class="opp-detail-card" style="margin-top:16px">
        <div style="padding:16px 24px;border-bottom:1px solid #f3f4f6">
            <h3 style="font-size:14px;font-weight:600;color:#111827;margin:0">Linked Treatment Plan</h3>
        </div>
        <div style="padding:16px 24px">
            <a href="{{ route('treatment-plans.show', $opportunity->treatmentPlan) }}" style="font-size:14px;color:#6366f1;text-decoration:none">
                {{ $opportunity->treatmentPlan->plan_name ?? 'Treatment Plan #'.$opportunity->treatment_plan_id }}
            </a>
            <span style="font-size:13px;color:#9ca3af;margin-left:8px">Rs. {{ number_format($opportunity->treatmentPlan->total ?? 0) }}</span>
        </div>
    </div>
    @endif

</div>

{{-- Convert Modal --}}
<div id="convert-prm-modal" class="opp-modal-overlay" style="display:none" onclick="closeConvertModal(event)">
    <div class="opp-modal opp-modal-sm">
        <div class="opp-modal-header">
            <h3>Convert to PRM Lead</h3>
            <p>Move this opportunity into the active sales pipeline</p>
            <button class="opp-modal-close" onclick="closeConvertModal()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="opp-modal-body">
            <div class="opp-patient-preview">
                <div class="opp-pat-avatar" id="convert-avatar">{{ $initials }}</div>
                <div>
                    <span class="opp-pat-name" id="convert-name">{{ $opportunity->patient->name ?? '' }}</span>
                    <span class="opp-pat-tag" id="convert-treatment">{{ $opportunity->display_label }}</span>
                </div>
            </div>
            <div class="opp-form-group" style="margin-top:16px">
                <label class="opp-form-label">Initial Pipeline Stage</label>
                <select class="opp-modal-select" id="convert-stage">
                    <option value="new">New Lead</option>
                    <option value="contacted">Contacted</option>
                    <option value="consultation" selected>Consultation Booked</option>
                </select>
            </div>
        </div>
        <div class="opp-modal-footer">
            <button type="button" class="btn-ghost-prm" onclick="closeConvertModal()">Cancel</button>
            <button type="button" class="btn-primary-prm" id="btn-do-convert">Convert to Lead</button>
        </div>
    </div>
</div>

<script>
window.oppRoutes = {
    base:      '{{ url("communication/opportunities") }}',
    csrfToken: '{{ csrf_token() }}',
};

// Stage move (from detail page)
function moveStage(id, newStatus) {
    fetch(`${window.oppRoutes.base}/${id}/stage`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.oppRoutes.csrfToken },
        body: JSON.stringify({ status: newStatus }),
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) window.location.reload();
        else alert('Failed to update stage.');
    });
}

function openConvertModal(id, name, treatment) {
    window._convertOppId = id;
    document.getElementById('convert-avatar').textContent = name.split(' ').map(w => w[0]||'').join('').slice(0,2).toUpperCase();
    document.getElementById('convert-name').textContent = name;
    document.getElementById('convert-treatment').textContent = treatment;
    document.getElementById('convert-prm-modal').style.display = 'flex';
}
function closeConvertModal(e) {
    if (e && e.target !== document.getElementById('convert-prm-modal')) return;
    document.getElementById('convert-prm-modal').style.display = 'none';
}

document.getElementById('btn-do-convert')?.addEventListener('click', function() {
    const id    = window._convertOppId;
    const stage = document.getElementById('convert-stage').value;
    fetch(`${window.oppRoutes.base}/${id}/convert`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.oppRoutes.csrfToken },
        body: JSON.stringify({ stage }),
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            closeConvertModal();
            window.location.href = window.oppRoutes.base;
        } else alert('Conversion failed.');
    });
});

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeConvertModal(); });
</script>
@endsection
