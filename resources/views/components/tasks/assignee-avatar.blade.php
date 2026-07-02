{{-- Assignee Avatar Component --}}
{{-- Props: $name, $initial, $color (optional), $size (sm/md/lg), $showName (bool) --}}
@php
$size = $size ?? 'md';
$sizes = ['sm' => '24px', 'md' => '32px', 'lg' => '40px'];
$fontSizes = ['sm' => '9px', 'md' => '11px', 'lg' => '14px'];
$color = $color ?? '#5B4FBE';
$dim = $sizes[$size];
$fs = $fontSizes[$size];
@endphp

<div class="assignee-avatar-component" style="display:inline-flex; align-items:center; gap:7px;">
    <div style="
        width: {{ $dim }};
        height: {{ $dim }};
        border-radius: 50%;
        background: {{ $color }}20;
        color: {{ $color }};
        font-size: {{ $fs }};
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    ">{{ $initial ?? substr($name ?? 'U', 0, 2) }}</div>

    @if(!empty($showName))
    <div>
        <span style="font-size:13px; font-weight:500; color:#2D3748; display:block;">{{ $name }}</span>
        @if(!empty($role))
        <span style="font-size:11px; color:#718096; display:block;">{{ $role }}</span>
        @endif
    </div>
    @endif
</div>
