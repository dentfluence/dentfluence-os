{{-- Reusable tag pill --}}
@php
$label = $label ?? '';
$color = $color ?? '#6b7280';
$bg    = $bg    ?? '#f3f4f6';
@endphp
<span style="display:inline-block;padding:2px 9px;border-radius:99px;font-size:10px;font-weight:700;background:{{ $bg }};color:{{ $color }};white-space:nowrap;">
    {{ $label }}
</span>
