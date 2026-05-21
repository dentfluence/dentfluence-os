{{-- Component: stage-badge  Usage: <x-prm.stage-badge :stage="$lead['stage']" /> --}}
@props(['stage' => 'new_lead'])

@php
$map = [
    'new_lead'     => ['label' => 'New',         'class' => 'sp-new'],
    'contacted'    => ['label' => 'Contacted',    'class' => 'sp-contacted'],
    'appointment'  => ['label' => 'Appt. Fixed',  'class' => 'sp-appt'],
    'consultation' => ['label' => 'Consultation', 'class' => 'sp-consult'],
    'plan_given'   => ['label' => 'Plan Given',   'class' => 'sp-plan'],
    'converted'    => ['label' => 'Converted',    'class' => 'sp-converted'],
    'lost'         => ['label' => 'Lost',         'class' => 'sp-lost'],
];
$item = $map[$stage] ?? ['label' => ucfirst($stage), 'class' => 'sp-new'];
@endphp

<span class="stage-pill {{ $item['class'] }}">{{ $item['label'] }}</span>
