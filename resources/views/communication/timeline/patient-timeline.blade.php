{{-- resources/views/communication/timeline/patient-timeline.blade.php --}}
@extends('layouts.communication')

@section('title', $person['name'] . ' — Communication Timeline')

@section('content')
<div class="patient-timeline-page" data-person-id="{{ $personId }}">

    {{-- Back + Header --}}
    <div class="page-header">
        <div class="page-header-left">
            <a href="{{ route('communication.timeline.index') }}" class="back-btn">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div class="page-icon">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <h1 class="page-title">Communication Timeline</h1>
                <p class="page-subtitle">Full communication history for {{ $person['name'] }}</p>
            </div>
        </div>
        <div class="page-header-right">
            <a href="{{ route('communication.timeline.show', $personId) }}?type=all" class="btn-secondary btn-sm">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
                All Activity
            </a>
        </div>
    </div>

    <div class="timeline-layout">

        {{-- LEFT: Person Profile Card + Quick Actions --}}
        <div class="timeline-sidebar">

            {{-- Person Card --}}
            <div class="person-profile-card">
                <div class="profile-avatar {{ $person['type'] === 'patient' ? 'avatar-patient' : 'avatar-lead' }}">
                    {{ $person['avatar'] }}
                </div>
                <h2 class="profile-name">{{ $person['name'] }}</h2>
                <div class="profile-type-badge {{ $person['type'] === 'patient' ? 'badge-patient' : 'badge-lead' }}">
                    {{ $person['type'] === 'patient' ? 'Patient' : 'Lead' }}
                </div>

                <div class="profile-details">
                    <div class="profile-detail-row">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.948V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 8V5z"/>
                        </svg>
                        <span>{{ $person['phone'] }}</span>
                    </div>
                    <div class="profile-detail-row">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                        <span>{{ $person['treatment'] }}</span>
                    </div>
                    <div class="profile-detail-row">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <span>{{ $person['assigned_to'] }}</span>
                    </div>
                    <div class="profile-detail-row">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ $person['status'] }}</span>
                    </div>
                </div>

                <div class="profile-actions">
                    <button class="profile-action-btn btn-call" onclick="window.open('tel:{{ str_replace(' ', '', $person['phone']) }}')">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.948V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 8V5z"/>
                        </svg>
                        Call
                    </button>
                    <button class="profile-action-btn btn-whatsapp"
                        onclick="window.open('https://wa.me/91{{ str_replace([' ', '+91'], '', $person['phone']) }}', '_blank')">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                            <path d="M12 0C5.373 0 0 5.373 0 12c0 2.125.557 4.118 1.528 5.845L0 24l6.335-1.652A11.954 11.954 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 01-5.006-1.371l-.36-.214-3.723.973.99-3.617-.235-.372A9.818 9.818 0 1112 21.818z"/>
                        </svg>
                        WhatsApp
                    </button>
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="quick-actions-card">
                <h3 class="qa-title">Quick Actions</h3>
                <div class="qa-list">
                    <button class="qa-item" onclick="openAddNoteModal()">
                        <div class="qa-icon qa-note">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </div>
                        <span>Add Note</span>
                        <svg class="qa-arrow" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                    <button class="qa-item" onclick="openScheduleFollowupModal()">
                        <div class="qa-icon qa-followup">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <span>Schedule Follow-up</span>
                        <svg class="qa-arrow" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                    <button class="qa-item"
                        onclick="window.open('https://wa.me/91{{ str_replace([' ', '+91'], '', $person['phone']) }}', '_blank')">
                        <div class="qa-icon qa-whatsapp">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                                <path d="M12 0C5.373 0 0 5.373 0 12c0 2.125.557 4.118 1.528 5.845L0 24l6.335-1.652A11.954 11.954 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 01-5.006-1.371l-.36-.214-3.723.973.99-3.617-.235-.372A9.818 9.818 0 1112 21.818z"/>
                            </svg>
                        </div>
                        <span>Send WhatsApp</span>
                        <svg class="qa-arrow" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                    <button class="qa-item" onclick="openLogCallModal()">
                        <div class="qa-icon qa-call">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.948V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 8V5z"/>
                            </svg>
                        </div>
                        <span>Log a Call</span>
                        <svg class="qa-arrow" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                    @if($person['type'] === 'lead')
                    <button class="qa-item qa-convert" onclick="openConvertModal()">
                        <div class="qa-icon qa-patient">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                            </svg>
                        </div>
                        <span>Convert to Patient</span>
                        <svg class="qa-arrow" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                    @endif
                </div>
            </div>

            {{-- Timeline Summary --}}
            <div class="timeline-summary-card">
                <h3 class="qa-title">Activity Summary</h3>
                <div class="summary-stats">
                    @php
                        $callCount      = count(array_filter($timeline, fn($e) => $e['type'] === 'call'));
                        $noteCount      = count(array_filter($timeline, fn($e) => $e['type'] === 'note'));
                        $followupCount  = count(array_filter($timeline, fn($e) => $e['type'] === 'followup'));
                        $waCount        = count(array_filter($timeline, fn($e) => $e['type'] === 'whatsapp'));
                        $taskCount      = count(array_filter($timeline, fn($e) => $e['type'] === 'task'));
                        $opCount        = count(array_filter($timeline, fn($e) => $e['type'] === 'opportunity'));
                    @endphp
                    <div class="summary-item">
                        <span class="summary-icon si-call">📞</span>
                        <span class="summary-count">{{ $callCount }}</span>
                        <span class="summary-label">Calls</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-icon si-wa">💬</span>
                        <span class="summary-count">{{ $waCount }}</span>
                        <span class="summary-label">WhatsApp</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-icon si-note">📝</span>
                        <span class="summary-count">{{ $noteCount }}</span>
                        <span class="summary-label">Notes</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-icon si-followup">📅</span>
                        <span class="summary-count">{{ $followupCount }}</span>
                        <span class="summary-label">Follow-ups</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-icon si-task">✅</span>
                        <span class="summary-count">{{ $taskCount }}</span>
                        <span class="summary-label">Tasks</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-icon si-op">💡</span>
                        <span class="summary-count">{{ $opCount }}</span>
                        <span class="summary-label">Opportunities</span>
                    </div>
                </div>
            </div>

        </div>

        {{-- RIGHT: Timeline --}}
        <div class="timeline-main">

            {{-- Filter Tabs --}}
            <div class="timeline-filter-bar">
                @php
                    $filters = [
                        'all'         => 'All Activity',
                        'call'        => 'Calls',
                        'whatsapp'    => 'WhatsApp',
                        'note'        => 'Notes',
                        'followup'    => 'Follow-ups',
                        'appointment' => 'Appointments',
                        'task'        => 'Tasks',
                        'opportunity' => 'Opportunities',
                    ];
                @endphp
                @foreach($filters as $key => $label)
                    <a href="{{ route('communication.timeline.show', $personId) }}?type={{ $key }}"
                       class="filter-tab {{ $filterType === $key ? 'active' : '' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            {{-- Timeline Events --}}
            @if(count($timeline) > 0)
                <div class="timeline-wrapper">
                    @foreach($timeline as $index => $event)
                        @include('components.timeline.timeline-item', ['event' => $event, 'index' => $index])
                    @endforeach
                </div>
            @else
                <div class="timeline-empty">
                    <div class="empty-icon">
                        <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <p>No {{ $filterType !== 'all' ? $filterType . ' activity' : 'activity' }} recorded yet.</p>
                    <button class="btn-primary btn-sm" onclick="openAddNoteModal()">Add First Entry</button>
                </div>
            @endif

        </div>
    </div>

</div>

{{-- Add Note Modal --}}
@include('components.timeline.add-note-modal', ['person' => $person])

{{-- Schedule Follow-up Modal --}}
@include('components.timeline.schedule-followup-modal', ['person' => $person])

{{-- Log Call Modal --}}
@include('components.timeline.log-call-modal', ['person' => $person])

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/communication/timeline.css') }}">
@endpush

@push('scripts')
<script src="{{ asset('js/communication/timeline.js') }}"></script>
@endpush
