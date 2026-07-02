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
        'clinical'    => 'Clinical',
        'admin'       => 'Admin',
        'lab'         => 'Lab',
        'follow_up'   => 'Follow-up',
        'call'        => 'Call',
        'whatsapp'    => 'WhatsApp',
        'maintenance' => 'Maintenance',
        'other'       => 'Other',
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

            {{-- Practice Protocol badges --}}
            @if($task->practice_protocol_id)
            <span style="font-size:11px;color:#6a0f70;background:#f3e6f8;padding:2px 8px;border-radius:4px;">Protocol</span>
                @if($task->requires_evidence)
                <span style="font-size:11px;color:#7a5c00;background:#fff4d6;padding:2px 8px;border-radius:4px;">Evidence required</span>
                @endif
            @endif

            {{-- Maintenance sub-type --}}
            @if($task->category === 'maintenance' && $task->maintenance_type)
            <span style="font-size:11px;color:#7a5c00;background:#fff4d6;padding:2px 8px;border-radius:4px;">
                {{ \App\Models\Task::MAINTENANCE_TYPES[$task->maintenance_type] ?? $task->maintenance_type }}
            </span>
            @endif

            {{-- Recurring badge --}}
            @if($task->is_recurring)
            <span style="font-size:11px;color:#1a5ea8;background:#e6f0fb;padding:2px 8px;border-radius:4px;"
                  title="{{ $task->recurrenceLabel() }}">
                {{ $task->recurrenceLabel() }}
            </span>
            @endif

            {{-- Next due (shown on completed recurring tasks) --}}
            @if($task->status === 'done' && $task->is_recurring && $task->next_due_date)
            <span style="font-size:11px;color:#1a7a45;background:#e8f7ef;padding:2px 8px;border-radius:4px;">
                Next: {{ $task->next_due_date->format('d M Y') }}
            </span>
            @endif

            {{-- Patient link --}}
            @if($task->patient)
            <span style="display:inline-flex;align-items:center;gap:4px;font-size:11.5px;color:#6a0f70;">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                </svg>
                {{ $task->patient->name ?? 'Patient #'.$task->patient_id }}
            </span>
            @endif

            {{-- Vendor / PO badge --}}
            @if($task->vendor_note)
            <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;color:#7a4500;background:#fff4e0;border:1px solid #f0d080;padding:2px 8px;border-radius:4px;">
                <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 9m12-9l2 9M9 21h6"/>
                </svg>
                {{ $task->vendor_note }}
            </span>
            @endif

        </div>

        {{-- SOP steps (from the linked practice protocol) --}}
        @php $sop = $task->practice_protocol_id ? optional($task->protocol)->materials?->firstWhere('type', 'sop_steps') : null; @endphp
        @if($sop && !empty($sop->body))
        <div style="margin-top:9px;">
            <button type="button"
                    onclick="const b=this.nextElementSibling;b.style.display=(b.style.display==='none'?'block':'none');"
                    style="display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:600;color:#6a0f70;background:none;border:none;cursor:pointer;padding:0;font-family:inherit;">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                View SOP
            </button>
            <div style="display:none;margin-top:7px;background:#faf6fd;border:1px solid #ede4f3;border-radius:8px;padding:10px 13px;">
                <ol style="margin:0;padding-left:18px;font-size:12px;color:#4a3a52;line-height:1.7;">
                    @foreach($sop->body as $step)
                    <li>{{ $step }}</li>
                    @endforeach
                </ol>
            </div>
        </div>
        @endif
    </div>

    {{-- Actions --}}
    @if($showActions && $badge !== 'done')
    <div style="display:flex;gap:6px;flex-shrink:0;align-items:flex-start;">
        @if($task->requires_evidence)
        {{-- Evidence-required: upload proof to complete --}}
        <input type="file" id="evi-{{ $task->id }}" accept="image/*,application/pdf" style="display:none;"
               onchange="uploadTaskEvidence({{ $task->id }}, this)">
        <button type="button" onclick="document.getElementById('evi-{{ $task->id }}').click()"
                style="padding:5px 12px;background:#fff4d6;color:#7a5c00;border:1.5px solid #f0d890;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;white-space:nowrap;">
            Attach proof &amp; complete
        </button>
        @else
        <button onclick="markTaskDone({{ $task->id }}, this)"
                style="padding:5px 14px;background:#e8f7ef;color:#1a7a45;border:1.5px solid #c8ebd8;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;transition:all 120ms;"
                onmouseover="this.style.background='#1a7a45';this.style.color='#fff'"
                onmouseout="this.style.background='#e8f7ef';this.style.color='#1a7a45'">
            ✓ Complete
        </button>
        @endif
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
    const card = btn.closest('div[style]');
    try {
        const res = await fetch(`/tasks/${id}/done`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            }
        });
        const data = await res.json();

        // Evidence-required task: can't complete without proof — nudge the user.
        if (!res.ok && data.needs_evidence) {
            alert(data.message || 'Evidence is required before completing this task.');
            btn.textContent = '✓ Complete';
            btn.disabled = false;
            return;
        }

        // If it's a recurring task, show the next-due banner before removing
        if (data.is_recurring && data.next_due_date) {
            const banner = document.createElement('div');
            banner.style.cssText = 'position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(26,122,69,.92);color:#fff;border-radius:10px;font-size:13px;font-weight:600;gap:8px;z-index:2;';
            banner.innerHTML = `<span>✓ Done</span><span style="opacity:.6">·</span><span>Next due: ${data.next_due_date}</span>`;
            card.style.position = 'relative';
            card.appendChild(banner);
            setTimeout(() => {
                card.style.opacity = '0';
                card.style.transition = 'opacity 400ms';
                setTimeout(() => card.remove(), 420);
            }, 2200);
        } else {
            // Regular task: just fade out
            card.style.opacity = '0';
            card.style.transition = 'opacity 300ms';
            setTimeout(() => card.remove(), 320);
        }
    } catch(e) {
        btn.textContent = '✓ Complete';
        btn.disabled = false;
    }
}

// Attach proof (image/PDF) and complete an evidence-required task in one step.
async function uploadTaskEvidence(id, input) {
    if (!input.files || !input.files[0]) return;
    const card = input.closest('div[style]');
    const fd = new FormData();
    fd.append('proof', input.files[0]);
    try {
        const res = await fetch(`/tasks/${id}/evidence`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: fd,
        });
        const data = await res.json();
        if (!res.ok) {
            alert(data.message || 'Upload failed. Please use a JPG, PNG or PDF under 5 MB.');
            input.value = '';
            return;
        }
        card.style.opacity = '0';
        card.style.transition = 'opacity 300ms';
        setTimeout(() => card.remove(), 320);
    } catch (e) {
        alert('Upload failed. Please try again.');
        input.value = '';
    }
}
</script>
@endonce
