{{--
|==========================================================================
| Dentfluence OS — Relationship (PRE) Module Layout
| File: resources/views/relationship/layouts/app.blade.php
|
| Mirrors resources/views/marketing/layouts/app.blade.php exactly — same
| sticky full-width sub-nav pattern, same inline-style tab markup, same
| active-state convention (request()->routeIs('relationship.xxx*')).
| Built so the Relationship module has the same top tab-strip UX as
| Marketing, instead of the old "3 pill buttons" header row.
|
| Architecture:
|   Extends layouts.app (inherits sidebar + topbar + shell).
|   Injects a full-width secondary tab nav into the content slot.
|   Child views extend THIS layout and fill @section('relationship-content').
|
| Usage in child views:
|   @extends('relationship.layouts.app')
|   @section('page-title', 'Relationships')
|   @section('relationship-content') ... @endsection
|==========================================================================
--}}
@extends('layouts.app')

@section('page-title', 'Relationships — ' . ($relationshipPageTitle ?? 'Dashboard'))

@section('content')

{{-- ══════════════════════════════════════════════════════════════
     RELATIONSHIP SUB-NAV — same structure/spacing/colours as
     marketing/layouts/app.blade.php's #mkt-subnav.
═══════════════════════════════════════════════════════════════ --}}
<div id="rel-subnav" style="
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
            $relTabs = [
                ['route' => 'relationship.dashboard',     'label' => 'Dashboard',        'icon' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>'],
                ['route' => 'relationship.list',          'label' => 'All Relationships','icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
                ['route' => 'relationship.today',         'label' => "Today's Actions",  'icon' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'],
                ['route' => 'relationship.analytics',     'label' => 'Analytics',        'icon' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
                ['route' => 'relationship.pipeline',      'label' => 'Pipeline',         'icon' => '<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/>'],
                ['route' => 'relationship.opportunities', 'label' => 'Opportunities',    'icon' => '<path d="M12 2l3 7h7l-5.5 4.5L18.5 21 12 16.5 5.5 21 7.5 13.5 2 9h7z"/>'],
                ['route' => 'relationship.recalls',       'label' => 'Recalls',          'icon' => '<path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>'],
                ['route' => 'relationship.reception',     'label' => 'Reception',        'icon' => '<path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>'],
                // Kept last — module-scoped settings so PRE can be sold/run standalone
                // without depending on the global Settings module.
                ['route' => 'relationship.settings',      'label' => 'Settings',         'icon' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>'],
            ];
        @endphp

        @foreach($relTabs as $tab)
        @php $isActive = request()->routeIs($tab['route'] . '*'); @endphp
        <a
            href="{{ route($tab['route']) }}"
            title="{{ $tab['label'] }}"
            style="
                display: inline-flex; align-items: center; gap: 6px;
                padding: 0 14px; height: 52px;
                font-family: 'DM Sans', sans-serif; font-size: 13px;
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
    </div>{{-- /tab strip --}}
</div>{{-- /#rel-subnav --}}

{{-- ══════════════════════════════════════════════════════════════
     RELATIONSHIP CONTENT SLOT — child views fill this.
═══════════════════════════════════════════════════════════════ --}}
@yield('relationship-content')

@endsection
