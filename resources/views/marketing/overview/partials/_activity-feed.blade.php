{{--
|==========================================================================
| Partial: _activity-feed
| File: resources/views/marketing/overview/partials/_activity-feed.blade.php
|
| Recent marketing activity list with icon, text, and time-ago.
| Phase 2.1-B — Marketing Overview Dashboard
|==========================================================================
--}}

@php
/**
 * Activity type → icon + color mapping.
 * type: publish | blog | draft | idea | schedule
 */
$activities = [
    [
        'type'    => 'publish',
        'text'    => 'Implant Reel published on Instagram',
        'time'    => '2h ago',
        'color'   => '#e1306c',
        'bg'      => 'rgba(225,48,108,0.10)',
        'icon'    => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>',
    ],
    [
        'type'    => 'blog',
        'text'    => 'Blog: RCT Guide scheduled',
        'time'    => '3h ago',
        'color'   => '#2563eb',
        'bg'      => 'rgba(37,99,235,0.10)',
        'icon'    => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
    ],
    [
        'type'    => 'publish',
        'text'    => 'Monsoon Offer post published on GBP',
        'time'    => '5h ago',
        'color'   => '#059669',
        'bg'      => 'rgba(5,150,105,0.10)',
        'icon'    => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
    ],
    [
        'type'    => 'draft',
        'text'    => 'Before/After IG post saved as draft',
        'time'    => '1d ago',
        'color'   => '#9b6aad',
        'bg'      => 'rgba(185,92,183,0.10)',
        'icon'    => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
    ],
    [
        'type'    => 'idea',
        'text'    => 'New idea added: Aligner Journey',
        'time'    => '1d ago',
        'color'   => '#d97706',
        'bg'      => 'rgba(217,119,6,0.10)',
        'icon'    => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>',
    ],
];
@endphp

<x-marketing.marketing-card title="Recent Activity">

    {{-- ── Header action: "View All →" ── --}}
    <x-slot:actions>
        <a href="{{ route('marketing.library') }}" style="
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

    {{-- ── Activity rows ── --}}
    @foreach($activities as $activity)
    <div style="
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 11px 4px;
        {{ !$loop->last ? 'border-bottom: 1px solid rgba(185,92,183,0.07);' : '' }}
        font-family: 'Inter', sans-serif;
    ">
        {{-- Type icon bubble --}}
        <div style="
            width: 32px;
            height: 32px;
            border-radius: 9px;
            background: {{ $activity['bg'] }};
            border: 1px solid {{ $activity['color'] }}28;
            display: flex;
            align-items: center;
            justify-content: center;
            color: {{ $activity['color'] }};
            flex-shrink: 0;
        ">
            {!! $activity['icon'] !!}
        </div>

        {{-- Activity text --}}
        <div style="flex: 1; min-width: 0;">
            <div style="
                font-size: 13px;
                font-weight: 500;
                color: #1e0a2c;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            ">{{ $activity['text'] }}</div>
        </div>

        {{-- Time ago --}}
        <div style="
            font-size: 11.5px;
            color: #9b6aad;
            white-space: nowrap;
            flex-shrink: 0;
        ">{{ $activity['time'] }}</div>

    </div>
    @endforeach

</x-marketing.marketing-card>
