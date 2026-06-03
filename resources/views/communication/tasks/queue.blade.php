@extends('layouts.communication')

@section('title', 'Task Queue')

@section('content')
<div class="tasks-page">
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-icon" style="background:#FFF5F5; color:#E53E3E">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div>
                <h1 class="page-title">Task Queue</h1>
                <p class="page-subtitle">All pending tasks across the team</p>
            </div>
        </div>
        <div class="page-header-right">
            <a href="{{ route('communication.tasks.index') }}" class="btn-secondary">← All Tasks</a>
        </div>
    </div>

    <div style="background:#fff; border:1px solid #E8ECF4; border-radius:12px; padding:40px; text-align:center; color:#718096;">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#C3B8F8" stroke-width="1.5" style="margin:0 auto 12px; display:block"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <p style="font-size:14px; font-weight:500; color:#4A5568; margin:0 0 4px">Task Queue — staff execution view</p>
        <p style="font-size:12px; margin:0">Shows tasks grouped by urgency and assignee. Wired in Session 11.</p>
    </div>
</div>
@endsection
