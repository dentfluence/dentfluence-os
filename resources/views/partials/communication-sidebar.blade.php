<aside class="comm-sidebar" id="commSidebar">

    {{-- ── Dashboard ───────────────────────────────────────────────── --}}
    <a href="{{ route('dashboard') }}"
       class="comm-nav-item {{ request()->routeIs('dashboard') ? 'comm-nav-item--active' : '' }}">
        <i class="ti ti-layout-dashboard"></i>
        <span>Dashboard</span>
    </a>

    {{-- ── Communication ───────────────────────────────────────────── --}}
    <div class="comm-nav-section">COMMUNICATION</div>

    <a href="{{ route('communication.manager.overdue') }}"
       class="comm-nav-item {{ request()->routeIs('communication.manager.overdue') ? 'comm-nav-item--active' : '' }}">
        <i class="ti ti-alert-triangle"></i>
        <span>Overdue</span>
        <span class="comm-nav-badge comm-nav-badge--red">18</span>
    </a>

    <a href="{{ route('communication.manager.today') }}"
       class="comm-nav-item {{ request()->routeIs('communication.manager.today') ? 'comm-nav-item--active' : '' }}">
        <i class="ti ti-calendar"></i>
        <span>Today</span>
        <span class="comm-nav-badge comm-nav-badge--purple">34</span>
    </a>

    <a href="{{ route('communication.manager.long-term') }}"
       class="comm-nav-item {{ request()->routeIs('communication.manager.long-term') ? 'comm-nav-item--active' : '' }}">
        <i class="ti ti-clock"></i>
        <span>Long Term (6M+)</span>
        <span class="comm-nav-badge comm-nav-badge--amber">23</span>
    </a>

    <a href="{{ route('communication.manager.ongoing') }}"
       class="comm-nav-item {{ request()->routeIs('communication.manager.ongoing') ? 'comm-nav-item--active' : '' }}">
        <i class="ti ti-heart-handshake"></i>
        <span>Ongoing Treatment</span>
        <span class="comm-nav-badge comm-nav-badge--blue">16</span>
    </a>

    <a href="{{ route('communication.manager.yesterday') }}"
       class="comm-nav-item {{ request()->routeIs('communication.manager.yesterday') ? 'comm-nav-item--active' : '' }}">
        <i class="ti ti-history"></i>
        <span>Yesterday</span>
        <span class="comm-nav-badge comm-nav-badge--gray">12</span>
    </a>

    <a href="{{ route('communication.manager.special-days') }}"
       class="comm-nav-item {{ request()->routeIs('communication.manager.special-days') ? 'comm-nav-item--active' : '' }}">
        <i class="ti ti-gift"></i>
        <span>Special Days</span>
        <span class="comm-nav-badge comm-nav-badge--pink">7</span>
    </a>

    {{-- ── Leads & Calls ────────────────────────────────────────────── --}}
    <div class="comm-nav-section">LEADS & CALLS</div>

    <a href="{{ route('communication.manager.calls') }}"
       class="comm-nav-item {{ request()->routeIs('communication.manager.calls') ? 'comm-nav-item--active' : '' }}">
        <i class="ti ti-phone-call"></i>
        <span>Call Manager</span>
    </a>

    <a href="{{ route('prm.index') }}"
       class="comm-nav-item {{ request()->routeIs('prm.index') ? 'comm-nav-item--active' : '' }}">
        <i class="ti ti-users"></i>
        <span>Leads</span>
    </a>

    <a href="{{ route('prm.board') }}"
       class="comm-nav-item {{ request()->routeIs('prm.board') || request()->routeIs('prm.*') ? 'comm-nav-item--active' : '' }}">
        <i class="ti ti-layout-kanban"></i>
        <span>Pipeline</span>
    </a>

    {{-- ── Tools ───────────────────────────────────────────────────── --}}
    <div class="comm-nav-section">TOOLS</div>

    <a href="{{ route('communication.activity-log') }}"
       class="comm-nav-item {{ request()->routeIs('communication.activity-log') ? 'comm-nav-item--active' : '' }}">
        <i class="ti ti-history"></i>
        <span>Activity Log</span>
    </a>

    <a href="{{ route('communication.followup.calendar') }}"
       class="comm-nav-item {{ request()->routeIs('communication.followup.*') ? 'comm-nav-item--active' : '' }}">
        <i class="ti ti-calendar-stats"></i>
        <span>Follow-up Calendar</span>
    </a>

    <a href="{{ route('communication.tasks.index') }}"
       class="comm-nav-item {{ request()->routeIs('communication.tasks.*') ? 'comm-nav-item--active' : '' }}">
        <i class="ti ti-checklist"></i>
        <span>Tasks</span>
    </a>

    {{-- ── Settings ─────────────────────────────────────────────────── --}}
    <div class="comm-nav-section">SETTINGS</div>

    <a href="{{ route('prm.settings') }}"
       class="comm-nav-item {{ request()->routeIs('prm.settings') ? 'comm-nav-item--active' : '' }}">
        <i class="ti ti-settings"></i>
        <span>PRM Settings</span>
    </a>

</aside>
