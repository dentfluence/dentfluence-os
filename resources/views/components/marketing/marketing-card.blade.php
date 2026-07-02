{{--
|==========================================================================
| Component: marketing-card
| Usage: <x-marketing.marketing-card title="..." subtitle="...">
|            <x-slot:actions> ... </x-slot:actions>
|            body content here
|        </x-marketing.marketing-card>
|
| Props:
|   title    (optional) — card header title
|   subtitle (optional) — muted text below title
|   actions  (slot, optional) — buttons / links rendered right-side of header
|==========================================================================
--}}
@props([
    'title'    => null,
    'subtitle' => null,
])

<div style="
    background: #ffffff;
    border: 1px solid rgba(185,92,183,0.15);
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(106,15,112,0.06);
    overflow: hidden;
    font-family: 'Inter', sans-serif;
">

    {{-- ── Header row (only if title or actions slot provided) ── --}}
    @if($title || isset($actions))
    <div style="
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 20px;
        border-bottom: 1px solid rgba(185,92,183,0.10);
        background: #fdf8fe;
    ">
        {{-- Title + Subtitle --}}
        @if($title)
        <div>
            <div style="
                font-size: 14px;
                font-weight: 600;
                color: #1e0a2c;
                line-height: 1.3;
            ">{{ $title }}</div>
            @if($subtitle)
            <div style="
                font-size: 12px;
                font-weight: 400;
                color: #9b6aad;
                margin-top: 2px;
                line-height: 1.3;
            ">{{ $subtitle }}</div>
            @endif
        </div>
        @endif

        {{-- Actions slot --}}
        @if(isset($actions))
        <div style="
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        ">
            {{ $actions }}
        </div>
        @endif
    </div>
    @endif

    {{-- ── Body ── --}}
    <div style="padding: 20px;">
        {{ $slot }}
    </div>

</div>
