@extends('layouts.communication')

@section('title', 'Tasks & Assignments')

@section('content')
<div class="tasks-page">

    {{-- Page Header --}}
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            </div>
            <div>
                <h1 class="page-title">Tasks & Assignments</h1>
                <p class="page-subtitle">Manage, assign and track all clinic tasks</p>
            </div>
        </div>
        <div class="page-header-right">
            <button class="btn-secondary" onclick="openFilterModal()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                Filters
            </button>
            <button class="btn-primary" onclick="openAddTaskModal()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Task
            </button>
        </div>
    </div>

    {{-- Stats Row --}}
    <div class="stats-row">
        <div class="stat-card stat-total">
            <div class="stat-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg></div>
            <div class="stat-info">
                <span class="stat-number">47</span>
                <span class="stat-label">Total Tasks</span>
            </div>
        </div>
        <div class="stat-card stat-overdue">
            <div class="stat-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
            <div class="stat-info">
                <span class="stat-number">8</span>
                <span class="stat-label">Overdue</span>
            </div>
        </div>
        <div class="stat-card stat-today">
            <div class="stat-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
            <div class="stat-info">
                <span class="stat-number">12</span>
                <span class="stat-label">Due Today</span>
            </div>
        </div>
        <div class="stat-card stat-escalated">
            <div class="stat-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
            <div class="stat-info">
                <span class="stat-number">3</span>
                <span class="stat-label">Escalated</span>
            </div>
        </div>
        <div class="stat-card stat-completed">
            <div class="stat-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div>
            <div class="stat-info">
                <span class="stat-number">24</span>
                <span class="stat-label">Completed Today</span>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="tasks-tabs">
        <button class="tab-btn active" onclick="switchTab('all', this)">All Tasks <span class="tab-count">47</span></button>
        <button class="tab-btn" onclick="switchTab('my', this)">My Tasks <span class="tab-count">14</span></button>
        <button class="tab-btn" onclick="switchTab('overdue', this)">Overdue <span class="tab-count overdue-count">8</span></button>
        <button class="tab-btn" onclick="switchTab('escalated', this)">Escalated <span class="tab-count escalated-count">3</span></button>
        <button class="tab-btn" onclick="switchTab('completed', this)">Completed <span class="tab-count">24</span></button>
    </div>

    {{-- Main Layout: Task List + Right Panel --}}
    <div class="tasks-layout">

        {{-- Task List --}}
        <div class="tasks-list-panel">

            {{-- Overdue Section --}}
            <div class="task-section" id="overdue-section">
                <div class="section-header">
                    <div class="section-title">
                        <span class="section-dot overdue-dot"></span>
                        Overdue
                        <span class="section-count">8</span>
                    </div>
                    <button class="section-toggle" onclick="toggleSection('overdue-tasks')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                </div>
                <div class="task-cards" id="overdue-tasks">
                    @include('communication.tasks.partials.task-card', [
                        'task' => [
                            'id' => 1,
                            'title' => 'Follow up with Riya Sharma about implant consultation',
                            'lead_name' => 'Riya Sharma',
                            'lead_initial' => 'RS',
                            'assigned_to' => 'Neha (Front Desk)',
                            'due_date' => '17 May 2025, 10:00 AM',
                            'overdue_by' => '2 days',
                            'priority' => 'high',
                            'type' => 'call',
                            'status' => 'overdue',
                            'tags' => ['Dental Implant', 'New Lead'],
                        ]
                    ])
                    @include('communication.tasks.partials.task-card', [
                        'task' => [
                            'id' => 2,
                            'title' => 'Send WhatsApp template to Sneha Reddy',
                            'lead_name' => 'Sneha Reddy',
                            'lead_initial' => 'SR',
                            'assigned_to' => 'Anjali Kapoor',
                            'due_date' => '16 May 2025, 03:00 PM',
                            'overdue_by' => '3 days',
                            'priority' => 'medium',
                            'type' => 'whatsapp',
                            'status' => 'overdue',
                            'tags' => ['Recall', 'Existing Patient'],
                        ]
                    ])
                    @include('communication.tasks.partials.task-card', [
                        'task' => [
                            'id' => 3,
                            'title' => 'Escalate estimate follow-up for Amit Kulkarni',
                            'lead_name' => 'Amit Kulkarni',
                            'lead_initial' => 'AK',
                            'assigned_to' => 'Priya Singh',
                            'due_date' => '15 May 2025, 11:00 AM',
                            'overdue_by' => '4 days',
                            'priority' => 'high',
                            'type' => 'escalation',
                            'status' => 'overdue',
                            'tags' => ['High Value', 'Braces'],
                            'escalated' => true,
                        ]
                    ])
                </div>
            </div>

            {{-- Today Section --}}
            <div class="task-section">
                <div class="section-header">
                    <div class="section-title">
                        <span class="section-dot today-dot"></span>
                        Due Today — 19 May 2025
                        <span class="section-count">12</span>
                    </div>
                    <button class="section-toggle" onclick="toggleSection('today-tasks')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                </div>
                <div class="task-cards" id="today-tasks">
                    @include('communication.tasks.partials.task-card', [
                        'task' => [
                            'id' => 4,
                            'title' => 'Call Riya Sharma — Follow-up on treatment plan',
                            'lead_name' => 'Riya Sharma',
                            'lead_initial' => 'RS',
                            'assigned_to' => 'Neha (Front Desk)',
                            'due_date' => 'Today, 10:00 AM',
                            'overdue_by' => null,
                            'priority' => 'high',
                            'type' => 'call',
                            'status' => 'pending',
                            'tags' => ['Dental Implant'],
                        ]
                    ])
                    @include('communication.tasks.partials.task-card', [
                        'task' => [
                            'id' => 5,
                            'title' => 'WhatsApp Priya Singh — Appointment reminder',
                            'lead_name' => 'Priya Singh',
                            'lead_initial' => 'PS',
                            'assigned_to' => 'Anjali Kapoor',
                            'due_date' => 'Today, 11:00 AM',
                            'overdue_by' => null,
                            'priority' => 'medium',
                            'type' => 'whatsapp',
                            'status' => 'pending',
                            'tags' => ['Root Canal'],
                        ]
                    ])
                    @include('communication.tasks.partials.task-card', [
                        'task' => [
                            'id' => 6,
                            'title' => 'Clinic visit follow-up — Karan Malhotra consultation',
                            'lead_name' => 'Karan Malhotra',
                            'lead_initial' => 'KM',
                            'assigned_to' => 'Priya Singh',
                            'due_date' => 'Today, 12:00 PM',
                            'overdue_by' => null,
                            'priority' => 'medium',
                            'type' => 'clinic-visit',
                            'status' => 'pending',
                            'tags' => ['Consultation'],
                        ]
                    ])
                    @include('communication.tasks.partials.task-card', [
                        'task' => [
                            'id' => 7,
                            'title' => 'Follow up with Vikram Mehta — Scaling & SRP',
                            'lead_name' => 'Vikram Mehta',
                            'lead_initial' => 'VM',
                            'assigned_to' => 'Neha (Front Desk)',
                            'due_date' => 'Today, 01:00 PM',
                            'overdue_by' => null,
                            'priority' => 'low',
                            'type' => 'call',
                            'status' => 'pending',
                            'tags' => ['Scaling & SRP'],
                        ]
                    ])
                </div>
            </div>

            {{-- Upcoming Section --}}
            <div class="task-section">
                <div class="section-header">
                    <div class="section-title">
                        <span class="section-dot upcoming-dot"></span>
                        Upcoming
                        <span class="section-count">27</span>
                    </div>
                    <button class="section-toggle" onclick="toggleSection('upcoming-tasks')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                </div>
                <div class="task-cards" id="upcoming-tasks">
                    @include('communication.tasks.partials.task-card', [
                        'task' => [
                            'id' => 8,
                            'title' => 'Call Neha Kapoor — Aligners follow-up',
                            'lead_name' => 'Neha Kapoor',
                            'lead_initial' => 'NK',
                            'assigned_to' => 'Anjali Kapoor',
                            'due_date' => '20 May 2025, 11:30 AM',
                            'overdue_by' => null,
                            'priority' => 'medium',
                            'type' => 'call',
                            'status' => 'upcoming',
                            'tags' => ['Aligners'],
                        ]
                    ])
                    @include('communication.tasks.partials.task-card', [
                        'task' => [
                            'id' => 9,
                            'title' => 'Send estimate to Siddharth Rao — Crown procedure',
                            'lead_name' => 'Siddharth Rao',
                            'lead_initial' => 'SR',
                            'assigned_to' => 'Priya Singh',
                            'due_date' => '21 May 2025, 02:00 PM',
                            'overdue_by' => null,
                            'priority' => 'high',
                            'type' => 'note',
                            'status' => 'upcoming',
                            'tags' => ['Crown', 'Estimate'],
                        ]
                    ])
                </div>
            </div>

        </div>

        {{-- Right Panel: Assignee Summary --}}
        <div class="tasks-right-panel">
            <div class="panel-section">
                <div class="panel-section-title">Team Workload</div>
                <div class="assignee-list">
                    @php
                    $assignees = [
                        ['name' => 'Neha (Front Desk)', 'initial' => 'N', 'total' => 18, 'overdue' => 3, 'color' => '#5B4FBE'],
                        ['name' => 'Anjali Kapoor', 'initial' => 'AK', 'total' => 15, 'overdue' => 2, 'color' => '#0F6E56'],
                        ['name' => 'Priya Singh', 'initial' => 'PS', 'total' => 10, 'overdue' => 2, 'color' => '#854F0B'],
                        ['name' => 'Siddharth Rao', 'initial' => 'SR', 'total' => 4, 'overdue' => 1, 'color' => '#993C1D'],
                    ];
                    @endphp
                    @foreach($assignees as $a)
                    <div class="assignee-row" onclick="filterByAssignee('{{ $a['name'] }}')">
                        <div class="assignee-avatar" style="background: {{ $a['color'] }}20; color: {{ $a['color'] }}">{{ $a['initial'] }}</div>
                        <div class="assignee-info">
                            <span class="assignee-name">{{ $a['name'] }}</span>
                            <div class="assignee-bar-wrap">
                                <div class="assignee-bar" style="width: {{ ($a['total'] / 18) * 100 }}%; background: {{ $a['color'] }}"></div>
                            </div>
                        </div>
                        <div class="assignee-counts">
                            <span class="assignee-total">{{ $a['total'] }}</span>
                            @if($a['overdue'] > 0)
                            <span class="assignee-overdue">{{ $a['overdue'] }} overdue</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="panel-section">
                <div class="panel-section-title">Quick Actions</div>
                <div class="quick-actions-list">
                    <button class="quick-action-btn" onclick="openAddTaskModal()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add New Task
                    </button>
                    <button class="quick-action-btn" onclick="openBulkAssignModal()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                        Bulk Assign
                    </button>
                    <button class="quick-action-btn" onclick="viewEscalated()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                        View Escalated
                        <span class="action-badge">3</span>
                    </button>
                </div>
            </div>

            <div class="panel-section">
                <div class="panel-section-title">Today's Priority</div>
                <div class="priority-cards">
                    <div class="priority-item priority-high">
                        <div class="priority-dot"></div>
                        <div class="priority-text">
                            <span class="priority-label">High Priority</span>
                            <span class="priority-val">5 tasks</span>
                        </div>
                    </div>
                    <div class="priority-item priority-medium">
                        <div class="priority-dot"></div>
                        <div class="priority-text">
                            <span class="priority-label">Medium Priority</span>
                            <span class="priority-val">4 tasks</span>
                        </div>
                    </div>
                    <div class="priority-item priority-low">
                        <div class="priority-dot"></div>
                        <div class="priority-text">
                            <span class="priority-label">Low Priority</span>
                            <span class="priority-val">3 tasks</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- Add Task Modal --}}
<div class="modal-overlay" id="add-task-modal" style="display:none">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">Add Task</h3>
            <button class="modal-close" onclick="closeAddTaskModal()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        @include('components.tasks.assignment-modal')
    </div>
</div>

{{-- Escalation Modal --}}
<div class="modal-overlay" id="escalation-modal" style="display:none">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">Escalate Task</h3>
            <button class="modal-close" onclick="closeEscalationModal()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        @include('components.tasks.escalation-flag')
    </div>
</div>

@push('scripts')
<script src="{{ asset('js/communication/tasks.js') }}"></script>
@endpush
@endsection
