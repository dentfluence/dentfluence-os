{{-- resources/views/tasks/_task_row.blade.php --}}
@php
    $badge = match($task->priority) {
        'urgent' => 'bg-red-100 text-red-700',
        'high'   => 'bg-orange-100 text-orange-700',
        'medium' => 'bg-yellow-100 text-yellow-700',
        'low'    => 'bg-green-100 text-green-700',
        default  => 'bg-gray-100 text-gray-600',
    };
@endphp

<div class="flex items-start justify-between bg-white border border-gray-100 rounded-xl px-4 py-3 shadow-sm"
     data-task-id="{{ $task->id }}">

    <div class="flex-1 min-w-0">
        <p class="text-sm font-medium text-gray-900 truncate {{ $task->status === 'done' ? 'line-through text-gray-400' : '' }}">
            {{ $task->title }}
        </p>
        <p class="text-xs text-gray-400 mt-0.5">
            {{ $task->assignedTo->name }}
            · {{ $task->due_date->format('d M Y') }}
            @if($task->patient)
                · <span class="text-purple-600 font-medium">👤 {{ $task->patient->name }}</span>
            @endif
            @if($task->category)
                · <span class="capitalize">{{ str_replace('_',' ', $task->category) }}</span>
            @endif
        </p>
    </div>

    <div class="flex items-center gap-2 ml-4 shrink-0">
        <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $badge }}">
            {{ $task->priority }}
        </span>
        @if($task->status !== 'done')
        <button onclick="markDone({{ $task->id }}, this)"
                class="text-xs text-gray-400 hover:text-green-600 transition-colors px-2 py-1 rounded border border-gray-200 hover:border-green-300">
            Done
        </button>
        @else
        <span class="text-xs text-green-500 font-medium">✓ Done</span>
        @endif
    </div>

</div>