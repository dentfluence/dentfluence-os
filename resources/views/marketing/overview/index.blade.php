{{--
|==========================================================================
| Marketing — Overview
| File: resources/views/marketing/overview/index.blade.php
|
| Phase 2.1-A: Top half of the Overview Dashboard
|   - _stat-row           : Marketing Score gauge + 5 stat cards
|   - _running-campaigns  : Active campaign snapshot table
|   - _upcoming-schedule  : Next posts grouped by Today / Tomorrow
|
| Phase 2.1-B: Bottom half of the Overview Dashboard
|   - _quick-actions      : 6 icon shortcuts (full-width strip)
|   - 3-column grid:
|       col 1 — _platform-status (full height)
|       col 2 — _activity-feed
|       col 3 — _attention-needed
|==========================================================================
--}}
@extends('marketing.layouts.app')

@php $marketingPageTitle = 'Overview'; @endphp

@section('page-title', 'Marketing — Overview')

@section('marketing-content')

{{-- ── Page header ── --}}
<div class="df-page-header" style="margin-bottom: 20px;">
    <div>
        <h1 class="df-page-title">Marketing Overview</h1>
        <p class="df-page-subtitle">Your clinic's marketing performance at a glance.</p>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     ROW 1 — Marketing Score Gauge + 5 Stat Cards
══════════════════════════════════════════════════════════════ --}}
@include('marketing.overview.partials._stat-row', [
    'stats' => $stats,
])

{{-- ══════════════════════════════════════════════════════════════
     ROW 2 — Running Campaigns | Upcoming Schedule
     2-column grid, equal width
══════════════════════════════════════════════════════════════ --}}
<div style="
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 20px;
">

    {{-- Running Campaigns --}}
    @include('marketing.overview.partials._running-campaigns', [
        'runningCampaigns' => $runningCampaigns,
    ])

    {{-- Upcoming Schedule --}}
    @include('marketing.overview.partials._upcoming-schedule', [
        'upcomingSchedule' => $upcomingSchedule,
    ])

</div>

{{-- ══════════════════════════════════════════════════════════════
     ROW 3 — Quick Actions Strip (full width)
══════════════════════════════════════════════════════════════ --}}
@include('marketing.overview.partials._quick-actions')

{{-- ══════════════════════════════════════════════════════════════
     ROW 4 — Platform Status | Activity Feed | Attention Needed
     3-column grid: 1.1fr | 1.4fr | 1fr
══════════════════════════════════════════════════════════════ --}}
<div style="
    display: grid;
    grid-template-columns: 1.1fr 1.4fr 1fr;
    gap: 20px;
    margin-top: 20px;
    align-items: start;
">

    {{-- Platform Status --}}
    @include('marketing.overview.partials._platform-status')

    {{-- Recent Activity --}}
    @include('marketing.overview.partials._activity-feed')

    {{-- Attention Needed --}}
    @include('marketing.overview.partials._attention-needed')

</div>

{{-- Phase B 4.7 — reviews as social proof (ties Reviews 2.4 into Marketing) --}}
@include('marketing.overview.partials._recent-reviews')

@endsection
