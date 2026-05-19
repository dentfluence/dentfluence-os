{{--
  Classification Picker Component
  Usage: <x-communication.classification-picker :selected="$selected" name="classification" />
--}}
@props(['selected' => null, 'name' => 'classification'])

@php
$options = [
    'new_patient'     => 'New Patient',
    'existing'        => 'Existing Patient',
    'ongoing_case'    => 'Ongoing Case',
    'doctor'          => 'Doctor',
    'vendor'          => 'Vendor',
    'lab'             => 'Lab',
    'spam'            => 'Spam',
    'other_important' => 'Other Important',
    'other'           => 'Other',
];
@endphp

<div class="cm-class-grid" id="class-grid-{{ $name }}">
    @foreach($options as $value => $label)
        <div class="cm-class-opt {{ $selected === $value ? 'selected' : '' }}"
             data-value="{{ $value }}"
             data-name="{{ $name }}"
             onclick="pickClass(this)">
            {{ $label }}
        </div>
    @endforeach
</div>

<input type="hidden" name="{{ $name }}" id="input-{{ $name }}" value="{{ $selected }}">

<script>
function pickClass(el) {
    const name = el.dataset.name;
    document.querySelectorAll(`#class-grid-${name} .cm-class-opt`).forEach(opt => opt.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById(`input-${name}`).value = el.dataset.value;
}
</script>
