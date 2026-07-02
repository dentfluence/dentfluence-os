{{--
|==========================================================================
| Component: stat-card
| Usage: <x-marketing.stat-card
|            label="Total Campaigns"
|            value="48"
|            trend="+20%"
|            trend_direction="up"
|            icon="<svg>...</svg>"
|            color="#6a0f70"
|        />
|
| Props:
|   label           — metric label (e.g. "Total Reach")
|   value           — big number to display
|   trend           — trend string (e.g. "+20%" or "-5%")
|   trend_direction — 'up' | 'down'
|   icon            — raw SVG string (24×24 recommended)
|   color           — hex colour for the icon background tint
|==========================================================================
--}}
@props([
    'label'           => 'Metric',
    'value'           => '0',
    'trend'           => null,
    'trend_direction' => 'up',
    'icon'            => null,
    'color'           => '#6a0f70',
])

@php
    // Derive a soft tint from the colour (used as icon bg)
    $trendUp   = $trend_direction === 'up';
    $trendColor = $trendUp ? '#16a34a' : '#dc2626';
    $trendBg    = $trendUp ? 'rgba(22,163,74,0.10)' : 'rgba(220,38,38,0.10)';
@endphp

<div style="
    background: #ffffff;
    border: 1px solid rgba(185,92,183,0.15);
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(106,15,112,0.06);
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    font-family: 'Inter', sans-serif;
">

    {{-- ── Icon bubble ── --}}
    @if($icon)
    <div style="
        width: 46px;
        height: 46px;
        border-radius: 10px;
        background: {{ $color }}18;
        border: 1px solid {{ $color }}22;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: {{ $color }};
    ">
        {!! $icon !!}
    </div>
    @endif

    {{-- ── Metric block ── --}}
    <div style="flex: 1; min-width: 0;">

        {{-- Large value --}}
        <div style="
            font-size: 26px;
            font-weight: 700;
            color: #1e0a2c;
            line-height: 1.1;
            letter-spacing: -0.5px;
        ">{{ $value }}</div>

        {{-- Label --}}
        <div style="
            font-size: 12.5px;
            font-weight: 400;
            color: #9b6aad;
            margin-top: 3px;
        ">{{ $label }}</div>

    </div>

    {{-- ── Trend badge ── --}}
    @if($trend)
    <div style="
        display: inline-flex;
        align-items: center;
        gap: 3px;
        padding: 3px 8px;
        border-radius: 20px;
        background: {{ $trendBg }};
        font-size: 11.5px;
        font-weight: 600;
        color: {{ $trendColor }};
        flex-shrink: 0;
        white-space: nowrap;
    ">
        {{-- Arrow icon --}}
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            @if($trendUp)
                <polyline points="18 15 12 9 6 15"/>
            @else
                <polyline points="6 9 12 15 18 9"/>
            @endif
        </svg>
        {{ $trend }}
    </div>
    @endif

</div>
