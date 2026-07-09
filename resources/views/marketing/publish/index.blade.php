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
    showPreviews: false,
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
            {{-- Ideas / Brainstorm — kept reachable here now that it no longer
                 has its own top-level nav tab (folds fully into Content in a
                 later pass; this link keeps it from becoming a dead end). --}}
            <a href="{{ route('marketing.brainstorm') }}" style="
                display: inline-flex;
                align-items: center;
                gap: 7px;
                height: 38px;
                padding: 0 16px;
                font-family: 'Inter', sans-serif;
                font-size: 13px;
                font-weight: 500;
                color: #5a4868;
                background: #ffffff;
                border: 1.5px solid rgba(185,92,183,0.25);
                border-radius: 6px;
                text-decoration: none;
                transition: background 150ms;
            "
            onmouseover="this.style.background='#faf5ff'"
            onmouseout="this.style.background='#ffffff'"
            >
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
                Ideas
            </a>

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

    {{-- ── CONTENT BOARD — Ideas → Drafts → Scheduled → Published ──
         Slice 3 addition: merges what used to be three separate pages
         (Publish/Brainstorm/Ideas) into one workflow. Read-only summary;
         clicking a column takes you to the fuller list. --}}
    <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:12px; margin-bottom:20px;">
        @foreach ($board as $key => $col)
        <div style="background:#fff; border:1px solid rgba(185,92,183,0.15); border-radius:8px; padding:14px 16px;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                <span style="font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:#9b6aad; text-transform:uppercase; letter-spacing:.5px;">{{ $col['label'] }}</span>
                <span style="font-family:'Inter',sans-serif; font-size:12px; font-weight:700; color:#1e0a2c;">{{ $col['total'] }}</span>
            </div>
            @forelse ($col['items'] as $item)
            <a href="{{ $key === 'ideas' ? route('marketing.brainstorm') : '#' }}" style="
                display:block; text-decoration:none;
                font-family:'Inter',sans-serif; font-size:12px; color:#5a4868;
                padding:5px 0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
                border-bottom:1px solid rgba(185,92,183,0.06);
            ">{{ $item->title ?: \Illuminate\Support\Str::limit($item->caption ?? '', 40) }}</a>
            @empty
            <p style="font-family:'Inter',sans-serif; font-size:11px; color:#c4b5cc; margin:0;">Nothing here yet.</p>
            @endforelse
        </div>
        @endforeach
    </div>
    {{-- /CONTENT BOARD --}}

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

        {{-- PANEL 2 — Platform Previews (40%) — collapsed by default.
             Slice 3: live previews were one of the biggest sources of
             on-screen clutter (dossier §10). Hidden behind a toggle
             instead of removed — nothing here was deleted. --}}
        <div style="
            flex: 0 0 40%;
            width: 40%;
            min-width: 300px;
            border-right: 1px solid rgba(185,92,183,0.10);
            height: 100%;
            overflow-y: auto;
        ">
            <div x-show="!showPreviews" style="padding:24px 20px;">
                <button type="button" @click="showPreviews = true" style="
                    display:flex; align-items:center; gap:8px; width:100%;
                    justify-content:center;
                    padding:12px 16px;
                    background:#faf5ff; border:1px dashed rgba(185,92,183,0.35); border-radius:8px;
                    font-family:'Inter',sans-serif; font-size:13px; color:#6a0f70; font-weight:500;
                    cursor:pointer;
                ">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                    Show live platform previews
                </button>
            </div>
            <div x-show="showPreviews" x-cloak>
                <div style="padding:8px 16px 0; display:flex; justify-content:flex-end;">
                    <button type="button" @click="showPreviews = false" style="background:none; border:none; cursor:pointer; font-family:'Inter',sans-serif; font-size:11px; color:#9ca3af;">Hide previews</button>
                </div>
                @include('marketing.publish.partials._platform-previews')
            </div>
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
