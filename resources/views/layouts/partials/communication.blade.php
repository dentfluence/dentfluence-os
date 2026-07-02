<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Communication OS') — Dentfluence</title>

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    {{-- Tabler Icons --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

    {{-- Module CSS --}}
    <link rel="stylesheet" href="{{ asset('css/communication/module.css') }}">
    @stack('styles')
</head>
<body class="comm-body">

    {{-- ── Top Bar ──────────────────────────────────────────────────── --}}
    <header class="comm-topbar">
        <div class="comm-topbar__brand">
            <button class="comm-topbar__hamburger" id="sidebarToggle">
                <i class="ti ti-menu-2"></i>
            </button>
            <div class="comm-topbar__logo">
                <i class="ti ti-tooth"></i>
            </div>
            <div class="comm-topbar__title">
                <span class="comm-topbar__title--main">PRM</span>
                <span class="comm-topbar__title--sub">Patient Relationship Manager</span>
            </div>
        </div>

        <div class="comm-topbar__search">
            <i class="ti ti-search comm-topbar__search-icon"></i>
            <input type="text" placeholder="Search leads by name or phone" class="comm-topbar__search-input">
        </div>

        <div class="comm-topbar__actions">
            <div class="comm-topbar__notif">
                <i class="ti ti-bell"></i>
                <span class="comm-topbar__notif-badge">1</span>
            </div>
            <div class="comm-topbar__user">
                <div class="comm-avatar comm-avatar--sm" style="background:#EEEDFE;color:#534AB7">N</div>
                <div class="comm-topbar__user-info">
                    <span class="comm-topbar__user-name">Dr. Neha</span>
                    <span class="comm-topbar__user-role">Front Desk</span>
                </div>
                <i class="ti ti-chevron-down" style="font-size:14px;color:var(--c-text-muted)"></i>
            </div>
        </div>
    </header>

    <div class="comm-layout">

        {{-- ── Sidebar ──────────────────────────────────────────────── --}}
        @include('layouts.partials.communication-sidebar')

        {{-- ── Page Content ─────────────────────────────────────────── --}}
        <main class="comm-main">
            @if(session('success'))
                <div class="comm-alert comm-alert--success">
                    <i class="ti ti-circle-check"></i>
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="comm-alert comm-alert--danger">
                    <i class="ti ti-alert-circle"></i>
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    {{-- ── Make a Call FAB ──────────────────────────────────────────── --}}
    <div class="comm-fab">
        <div class="comm-fab__icon"><i class="ti ti-phone"></i></div>
        <div class="comm-fab__text">
            <span class="comm-fab__label">Make a Call</span>
            <span class="comm-fab__sub">Quick dialer</span>
        </div>
        <i class="ti ti-arrow-right comm-fab__arrow"></i>
    </div>

    {{-- Scripts --}}
    <script src="{{ asset('js/communication/navigation.js') }}"></script>
    @stack('scripts')
</body>
</html>
