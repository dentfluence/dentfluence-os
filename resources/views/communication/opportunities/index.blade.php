@extends('layouts.communication')

@section('title', 'Opportunity Engine')

@push('communication-styles')
    @vite('resources/css/communication/opportunities.css')
@endpush

@section('communication-content')
<div class="opp-page">

    {{-- Top Bar --}}
    <div class="opp-topbar">
        <div class="opp-topbar-left">
            <div class="opp-page-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            </div>
            <div>
                <h1 class="opp-page-title">Opportunity Engine</h1>
                <p class="opp-page-sub">Track and nurture patient treatment opportunities</p>
            </div>
        </div>
        <div class="opp-topbar-right">
            {{-- Phase 8 PRM Retirement (Slice 5) — points at PRE's lead pipeline now. --}}
            <button class="btn-outline-prm" onclick="window.location.href='{{ route('relationship.pipeline') }}'">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                View Pipeline
            </button>
            <button class="btn-primary-prm" onclick="openAddOpportunityModal()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Opportunity
            </button>
        </div>
    </div>

    {{-- Flash message --}}
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Summary Stats --}}
    <div class="opp-stats-row">
        <div class="opp-stat-card opp-stat-blue">
            <div class="opp-stat-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div class="opp-stat-body">
                <span class="opp-stat-num">{{ $stats['total_open'] }}</span>
                <span class="opp-stat-label">Total Open</span>
            </div>
        </div>
        <div class="opp-stat-card opp-stat-amber">
            <div class="opp-stat-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div class="opp-stat-body">
                <span class="opp-stat-num">{{ $stats['followup_today'] }}</span>
                <span class="opp-stat-label">Follow-up Due</span>
            </div>
            <span class="opp-stat-trend warn">Due Today</span>
        </div>
        <div class="opp-stat-card opp-stat-green">
            <div class="opp-stat-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <div class="opp-stat-body">
                <span class="opp-stat-num">{{ $stats['converted_mtd'] }}</span>
                <span class="opp-stat-label">Converted (MTD)</span>
            </div>
        </div>
        <div class="opp-stat-card opp-stat-purple">
            <div class="opp-stat-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <div class="opp-stat-body">
                @php $lakh = $stats['pipeline_value'] >= 100000 ? round($stats['pipeline_value']/100000, 1).'L' : 'Rs. '.number_format($stats['pipeline_value']); @endphp
                <span class="opp-stat-num">Rs. {{ $lakh }}</span>
                <span class="opp-stat-label">Pipeline Value</span>
            </div>
            <span class="opp-stat-trend up">Est. Revenue</span>
        </div>
    </div>

    {{-- View Toggle + Filters --}}
    <div class="opp-controls">
        <div class="opp-view-toggle">
            <button class="view-btn active" id="btn-board" onclick="switchView('board')">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                Board
            </button>
            <button class="view-btn" id="btn-list" onclick="switchView('list')">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                List
            </button>
        </div>
    </div>

    {{-- Board View --}}
    @php
        $stages = \App\Models\TreatmentOpportunity::STAGES;
        $priorityColors = \App\Models\TreatmentOpportunity::PRIORITY_COLORS;
        $priorityLabels = \App\Models\TreatmentOpportunity::PRIORITY_LABELS;
        $boardStages = array_filter($stages, fn($k) => $k !== 'declined', ARRAY_FILTER_USE_KEY);
    @endphp

    <div id="view-board" class="opp-board">
        @foreach($boardStages as $statusKey => $stageInfo)
        @php $colOpps = $grouped->get($statusKey, collect()); @endphp
        <div class="opp-column" data-stage="{{ $statusKey }}">
            <div class="opp-col-header" style="border-top: 3px solid {{ $stageInfo['color'] }}">
                <div class="opp-col-title-row">
                    <span class="opp-col-title">{{ $stageInfo['label'] }}</span>
                    <span class="opp-col-badge" style="background:{{ $stageInfo['bg'] }};color:{{ $stageInfo['color'] }}">{{ $colOpps->count() }}</span>
                </div>
            </div>
            <div class="opp-col-body" id="col-{{ $statusKey }}">
                @forelse($colOpps as $opp)
                @php
                    $pc       = $priorityColors[$opp->priority] ?? ['bg' => '#f3f4f6', 'text' => '#6b7280'];
                    $pl       = $priorityLabels[$opp->priority] ?? $opp->priority;
                    $initials = collect(explode(' ', $opp->patient->name ?? 'U'))
                                    ->map(fn($w) => strtoupper($w[0] ?? ''))
                                    ->take(2)->implode('');
                    $daysInStage = (int) $opp->updated_at->diffInDays(now());
                @endphp
                <div class="opp-card"
                     draggable="true"
                     data-id="{{ $opp->id }}"
                     data-status="{{ $opp->status }}"
                     onclick="openOpportunityDetailModal({{ $opp->id }})">
                    <div class="opp-card-top">
                        <div class="opp-card-avatar">{{ $initials }}</div>
                        <div class="opp-card-nameblock">
                            <span class="opp-card-name">{{ $opp->patient->name ?? 'Unknown' }}</span>
                            <span class="opp-card-treatment">{{ $opp->display_label }}</span>
                        </div>
                    </div>
                    <div class="opp-card-value-row">
                        <span class="opp-card-value">{{ $opp->estimated_value ? 'Rs. '.number_format($opp->estimated_value) : '—' }}</span>
                        <span class="opp-intent-tag" style="background:{{ $pc['bg'] }};color:{{ $pc['text'] }}">{{ $pl }}</span>
                    </div>
                    @if($opp->follow_up_date)
                    <div class="opp-card-meta">
                        <span class="opp-meta-item {{ $opp->is_overdue ? 'text-danger' : ($opp->due_today ? 'opp-due-today' : '') }}">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            @if($opp->due_today) Today
                            @elseif($opp->is_overdue) Overdue · {{ $opp->follow_up_date->format('d M') }}
                            @else {{ $opp->follow_up_date->format('d M') }}
                            @endif
                        </span>
                    </div>
                    @endif
                    <div class="opp-card-footer">
                        @if($opp->status === 'completed')
                            <span class="opp-card-age converted-tag">✓ Converted</span>
                        @else
                            <span class="opp-card-age">{{ $daysInStage }}d in stage</span>
                        @endif
                        <div class="opp-card-actions">
                            <button class="opp-quick-btn convert"
                                    title="Convert to Lead"
                                    onclick="event.stopPropagation(); openConvertModal({{ $opp->id }}, '{{ addslashes($opp->patient->name ?? '') }}', '{{ addslashes($opp->display_label) }}')">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
                @empty
                <div class="opp-col-empty">No opportunities</div>
                @endforelse
                <button class="opp-add-card-btn" onclick="openAddOpportunityModal('{{ $statusKey }}')">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Opportunity
                </button>
            </div>
        </div>
        @endforeach
    </div>

    {{-- List View --}}
    <div id="view-list" class="opp-list-view" style="display:none">
        <table class="opp-table">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Treatment</th>
                    <th>Stage</th>
                    <th>Value</th>
                    <th>Priority</th>
                    <th>Assigned</th>
                    <th>Follow-up</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($opportunities as $opp)
                @php
                    $stg = $stages[$opp->status] ?? ['label' => $opp->status, 'color' => '#6b7280', 'bg' => '#f3f4f6'];
                    $pc  = $priorityColors[$opp->priority] ?? ['bg' => '#f3f4f6', 'text' => '#6b7280'];
                    $pl  = $priorityLabels[$opp->priority] ?? $opp->priority;
                @endphp
                <tr class="opp-table-row"
                    onclick="openOpportunityDetailModal({{ $opp->id }})">
                    <td>
                        <div class="opp-tbl-patient">
                            <div class="opp-tbl-avatar">
                                {{ collect(explode(' ', $opp->patient->name ?? 'U'))->map(fn($w) => strtoupper($w[0] ?? ''))->take(2)->implode('') }}
                            </div>
                            <span>{{ $opp->patient->name ?? 'Unknown' }}</span>
                        </div>
                    </td>
                    <td>{{ $opp->display_label }}</td>
                    <td><span class="opp-stage-chip" style="background:{{ $stg['bg'] }};color:{{ $stg['color'] }}">{{ $stg['label'] }}</span></td>
                    <td class="opp-value-cell">{{ $opp->estimated_value ? 'Rs. '.number_format($opp->estimated_value) : '—' }}</td>
                    <td><span class="opp-intent-tag" style="background:{{ $pc['bg'] }};color:{{ $pc['text'] }}">{{ $pl }}</span></td>
                    <td>{{ $opp->assignedStaff?->name ?? '—' }}</td>
                    <td class="{{ $opp->is_overdue ? 'text-danger fw-semibold' : '' }}">
                        {{ $opp->follow_up_date ? $opp->follow_up_date->format('d M Y') : '—' }}
                    </td>
                    <td>
                        <button class="opp-tbl-btn"
                                onclick="event.stopPropagation(); openConvertModal({{ $opp->id }}, '{{ addslashes($opp->patient->name ?? '') }}', '{{ addslashes($opp->display_label) }}')">
                            Convert
                        </button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center py-4 text-muted">No opportunities yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

{{-- Add Opportunity Modal --}}
<div id="add-opp-modal" class="opp-modal-overlay" style="display:none" onclick="closeAddOpportunityModal(event)">
    <div class="opp-modal">
        <div class="opp-modal-header">
            <h3>Add Opportunity</h3>
            <p>Track a future treatment interest for a patient</p>
            <button class="opp-modal-close" onclick="closeAddOpportunityModal()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="add-opp-form" method="POST" action="{{ route('communication.opportunities.store') }}">
        @csrf
        <div class="opp-modal-body">
            <div class="opp-form-group">
                <label class="opp-form-label">Patient <span class="req">*</span></label>
                <div class="opp-search-field" style="position:relative">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="patient-search-input" placeholder="Search by name or phone..." class="opp-modal-input no-border" autocomplete="off">
                    <input type="hidden" name="patient_id" id="patient-id-input">
                    <div id="patient-search-results" class="opp-search-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #e5e7eb;border-radius:8px;z-index:100;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.1)"></div>
                </div>
            </div>
            <div class="opp-form-row">
                <div class="opp-form-group">
                    <label class="opp-form-label">Treatment Interest <span class="req">*</span></label>
                    <select class="opp-modal-select" name="type" required>
                        <option value="">Select treatment</option>
                        @foreach(\App\Models\TreatmentOpportunity::TREATMENT_TYPES as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="opp-form-group">
                    <label class="opp-form-label">Estimated Value</label>
                    <div class="opp-input-prefix">
                        <span class="opp-prefix">Rs. </span>
                        <input type="number" name="estimated_value" placeholder="0" class="opp-modal-input with-prefix" min="0">
                    </div>
                </div>
            </div>
            <div class="opp-form-row">
                <div class="opp-form-group">
                    <label class="opp-form-label">Priority <span class="req">*</span></label>
                    <div class="opp-intent-picker">
                        <label class="intent-option high"><input type="radio" name="priority" value="high"><span>High Priority</span></label>
                        <label class="intent-option warm"><input type="radio" name="priority" value="medium" checked><span>Warm</span></label>
                        <label class="intent-option cold"><input type="radio" name="priority" value="low"><span>Long Term</span></label>
                    </div>
                </div>
                <div class="opp-form-group">
                    <label class="opp-form-label">Assign To</label>
                    <select class="opp-modal-select" name="assigned_to">
                        <option value="">— Unassigned —</option>
                        @foreach($staff as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="opp-form-row">
                <div class="opp-form-group">
                    <label class="opp-form-label">Follow-up Date <span class="req">*</span></label>
                    <input type="date" name="follow_up_date" class="opp-modal-input" value="{{ date('Y-m-d', strtotime('+3 days')) }}" required>
                </div>
                <div class="opp-form-group">
                    <label class="opp-form-label">Follow-up Time</label>
                    <input type="time" name="follow_up_time" class="opp-modal-input" value="11:00">
                </div>
            </div>
            <div class="opp-form-group">
                <label class="opp-form-label">Notes</label>
                <textarea class="opp-modal-textarea" name="notes" rows="3" placeholder="How did this opportunity come up?"></textarea>
            </div>
        </div>
        <div class="opp-modal-footer">
            <button type="button" class="btn-ghost-prm" onclick="closeAddOpportunityModal()">Cancel</button>
            <button type="submit" class="btn-primary-prm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                Save Opportunity
            </button>
        </div>
        </form>
    </div>
</div>

{{-- Convert to PRM Modal --}}
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
                <div class="opp-pat-avatar" id="convert-avatar">—</div>
                <div>
                    <span class="opp-pat-name" id="convert-name">—</span>
                    <span class="opp-pat-tag" id="convert-treatment">—</span>
                </div>
            </div>
            <div class="opp-info-banner convert-info" style="margin-top:12px">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Converting will create a PRM lead and mark this opportunity as Converted.
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
            <button type="button" class="btn-primary-prm" id="btn-do-convert">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/></svg>
                Convert to Lead
            </button>
        </div>
    </div>
</div>

{{-- Opportunity Detail Modal — board/list cards open this instead of navigating to a new page --}}
<div id="opp-detail-modal" class="opp-modal-overlay" style="display:none" onclick="closeOpportunityDetailModal(event)">
    <div class="opp-modal" style="max-width:720px">
        <div class="opp-modal-header">
            <h3>Opportunity Detail</h3>
            <p>Full history and stage controls for this opportunity</p>
            <button class="opp-modal-close" onclick="closeOpportunityDetailModal()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="opp-modal-body" id="opp-detail-modal-body" style="padding:0;max-height:70vh;overflow-y:auto">
            <div style="padding:48px 24px;text-align:center;color:#9ca3af;font-size:13px">Loading...</div>
        </div>
    </div>
</div>

<script>
window.oppRoutes = {
    base:          '{{ url("communication/opportunities") }}',
    patientSearch: '{{ route("communication.opportunities.patient-search") }}',
    csrfToken:     '{{ csrf_token() }}',
};
</script>

@push('communication-scripts')
    @vite('resources/js/communication/opportunities.js')
@endpush
@endsection
