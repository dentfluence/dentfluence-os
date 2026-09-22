{{--
    One task = one table row. See the note above the table in index.blade.php
    for why this is columns rather than a sentence per line.

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

    // The Status column answers "where does this stand", which for open work
    // means WHEN — a task three days late and a task due Friday are in
    // different states, and that is the only thing worth colouring.
    if ($task->status === 'cancelled') {
        [$stateLabel, $stateColour] = ['Cancelled', '#9a7aaa'];
    } elseif ($task->status === 'done') {
        [$stateLabel, $stateColour] = ['Done', '#1a7a45'];
    } elseif ($late > 0) {
        [$stateLabel, $stateColour] = [$late === 1 ? '1 day late' : $late . ' days late', '#b52020'];
    } elseif ($task->due_date->isToday()) {
        [$stateLabel, $stateColour] = ['Today', '#a05c00'];
    } else {
        [$stateLabel, $stateColour] = ['Due ' . $task->due_date->format('d M'), '#7a6088'];
    }

    $cell = 'padding:11px 10px;border-bottom:1px solid #f3eef7;vertical-align:middle;';
@endphp

<tr @click="open({{ $task->id }})"
    style="cursor:pointer;{{ $closed ? 'opacity:.62;' : '' }}"
    onmouseenter="this.style.background='#faf7fc'" onmouseleave="this.style.background='transparent'">

    {{-- Task --}}
    <td style="{{ $cell }}padding-left:18px;max-width:0;">
        <div style="font-weight:500;color:#1a0320;{{ $closed ? 'text-decoration:line-through;' : '' }}
                    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            {{ $task->title }}
        </div>
        @if($task->patient)
            <div style="font-size:11.5px;color:#9a7aaa;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                {{ $task->patient->name }}
            </div>
        @endif
    </td>

    {{-- Staff --}}
    <td style="{{ $cell }}color:#4a3a55;font-size:12.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:130px;">
        {{ $task->assignedTo?->name ?? '—' }}
    </td>

    {{-- Type --}}
    <td style="{{ $cell }}color:#7a6088;font-size:12.5px;white-space:nowrap;">
        {{ $task->categoryLabel() }}
        @if($task->is_recurring)
            <div style="font-size:10.5px;color:#9a7aaa;">{{ $task->recurrenceLabel() }}</div>
        @endif
    </td>

    {{-- Priority --}}
    <td style="{{ $cell }}white-space:nowrap;">
        <span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:{{ $dot }};font-weight:500;">
            <span style="width:7px;height:7px;border-radius:50%;background:{{ $dot }};flex-shrink:0;"></span>
            {{ ucfirst($task->priority) }}
        </span>
    </td>

    {{-- Status as a pill, matching Today's Actions' Open / Attempted chips, so
         a receptionist reads the same shape for the same idea on both screens. --}}
    <td style="{{ $cell }}white-space:nowrap;">
        <span style="display:inline-block;font-size:11.5px;font-weight:600;padding:3px 10px;border-radius:999px;
                     color:{{ $stateColour }};background:{{ $stateColour }}14;border:1px solid {{ $stateColour }}33;">
            {{ $stateLabel }}
        </span>
        <div style="display:flex;gap:4px;margin-top:3px;flex-wrap:wrap;">
            @if($task->wasAttempted())
                <span style="font-size:10px;font-weight:600;padding:1px 6px;border-radius:4px;background:#fff4e0;color:#a05c00;">{{ $task->attemptLabel() }}</span>
            @endif
            @if($task->wasRescheduled())
                <span style="font-size:10px;font-weight:600;padding:1px 6px;border-radius:4px;background:#f3eef7;color:#7a6088;"
                      title="First due {{ optional($task->original_due_date)->format('d M Y') }}">Moved {{ $task->reschedule_count }}x</span>
            @endif
            @if($task->practice_protocol_id)
                <span style="font-size:10px;font-weight:600;padding:1px 6px;border-radius:4px;background:#e6f0fb;color:#1a5ea8;">Protocol</span>
            @endif
            @if($task->requires_evidence)
                <span style="font-size:10px;font-weight:600;padding:1px 6px;border-radius:4px;background:#fdeaea;color:#b52020;">Proof</span>
            @endif
        </div>
    </td>

    {{-- Action --}}
    <td style="{{ $cell }}padding-right:18px;text-align:right;white-space:nowrap;">
        @if(! $closed)
            <button @click.stop="open({{ $task->id }}, 'done')"
                    style="padding:6px 14px;background:#fff;border:1.5px solid #d9c7e4;border-radius:999px;font-size:12px;font-weight:600;color:#6a0f70;cursor:pointer;font-family:inherit;">
                Done
            </button>
        @endif
    </td>
</tr>
