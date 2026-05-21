{{-- Component: lead-card  Usage: <x-prm.lead-card :lead="$lead" /> --}}
@props(['lead'])

<div class="lead-card"
     draggable="true"
     data-lead-id="{{ $lead['id'] }}"
     data-stage="{{ $lead['stage'] }}"
     onclick="window.location='/communication/prm/lead/{{ $lead['id'] }}'">

    <div class="lc-top">
        <div>
            <div class="lc-name">{{ $lead['name'] }}</div>
            <div class="lc-phone">{{ $lead['phone'] }}</div>
        </div>
        <div class="lc-actions" onclick="event.stopPropagation()">
            <a href="tel:{{ preg_replace('/\s+/', '', $lead['phone']) }}"
               class="lc-icon-btn" title="Call">
                <i class="ti ti-phone" aria-hidden="true"></i>
            </a>
            <button class="lc-icon-btn" title="More actions"
                    onclick="toggleLeadMenu({{ $lead['id'] }})">
                <i class="ti ti-dots-vertical" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <x-prm.stage-badge :stage="$lead['stage']" />

    @if($lead['is_overdue'] && $lead['overdue_days'] > 0)
        <div class="lc-followup lc-overdue">
            <i class="ti ti-alert-circle" aria-hidden="true"></i>
            Overdue by {{ $lead['overdue_days'] }} {{ $lead['overdue_days'] === 1 ? 'day' : 'days' }}
        </div>
    @elseif(!empty($lead['followup_date']))
        <div class="lc-followup">
            <i class="ti ti-clock" aria-hidden="true"></i>
            @if(in_array($lead['stage'], ['appointment', 'consultation']))
                {{ \Carbon\Carbon::parse($lead['followup_date'])->format('d M Y') }}, {{ $lead['followup_time'] }}
            @else
                Follow-up: {{ \Carbon\Carbon::parse($lead['followup_date'])->isToday() ? 'Today' : \Carbon\Carbon::parse($lead['followup_date'])->format('d M') }}, {{ $lead['followup_time'] }}
            @endif
        </div>
    @endif

    {{-- Hidden context menu --}}
    <div class="lead-context-menu" id="menu-{{ $lead['id'] }}" style="display:none">
        <a href="/communication/prm/lead/{{ $lead['id'] }}"><i class="ti ti-eye" aria-hidden="true"></i> View Details</a>
        <a href="/communication/prm/lead/{{ $lead['id'] }}/edit"><i class="ti ti-edit" aria-hidden="true"></i> Edit Lead</a>
        <button onclick="openChangeStage({{ $lead['id'] }}, '{{ $lead['stage'] }}')"><i class="ti ti-arrows-exchange" aria-hidden="true"></i> Change Stage</button>
        <button onclick="openAddNote({{ $lead['id'] }})"><i class="ti ti-notes" aria-hidden="true"></i> Add Note</button>
        <button onclick="openScheduleFollowup({{ $lead['id'] }})"><i class="ti ti-calendar-plus" aria-hidden="true"></i> Schedule Follow-up</button>
        <a href="https://wa.me/91{{ preg_replace('/\s+/', '', $lead['phone']) }}" target="_blank"><i class="ti ti-brand-whatsapp" aria-hidden="true"></i> WhatsApp</a>
    </div>

</div>
