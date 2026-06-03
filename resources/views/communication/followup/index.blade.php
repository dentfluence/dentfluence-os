{{-- resources/views/communication/followup/index.blade.php --}}

@extends('layouts.communication')

@section('title', 'Follow-up Calendar')

@section('content')
<div style="padding:10px 20px 10px 28px;border-bottom:1px solid rgba(0,0,0,0.06);background:#fff;position:relative;z-index:10;">
    <a href="/communication" style="font-size:12px;color:#5A5A56;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Communication
    </a>
</div>

<div class="fu-page">

    {{-- TOP BAR --}}
    <div class="fu-topbar">
        <div class="fu-topbar-left">
            <div class="fu-page-icon">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div>
                <h1 class="fu-page-title">Follow-up Calendar</h1>
                <p class="fu-page-sub">Schedule and manage follow-ups</p>
            </div>
        </div>
        <div class="fu-topbar-right">
            <button class="fu-btn-secondary" id="btnFilters">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                Filters
            </button>
            <div class="fu-schedule-wrap">
                <button class="fu-btn-primary" id="btnSchedule">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Schedule Follow-up
                </button>
                <button class="fu-btn-primary fu-btn-caret" id="btnScheduleCaret">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
            </div>
        </div>
    </div>

    {{-- STATS ROW --}}
    <div class="fu-stats-row">
        @php
        $statItems = [
            ['icon' => 'calendar', 'label' => 'Total Follow-ups', 'value' => $stats['total'],     'color' => '#6B5BDF'],
            ['icon' => 'clock',    'label' => 'Due Today',         'value' => $stats['due_today'], 'color' => '#F97316'],
            ['icon' => 'alert',    'label' => 'Overdue',           'value' => $stats['overdue'],   'color' => '#EF4444'],
            ['icon' => 'check',    'label' => 'Completed',         'value' => $stats['completed'], 'color' => '#22C55E'],
            ['icon' => 'upcoming', 'label' => 'Upcoming',          'value' => $stats['upcoming'],  'color' => '#3B82F6'],
        ];
        @endphp
        @foreach($statItems as $stat)
        <div class="fu-stat-card">
            <div class="fu-stat-icon" style="background: {{ $stat['color'] }}18; color: {{ $stat['color'] }}">
                @include('communication.followup.partials.icon', ['name' => $stat['icon']])
            </div>
            <div class="fu-stat-body">
                <span class="fu-stat-label">{{ $stat['label'] }}</span>
                <span class="fu-stat-value">{{ $stat['value'] }}</span>
            </div>
        </div>
        @endforeach
    </div>

    {{-- CALENDAR SECTION --}}
    <div class="fu-calendar-section">

        {{-- CALENDAR CONTROLS --}}
        <div class="fu-cal-controls">
            <div class="fu-cal-nav">
                <button class="fu-nav-btn" id="btnToday">Today</button>
                <button class="fu-nav-btn fu-nav-arrow" id="btnPrev">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <button class="fu-nav-btn fu-nav-arrow" id="btnNext">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
                <button class="fu-date-range-btn" id="btnDateRange">
                    <span id="calDateLabel">18 – 24 May 2025</span>
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
            </div>
            <div class="fu-view-switcher">
                <button class="fu-view-btn" data-view="day">Day</button>
                <button class="fu-view-btn fu-view-active" data-view="week">Week</button>
                <button class="fu-view-btn" data-view="month">Month</button>
                <button class="fu-view-btn" data-view="agenda">Agenda</button>
            </div>
        </div>

        {{-- MAIN CALENDAR + SIDEBAR --}}
        <div class="fu-cal-body">

            {{-- WEEK CALENDAR --}}
            <div class="fu-calendar-wrap" id="calendarWrap">
                @include('communication.followup.partials.calendar-week', ['events' => $dummy])
            </div>

            {{-- RIGHT SIDEBAR --}}
            <div class="fu-sidebar">

                {{-- MINI CALENDAR --}}
                <div class="fu-mini-cal">
                    <div class="fu-mini-cal-header">
                        <button class="fu-mini-nav" id="miniPrev">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>
                        <span class="fu-mini-month" id="miniMonthLabel">May 2025</span>
                        <button class="fu-mini-nav" id="miniNext">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                    </div>
                    <div class="fu-mini-grid">
                        <div class="fu-mini-dow">Su</div><div class="fu-mini-dow">Mo</div>
                        <div class="fu-mini-dow">Tu</div><div class="fu-mini-dow">We</div>
                        <div class="fu-mini-dow">Th</div><div class="fu-mini-dow">Fr</div>
                        <div class="fu-mini-dow">Sa</div>
                        {{-- Days filled by JS --}}
                        <div id="miniCalDays" class="fu-mini-days-grid"></div>
                    </div>
                </div>

                {{-- TODAY'S FOLLOW-UPS --}}
                <div class="fu-sidebar-section">
                    <div class="fu-sidebar-section-header">
                        <span>Today's Follow-ups (19 May)</span>
                        <span class="fu-sidebar-badge">{{ count($todayList) }}</span>
                    </div>
                    <div class="fu-today-list">
                        @foreach($todayList as $item)
                        <div class="fu-today-item" data-id="{{ $item['id'] }}">
                            <div class="fu-today-avatar" style="background: {{ $item['color'] }}18; color: {{ $item['color'] }}">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    @if($item['channel'] === 'call')
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.63 3.38 2 2 0 0 1 3.6 1.21h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.81a16 16 0 0 0 6.29 6.29l1.87-1.87a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
                                    @elseif($item['channel'] === 'whatsapp')
                                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                                    @else
                                    <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                                    @endif
                                </svg>
                            </div>
                            <div class="fu-today-info">
                                <span class="fu-today-name">{{ $item['name'] }}</span>
                                <span class="fu-today-time">{{ $item['time'] }} · {{ ucfirst($item['channel']) }}</span>
                            </div>
                            <span class="fu-today-due {{ \Carbon\Carbon::now()->diffInMinutes(\Carbon\Carbon::today()) > 0 ? '' : 'fu-due-overdue' }}">
                                Due in {{ $item['due_in'] }}
                            </span>
                        </div>
                        @endforeach
                        <button class="fu-view-all-btn" onclick="window.location.href='{{ route('communication.followup.queue', ['filter' => 'today']) }}'">
                            View All Today's (34)
                        </button>
                    </div>
                </div>

                {{-- QUICK ACTIONS --}}
                <div class="fu-sidebar-section">
                    <div class="fu-sidebar-section-header">
                        <span>Quick Actions</span>
                    </div>
                    <div class="fu-quick-actions">
                        <button class="fu-quick-action" onclick="openScheduleModal()">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            Schedule Follow-up
                        </button>
                        <button class="fu-quick-action" onclick="openNoteModal()">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            Add Note
                        </button>
                        <button class="fu-quick-action" onclick="openWhatsApp()">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                            Send WhatsApp
                        </button>
                        <button class="fu-quick-action" onclick="makeCall()">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.63 3.38 2 2 0 0 1 3.6 1.21h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.81a16 16 0 0 0 6.29 6.29l1.87-1.87a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            Make a Call
                        </button>
                    </div>
                </div>

            </div>{{-- end sidebar --}}

        </div>{{-- end cal-body --}}

        {{-- OVERDUE STRIP --}}
        <div class="fu-overdue-strip">
            <div class="fu-overdue-strip-header">
                <span class="fu-overdue-strip-title">
                    Overdue Follow-ups
                    <span class="fu-overdue-count">{{ count($overdue) }}</span>
                </span>
                <a href="{{ route('communication.followup.overdue') }}" class="fu-view-all-link">View All</a>
            </div>
            <div class="fu-overdue-cards">
                @foreach($overdue as $item)
                <div class="fu-overdue-card">
                    <div class="fu-overdue-avatar" style="background: #EF444418; color: #EF4444">
                        {{ $item['avatar'] }}
                    </div>
                    <div class="fu-overdue-info">
                        <span class="fu-overdue-name">{{ $item['name'] }}</span>
                        <span class="fu-overdue-date">{{ $item['date'] }}</span>
                        <span class="fu-overdue-days">Overdue by {{ $item['overdue_by'] }}</span>
                    </div>
                    <button class="fu-overdue-call" onclick="openCompleteModal({{ $item['id'] }})">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.63 3.38 2 2 0 0 1 3.6 1.21h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.81a16 16 0 0 0 6.29 6.29l1.87-1.87a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    </button>
                </div>
                @endforeach
            </div>
        </div>

    </div>{{-- end calendar-section --}}

</div>{{-- end fu-page --}}

{{-- LEGEND --}}
<div class="fu-legend">
    <span class="fu-legend-dot" style="background:#6B5BDF"></span> Call
    <span class="fu-legend-dot" style="background:#22C55E"></span> WhatsApp
    <span class="fu-legend-dot" style="background:#F97316"></span> Clinic Visit / Appointment
    <span class="fu-legend-dot" style="background:#EF4444"></span> Overdue
</div>

{{-- ALL MODALS --}}
@include('components.followup.complete-followup-modal')
@include('components.followup.reschedule-modal')
@include('components.followup.add-note-modal')
@include('components.followup.change-status-modal')
@include('components.followup.schedule-followup-modal')
@include('components.followup.convert-to-patient-modal')
@include('components.followup.filter-sort-modal')
@include('components.followup.create-case-modal')

@endsection

@push('communication-styles')
    @vite('resources/css/communication/followup.css')
@endpush

@push('communication-scripts')
    @vite('resources/js/communication/followup-calendar.js')
    @vite('resources/js/communication/followup-modals.js')
@endpush