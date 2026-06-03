@extends('layouts.communication')

@section('title', 'Escalated Tasks')

@section('content')
<div class="tasks-page">
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-icon" style="background:#FFF5F5; color:#E53E3E">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div>
                <h1 class="page-title">Escalated Tasks</h1>
                <p class="page-subtitle">Tasks requiring immediate supervisor attention</p>
            </div>
        </div>
        <div class="page-header-right">
            <a href="{{ route('communication.tasks.index') }}" class="btn-secondary">← All Tasks</a>
        </div>
    </div>

    {{-- Escalated count banner --}}
    <div style="background:#FFF5F5; border:1px solid #FEB2B2; border-radius:10px; padding:14px 18px; display:flex; align-items:center; gap:10px; margin-bottom:18px; color:#C53030; font-size:13px; font-weight:500;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        3 tasks are escalated and require immediate attention.
    </div>

    {{-- Escalated task cards --}}
    <div class="task-section">
        <div class="section-header">
            <div class="section-title">
                <span class="section-dot overdue-dot"></span>
                Escalated Tasks
                <span class="section-count">3</span>
            </div>
        </div>
        <div class="task-cards">
            @include('communication.tasks.partials.task-card', [
                'task' => [
                    'id' => 3,
                    'title' => 'Escalate estimate follow-up for Amit Kulkarni — No response in 4 days',
                    'lead_name' => 'Amit Kulkarni',
                    'lead_initial' => 'AK',
                    'assigned_to' => 'Anjali Kapoor (Treatment Coordinator)',
                    'due_date' => '15 May 2025, 11:00 AM',
                    'overdue_by' => '4 days',
                    'priority' => 'high',
                    'type' => 'escalation',
                    'status' => 'overdue',
                    'tags' => ['High Value', 'Braces', 'Escalated'],
                    'escalated' => true,
                ]
            ])
            @include('communication.tasks.partials.task-card', [
                'task' => [
                    'id' => 10,
                    'title' => 'Patient Vikram Mehta — Billing dispute needs Dr. attention',
                    'lead_name' => 'Vikram Mehta',
                    'lead_initial' => 'VM',
                    'assigned_to' => 'Dr. Mehta (Dentist)',
                    'due_date' => '18 May 2025, 02:00 PM',
                    'overdue_by' => '1 day',
                    'priority' => 'high',
                    'type' => 'escalation',
                    'status' => 'overdue',
                    'tags' => ['Billing', 'Complaint'],
                    'escalated' => true,
                ]
            ])
        </div>
    </div>
</div>
@endsection
