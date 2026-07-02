{{--
|==========================================================================
| Component: activity-item
| Usage:
|   <x-marketing.activity-item
|       icon="<svg>...</svg>"
|       icon_color="#6a0f70"
|       text="Campaign 'Smile Week' was published."
|       time_ago="2 min ago"
|   />
|
| Props:
|   icon        — raw SVG string (16×16 recommended)
|   icon_color  — hex colour for icon bubble background tint + icon colour
|   text        — activity description
|   time_ago    — relative time string (e.g. "5 min ago")
|==========================================================================
--}}
@props([
    'icon'       => null,
    'icon_color' => '#6a0f70',
    'text'       => '',
    'time_ago'   => '',
])

<div style="
    display: flex;
    align-items: flex-start;
    gap: 11px;
    padding: 10px 0;
    border-bottom: 1px solid rgba(185,92,183,0.07);
    font-family: 'Inter', sans-serif;
">

    {{-- ── Coloured icon bubble ── --}}
    <div style="
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: {{ $icon_color }}18;
        border: 1px solid {{ $icon_color }}22;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: {{ $icon_color }};
        margin-top: 1px;
    ">
        @if($icon)
            {!! $icon !!}
        @else
            {{-- Default: circle dot --}}
            <svg width="8" height="8" viewBox="0 0 8 8" fill="currentColor">
                <circle cx="4" cy="4" r="4"/>
            </svg>
        @endif
    </div>

    {{-- ── Text ── --}}
    <div style="
        flex: 1;
        min-width: 0;
        font-size: 13px;
        font-weight: 400;
        color: #2d1040;
        line-height: 1.5;
        padding-top: 6px;
    ">{{ $text }}</div>

    {{-- ── Time ── --}}
    @if($time_ago)
    <div style="
        font-size: 11.5px;
        font-weight: 400;
        color: #9b6aad;
        white-space: nowrap;
        padding-top: 7px;
        flex-shrink: 0;
    ">{{ $time_ago }}</div>
    @endif

</div>
