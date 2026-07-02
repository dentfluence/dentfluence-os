{{-- resources/views/communication/followup/queue.blade.php --}}

@extends('layouts.communication')
@section('title', 'Follow-up Queue')

@section('communication-content')
<div class="fu-page">

    <div class="fu-topbar">
        <div class="fu-topbar-left">
            <a href="{{ route('communication.followup.index') }}" class="fu-back-btn">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            </a>
            <div>
                <h1 class="fu-page-title">Follow-up Queue</h1>
                <p class="fu-page-sub">All scheduled follow-ups</p>
            </div>
        </div>
        <div class="fu-topbar-right">
            <button class="fu-btn-secondary" id="btnFilters">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                Filters
            </button>
            <button class="fu-btn-primary" id="btnSchedule" onclick="openScheduleModal()">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Schedule Follow-up
            </button>
        </div>
    </div>

    {{-- FILTER TABS --}}
    <div class="fu-filter-tabs">
        @php
        $tabs = [
            'today'    => 'Today (' . $counts['today'] . ')',
            'overdue'  => 'Overdue (' . $counts['overdue'] . ')',
            'upcoming' => 'Upcoming (' . $counts['upcoming'] . ')',
            'all'      => 'All (' . $counts['all'] . ')',
        ];
        @endphp
        @foreach($tabs as $key => $label)
        <a href="{{ route('communication.followup.queue', ['filter' => $key]) }}"
           class="fu-filter-tab {{ $filter === $key ? 'fu-filter-tab-active' : '' }}">
            {{ $label }}
        </a>
        @endforeach
    </div>

    {{-- QUEUE LIST --}}
    <div class="fu-queue-list">
        @forelse($grouped as $date => $items)
            <div class="fu-queue-date-group">
                <div class="fu-queue-date-label">{{ \Carbon\Carbon::parse($date)->format('D, d M Y') }}</div>
                @foreach($items as $item)
                <div class="fu-queue-card {{ $item['type'] === 'overdue' ? 'fu-queue-card-overdue' : '' }}">
                    <div class="fu-queue-avatar" style="background: {{ $item['color'] }}18; color: {{ $item['color'] }}">
                        {{ strtoupper(substr($item['name'], 0, 2)) }}
                    </div>
                    <div class="fu-queue-info">
                        <span class="fu-queue-name">{{ $item['name'] }}</span>
                        <span class="fu-queue-meta">{{ $item['time'] }} · {{ ucfirst($item['channel']) }}</span>
                    </div>
                    @if($item['type'] === 'overdue')
                    <span class="fu-queue-overdue-tag">Overdue</span>
                    @endif
                    <div class="fu-queue-actions">
                        <button class="fu-queue-call" onclick="openCompleteModal({{ $item['id'] }})">
                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.63 3.38 2 2 0 0 1 3.6 1.21h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.81a16 16 0 0 0 6.29 6.29l1.87-1.87a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        </button>
                        <button class="fu-queue-more" onclick="openEventActions({{ $item['id'] }}, '{{ $item['name'] }}', '{{ $item['channel'] }}')">
                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
        @empty
            <div class="fu-empty-state" style="padding:60px;text-align:center;color:#888;">
                No follow-ups found for this filter.
            </div>
        @endforelse
    </div>

</div>

@include('components.followup.complete-followup-modal')
@include('components.followup.reschedule-modal')
@include('components.followup.add-note-modal')
@include('components.followup.schedule-followup-modal')
@include('components.followup.filter-sort-modal')

@endsection

@push('scripts')
<script src="{{ asset('resources/js/communication/followup-modals.js') }}"></script>
@endpush
