{{-- Usage: @include('patients.partials.detail-row', ['label' => 'Mobile', 'value' => $patient->phone]) --}}
<div class="flex gap-2 items-start">
    <span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">{{ $label }}</span>
    <span class="text-sm text-gray-700">{{ $value ?? '—' }}</span>
</div>
