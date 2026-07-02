{{--
|==========================================================================
| Partial: _platform-status
| File: resources/views/marketing/overview/partials/_platform-status.blade.php
|
| Displays the connected/disconnected state of each marketing platform.
| Phase 2.1-B — Marketing Overview Dashboard
|==========================================================================
--}}

<x-marketing.marketing-card title="Platform Status">

    {{-- ── Header action: "Manage →" ── --}}
    <x-slot:actions>
        <a href="{{ route('marketing.integrations') }}" style="
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
            Manage
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="9 18 15 12 9 6"/>
            </svg>
        </a>
    </x-slot:actions>

    {{-- ── Platform rows ── --}}
    @php
    $platforms = [
        [
            'name'      => 'Instagram',
            'handle'    => '@tulipdental_clinic',
            'connected' => true,
            'color'     => '#e1306c',
            'icon'      => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>',
        ],
        [
            'name'      => 'Facebook',
            'handle'    => 'Tulip Dental Clinic',
            'connected' => true,
            'color'     => '#1877f2',
            'icon'      => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>',
        ],
        [
            'name'      => 'Google Business',
            'handle'    => 'Verified Profile',
            'connected' => true,
            'color'     => '#4285f4',
            'icon'      => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>',
        ],
        [
            'name'      => 'Website / WordPress',
            'handle'    => 'tulipdental.com',
            'connected' => false,
            'color'     => '#21759b',
            'icon'      => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 010 20M12 2a15.3 15.3 0 000 20"/></svg>',
        ],
        [
            'name'      => 'WhatsApp Business',
            'handle'    => '+91 98765 43210',
            'connected' => true,
            'color'     => '#25d366',
            'icon'      => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>',
        ],
    ];
    @endphp

    @foreach($platforms as $platform)
    <div style="
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 4px;
        {{ !$loop->last ? 'border-bottom: 1px solid rgba(185,92,183,0.07);' : '' }}
        font-family: 'Inter', sans-serif;
    ">
        {{-- Platform icon bubble --}}
        <div style="
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: {{ $platform['color'] }}18;
            border: 1px solid {{ $platform['color'] }}30;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: {{ $platform['color'] }};
        ">
            {!! $platform['icon'] !!}
        </div>

        {{-- Platform name + handle --}}
        <div style="flex: 1; min-width: 0;">
            <div style="
                font-size: 13.5px;
                font-weight: 600;
                color: #1e0a2c;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            ">{{ $platform['name'] }}</div>
            <div style="
                font-size: 11.5px;
                color: #9b6aad;
                margin-top: 1px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            ">{{ $platform['handle'] }}</div>
        </div>

        {{-- Connected / Not connected badge --}}
        @if($platform['connected'])
            <div style="
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 4px 10px;
                border-radius: 20px;
                background: #dcfce7;
                border: 1px solid #bbf7d0;
                font-size: 11.5px;
                font-weight: 600;
                color: #16a34a;
                flex-shrink: 0;
            ">
                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Connected
            </div>
        @else
            <div style="
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 4px 10px;
                border-radius: 20px;
                background: #fef2f2;
                border: 1px solid #fecaca;
                font-size: 11.5px;
                font-weight: 600;
                color: #dc2626;
                flex-shrink: 0;
            ">
                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
                Not Connected
            </div>
        @endif

    </div>
    @endforeach

</x-marketing.marketing-card>
