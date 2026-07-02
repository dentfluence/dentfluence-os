{{--
|==========================================================================
| Partial: _quick-actions
| File: resources/views/marketing/overview/partials/_quick-actions.blade.php
|
| 6 quick-action icon buttons spanning the full width.
| Phase 2.1-B — Marketing Overview Dashboard
|==========================================================================
--}}

@php
$actions = [
    [
        'label'  => 'Universal Publish',
        'route'  => 'marketing.publish',
        'color'  => '#6a0f70',
        'bg'     => 'rgba(106,15,112,0.10)',
        'icon'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>',
    ],
    [
        'label'  => 'Create Blog',
        'route'  => 'marketing.library',
        'color'  => '#2563eb',
        'bg'     => 'rgba(37,99,235,0.10)',
        'icon'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
    ],
    [
        'label'  => 'Create Reel',
        'route'  => 'marketing.library',
        'color'  => '#e1306c',
        'bg'     => 'rgba(225,48,108,0.10)',
        'icon'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>',
    ],
    [
        'label'  => 'Add Idea',
        'route'  => 'marketing.brainstorm',
        'color'  => '#d97706',
        'bg'     => 'rgba(217,119,6,0.10)',
        'icon'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
    ],
    [
        'label'  => 'Create Campaign',
        'route'  => 'marketing.campaigns.index',
        'color'  => '#059669',
        'bg'     => 'rgba(5,150,105,0.10)',
        'icon'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
    ],
    [
        'label'  => 'Import Sheet',
        'route'  => 'marketing.library',
        'color'  => '#0891b2',
        'bg'     => 'rgba(8,145,178,0.10)',
        'icon'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
    ],
];
@endphp

{{-- ── Quick Actions strip (no card wrapper — sits between rows) ── --}}
<div style="
    background: #ffffff;
    border: 1px solid rgba(185,92,183,0.13);
    border-radius: 12px;
    box-shadow: 0 1px 4px rgba(106,15,112,0.05);
    padding: 16px 20px;
    margin-top: 20px;
">
    {{-- Label --}}
    <div style="
        font-family: 'Inter', sans-serif;
        font-size: 11px;
        font-weight: 600;
        color: #9b6aad;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 14px;
    ">Quick Actions</div>

    {{-- Action buttons row --}}
    <div style="
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 12px;
    ">
        @foreach($actions as $action)
        <a
            href="{{ route($action['route']) }}"
            style="
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 8px;
                text-decoration: none;
                padding: 4px 0;
            "
            onmouseover="this.querySelector('.qa-icon').style.transform='translateY(-2px)'; this.querySelector('.qa-icon').style.boxShadow='0 4px 12px {{ $action['color'] }}30';"
            onmouseout="this.querySelector('.qa-icon').style.transform='translateY(0)'; this.querySelector('.qa-icon').style.boxShadow='none';"
        >
            {{-- Rounded icon bubble --}}
            <div class="qa-icon" style="
                width: 48px;
                height: 48px;
                border-radius: 14px;
                background: {{ $action['bg'] }};
                border: 1.5px solid {{ $action['color'] }}25;
                display: flex;
                align-items: center;
                justify-content: center;
                color: {{ $action['color'] }};
                transition: transform 150ms ease, box-shadow 150ms ease;
                flex-shrink: 0;
            ">
                {!! $action['icon'] !!}
            </div>

            {{-- Label --}}
            <span style="
                font-family: 'Inter', sans-serif;
                font-size: 11.5px;
                font-weight: 500;
                color: #3d2a4a;
                text-align: center;
                line-height: 1.3;
            ">{{ $action['label'] }}</span>
        </a>
        @endforeach
    </div>
</div>
