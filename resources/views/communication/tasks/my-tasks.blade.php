@extends('layouts.communication')

@section('title', 'My Tasks')

@section('content')
<div class="tasks-page">
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <div>
                <h1 class="page-title">My Tasks</h1>
                <p class="page-subtitle">Tasks assigned to you — Neha (Front Desk)</p>
            </div>
        </div>
        <div class="page-header-right">
            <a href="{{ route('communication.tasks.index') }}" class="btn-secondary">← All Tasks</a>
            <button class="btn-primary" onclick="openAddTaskModal()">+ Add Task</button>
        </div>
    </div>

    {{-- My task stats --}}
    <div class="stats-row">
        <div class="stat-card stat-overdue">
            <div class="stat-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
            <div class="stat-info"><span class="stat-number">3</span><span class="stat-label">Overdue</span></div>
        </div>
        <div class="stat-card stat-today">
            <div class="stat-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
            <div class="stat-info"><span class="stat-number">6</span><span class="stat-label">Due Today</span></div>
        </div>
        <div class="stat-card stat-total">
            <div class="stat-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/></svg></div>
            <div class="stat-info"><span class="stat-number">14</span><span class="stat-label">Total Assigned</span></div>
        </div>
        <div class="stat-card stat-completed">
            <div class="stat-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div>
            <div class="stat-info"><span class="stat-number">8</span><span class="stat-label">Completed Today</span></div>
        </div>
    </div>

    <div style="background:#fff; border:1px solid #E8ECF4; border-radius:12px; padding:20px; text-align:center; color:#718096; margin-top:8px;">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#C3B8F8" stroke-width="1.5" style="margin:0 auto 12px; display:block"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        <p style="font-size:14px; font-weight:500; color:#4A5568; margin:0 0 4px">My Tasks view — same structure as All Tasks, filtered to current user</p>
        <p style="font-size:12px; margin:0">This will be wired to real data in Session 11.</p>
    </div>
</div>
@endsection
