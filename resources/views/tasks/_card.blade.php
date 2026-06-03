{{--
  Task Card Partial
  Variables:
    $task        — Task model (with assignedTo, patient)
    $badge       — 'backlog' | 'today' | 'upcoming' | 'done'
    $showActions — bool (default false) — show Complete/NA buttons for staff
--}}
@php
    $showActions = $showActions ?? false;

    $badgeStyles = [
        'backlog'  => 'background:#fdeaea;color:#b52020;',
        'today'    => 'background:#fff4e0;color:#a05c00;',
        'upcoming' => 'background:#e6f0fb;color:#1a5ea8;',
        'done'     => 'background:#e8f7ef;color:#1a7a45;',
    ];
    $badgeLabels = [
        'backlog'  => 'Overdue',
        'today'    => 'Due Today',
        'upcoming' => 'Upcoming',
        'done'     => 'Done',
    ];
    $priorityDots = [
        'urgent' => '#b52020',
        'high'   => '#c0580a',
        'medium' => '#a05c00',
        'low'    => '#1a7a45',
    ];
    $categoryLabels = [
        'clinical'  => 'Clinical',
        'admin'     => 'Admin',
        'lab'       => 'Lab',
        'follow_up' => 'Follow-up',
        'other'     => 'Other',
    ];
@endphp

<div style="background:#fff;border:1.5px solid #ede4f3;border-radius:10px;padding:14px 16px;display:flex;align-items:flex-start;gap:13px;transition:box-shadow 140ms;"
     onmouseover="this.style.boxShadow='0 2px 12px rgba(106,15,112,.08)'"
     onmouseout="this.style.boxShadow='none'">

    {{-- Priority dot --}}
    <div style="width:9px;height:9px;border-radius:50%;background:{{ $priorityDots[$task->priority] ?? '#9a7aaa' }};flex-shrink:0;margin-top:5px;"></div>

    {{-- Main content --}}
    <div style="flex:1;min-width:0;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;">
            <div style="font-size:13.5px;font-weight:500;color:#1a0320;line-height:1.4;{{ $badge==='done' ? 'text-decoration:line-through;color:#9a7aaa;' : '' }}">
                {{ $task->title }}
            </div>
            {{-- Badge --}}
            <span style="flex-shrink:0;font-size:10.5px;font-weight:600;padding:3px 9px;border-radius:20px;{{ $badgeStyles[$badge] ?? '' }}">
                {{ $badgeLabels[$badge] ?? '' }}
            </span>
        </div>

        {{-- Meta row --}}
        <div style="display:flex;align-items:center;flex-wrap:wrap;gap:12px;margin-top:7px;">

            {{-- Assigned to --}}
            @if($task->assignedTo)
            <span style="display:inline-flex;align-items:center;gap:5px;font-size:11.5px;color:#7a6a85;">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
                {{ $task->assignedTo->name }}
            </span>
            @endif

            {{-- Due date --}}
            <span style="display:inline-flex;align-items:center;gap:5px;font-size:11.5px;color:{{ $badge==='backlog' ? '#b52020' : '#7a6a85' }};">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                {{ $task->due_date->format('d M Y') }}
                @if($task->due_time)
                    · {{ \Carbon\Carbon::parse($task->due_time)->format('g:i A') }}
                @endif
            </span>

            {{-- Category --}}
            <span style="font-size:11px;color:#9a7aaa;background:#f5f0f8;padding:2px 8px;border-radius:4px;">
                {{ $categoryLabels[$task->category] ?? $task->category }}
            </span>

            {{-- Patient link --}}
            @if($task->patient)
            <span style="display:inline-flex;align-items:center;gap:4px;font-size:11.5px;color:#6a0f70;">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                </svg>
                {{ $task->patient->name ?? 'Patient #'.$task->patient_id }}
            </span>
            @endif

        </div>
    </div>

    {{-- Actions --}}
    @if($showActions && $badge !== 'done')
    <div style="display:flex;gap:6px;flex-shrink:0;">
        <button onclick="markTaskDone({{ $task->id }}, this)"
                style="padding:5px 14px;background:#e8f7ef;color:#1a7a45;border:1.5px solid #c8ebd8;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;transition:all 120ms;"
                onmouseover="this.style.background='#1a7a45';this.style.color='#fff'"
                onmouseout="this.style.background='#e8f7ef';this.style.color='#1a7a45'">
            ✓ Complete
        </button>
        <button style="padding:5px 12px;background:#f9f5fc;color:#9a7aaa;border:1.5px solid #ede4f3;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;font-family:inherit;">
            NA
        </button>
    </div>
    @elseif($badge === 'done' && $task->done_at)
    <div style="font-size:11px;color:#1a7a45;flex-shrink:0;white-space:nowrap;">
        ✓ {{ $task->done_at->format('d M, g:i A') }}
    </div>
    @endif

</div>

@once
<script>
async function markTaskDone(id, btn) {
    btn.textContent = '…';
    btn.disabled = true;
    try {
        await fetch(`/tasks/${id}/done`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        });
        // Fade out the card
        const card = btn.closest('[style]');
        card.style.opacity = '0';
        card.style.transition = 'opacity 300ms';
        setTimeout(() => card.remove(), 320);
    } catch(e) {
        btn.textContent = '✓ Complete';
        btn.disabled = false;
    }
}
</script>
@endonce
