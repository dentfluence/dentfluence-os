{{--
    Communication OS — Module Dashboard
    Dentfluence · Tulip Dental · Session 1 (Redesigned)

    CONFIRMED ROUTES (as sessions complete):
    ✅ Session 1: communication.index
    ✅ Session 2: communication.manager.index, communication.manager.overdue, communication.manager.log.form
    ⏳ Session 3: communication.prm.index
    ⏳ Session 4: communication.followup.index
    ⏳ Session 6: communication.tasks.index
    ⏳ Session 7: communication.opportunities.index
    ⏳ Session 8: communication.activity (activity log)

    commRoute() safely falls back to '#' for unregistered routes.
    Replace with route() calls as each session is completed.
--}}
@extends('layouts.app')

@section('title', 'Communication OS — Dashboard')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/communication/module.css') }}">
@endpush

@php
    /**
     * Safe route helper — returns '#' instead of throwing RouteNotFoundException.
     * Remove once all routes are registered.
     */
    if (!function_exists('commRoute')) {
        function commRoute(string $name, array $params = []): string {
            try { return route($name, $params); } catch (\Exception $e) { return '#'; }
        }
    }

    // Dummy activity feed — replace with $recentActivity from DashboardController
    $activityFeed = $recentActivity ?? [
        ['type' => 'lead',      'color' => 'blue',  'text' => 'New lead added — Ravi Sharma',    'by' => 'Neha',       'time' => '10:15 AM', 'badge' => 'Lead'],
        ['type' => 'followup',  'color' => 'green', 'text' => 'Follow-up done — Priya Mehta',    'by' => 'Samiksha',   'time' => '09:45 AM', 'badge' => 'Follow-up Done'],
        ['type' => 'converted', 'color' => 'teal',  'text' => 'Lead converted — Ankit Verma',    'by' => 'Dr. Rohit',  'time' => '09:30 AM', 'badge' => 'Converted'],
        ['type' => 'call',      'color' => 'amber', 'text' => 'Call logged — 9812345678',         'by' => 'Front Desk', 'time' => '09:10 AM', 'badge' => 'Call Logged'],
        ['type' => 'lead',      'color' => 'blue',  'text' => 'New lead added — Neha Gupta',     'by' => 'Front Desk', 'time' => '08:50 AM', 'badge' => 'Lead'],
    ];
@endphp

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────────────────── --}}
<div class="co-page-header">
    <div>
        <h1 class="co-page-header__title">Communication OS</h1>
        <p class="co-page-header__sub">Operational Command Center · Tulip Dental</p>
    </div>
    <span class="co-page-header__date">{{ now()->format('D, d M Y') }}</span>
</div>

{{-- ── Time Tabs ────────────────────────────────────────────────────────────── --}}
<div class="co-tabs-wrap">
    <div class="co-tabs">
        <button class="co-tab co-tab--active" data-period="today">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Today
        </button>
        <button class="co-tab" data-period="month">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            This Month
        </button>
        <button class="co-tab" data-period="quarter">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            Quarter
        </button>
        <button class="co-tab" data-period="custom">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Custom
        </button>
    </div>
</div>

{{-- ── Stat Cards ───────────────────────────────────────────────────────────── --}}
<div class="co-stats">

    <div class="co-stat co-stat--purple">
        <div class="co-stat__icon co-stat__icon--purple">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="co-stat__body">
            <span class="co-stat__label">New Leads</span>
            <span class="co-stat__value">{{ $stats['new_leads'] ?? 28 }}</span>
        </div>
        <a href="{{ route('prm.index') }}" class="co-stat__cta co-stat__cta--purple">View all leads &rarr;</a>
    </div>

    <div class="co-stat co-stat--amber">
        <div class="co-stat__icon co-stat__icon--amber">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.63 3.42 2 2 0 0 1 3.6 1.25h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 9a16 16 0 0 0 6 6l1.1-1.1a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 21.72 16a2 2 0 0 1 .2.92z"/></svg>
        </div>
        <div class="co-stat__body">
            <span class="co-stat__label">Follow-ups Due</span>
            <span class="co-stat__value">{{ $stats['followups_due'] ?? 52 }}</span>
        </div>
        <a href="{{ commRoute('communication.followup.index') }}" class="co-stat__cta co-stat__cta--amber">Action needed &rarr;</a>
    </div>

    <div class="co-stat co-stat--green">
        <div class="co-stat__icon co-stat__icon--green">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="co-stat__body">
            <span class="co-stat__label">Converted</span>
            <span class="co-stat__value">{{ $stats['converted'] ?? 16 }}</span>
        </div>
        <a href="{{ route('prm.index') }}" class="co-stat__cta co-stat__cta--green">This period &rarr;</a>
    </div>

    <div class="co-stat co-stat--red">
        <div class="co-stat__icon co-stat__icon--red">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div class="co-stat__body">
            <span class="co-stat__label">Overdue</span>
            <span class="co-stat__value">{{ $stats['overdue'] ?? 18 }}</span>
        </div>
        {{-- ✅ Session 2 route confirmed --}}
        <a href="{{ route('communication.manager.overdue') }}" class="co-stat__cta co-stat__cta--red">Needs attention &rarr;</a>
    </div>

</div>

{{-- ── Quick Actions ──────────────────────────────────────────────────────────── --}}
<div class="co-box">
<div class="co-section-hd">
    <h2 class="co-section-title">Quick Actions</h2>
</div>
<div class="co-quick-actions">

    <a href="{{ route('communication.manager.log.form') }}" class="co-qa">
        <div class="co-qa__icon co-qa__icon--purple">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
        </div>
        <span class="co-qa__label">Add Lead</span>
        <span class="co-qa__arrow">&rarr;</span>
    </a>

    {{-- ✅ Session 2 route confirmed --}}
    <a href="{{ route('communication.manager.index') }}" class="co-qa">
        <div class="co-qa__icon co-qa__icon--teal">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.63 3.42A2 2 0 0 1 3.6 1.25h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9a16 16 0 0 0 6 6l1.1-1.1a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21.72 16a2 2 0 0 1 .28.92z"/></svg>
        </div>
        <span class="co-qa__label">Call Manager</span>
        <span class="co-qa__arrow">&rarr;</span>
    </a>

    {{-- ⏳ Session 3 --}}
    <a href="{{ route('prm.index') }}" class="co-qa">
        <div class="co-qa__icon co-qa__icon--green">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
        </div>
        <span class="co-qa__label">PRM Pipeline</span>
        <span class="co-qa__arrow">&rarr;</span>
    </a>

    {{-- ✅ Session 2 route confirmed --}}
    <a href="{{ route('communication.manager.index') }}" class="co-qa">
        <div class="co-qa__icon co-qa__icon--amber">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <span class="co-qa__label">Communication List</span>
        <span class="co-qa__arrow">&rarr;</span>
    </a>

    {{-- ⏳ Session 7 --}}
    <a href="{{ commRoute('communication.opportunities.index') }}" class="co-qa">
        <div class="co-qa__icon co-qa__icon--coral">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        </div>
        <span class="co-qa__label">Opportunity</span>
        <span class="co-qa__arrow">&rarr;</span>
    </a>

    {{-- ⏳ Session 6 --}}
    <a href="{{ commRoute('communication.tasks.index') }}" class="co-qa">
        <div class="co-qa__icon co-qa__icon--indigo">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <span class="co-qa__label">Tasks</span>
        <span class="co-qa__arrow">&rarr;</span>
    </a>

    {{-- ⏳ Session 4 --}}
    <a href="{{ commRoute('communication.followup.index') }}" class="co-qa">
        <div class="co-qa__icon co-qa__icon--rose">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.3"/></svg>
        </div>
        <span class="co-qa__label">Follow-ups</span>
        <span class="co-qa__arrow">&rarr;</span>
    </a>

    {{-- ✅ Session 2 route confirmed --}}
    <a href="{{ route('communication.manager.log.form') }}" class="co-qa">
        <div class="co-qa__icon co-qa__icon--slate">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        </div>
        <span class="co-qa__label">Activity Log</span>
        <span class="co-qa__arrow">&rarr;</span>
    </a>

</div>
</div>{{-- /co-box quick actions --}}

{{-- ── Lower 2-col ──────────────────────────────────────────────────────────── --}}
<div class="co-lower">

    {{-- Left: Communication Overview --}}
    <div class="co-box" style="margin-bottom:0">
        <div class="co-section-hd">
            <h2 class="co-section-title">Communication Overview</h2>
            {{-- ✅ Session 2 --}}
            <a href="{{ route('communication.manager.index') }}" class="co-view-all">View All &rarr;</a>
        </div>

        <div class="co-overview-grid">

            <div class="co-ov co-ov--red">
                <div class="co-ov__icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <span class="co-ov__label">Overdue</span>
                <span class="co-ov__value">{{ $stats['overdue'] ?? 18 }}</span>
                <span class="co-ov__desc">Missed follow-ups</span>
                {{-- ✅ Session 2 --}}
                <a href="{{ route('communication.manager.overdue') }}" class="co-ov__cta">View Overdue &rarr;</a>
            </div>

            <div class="co-ov co-ov--amber">
                <div class="co-ov__icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </div>
                <span class="co-ov__label">Today</span>
                <span class="co-ov__value">{{ $stats['today_count'] ?? 34 }}</span>
                <span class="co-ov__desc">To contact today</span>
                {{-- ✅ Session 2 --}}
                <a href="{{ route('communication.manager.index') }}" class="co-ov__cta">View Today's List &rarr;</a>
            </div>

            <div class="co-ov co-ov--green">
                <div class="co-ov__icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <span class="co-ov__label">Active Leads</span>
                <span class="co-ov__value">{{ $stats['active_leads'] ?? 23 }}</span>
                <span class="co-ov__desc">In pipeline</span>
                {{-- ⏳ Session 3 --}}
                <a href="{{ route('prm.index') }}" class="co-ov__cta">Open Pipeline &rarr;</a>
            </div>

            <div class="co-ov co-ov--blue">
                <div class="co-ov__icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                </div>
                <span class="co-ov__label">WhatsApp Pending</span>
                <span class="co-ov__value">{{ $stats['whatsapp_pending'] ?? 11 }}</span>
                <span class="co-ov__desc">Awaiting reply</span>
                {{-- ✅ Session 2 --}}
                <a href="{{ route('communication.manager.index') }}" class="co-ov__cta">Open List &rarr;</a>
            </div>

        </div>
    </div>

    {{-- Right: Recent Activity --}}
    <div class="co-box" style="margin-bottom:0">
        <div class="co-section-hd">
            <h2 class="co-section-title">Recent Activity</h2>
            <a href="{{ route('communication.manager.index') }}" class="co-view-all">View All &rarr;</a>
        </div>

        <div class="co-activity">
            @php
            $iconPaths = [
                'lead'      => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
                'followup'  => '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
                'converted' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
                'call'      => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.63 3.42A2 2 0 0 1 3.6 1.25h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9a16 16 0 0 0 6 6l1.1-1.1a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21.72 16a2 2 0 0 1 .28.92z"/>',
            ];
            @endphp
            @foreach($activityFeed as $item)
            <div class="co-activity__row">
                <div class="co-activity__icon co-activity__icon--{{ $item['color'] }}">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        {!! $iconPaths[$item['type']] ?? '<circle cx="12" cy="12" r="4"/>' !!}
                    </svg>
                </div>
                <div class="co-activity__content">
                    <p class="co-activity__text">{{ $item['text'] }}</p>
                    <p class="co-activity__meta">By {{ $item['by'] }} &bull; {{ $item['time'] }}</p>
                </div>
                <span class="co-activity__badge co-activity__badge--{{ $item['color'] }}">{{ $item['badge'] }}</span>
            </div>
            @endforeach
        </div>
    </div>

</div>

{{-- ── Bottom Metrics ───────────────────────────────────────────────────────── --}}
<div class="co-box">
<div class="co-section-hd">
    <h2 class="co-section-title">Performance Summary</h2>
</div>
<div class="co-metrics">

    <div class="co-metric">
        <div class="co-metric__icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="co-metric__body">
            <span class="co-metric__label">Total Leads</span>
            <span class="co-metric__value">{{ $metrics['total_leads'] ?? 248 }}</span>
            <span class="co-metric__delta co-metric__delta--up">&#9650; 12% vs last month</span>
        </div>
    </div>

    <div class="co-metric">
        <div class="co-metric__icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.63 3.42A2 2 0 0 1 3.6 1.25h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9a16 16 0 0 0 6 6l1.1-1.1a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21.72 16a2 2 0 0 1 .28.92z"/></svg>
        </div>
        <div class="co-metric__body">
            <span class="co-metric__label">Total Calls</span>
            <span class="co-metric__value">{{ $metrics['total_calls'] ?? 156 }}</span>
            <span class="co-metric__delta co-metric__delta--up">&#9650; 8% vs last month</span>
        </div>
    </div>

    <div class="co-metric">
        <div class="co-metric__icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.3"/></svg>
        </div>
        <div class="co-metric__body">
            <span class="co-metric__label">Follow-ups Completed</span>
            <span class="co-metric__value">{{ $metrics['followups_completed'] ?? 102 }}</span>
            <span class="co-metric__delta co-metric__delta--up">&#9650; 15% vs last month</span>
        </div>
    </div>

    <div class="co-metric">
        <div class="co-metric__icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="co-metric__body">
            <span class="co-metric__label">Conversion Rate</span>
            <span class="co-metric__value">{{ $metrics['conversion_rate'] ?? '18.5%' }}</span>
            <span class="co-metric__delta co-metric__delta--up">&#9650; 5% vs last month</span>
        </div>
    </div>

</div>
</div>{{-- /co-box metrics --}}

@endsection

@push('scripts')
<script>
// Tab switching (UI only — real data filter wired in Session 11)
document.querySelectorAll('.co-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.co-tab').forEach(t => t.classList.remove('co-tab--active'));
        tab.classList.add('co-tab--active');
    });
});
</script>
@endpush

@push('styles')
<style>
/* ── Communication OS Dashboard ──────────────────────────────────────── */
.co-page-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px}
.co-page-header__title{font-size:18px;font-weight:600;color:var(--color-text-primary);line-height:1.2}
.co-page-header__sub{font-size:11px;color:var(--color-text-secondary);margin-top:2px}
.co-page-header__date{font-size:12px;color:var(--color-text-tertiary);padding-top:4px}

/* Tabs */
.co-tabs-wrap{margin-bottom:10px}
.co-tabs{display:inline-flex;gap:4px;background:var(--color-background-secondary);border:1px solid var(--color-border-tertiary);border-radius:10px;padding:4px}
.co-tab{display:inline-flex;align-items:center;gap:5px;padding:5px 13px;border-radius:6px;border:none;background:transparent;color:var(--color-text-secondary);font-size:12px;font-weight:500;cursor:pointer;transition:background .15s,color .15s;font-family:inherit;white-space:nowrap}
.co-tab:hover{background:var(--color-background-tertiary);color:var(--color-text-primary)}
.co-tab--active{background:#5B40C2;color:#fff;box-shadow:0 1px 4px rgba(91,64,194,.25)}
.co-tab--active svg{stroke:#fff}

/* Stat cards */
.co-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px}
.co-stat{background:var(--color-background-primary);border:1px solid var(--color-border-tertiary);border-radius:10px;padding:10px 14px 10px;display:flex;flex-direction:row;align-items:center;gap:10px;position:relative;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.co-stat::after{content:"";position:absolute;bottom:0;left:0;right:0;height:2px;border-radius:0 0 10px 10px}
.co-stat--purple::after{background:#5B40C2}
.co-stat--amber::after{background:#D97706}
.co-stat--green::after{background:#059669}
.co-stat--red::after{background:#DC2626}
.co-stat__icon{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.co-stat__icon--purple{background:#EEE9FD;color:#5B40C2}
.co-stat__icon--amber{background:#FEF3C7;color:#D97706}
.co-stat__icon--green{background:#D1FAE5;color:#059669}
.co-stat__icon--red{background:#FEE2E2;color:#DC2626}
.co-stat__body{display:flex;flex-direction:column;gap:1px;flex:1}
.co-stat__label{font-size:11px;color:var(--color-text-secondary);font-weight:500}
.co-stat__value{font-size:22px;font-weight:700;color:var(--color-text-primary);line-height:1.2}
.co-stat__cta{font-size:11px;font-weight:500;text-decoration:none;white-space:nowrap}
.co-stat__cta--purple{color:#5B40C2}
.co-stat__cta--amber{color:#D97706}
.co-stat__cta--green{color:#059669}
.co-stat__cta--red{color:#DC2626}
.co-stat__cta:hover{text-decoration:underline}

/* Section headings */
.co-section-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.co-section-title{font-size:13px;font-weight:600;color:var(--color-text-primary)}
.co-view-all{font-size:12px;font-weight:500;color:var(--color-text-secondary);text-decoration:none}
.co-view-all:hover{color:var(--color-text-primary);text-decoration:underline}

/* Quick actions */
.co-quick-actions{display:grid;grid-template-columns:repeat(8,1fr);gap:8px;margin-bottom:14px}
.co-qa{background:var(--color-background-primary);border:1px solid var(--color-border-tertiary);border-radius:8px;padding:9px 10px 8px;display:flex;flex-direction:column;align-items:flex-start;gap:5px;text-decoration:none;transition:box-shadow .15s,border-color .15s,transform .1s;box-shadow:0 2px 6px rgba(0,0,0,.05)}
.co-qa:hover{border-color:var(--color-border-secondary);box-shadow:0 2px 10px rgba(0,0,0,.06);transform:translateY(-1px)}
.co-qa__icon{width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center}
.co-qa__icon--purple{background:#EEE9FD;color:#5B40C2}
.co-qa__icon--teal{background:#CCFBF1;color:#0F766E}
.co-qa__icon--green{background:#D1FAE5;color:#059669}
.co-qa__icon--amber{background:#FEF3C7;color:#D97706}
.co-qa__icon--coral{background:#FFE4E6;color:#E11D48}
.co-qa__icon--indigo{background:#E0E7FF;color:#4338CA}
.co-qa__icon--rose{background:#FDF2F8;color:#9D174D}
.co-qa__icon--slate{background:#F1F5F9;color:#475569}
.co-qa__label{font-size:11px;font-weight:500;color:var(--color-text-primary);line-height:1.2}
.co-qa__arrow{font-size:11px;color:var(--color-text-tertiary)}

/* Box container — wraps quick actions, metrics */
.co-box{background:var(--color-background-primary);border:1px solid var(--color-border-tertiary);border-radius:10px;padding:12px 14px;margin-bottom:12px;box-shadow:0 2px 8px rgba(0,0,0,.05)}
.co-box .co-quick-actions{margin-bottom:0}
.co-box .co-metrics{margin-bottom:0}

/* Lower 2-col */
.co-lower{display:grid;grid-template-columns:1fr 340px;gap:14px;margin-bottom:12px}

/* Overview cards */
.co-overview-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:0}
.co-ov{border-radius:8px;padding:10px 12px;display:flex;flex-direction:column;gap:2px;box-shadow:0 2px 6px rgba(0,0,0,.05)}
.co-ov--red{background:#FFF5F5;border:1px solid #FECACA}
.co-ov--amber{background:#FFFBEB;border:1px solid #FDE68A}
.co-ov--green{background:#F0FDF4;border:1px solid #BBF7D0}
.co-ov--blue{background:#EEF2FF;border:1px solid #C7D2FE}
.co-ov__icon{margin-bottom:1px}
.co-ov--red   .co-ov__icon{color:#DC2626}
.co-ov--amber .co-ov__icon{color:#D97706}
.co-ov--green .co-ov__icon{color:#059669}
.co-ov--blue  .co-ov__icon{color:#4338CA}
.co-ov__label{font-size:9px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-secondary)}
.co-ov__value{font-size:24px;font-weight:700;line-height:1.2;color:var(--color-text-primary)}
.co-ov--red   .co-ov__value{color:#DC2626}
.co-ov--amber .co-ov__value{color:#D97706}
.co-ov__desc{font-size:11px;color:var(--color-text-secondary)}
.co-ov__cta{font-size:11px;font-weight:600;text-decoration:none;margin-top:3px}
.co-ov--red   .co-ov__cta{color:#DC2626}
.co-ov--amber .co-ov__cta{color:#D97706}
.co-ov--green .co-ov__cta{color:#059669}
.co-ov--blue  .co-ov__cta{color:#4338CA}
.co-ov__cta:hover{text-decoration:underline}
@media(prefers-color-scheme:dark){
    .co-ov--red  {background:rgba(220,38,38,.08);border-color:rgba(220,38,38,.2)}
    .co-ov--amber{background:rgba(217,119,6,.08);border-color:rgba(217,119,6,.2)}
    .co-ov--green{background:rgba(5,150,105,.08);border-color:rgba(5,150,105,.2)}
    .co-ov--blue {background:rgba(67,56,202,.08);border-color:rgba(67,56,202,.2)}
}

/* Activity feed */
.co-activity{background:transparent;border:none;border-radius:0;overflow:hidden}
.co-activity__row{display:flex;align-items:center;gap:9px;padding:8px 12px;border-bottom:1px solid var(--color-border-tertiary);transition:background .1s}
.co-activity__row:last-child{border-bottom:none}
.co-activity__row:hover{background:var(--color-background-secondary)}
.co-activity__icon{width:24px;height:24px;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.co-activity__icon--blue  {background:#EEE9FD;color:#5B40C2}
.co-activity__icon--green {background:#D1FAE5;color:#059669}
.co-activity__icon--teal  {background:#CCFBF1;color:#0F766E}
.co-activity__icon--amber {background:#FEF3C7;color:#D97706}
.co-activity__icon--red   {background:#FEE2E2;color:#DC2626}
.co-activity__content{flex:1;min-width:0}
.co-activity__text{font-size:12px;font-weight:500;color:var(--color-text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.co-activity__meta{font-size:10px;color:var(--color-text-tertiary);margin-top:1px}
.co-activity__badge{font-size:10px;font-weight:600;padding:3px 8px;border-radius:20px;white-space:nowrap;flex-shrink:0}
.co-activity__badge--blue  {background:#EEE9FD;color:#5B40C2}
.co-activity__badge--green {background:#D1FAE5;color:#059669}
.co-activity__badge--teal  {background:#CCFBF1;color:#0F766E}
.co-activity__badge--amber {background:#FEF3C7;color:#D97706}
.co-activity__badge--red   {background:#FEE2E2;color:#DC2626}

/* Bottom metrics */
.co-metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.co-metric{background:var(--color-background-primary);border:1px solid var(--color-border-tertiary);border-radius:8px;padding:10px 14px;display:flex;align-items:center;gap:11px;box-shadow:0 2px 6px rgba(0,0,0,.05)}
.co-metric__icon{width:32px;height:32px;border-radius:7px;background:var(--color-background-secondary);color:var(--color-text-secondary);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.co-metric__body{display:flex;flex-direction:column;gap:2px}
.co-metric__label{font-size:10px;color:var(--color-text-secondary);font-weight:500}
.co-metric__value{font-size:16px;font-weight:700;color:var(--color-text-primary)}
.co-metric__delta{font-size:10px;font-weight:500}
.co-metric__delta--up{color:#059669}
.co-metric__delta--down{color:#DC2626}

/* Responsive */
@media(max-width:1280px){
    .co-quick-actions{grid-template-columns:repeat(4,1fr)}
    .co-stats{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:1024px){
    .co-lower{grid-template-columns:1fr}
    .co-metrics{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:640px){
    .co-stats{grid-template-columns:1fr 1fr}
    .co-quick-actions{grid-template-columns:repeat(3,1fr)}
    .co-metrics{grid-template-columns:1fr 1fr}
}
</style>
@endpush