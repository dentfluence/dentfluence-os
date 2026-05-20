{{--
    Component: prm.stage-badge
    Props: $stage (string — stage id)
--}}
@php
    $stageMap = [
        'new_lead'     => ['label' => 'New Lead',    'bg' => '#EEEDFE', 'color' => '#534AB7'],
        'contacted'    => ['label' => 'Contacted',   'bg' => '#E1F5EE', 'color' => '#0F6E56'],
        'appointment'  => ['label' => 'Appt. Fixed', 'bg' => '#FAEEDA', 'color' => '#854F0B'],
        'consultation' => ['label' => 'Consultation','bg' => '#E6F1FB', 'color' => '#185FA5'],
        'plan_given'   => ['label' => 'Plan Given',  'bg' => '#FBEAF0', 'color' => '#993556'],
        'converted'    => ['label' => 'Converted',   'bg' => '#EAF3DE', 'color' => '#3B6D11'],
    ];
    $meta = $stageMap[$stage] ?? ['label' => $stage, 'bg' => '#F1EFE8', 'color' => '#5F5E5A'];
@endphp

<span class="prm-stage-badge"
      style="background:{{ $meta['bg'] }};color:{{ $meta['color'] }}">
    {{ $meta['label'] }}
</span>
