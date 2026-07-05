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

    {{-- Main Detail Card — shared partial, also used by the board/list popup modal --}}
    <div class="opp-detail-card">
        @include('communication.opportunities._detail-card', ['opportunity' => $opportunity])
    </div>

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
