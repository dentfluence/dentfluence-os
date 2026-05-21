{{-- Component: stage-selector  Usage: <x-prm.stage-selector :current="$lead['stage']" :leadId="$lead['id']" /> --}}
@props(['current' => 'new_lead', 'leadId' => 0])

@php
$stages = [
    'new_lead'     => 'New Lead',
    'contacted'    => 'Contacted',
    'appointment'  => 'Appointment',
    'consultation' => 'Consultation',
    'plan_given'   => 'Plan Given',
    'converted'    => 'Converted',
    'lost'         => 'Lost',
];
@endphp

<div class="stage-selector-wrap">
    <label class="form-label">Pipeline Stage</label>
    <select name="stage" class="stage-selector" onchange="confirmStageChange(this, {{ $leadId }})">
        @foreach($stages as $key => $label)
            <option value="{{ $key }}" {{ $current === $key ? 'selected' : '' }}>
                {{ $label }}
            </option>
        @endforeach
    </select>
</div>
