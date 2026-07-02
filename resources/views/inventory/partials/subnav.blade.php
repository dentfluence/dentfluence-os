{{--
|==========================================================================
| Inventory OS — Horizontal Sub-Navigation  (Phase 1 upgrade)
|
| New tab structure:
|   Dashboard · Inventory · Orders · Alerts · Implants · Assets · Reports · Settings
|
| Role-based visibility:
|   assistant / front_desk  → Dashboard, Inventory, Orders, Alerts
|   manager / accounts      → Dashboard, Inventory, Orders, Alerts, Implants, Assets, Reports
|   doctor / dentist roles  → Dashboard only (exec view)
|   admin                   → All 8 tabs
|
| Terminology (UI label → backend):
|   Inventory  = products/catalogue page
|   Orders     = purchase orders
|   Alerts     = new hub (critical stock, expiry, dead stock, suggestions)
|   Assets     = reusable assets
|==========================================================================
--}}
@php
    $currentRoute = Route::currentRouteName();
    $user         = auth()->user();
    $userRole     = $user?->role ?? '';

    // ── Role tier helpers ─────────────────────────────────────────────────
    $isAdmin    = $user?->isAdmin() ?? false;
    $isDoctor   = in_array($userRole, \App\Models\User::DOCTOR_ROLES);
    $isManager  = in_array($userRole, ['manager', 'accounts']);
    $isAssistant = in_array($userRole, ['assistant', 'front_desk']);

    // Dentist/Owner sees Dashboard only; everyone else gets at least 4 tabs
    $showOperational = $isAdmin || $isManager || $isAssistant;
    $showExtended    = $isAdmin || $isManager;  // Implants, Assets, Reports
    $showSettings    = $isAdmin;

    // ── Active detection helpers ──────────────────────────────────────────
    $r = $currentRoute ?? '';

    // ── Full tab definitions (all 8) ─────────────────────────────────────
    // Each entry: route, label, icon path(s), active-check closure flag, visible flag
    $allTabs = [
        [
            'route'   => 'inventory.index',
            'label'   => 'Dashboard',
            'icon'    => 'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z',
            'active'  => in_array($r, ['inventory.index', 'inventory.dashboard']),
            'visible' => true,  // everyone
        ],
        [
            'route'   => 'inventory.products',
            'label'   => 'Inventory',
            'icon'    => 'M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z',
            // also active on stock-in / stock-out / stock-count (sub-actions of the Inventory tab)
            'active'  => in_array($r, ['inventory.products', 'inventory.items', 'inventory.stock-in', 'inventory.stock-out', 'inventory.stock-check'])
                         || str_starts_with($r, 'inventory.stock-count'),
            'visible' => $showOperational,
        ],
        [
            'route'   => 'inventory.purchase',
            'label'   => 'Orders',
            'icon'    => 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2',
            'active'  => in_array($r, ['inventory.purchase', 'inventory.vendors']),
            'visible' => $showOperational,
        ],
        [
            'route'   => 'inventory.alerts',
            'label'   => 'Alerts',
            'icon'    => 'M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0',
            'active'  => $r === 'inventory.alerts',
            'visible' => $showOperational,
        ],
        [
            'route'   => 'inventory.implants',
            'label'   => 'Implants',
            'icon'    => 'M12 2a5 5 0 0 1 5 5c0 5-5 11-5 11S7 12 7 7a5 5 0 0 1 5-5z',
            'active'  => $r === 'inventory.implants',
            'visible' => $showExtended,
        ],
        [
            'route'   => 'inventory.reusable-assets',
            'label'   => 'Assets',
            'icon'    => 'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z',
            'active'  => $r === 'inventory.reusable-assets',
            'visible' => $showExtended,
        ],
        [
            'route'   => 'inventory.reports',
            'label'   => 'Reports',
            'icon'    => 'M9 19v-6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2zm0 0V9a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v10m-6 0a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2m0 0V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2z',
            'active'  => in_array($r, ['inventory.reports', 'inventory.expiry']),
            'visible' => $showExtended,
        ],
    ];

    // Visible tabs only
    $visibleTabs = array_filter($allTabs, fn($t) => $t['visible']);
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
    -webkit-overflow-scrolling: touch;
">

    @foreach($visibleTabs as $tab)
        @php $isActive = $tab['active']; @endphp
        <a href="{{ route($tab['route']) }}"
           style="
               display: inline-flex; align-items: center; gap: 6px;
               padding: 10px 14px;
               font-family: 'Inter', sans-serif; font-size: 13px;
               font-weight: {{ $isActive ? '600' : '400' }};
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

    {{-- ── Settings — admin only, pinned to the right ── --}}
    @if($showSettings)
        <div style="width:1px;height:20px;background:rgba(185,92,183,0.15);margin:0 4px 0 auto;flex-shrink:0;"></div>
        @php $isSettingsActive = str_starts_with($r, 'settings.') && request()->get('tab') === 'inventory'; @endphp
        <a href="{{ route('settings.index', ['tab' => 'inventory']) }}"
           style="
               display: inline-flex; align-items: center; gap: 6px;
               padding: 10px 14px;
               font-family: 'Inter', sans-serif; font-size: 13px;
               font-weight: {{ $isSettingsActive ? '600' : '400' }};
               color: {{ $isSettingsActive ? '#6a0f70' : '#7a6884' }};
               text-decoration: none;
               border-bottom: 2px solid {{ $isSettingsActive ? '#6a0f70' : 'transparent' }};
               white-space: nowrap; flex-shrink: 0;
               transition: color 150ms, border-color 150ms;
           "
           onmouseover="if(!{{ $isSettingsActive ? 'true' : 'false' }})this.style.color='#4e0a53'"
           onmouseout="if(!{{ $isSettingsActive ? 'true' : 'false' }})this.style.color='#7a6884'"
           title="Inventory Settings"
        >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                 stroke="{{ $isSettingsActive ? '#6a0f70' : '#a090b0' }}"
                 stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
                 style="flex-shrink:0;">
                <circle cx="12" cy="12" r="3"/>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
            </svg>
            Settings
        </a>
    @endif

</div>
