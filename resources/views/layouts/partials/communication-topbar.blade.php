{{--
    Communication OS — Topbar
    Dentfluence · Tulip Dental
--}}
<header class="comm-topbar" role="banner">

    {{-- ── Left: Mobile sidebar toggle + breadcrumb ───────────────── --}}
    <div class="comm-topbar__left">
        <button class="comm-topbar__menu-btn" id="sidebar-mobile-toggle"
                aria-label="Open navigation" aria-expanded="false" aria-controls="comm-sidebar">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>

        <nav class="comm-topbar__breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}" class="comm-topbar__breadcrumb-link">Dentfluence</a>
            <span class="comm-topbar__breadcrumb-sep" aria-hidden="true">/</span>
            <a href="{{ route('communication.index') }}" class="comm-topbar__breadcrumb-link">Communication OS</a>
            @if(isset($pageTitle) && $pageTitle !== 'Communication OS')
                <span class="comm-topbar__breadcrumb-sep" aria-hidden="true">/</span>
                <span class="comm-topbar__breadcrumb-current">{{ $pageTitle }}</span>
            @endif
        </nav>
    </div>

    {{-- ── Right: Quick actions ────────────────────────────────────── --}}
    <div class="comm-topbar__right">

        {{-- Quick Log Communication --}}
        <a href="{{ route('communication.manager.log.form') }}"
           class="comm-topbar__action-btn comm-topbar__action-btn--primary"
           title="Log communication">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span>Log Communication</span>
        </a>

        {{-- Separator --}}
        <div class="comm-topbar__sep" aria-hidden="true"></div>

        {{-- Overdue indicator --}}
        @php $overdueCount = $navBadges['overdue_count'] ?? 0; @endphp
        @if($overdueCount > 0)
            <a href="{{ route('communication.manager.overdue') }}"
               class="comm-topbar__overdue-pill"
               title="{{ $overdueCount }} overdue items need attention"
               aria-label="{{ $overdueCount }} overdue items">
                <span class="comm-topbar__overdue-dot" aria-hidden="true"></span>
                {{ $overdueCount }} overdue
            </a>
        @endif

        {{-- Current user --}}
        <div class="comm-topbar__user">
            <div class="comm-topbar__user-avatar" aria-hidden="true">
                {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
            </div>
            <span class="comm-topbar__user-name">{{ auth()->user()->name ?? 'User' }}</span>
        </div>
    </div>

</header>
