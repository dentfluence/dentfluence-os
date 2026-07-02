{{--
|==========================================================================
| Dentfluence OS — Marketing Module Layout
| File: resources/views/marketing/layouts/app.blade.php
|
| Architecture:
|   Extends layouts.app (inherits sidebar + topbar + shell).
|   Injects a full-width secondary tab nav into the content slot.
|   Child views extend THIS layout and fill @section('marketing-content').
|
| Usage in child views:
|   @extends('marketing.layouts.app')
|   @section('page-title', 'Marketing — Overview')
|   @section('marketing-content') ... @endsection
|==========================================================================
--}}
@extends('layouts.app')

{{-- ── Let child views override the browser title ── --}}
@section('page-title', 'Marketing — ' . ($marketingPageTitle ?? 'Overview'))

@section('content')

{{-- ══════════════════════════════════════════════════════════════
     MARKETING SUB-NAV
     Full-width strip that sits flush with the content area top.
     Negative margins cancel #df-content-inner padding so the bar
     spans edge-to-edge, then re-applies horizontal padding for tabs.
═══════════════════════════════════════════════════════════════ --}}
<div id="mkt-subnav" style="
    margin: -28px -32px 28px;
    background: #ffffff;
    border-bottom: 1px solid rgba(185,92,183,0.13);
    box-shadow: 0 1px 0 0 rgba(185,92,183,0.06);
    position: sticky;
    top: 0;
    z-index: 20;
">
    <div style="
        display: flex;
        align-items: stretch;
        gap: 0;
        padding: 0 32px;
        overflow-x: auto;
        scrollbar-width: none;
    ">
        {{-- ── 10 TABS ─────────────────────────────────────────── --}}

        {{--
            Helper macro: is $route active?
            We check routeIs() so sub-routes (campaigns.show) also highlight.
        --}}

        {{-- 1 — Overview --}}
        <a
            href="{{ route('marketing.overview') }}"
            title="Overview"
            style="
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 0 14px;
                height: 52px;
                font-family: 'Inter', sans-serif;
                font-size: 13px;
                font-weight: {{ request()->routeIs('marketing.overview*') ? '600' : '400' }};
                color: {{ request()->routeIs('marketing.overview*') ? '#6a0f70' : '#5a4868' }};
                text-decoration: none;
                white-space: nowrap;
                border-bottom: 2px solid {{ request()->routeIs('marketing.overview*') ? '#6a0f70' : 'transparent' }};
                transition: color 150ms, border-color 150ms;
                flex-shrink: 0;
            "
            onmouseover="if(!this.style.borderBottomColor.includes('6a0f70'))this.style.color='#1e0a2c'"
            onmouseout="if(!this.style.borderBottomColor.includes('6a0f70'))this.style.color='#5a4868'"
        >
            {{-- Overview icon --}}
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
            </svg>
            Overview
        </a>

        {{-- 2 — Publish --}}
        <a
            href="{{ route('marketing.publish') }}"
            title="Publish"
            style="
                display: inline-flex; align-items: center; gap: 6px;
                padding: 0 14px; height: 52px;
                font-family: 'Inter', sans-serif; font-size: 13px;
                font-weight: {{ request()->routeIs('marketing.publish*') ? '600' : '400' }};
                color: {{ request()->routeIs('marketing.publish*') ? '#6a0f70' : '#5a4868' }};
                text-decoration: none; white-space: nowrap;
                border-bottom: 2px solid {{ request()->routeIs('marketing.publish*') ? '#6a0f70' : 'transparent' }};
                transition: color 150ms, border-color 150ms; flex-shrink: 0;
            "
            onmouseover="if(!this.style.borderBottomColor.includes('6a0f70'))this.style.color='#1e0a2c'"
            onmouseout="if(!this.style.borderBottomColor.includes('6a0f70'))this.style.color='#5a4868'"
        >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 19V5M5 12l7-7 7 7"/>
            </svg>
            Publish
        </a>

        {{-- 3 — Calendar --}}
        <a
            href="{{ route('marketing.calendar') }}"
            title="Calendar"
            style="
                display: inline-flex; align-items: center; gap: 6px;
                padding: 0 14px; height: 52px;
                font-family: 'Inter', sans-serif; font-size: 13px;
                font-weight: {{ request()->routeIs('marketing.calendar*') ? '600' : '400' }};
                color: {{ request()->routeIs('marketing.calendar*') ? '#6a0f70' : '#5a4868' }};
                text-decoration: none; white-space: nowrap;
                border-bottom: 2px solid {{ request()->routeIs('marketing.calendar*') ? '#6a0f70' : 'transparent' }};
                transition: color 150ms, border-color 150ms; flex-shrink: 0;
            "
            onmouseover="if(!this.style.borderBottomColor.includes('6a0f70'))this.style.color='#1e0a2c'"
            onmouseout="if(!this.style.borderBottomColor.includes('6a0f70'))this.style.color='#5a4868'"
        >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            Calendar
        </a>

        {{-- 4 — Brainstorm --}}
        <a
            href="{{ route('marketing.brainstorm') }}"
            title="Brainstorm"
            style="
                display: inline-flex; align-items: center; gap: 6px;
                padding: 0 14px; height: 52px;
                font-family: 'Inter', sans-serif; font-size: 13px;
                font-weight: {{ request()->routeIs('marketing.brainstorm*') ? '600' : '400' }};
                color: {{ request()->routeIs('marketing.brainstorm*') ? '#6a0f70' : '#5a4868' }};
                text-decoration: none; white-space: nowrap;
                border-bottom: 2px solid {{ request()->routeIs('marketing.brainstorm*') ? '#6a0f70' : 'transparent' }};
                transition: color 150ms, border-color 150ms; flex-shrink: 0;
            "
            onmouseover="if(!this.style.borderBottomColor.includes('6a0f70'))this.style.color='#1e0a2c'"
            onmouseout="if(!this.style.borderBottomColor.includes('6a0f70'))this.style.color='#5a4868'"
        >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
            </svg>
            Brainstorm
        </a>

        {{-- 5 — Campaigns --}}
        <a
            href="{{ route('marketing.campaigns.index') }}"
            title="Campaigns"
            style="
                display: inline-flex; align-items: center; gap: 6px;
                padding: 0 14px; height: 52px;
                font-family: 'Inter', sans-serif; font-size: 13px;
                font-weight: {{ request()->routeIs('marketing.campaigns*') ? '600' : '400' }};
                color: {{ request()->routeIs('marketing.campaigns*') ? '#6a0f70' : '#5a4868' }};
                text-decoration: none; white-space: nowrap;
                border-bottom: 2px solid {{ request()->routeIs('marketing.campaigns*') ? '#6a0f70' : 'transparent' }};
                transition: color 150ms, border-color 150ms; flex-shrink: 0;
            "
            onmouseover="if(!this.style.borderBottomColor.includes('6a0f70'))this.style.color='#1e0a2c'"
            onmouseout="if(!this.style.borderBottomColor.includes('6a0f70'))this.style.color='#5a4868'"
        >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
            </svg>
            Campaigns
        </a>

        {{-- 6 — Library --}}
        <a
            href="{{ route('marketing.library') }}"
            title="Library"
            style="
                display: inline-flex; align-items: center; gap: 6px;
                padding: 0 14px; height: 52px;
                font-family: 'Inter', sans-serif; font-size: 13px;
                font-weight: {{ request()->routeIs('marketing.library*') ? '600' : '400' }};
                color: {{ request()->routeIs('marketing.library*') ? '#6a0f70' : '#5a4868' }};
                text-decoration: none; white-space: nowrap;
                border-bottom: 2px solid {{ request()->routeIs('marketing.library*') ? '#6a0f70' : 'transparent' }};
                transition: color 150ms, border-color 150ms; flex-shrink: 0;
            "
            onmouseover="if(!this.style.borderBottomColor.includes('6a0f70'))this.style.color='#1e0a2c'"
            onmouseout="if(!this.style.borderBottomColor.includes('6a0f70'))this.style.color='#5a4868'"
        >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/>
            </svg>
            Library
        </a>

        {{-- 7 — Brand Kit --}}
        <a
            href="{{ route('marketing.brand-kit') }}"
            title="Brand Kit"
            style="
                display: inline-flex; align-items: center; gap: 6px;
                padding: 0 14px; height: 52px;
                font-family: 'Inter', sans-serif; font-size: 13px;
                font-weight: {{ request()->routeIs('marketing.brand-kit*') ? '600' : '400' }};
                color: {{ request()->routeIs('marketing.brand-kit*') ? '#6a0f70' : '#5a4868' }};
                text-decoration: none; white-space: nowrap;
                border-bottom: 2px solid {{ request()->routeIs('marketing.brand-kit*') ? '#6a0f70' : 'transparent' }};
                transition: color 150ms, border-color 150ms; flex-shrink: 0;
            "
            onmouseover="if(!this.style.borderBottomColor.includes('6a0f70'))this.style.color='#1e0a2c'"
            onmouseout="if(!this.style.borderBottomColor.includes('6a0f70'))this.style.color='#5a4868'"
        >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><path d="M8.56 2.75c4.37 6.03 6.02 9.42 8.03 17.72m2.54-15.38c-3.72 4.35-8.94 5.66-16.88 5.85m19.5 1.9c-3.5-.93-6.63-.82-8.94 0-2.58.92-5.01 2.86-7.44 6.32"/>
            </svg>
            Brand Kit
        </a>

        {{-- 8 — Integrations --}}
        <a
            href="{{ route('marketing.integrations') }}"
            title="Integrations"
            style="
                display: inline-flex; align-items: center; gap: 6px;
                padding: 0 14px; height: 52px;
                font-family: 'Inter', sans-serif; font-size: 13px;
                font-weight: {{ request()->routeIs('marketing.integrations*') ? '600' : '400' }};
                color: {{ request()->routeIs('marketing.integrations*') ? '#6a0f70' : '#5a4868' }};
                text-decoration: none; white-space: nowrap;
                border-bottom: 2px solid {{ request()->routeIs('marketing.integrations*') ? '#6a0f70' : 'transparent' }};
                transition: color 150ms, border-color 150ms; flex-shrink: 0;
            "
            onmouseover="if(!this.style.borderBottomColor.includes('6a0f70'))this.style.color='#1e0a2c'"
            onmouseout="if(!this.style.borderBottomColor.includes('6a0f70'))this.style.color='#5a4868'"
        >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/>
            </svg>
            Integrations
        </a>

        {{-- 9 — Analytics --}}
        <a
            href="{{ route('marketing.analytics') }}"
            title="Analytics"
            style="
                display: inline-flex; align-items: center; gap: 6px;
                padding: 0 14px; height: 52px;
                font-family: 'Inter', sans-serif; font-size: 13px;
                font-weight: {{ request()->routeIs('marketing.analytics*') ? '600' : '400' }};
                color: {{ request()->routeIs('marketing.analytics*') ? '#6a0f70' : '#5a4868' }};
                text-decoration: none; white-space: nowrap;
                border-bottom: 2px solid {{ request()->routeIs('marketing.analytics*') ? '#6a0f70' : 'transparent' }};
                transition: color 150ms, border-color 150ms; flex-shrink: 0;
            "
            onmouseover="if(!this.style.borderBottomColor.includes('6a0f70'))this.style.color='#1e0a2c'"
            onmouseout="if(!this.style.borderBottomColor.includes('6a0f70'))this.style.color='#5a4868'"
        >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
            </svg>
            Analytics
        </a>

        {{-- 10 — Settings --}}
        <a
            href="{{ route('marketing.settings') }}"
            title="Settings"
            style="
                display: inline-flex; align-items: center; gap: 6px;
                padding: 0 14px; height: 52px;
                font-family: 'Inter', sans-serif; font-size: 13px;
                font-weight: {{ request()->routeIs('marketing.settings*') ? '600' : '400' }};
                color: {{ request()->routeIs('marketing.settings*') ? '#6a0f70' : '#5a4868' }};
                text-decoration: none; white-space: nowrap;
                border-bottom: 2px solid {{ request()->routeIs('marketing.settings*') ? '#6a0f70' : 'transparent' }};
                transition: color 150ms, border-color 150ms; flex-shrink: 0;
            "
            onmouseover="if(!this.style.borderBottomColor.includes('6a0f70'))this.style.color='#1e0a2c'"
            onmouseout="if(!this.style.borderBottomColor.includes('6a0f70'))this.style.color='#5a4868'"
        >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>
            </svg>
            Settings
        </a>

        {{-- ── SPACER + SEARCH + USER ──────────────────────────── --}}
        <div style="flex: 1; min-width: 16px;"></div>

        {{-- Search --}}
        <div style="
            display: flex;
            align-items: center;
            align-self: center;
            gap: 8px;
            margin-right: 12px;
        ">
            <div style="
                display: flex;
                align-items: center;
                gap: 7px;
                background: #f9f3fa;
                border: 1px solid rgba(185,92,183,0.18);
                border-radius: 3px;
                padding: 0 10px;
                height: 34px;
                width: 220px;
            ">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input
                    type="search"
                    placeholder="Search patient, content, campaign..."
                    style="
                        border: none;
                        background: transparent;
                        outline: none;
                        font-family: 'Inter', sans-serif;
                        font-size: 12.5px;
                        color: #1e0a2c;
                        width: 100%;
                    "
                    aria-label="Search marketing"
                >
            </div>
        </div>

        {{-- User avatar + name + role --}}
        @auth
        <div style="
            display: flex;
            align-items: center;
            gap: 9px;
            align-self: center;
            padding-left: 12px;
            border-left: 1px solid rgba(185,92,183,0.12);
            height: 36px;
            flex-shrink: 0;
        ">
            {{-- Avatar circle with initials --}}
            <div style="
                width: 30px;
                height: 30px;
                border-radius: 50%;
                background: linear-gradient(135deg, #6a0f70 0%, #b95cb7 100%);
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Inter', sans-serif;
                font-size: 11px;
                font-weight: 600;
                color: #fff;
                flex-shrink: 0;
                letter-spacing: 0.02em;
            ">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}{{ strtoupper(substr(strrchr(auth()->user()->name, ' '), 1, 1)) }}
            </div>
            <div style="line-height: 1.2;">
                <div style="
                    font-family: 'Inter', sans-serif;
                    font-size: 12.5px;
                    font-weight: 500;
                    color: #1e0a2c;
                    white-space: nowrap;
                ">{{ auth()->user()->name }}</div>
                <div style="
                    font-family: 'Inter', sans-serif;
                    font-size: 11px;
                    font-weight: 300;
                    color: #9b6aad;
                    white-space: nowrap;
                    text-transform: capitalize;
                ">{{ auth()->user()->role ?? 'Staff' }}</div>
            </div>
        </div>
        @endauth

    </div>{{-- /tab strip --}}
</div>{{-- /#mkt-subnav --}}


{{-- ══════════════════════════════════════════════════════════════
     MARKETING CONTENT SLOT
     Child views fill this section.
═══════════════════════════════════════════════════════════════ --}}
@yield('marketing-content')

@endsection
