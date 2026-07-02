{{--
|==========================================================================
| Partial: _attention-needed
| File: resources/views/marketing/overview/partials/_attention-needed.blade.php
|
| Alerts that need the clinic's attention — missed posts, pending approvals,
| idle ideas, ending campaigns.
| Phase 2.1-B — Marketing Overview Dashboard
|==========================================================================
--}}

@php
$alerts = [
    [
        'emoji'  => '',
        'text'   => '2 missed posts this week',
        'color'  => '#dc2626',
        'bg'     => '#fef2f2',
        'border' => '#fecaca',
        'route'  => 'marketing.calendar',
    ],
    [
        'emoji'  => '',
        'text'   => '1 blog pending approval',
        'color'  => '#d97706',
        'bg'     => '#fffbeb',
        'border' => '#fde68a',
        'route'  => 'marketing.library',
    ],
    [
        'emoji'  => '',
        'text'   => '4 ideas waiting to be converted',
        'color'  => '#7c3aed',
        'bg'     => '#f5f3ff',
        'border' => '#ddd6fe',
        'route'  => 'marketing.brainstorm',
    ],
    [
        'emoji'  => '',
        'text'   => '1 campaign ending soon',
        'color'  => '#0891b2',
        'bg'     => '#ecfeff',
        'border' => '#a5f3fc',
        'route'  => 'marketing.campaigns.index',
    ],
];
@endphp

<x-marketing.marketing-card title="Attention Needed">

    {{-- ── Header action: "View All →" ── --}}
    <x-slot:actions>
        <a href="{{ route('marketing.overview') }}" style="
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-family: 'Inter', sans-serif;
            font-size: 12.5px;
            font-weight: 500;
            color: #6a0f70;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 6px;
            border: 1px solid rgba(106,15,112,0.18);
            background: rgba(106,15,112,0.04);
            transition: background 150ms;
        "
        onmouseover="this.style.background='rgba(106,15,112,0.09)'"
        onmouseout="this.style.background='rgba(106,15,112,0.04)'"
        >
            View All
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="9 18 15 12 9 6"/>
            </svg>
        </a>
    </x-slot:actions>

    {{-- ── Alert rows ── --}}
    <div style="display: flex; flex-direction: column; gap: 10px; padding: 4px 0;">
        @foreach($alerts as $alert)
        <a href="{{ route($alert['route']) }}" style="
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 10px;
            background: {{ $alert['bg'] }};
            border: 1px solid {{ $alert['border'] }};
            text-decoration: none;
            transition: opacity 150ms;
        "
        onmouseover="this.style.opacity='0.8'"
        onmouseout="this.style.opacity='1'"
        >
            {{-- Emoji icon --}}
            <span style="font-size: 18px; flex-shrink: 0; line-height: 1;">{{ $alert['emoji'] }}</span>

            {{-- Alert text --}}
            <span style="
                font-family: 'Inter', sans-serif;
                font-size: 13px;
                font-weight: 500;
                color: {{ $alert['color'] }};
                line-height: 1.3;
            ">{{ $alert['text'] }}</span>

            {{-- Arrow --}}
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="{{ $alert['color'] }}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-left: auto; flex-shrink: 0; opacity: 0.6;">
                <polyline points="9 18 15 12 9 6"/>
            </svg>
        </a>
        @endforeach
    </div>

</x-marketing.marketing-card>
