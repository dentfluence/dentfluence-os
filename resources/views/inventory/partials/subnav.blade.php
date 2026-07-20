{{--
|==========================================================================
| Inventory OS — Horizontal Sub-Navigation
|
| Primary tabs (daily work):  Dashboard · Items · Purchase Orders ·
|                             Stock Movement · Stock Count
| Secondary (in "More" menu):  Alerts · Received Stock · Reports ·
|                             Implants · Assets · Inventory Settings
|
| Role visibility:
|   assistant / front_desk  → daily primary tabs + Alerts/Received Stock
|   manager / accounts      → + Reports / Implants / Assets
|   doctor / dentist roles  → Dashboard only (exec view)
|   admin                   → everything + Settings
|==========================================================================
--}}
@php
    $currentRoute = Route::currentRouteName();
    $user         = auth()->user();
    $userRole     = $user?->role ?? '';

    $isAdmin     = $user?->isAdmin() ?? false;
    $isManager   = in_array($userRole, ['manager', 'accounts']);
    $isAssistant = in_array($userRole, ['assistant', 'front_desk']);

    $showOperational = $isAdmin || $isManager || $isAssistant;  // daily tabs
    $showExtended    = $isAdmin || $isManager;                  // Reports/Implants/Assets
    $showSettings    = $isAdmin;

    $r = $currentRoute ?? '';

    // ── Primary tabs (always shown in the bar) ──
    $primaryTabs = [
        [
            'route'   => 'inventory.index',
            'label'   => 'Dashboard',
            'icon'    => 'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z',
            'active'  => in_array($r, ['inventory.index', 'inventory.dashboard']),
            'visible' => true,
        ],
        [
            'route'   => 'inventory.products',
            'label'   => 'Items',
            'icon'    => 'M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z',
            'active'  => in_array($r, ['inventory.products', 'inventory.items', 'inventory.stock-in', 'inventory.stock-out', 'inventory.stock-check']),
            'visible' => $showOperational,
        ],
        [
            'route'   => 'inventory.purchase',
            'label'   => 'Purchase Orders',
            'icon'    => 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2',
            'active'  => in_array($r, ['inventory.purchase', 'inventory.vendors']),
            'visible' => $showOperational,
        ],
        [
            'route'   => 'inventory.stock-movement',
            'label'   => 'Stock Movement',
            'icon'    => 'M3 12h18M3 6h18M3 18h18',
            'active'  => $r === 'inventory.stock-movement',
            'visible' => $showOperational,
        ],
        [
            'route'   => 'inventory.stock-count.index',
            'label'   => 'Stock Count',
            'icon'    => 'M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11',
            'active'  => str_starts_with($r, 'inventory.stock-count'),
            'visible' => $showOperational,
        ],
        [
            'route'   => 'inventory.implants',
            'label'   => 'Implants',
            'icon'    => 'M12 2a5 5 0 0 1 5 5c0 5-5 11-5 11S7 12 7 7a5 5 0 0 1 5-5z',
            'active'  => $r === 'inventory.implants',
            'visible' => $showExtended,
        ],
    ];

    // ── Secondary tabs (inside the "More" menu) ──
    $moreTabs = [
        [
            'route'   => 'inventory.alerts',
            'label'   => 'Alerts',
            'icon'    => 'M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0',
            'active'  => $r === 'inventory.alerts',
            'visible' => $showOperational,
        ],
        [
            'route'   => 'inventory.received-stock',
            'label'   => 'Received Stock',
            'icon'    => 'M20 6L9 17l-5-5',
            'active'  => $r === 'inventory.received-stock',
            'visible' => $showOperational,
        ],
        [
            'route'   => 'inventory.reports',
            'label'   => 'Reports',
            'icon'    => 'M9 19v-6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2zm0 0V9a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v10m-6 0a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2m0 0V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2z',
            'active'  => in_array($r, ['inventory.reports', 'inventory.expiry']),
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
            'route'   => 'inventory.settings',
            'label'   => 'Inventory Settings',
            'icon'    => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z',
            'active'  => $r === 'inventory.settings',
            'visible' => $showSettings,
        ],
    ];

    $primaryVisible = array_filter($primaryTabs, fn($t) => $t['visible']);
    $moreVisible    = array_filter($moreTabs, fn($t) => $t['visible']);
    $moreActive     = collect($moreVisible)->contains(fn($t) => $t['active']);
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
    @foreach($primaryVisible as $tab)
        @php $isActive = $tab['active']; @endphp
        <a href="{{ route($tab['route']) }}"
           style="display:inline-flex;align-items:center;gap:6px;padding:10px 14px;
                  font-family:'Inter',sans-serif;font-size:13px;
                  font-weight:{{ $isActive ? '600' : '400' }};
                  color:{{ $isActive ? '#6a0f70' : '#7a6884' }};
                  text-decoration:none;
                  border-bottom:2px solid {{ $isActive ? '#6a0f70' : 'transparent' }};
                  white-space:nowrap;flex-shrink:0;transition:color 150ms,border-color 150ms;"
           onmouseover="if(!{{ $isActive ? 'true' : 'false' }})this.style.color='#4e0a53'"
           onmouseout="if(!{{ $isActive ? 'true' : 'false' }})this.style.color='#7a6884'">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                 stroke="{{ $isActive ? '#6a0f70' : '#a090b0' }}" stroke-width="1.75"
                 stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                <path d="{{ $tab['icon'] }}"/>
            </svg>
            {{ $tab['label'] }}
        </a>
    @endforeach

    {{-- ── "More" menu — secondary sections ── --}}
    @if(count($moreVisible))
    <div id="inv-more-wrap" style="position:relative;margin-left:auto;flex-shrink:0;">
        <button type="button" onclick="dfInvToggleMore(event)"
                style="display:inline-flex;align-items:center;gap:6px;padding:10px 14px;background:none;
                       border:none;cursor:pointer;font-family:'Inter',sans-serif;font-size:13px;
                       font-weight:{{ $moreActive ? '600' : '400' }};
                       color:{{ $moreActive ? '#6a0f70' : '#7a6884' }};
                       border-bottom:2px solid {{ $moreActive ? '#6a0f70' : 'transparent' }};white-space:nowrap;">
            More
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                 stroke="{{ $moreActive ? '#6a0f70' : '#a090b0' }}" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div id="inv-more-menu"
             style="display:none;position:fixed;background:#fff;
                    border:1px solid #ede4f3;border-radius:8px;box-shadow:0 8px 24px rgba(14,1,24,.14);
                    width:210px;z-index:1000;overflow:hidden;padding:4px 0;">
            @foreach($moreVisible as $tab)
                @php $isActive = $tab['active']; @endphp
                <a href="{{ route($tab['route']) }}"
                   style="display:flex;align-items:center;gap:9px;padding:10px 14px;
                          font-family:'Inter',sans-serif;font-size:13px;text-decoration:none;
                          color:{{ $isActive ? '#6a0f70' : '#4e2060' }};
                          font-weight:{{ $isActive ? '600' : '400' }};
                          background:{{ $isActive ? '#faf5fb' : '#fff' }};"
                   onmouseover="this.style.background='#faf5fb'"
                   onmouseout="this.style.background='{{ $isActive ? '#faf5fb' : '#fff' }}'">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                         stroke="{{ $isActive ? '#6a0f70' : '#a090b0' }}" stroke-width="1.75"
                         stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                        <path d="{{ $tab['icon'] }}"/>
                    </svg>
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </div>
    </div>
    @endif
</div>

<script>
function dfInvToggleMore(e) {
    e.stopPropagation();
    var m = document.getElementById('inv-more-menu');
    var b = e.currentTarget;
    if (!m) return;
    if (m.style.display === 'block') { m.style.display = 'none'; return; }
    var r = b.getBoundingClientRect();
    // position:fixed so the menu escapes the subnav's overflow clipping
    m.style.top  = (r.bottom + 2) + 'px';
    m.style.left = Math.max(8, r.right - 210) + 'px';
    m.style.display = 'block';
}
document.addEventListener('click', function (e) {
    var wrap = document.getElementById('inv-more-wrap');
    var menu = document.getElementById('inv-more-menu');
    if (menu && wrap && !wrap.contains(e.target)) menu.style.display = 'none';
});
</script>
