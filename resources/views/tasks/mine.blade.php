@extends('layouts.app')
@section('page-title', 'My Tasks')

@section('content')
<div style="font-family:'Inter',sans-serif;padding:28px;">

    {{-- Header --}}
    <div style="margin-bottom:24px;">
        <h1 style="font-family:'Cormorant Garamond',serif;font-size:26px;font-weight:700;color:#1a0320;margin:0 0 2px;">My Tasks</h1>
        <p style="font-size:12.5px;color:#9a7aaa;margin:0;">{{ today()->format('l, d M Y') }} · Tasks assigned to you</p>
    </div>

    {{-- Counter chips --}}
    <div style="display:flex;gap:10px;margin-bottom:24px;flex-wrap:wrap;">
        <div style="padding:7px 16px;background:#fdeaea;border-radius:20px;font-size:12px;font-weight:600;color:#b52020;">
            {{ $overdue->count() }} Overdue
        </div>
        <div style="padding:7px 16px;background:#fff4e0;border-radius:20px;font-size:12px;font-weight:600;color:#a05c00;">
            {{ $today->count() }} Due Today
        </div>
        <div style="padding:7px 16px;background:#e6f0fb;border-radius:20px;font-size:12px;font-weight:600;color:#1a5ea8;">
            {{ $upcoming->count() }} Upcoming
        </div>
        <div style="padding:7px 16px;background:#e8f7ef;border-radius:20px;font-size:12px;font-weight:600;color:#1a7a45;">
            {{ $done->count() }} Done
        </div>
    </div>

    @if($overdue->isEmpty() && $today->isEmpty() && $upcoming->isEmpty() && $done->isEmpty())
        <div style="text-align:center;padding:60px 20px;color:#c5b0d5;">
            <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 12px;display:block;opacity:.5;">
                <polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
            <p style="font-size:14px;margin:0;">No tasks assigned to you.</p>
        </div>
    @endif

    {{-- Overdue --}}
    @if($overdue->count())
    <div style="margin-bottom:24px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
            <div style="width:8px;height:8px;border-radius:50%;background:#b52020;"></div>
            <span style="font-size:11.5px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#b52020;">Overdue · {{ $overdue->count() }}</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;">
            @foreach($overdue as $task)
                @include('tasks._card', ['task' => $task, 'badge' => 'backlog', 'showActions' => true])
            @endforeach
        </div>
    </div>
    @endif

    {{-- Due Today --}}
    @if($today->count())
    <div style="margin-bottom:24px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
            <div style="width:8px;height:8px;border-radius:50%;background:#a05c00;"></div>
            <span style="font-size:11.5px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#a05c00;">Due Today · {{ $today->count() }}</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;">
            @foreach($today as $task)
                @include('tasks._card', ['task' => $task, 'badge' => 'today', 'showActions' => true])
            @endforeach
        </div>
    </div>
    @endif

    {{-- Upcoming --}}
    @if($upcoming->count())
    <div style="margin-bottom:24px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
            <div style="width:8px;height:8px;border-radius:50%;background:#1a5ea8;"></div>
            <span style="font-size:11.5px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#1a5ea8;">Upcoming · {{ $upcoming->count() }}</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;">
            @foreach($upcoming as $task)
                @include('tasks._card', ['task' => $task, 'badge' => 'upcoming', 'showActions' => true])
            @endforeach
        </div>
    </div>
    @endif

    {{-- Done --}}
    @if($done->count())
    <div style="margin-bottom:24px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
            <div style="width:8px;height:8px;border-radius:50%;background:#1a7a45;"></div>
            <span style="font-size:11.5px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#1a7a45;">Completed · {{ $done->count() }}</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;">
            @foreach($done as $task)
                @include('tasks._card', ['task' => $task, 'badge' => 'done', 'showActions' => false])
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection
