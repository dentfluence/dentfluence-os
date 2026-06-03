{{--
|==========================================================================
| Dentfluence OS — Topbar Component
| File: resources/views/components/topbar.blade.php
|==========================================================================
--}}

<header id="df-topbar" role="banner" aria-label="Application topbar">

    {{-- ── LEFT: Hamburger + Breadcrumb ── --}}
    <div style="display:flex;align-items:center;gap:14px;flex-shrink:0;">

        <button
            onclick="DFLayout.toggleSidebar()"
            aria-label="Toggle navigation"
            title="Toggle sidebar (Ctrl+B)"
            style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;background:none;border:1px solid transparent;border-radius:3px;cursor:pointer;color:#7a6884;transition:color 140ms,border-color 140ms,background 140ms;"
            onmouseover="this.style.color='#5a006e';this.style.borderColor='rgba(185,92,183,0.20)';this.style.background='#f9f3fa';"
            onmouseout="this.style.color='#7a6884';this.style.borderColor='transparent';this.style.background='none';"
        >
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <line x1="3" y1="6"  x2="21" y2="6"/>
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>

        <nav aria-label="Breadcrumb" style="display:flex;align-items:center;gap:6px;">
            @isset($breadcrumbs)
                @foreach($breadcrumbs as $crumb)
                    @if(!$loop->last)
                        <a href="{{ $crumb['url'] }}"
                           style="font-size:12px;font-weight:400;color:#b0a4bc;text-decoration:none;transition:color 140ms;"
                           onmouseover="this.style.color='#6a0f70';"
                           onmouseout="this.style.color='#b0a4bc';">{{ $crumb['label'] }}</a>
                        <span style="color:#d4c8dc;font-size:11px;" aria-hidden="true">/</span>
                    @else
                        <span style="font-size:12px;font-weight:500;color:#2a1440;">{{ $crumb['label'] }}</span>
                    @endif
                @endforeach
            @else
                <span style="font-size:13px;font-weight:500;color:#2a1440;">
                    @yield('page-title', 'Dashboard')
                </span>
            @endisset
        </nav>

    </div>

    {{-- ── CENTER: Global Search ── --}}
    <div style="flex:1;display:flex;align-items:center;justify-content:center;padding:0 20px;max-width:520px;margin:0 auto;">
        @include('patients._search')
    </div>

    {{-- ── RIGHT: Actions + Profile ── --}}
    <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">

        {{-- Quick Add --}}
        <div style="position:relative;" id="df-quickadd-wrap">
            <button
                id="df-quickadd-btn"
                onclick="dfToggleDropdown('df-quickadd-menu')"
                aria-label="Quick add"
                aria-haspopup="true"
                aria-expanded="false"
                style="width:34px;height:34px;display:flex;align-items:center;justify-content:center;background:none;border:1px solid rgba(185,92,183,0.18);border-radius:3px;cursor:pointer;color:#7a6884;transition:color 140ms,border-color 140ms,background 140ms;"
                onmouseover="this.style.color='#5a006e';this.style.background='#f9f3fa';this.style.borderColor='rgba(185,92,183,0.35)';"
                onmouseout="this.style.color='#7a6884';this.style.background='none';this.style.borderColor='rgba(185,92,183,0.18)';"
            >
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
            </button>

            <div
                id="df-quickadd-menu"
                style="display:none;position:absolute;top:calc(100% + 8px);right:0;width:200px;background:#ffffff;border:1px solid rgba(185,92,183,0.14);border-radius:3px;box-shadow:0 4px 16px rgba(14,1,24,0.12);z-index:70;overflow:hidden;"
                role="menu"
            >
                @php
                $quickItems = [
                    [
                        'label' => 'New Appointment',
                        'icon'  => '<rect x="3" y="4" width="18" height="18" rx="0"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
                        'href'  => route('appointments.create'),
                    ],
                    [
                        'label' => 'New Patient',
                        'icon'  => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
                        'href'  => route('patients.create'),
                    ],
                    [
                        'label' => 'New Task',
                        'icon'  => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
                        'href'  => '#',
                    ],
                    [
                        'label' => 'New Lab Order',
                        'icon'  => '<path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2v-4M9 21H5a2 2 0 0 1-2-2v-4m0 0h18"/>',
                        'href'  => route('lab.index'),
                    ],
                ];
                @endphp

                @foreach($quickItems as $item)
                <a
                    href="{{ $item['href'] }}"
                    role="menuitem"
                    style="display:flex;align-items:center;gap:10px;padding:9px 14px;font-size:13px;font-weight:400;color:#2a1440;text-decoration:none;border-bottom:1px solid #f3edf7;transition:background 100ms;"
                    onmouseover="this.style.background='#f9f3fa';"
                    onmouseout="this.style.background='none';"
                >
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#8e24aa" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        {!! $item['icon'] !!}
                    </svg>
                    {{ $item['label'] }}
                </a>
                @endforeach

            </div>
        </div>

        {{-- Sync status --}}
        <div
            id="df-sync-btn"
            title="Sync status"
            style="width:34px;height:34px;display:flex;align-items:center;justify-content:center;color:#b0a4bc;cursor:default;position:relative;"
            aria-label="All data synced"
        >
            <svg id="df-sync-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="23 4 23 10 17 10"/>
                <polyline points="1 20 1 14 7 14"/>
                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
            </svg>
            <span
                id="df-sync-dot"
                style="position:absolute;top:6px;right:5px;width:7px;height:7px;border-radius:50%;background:#1a7a45;border:1.5px solid #ffffff;"
                aria-hidden="true"
            ></span>
        </div>

        {{-- Notifications --}}
        <div style="position:relative;" id="df-notif-wrap">
            <button
                id="df-notif-btn"
                onclick="dfToggleDropdown('df-notif-panel')"
                aria-label="Notifications"
                aria-haspopup="true"
                aria-expanded="false"
                style="width:34px;height:34px;display:flex;align-items:center;justify-content:center;background:none;border:none;border-radius:3px;cursor:pointer;color:#7a6884;position:relative;transition:color 140ms,background 140ms;"
                onmouseover="this.style.color='#5a006e';this.style.background='#f9f3fa';"
                onmouseout="this.style.color='#7a6884';this.style.background='none';"
            >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                <span
                    id="df-notif-badge"
                    style="position:absolute;top:4px;right:3px;min-width:16px;height:16px;border-radius:8px;background:#b52020;color:#fff;font-size:9px;font-weight:700;display:flex;align-items:center;justify-content:center;padding:0 3px;border:1.5px solid #fff;line-height:1;"
                    aria-hidden="true"
                >0</span>
            </button>

            <div
                id="df-notif-panel"
                style="display:none;position:absolute;top:calc(100% + 8px);right:0;width:320px;background:#ffffff;border:1px solid rgba(185,92,183,0.14);border-radius:3px;box-shadow:0 4px 16px rgba(14,1,24,0.12);z-index:70;overflow:hidden;"
                role="dialog"
                aria-label="Notifications"
            >
                <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #f0e8f8;background:#faf4fb;">
                    <span style="font-size:13px;font-weight:600;color:#1a0a24;">Notifications</span>
                    <button style="font-size:11px;font-weight:500;color:#6a0f70;background:none;border:none;cursor:pointer;"
                            onmouseover="this.style.textDecoration='underline';"
                            onmouseout="this.style.textDecoration='none';">Mark all read</button>
                </div>
                <div id="df-notif-empty" style="padding:32px 16px;text-align:center;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#d4c8dc" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto 10px;">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    <p style="font-size:13px;color:#b0a4bc;font-weight:300;">You're all caught up</p>
                </div>
                <div id="df-notif-list" aria-live="polite"></div>
                <div style="border-top:1px solid #f0e8f8;padding:10px 16px;text-align:center;">
                    <a href="{{ route('notifications.index') }}"
                       style="font-size:12px;font-weight:500;color:#6a0f70;text-decoration:none;"
                       onmouseover="this.style.textDecoration='underline';"
                       onmouseout="this.style.textDecoration='none';">View all notifications</a>
                </div>
            </div>
        </div>

        {{-- Divider --}}
        <div style="width:1px;height:22px;background:rgba(185,92,183,0.14);margin:0 4px;" aria-hidden="true"></div>

        {{-- User Profile --}}
        <div style="position:relative;" id="df-profile-wrap">
            <button
                id="df-profile-btn"
                onclick="dfToggleDropdown('df-profile-menu')"
                aria-label="User profile"
                aria-haspopup="true"
                aria-expanded="false"
                style="display:flex;align-items:center;gap:9px;padding:4px 6px 4px 4px;background:none;border:1px solid transparent;border-radius:3px;cursor:pointer;transition:background 140ms,border-color 140ms;"
                onmouseover="this.style.background='#f9f3fa';this.style.borderColor='rgba(185,92,183,0.18)';"
                onmouseout="this.style.background='none';this.style.borderColor='transparent';"
            >
                <div
                    style="width:30px;height:30px;border-radius:3px;flex-shrink:0;background:#5a006e;color:#fff;display:flex;align-items:center;justify-content:center;font-family:'DM Sans',sans-serif;font-size:12px;font-weight:600;letter-spacing:0.03em;"
                    aria-hidden="true"
                >
                    {{ strtoupper(substr(auth()->user()->name ?? 'D', 0, 2)) }}
                </div>
                <div style="text-align:left;display:flex;flex-direction:column;justify-content:center;max-width:120px;" class="hidden md:flex">
                    <span style="font-size:12.5px;font-weight:500;color:#1a0a24;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.3;">
                        {{ auth()->user()->name ?? 'Doctor' }}
                    </span>
                    <span style="font-size:10.5px;font-weight:400;color:#9e8fa0;white-space:nowrap;letter-spacing:0.01em;line-height:1.3;">
                        {{ auth()->user()->role ?? 'Front Desk' }}
                    </span>
                </div>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#b0a4bc" stroke-width="2.5" stroke-linecap="round" style="flex-shrink:0;margin-left:2px;" class="hidden md:block">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>

            <div
                id="df-profile-menu"
                style="display:none;position:absolute;top:calc(100% + 8px);right:0;width:210px;background:#ffffff;border:1px solid rgba(185,92,183,0.14);border-radius:3px;box-shadow:0 4px 16px rgba(14,1,24,0.12);z-index:70;overflow:hidden;"
                role="menu"
            >
                <div style="padding:12px 14px;border-bottom:1px solid #f0e8f8;background:#faf4fb;">
                    <p style="font-size:13px;font-weight:600;color:#1a0a24;">{{ auth()->user()->name ?? 'Doctor' }}</p>
                    <p style="font-size:11.5px;font-weight:300;color:#9e8fa0;margin-top:2px;">{{ auth()->user()->email ?? '' }}</p>
                </div>

                @php
                $profileItems = [
                    [
                        'label' => 'My Profile',
                        'icon'  => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
                        'href'  => route('profile.edit'),
                    ],
                    [
                        'label' => 'Preferences',
                        'icon'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
                        'href'  => route('settings.index'),
                    ],
                    [
                        'label' => 'Help & Support',
                        'icon'  => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
                        'href'  => route('help.index'),
                    ],
                ];
                @endphp

                @foreach($profileItems as $item)
                <a
                    href="{{ $item['href'] }}"
                    role="menuitem"
                    style="display:flex;align-items:center;gap:10px;padding:9px 14px;font-size:12.5px;font-weight:400;color:#2a1440;text-decoration:none;border-bottom:1px solid #f8f2fb;transition:background 100ms;"
                    onmouseover="this.style.background='#f9f3fa';"
                    onmouseout="this.style.background='none';"
                >
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9e8fa0" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        {!! $item['icon'] !!}
                    </svg>
                    {{ $item['label'] }}
                </a>
                @endforeach

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button
                        type="submit"
                        role="menuitem"
                        style="display:flex;align-items:center;gap:10px;width:100%;padding:9px 14px;font-size:12.5px;font-weight:400;color:#b52020;background:none;border:none;cursor:pointer;text-align:left;transition:background 100ms;"
                        onmouseover="this.style.background='#fdeaea';"
                        onmouseout="this.style.background='none';"
                    >
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#b52020" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                        Sign Out
                    </button>
                </form>

            </div>
        </div>

    </div>{{-- /right zone --}}

</header>

{{-- ── Dropdown JS ── --}}
<script>
(function () {

    window.dfToggleDropdown = function (id) {
        var panel  = document.getElementById(id);
        if (!panel) return;
        var isOpen = panel.style.display !== 'none';

        ['df-quickadd-menu','df-notif-panel','df-profile-menu'].forEach(function (pid) {
            var el = document.getElementById(pid);
            if (el) el.style.display = 'none';
        });
        ['df-quickadd-btn','df-notif-btn','df-profile-btn'].forEach(function (bid) {
            var btn = document.getElementById(bid);
            if (btn) btn.setAttribute('aria-expanded', 'false');
        });

        if (!isOpen) {
            panel.style.display = 'block';
            var triggerId = id === 'df-quickadd-menu' ? 'df-quickadd-btn'
                          : id === 'df-notif-panel'   ? 'df-notif-btn'
                          : 'df-profile-btn';
            var trigger = document.getElementById(triggerId);
            if (trigger) trigger.setAttribute('aria-expanded', 'true');
        }
    };

    document.addEventListener('click', function (e) {
        var inside = ['df-quickadd-wrap','df-notif-wrap','df-profile-wrap'].some(function (id) {
            var el = document.getElementById(id);
            return el && el.contains(e.target);
        });
        if (!inside) {
            ['df-quickadd-menu','df-notif-panel','df-profile-menu'].forEach(function (pid) {
                var el = document.getElementById(pid);
                if (el) el.style.display = 'none';
            });
        }
    });

    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            var s = document.getElementById('df-global-search');
            if (s) s.focus();
        }
    });

    window.DFTopbar = {
        setNotifCount: function (n) {
            var badge = document.getElementById('df-notif-badge');
            if (!badge) return;
            badge.style.display = n > 0 ? 'flex' : 'none';
            if (n > 0) badge.textContent = n > 99 ? '99+' : n;
        },
        setSyncState: function (state) {
            var dot   = document.getElementById('df-sync-dot');
            var icon  = document.getElementById('df-sync-icon');
            var btn   = document.getElementById('df-sync-btn');
            var map   = {
                synced:  { bg:'#1a7a45', spin:false, label:'All data synced' },
                syncing: { bg:'#a05c00', spin:true,  label:'Syncing...' },
                error:   { bg:'#b52020', spin:false, label:'Sync failed' },
            };
            var s = map[state] || map.synced;
            if (dot) dot.style.background = s.bg;
            if (btn) btn.setAttribute('aria-label', s.label);
            if (icon) icon.style.animation = s.spin ? 'syncSpin 1s linear infinite' : 'none';
        },
    };

})();
</script>

<style>
    @keyframes syncSpin { to { transform: rotate(360deg); } }
    #df-notif-badge { display: none; }
</style>