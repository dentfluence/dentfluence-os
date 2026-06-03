{{--
|==========================================================================
| Inventory Module — Horizontal Sub-Navigation
| Staff sees: Dashboard, Stock, Stock In, Stock Out, Purchase Orders
| Admin also sees: Product Master, Vendors, Expiry, Implants, Reusable Assets
| Reports are integrated into Dashboard (no separate tab)
|==========================================================================
--}}
@php
    $currentRoute = Route::currentRouteName();
    $isAdmin = auth()->user()?->role === 'admin';

    // ── Tabs visible to ALL staff ──────────────────────────────────────────
    $staffTabs = [
        ['route' => 'inventory.index',    'label' => 'Dashboard',       'icon' => 'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'],
        ['route' => 'inventory.items',    'label' => 'Stock',           'icon' => 'M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z'],
        ['route' => 'inventory.stock-in', 'label' => 'Stock In',        'icon' => 'M12 5v14M5 12l7-7 7 7'],
        ['route' => 'inventory.stock-out','label' => 'Stock Out',       'icon' => 'M12 19V5M5 12l7 7 7-7'],
        ['route' => 'inventory.purchase', 'label' => 'Purchase Orders', 'icon' => 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2'],
    ];

    // ── Extra tabs visible to ADMIN only ──────────────────────────────────
    $adminTabs = [
        ['route' => 'inventory.products',        'label' => 'Product Master',   'icon' => 'M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2zM16 3H8L6 7h12l-2-4z'],
        ['route' => 'inventory.vendors',         'label' => 'Vendors',          'icon' => 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 7a4 4 0 1 0 0-8 4 4 0 0 0 0 8'],
        ['route' => 'inventory.expiry',          'label' => 'Expiry',           'icon' => 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01'],
        ['route' => 'inventory.implants',        'label' => 'Implants',         'icon' => 'M12 2a5 5 0 0 1 5 5c0 5-5 11-5 11S7 12 7 7a5 5 0 0 1 5-5z'],
        ['route' => 'inventory.reusable-assets', 'label' => 'Reusable Assets',  'icon' => 'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z'],
    ];
@endphp

<div class="inv-subnav" style="
    background: #ffffff;
    border: 1px solid rgba(185,92,183,0.10);
    border-radius: 4px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    padding: 0 4px;
    gap: 2px;
    overflow-x: auto;
    scrollbar-width: none;
">

    {{-- ── Staff tabs (everyone sees these) ── --}}
    @foreach($staffTabs as $tab)
        @php $isActive = $currentRoute === $tab['route']; @endphp
        <a href="{{ route($tab['route']) }}"
           style="
               display: inline-flex; align-items: center; gap: 6px;
               padding: 10px 14px;
               font-family: 'DM Sans', sans-serif; font-size: 13px;
               font-weight: {{ $isActive ? '500' : '400' }};
               color: {{ $isActive ? '#6a0f70' : '#7a6884' }};
               text-decoration: none;
               border-bottom: 2px solid {{ $isActive ? '#6a0f70' : 'transparent' }};
               white-space: nowrap;
               transition: color 150ms, border-color 150ms;
               flex-shrink: 0;
           "
           onmouseover="if(!{{ $isActive ? 'true' : 'false' }})this.style.color='#4e0a53'"
           onmouseout="if(!{{ $isActive ? 'true' : 'false' }})this.style.color='#7a6884'"
        >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                 stroke="{{ $isActive ? '#6a0f70' : '#a090b0' }}"
                 stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
                 style="flex-shrink:0;">
                <path d="{{ $tab['icon'] }}"/>
            </svg>
            {{ $tab['label'] }}
        </a>
    @endforeach

    {{-- ── Admin-only tabs — shown only to admin role ── --}}
    @if($isAdmin)
        {{-- Thin divider to visually separate staff vs admin tabs --}}
        <div style="width:1px;height:20px;background:rgba(185,92,183,0.15);margin:0 4px;flex-shrink:0;"></div>

        @foreach($adminTabs as $tab)
            @php $isActive = $currentRoute === $tab['route']; @endphp
            <a href="{{ route($tab['route']) }}"
               style="
                   display: inline-flex; align-items: center; gap: 6px;
                   padding: 10px 12px;
                   font-family: 'DM Sans', sans-serif; font-size: 12px;
                   font-weight: {{ $isActive ? '500' : '400' }};
                   color: {{ $isActive ? '#6a0f70' : '#9a85aa' }};
                   text-decoration: none;
                   border-bottom: 2px solid {{ $isActive ? '#6a0f70' : 'transparent' }};
                   white-space: nowrap;
                   transition: color 150ms, border-color 150ms;
                   flex-shrink: 0;
               "
               onmouseover="if(!{{ $isActive ? 'true' : 'false' }})this.style.color='#4e0a53'"
               onmouseout="if(!{{ $isActive ? 'true' : 'false' }})this.style.color='#9a85aa'"
            >
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                     stroke="{{ $isActive ? '#6a0f70' : '#b8a8c8' }}"
                     stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
                     style="flex-shrink:0;">
                    <path d="{{ $tab['icon'] }}"/>
                </svg>
                {{ $tab['label'] }}
            </a>
        @endforeach

        {{-- Settings — far right (now unified under Settings module) ── --}}
        @php $isSettingsActive = false; @endphp
        <a href="{{ route('settings.index', ['tab' => 'inventory']) }}"
           style="
               display: inline-flex; align-items: center; gap: 6px;
               padding: 10px 14px; margin-left: auto;
               font-family: 'DM Sans', sans-serif; font-size: 12px;
               font-weight: {{ $isSettingsActive ? '500' : '400' }};
               color: {{ $isSettingsActive ? '#6a0f70' : '#9a85aa' }};
               text-decoration: none;
               border-bottom: 2px solid {{ $isSettingsActive ? '#6a0f70' : 'transparent' }};
               white-space: nowrap; flex-shrink: 0;
               transition: color 150ms, border-color 150ms;
           "
           onmouseover="if(!{{ $isSettingsActive ? 'true' : 'false' }})this.style.color='#4e0a53'"
           onmouseout="if(!{{ $isSettingsActive ? 'true' : 'false' }})this.style.color='#9a85aa'"
           title="Inventory Settings (Admin)"
        >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                 stroke="{{ $isSettingsActive ? '#6a0f70' : '#b8a8c8' }}"
                 stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
                 style="flex-shrink:0;">
                <circle cx="12" cy="12" r="3"/>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
            </svg>
            Settings
        </a>
    @endif

</div>
