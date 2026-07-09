{{--
|==========================================================================
| Component: Marketing Sub-nav
| File: resources/views/components/marketing/subnav.blade.php
|
| Extracted from marketing/layouts/app.blade.php 2026-07-09 for reuse.
| The Reviews tab used to link out to /communication/reviews (Reviews'
| data/actions are owned by Communication/PRE — see ReviewService). An
| earlier fix bolted this bar onto that page via a ?from=marketing query
| param, but Sumit flagged that it still felt like leaving Marketing since
| the URL and page shell stayed Communication's. Reviews now has a native
| page at /marketing/reviews (App\Http\Controllers\Marketing\ReviewsController)
| that reuses the same ReviewService/content partial without duplicating
| logic, so this bar renders on every real Marketing page and this
| component no longer needs to be embedded outside the module.
|
| Usage in Marketing pages:
|   x-marketing.subnav
|
| The with-user-block prop exists in case a future host page already has
| its own topbar search/avatar and wants to hide the duplicate:
|   x-marketing.subnav with :with-user-block="false"
|==========================================================================
--}}

@props(['withUserBlock' => true])

<div id="mkt-subnav" style="
    margin: -28px -32px 28px;
    background: #ffffff;
    border-bottom: 1px solid rgba(185,92,183,0.13);
    box-shadow: 0 1px 0 0 rgba(185,92,183,0.06);
    position: sticky;
    top: 0;
    z-index: 20;
">
    <div style="
        display: flex;
        align-items: stretch;
        gap: 0;
        padding: 0 32px;
        overflow-x: auto;
        scrollbar-width: none;
    ">
        @php
            $mktTabs = [
                ['route' => 'marketing.overview',        'label' => 'Dashboard', 'active' => 'marketing.overview*',    'icon' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>'],
                ['route' => 'marketing.publish',         'label' => 'Content',   'active' => 'marketing.publish*',     'icon' => '<path d="M12 19V5M5 12l7-7 7 7"/>'],
                ['route' => 'marketing.calendar',        'label' => 'Calendar',  'active' => 'marketing.calendar*',    'icon' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
                ['route' => 'marketing.reviews',         'label' => 'Reviews',   'active' => 'marketing.reviews*',     'icon' => '<path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>'],
                ['route' => 'marketing.analytics',       'label' => 'Analytics', 'active' => 'marketing.analytics*',   'icon' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
                ['route' => 'marketing.settings',        'label' => 'Settings',  'active' => 'marketing.settings*',    'icon' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>'],
            ];
        @endphp

        @foreach ($mktTabs as $tab)
            @php $isActive = request()->routeIs($tab['active']); @endphp
            <a
                href="{{ route($tab['route'], $tab['params'] ?? []) }}"
                title="{{ $tab['label'] }}"
                style="
                    display: inline-flex; align-items: center; gap: 6px;
                    padding: 0 14px; height: 52px;
                    font-family: 'Inter', sans-serif; font-size: 13px;
                    font-weight: {{ $isActive ? '600' : '400' }};
                    color: {{ $isActive ? '#6a0f70' : '#5a4868' }};
                    text-decoration: none; white-space: nowrap;
                    border-bottom: 2px solid {{ $isActive ? '#6a0f70' : 'transparent' }};
                    transition: color 150ms, border-color 150ms; flex-shrink: 0;
                "
                onmouseover="if(!this.style.borderBottomColor.includes('6a0f70'))this.style.color='#1e0a2c'"
                onmouseout="if(!this.style.borderBottomColor.includes('6a0f70'))this.style.color='#5a4868'"
            >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    {!! $tab['icon'] !!}
                </svg>
                {{ $tab['label'] }}
            </a>
        @endforeach

        <div style="flex: 1; min-width: 16px;"></div>

        @if ($withUserBlock)
        {{-- Search --}}
        <div style="display: flex; align-items: center; align-self: center; gap: 8px; margin-right: 12px;">
            <div style="
                display: flex; align-items: center; gap: 7px;
                background: #f9f3fa; border: 1px solid rgba(185,92,183,0.18);
                border-radius: 3px; padding: 0 10px; height: 34px; width: 220px;
            ">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="search" placeholder="Search patient, content, campaign..." style="
                    border: none; background: transparent; outline: none;
                    font-family: 'Inter', sans-serif; font-size: 12.5px; color: #1e0a2c; width: 100%;
                " aria-label="Search marketing">
            </div>
        </div>

        @auth
        <div style="
            display: flex; align-items: center; gap: 9px; align-self: center;
            padding-left: 12px; border-left: 1px solid rgba(185,92,183,0.12);
            height: 36px; flex-shrink: 0;
        ">
            <div style="
                width: 30px; height: 30px; border-radius: 50%;
                background: linear-gradient(135deg, #6a0f70 0%, #b95cb7 100%);
                display: flex; align-items: center; justify-content: center;
                font-family: 'Inter', sans-serif; font-size: 11px; font-weight: 600;
                color: #fff; flex-shrink: 0; letter-spacing: 0.02em;
            ">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}{{ strtoupper(substr(strrchr(auth()->user()->name, ' '), 1, 1)) }}</div>
            <div style="line-height: 1.2;">
                <div style="font-family: 'Inter', sans-serif; font-size: 12.5px; font-weight: 500; color: #1e0a2c; white-space: nowrap;">{{ auth()->user()->name }}</div>
                <div style="font-family: 'Inter', sans-serif; font-size: 11px; font-weight: 300; color: #9b6aad; white-space: nowrap; text-transform: capitalize;">{{ auth()->user()->role ?? 'Staff' }}</div>
            </div>
        </div>
        @endauth
        @endif

    </div>{{-- /tab strip --}}
</div>{{-- /#mkt-subnav --}}
