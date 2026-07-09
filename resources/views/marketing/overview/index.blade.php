{{--
|==========================================================================
| Marketing — Dashboard
| File: resources/views/marketing/overview/index.blade.php
|
| Re-engineered per docs/marketing-module-reengineering-plan.md (Slice 2).
| This page answers one question: "what should I do today?" — not a long
| scroll of KPI widgets. Old partials under overview/partials/ are left in
| place (unreferenced, not deleted) rather than removed.
|==========================================================================
--}}
@extends('marketing.layouts.app')

@php $marketingPageTitle = 'Dashboard'; @endphp

@section('page-title', 'Marketing — Dashboard')

@section('marketing-content')

{{-- ── Page header ── --}}
<div class="df-page-header" style="margin-bottom: 20px;">
    <div>
        <h1 class="df-page-title">Dashboard</h1>
        <p class="df-page-subtitle">{{ now()->format('l, d F Y') }} — here's what needs your attention today.</p>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     TOP STRIP — Score · Streak · Estimated time
═══════════════════════════════════════════════════════════════ --}}
<div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; margin-bottom:20px;">

    {{-- Marketing Score --}}
    <div style="background:#fff; border:1px solid rgba(185,92,183,0.15); border-radius:10px; padding:18px 20px;">
        <p style="font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:#9b6aad; text-transform:uppercase; letter-spacing:.5px; margin:0 0 6px;">Today's Score</p>
        <p style="font-family:'Cormorant Garamond',serif; font-size:34px; font-weight:700; color:#1e0a2c; margin:0; line-height:1;">{{ $score }}<span style="font-size:16px; color:#9ca3af; font-family:'Inter',sans-serif; font-weight:400;">/100</span></p>
    </div>

    {{-- Streak --}}
    <div style="background:#fff; border:1px solid rgba(185,92,183,0.15); border-radius:10px; padding:18px 20px;">
        <p style="font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:#9b6aad; text-transform:uppercase; letter-spacing:.5px; margin:0 0 6px;">Current Streak</p>
        <p style="font-family:'Cormorant Garamond',serif; font-size:34px; font-weight:700; color:#1e0a2c; margin:0; line-height:1;">{{ $streak }}<span style="font-size:16px; color:#9ca3af; font-family:'Inter',sans-serif; font-weight:400;"> day{{ $streak === 1 ? '' : 's' }}</span></p>
    </div>

    {{-- Estimated time --}}
    <div style="background:#fff; border:1px solid rgba(185,92,183,0.15); border-radius:10px; padding:18px 20px;">
        <p style="font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:#9b6aad; text-transform:uppercase; letter-spacing:.5px; margin:0 0 6px;">Estimated Time Today</p>
        <p style="font-family:'Cormorant Garamond',serif; font-size:34px; font-weight:700; color:#1e0a2c; margin:0; line-height:1;">~{{ $estimatedMinutes }}<span style="font-size:16px; color:#9ca3af; font-family:'Inter',sans-serif; font-weight:400;"> min</span></p>
    </div>

</div>

{{-- ══════════════════════════════════════════════════════════════
     TODAY'S TASKS
═══════════════════════════════════════════════════════════════ --}}
<div style="background:#fff; border:1px solid rgba(185,92,183,0.15); border-radius:10px; padding:20px 22px; margin-bottom:20px;">
    <h2 style="font-family:'Inter',sans-serif; font-size:14px; font-weight:600; color:#1e0a2c; margin:0 0 14px;">Today's Tasks</h2>

    @if (count($tasks) === 0)
        <p style="font-family:'Inter',sans-serif; font-size:13px; color:#7a6884; margin:0;">Nothing needs attention right now — you're caught up.</p>
    @else
        <div style="display:flex; flex-direction:column; gap:0;">
            @foreach ($tasks as $i => $task)
            <div style="
                display:flex; align-items:center; justify-content:space-between; gap:12px;
                padding:11px 0;
                {{ !$loop->last ? 'border-bottom:1px solid rgba(185,92,183,0.08);' : '' }}
            ">
                <div style="display:flex; align-items:center; gap:10px; min-width:0;">
                    <span style="
                        width:20px; height:20px; border-radius:50%; flex-shrink:0;
                        border:1.5px solid rgba(185,92,183,0.35);
                        display:flex; align-items:center; justify-content:center;
                        font-family:'Inter',sans-serif; font-size:10px; font-weight:600; color:#9b6aad;
                    ">{{ $i + 1 }}</span>
                    <span style="font-family:'Inter',sans-serif; font-size:13px; color:#1e0a2c;">{{ $task['label'] }}</span>
                </div>
                <div style="display:flex; align-items:center; gap:12px; flex-shrink:0;">
                    @if (!empty($task['action_url']) && ($task['action_method'] ?? 'GET') === 'POST')
                        <form method="POST" action="{{ $task['action_url'] }}" style="margin:0;">
                            @csrf
                            <button type="submit" style="
                                background:#faf5ff; border:1px solid rgba(185,92,183,0.35); border-radius:6px;
                                padding:4px 10px; font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:#6a0f70; cursor:pointer;
                            ">{{ $task['action_label'] ?? 'Open' }}</button>
                        </form>
                    @elseif (!empty($task['action_url']))
                        <a href="{{ $task['action_url'] }}" style="
                            display:inline-block; background:#faf5ff; border:1px solid rgba(185,92,183,0.35); border-radius:6px;
                            padding:4px 10px; font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:#6a0f70; text-decoration:none;
                        ">{{ $task['action_label'] ?? 'Open' }}</a>
                    @endif
                    <span style="font-family:'Inter',sans-serif; font-size:11px; color:#9ca3af;">~{{ $task['minutes'] }} min</span>
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>

{{-- ══════════════════════════════════════════════════════════════
     UPCOMING POSTS · REVIEWS & MISSED ACTIVITIES
═══════════════════════════════════════════════════════════════ --}}
<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">

    {{-- Upcoming Posts --}}
    <div style="background:#fff; border:1px solid rgba(185,92,183,0.15); border-radius:10px; padding:20px 22px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
            <h2 style="font-family:'Inter',sans-serif; font-size:14px; font-weight:600; color:#1e0a2c; margin:0;">Upcoming Posts</h2>
            <a href="{{ route('marketing.calendar') }}" style="font-family:'Inter',sans-serif; font-size:12px; color:#6a0f70; text-decoration:none;">View calendar →</a>
        </div>

        @if ($upcomingPosts->isEmpty())
            <p style="font-family:'Inter',sans-serif; font-size:13px; color:#7a6884; margin:0;">Nothing scheduled yet.</p>
        @else
            <div style="display:flex; flex-direction:column; gap:0;">
                @foreach ($upcomingPosts as $i => $post)
                <div style="
                    display:flex; align-items:center; justify-content:space-between; gap:12px;
                    padding:10px 0;
                    {{ !$loop->last ? 'border-bottom:1px solid rgba(185,92,183,0.08);' : '' }}
                ">
                    <div style="min-width:0;">
                        <p style="font-family:'Inter',sans-serif; font-size:13px; color:#1e0a2c; margin:0 0 2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $post['title'] }}</p>
                        <p style="font-family:'Inter',sans-serif; font-size:11px; color:#9ca3af; margin:0; text-transform:capitalize;">{{ str_replace('_', ' ', $post['platform']) }}</p>
                    </div>
                    <span style="font-family:'Inter',sans-serif; font-size:11px; color:#7a6884; flex-shrink:0; white-space:nowrap;">{{ $post['when'] }}</span>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Reviews & Missed Activities --}}
    <div style="display:flex; flex-direction:column; gap:20px;">

        <div style="background:#fff; border:1px solid rgba(185,92,183,0.15); border-radius:10px; padding:20px 22px;">
            <div style="display:flex; align-items:center; justify-content:space-between;">
                <div>
                    <p style="font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:#9b6aad; text-transform:uppercase; letter-spacing:.5px; margin:0 0 6px;">Pending Reviews</p>
                    <p style="font-family:'Cormorant Garamond',serif; font-size:28px; font-weight:700; color:#1e0a2c; margin:0;">{{ $pendingReviews }}</p>
                </div>
                <a href="{{ route('marketing.reviews') }}" style="font-family:'Inter',sans-serif; font-size:12px; color:#6a0f70; text-decoration:none;">Reply →</a>
            </div>
        </div>

        <div style="background:#fff; border:1px solid {{ $missedActivities > 0 ? 'rgba(220,38,38,0.25)' : 'rgba(185,92,183,0.15)' }}; border-radius:10px; padding:20px 22px;">
            <p style="font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:{{ $missedActivities > 0 ? '#dc2626' : '#9b6aad' }}; text-transform:uppercase; letter-spacing:.5px; margin:0 0 6px;">Missed Activities</p>
            <p style="font-family:'Cormorant Garamond',serif; font-size:28px; font-weight:700; color:#1e0a2c; margin:0;">{{ $missedActivities }}</p>
            @if ($missedActivities > 0)
            <p style="font-family:'Inter',sans-serif; font-size:11px; color:#7a6884; margin:6px 0 0;">Overdue scheduled posts or failed publishes this week.</p>
            @endif
        </div>

    </div>

</div>

@endsection
