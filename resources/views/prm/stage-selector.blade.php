{{--
    Component: prm.stage-selector
    Props: $currentStage (string), $leadId (int)
    Renders a dropdown/list of all stages for quick movement.
--}}
@php
    $stages = [
        ['id'=>'new_lead',     'label'=>'New Lead',    'color'=>'#534AB7'],
        ['id'=>'contacted',    'label'=>'Contacted',   'color'=>'#0F6E56'],
        ['id'=>'appointment',  'label'=>'Appointment', 'color'=>'#854F0B'],
        ['id'=>'consultation', 'label'=>'Consultation','color'=>'#185FA5'],
        ['id'=>'plan_given',   'label'=>'Plan Given',  'color'=>'#993556'],
        ['id'=>'converted',    'label'=>'Converted',   'color'=>'#3B6D11'],
    ];
@endphp

<div class="prm-stage-selector" data-lead="{{ $leadId }}">
    @foreach($stages as $stage)
    <button class="prm-stage-selector__option
                   {{ $stage['id'] === $currentStage ? 'prm-stage-selector__option--current' : '' }}"
            data-stage="{{ $stage['id'] }}"
            data-lead="{{ $leadId }}">
        <span class="prm-stage-selector__dot" style="background:{{ $stage['color'] }}"></span>
        {{ $stage['label'] }}
        @if($stage['id'] === $currentStage)
        <i class="ti ti-check prm-stage-selector__check"></i>
        @endif
    </button>
    @endforeach
</div>
