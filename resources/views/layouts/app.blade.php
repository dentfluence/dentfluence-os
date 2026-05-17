{{--
|==========================================================================
| Dentfluence OS — Master Application Layout
| File: resources/views/layouts/app.blade.php
|
| Architecture:
|   <html>
|     <head>  — fonts, Tailwind, meta
|     <body>
|       #df-shell                  ← full-viewport root
|         #df-sidebar              ← fixed left column (from component)
|         #df-body-column          ← right column, flex-col
|           #df-topbar             ← fixed topbar (from component)
|           #df-content-area       ← scrollable main content
|             @yield('content')
|         #df-drawer-overlay       ← mobile sidebar backdrop
|       #df-toast-region           ← global notification portal
|
| Usage in child views:
|   @extends('layouts.app')
|   @section('page-title', 'Appointments')
|   @section('content') ... @endsection
|   @section('head-extra') ... @endsection   (optional extra head tags)
|==========================================================================
--}}
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class="h-full"
    data-sidebar="expanded"
>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1e0030">

    <title>@yield('page-title', 'Dashboard') — Dentfluence OS</title>

    {{-- ── Preconnect for fast font load ── --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Mono:wght@400;500&display=swap"
        rel="stylesheet"
    >

    {{-- ── Tailwind CDN ── --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        display: ['"Cormorant Garamond"', 'Georgia', 'serif'],
                        ui:      ['"DM Sans"',            'system-ui', 'sans-serif'],
                        mono:    ['"DM Mono"',            'ui-monospace', 'monospace'],
                    },
                    colors: {
                        /* ── Tulip Dental brand ramp ── */
                        brand: {
                            50:  '#f9f3fa',
                            100: '#f3e8f4',
                            200: '#dfc5e1',
                            300: '#b95cb7',
                            400: '#8e24aa',
                            500: '#6a0f70',
                            600: '#4e0a53',
                            700: '#380740',
                            800: '#2d0538',
                            900: '#1a0320',
                        },
                        /* ── Sidebar-specific deep palette ── */
                        sidebar: {
                            DEFAULT: '#160220',
                            hover:   '#200a2e',
                            active:  '#2a0e3a',
                            border:  'rgba(185,92,183,0.10)',
                            text:    'rgba(215,185,228,0.60)',
                            muted:   'rgba(185,150,205,0.35)',
                        },
                        /* ── Canvas / surface ── */
                        canvas:  '#f5eef9',
                        surface: '#ffffff',
                        /* ── Operational semantic ── */
                        ok:    { DEFAULT: '#1a7a45', bg: '#e8f7ef' },
                        warn:  { DEFAULT: '#a05c00', bg: '#fff4e0' },
                        err:   { DEFAULT: '#b52020', bg: '#fdeaea' },
                        info:  { DEFAULT: '#1a5ea8', bg: '#e6f0fb' },
                    },
                    spacing: {
                        sidebar:   '240px',
                        'sidebar-sm': '64px',
                        topbar:    '64px',
                    },
                    zIndex: {
                        sidebar:   '30',
                        topbar:    '40',
                        overlay:   '50',
                        modal:     '60',
                        toast:     '80',
                    },
                    boxShadow: {
                        'sidebar-right': '1px 0 0 0 rgba(185,92,183,0.08)',
                        'topbar-bottom': '0 1px 0 0 rgba(185,92,183,0.06)',
                        'input-focus':   '0 0 0 3px rgba(106,15,112,0.14)',
                    },
                    transitionDuration: {
                        sidebar: '280ms',
                    },
                }
            }
        }
    </script>

    {{-- ── Global application styles ── --}}
    <style>
        /* ── Reset ── */
        *, *::before, *::after { box-sizing: border-box; }

        /* ── Root ── */
        html, body {
            height: 100%;
            font-family: 'DM Sans', system-ui, sans-serif;
            font-size: 14px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            background: #f5eef9;
            color: #1a0a24;
        }

        /* ════════════════════════════════════════════
           SHELL — full viewport, no overflow at root
        ════════════════════════════════════════════ */
        #df-shell {
            display: flex;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
            position: relative;
        }

        /* ════════════════════════════════════════════
           SIDEBAR COLUMN
        ════════════════════════════════════════════ */
        #df-sidebar {
            width: 240px;
            flex-shrink: 0;
            height: 100vh;
            position: relative;
            z-index: 30;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: width 280ms cubic-bezier(0.4, 0, 0.2, 1);

            /* Deep luxury background */
            background:
                radial-gradient(ellipse 120% 55% at 60% 0%, rgba(140,30,170,0.40) 0%, transparent 50%),
                radial-gradient(ellipse 80%  50% at 10% 100%, rgba(90,10,120,0.32) 0%, transparent 55%),
                linear-gradient(175deg, #200a2e 0%, #160220 40%, #0e0118 100%);

            /* Right edge separator */
            box-shadow: 1px 0 0 0 rgba(185,92,183,0.10);
        }

        /* Collapsed: icon-only */
        #df-shell[data-sidebar="collapsed"] #df-sidebar {
            width: 64px;
        }

        /* Collapsed: hide text labels */
        #df-shell[data-sidebar="collapsed"] .df-nav-label,
        #df-shell[data-sidebar="collapsed"] .df-nav-section-label,
        #df-shell[data-sidebar="collapsed"] .df-sidebar-brand-sub,
        #df-shell[data-sidebar="collapsed"] .df-nav-badge-text {
            opacity: 0;
            pointer-events: none;
            width: 0;
            overflow: hidden;
        }

        /* Collapsed: center icons */
        #df-shell[data-sidebar="collapsed"] .df-nav-item {
            justify-content: center;
            padding-left: 0;
            padding-right: 0;
        }

        #df-shell[data-sidebar="collapsed"] .df-sidebar-brand {
            justify-content: center;
            padding-left: 0;
            padding-right: 0;
        }

        /* ════════════════════════════════════════════
           BODY COLUMN (topbar + content)
        ════════════════════════════════════════════ */
        #df-body-column {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        /* ════════════════════════════════════════════
           TOPBAR
        ════════════════════════════════════════════ */
        #df-topbar {
            height: 64px;
            flex-shrink: 0;
            z-index: 40;
            display: flex;
            align-items: center;
            background: #ffffff;
            border-bottom: 1px solid rgba(185,92,183,0.10);
            padding: 0 28px;
            gap: 16px;
        }

        /* ════════════════════════════════════════════
           CONTENT AREA — owns the scroll
        ════════════════════════════════════════════ */
        #df-content-area {
            flex: 1;
            min-height: 0;     /* critical: allows shrinking in flex */
            overflow-y: auto;
            overflow-x: hidden;
            background: #f5eef9;
        }

        /* Scrollbar — slim, brand-tinted */
        #df-content-area::-webkit-scrollbar         { width: 5px; }
        #df-content-area::-webkit-scrollbar-track   { background: transparent; }
        #df-content-area::-webkit-scrollbar-thumb   { background: rgba(185,92,183,0.28); }
        #df-content-area::-webkit-scrollbar-thumb:hover { background: rgba(106,15,112,0.45); }

        /* Inner content wrapper — max-width + consistent padding */
        #df-content-inner {
            max-width: 1440px;
            width: 100%;
            margin: 0 auto;
            padding: 28px 32px 48px;
        }

        /* ════════════════════════════════════════════
           SIDEBAR INNER SCROLL (nav items)
        ════════════════════════════════════════════ */
        #df-sidebar-nav {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 8px 0;
        }
        #df-sidebar-nav::-webkit-scrollbar         { width: 3px; }
        #df-sidebar-nav::-webkit-scrollbar-track   { background: transparent; }
        #df-sidebar-nav::-webkit-scrollbar-thumb   { background: rgba(185,92,183,0.20); }

        /* ════════════════════════════════════════════
           MOBILE DRAWER OVERLAY
        ════════════════════════════════════════════ */
        #df-drawer-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 25;
            background: rgba(14, 1, 24, 0.55);
            backdrop-filter: blur(2px);
        }
        #df-drawer-overlay.visible { display: block; }

        /* Mobile: sidebar becomes drawer */
        @media (max-width: 767px) {
            #df-sidebar {
                position: fixed;
                left: 0; top: 0; bottom: 0;
                width: 240px !important;
                z-index: 50;
                transform: translateX(-100%);
                transition: transform 280ms cubic-bezier(0.4, 0, 0.2, 1),
                            box-shadow 280ms;
                box-shadow: none;
            }
            #df-sidebar.drawer-open {
                transform: translateX(0);
                box-shadow: 4px 0 24px rgba(14,1,24,0.45);
            }
            #df-body-column { width: 100%; }
            #df-content-inner { padding: 20px 16px 40px; }
            #df-topbar { padding: 0 16px; }
        }

        /* Tablet: auto-collapse sidebar to icon-only */
        @media (min-width: 768px) and (max-width: 1199px) {
            #df-sidebar { width: 64px; }
            .df-nav-label,
            .df-nav-section-label,
            .df-sidebar-brand-sub,
            .df-nav-badge-text {
                opacity: 0;
                pointer-events: none;
                width: 0;
                overflow: hidden;
            }
            .df-nav-item { justify-content: center; padding-left: 0; padding-right: 0; }
            .df-sidebar-brand { justify-content: center; padding-left: 0; padding-right: 0; }
        }

        /* ════════════════════════════════════════════
           TOAST NOTIFICATION REGION
        ════════════════════════════════════════════ */
        #df-toast-region {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 80;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
            max-width: 360px;
            width: calc(100vw - 48px);
        }

        /* Individual toast — injected via JS */
        .df-toast {
            pointer-events: all;
            background: #ffffff;
            border: 1px solid #ede8e0;
            border-left: 3px solid #6a0f70;
            border-radius: 3px;
            padding: 12px 14px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 400;
            color: #1a0a24;
            box-shadow: 0 4px 12px rgba(14,1,24,0.10);
            animation: toastIn 150ms cubic-bezier(0.16,1,0.3,1) both;
        }
        .df-toast.toast-success  { border-left-color: #1a7a45; }
        .df-toast.toast-error    { border-left-color: #b52020; }
        .df-toast.toast-warning  { border-left-color: #a05c00; }
        .df-toast.toast-info     { border-left-color: #1a5ea8; }
        .df-toast.toast-out      { animation: toastOut 150ms ease-in both; }

        @keyframes toastIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0);   }
        }
        @keyframes toastOut {
            from { opacity: 1; transform: translateY(0); max-height: 100px; }
            to   { opacity: 0; transform: translateY(4px); max-height: 0; padding: 0; margin: 0; }
        }

        /* ════════════════════════════════════════════
           PAGE HEADER (standardised, used in yield)
        ════════════════════════════════════════════ */
        .df-page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            gap: 16px;
            flex-wrap: wrap;
        }
        .df-page-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 28px;
            font-weight: 600;
            color: #1e0a2c;
            line-height: 1.1;
            letter-spacing: -0.01em;
        }
        .df-page-subtitle {
            font-size: 13px;
            font-weight: 300;
            color: #7a6884;
            margin-top: 3px;
        }
        .df-page-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        /* ════════════════════════════════════════════
           SKELETON LOADING STATE (reusable utility)
        ════════════════════════════════════════════ */
        .df-skeleton {
            background: #e8dff0;
            border-radius: 2px;
            position: relative;
            overflow: hidden;
        }
        .df-skeleton::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(
                90deg,
                transparent 0%,
                rgba(255,255,255,0.40) 50%,
                transparent 100%
            );
            animation: skeletonShimmer 1.6s ease-in-out infinite;
        }
        @keyframes skeletonShimmer {
            0%   { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        /* ════════════════════════════════════════════
           FOCUS RING — global accessible default
        ════════════════════════════════════════════ */
        :focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px rgba(106,15,112,0.22);
        }

        /* ════════════════════════════════════════════
           UTILITY — content section card
        ════════════════════════════════════════════ */
        .df-card {
            background: #ffffff;
            border: 1px solid rgba(185,92,183,0.12);
            border-radius: 3px;
        }
        .df-card-header {
            padding: 14px 20px;
            border-bottom: 1px solid rgba(185,92,183,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #faf5fb;
        }
        .df-card-body { padding: 20px; }

        /* ════════════════════════════════════════════
           PRINT
        ════════════════════════════════════════════ */
        @media print {
            #df-sidebar, #df-topbar, #df-toast-region, #df-drawer-overlay {
                display: none !important;
            }
            #df-body-column {
                width: 100% !important;
                height: auto !important;
            }
            #df-content-area {
                overflow: visible !important;
                height: auto !important;
            }
        }
    </style>

    {{-- ── Page-specific head injection ── --}}
    @yield('head-extra')
    @stack('styles')
</head>

<body class="h-full antialiased">

{{-- ══════════════════════════════════════════════════════════════
     APP SHELL — root container
═══════════════════════════════════════════════════════════════ --}}
<div id="df-shell" aria-label="Dentfluence OS application">

    {{-- ── SIDEBAR COLUMN ─────────────────────────────────────── --}}
    {{-- Rendered by resources/views/components/sidebar.blade.php --}}
    @include('components.sidebar')

    {{-- ── BODY COLUMN (topbar + scrollable content) ──────────── --}}
    <div id="df-body-column">

        {{-- ── TOPBAR ──────────────────────────────────────────── --}}
        {{-- Rendered by resources/views/components/topbar.blade.php --}}
        @include('components.topbar')

        {{-- ── MAIN CONTENT AREA ──────────────────────────────── --}}
        <main
            id="df-content-area"
            role="main"
            aria-label="Main content"
            tabindex="-1"
        >
            {{-- Skip-to-content target (accessibility) --}}
            <a
                id="df-skip-target"
                tabindex="-1"
                style="position:absolute;opacity:0;pointer-events:none;"
                aria-hidden="true"
            ></a>

            <div id="df-content-inner">

                {{-- ── Page-level flash messages ─────────────── --}}
                @if (session('success'))
                    <div
                        role="alert"
                        class="df-flash df-flash-success"
                        style="
                            display:flex; align-items:flex-start; gap:10px;
                            padding:12px 16px; margin-bottom:20px;
                            background:#e8f7ef;
                            border:1px solid rgba(26,122,69,0.22);
                            border-left:3px solid #1a7a45;
                            border-radius:3px;
                            font-size:13px; font-weight:400; color:#0e4a28;
                        "
                    >
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#1a7a45" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        <span>{{ session('success') }}</span>
                        <button
                            onclick="this.parentElement.remove()"
                            style="margin-left:auto;background:none;border:none;cursor:pointer;color:#1a7a45;padding:2px;flex-shrink:0;"
                            aria-label="Dismiss"
                        >
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                @endif

                @if (session('error'))
                    <div
                        role="alert"
                        class="df-flash df-flash-error"
                        style="
                            display:flex; align-items:flex-start; gap:10px;
                            padding:12px 16px; margin-bottom:20px;
                            background:#fdeaea;
                            border:1px solid rgba(181,32,32,0.22);
                            border-left:3px solid #b52020;
                            border-radius:3px;
                            font-size:13px; font-weight:400; color:#6b1010;
                        "
                    >
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#b52020" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;">
                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <span>{{ session('error') }}</span>
                        <button
                            onclick="this.parentElement.remove()"
                            style="margin-left:auto;background:none;border:none;cursor:pointer;color:#b52020;padding:2px;flex-shrink:0;"
                            aria-label="Dismiss"
                        >
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                @endif

                @if (session('warning'))
                    <div
                        role="alert"
                        style="
                            display:flex; align-items:flex-start; gap:10px;
                            padding:12px 16px; margin-bottom:20px;
                            background:#fff4e0;
                            border:1px solid rgba(160,92,0,0.22);
                            border-left:3px solid #a05c00;
                            border-radius:3px;
                            font-size:13px; font-weight:400; color:#5c3500;
                        "
                    >
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#a05c00" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;">
                            <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        <span>{{ session('warning') }}</span>
                        <button
                            onclick="this.parentElement.remove()"
                            style="margin-left:auto;background:none;border:none;cursor:pointer;color:#a05c00;padding:2px;flex-shrink:0;"
                            aria-label="Dismiss"
                        >
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                @endif

                {{-- ════════════════════════════════════════════
                     PAGE CONTENT — child views inject here
                ════════════════════════════════════════════ --}}
                @yield('content')

            </div>{{-- /#df-content-inner --}}
        </main>{{-- /#df-content-area --}}

    </div>{{-- /#df-body-column --}}

    {{-- ── MOBILE DRAWER BACKDROP ──────────────────────────────── --}}
    <div
        id="df-drawer-overlay"
        role="presentation"
        onclick="DFLayout.closeSidebar()"
        aria-hidden="true"
    ></div>

</div>{{-- /#df-shell --}}


{{-- ══════════════════════════════════════════════════════════════
     TOAST NOTIFICATION REGION
     Toasts are programmatically injected here via DFLayout.toast()
═══════════════════════════════════════════════════════════════ --}}
<div
    id="df-toast-region"
    role="region"
    aria-label="Notifications"
    aria-live="polite"
    aria-atomic="false"
></div>


{{-- ══════════════════════════════════════════════════════════════
     LAYOUT JAVASCRIPT
     Handles: sidebar toggle · mobile drawer · toast API ·
              keyboard shortcuts · active-nav detection
═══════════════════════════════════════════════════════════════ --}}
<script>
(function () {
    'use strict';

    /* ── Constants ── */
    var STORAGE_KEY   = 'df_sidebar';
    var shell         = document.getElementById('df-shell');
    var sidebar       = document.getElementById('df-sidebar');
    var drawerOverlay = document.getElementById('df-drawer-overlay');
    var toastRegion   = document.getElementById('df-toast-region');

    /* ─────────────────────────────────────────────────────────
       isMobile — ≤767px
    ───────────────────────────────────────────────────────── */
    function isMobile() {
        return window.innerWidth <= 767;
    }

    /* ─────────────────────────────────────────────────────────
       SIDEBAR STATE MANAGEMENT
    ───────────────────────────────────────────────────────── */
    function getSavedState() {
        try {
            return localStorage.getItem(STORAGE_KEY) || 'expanded';
        } catch (e) {
            return 'expanded';
        }
    }

    function saveState(state) {
        try {
            localStorage.setItem(STORAGE_KEY, state);
        } catch (e) {}
    }

    function applyState(state) {
        if (!shell) return;
        shell.setAttribute('data-sidebar', state);
    }

    /* Restore persisted state on load (desktop only) */
    if (!isMobile()) {
        applyState(getSavedState());
    }

    /* ─────────────────────────────────────────────────────────
       PUBLIC API — window.DFLayout
    ───────────────────────────────────────────────────────── */
    window.DFLayout = {

        /* Toggle sidebar (desktop: expand/collapse; mobile: drawer) */
        toggleSidebar: function () {
            if (isMobile()) {
                if (sidebar && sidebar.classList.contains('drawer-open')) {
                    this.closeSidebar();
                } else {
                    this.openSidebar();
                }
                return;
            }
            var current = shell ? shell.getAttribute('data-sidebar') : 'expanded';
            var next    = current === 'expanded' ? 'collapsed' : 'expanded';
            applyState(next);
            saveState(next);
        },

        openSidebar: function () {
            if (sidebar)       sidebar.classList.add('drawer-open');
            if (drawerOverlay) drawerOverlay.classList.add('visible');
            document.body.style.overflow = 'hidden';
        },

        closeSidebar: function () {
            if (sidebar)       sidebar.classList.remove('drawer-open');
            if (drawerOverlay) drawerOverlay.classList.remove('visible');
            document.body.style.overflow = '';
        },

        /* ── Toast API ────────────────────────────────────────
           Usage:
             DFLayout.toast('Patient saved successfully.', 'success')
             DFLayout.toast('Sync failed. Please retry.', 'error')
             DFLayout.toast('Pending balance detected.', 'warning')
             DFLayout.toast('Lab order updated.', 'info')
        ─────────────────────────────────────────────────────── */
        toast: function (message, type, duration) {
            if (!toastRegion) return;
            type     = type     || 'info';
            duration = duration || (type === 'error' ? 8000 : type === 'warning' ? 6000 : 4000);

            var icons = {
                success: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
                error:   '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
                warning: '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
                info:    '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="8"/><line x1="12" y1="12" x2="12" y2="16"/>',
            };
            var colors = {
                success: '#1a7a45',
                error:   '#b52020',
                warning: '#a05c00',
                info:    '#1a5ea8',
            };

            var toast = document.createElement('div');
            toast.className = 'df-toast toast-' + type;
            toast.setAttribute('role', 'status');
            toast.innerHTML =
                '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="' + (colors[type] || colors.info) + '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;">' +
                    (icons[type] || icons.info) +
                '</svg>' +
                '<span style="flex:1;line-height:1.55;">' + message + '</span>' +
                '<button onclick="DFLayout._removeToast(this.parentElement)" style="background:none;border:none;cursor:pointer;color:' + (colors[type] || colors.info) + ';padding:2px;flex-shrink:0;margin-left:4px;" aria-label="Dismiss">' +
                    '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
                '</button>';

            toastRegion.appendChild(toast);

            /* Max 3 visible toasts — remove oldest non-error */
            var all = toastRegion.querySelectorAll('.df-toast');
            if (all.length > 3) {
                for (var i = 0; i < all.length - 3; i++) {
                    if (!all[i].classList.contains('toast-error')) {
                        DFLayout._removeToast(all[i]);
                        break;
                    }
                }
            }

            /* Auto-dismiss */
            if (type !== 'error') {
                setTimeout(function () {
                    DFLayout._removeToast(toast);
                }, duration);
            }
        },

        _removeToast: function (el) {
            if (!el || !el.parentNode) return;
            el.classList.add('toast-out');
            setTimeout(function () {
                if (el.parentNode) el.parentNode.removeChild(el);
            }, 160);
        },

    };

    /* ─────────────────────────────────────────────────────────
       KEYBOARD SHORTCUTS
    ───────────────────────────────────────────────────────── */
    document.addEventListener('keydown', function (e) {
        /* Ctrl+B — toggle sidebar */
        if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
            e.preventDefault();
            DFLayout.toggleSidebar();
        }

        /* Escape — close mobile drawer */
        if (e.key === 'Escape' && isMobile()) {
            DFLayout.closeSidebar();
        }
    });

    /* ─────────────────────────────────────────────────────────
       ACTIVE NAV ITEM — highlight based on current URL
    ───────────────────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        var path  = window.location.pathname;
        var links = document.querySelectorAll('[data-nav-href]');
        links.forEach(function (link) {
            var href = link.getAttribute('data-nav-href');
            if (href && path.startsWith(href) && href !== '/') {
                link.classList.add('df-nav-active');
            } else if (href === '/' && path === '/') {
                link.classList.add('df-nav-active');
            }
        });
    });

    /* ─────────────────────────────────────────────────────────
       RESIZE — reset mobile state on widen
    ───────────────────────────────────────────────────────── */
    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (!isMobile()) {
                DFLayout.closeSidebar();
                document.body.style.overflow = '';
            }
        }, 120);
    });

})();
</script>

{{-- ── Page-specific scripts injected by child views ── --}}
@stack('scripts')

</body>
</html>
