{{-- Component: lead-card  Usage: <x-prm.lead-card :lead="$lead" /> --}}
@props(['lead'])

@php
$leadJson = json_encode([
    'id'                => $lead['id'],
    'name'              => $lead['name'],
    'phone'             => $lead['phone'],
    'stage'             => $lead['stage'],
    'email'             => $lead['email'] ?? '',
    'dob'               => $lead['dob'] ?? '',
    'gender'            => $lead['gender'] ?? '',
    'treatment'         => $lead['treatment'] ?? '',
    'secondary_treatment'=> $lead['secondary_treatment'] ?? '',
    'source'            => $lead['source'] ?? '',
    'referred_by'       => $lead['referred_by'] ?? '',
    'urgency'           => $lead['urgency'] ?? 'medium',
    'preferred_time'    => $lead['preferred_time'] ?? '',
    'contact_method'    => $lead['contact_method'] ?? '',
    'preferred_contact' => $lead['preferred_contact'] ?? 'call',
    'lead_type'         => $lead['lead_type'] ?? 'new_patient',
    'assigned_to_id'    => $lead['assigned_to_id'] ?? '',
    'followup_date'     => $lead['followup_date'] ?? '',
    'followup_time'     => $lead['followup_time'] ?? '',
    'notes'             => $lead['notes'] ?? '',
    'tags'              => $lead['tags'] ?? [],
    'occupation'        => $lead['occupation'] ?? '',
    'location'          => $lead['location'] ?? '',
    'language'          => $lead['language'] ?? '',
]);
@endphp

<div class="lead-card"
     draggable="true"
     data-lead-id="{{ $lead['id'] }}"
     data-stage="{{ $lead['stage'] }}"
     onclick="window.location='/communication/prm/leads/{{ $lead['id'] }}'">

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
                    onclick="openContextMenu(this, {{ $leadJson }})">
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
            @if(in_array($lead['stage'], ['appointment','consultation']))
                {{ \Carbon\Carbon::parse($lead['followup_date'])->format('d M Y') }}, {{ $lead['followup_time'] }}
            @else
                Follow-up: {{ \Carbon\Carbon::parse($lead['followup_date'])->isToday() ? 'Today' : \Carbon\Carbon::parse($lead['followup_date'])->format('d M') }}, {{ $lead['followup_time'] }}
            @endif
        </div>
    @endif

</div>