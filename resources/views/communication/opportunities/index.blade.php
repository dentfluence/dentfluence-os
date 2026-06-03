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
            <button class="btn-outline-prm" onclick="window.location.href='{{route('prm.index') }}'">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                View Pipeline
            </button>
            <button class="btn-primary-prm" onclick="openAddOpportunityModal()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Opportunity
            </button>
        </div>
    </div>

    {{-- Summary Stats --}}
    <div class="opp-stats-row">
        <div class="opp-stat-card opp-stat-blue">
            <div class="opp-stat-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div class="opp-stat-body">
                <span class="opp-stat-num">48</span>
                <span class="opp-stat-label">Total Open</span>
            </div>
            <span class="opp-stat-trend up">↑ 12%</span>
        </div>
        <div class="opp-stat-card opp-stat-amber">
            <div class="opp-stat-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div class="opp-stat-body">
                <span class="opp-stat-num">14</span>
                <span class="opp-stat-label">Follow-up Due</span>
            </div>
            <span class="opp-stat-trend warn">Due Today</span>
        </div>
        <div class="opp-stat-card opp-stat-green">
            <div class="opp-stat-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <div class="opp-stat-body">
                <span class="opp-stat-num">23</span>
                <span class="opp-stat-label">Converted (MTD)</span>
            </div>
            <span class="opp-stat-trend up">↑ 18%</span>
        </div>
        <div class="opp-stat-card opp-stat-purple">
            <div class="opp-stat-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <div class="opp-stat-body">
                <span class="opp-stat-num">₹4.2L</span>
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
        <div class="opp-filters-row">
            <select class="opp-filter-select" id="filter-intent">
                <option value="">All Intent Types</option>
                <option value="high">High Priority</option>
                <option value="warm">Warm Interest</option>
                <option value="cold">Cold / Long Term</option>
            </select>
            <select class="opp-filter-select" id="filter-treatment">
                <option value="">All Treatments</option>
                <option value="implant">Dental Implant</option>
                <option value="ortho">Orthodontics</option>
                <option value="whitening">Teeth Whitening</option>
                <option value="rct">Root Canal</option>
                <option value="crown">Crown / Bridge</option>
                <option value="veneer">Veneers</option>
            </select>
            <select class="opp-filter-select" id="filter-assigned">
                <option value="">All Staff</option>
                <option value="neha">Neha (Front Desk)</option>
                <option value="anjali">Anjali (Treatment Coord)</option>
                <option value="priya">Priya Singh</option>
            </select>
            <button class="opp-filter-btn" onclick="openFiltersModal()">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                Filters
            </button>
        </div>
    </div>

    {{-- Board View --}}
    <div id="view-board" class="opp-board">
        @php
        $stages = [
            ['key' => 'identified', 'label' => 'Identified', 'color' => '#6366f1', 'bg' => '#eef2ff', 'count' => 14],
            ['key' => 'nurturing', 'label' => 'Nurturing', 'color' => '#f59e0b', 'bg' => '#fffbeb', 'count' => 12],
            ['key' => 'estimate', 'label' => 'Estimate Given', 'color' => '#3b82f6', 'bg' => '#eff6ff', 'count' => 9],
            ['key' => 'committed', 'label' => 'Committed', 'color' => '#10b981', 'bg' => '#ecfdf5', 'count' => 7],
            ['key' => 'converted', 'label' => 'Converted', 'color' => '#22c55e', 'bg' => '#f0fdf4', 'count' => 6],
        ];

        $sampleOpps = [
            'identified' => [
                ['name' => 'Riya Sharma', 'treatment' => 'Dental Implant', 'value' => '₹45,000', 'intent' => 'high', 'due' => 'Today, 11 AM', 'source' => 'Call', 'assigned' => 'NK', 'days' => 3],
                ['name' => 'Amit Kulkarni', 'treatment' => 'Teeth Whitening', 'value' => '₹8,000', 'intent' => 'warm', 'due' => 'Tomorrow, 10 AM', 'source' => 'WhatsApp', 'assigned' => 'PS', 'days' => 5],
                ['name' => 'Priya Singh', 'treatment' => 'Veneers', 'value' => '₹60,000', 'intent' => 'warm', 'due' => '21 May, 12 PM', 'source' => 'Walk-in', 'assigned' => 'NK', 'days' => 8],
                ['name' => 'Neha Kapoor', 'treatment' => 'Orthodontics', 'value' => '₹80,000', 'intent' => 'cold', 'due' => '25 May, 11 AM', 'source' => 'Instagram', 'assigned' => 'AK', 'days' => 12],
            ],
            'nurturing' => [
                ['name' => 'Vikram Mehta', 'treatment' => 'Root Canal', 'value' => '₹12,000', 'intent' => 'high', 'due' => 'Today, 2 PM', 'source' => 'Call', 'assigned' => 'PS', 'days' => 7],
                ['name' => 'Karan Malhotra', 'treatment' => 'Crown', 'value' => '₹18,000', 'intent' => 'warm', 'due' => '20 May, 3 PM', 'source' => 'Referral', 'assigned' => 'NK', 'days' => 15],
                ['name' => 'Sunita Joshi', 'treatment' => 'Implant', 'value' => '₹50,000', 'intent' => 'cold', 'due' => '22 May, 11 AM', 'source' => 'Google', 'assigned' => 'AK', 'days' => 20],
            ],
            'estimate' => [
                ['name' => 'Anjali Verma', 'treatment' => 'Full Mouth Rehab', 'value' => '₹1,80,000', 'intent' => 'high', 'due' => 'Today, 11 AM', 'source' => 'Walk-in', 'assigned' => 'NK', 'days' => 4],
                ['name' => 'Rohit Tiwari', 'treatment' => 'Aligner', 'value' => '₹1,20,000', 'intent' => 'warm', 'due' => '21 May, 4 PM', 'source' => 'Instagram', 'assigned' => 'AK', 'days' => 9],
            ],
            'committed' => [
                ['name' => 'Siddharth Rao', 'treatment' => 'Dental Implant', 'value' => '₹48,000', 'intent' => 'high', 'due' => '22 May, 10 AM', 'source' => 'Call', 'assigned' => 'PS', 'days' => 6],
                ['name' => 'Harshita Agarwal', 'treatment' => 'Orthodontics', 'value' => '₹95,000', 'intent' => 'high', 'due' => '23 May, 2 PM', 'source' => 'Referral', 'assigned' => 'NK', 'days' => 11],
            ],
            'converted' => [
                ['name' => 'Nisha Chauhan', 'treatment' => 'Veneers', 'value' => '₹72,000', 'intent' => 'high', 'due' => 'Completed', 'source' => 'Google', 'assigned' => 'AK', 'days' => 0],
                ['name' => 'Pooja Desai', 'treatment' => 'Implant', 'value' => '₹52,000', 'intent' => 'high', 'due' => 'Completed', 'source' => 'Walk-in', 'assigned' => 'NK', 'days' => 0],
            ],
        ];

        $intentColors = ['high' => '#ef4444', 'warm' => '#f59e0b', 'cold' => '#6366f1'];
        $intentLabels = ['high' => 'High Priority', 'warm' => 'Warm', 'cold' => 'Long Term'];
        @endphp

        @foreach($stages as $stage)
        <div class="opp-column" data-stage="{{ $stage['key'] }}">
            <div class="opp-col-header" style="border-top: 3px solid {{ $stage['color'] }}">
                <div class="opp-col-title-row">
                    <span class="opp-col-title">{{ $stage['label'] }}</span>
                    <span class="opp-col-badge" style="background: {{ $stage['bg'] }}; color: {{ $stage['color'] }}">{{ $stage['count'] }}</span>
                </div>
            </div>
            <div class="opp-col-body" id="col-{{ $stage['key'] }}">
                @foreach(($sampleOpps[$stage['key']] ?? []) as $opp)
                <div class="opp-card" draggable="true" onclick="openOpportunityDetail(this)">
                    <div class="opp-card-top">
                        <div class="opp-card-avatar">{{ substr($opp['name'], 0, 2) }}</div>
                        <div class="opp-card-nameblock">
                            <span class="opp-card-name">{{ $opp['name'] }}</span>
                            <span class="opp-card-treatment">{{ $opp['treatment'] }}</span>
                        </div>
                        <button class="opp-card-menu" onclick="event.stopPropagation(); toggleCardMenu(this)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                        </button>
                    </div>
                    <div class="opp-card-value-row">
                        <span class="opp-card-value">{{ $opp['value'] }}</span>
                        <span class="opp-intent-tag" style="background: {{ $intentColors[$opp['intent']] }}20; color: {{ $intentColors[$opp['intent']] }}">{{ $intentLabels[$opp['intent']] }}</span>
                    </div>
                    <div class="opp-card-meta">
                        <span class="opp-meta-item">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            {{ $opp['due'] }}
                        </span>
                        <span class="opp-meta-source">{{ $opp['source'] }}</span>
                    </div>
                    <div class="opp-card-footer">
                        @if($opp['days'] > 0)
                        <span class="opp-card-age">{{ $opp['days'] }}d in stage</span>
                        @else
                        <span class="opp-card-age converted-tag">✓ Converted</span>
                        @endif
                        <div class="opp-card-actions">
                            <button class="opp-quick-btn" title="Call" onclick="event.stopPropagation()">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81a19.79 19.79 0 01-3.07-8.63A2 2 0 012 0h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 14z"/></svg>
                            </button>
                            <button class="opp-quick-btn whatsapp" title="WhatsApp" onclick="event.stopPropagation()">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
                            </button>
                            <button class="opp-quick-btn convert" title="Convert to PRM" onclick="event.stopPropagation(); openConvertModal('{{ $opp['name'] }}')">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
                @endforeach
                <button class="opp-add-card-btn" onclick="openAddOpportunityModal('{{ $stage['key'] }}')">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Opportunity
                </button>
            </div>
        </div>
        @endforeach
    </div>

    {{-- List View (hidden by default) --}}
    <div id="view-list" class="opp-list-view" style="display: none">
        <table class="opp-table">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Treatment</th>
                    <th>Stage</th>
                    <th>Value</th>
                    <th>Intent</th>
                    <th>Assigned</th>
                    <th>Next Follow-up</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @php
                $allOpps = [
                    ['name'=>'Riya Sharma','treatment'=>'Dental Implant','stage'=>'Identified','value'=>'₹45,000','intent'=>'high','assigned'=>'Neha','due'=>'Today, 11 AM','stageColor'=>'#6366f1'],
                    ['name'=>'Vikram Mehta','treatment'=>'Root Canal','stage'=>'Nurturing','value'=>'₹12,000','intent'=>'high','assigned'=>'Priya','due'=>'Today, 2 PM','stageColor'=>'#f59e0b'],
                    ['name'=>'Anjali Verma','treatment'=>'Full Mouth Rehab','stage'=>'Estimate Given','value'=>'₹1,80,000','intent'=>'high','assigned'=>'Neha','due'=>'Today, 11 AM','stageColor'=>'#3b82f6'],
                    ['name'=>'Amit Kulkarni','treatment'=>'Teeth Whitening','stage'=>'Identified','value'=>'₹8,000','intent'=>'warm','assigned'=>'Priya','due'=>'Tomorrow, 10 AM','stageColor'=>'#6366f1'],
                    ['name'=>'Siddharth Rao','treatment'=>'Dental Implant','stage'=>'Committed','value'=>'₹48,000','intent'=>'high','assigned'=>'Priya','due'=>'22 May, 10 AM','stageColor'=>'#10b981'],
                    ['name'=>'Priya Singh','treatment'=>'Veneers','stage'=>'Identified','value'=>'₹60,000','intent'=>'warm','assigned'=>'Neha','due'=>'21 May, 12 PM','stageColor'=>'#6366f1'],
                    ['name'=>'Nisha Chauhan','treatment'=>'Veneers','stage'=>'Converted','value'=>'₹72,000','intent'=>'high','assigned'=>'Anjali','due'=>'Completed','stageColor'=>'#22c55e'],
                    ['name'=>'Rohit Tiwari','treatment'=>'Aligner','stage'=>'Estimate Given','value'=>'₹1,20,000','intent'=>'warm','assigned'=>'Anjali','due'=>'21 May, 4 PM','stageColor'=>'#3b82f6'],
                ];
                @endphp
                @foreach($allOpps as $opp)
                <tr class="opp-table-row" onclick="openOpportunityDetail(this)">
                    <td>
                        <div class="opp-tbl-patient">
                            <div class="opp-tbl-avatar">{{ substr($opp['name'], 0, 2) }}</div>
                            <span>{{ $opp['name'] }}</span>
                        </div>
                    </td>
                    <td>{{ $opp['treatment'] }}</td>
                    <td><span class="opp-stage-chip" style="background:{{ $opp['stageColor'] }}20; color:{{ $opp['stageColor'] }}">{{ $opp['stage'] }}</span></td>
                    <td class="opp-value-cell">{{ $opp['value'] }}</td>
                    <td><span class="opp-intent-tag" style="background: {{ $intentColors[$opp['intent']] }}20; color: {{ $intentColors[$opp['intent']] }}">{{ $intentLabels[$opp['intent']] }}</span></td>
                    <td>{{ $opp['assigned'] }}</td>
                    <td class="{{ str_contains($opp['due'], 'Today') ? 'opp-due-today' : '' }}">{{ $opp['due'] }}</td>
                    <td>
                        <div class="opp-tbl-actions">
                            <button class="opp-tbl-btn" onclick="event.stopPropagation()">Call</button>
                            <button class="opp-tbl-btn wa" onclick="event.stopPropagation()">WA</button>
                        </div>
                    </td>
                </tr>
                @endforeach
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
        <div class="opp-modal-body">
            <div class="opp-form-group">
                <label class="opp-form-label">Patient / Lead <span class="req">*</span></label>
                <div class="opp-search-field">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" placeholder="Search patient or lead by name or phone..." class="opp-modal-input no-border">
                </div>
            </div>
            <div class="opp-form-row">
                <div class="opp-form-group">
                    <label class="opp-form-label">Treatment Interest <span class="req">*</span></label>
                    <select class="opp-modal-select">
                        <option value="">Select treatment</option>
                        <option>Dental Implant</option>
                        <option>Orthodontics / Aligners</option>
                        <option>Teeth Whitening</option>
                        <option>Veneers</option>
                        <option>Root Canal</option>
                        <option>Crown / Bridge</option>
                        <option>Full Mouth Rehab</option>
                        <option>Other</option>
                    </select>
                </div>
                <div class="opp-form-group">
                    <label class="opp-form-label">Estimated Value</label>
                    <div class="opp-input-prefix">
                        <span class="opp-prefix">₹</span>
                        <input type="text" placeholder="0" class="opp-modal-input with-prefix">
                    </div>
                </div>
            </div>
            <div class="opp-form-row">
                <div class="opp-form-group">
                    <label class="opp-form-label">Intent Level <span class="req">*</span></label>
                    <div class="opp-intent-picker">
                        <label class="intent-option high">
                            <input type="radio" name="intent" value="high">
                            <span>🔴 High Priority</span>
                        </label>
                        <label class="intent-option warm">
                            <input type="radio" name="intent" value="warm" checked>
                            <span>🟡 Warm</span>
                        </label>
                        <label class="intent-option cold">
                            <input type="radio" name="intent" value="cold">
                            <span>🔵 Long Term</span>
                        </label>
                    </div>
                </div>
                <div class="opp-form-group">
                    <label class="opp-form-label">Assign To <span class="req">*</span></label>
                    <select class="opp-modal-select">
                        <option>Neha (Front Desk)</option>
                        <option>Anjali (Treatment Coord)</option>
                        <option>Priya Singh</option>
                    </select>
                </div>
            </div>
            <div class="opp-form-row">
                <div class="opp-form-group">
                    <label class="opp-form-label">Next Follow-up Date <span class="req">*</span></label>
                    <input type="date" class="opp-modal-input" value="{{ date('Y-m-d', strtotime('+3 days')) }}">
                </div>
                <div class="opp-form-group">
                    <label class="opp-form-label">Follow-up Time <span class="req">*</span></label>
                    <input type="time" class="opp-modal-input" value="11:00">
                </div>
            </div>
            <div class="opp-form-group">
                <label class="opp-form-label">Notes (How did this opportunity come up?)</label>
                <textarea class="opp-modal-textarea" rows="3" placeholder="E.g. Patient mentioned interest in whitening during RCT procedure. Will follow up after treatment completes."></textarea>
            </div>
            <div class="opp-info-banner">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                This opportunity will appear in the Follow-up Calendar and Communication list on the selected date.
            </div>
        </div>
        <div class="opp-modal-footer">
            <button class="btn-ghost-prm" onclick="closeAddOpportunityModal()">Cancel</button>
            <button class="btn-primary-prm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                Save Opportunity
            </button>
        </div>
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
            <div class="opp-patient-preview" id="convert-patient-name">
                <div class="opp-pat-avatar" id="convert-avatar">RS</div>
                <div>
                    <span class="opp-pat-name" id="convert-name">Riya Sharma</span>
                    <span class="opp-pat-tag">Dental Implant</span>
                </div>
            </div>
            <div class="opp-info-banner convert-info">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Converting will create a new PRM lead and move all opportunity history to the lead timeline.
            </div>
            <div class="opp-form-group" style="margin-top: 16px">
                <label class="opp-form-label">Initial Pipeline Stage</label>
                <select class="opp-modal-select">
                    <option>New Lead</option>
                    <option>Contacted</option>
                    <option selected>Consultation Booked</option>
                    <option>Visited Clinic</option>
                </select>
            </div>
            <div class="opp-form-group">
                <label class="opp-form-label">Assign To</label>
                <select class="opp-modal-select">
                    <option>Neha (Front Desk)</option>
                    <option>Anjali (Treatment Coord)</option>
                </select>
            </div>
        </div>
        <div class="opp-modal-footer">
            <button class="btn-ghost-prm" onclick="closeConvertModal()">Cancel</button>
            <button class="btn-primary-prm" onclick="closeConvertModal()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/></svg>
                Convert to Lead
            </button>
        </div>
    </div>
</div>

@push('communication-scripts')
    @vite('resources/js/communication/opportunities.js')
@endpush
@push('communication-scripts')
    @vite('resources/js/communication/opportunities.js')
@endpush
@endsection