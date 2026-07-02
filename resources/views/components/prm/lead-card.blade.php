{{-- Component: lead-card  Usage: <x-prm.lead-card :lead="$lead" /> --}}
@props(['lead'])

@php
$leadJson = json_encode([
    'id'                 => $lead['id'],
    'relationship_id'    => $lead['relationship_id'] ?? null,
    'name'               => $lead['name'],
    'phone'              => $lead['phone'],
    'stage'              => $lead['stage'],
    'email'              => $lead['email'] ?? '',
    'dob'                => $lead['dob'] ?? '',
    'gender'             => $lead['gender'] ?? '',
    'treatment'          => $lead['treatment'] ?? '',
    'secondary_treatment'=> $lead['secondary_treatment'] ?? '',
    'source'             => $lead['source'] ?? '',
    'lead_source'        => $lead['lead_source'] ?? '',
    'lead_value'         => $lead['lead_value'] ?? null,
    'referred_by'        => $lead['referred_by'] ?? '',
    'urgency'            => $lead['urgency'] ?? 'medium',
    'preferred_time'     => $lead['preferred_time'] ?? '',
    'contact_method'     => $lead['contact_method'] ?? '',
    'preferred_contact'  => $lead['preferred_contact'] ?? 'call',
    'lead_type'          => $lead['lead_type'] ?? 'new_patient',
    'assigned_to_id'     => $lead['assigned_to_id'] ?? '',
    'followup_date'      => $lead['followup_date'] ?? '',
    'followup_time'      => $lead['followup_time'] ?? '',
    'notes'              => $lead['notes'] ?? '',
    'tags'               => $lead['tags'] ?? [],
    'occupation'         => $lead['occupation'] ?? '',
    'location'           => $lead['location'] ?? '',
    'language'           => $lead['language'] ?? '',
]);
@endphp

<div class="lead-card"
     draggable="true"
     data-lead-id="{{ $lead['id'] }}"
     data-stage="{{ $lead['stage'] }}"
     onclick="window.location='{{ !empty($lead['relationship_id']) ? route('relationship.profile', $lead['relationship_id']) : '/communication/prm/leads/'.$lead['id'] }}'"
     title="{{ !empty($lead['relationship_id']) ? 'View Relationship Profile' : 'View Lead' }}">

    <div class="lc-top">
        <div>
            <div class="lc-name">
                {{ $lead['name'] }}
                @if(!empty($lead['relationship_id']))
                    <span style="display:inline-block;margin-left:4px;font-size:9px;font-weight:600;color:#534AB7;background:#EEEDFE;border-radius:10px;padding:1px 5px;vertical-align:middle;" title="Linked to Relationship #{{ $lead['relationship_id'] }}">REL</span>
                @endif
            </div>
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

    {{-- Row: stage + Rs.  value --}}
    <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;margin-bottom:2px;">
        <x-prm.stage-badge :stage="$lead['stage']" />
        @if(!empty($lead['lead_value']) && $lead['lead_value'] > 0)
            <span style="font-size:11px;font-weight:600;color:#0F6E56;background:#E1F5EE;border-radius:20px;padding:2px 7px;white-space:nowrap;">
                Rs. {{ number_format($lead['lead_value']) }}
            </span>
        @endif
    </div>

    {{-- AI insight (Phase 1) — only when enabled and the lead has been enriched --}}
    @if(config('prm.ai.show_on_cards') && !empty($lead['ai_summary']))
        <div class="lc-ai" title="AI summary">
            <i class="ti ti-sparkles" aria-hidden="true" style="color:#7A5AF8;"></i>
            <span style="font-size:11px;color:#5A5A56;">{{ $lead['ai_summary'] }}</span>
        </div>
        @if(!empty($lead['ai_treatment_label']))
            <span style="display:inline-block;margin-top:3px;font-size:10px;font-weight:600;color:#534AB7;background:#EEEDFE;border-radius:20px;padding:2px 7px;">
                {{ $lead['ai_treatment_label'] }}
            </span>
        @endif
    @endif

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

    {{-- Assignee (Phase 2a auto-assign) --}}
    @if(!empty($lead['assigned_to']))
        <div style="display:flex;align-items:center;gap:5px;margin-top:6px;font-size:11px;color:#5A5A56;">
            <i class="ti ti-user" aria-hidden="true" style="font-size:13px;"></i>
            {{ $lead['assigned_to'] }}
        </div>
    @endif

</div>