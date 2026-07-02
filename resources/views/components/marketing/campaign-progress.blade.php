{{--
|==========================================================================
| Component: campaign-progress
| Usage: <x-marketing.campaign-progress :percentage="72" />
|
| Props:
|   percentage — integer 0–100
|==========================================================================
--}}
@props(['percentage' => 0])

@php
    $pct        = max(0, min(100, (int) $percentage));
    $radius     = 28;          // SVG circle radius
    $stroke     = 5;           // ring thickness
    $cx         = 36;          // center x/y of 72×72 viewBox
    $circumference = 2 * M_PI * $radius;
    $dashOffset    = $circumference - ($pct / 100) * $circumference;
@endphp

<div style="
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-family: 'Inter', sans-serif;
    position: relative;
    width: 72px;
    height: 72px;
">
    <svg width="72" height="72" viewBox="0 0 72 72" style="transform: rotate(-90deg);">
        {{-- Background track --}}
        <circle
            cx="{{ $cx }}"
            cy="{{ $cx }}"
            r="{{ $radius }}"
            fill="none"
            stroke="rgba(185,92,183,0.15)"
            stroke-width="{{ $stroke }}"
        />
        {{-- Progress ring --}}
        <circle
            cx="{{ $cx }}"
            cy="{{ $cx }}"
            r="{{ $radius }}"
            fill="none"
            stroke="{{ $pct >= 100 ? '#16a34a' : ($pct >= 50 ? '#6a0f70' : '#b95cb7') }}"
            stroke-width="{{ $stroke }}"
            stroke-linecap="round"
            stroke-dasharray="{{ $circumference }}"
            stroke-dashoffset="{{ $dashOffset }}"
            style="transition: stroke-dashoffset 0.5s ease;"
        />
    </svg>

    {{-- Centre label --}}
    <div style="
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
        line-height: 1;
    ">
        <span style="
            font-size: 14px;
            font-weight: 700;
            color: #1e0a2c;
            display: block;
        ">{{ $pct }}<span style="font-size:9px;font-weight:500;">%</span></span>
    </div>
</div>
