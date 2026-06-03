{{--
    Task Card Component
    Props: $task (array with id, title, lead_name, lead_initial, assigned_to,
           due_date, overdue_by, priority, type, status, tags, escalated)
    Usage: @include('components.tasks.task-card', ['task' => $taskArray])
           OR as component: <x-tasks.task-card :task="$task" />
--}}
@include('communication.tasks.partials.task-card', ['task' => $task])
