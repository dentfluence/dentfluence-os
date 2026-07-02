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
                    @hasSection('page-title')
                        @yield('page-title')
                    @else
                        {{ \Illuminate\Support\Str::headline(request()->segment(1) ?: 'Dashboard') }}
                    @endif
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
                // Quick Add items.
                //  - type 'link'   → plain navigation (global create pages)
                //  - type 'picker' → first ask "which patient?", then redirect to a
                //                    patient-scoped page. 'target' tells the picker
                //                    which URL pattern to build ({id} is swapped in JS).
                $quickItems = [
                    [
                        'type'  => 'link',
                        'label' => 'New Appointment',
                        'icon'  => '<rect x="3" y="4" width="18" height="18" rx="0"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
                        'href'  => route('appointments.create'),
                    ],
                    [
                        'type'  => 'link',
                        'label' => 'New Patient',
                        'icon'  => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
                        'href'  => route('patients.create'),
                    ],
                    [
                        'type'   => 'picker',
                        'label'  => 'New Prescription',
                        'icon'   => '<path d="M4 4h7l3 3v3l-3 3H8m-4-9v16m0-7 8 8m2-10 4 4m0-4-4 4"/>',
                        // {id} is replaced with the chosen patient id by the picker JS.
                        'target' => url('patients/{id}/prescriptions/create'),
                        'pickerLabel' => 'Pick a patient for the prescription',
                    ],
                    [
                        'type'   => 'picker',
                        'label'  => 'New Payment',
                        'icon'   => '<rect x="2" y="5" width="20" height="14" rx="0"/><line x1="2" y1="10" x2="22" y2="10"/><line x1="6" y1="15" x2="10" y2="15"/>',
                        'target' => url('wallets/{id}'),
                        'pickerLabel' => 'Pick a patient to record a payment',
                    ],
                ];
                @endphp

                @foreach($quickItems as $item)
                @if($item['type'] === 'picker')
                <button
                    type="button"
                    role="menuitem"
                    onclick="dfQuickPicker.open('{{ $item['target'] }}', @js($item['pickerLabel']))"
                    style="display:flex;align-items:center;gap:10px;width:100%;padding:9px 14px;font-size:13px;font-weight:400;color:#2a1440;background:none;border:none;border-bottom:1px solid #f3edf7;cursor:pointer;text-align:left;transition:background 100ms;"
                    onmouseover="this.style.background='#f9f3fa';"
                    onmouseout="this.style.background='none';"
                >
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#8e24aa" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        {!! $item['icon'] !!}
                    </svg>
                    {{ $item['label'] }}
                </button>
                @else
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
                @endif
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
                    <button id="df-notif-markall"
                            style="font-size:11px;font-weight:500;color:#6a0f70;background:none;border:none;cursor:pointer;"
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
                @if(auth()->user()->avatar)
                    <img src="{{ Storage::url(auth()->user()->avatar) }}" alt="{{ auth()->user()->name }}"
                         style="width:30px;height:30px;border-radius:3px;flex-shrink:0;object-fit:cover;border:1.5px solid rgba(185,92,183,0.25);" aria-hidden="true">
                @else
                <div
                    style="width:30px;height:30px;border-radius:3px;flex-shrink:0;background:#5a006e;color:#fff;display:flex;align-items:center;justify-content:center;font-family:'DM Sans',sans-serif;font-size:12px;font-weight:600;letter-spacing:0.03em;"
                    aria-hidden="true"
                >
                    {{ strtoupper(substr(auth()->user()->name ?? 'D', 0, 2)) }}
                </div>
                @endif
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
                        'href'  => route('profile.show'),
                    ],
                    [
                        'label' => 'Preferences',
                        'icon'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
                        'href'  => route('settings.index'),
                    ],
                    [
                        'label' => 'Help & Support',
                        'icon'  => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
                        'href'  => '#',
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

{{-- ─────────────────────────────────────────────────────────────────────
| Quick Add — Patient Picker
| Used by the "New Prescription" / "New Payment" items. Both actions need a
| patient first (there is no global create page for them), so this little
| overlay searches patients and redirects to the chosen patient's page.
└────────────────────────────────────────────────────────────────────── --}}
<div id="df-qp-overlay"
     style="display:none;position:fixed;inset:0;z-index:200;background:rgba(20,4,30,0.40);align-items:flex-start;justify-content:center;padding-top:12vh;"
     role="dialog" aria-modal="true" aria-label="Choose a patient">
    <div style="width:min(480px,92vw);background:#fff;border:1px solid rgba(185,92,183,0.16);border-radius:4px;box-shadow:0 12px 40px rgba(14,1,24,0.28);overflow:hidden;"
         onclick="event.stopPropagation();">

        {{-- Header --}}
        <div style="display:flex;align-items:center;justify-content:space-between;padding:13px 16px;border-bottom:1px solid #f0e8f8;background:#faf4fb;">
            <span id="df-qp-title" style="font-size:13.5px;font-weight:600;color:#1a0a24;">Pick a patient</span>
            <button type="button" onclick="dfQuickPicker.close()" aria-label="Close"
                    style="width:26px;height:26px;display:flex;align-items:center;justify-content:center;background:none;border:none;cursor:pointer;color:#9e8fa0;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        {{-- Search input --}}
        <div style="padding:14px 16px 8px;position:relative;">
            <span style="position:absolute;top:24px;left:28px;pointer-events:none;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9d6ea8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </span>
            <input id="df-qp-input" type="text" autocomplete="off"
                   placeholder="Search patient — name, phone, ID…"
                   oninput="dfQuickPicker.search(this.value)"
                   onkeydown="if(event.key==='Escape')dfQuickPicker.close();"
                   style="width:100%;padding:10px 12px 10px 34px;font-size:13.5px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;outline:none;color:#1a0020;">
        </div>

        {{-- Results --}}
        <div id="df-qp-results" style="max-height:46vh;overflow-y:auto;padding:4px 0 10px;"></div>
    </div>
</div>

<script>
window.dfQuickPicker = (function () {
    var targetTpl = '';
    var overlay, input, results, title;

    function els() {
        overlay  = overlay  || document.getElementById('df-qp-overlay');
        input    = input    || document.getElementById('df-qp-input');
        results  = results  || document.getElementById('df-qp-results');
        title    = title    || document.getElementById('df-qp-title');
    }

    function render(items) {
        els();
        results.innerHTML = '';
        if (!items || !items.length) {
            results.innerHTML = '<p style="padding:18px 16px;text-align:center;font-size:13px;color:#b0a4bc;">Type at least 2 letters to search.</p>';
            return;
        }
        items.forEach(function (p) {
            var dest = targetTpl.replace('{id}', p.id);
            var a = document.createElement('a');
            a.href = dest;
            a.style.cssText = 'display:flex;align-items:center;gap:11px;padding:9px 16px;text-decoration:none;cursor:pointer;';
            a.onmouseover = function () { a.style.background = '#f9f3fa'; };
            a.onmouseout  = function () { a.style.background = 'none'; };
            a.innerHTML =
                '<span style="flex-shrink:0;width:30px;height:30px;display:flex;align-items:center;justify-content:center;background:#f5eef9;border:1px solid rgba(185,92,183,0.18);border-radius:3px;font-size:11px;font-weight:600;color:#6a0f70;">' + (p.initials || '?') + '</span>' +
                '<span style="flex:1;min-width:0;">' +
                  '<span style="display:block;font-size:13px;font-weight:500;color:#1a0020;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + (p.name || '') + '</span>' +
                  '<span style="display:block;font-size:11px;color:#9d6ea8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + (p.meta || '') + '</span>' +
                '</span>' +
                '<span style="flex-shrink:0;font-size:11px;color:#6a0f70;background:#f5eef9;border:1px solid rgba(185,92,183,0.14);padding:1px 6px;border-radius:2px;">#' + p.id + '</span>';
            results.appendChild(a);
        });
    }

    var timer = null;
    return {
        open: function (tpl, label) {
            targetTpl = tpl;
            // close the quick-add dropdown if it's open
            var qa = document.getElementById('df-quickadd-menu');
            if (qa) qa.style.display = 'none';
            els();
            if (label) title.textContent = label;
            input.value = '';
            render([]);
            overlay.style.display = 'flex';
            overlay.onclick = function () { window.dfQuickPicker.close(); };
            setTimeout(function () { input.focus(); }, 30);
        },
        close: function () {
            els();
            overlay.style.display = 'none';
        },
        search: function (q) {
            clearTimeout(timer);
            if (!q || q.trim().length < 2) { render([]); return; }
            timer = setTimeout(function () {
                fetch('/patients/search?q=' + encodeURIComponent(q.trim()), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function (r) { return r.json(); })
                .then(function (data) { render(data || []); })
                .catch(function () {
                    els();
                    results.innerHTML = '<p style="padding:18px 16px;text-align:center;font-size:13px;color:#b52020;">Search failed — try again.</p>';
                });
            }, 250);
        }
    };
})();
</script>

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

// ── Notifications AJAX ────────────────────────────────────────────────────
(function () {
    var UNREAD_URL    = '{{ route("notifications.unread") }}';
    var MARK_ALL_URL  = '{{ route("notifications.markAllRead") }}';
    var CSRF          = '{{ csrf_token() }}';

    var typeColors = {
        appointment: '#6a0f70',
        lab:         '#0070b0',
        inventory:   '#a05c00',
        payment:     '#b52020',
        system:      '#5a5a7a',
    };

    function renderNotifications(items) {
        var list  = document.getElementById('df-notif-list');
        var empty = document.getElementById('df-notif-empty');
        if (!list) return;
        list.innerHTML = '';
        if (!items || items.length === 0) {
            if (empty) empty.style.display = 'block';
            return;
        }
        if (empty) empty.style.display = 'none';
        items.forEach(function (n) {
            var dot = n.is_read
                ? ''
                : '<span style="width:7px;height:7px;border-radius:50%;background:' + (typeColors[n.type] || '#5a5a7a') + ';flex-shrink:0;margin-top:4px;"></span>';
            var link = n.action_url
                ? '<a href="' + n.action_url + '" style="font-size:11px;color:#6a0f70;text-decoration:none;font-weight:500;" onmouseover="this.style.textDecoration=\'underline\';" onmouseout="this.style.textDecoration=\'none\';">' + (n.action_label || 'View') + ' →</a>'
                : '';
            var row = document.createElement('div');
            row.style.cssText = 'display:flex;gap:10px;padding:10px 16px;border-bottom:1px solid #f8f2fb;' + (n.is_read ? '' : 'background:#fdf8ff;');
            row.innerHTML = '<div style="flex-shrink:0;margin-top:3px;">' + dot + '</div>'
                + '<div style="flex:1;min-width:0;">'
                + '<p style="font-size:12.5px;font-weight:' + (n.is_read ? '400' : '600') + ';color:#1a0a24;margin:0 0 2px;line-height:1.4;">' + n.title + '</p>'
                + (n.message ? '<p style="font-size:11.5px;color:#7a6884;margin:0 0 3px;line-height:1.4;">' + n.message + '</p>' : '')
                + '<div style="display:flex;align-items:center;gap:10px;">'
                + '<span style="font-size:10.5px;color:#b0a4bc;">' + n.time_ago + '</span>'
                + link
                + '</div>'
                + '</div>';
            list.appendChild(row);
        });
    }

    function loadNotifications() {
        fetch(UNREAD_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                window.DFTopbar.setNotifCount(data.unread_count || 0);
                renderNotifications(data.items || []);
            })
            .catch(function () { /* silently fail */ });
    }

    // Mark all read
    var markAllBtn = document.getElementById('df-notif-markall');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function () {
            fetch(MARK_ALL_URL, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            }).then(function () {
                window.DFTopbar.setNotifCount(0);
                loadNotifications(); // re-render to clear unread dots
            });
        });
    }

    // Load on page init, then poll every 60 seconds
    loadNotifications();
    setInterval(loadNotifications, 60000);

})();
</script>

<style>
    @keyframes syncSpin { to { transform: rotate(360deg); } }
    #df-notif-badge { display: none; }
</style>  