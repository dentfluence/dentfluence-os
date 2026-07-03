{{--
|==========================================================================
| Dentfluence OS — Sidebar Navigation Component
| File: resources/views/components/sidebar.blade.php
|==========================================================================
--}}
<aside id="df-sidebar" aria-label="Main navigation">

{{-- ── BRAND HEADER ── --}}
<div
    class="df-sidebar-brand"
    style="
        flex-shrink: 0;
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 0 16px;
        height: 64px;
        border-bottom: 1px solid rgba(185,92,183,0.10);
        overflow: hidden;
        text-decoration: none;
    "
    aria-label="Dentfluence OS — Home"
>
    {{-- New logo icon: infinity+tooth --}}
    <picture style="width:36px;height:24px;flex-shrink:0;display:flex;align-items:center;">
        <source srcset="{{ asset('images/logo.webp') }}" type="image/webp">
        <img src="{{ asset('images/logo.png') }}"
             alt="Dentfluence"
             aria-hidden="true"
             style="width:36px;height:24px;object-fit:contain;filter:drop-shadow(0 0 5px rgba(180,60,220,0.40));">
    </picture>

    <div class="df-nav-label df-sidebar-brand-sub" style="overflow:hidden;min-width:0;transition:opacity 200ms,width 200ms;">
        <div style="font-family:'Cormorant Garamond',serif; font-size:17px; font-weight:700; letter-spacing:0.08em; color:rgba(240,225,250,0.92); white-space:nowrap; line-height:1.15;">
            DENTFLUENCE
        </div>
        <div style="font-size:9px; font-weight:400; letter-spacing:0.28em; text-transform:uppercase; color:rgba(185,150,210,0.48); margin-top:1px; white-space:nowrap;">
            Infinity OS
        </div>
    </div>
</div>

{{-- ── NAV SCROLLABLE REGION ── --}}
<nav id="df-sidebar-nav" aria-label="Main navigation">

    @php $user = auth()->user(); @endphp

    {{-- ── SECTION: Overview ── --}}
    <div style="padding: 16px 0 4px;">
        <div class="df-nav-section-label" style="font-family:'Inter',sans-serif;font-size:9.5px;font-weight:600;letter-spacing:0.20em;text-transform:uppercase;color:rgba(185,130,210,0.38);padding:0 16px 5px;white-space:nowrap;overflow:hidden;transition:opacity 200ms,width 200ms;">
            Overview
        </div>

        {{-- Dashboard: always visible --}}
        @include('components.sidebar-item', [
            'href'  => route('dashboard'),
            'label' => 'Dashboard',
            'icon'  => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        ])

        @if($user->canAccess('daily_huddle'))
            @include('components.sidebar-item', [
                'href'  => route('huddle.index'),
                'label' => 'Daily Huddle',
                'icon'  => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 11l-4 4-4-4"/>',
            ])
        @endif
    </div>

    {{-- ── SECTION: Clinical (only if user has at least one clinical module) ── --}}
    @if($user->canAccess('patients') || $user->canAccess('appointments') || $user->canAccess('treatments') || $user->canAccess('cms'))
    <div style="padding: 12px 0 4px;">
        <div class="df-nav-section-label" style="font-family:'Inter',sans-serif;font-size:9.5px;font-weight:600;letter-spacing:0.20em;text-transform:uppercase;color:rgba(185,130,210,0.38);padding:0 16px 5px;white-space:nowrap;overflow:hidden;transition:opacity 200ms,width 200ms;">
            Clinical
        </div>

        @if($user->canAccess('patients'))
            @include('components.sidebar-item', [
                'href'  => route('patients.index'),
                'label' => 'Patients',
                'icon'  => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            ])
        @endif

        @if($user->canAccess('appointments'))
            @include('components.sidebar-item', [
                'href'       => route('appointments.index'),
                'label'      => 'Appointments',
                'icon'       => '<rect x="3" y="4" width="18" height="18" rx="0"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
                'badgeCount' => 0,
                'badgeType'  => 'brand',
            ])
        @endif

        @if($user->canAccess('treatments'))
            @include('components.sidebar-item', [
                'href'  => route('treatments.index'),
                'label' => 'Treatments',
                'icon'  => '<path d="M12 22 C12 22 5 17 5 11 C5 7 7.5 4 12 4 C16.5 4 19 7 19 11 C19 17 12 22 12 22Z"/><line x1="12" y1="8" x2="12" y2="14" stroke-dasharray="2 2"/>',
            ])
        @endif

        @if($user->canAccess('patients') || $user->canAccess('treatments'))
            @include('components.sidebar-item', [
                'href'  => route('prescriptions.index'),
                'label' => 'Prescriptions',
                'icon'  => '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="13" y2="16"/>',
            ])
        @endif

        @if($user->canAccess('cms'))
            @include('components.sidebar-item', [
                'href'  => route('cms.index'),
                'label' => 'CMS',
                'icon'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
            ])
        @endif
    </div>
    @endif

    {{-- ── SECTION: Communication / Relationships ── --}}
    @if($user->canAccess('prm'))
    @php($preNavPrimary = \App\Support\Features\Feature::enabled('nav.pre_primary'))
    <div style="padding: 12px 0 4px;">
        <div class="df-nav-section-label" style="font-family:'Inter',sans-serif;font-size:9.5px;font-weight:600;letter-spacing:0.20em;text-transform:uppercase;color:rgba(185,130,210,0.38);padding:0 16px 5px;white-space:nowrap;overflow:hidden;transition:opacity 200ms,width 200ms;">
            {{ $preNavPrimary ? 'Relationships' : 'Communication' }}
        </div>

        @if($preNavPrimary)
            {{-- Workstream F: PRE is the primary entry --}}
            @include('components.sidebar-item', [
                'href'  => route('relationship.dashboard'),
                'label' => 'Relationships',
                'icon'  => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            ])
            {{-- PRM demoted to a Legacy link — still reachable, nothing removed --}}
            @include('components.sidebar-item', [
                'href'  => route('communication.index'),
                'label' => 'PRM (Legacy)',
                'icon'  => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
            ])
        @else
            {{-- Default (flag off): PRM primary — unchanged --}}
            @include('components.sidebar-item', [
                'href'  => route('communication.index'),
                'label' => 'PRM',
                'icon'  => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
            ])
        @endif
    </div>
    @endif

    {{-- ── SECTION: Operations ── --}}
    @if($user->canAccess('finance') || $user->canAccess('inventory') || $user->canAccess('lab') || $user->canAccess('tasks') || $user->canAccess('marketing'))
    <div style="padding: 12px 0 4px;">
        <div class="df-nav-section-label" style="font-family:'Inter',sans-serif;font-size:9.5px;font-weight:600;letter-spacing:0.20em;text-transform:uppercase;color:rgba(185,130,210,0.38);padding:0 16px 5px;white-space:nowrap;overflow:hidden;transition:opacity 200ms,width 200ms;">
            Operations
        </div>

        @if($user->canAccess('finance'))
            @include('components.sidebar-item', [
                'href'  => route('finance.dashboard'),
                'label' => 'Accounts & Finance',
                'icon'  => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
            ])
        @endif

        @if($user->canAccess('inventory'))
            @include('components.sidebar-item', [
                'href'       => route('inventory.index'),
                'label'      => 'Inventory',
                'icon'       => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
                'badgeCount' => 0,
                'badgeType'  => 'warning',
            ])
        @endif

        @if($user->canAccess('lab'))
            @include('components.sidebar-item', [
                'href'  => route('lab.index'),
                'label' => 'Lab',
                'icon'  => '<path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2v-4M9 21H5a2 2 0 0 1-2-2v-4m0 0h18"/>',
            ])
        @endif

        @if($user->canAccess('tasks'))
            @include('components.sidebar-item', [
                'href'       => route('tasks.index'),
                'label'      => 'Tasks',
                'icon'       => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
                'badgeCount' => 0,
                'badgeType'  => 'danger',
            ])
        @endif

        @if($user->canAccess('practice_protocols'))
            @include('components.sidebar-item', [
                'href'  => route('practice-protocols.index'),
                'label' => 'Practice Protocols',
                'icon'  => '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6"/><path d="M9 16h6"/>',
            ])
        @endif

        @include('components.sidebar-item', [
            'href'  => route('hr.dashboard'),
            'label' => 'HR',
            'icon'  => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        ])

        @if($user->canAccess('marketing'))
            @include('components.sidebar-item', [
                'href'  => route('marketing.index'),
                'label' => 'Marketing',
                'icon'  => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
            ])
        @endif
    </div>
    @endif

    {{-- ── SECTION: Insights ── --}}
    @if($user->canAccess('reports') || $user->canAccess('analytics'))
    <div style="padding: 12px 0 4px;">
        <div class="df-nav-section-label" style="font-family:'Inter',sans-serif;font-size:9.5px;font-weight:600;letter-spacing:0.20em;text-transform:uppercase;color:rgba(185,130,210,0.38);padding:0 16px 5px;white-space:nowrap;overflow:hidden;transition:opacity 200ms,width 200ms;">
            Insights
        </div>

        @if($user->canAccess('reports'))
            @include('components.sidebar-item', [
                'href'  => route('reports.index'),
                'label' => 'Reports',
                'icon'  => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
            ])
        @endif

        @if($user->canAccess('analytics'))
            @include('components.sidebar-item', [
                'href'  => route('analytics.index'),
                'label' => 'Analytics',
                'icon'  => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
            ])
        @endif
    </div>
    @endif

    {{-- ── SECTION: System ── --}}
    @if($user->canAccess('settings'))
    <div style="padding: 12px 0 8px;">
        <div class="df-nav-section-label" style="font-family:'Inter',sans-serif;font-size:9.5px;font-weight:600;letter-spacing:0.20em;text-transform:uppercase;color:rgba(185,130,210,0.38);padding:0 16px 5px;white-space:nowrap;overflow:hidden;transition:opacity 200ms,width 200ms;">
            System
        </div>

        @include('components.sidebar-item', [
            'href'  => route('settings.index'),
            'label' => 'Settings',
            'icon'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        ])
    </div>
    @endif

</nav>{{-- /#df-sidebar-nav --}}

{{-- ── SIDEBAR FOOTER ── --}}
<div
    style="
        flex-shrink: 0;
        border-top: 1px solid rgba(185,92,183,0.10);
        background: rgba(0,0,0,0.12);
    "
>
    <button
        onclick="DFLayout.toggleSidebar()"
        aria-label="Toggle sidebar"
        aria-controls="df-sidebar"
        title="Toggle sidebar (Ctrl+B)"
        style="
            width: 100%;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0 16px;
            color: rgba(185,140,210,0.45);
            font-family:'Inter',sans-serif;
            font-size:12px;
            font-weight:400;
            transition: color 140ms, background 140ms;
        "
        onmouseover="this.style.color='rgba(215,175,235,0.80)';this.style.background='rgba(255,255,255,0.04)';"
        onmouseout="this.style.color='rgba(185,140,210,0.45)';this.style.background='none';"
    >
        <svg
            id="df-collapse-icon"
            width="15" height="15"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            style="flex-shrink:0; transition: transform 280ms cubic-bezier(0.4,0,0.2,1);"
        >
            <polyline points="11 17 6 12 11 7"/>
            <polyline points="18 17 13 12 18 7"/>
        </svg>
        <span class="df-nav-label" style="white-space:nowrap; transition:opacity 200ms,width 200ms;">Collapse</span>
    </button>
</div>

<style>
    .df-nav-item {
        display: flex;
        align-items: center;
        gap: 11px;
        height: 38px;
        padding: 0 12px 0 14px;
        margin: 0 6px 1px;
        font-family: 'Inter', sans-serif;
        font-size: 13px;
        font-weight: 400;
        color: rgba(215,185,230,0.58);
        text-decoration: none;
        border-radius: 3px;
        border-left: 2px solid transparent;
        cursor: pointer;
        white-space: nowrap;
        overflow: hidden;
        transition: background-color 120ms linear, color 120ms linear, border-color 120ms linear;
        position: relative;
    }
    .df-nav-item:hover {
        background: rgba(185,92,183,0.10);
        color: rgba(235,210,248,0.90);
    }
    .df-nav-item.df-nav-active {
        background: rgba(185,92,183,0.14);
        color: #ffffff;
        border-left-color: #6a0f70;
        font-weight: 500;
    }
    .df-nav-icon {
        flex-shrink: 0;
        width: 16px;
        height: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: inherit;
        opacity: 0.75;
        transition: opacity 120ms;
    }
    .df-nav-item:hover .df-nav-icon,
    .df-nav-item.df-nav-active .df-nav-icon { opacity: 1; }
    .df-nav-badge {
        margin-left: auto;
        flex-shrink: 0;
        min-width: 18px;
        height: 18px;
        border-radius: 9px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        font-weight: 700;
        padding: 0 5px;
    }
    .df-nav-badge.badge-brand   { background: #6a0f70; color: #fff; }
    .df-nav-badge.badge-danger  { background: #b52020; color: #fff; }
    .df-nav-badge.badge-warning { background: #a05c00; color: #fff; }
    #df-shell[data-sidebar="collapsed"] #df-collapse-icon {
        transform: rotate(180deg);
    }
    #df-shell[data-sidebar="collapsed"] .df-nav-item {
        position: relative;
    }
    #df-shell[data-sidebar="collapsed"] .df-nav-item::after {
        content: attr(data-tooltip);
        position: absolute;
        left: calc(100% + 10px);
        top: 50%;
        transform: translateY(-50%);
        background: #1e0a2c;
        color: rgba(225,200,240,0.90);
        font-size: 12px;
        font-family: 'Inter', sans-serif;
        font-weight: 400;
        padding: 5px 10px;
        border-radius: 3px;
        border: 1px solid rgba(185,92,183,0.20);
        white-space: nowrap;
        pointer-events: none;
        opacity: 0;
        z-index: 100;
        box-shadow: 0 4px 12px rgba(14,1,24,0.25);
        transition: opacity 140ms;
    }
    #df-shell[data-sidebar="collapsed"] .df-nav-item:hover::after {
        opacity: 1;
    }
    /* ── Access-denied toast ── */
    #df-access-toast {
        position: fixed;
        bottom: 28px;
        left: 50%;
        transform: translateX(-50%) translateY(12px);
        background: #1e0a2c;
        border: 1px solid rgba(185,92,183,0.30);
        border-radius: 6px;
        padding: 10px 16px 10px 12px;
        display: flex;
        align-items: center;
        gap: 9px;
        font-family: 'Inter', sans-serif;
        font-size: 13px;
        color: rgba(225,200,240,0.90);
        box-shadow: 0 6px 24px rgba(14,1,24,0.45);
        z-index: 9999;
        opacity: 0;
        pointer-events: none;
        transition: opacity 180ms ease, transform 180ms ease;
        white-space: nowrap;
    }
    #df-access-toast.df-toast-show {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
    #df-access-toast .df-toast-icon {
        color: rgba(185,92,183,0.80);
        flex-shrink: 0;
    }
</style>

{{-- Access-denied toast (shared by all locked nav items) --}}
<div id="df-access-toast" role="status" aria-live="polite">
    <span class="df-toast-icon">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
    </span>
    <span id="df-access-toast-msg">You don't have access to this section.</span>
</div>

<script>
(function () {
    var _toastTimer = null;

    window.dfNavAccessDenied = function (e, label) {
        e.preventDefault();
        var toast = document.getElementById('df-access-toast');
        var msg   = document.getElementById('df-access-toast-msg');
        if (!toast) return;

        msg.textContent = label
            ? '‘' + label + '’ is restricted for your role.'
            : 'You don’t have access to this section.';

        clearTimeout(_toastTimer);
        toast.classList.add('df-toast-show');
        _toastTimer = setTimeout(function () {
            toast.classList.remove('df-toast-show');
        }, 2800);
    };
})();
</script>

</aside>{{-- /#df-sidebar --}}                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           