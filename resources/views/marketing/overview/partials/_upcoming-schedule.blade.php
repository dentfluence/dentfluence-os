{{--
|==========================================================================
| Partial: _upcoming-schedule
| File: resources/views/marketing/overview/partials/_upcoming-schedule.blade.php
|
| Variables expected:
|   $upcomingSchedule — associative array keyed by date label, e.g.:
|       'Today - 14 Jun' => [
|           [ 'time' => '09:00 AM', 'platform' => 'instagram',
|             'title' => '...', 'content_type' => 'reel' ],
|           ...
|       ],
|       'Tomorrow - 15 Jun' => [ ... ],
|==========================================================================
--}}

<x-marketing.marketing-card title="Upcoming Schedule">

    {{-- ── Header action: "View Calendar →" ── --}}
    <x-slot:actions>
        <a href="{{ route('marketing.calendar') }}" style="
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
            View Calendar
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="9 18 15 12 9 6"/>
            </svg>
        </a>
    </x-slot:actions>

    {{-- ── Date groups ── --}}
    @foreach($upcomingSchedule as $dateLabel => $posts)

    {{-- Date group header --}}
    <div style="
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
        {{ !$loop->first ? 'margin-top: 18px;' : '' }}
    ">
        <span style="
            font-family: 'Inter', sans-serif;
            font-size: 11.5px;
            font-weight: 700;
            color: #6a0f70;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            white-space: nowrap;
        ">{{ $dateLabel }}</span>
        <div style="
            flex: 1;
            height: 1px;
            background: rgba(185,92,183,0.12);
        "></div>
        <span style="
            font-family: 'Inter', sans-serif;
            font-size: 11px;
            font-weight: 500;
            color: #9b6aad;
        ">{{ count($posts) }} post{{ count($posts) !== 1 ? 's' : '' }}</span>
    </div>

    {{-- Post rows for this date --}}
    @foreach($posts as $post)
    <div style="
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 10px 10px 8px;
        border-radius: 8px;
        margin-bottom: 6px;
        background: {{ $loop->even ? 'rgba(185,92,183,0.03)' : 'transparent' }};
        border: 1px solid {{ $loop->even ? 'rgba(185,92,183,0.07)' : 'transparent' }};
        font-family: 'Inter', sans-serif;
        transition: background 150ms;
    "
    onmouseover="this.style.background='rgba(106,15,112,0.04)'; this.style.borderColor='rgba(185,92,183,0.12)'"
    onmouseout="this.style.background='{{ $loop->even ? 'rgba(185,92,183,0.03)' : 'transparent' }}'; this.style.borderColor='{{ $loop->even ? 'rgba(185,92,183,0.07)' : 'transparent' }}'"
    >

        {{-- Time column --}}
        <div style="
            width: 72px;
            flex-shrink: 0;
            font-size: 12px;
            font-weight: 600;
            color: #5a4868;
            line-height: 1.3;
        ">{{ $post['time'] }}</div>

        {{-- Vertical divider --}}
        <div style="
            width: 1px;
            height: 32px;
            background: rgba(185,92,183,0.15);
            flex-shrink: 0;
        "></div>

        {{-- Platform badge --}}
        <div style="flex-shrink: 0;">
            <x-marketing.platform-badge :platform="$post['platform']" />
        </div>

        {{-- Post title --}}
        <div style="
            flex: 1;
            min-width: 0;
            font-size: 13px;
            font-weight: 500;
            color: #1e0a2c;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        ">{{ $post['title'] }}</div>

        {{-- Content type badge (right) --}}
        <div style="flex-shrink: 0;">
            <x-marketing.content-type-badge :type="$post['content_type']" />
        </div>

    </div>
    @endforeach

    @endforeach

</x-marketing.marketing-card>
