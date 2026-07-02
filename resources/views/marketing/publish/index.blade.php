{{--
|==========================================================================
| Marketing — Universal Publish
| File: resources/views/marketing/publish/index.blade.php
|
| Layout: 3-panel flex row
|   Panel 1 (40%) — Content (swaps per tab via Alpine x-show)
|                   universal       → _master-content.blade.php
|                   instagram       → _panel1-instagram.blade.php
|                   facebook        → _panel1-facebook.blade.php
|                   google_business → _panel1-google.blade.php
|                   blog            → _panel1-blog.blade.php
|                   whatsapp        → _panel1-whatsapp.blade.php
|   Panel 2 (40%) — Platform Preview → _platform-previews.blade.php
|   Panel 3 (20%) — Publish Settings → _publish-panel.blade.php
|
| Phase 2.4-B complete.
| Alpine.js: activeTab drives both Panel 1 swap and sub-tab underline.
|==========================================================================
--}}
@extends('marketing.layouts.app')

@php $marketingPageTitle = 'Publish'; @endphp
@section('page-title', 'Marketing — Universal Publish')

@section('marketing-content')

{{-- ══════════════════════════════════════════════════════════════
     PAGE SHELL — Alpine scope wraps everything so sub-tabs and
     panel state share the same x-data instance.
═══════════════════════════════════════════════════════════════ --}}
<div x-data="{
    activeTab: 'universal',
    tabs: [
        { key: 'universal',        label: 'Universal' },
        { key: 'instagram',        label: 'Instagram' },
        { key: 'facebook',         label: 'Facebook' },
        { key: 'google_business',  label: 'Google Business' },
        { key: 'blog',             label: 'Blog' },
        { key: 'whatsapp',         label: 'WhatsApp' },
    ]
}">

    {{-- ── PAGE HEADER ──────────────────────────────────────────── --}}
    <div style="
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    ">
        {{-- Title + Subtitle --}}
        <div>
            <h1 style="
                font-family: 'Cormorant Garamond', serif;
                font-size: 26px;
                font-weight: 700;
                color: #1e0a2c;
                margin: 0 0 4px;
                line-height: 1.2;
            ">Universal Publish</h1>
            <p style="
                font-family: 'Inter', sans-serif;
                font-size: 13px;
                font-weight: 300;
                color: #7a6884;
                margin: 0;
            ">Create once. Publish everywhere.</p>
        </div>

        {{-- Action Buttons --}}
        <div style="display: flex; align-items: center; gap: 10px; flex-shrink: 0;">
            {{-- Save as Draft --}}
            <button type="button" style="
                display: inline-flex;
                align-items: center;
                gap: 7px;
                height: 38px;
                padding: 0 18px;
                font-family: 'Inter', sans-serif;
                font-size: 13px;
                font-weight: 500;
                color: #6a0f70;
                background: #ffffff;
                border: 1.5px solid rgba(106,15,112,0.28);
                border-radius: 6px;
                cursor: pointer;
                transition: background 150ms, border-color 150ms;
            "
            onmouseover="this.style.background='#faf3fb';this.style.borderColor='rgba(106,15,112,0.5)'"
            onmouseout="this.style.background='#ffffff';this.style.borderColor='rgba(106,15,112,0.28)'"
            >
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                </svg>
                Save as Draft
            </button>

            {{-- Schedule / Publish --}}
            <button type="button" style="
                display: inline-flex;
                align-items: center;
                gap: 7px;
                height: 38px;
                padding: 0 18px;
                font-family: 'Inter', sans-serif;
                font-size: 13px;
                font-weight: 600;
                color: #ffffff;
                background: linear-gradient(135deg, #6a0f70 0%, #9b3da0 100%);
                border: none;
                border-radius: 6px;
                cursor: pointer;
                box-shadow: 0 2px 8px rgba(106,15,112,0.28);
                transition: opacity 150ms, box-shadow 150ms;
            "
            onmouseover="this.style.opacity='0.9';this.style.boxShadow='0 4px 14px rgba(106,15,112,0.38)'"
            onmouseout="this.style.opacity='1';this.style.boxShadow='0 2px 8px rgba(106,15,112,0.28)'"
            >
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 19V5M5 12l7-7 7 7"/>
                </svg>
                Schedule / Publish
                {{-- Dropdown caret --}}
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-left:2px;opacity:0.8;">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>
        </div>
    </div>
    {{-- /PAGE HEADER --}}

    {{-- ── PLATFORM SUB-TABS ────────────────────────────────────── --}}
    <div style="
        display: flex;
        align-items: stretch;
        gap: 0;
        background: #ffffff;
        border: 1px solid rgba(185,92,183,0.14);
        border-radius: 8px 8px 0 0;
        border-bottom: none;
        overflow-x: auto;
        scrollbar-width: none;
        padding: 0 4px;
    ">
        <template x-for="tab in tabs" :key="tab.key">
            <button
                type="button"
                @click="activeTab = tab.key"
                :style="`
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 0 16px;
                    height: 44px;
                    font-family: 'Inter', sans-serif;
                    font-size: 13px;
                    font-weight: ${activeTab === tab.key ? '600' : '400'};
                    color: ${activeTab === tab.key ? '#6a0f70' : '#5a4868'};
                    background: transparent;
                    border: none;
                    border-bottom: 2.5px solid ${activeTab === tab.key ? '#6a0f70' : 'transparent'};
                    cursor: pointer;
                    white-space: nowrap;
                    flex-shrink: 0;
                    transition: color 150ms, border-color 150ms;
                `"
            >
                {{-- Platform icon --}}
                <span x-show="tab.key === 'universal'">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" :stroke="activeTab==='universal' ? '#6a0f70' : '#5a4868'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/>
                    </svg>
                </span>
                <span x-text="tab.label"></span>
            </button>
        </template>
    </div>
    {{-- /PLATFORM SUB-TABS --}}

    {{-- ── 3-PANEL LAYOUT ──────────────────────────────────────── --}}
    <div style="
        display: flex;
        align-items: flex-start;
        gap: 0;
        background: #ffffff;
        border: 1px solid rgba(185,92,183,0.14);
        border-radius: 0 0 8px 8px;
        min-height: calc(100vh - 260px);
        overflow: hidden;
    ">

        {{-- PANEL 1 — Content (40%) — swaps per active tab via x-show --}}
        <div style="
            flex: 0 0 40%;
            width: 40%;
            min-width: 320px;
            border-right: 1px solid rgba(185,92,183,0.10);
            height: 100%;
            overflow-y: auto;
        ">
            {{-- Universal (default) --}}
            <div x-show="activeTab === 'universal'" x-transition.opacity.duration.150ms>
                @include('marketing.publish.partials._master-content')
            </div>

            {{-- Instagram --}}
            <div x-show="activeTab === 'instagram'" x-transition.opacity.duration.150ms>
                @include('marketing.publish.partials._panel1-instagram')
            </div>

            {{-- Facebook --}}
            <div x-show="activeTab === 'facebook'" x-transition.opacity.duration.150ms>
                @include('marketing.publish.partials._panel1-facebook')
            </div>

            {{-- Google Business --}}
            <div x-show="activeTab === 'google_business'" x-transition.opacity.duration.150ms>
                @include('marketing.publish.partials._panel1-google')
            </div>

            {{-- Blog --}}
            <div x-show="activeTab === 'blog'" x-transition.opacity.duration.150ms>
                @include('marketing.publish.partials._panel1-blog')
            </div>

            {{-- WhatsApp --}}
            <div x-show="activeTab === 'whatsapp'" x-transition.opacity.duration.150ms>
                @include('marketing.publish.partials._panel1-whatsapp')
            </div>
        </div>
        {{-- /PANEL 1 --}}

        {{-- PANEL 2 — Platform Previews (40%) --}}
        <div style="
            flex: 0 0 40%;
            width: 40%;
            min-width: 300px;
            border-right: 1px solid rgba(185,92,183,0.10);
            height: 100%;
        ">
            @include('marketing.publish.partials._platform-previews')
        </div>
        {{-- /PANEL 2 --}}

        {{-- PANEL 3 — Publish Settings (20%) --}}
        <div style="
            flex: 1;
            min-width: 200px;
            overflow-y: auto;
        ">
            @include('marketing.publish.partials._publish-panel')
        </div>
        {{-- /PANEL 3 --}}

    </div>
    {{-- /3-PANEL LAYOUT --}}

</div>{{-- /Alpine x-data --}}

@endsection
