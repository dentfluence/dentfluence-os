{{--
    One task = one line. See the note at the top of tasks/index.blade.php for
    why this is a row and not a card.

    The whole row opens the outcome drawer; the Done button opens the same
    drawer already set to "done". Nothing here closes a task in one silent
    click — a close always carries a reason, which is the point of the rebuild.
--}}
@php
    $dot = match ($task->priority) {
        'urgent' => '#b52020',
        'high'   => '#a05c00',
        'medium' => '#1a5ea8',
        default  => '#7aa88a',
    };

    $late   = $task->daysLate();
    $closed = ! $task->isOpen();

    if ($closed) {
        $dueLabel  = $task->status === 'cancelled' ? 'Cancelled' : 'Done';
        $dueColour = $task->status === 'cancelled' ? '#9a7aaa' : '#1a7a45';
    } elseif ($late > 0) {
        $dueLabel  = $late === 1 ? '1 day late' : $late . ' days late';
        $dueColour = '#b52020';
    } elseif ($task->due_date->isToday()) {
        $dueLabel  = 'Today';
        $dueColour = '#a05c00';
    } else {
        $dueLabel  = $task->due_date->format('d M');
        $dueColour = '#7a6088';
    }
@endphp

<div @click="open({{ $task->id }})"
     style="display:flex;align-items:center;gap:12px;padding:11px 28px;border-bottom:1px solid #f3eef7;cursor:pointer;{{ $closed ? 'opacity:.62;' : '' }}"
     onmouseenter="this.style.background='#faf7fc'" onmouseleave="this.style.background='transparent'">

    <span style="width:7px;height:7px;border-radius:50%;background:{{ $dot }};flex-shrink:0;"
          title="{{ ucfirst($task->priority) }} priority"></span>

    <div style="flex:1;min-width:0;">
        <div style="font-size:13.5px;color:#1a0320;font-weight:500;{{ $closed ? 'text-decoration:line-through;' : '' }}
                    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            {{ $task->title }}
        </div>
        <div style="font-size:11.5px;color:#9a7aaa;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            {{ $task->categoryLabel() }}
            @if($task->patient) · {{ $task->patient->name }} @endif
            @if($task->assignedTo) · {{ $task->assignedTo->name }} @endif
            @if($task->is_recurring) · {{ $task->recurrenceLabel() }} @endif
        </div>
    </div>

    {{-- Badges: what the staff member needs to know before opening the row. --}}
    <div style="display:flex;gap:5px;flex-shrink:0;">
        @if($task->wasAttempted())
            <span style="font-size:10.5px;font-weight:600;padding:2px 7px;border-radius:4px;background:#fff4e0;color:#a05c00;">{{ $task->attemptLabel() }}</span>
        @endif
        @if($task->wasRescheduled())
            <span style="font-size:10.5px;font-weight:600;padding:2px 7px;border-radius:4px;background:#f3eef7;color:#7a6088;"
                  title="First due {{ optional($task->original_due_date)->format('d M Y') }}">Moved {{ $task->reschedule_count }}x</span>
        @endif
        @if($task->practice_protocol_id)
            <span style="font-size:10.5px;font-weight:600;padding:2px 7px;border-radius:4px;background:#e6f0fb;color:#1a5ea8;">Protocol</span>
        @endif
        @if($task->requires_evidence)
            <span style="font-size:10.5px;font-weight:600;padding:2px 7px;border-radius:4px;background:#fdeaea;color:#b52020;">Proof needed</span>
        @endif
    </div>

    <div style="width:88px;text-align:right;font-size:12px;font-weight:600;color:{{ $dueColour }};flex-shrink:0;">
        {{ $dueLabel }}
    </div>

    @if(! $closed)
        <button @click.stop="open({{ $task->id }}, 'done')"
                style="flex-shrink:0;padding:5px 13px;background:#fff;border:1.5px solid #ede4f3;border-radius:6px;font-size:12px;font-weight:600;color:#6a0f70;cursor:pointer;font-family:inherit;">
            Done
        </button>
    @else
        <span style="flex-shrink:0;width:58px;"></span>
    @endif
</div>
