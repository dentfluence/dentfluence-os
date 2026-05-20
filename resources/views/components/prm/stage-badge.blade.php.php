@props(['stage' => 'new_lead'])

@php
$stages = [
    'new_lead'     => ['label' => 'New Lead',     'bg' => '#EEEDFE', 'color' => '#534AB7'],
    'contacted'    => ['label' => 'Contacted',    'bg' => '#E1F5EE', 'color' => '#0F6E56'],
    'appointment'  => ['label' => 'Appointment',  'bg' => '#FAEEDA', 'color' => '#854F0B'],
    'consultation' => ['label' => 'Consultation', 'bg' => '#E6F1FB', 'color' => '#185FA5'],
    'plan_given'   => ['label' => 'Plan Given',   'bg' => '#FBEAF0', 'color' => '#993556'],
    'converted'    => ['label' => 'Converted',    'bg' => '#EAF3DE', 'color' => '#3B6D11'],
    'lost'         => ['label' => 'Lost',         'bg' => '#FAECE7', 'color' => '#993C1D'],
];

$s = $stages[$stage] ?? ['label' => ucfirst($stage), 'bg' => '#F1EFE8', 'color' => '#5F5E5A'];
@endphp

<span style="background:{{ $s['bg'] }};color:{{ $s['color'] }};padding:2px 10px;border-radius:10px;font-size:11px;font-weight:500;">
    {{ $s['label'] }}
</span>