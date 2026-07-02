@php
$priorityColors = ['high' => '#E53E3E', 'medium' => '#DD6B20', 'low' => '#38A169'];
$typeIcons = [
    'call' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81a19.79 19.79 0 01-3.07-8.67A2 2 0 012 .99h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 8.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>',
    'whatsapp' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>',
    'clinic-visit' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
    'note' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
    'escalation' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>',
];
$typeColors = ['call' => '#5B4FBE', 'whatsapp' => '#25D366', 'clinic-visit' => '#DD6B20', 'note' => '#718096', 'escalation' => '#E53E3E'];
@endphp

<div class="task-card {{ $task['status'] === 'overdue' ? 'task-card--overdue' : '' }} {{ !empty($task['escalated']) ? 'task-card--escalated' : '' }}"
     data-task-id="{{ $task['id'] }}"
     onclick="openTaskDetail({{ $task['id'] }})">

    {{-- Left: Priority stripe --}}
    <div class="task-priority-stripe" style="background: {{ $priorityColors[$task['priority']] }}"></div>

    {{-- Task body --}}
    <div class="task-body">
        <div class="task-top-row">
            {{-- Type badge --}}
            <span class="task-type-badge" style="color: {{ $typeColors[$task['type']] }}; background: {{ $typeColors[$task['type']] }}15">
                {!! $typeIcons[$task['type']] !!}
                {{ ucfirst(str_replace('-', ' ', $task['type'])) }}
            </span>

            @if(!empty($task['escalated']))
            <span class="escalation-flag-badge">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                Escalated
            </span>
            @endif

            @if($task['status'] === 'overdue')
            <span class="overdue-badge-inline">Overdue by {{ $task['overdue_by'] }}</span>
            @endif

            {{-- Actions --}}
            <div class="task-actions" onclick="event.stopPropagation()">
                <button class="task-action-btn task-complete-btn" title="Mark Complete" onclick="markComplete({{ $task['id'] }})">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                </button>
                <button class="task-action-btn" title="Assign" onclick="openAssignModal({{ $task['id'] }})">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </button>
                <button class="task-action-btn task-escalate-btn" title="Escalate" onclick="openEscalationModal({{ $task['id'] }})">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                </button>
                <button class="task-action-btn task-more-btn" title="More" onclick="openMoreMenu({{ $task['id'] }}, event)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                </button>
            </div>
        </div>

        <div class="task-title">{{ $task['title'] }}</div>

        <div class="task-meta-row">
            {{-- Lead --}}
            <div class="task-meta-item">
                <div class="mini-avatar">{{ $task['lead_initial'] }}</div>
                <span class="meta-text">{{ $task['lead_name'] }}</span>
            </div>

            {{-- Assigned --}}
            <div class="task-meta-item">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span class="meta-text">{{ $task['assigned_to'] }}</span>
            </div>

            {{-- Due date --}}
            <div class="task-meta-item {{ $task['status'] === 'overdue' ? 'meta-overdue' : '' }}">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span class="meta-text">{{ $task['due_date'] }}</span>
            </div>
        </div>

        @if(!empty($task['tags']))
        <div class="task-tags">
            @foreach($task['tags'] as $tag)
            <span class="task-tag">{{ $tag }}</span>
            @endforeach
        </div>
        @endif
    </div>
</div>
