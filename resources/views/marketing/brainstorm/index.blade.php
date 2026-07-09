{{--
|==========================================================================
| Marketing — Brainstorm
| File: resources/views/marketing/brainstorm/index.blade.php
|
| Phase 2.2-A: UI shell with mock data.
|   - Page header + action buttons
|   - 4-tab bar (Alpine.js): AI Generate | Quick Idea | Idea Bank | Festival Planner
|   - AI Generate tab: left control bar + category filter pills + 8-card grid
|
| Partials (Phase 2.2-B):
|   partials/_idea-card.blade.php
|   partials/_idea-detail-panel.blade.php
|==========================================================================
--}}
@extends('marketing.layouts.app')

@php $marketingPageTitle = 'Brainstorm'; @endphp

@section('page-title', 'Marketing — Brainstorm')

@section('marketing-content')

{{-- ═══════════════════════════════════════════════════════════════════
     ROOT Alpine scope
     activeTab   : which of the 4 inner tabs is visible
     activeFilter: category pill selected on AI Generate tab
     panelOpen   : whether the detail slide-in panel is open
     activeIdea  : index of the card whose panel is open (null = none)
════════════════════════════════════════════════════════════════════ --}}
<div
    x-data="{
        activeTab:    'ai-generate',
        activeFilter: 'all',
        panelOpen:    false,
        activeIdea:   null,
        openIdea(idx) { this.activeIdea = idx; this.panelOpen = true; },
        closePanel()  { this.panelOpen = false; this.activeIdea = null; }
    }"
    style="position: relative;"
>

{{-- ── PAGE HEADER ─────────────────────────────────────────────────── --}}
<div style="
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
">
    <div>
        <h1 style="
            font-family: 'Cormorant Garamond', serif;
            font-size: 26px;
            font-weight: 600;
            color: #1e0a2c;
            margin: 0 0 3px;
        ">Brainstorm</h1>
        <p style="
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 300;
            color: #7a6884;
            margin: 0;
        ">AI-assisted content ideas tailored for your clinic.</p>
    </div>

    <div style="display: flex; gap: 10px; align-items: center;">
        {{-- Import Ideas --}}
        <button type="button" style="
            display: inline-flex;
            align-items: center;
            gap: 7px;
            height: 36px;
            padding: 0 16px;
            background: #ffffff;
            border: 1px solid rgba(185,92,183,0.30);
            border-radius: 6px;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 500;
            color: #6a0f70;
            cursor: pointer;
            transition: background 150ms;
        "
        onmouseover="this.style.background='#f9f3fa'"
        onmouseout="this.style.background='#ffffff'"
        >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
            </svg>
            Import Ideas
        </button>

        {{-- Create New Idea --}}
        <button type="button" style="
            display: inline-flex;
            align-items: center;
            gap: 7px;
            height: 36px;
            padding: 0 18px;
            background: linear-gradient(135deg, #6a0f70 0%, #9b3da0 100%);
            border: none;
            border-radius: 6px;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 500;
            color: #ffffff;
            cursor: pointer;
            transition: opacity 150ms;
        "
        onmouseover="this.style.opacity='0.88'"
        onmouseout="this.style.opacity='1'"
        >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Create New Idea
        </button>
    </div>
</div>


{{-- ── INNER TAB BAR (segmented capsule) ───────────────────────────── --}}
{{-- NOTE: Using real CSS classes (not Alpine :style) so Tailwind preflight
     cannot override the active/inactive button colours.                    --}}
<style>
    .qi-saving {
        opacity: 0.6 !important;
        cursor: default !important;
    }
    .bs-tab-track {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #f0e9f4 !important;
        border-radius: 40px;
        padding: 4px;
        margin-bottom: 24px;
    }
    .bs-tab-btn {
        display: inline-flex !important;
        align-items: center !important;
        gap: 7px !important;
        padding: 0 18px !important;
        height: 36px !important;
        border: none !important;
        border-radius: 40px !important;
        font-family: 'Inter', sans-serif !important;
        font-size: 13px !important;
        font-weight: 500 !important;
        cursor: pointer !important;
        white-space: nowrap !important;
        flex-shrink: 0 !important;
        background: transparent !important;
        color: #7a4e8a !important;
        box-shadow: none !important;
        transition: background 180ms, color 180ms, box-shadow 180ms !important;
    }
    .bs-tab-btn.bs-tab-active {
        background: #6a0f70 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 8px rgba(106,15,112,0.22) !important;
    }
</style>

@php
    $innerTabs = [
        ['key' => 'ai-generate',      'label' => 'AI Generate',     'icon' => '<path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>'],
        ['key' => 'quick-idea',       'label' => 'Quick Idea',      'icon' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/>'],
        ['key' => 'idea-bank',        'label' => 'Idea Bank',       'icon' => '<path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>'],
        ['key' => 'festival-planner', 'label' => 'Festival Planner','icon' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
    ];
@endphp

<div class="bs-tab-track">
    @foreach($innerTabs as $tab)
    <button
        type="button"
        @click="activeTab = '{{ $tab['key'] }}'"
        x-init="
            const el = $el;
            const key = '{{ $tab['key'] }}';
            function syncBsTab(tab) {
                const on = (tab === key);
                el.style.setProperty('background',  on ? '#6a0f70' : 'transparent', 'important');
                el.style.setProperty('color',       on ? '#ffffff'  : '#7a4e8a',     'important');
                el.style.setProperty('box-shadow',  on ? '0 2px 8px rgba(106,15,112,0.22)' : 'none', 'important');
            }
            syncBsTab(activeTab);
            $watch('activeTab', syncBsTab);
        "
        style="display:inline-flex; align-items:center; gap:7px; padding:0 18px; height:36px; border:none; border-radius:40px; font-family:'Inter',sans-serif; font-size:13px; font-weight:500; cursor:pointer; white-space:nowrap; flex-shrink:0; transition:background 180ms, color 180ms, box-shadow 180ms;"
    >
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            {!! $tab['icon'] !!}
        </svg>
        {{ $tab['label'] }}
    </button>
    @endforeach
</div>


{{-- ════════════════════════════════════════════════════════════════════
     TAB PANELS
════════════════════════════════════════════════════════════════════ --}}

{{-- ── TAB: AI GENERATE ────────────────────────────────────────────── --}}
<div x-show="activeTab === 'ai-generate'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">

    {{-- ┌─ TWO-COLUMN LAYOUT: Control Bar (left) + Main Area (right) ─┐ --}}
    <div style="display: flex; gap: 20px; align-items: flex-start;">

        {{-- LEFT CONTROL BAR ──────────────────────────────────────── --}}
        <div style="
            width: 220px;
            flex-shrink: 0;
            background: #ffffff;
            border: 1px solid rgba(185,92,183,0.13);
            border-radius: 10px;
            padding: 20px 16px;
        ">
            <p style="
                font-family: 'Inter', sans-serif;
                font-size: 11px;
                font-weight: 600;
                color: #9b6aad;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                margin: 0 0 14px;
            ">Generate Ideas</p>

            {{-- Treatment --}}
            <div style="margin-bottom: 14px;">
                <label style="
                    display: block;
                    font-family: 'Inter', sans-serif;
                    font-size: 11.5px;
                    font-weight: 500;
                    color: #5a4868;
                    margin-bottom: 5px;
                ">Treatment</label>
                <select style="
                    width: 100%;
                    height: 34px;
                    padding: 0 10px;
                    background: #f9f3fa;
                    border: 1px solid rgba(185,92,183,0.22);
                    border-radius: 6px;
                    font-family: 'Inter', sans-serif;
                    font-size: 12.5px;
                    color: #1e0a2c;
                    outline: none;
                    cursor: pointer;
                ">
                    <option value="">All Treatments</option>
                    <option>Implants</option>
                    <option>Smile Makeover</option>
                    <option>Whitening</option>
                    <option>Aligners</option>
                    <option>General Dentistry</option>
                </select>
            </div>

            {{-- Platform --}}
            <div style="margin-bottom: 14px;">
                <label style="
                    display: block;
                    font-family: 'Inter', sans-serif;
                    font-size: 11.5px;
                    font-weight: 500;
                    color: #5a4868;
                    margin-bottom: 5px;
                ">Platform</label>
                <select style="
                    width: 100%;
                    height: 34px;
                    padding: 0 10px;
                    background: #f9f3fa;
                    border: 1px solid rgba(185,92,183,0.22);
                    border-radius: 6px;
                    font-family: 'Inter', sans-serif;
                    font-size: 12.5px;
                    color: #1e0a2c;
                    outline: none;
                    cursor: pointer;
                ">
                    <option value="">All Platforms</option>
                    <option>Instagram</option>
                    <option>Facebook</option>
                    <option>Google</option>
                    <option>WhatsApp</option>
                    <option>Blog</option>
                </select>
            </div>

            {{-- Tone --}}
            <div style="margin-bottom: 20px;">
                <label style="
                    display: block;
                    font-family: 'Inter', sans-serif;
                    font-size: 11.5px;
                    font-weight: 500;
                    color: #5a4868;
                    margin-bottom: 5px;
                ">Tone</label>
                <select style="
                    width: 100%;
                    height: 34px;
                    padding: 0 10px;
                    background: #f9f3fa;
                    border: 1px solid rgba(185,92,183,0.22);
                    border-radius: 6px;
                    font-family: 'Inter', sans-serif;
                    font-size: 12.5px;
                    color: #1e0a2c;
                    outline: none;
                    cursor: pointer;
                ">
                    <option>Friendly</option>
                    <option>Professional</option>
                    <option>Educational</option>
                    <option>Promotional</option>
                </select>
            </div>

            {{-- Generate button --}}
            <button type="button" style="
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                width: 100%;
                height: 38px;
                background: linear-gradient(135deg, #6a0f70 0%, #9b3da0 100%);
                border: none;
                border-radius: 7px;
                font-family: 'Inter', sans-serif;
                font-size: 13px;
                font-weight: 600;
                color: #ffffff;
                cursor: pointer;
                transition: opacity 150ms;
            "
            onmouseover="this.style.opacity='0.88'"
            onmouseout="this.style.opacity='1'"
            >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                </svg>
                Generate Ideas
            </button>
        </div>
        {{-- /LEFT CONTROL BAR --}}


        {{-- MAIN AREA: filter pills + card grid ─────────────────────── --}}
        <div style="flex: 1; min-width: 0;">

            {{-- CATEGORY FILTER PILLS ──────────────────────────────── --}}
            @php
                $filterPills = [
                    ['key' => 'all',              'label' => 'All'],
                    ['key' => 'implants',         'label' => 'Implants'],
                    ['key' => 'smile-makeover',   'label' => 'Smile Makeover'],
                    ['key' => 'whitening',        'label' => 'Whitening'],
                    ['key' => 'aligners',         'label' => 'Aligners'],
                    ['key' => 'general-dentistry','label' => 'General Dentistry'],
                ];
            @endphp

            <div style="
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-bottom: 20px;
            ">
                @foreach($filterPills as $pill)
                <button
                    type="button"
                    @click="activeFilter = '{{ $pill['key'] }}'"
                    x-init="
                        const el = $el;
                        const key = '{{ $pill['key'] }}';
                        function syncPill(f) {
                            const on = (f === key);
                            el.style.setProperty('background',   on ? '#6a0f70'                    : '#ffffff',                    'important');
                            el.style.setProperty('color',        on ? '#ffffff'                    : '#5a4868',                    'important');
                            el.style.setProperty('border-color', on ? '#6a0f70'                    : 'rgba(185,92,183,0.25)',       'important');
                        }
                        syncPill(activeFilter);
                        $watch('activeFilter', syncPill);
                    "
                    style="height:30px; padding:0 14px; border:1px solid rgba(185,92,183,0.25); border-radius:20px; font-family:'Inter',sans-serif; font-size:12.5px; font-weight:500; cursor:pointer; transition:background 150ms,color 150ms,border-color 150ms; white-space:nowrap;"
                >{{ $pill['label'] }}</button>
                @endforeach
            </div>
            {{-- /FILTER PILLS --}}


            {{-- IDEA CARD GRID ──────────────────────────────────────── --}}
            {{-- 4 columns; each card is @included from _idea-card.blade.php (Phase 2.2-B) --}}
            <div style="
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 16px;
            ">
                @foreach($ideas as $index => $idea)
                    @include('marketing.brainstorm.partials._idea-card', [
                        'idea'  => $idea,
                        'index' => $index,
                    ])
                @endforeach
            </div>
            {{-- /CARD GRID --}}

        </div>
        {{-- /MAIN AREA --}}

    </div>
    {{-- /TWO-COLUMN LAYOUT --}}

</div>
{{-- /AI GENERATE TAB --}}


{{-- ── TAB: QUICK IDEA ──────────────────────────────────────────────── --}}
<div
    x-show="activeTab === 'quick-idea'"
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    style="display:none;"
    x-data="{
        qiTitle: '',
        qiDescription: '',
        qiNotes: '',
        qiContentType: '',
        qiPlatforms: [],
        qiPriority: 'medium',
        qiSaving: false,
        qiError: '',
        qiSuccess: '',
        togglePlatform(p) {
            const i = this.qiPlatforms.indexOf(p);
            if (i === -1) this.qiPlatforms.push(p);
            else this.qiPlatforms.splice(i, 1);
        },
        hasPlatform(p) { return this.qiPlatforms.includes(p); },
        dragOver: false,
        // Wires the Quick Idea form to the real IdeaController::store
        // endpoint — this panel used to be UI-only with nothing behind
        // the Save Idea button. Found live 2026-07-09 while QA-testing.
        qiSaveIdea() {
            this.qiError = '';
            this.qiSuccess = '';

            if (!this.qiTitle.trim()) {
                this.qiError = 'Title is required.';
                return;
            }

            this.qiSaving = true;

            fetch('{{ route('marketing.ideas.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify({
                    title: this.qiTitle,
                    description: this.qiDescription,
                    notes: this.qiNotes,
                    content_type: this.qiContentType ? this.qiContentType.toLowerCase() : null,
                    platforms: this.qiPlatforms,
                }),
            })
            .then(r => r.json().then(data => ({ ok: r.ok, data })))
            .then(({ ok, data }) => {
                this.qiSaving = false;
                if (!ok || !data.success) {
                    this.qiError = (data && data.message) ? data.message : 'Could not save idea. Please try again.';
                    return;
                }
                this.qiSuccess = 'Idea saved to Idea Bank.';
                this.qiTitle = '';
                this.qiDescription = '';
                this.qiNotes = '';
                this.qiContentType = '';
                this.qiPlatforms = [];
                this.qiPriority = 'medium';
            })
            .catch(() => {
                this.qiSaving = false;
                this.qiError = 'Could not save idea. Check your connection and try again.';
            });
        }
    }"
>
    {{-- Centered card --}}
    <div style="max-width: 620px; margin: 0 auto;">

        <div style="
            background: #ffffff;
            border: 1px solid rgba(185,92,183,0.14);
            border-radius: 12px;
            padding: 28px 32px 32px;
        ">
            <h2 style="
                font-family: 'Cormorant Garamond', serif;
                font-size: 20px;
                font-weight: 600;
                color: #1e0a2c;
                margin: 0 0 4px;
            ">Quick Idea</h2>
            <p style="
                font-family: 'Inter', sans-serif;
                font-size: 12.5px;
                color: #7a6884;
                margin: 0 0 24px;
            ">Capture a content idea before it slips away.</p>

            {{-- ── TITLE ── --}}
            <div style="margin-bottom: 18px;">
                <label style="display:block; font-family:'Inter',sans-serif; font-size:12px; font-weight:600; color:#5a4868; margin-bottom:6px; letter-spacing:0.03em; text-transform:uppercase;">Title</label>
                <input type="text" x-model="qiTitle" placeholder="e.g. 5 reasons to choose implants over dentures" style="
                    width: 100%; height: 38px; padding: 0 12px;
                    border: 1px solid rgba(185,92,183,0.22);
                    border-radius: 7px;
                    font-family: 'Inter', sans-serif; font-size: 13px; color: #1e0a2c;
                    background: #faf7fb;
                    outline: none; box-sizing: border-box;
                    transition: border-color 150ms;
                "
                onfocus="this.style.borderColor='#9b3da0'"
                onblur="this.style.borderColor='rgba(185,92,183,0.22)'"
                >
            </div>

            {{-- ── CONTENT TYPE (pill selector) ── --}}
            <div style="margin-bottom: 18px;">
                <label style="display:block; font-family:'Inter',sans-serif; font-size:12px; font-weight:600; color:#5a4868; margin-bottom:8px; letter-spacing:0.03em; text-transform:uppercase;">Content Type</label>
                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                    @php
                        $contentTypes = ['Post','Reel','Carousel','Story','Blog','Offer'];
                    @endphp
                    @foreach($contentTypes as $ct)
                    <button
                        type="button"
                        @click="qiContentType = (qiContentType === '{{ $ct }}' ? '' : '{{ $ct }}')"
                        :style="qiContentType === '{{ $ct }}'
                            ? 'background:#6a0f70; color:#fff; border-color:#6a0f70;'
                            : 'background:#faf7fb; color:#5a4868; border-color:rgba(185,92,183,0.25);'"
                        style="
                            height: 32px; padding: 0 16px;
                            border: 1px solid; border-radius: 20px;
                            font-family: 'Inter', sans-serif; font-size: 12.5px; font-weight: 500;
                            cursor: pointer;
                            transition: background 150ms, color 150ms, border-color 150ms;
                        "
                    >{{ $ct }}</button>
                    @endforeach
                </div>
            </div>

            {{-- ── TREATMENT CATEGORY + PLATFORM (2-col) ── --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:18px;">

                {{-- Treatment Category --}}
                <div>
                    <label style="display:block; font-family:'Inter',sans-serif; font-size:12px; font-weight:600; color:#5a4868; margin-bottom:6px; letter-spacing:0.03em; text-transform:uppercase;">Treatment Category</label>
                    <select style="
                        width:100%; height:38px; padding:0 10px;
                        border:1px solid rgba(185,92,183,0.22); border-radius:7px;
                        font-family:'Inter',sans-serif; font-size:12.5px; color:#1e0a2c;
                        background:#faf7fb; outline:none; cursor:pointer;
                        box-sizing:border-box;
                    ">
                        <option value="">Select category…</option>
                        <option>Implants</option>
                        <option>Smile Makeover</option>
                        <option>Whitening</option>
                        <option>Aligners</option>
                        <option>General Dentistry</option>
                        <option>Paediatric Dentistry</option>
                        <option>Oral Hygiene</option>
                    </select>
                </div>

                {{-- Platform (icon multi-select) --}}
                <div>
                    <label style="display:block; font-family:'Inter',sans-serif; font-size:12px; font-weight:600; color:#5a4868; margin-bottom:8px; letter-spacing:0.03em; text-transform:uppercase;">Platform</label>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        @php
                            $platforms = [
                                ['key'=>'instagram', 'label'=>'IG',  'color'=>'#e1306c'],
                                ['key'=>'facebook',  'label'=>'FB',  'color'=>'#1877f2'],
                                ['key'=>'google',    'label'=>'G',   'color'=>'#34a853'],
                                ['key'=>'whatsapp',  'label'=>'WA',  'color'=>'#25d366'],
                                ['key'=>'blog',      'label'=>'Blog','color'=>'#6a0f70'],
                            ];
                        @endphp
                        @foreach($platforms as $pl)
                        <button
                            type="button"
                            @click="togglePlatform('{{ $pl['key'] }}')"
                            :style="hasPlatform('{{ $pl['key'] }}')
                                ? 'background:{{ $pl['color'] }}; color:#fff; border-color:{{ $pl['color'] }};'
                                : 'background:#faf7fb; color:#5a4868; border-color:rgba(185,92,183,0.25);'"
                            style="
                                height: 32px; padding: 0 11px;
                                border: 1px solid; border-radius: 6px;
                                font-family: 'Inter', sans-serif; font-size: 11.5px; font-weight: 600;
                                cursor: pointer;
                                transition: background 150ms, color 150ms, border-color 150ms;
                            "
                            title="{{ $pl['key'] }}"
                        >{{ $pl['label'] }}</button>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- ── DESCRIPTION ── --}}
            <div style="margin-bottom:18px;">
                <label style="display:block; font-family:'Inter',sans-serif; font-size:12px; font-weight:600; color:#5a4868; margin-bottom:6px; letter-spacing:0.03em; text-transform:uppercase;">
                    Description
                    <span style="font-weight:400; text-transform:none; letter-spacing:0; color:#9b6aad; margin-left:4px;">(<span x-text="qiDescription.length"></span>/500)</span>
                </label>
                <textarea
                    rows="4"
                    maxlength="500"
                    placeholder="What is this content about? What angle or hook will you use?"
                    x-model="qiDescription"
                    style="
                        width:100%; padding:10px 12px;
                        border:1px solid rgba(185,92,183,0.22); border-radius:7px;
                        font-family:'Inter',sans-serif; font-size:12.5px; color:#1e0a2c;
                        background:#faf7fb; resize:vertical; outline:none;
                        box-sizing:border-box; line-height:1.55;
                        transition:border-color 150ms;
                    "
                    onfocus="this.style.borderColor='#9b3da0'"
                    onblur="this.style.borderColor='rgba(185,92,183,0.22)'"
                ></textarea>
            </div>

            {{-- ── NOTES (optional) ── --}}
            <div style="margin-bottom:18px;">
                <label style="display:block; font-family:'Inter',sans-serif; font-size:12px; font-weight:600; color:#5a4868; margin-bottom:6px; letter-spacing:0.03em; text-transform:uppercase;">
                    Notes
                    <span style="font-weight:300; text-transform:none; letter-spacing:0; color:#9b6aad; margin-left:4px;">optional</span>
                </label>
                <textarea
                    rows="2"
                    placeholder="Any references, inspiration, or internal notes…"
                    x-model="qiNotes"
                    style="
                        width:100%; padding:10px 12px;
                        border:1px solid rgba(185,92,183,0.22); border-radius:7px;
                        font-family:'Inter',sans-serif; font-size:12.5px; color:#1e0a2c;
                        background:#faf7fb; resize:vertical; outline:none;
                        box-sizing:border-box; line-height:1.55;
                        transition:border-color 150ms;
                    "
                    onfocus="this.style.borderColor='#9b3da0'"
                    onblur="this.style.borderColor='rgba(185,92,183,0.22)'"
                ></textarea>
            </div>

            {{-- ── REFERENCE IMAGE (drag-drop zone) ── --}}
            <div style="margin-bottom:18px;">
                <label style="display:block; font-family:'Inter',sans-serif; font-size:12px; font-weight:600; color:#5a4868; margin-bottom:6px; letter-spacing:0.03em; text-transform:uppercase;">
                    Reference Image
                    <span style="font-weight:300; text-transform:none; letter-spacing:0; color:#9b6aad; margin-left:4px;">optional</span>
                </label>
                <div
                    @dragover.prevent="dragOver = true"
                    @dragleave="dragOver = false"
                    @drop.prevent="dragOver = false"
                    :style="dragOver ? 'border-color:#6a0f70; background:#f3eaf4;' : 'border-color:rgba(185,92,183,0.28); background:#faf7fb;'"
                    style="
                        border: 2px dashed;
                        border-radius: 8px;
                        padding: 24px 16px;
                        text-align: center;
                        cursor: pointer;
                        transition: border-color 150ms, background 150ms;
                    "
                    onclick="document.getElementById('qi-file-input').click()"
                >
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="rgba(155,106,173,0.60)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto 8px; display:block;">
                        <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                    </svg>
                    <p style="font-family:'Inter',sans-serif; font-size:12.5px; color:#7a6884; margin:0 0 4px;">Drag & drop an image here, or <span style="color:#6a0f70; font-weight:600;">browse</span></p>
                    <p style="font-family:'Inter',sans-serif; font-size:11px; color:#9b6aad; margin:0;">PNG, JPG, WEBP — max 5 MB</p>
                    <input type="file" id="qi-file-input" accept="image/*" style="display:none;">
                </div>
            </div>

            {{-- ── PRIORITY ── --}}
            <div style="margin-bottom:28px;">
                <label style="display:block; font-family:'Inter',sans-serif; font-size:12px; font-weight:600; color:#5a4868; margin-bottom:8px; letter-spacing:0.03em; text-transform:uppercase;">Priority</label>
                <div style="display:flex; gap:10px;">
                    @php $priorities = [['val'=>'low','label'=>'Low','color'=>'#34a853'],['val'=>'medium','label'=>'Medium','color'=>'#fbbc04'],['val'=>'high','label'=>'High','color'=>'#ea4335']]; @endphp
                    @foreach($priorities as $pr)
                    <label style="display:flex; align-items:center; gap:7px; cursor:pointer; font-family:'Inter',sans-serif; font-size:13px; color:#5a4868;">
                        <input
                            type="radio"
                            name="qi_priority"
                            value="{{ $pr['val'] }}"
                            x-model="qiPriority"
                            style="accent-color:{{ $pr['color'] }}; width:15px; height:15px; cursor:pointer;"
                        >
                        <span
                            :style="qiPriority === '{{ $pr['val'] }}' ? 'color:{{ $pr['color'] }}; font-weight:600;' : ''"
                            style="transition:color 150ms;"
                        >{{ $pr['label'] }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- ── STATUS MESSAGES ── --}}
            <div x-show="qiError" x-cloak x-text="qiError" style="
                margin-bottom:14px; padding:9px 14px; border-radius:7px;
                background:#fef2f2; border:1px solid #fecaca; color:#b91c1c;
                font-family:'Inter',sans-serif; font-size:12.5px;
            "></div>
            <div x-show="qiSuccess" x-cloak x-text="qiSuccess" style="
                margin-bottom:14px; padding:9px 14px; border-radius:7px;
                background:#f0fdf4; border:1px solid #bbf7d0; color:#16a34a;
                font-family:'Inter',sans-serif; font-size:12.5px;
            "></div>

            {{-- ── ACTION BUTTONS ── --}}
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                {{-- Convert to Publish — not wired yet, left as-is (out of scope for this fix) --}}
                <button type="button" style="
                    display:inline-flex; align-items:center; gap:7px;
                    height:38px; padding:0 20px;
                    background:#ffffff;
                    border:1px solid rgba(185,92,183,0.35); border-radius:7px;
                    font-family:'Inter',sans-serif; font-size:13px; font-weight:500; color:#6a0f70;
                    cursor:pointer; transition:background 150ms;
                "
                onmouseover="this.style.background='#f9f3fa'"
                onmouseout="this.style.background='#ffffff'"
                >
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 01-4 4H3"/>
                    </svg>
                    Convert to Publish
                </button>

                {{-- Save Idea — now wired to IdeaController::store via qiSaveIdea() --}}
                <button
                    type="button"
                    @click="qiSaveIdea()"
                    :disabled="qiSaving"
                    style="
                    display:inline-flex; align-items:center; gap:7px;
                    height:38px; padding:0 22px;
                    background:linear-gradient(135deg,#6a0f70 0%,#9b3da0 100%);
                    border:none; border-radius:7px;
                    font-family:'Inter',sans-serif; font-size:13px; font-weight:600; color:#ffffff;
                    cursor:pointer;
                    transition:opacity 150ms;
                "
                :class="qiSaving ? 'qi-saving' : ''"
                onmouseover="if (!this.disabled) this.style.opacity='0.88'"
                onmouseout="if (!this.disabled) this.style.opacity='1'"
                >
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                    </svg>
                    <span x-text="qiSaving ? 'Saving…' : 'Save Idea'"></span>
                </button>
            </div>

        </div>{{-- /card --}}
    </div>{{-- /centered --}}

</div>
{{-- /QUICK IDEA TAB --}}

{{-- ── TAB: IDEA BANK ───────────────────────────────────────────────── --}}
@php
$bankIdeas = [
    ['title'=>'5 Signs You Need a Dental Implant',      'type'=>'Post',     'description'=>'Educate patients on when an implant is the right call.',     'tags'=>['Implants','Education'],  'status'=>'Saved'],
    ['title'=>'Before & After: Smile Makeover Reel',    'type'=>'Reel',     'description'=>'Short-form video showcasing a full smile transformation.',    'tags'=>['Smile Makeover','Reel'], 'status'=>'Converted'],
    ['title'=>'Teeth Whitening Myths Debunked',         'type'=>'Carousel', 'description'=>'Swipeable carousel busting the top 6 whitening myths.',       'tags'=>['Whitening','Myths'],     'status'=>'Draft'],
    ['title'=>'Aligner Journey: Week 1 to Week 12',     'type'=>'Story',    'description'=>'Patient journey highlights across Instagram Stories.',         'tags'=>['Aligners','Story'],      'status'=>'Saved'],
    ['title'=>'Why Baby Teeth Matter More Than You Think','type'=>'Blog',   'description'=>'Long-form blog post on paediatric dental care.',               'tags'=>['Paediatric','Blog'],     'status'=>'Draft'],
    ['title'=>'Summer Whitening Offer — 20% Off',       'type'=>'Offer',    'description'=>'Promotional post for seasonal whitening discount.',            'tags'=>['Whitening','Promo'],     'status'=>'Saved'],
    ['title'=>'Implant vs Bridge: Which is Better?',    'type'=>'Post',     'description'=>'Comparison content for patients considering tooth replacement.','tags'=>['Implants','Compare'],    'status'=>'Converted'],
    ['title'=>'10-Minute Oral Hygiene Routine',         'type'=>'Reel',     'description'=>'Quick-tip reel for daily brushing and flossing habits.',       'tags'=>['Hygiene','Tips'],        'status'=>'Draft'],
];
@endphp

<div
    x-show="activeTab === 'idea-bank'"
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    style="display:none;"
    x-data="{
        ibSearch: '',
        ibTreatment: '',
        ibPlatform: '',
        ibStatus: '',
        ibBatchMode: false,
        ibSelected: [],
        ibBatchOpen: false,
        ibTotalIdeas: {{ count($bankIdeas) }},
        toggleSelect(i) {
            const idx = this.ibSelected.indexOf(i);
            if (idx === -1) this.ibSelected.push(i);
            else this.ibSelected.splice(idx, 1);
        },
        isSelected(i) { return this.ibSelected.includes(i); },
        selectAll() { this.ibSelected = Array.from({length: this.ibTotalIdeas}, (_, i) => i); },
        clearAll()  { this.ibSelected = []; }
    }"
>

    {{-- ── FILTER BAR ─────────────────────────────────────────────── --}}
    <div style="
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    ">
        {{-- Search --}}
        <div style="position:relative; flex:1; min-width:180px;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                style="position:absolute; left:10px; top:50%; transform:translateY(-50%); pointer-events:none;">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text" x-model="ibSearch" placeholder="Search ideas…" style="
                width:100%; height:36px; padding:0 12px 0 32px;
                border:1px solid rgba(185,92,183,0.22); border-radius:7px;
                font-family:'Inter',sans-serif; font-size:12.5px; color:#1e0a2c;
                background:#ffffff; outline:none; box-sizing:border-box;
                transition:border-color 150ms;
            "
            onfocus="this.style.borderColor='#9b3da0'"
            onblur="this.style.borderColor='rgba(185,92,183,0.22)'"
            >
        </div>

        {{-- Treatment --}}
        <select x-model="ibTreatment" style="
            height:36px; padding:0 10px;
            border:1px solid rgba(185,92,183,0.22); border-radius:7px;
            font-family:'Inter',sans-serif; font-size:12.5px; color:#5a4868;
            background:#ffffff; outline:none; cursor:pointer;
        ">
            <option value="">All Treatments</option>
            <option>Implants</option>
            <option>Smile Makeover</option>
            <option>Whitening</option>
            <option>Aligners</option>
            <option>General Dentistry</option>
        </select>

        {{-- Platform --}}
        <select x-model="ibPlatform" style="
            height:36px; padding:0 10px;
            border:1px solid rgba(185,92,183,0.22); border-radius:7px;
            font-family:'Inter',sans-serif; font-size:12.5px; color:#5a4868;
            background:#ffffff; outline:none; cursor:pointer;
        ">
            <option value="">All Platforms</option>
            <option>Instagram</option>
            <option>Facebook</option>
            <option>Google</option>
            <option>Blog</option>
        </select>

        {{-- Status --}}
        <select x-model="ibStatus" style="
            height:36px; padding:0 10px;
            border:1px solid rgba(185,92,183,0.22); border-radius:7px;
            font-family:'Inter',sans-serif; font-size:12.5px; color:#5a4868;
            background:#ffffff; outline:none; cursor:pointer;
        ">
            <option value="">All Status</option>
            <option value="Draft">Draft</option>
            <option value="Saved">Saved</option>
            <option value="Converted">Converted</option>
        </select>

        {{-- Date range --}}
        <div style="display:flex; align-items:center; gap:5px;">
            <input type="date" style="
                height:36px; padding:0 8px;
                border:1px solid rgba(185,92,183,0.22); border-radius:7px;
                font-family:'Inter',sans-serif; font-size:12px; color:#5a4868;
                background:#ffffff; outline:none; cursor:pointer;
            ">
            <span style="font-family:'Inter',sans-serif; font-size:12px; color:#9b6aad;">–</span>
            <input type="date" style="
                height:36px; padding:0 8px;
                border:1px solid rgba(185,92,183,0.22); border-radius:7px;
                font-family:'Inter',sans-serif; font-size:12px; color:#5a4868;
                background:#ffffff; outline:none; cursor:pointer;
            ">
        </div>
    </div>
    {{-- /FILTER BAR --}}


    {{-- ── META ROW: count + batch controls ──────────────────────── --}}
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">

        {{-- Showing count --}}
        <p style="font-family:'Inter',sans-serif; font-size:12.5px; color:#7a6884; margin:0;">
            Showing <strong style="color:#1e0a2c;">{{ count($bankIdeas) }}</strong> ideas
        </p>

        {{-- Batch controls --}}
        <div style="display:flex; align-items:center; gap:8px;">

            {{-- Select All (visible only in batch mode) --}}
            <button
                type="button"
                x-show="ibBatchMode"
                @click="ibSelected.length === ibTotalIdeas ? clearAll() : selectAll()"
                style="
                    height:32px; padding:0 14px;
                    background:#ffffff;
                    border:1px solid rgba(185,92,183,0.25); border-radius:6px;
                    font-family:'Inter',sans-serif; font-size:12px; font-weight:500; color:#5a4868;
                    cursor:pointer;
                "
                x-text="ibSelected.length === ibTotalIdeas ? 'Deselect All' : 'Select All'"
            ></button>

            {{-- Batch Actions dropdown (visible only in batch mode with selection) --}}
            <div x-show="ibBatchMode && ibSelected.length > 0" style="position:relative;">
                <button
                    type="button"
                    @click="ibBatchOpen = !ibBatchOpen"
                    style="
                        display:inline-flex; align-items:center; gap:6px;
                        height:32px; padding:0 14px;
                        background:linear-gradient(135deg,#6a0f70 0%,#9b3da0 100%);
                        border:none; border-radius:6px;
                        font-family:'Inter',sans-serif; font-size:12px; font-weight:600; color:#fff;
                        cursor:pointer;
                    "
                >
                    Batch Actions (<span x-text="ibSelected.length"></span>)
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                {{-- Dropdown --}}
                <div
                    x-show="ibBatchOpen"
                    @click.outside="ibBatchOpen = false"
                    style="
                        position:absolute; top:36px; right:0; z-index:20;
                        background:#ffffff;
                        border:1px solid rgba(185,92,183,0.18); border-radius:8px;
                        box-shadow:0 8px 24px rgba(106,15,112,0.12);
                        min-width:160px; padding:6px 0;
                        display:none;
                    "
                >
                    @php
                        $batchActions = [
                            ['label'=>'Save All',        'icon'=>'<path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>'],
                            ['label'=>'Convert to Publish','icon'=>'<polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 01-4 4H3"/>'],
                            ['label'=>'Delete Selected', 'icon'=>'<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/>'],
                        ];
                    @endphp
                    @foreach($batchActions as $ba)
                    <button type="button" style="
                        display:flex; align-items:center; gap:9px;
                        width:100%; padding:8px 14px;
                        background:none; border:none;
                        font-family:'Inter',sans-serif; font-size:12.5px; color:#1e0a2c;
                        cursor:pointer; text-align:left;
                        transition:background 100ms;
                    "
                    onmouseover="this.style.background='#f9f3fa'"
                    onmouseout="this.style.background='none'"
                    >
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $ba['icon'] !!}</svg>
                        {{ $ba['label'] }}
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Toggle batch mode button --}}
            <button
                type="button"
                @click="ibBatchMode = !ibBatchMode; if(!ibBatchMode){ ibSelected = []; ibBatchOpen = false; }"
                :style="ibBatchMode ? 'background:#f3eaf4; border-color:#9b3da0; color:#6a0f70;' : ''"
                style="
                    display:inline-flex; align-items:center; gap:6px;
                    height:32px; padding:0 14px;
                    background:#ffffff;
                    border:1px solid rgba(185,92,183,0.25); border-radius:6px;
                    font-family:'Inter',sans-serif; font-size:12px; font-weight:500; color:#5a4868;
                    cursor:pointer; transition:background 150ms, color 150ms;
                "
            >
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                </svg>
                <span x-text="ibBatchMode ? 'Exit Select' : 'Select'"></span>
            </button>
        </div>
    </div>
    {{-- /META ROW --}}


    {{-- ── CARD GRID ────────────────────────────────────────────────── --}}
    @if(count($bankIdeas) > 0)
    <div style="
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
    ">
        @foreach($bankIdeas as $bIdx => $bIdea)
        {{-- Wrapper: adds checkbox overlay in batch mode --}}
        <div style="position:relative;">

            {{-- Status badge (top-right of card) --}}
            @php
                $statusColors = ['Draft'=>['bg'=>'#fff8e1','text'=>'#b26a00'],'Saved'=>['bg'=>'#e8f5e9','text'=>'#2e7d32'],'Converted'=>['bg'=>'#ede7f6','text'=>'#6a0f70']];
                $sc = $statusColors[$bIdea['status']] ?? ['bg'=>'#f5f5f5','text'=>'#666'];
            @endphp
            <div style="
                position:absolute; top:8px; right:8px; z-index:2;
                height:20px; padding:0 8px;
                background:{{ $sc['bg'] }}; color:{{ $sc['text'] }};
                border-radius:4px;
                font-family:'Inter',sans-serif; font-size:10px; font-weight:600;
                display:flex; align-items:center;
                text-transform:uppercase; letter-spacing:0.04em;
                pointer-events:none;
            ">{{ $bIdea['status'] }}</div>

            {{-- Batch checkbox overlay --}}
            <div
                x-show="ibBatchMode"
                style="position:absolute; top:8px; left:8px; z-index:3;"
                @click.stop="toggleSelect({{ $bIdx }})"
            >
                <div
                    :style="isSelected({{ $bIdx }}) ? 'background:#6a0f70; border-color:#6a0f70;' : 'background:#fff; border-color:rgba(185,92,183,0.40);'"
                    style="
                        width:18px; height:18px; border:2px solid; border-radius:4px;
                        display:flex; align-items:center; justify-content:center;
                        cursor:pointer; transition:background 120ms, border-color 120ms;
                    "
                >
                    <svg x-show="isSelected({{ $bIdx }})" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
            </div>

            {{-- The actual idea card (reuse existing partial) --}}
            @include('marketing.brainstorm.partials._idea-card', [
                'idea'  => $bIdea,
                'index' => $bIdx + 100, {{-- offset to avoid index collision with AI Generate tab --}}
            ])
        </div>
        @endforeach
    </div>

    @else
    {{-- ── EMPTY STATE ──────────────────────────────────────────────── --}}
    <div style="
        text-align: center;
        padding: 60px 20px;
        background: #ffffff;
        border: 1px dashed rgba(185,92,183,0.25);
        border-radius: 12px;
    ">
        <div style="
            width: 56px; height: 56px;
            background: #f3eaf4;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
        ">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="rgba(155,106,173,0.60)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>
            </svg>
        </div>
        <h3 style="font-family:'Cormorant Garamond',serif; font-size:18px; font-weight:600; color:#1e0a2c; margin:0 0 6px;">No ideas yet</h3>
        <p style="font-family:'Inter',sans-serif; font-size:13px; color:#7a6884; margin:0 0 20px;">Start capturing ideas with Quick Idea or generate them with AI.</p>
        <button type="button"
            @click="activeTab = 'quick-idea'"
            style="
                height:36px; padding:0 20px;
                background:linear-gradient(135deg,#6a0f70 0%,#9b3da0 100%);
                border:none; border-radius:7px;
                font-family:'Inter',sans-serif; font-size:13px; font-weight:600; color:#fff;
                cursor:pointer;
            "
        >+ Add Quick Idea</button>
    </div>
    @endif
    {{-- /CARD GRID / EMPTY STATE --}}

</div>
{{-- /IDEA BANK TAB --}}

{{-- ── TAB: FESTIVAL PLANNER ────────────────────────────────────────── --}}
@php
$festivalEvents = [
    ['day'=>5,  'name'=>'World Environment Day',   'category'=>'National',  'content_type'=>'Post',     'color'=>'#34a853'],
    ['day'=>7,  'name'=>'Dental Caries Awareness', 'category'=>'Dental',    'content_type'=>'Carousel', 'color'=>'#6a0f70'],
    ['day'=>15, 'name'=>"Father's Day",            'category'=>'National',  'content_type'=>'Reel',     'color'=>'#1877f2'],
    ['day'=>20, 'name'=>'World Oral Health Day',   'category'=>'Dental',    'content_type'=>'Blog',     'color'=>'#6a0f70'],
    ['day'=>21, 'name'=>'International Yoga Day',  'category'=>'National',  'content_type'=>'Post',     'color'=>'#e1306c'],
    ['day'=>24, 'name'=>'Oral Cancer Awareness',   'category'=>'Dental',    'content_type'=>'Story',    'color'=>'#6a0f70'],
    ['day'=>29, 'name'=>'Eid al-Adha',             'category'=>'Religious', 'content_type'=>'Post',     'color'=>'#fbbc04'],
];
// Days that have a festival dot — for calendar rendering
$festivalDays = array_column($festivalEvents, 'day');

$catColors = [
    'Dental'   => ['bg'=>'#ede7f6','text'=>'#6a0f70'],
    'National' => ['bg'=>'#e3f2fd','text'=>'#0d47a1'],
    'Regional' => ['bg'=>'#fff3e0','text'=>'#e65100'],
    'Religious'=> ['bg'=>'#fff8e1','text'=>'#f57f17'],
];
$ctColors = [
    'Post'    =>['bg'=>'#e8f5e9','text'=>'#2e7d32'],
    'Reel'    =>['bg'=>'#fce4ec','text'=>'#880e4f'],
    'Carousel'=>['bg'=>'#e3f2fd','text'=>'#0d47a1'],
    'Story'   =>['bg'=>'#fff3e0','text'=>'#e65100'],
    'Blog'    =>['bg'=>'#f3e5f5','text'=>'#4a148c'],
];
@endphp

<div
    x-show="activeTab === 'festival-planner'"
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    style="display:none;"
    x-data="{
        fpSelectedDay: null,
        fpMonth: 'June 2026',
        selectDay(d) { this.fpSelectedDay = (this.fpSelectedDay === d ? null : d); },
        isSelected(d) { return this.fpSelectedDay === d; }
    }"
>
<div style="display:grid; grid-template-columns:300px 1fr; gap:20px; align-items:flex-start;">

    {{-- ══ LEFT: MINI CALENDAR ══════════════════════════════════════ --}}
    <div style="
        background:#ffffff;
        border:1px solid rgba(185,92,183,0.14);
        border-radius:12px;
        padding:20px;
        flex-shrink:0;
    ">
        {{-- Month navigator --}}
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
            <button type="button" style="
                width:28px; height:28px;
                display:flex; align-items:center; justify-content:center;
                background:#faf7fb; border:1px solid rgba(185,92,183,0.22);
                border-radius:6px; cursor:pointer; color:#6a0f70;
            ">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <span style="font-family:'Inter',sans-serif; font-size:14px; font-weight:600; color:#1e0a2c;" x-text="fpMonth">June 2026</span>
            <button type="button" style="
                width:28px; height:28px;
                display:flex; align-items:center; justify-content:center;
                background:#faf7fb; border:1px solid rgba(185,92,183,0.22);
                border-radius:6px; cursor:pointer; color:#6a0f70;
            ">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>

        {{-- Day-of-week headers --}}
        <div style="display:grid; grid-template-columns:repeat(7,1fr); gap:2px; margin-bottom:4px;">
            @foreach(['S','M','T','W','T','F','S'] as $dh)
            <div style="text-align:center; font-family:'Inter',sans-serif; font-size:10.5px; font-weight:600; color:#9b6aad; padding:4px 0;">{{ $dh }}</div>
            @endforeach
        </div>

        {{-- Calendar grid — June 2026 starts on Monday (col index 1) --}}
        {{-- We render 35 cells: 0 = empty, 1–30 = June days --}}
        <div style="display:grid; grid-template-columns:repeat(7,1fr); gap:3px;">
            @php
                // June 2026: 1st = Monday → 1 empty cell before day 1 (Sunday=0 col)
                $startOffset = 1; // Monday
                $daysInMonth = 30;
                $totalCells   = $startOffset + $daysInMonth;
                $totalCells   = $totalCells + (7 - ($totalCells % 7 ?: 7)); // round up to full week
            @endphp
            @for($cell = 0; $cell < $totalCells; $cell++)
                @php $day = $cell - $startOffset + 1; @endphp
                @if($day < 1 || $day > $daysInMonth)
                    <div style="height:34px;"></div>
                @else
                    @php $hasFestival = in_array($day, $festivalDays); @endphp
                    <button
                        type="button"
                        @click="selectDay({{ $day }})"
                        :style="isSelected({{ $day }})
                            ? 'background:#6a0f70; color:#fff;'
                            : '{{ $hasFestival ? 'background:#f3eaf4; color:#6a0f70;' : 'background:none; color:#1e0a2c;' }}'"
                        style="
                            height:34px; width:100%;
                            border:none; border-radius:7px;
                            font-family:'Inter',sans-serif;
                            font-size:12.5px; font-weight:500;
                            cursor:pointer;
                            position:relative;
                            transition:background 120ms, color 120ms;
                        "
                    >
                        {{ $day }}
                        {{-- Festival dot --}}
                        @if($hasFestival)
                        <span
                            :style="isSelected({{ $day }}) ? 'background:#fff;' : 'background:#6a0f70;'"
                            style="
                                position:absolute; bottom:4px; left:50%; transform:translateX(-50%);
                                width:4px; height:4px; border-radius:50%;
                                transition:background 120ms;
                            "
                        ></span>
                        @endif
                    </button>
                @endif
            @endfor
        </div>

        {{-- Legend --}}
        <div style="display:flex; align-items:center; gap:6px; margin-top:14px; padding-top:14px; border-top:1px solid rgba(185,92,183,0.10);">
            <span style="width:8px; height:8px; background:#6a0f70; border-radius:50%; flex-shrink:0;"></span>
            <span style="font-family:'Inter',sans-serif; font-size:11.5px; color:#7a6884;">Festival / Awareness date</span>
        </div>
        <div style="display:flex; align-items:center; gap:6px; margin-top:7px;">
            <span style="width:8px; height:8px; background:#f3eaf4; border:1px solid rgba(185,92,183,0.30); border-radius:50%; flex-shrink:0;"></span>
            <span style="font-family:'Inter',sans-serif; font-size:11.5px; color:#7a6884;">Has upcoming event</span>
        </div>
    </div>
    {{-- /LEFT CALENDAR --}}


    {{-- ══ RIGHT: FESTIVAL LIST ══════════════════════════════════════ --}}
    <div>
        {{-- Header --}}
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
            <div>
                <h3 style="font-family:'Inter',sans-serif; font-size:14px; font-weight:600; color:#1e0a2c; margin:0 0 2px;">
                    <span x-show="fpSelectedDay === null">All events — June 2026</span>
                    <span x-show="fpSelectedDay !== null">Events on <span x-text="fpSelectedDay + ' June 2026'"></span></span>
                </h3>
                <p style="font-family:'Inter',sans-serif; font-size:12px; color:#7a6884; margin:0;">
                    {{ count($festivalEvents) }} occasions this month
                </p>
            </div>
            <button
                type="button"
                x-show="fpSelectedDay !== null"
                @click="fpSelectedDay = null"
                style="
                    height:30px; padding:0 12px;
                    background:#faf7fb; border:1px solid rgba(185,92,183,0.22); border-radius:6px;
                    font-family:'Inter',sans-serif; font-size:12px; color:#6a0f70; font-weight:500;
                    cursor:pointer;
                "
            >Show all</button>
        </div>

        {{-- Festival entries --}}
        <div style="display:flex; flex-direction:column; gap:10px;">
            @foreach($festivalEvents as $ev)
            @php
                $cc = $catColors[$ev['category']] ?? ['bg'=>'#f5f5f5','text'=>'#666'];
                $tc = $ctColors[$ev['content_type']] ?? ['bg'=>'#f5f5f5','text'=>'#666'];
            @endphp
            <div
                x-show="fpSelectedDay === null || fpSelectedDay === {{ $ev['day'] }}"
                style="
                    display:flex;
                    align-items:center;
                    gap:14px;
                    background:#ffffff;
                    border:1px solid rgba(185,92,183,0.14);
                    border-radius:10px;
                    padding:14px 16px;
                    transition:box-shadow 150ms, border-color 150ms;
                "
                onmouseover="this.style.boxShadow='0 4px 14px rgba(106,15,112,0.09)'; this.style.borderColor='rgba(185,92,183,0.28)'"
                onmouseout="this.style.boxShadow='none'; this.style.borderColor='rgba(185,92,183,0.14)'"
            >
                {{-- Date pill --}}
                <div style="
                    flex-shrink:0;
                    width:52px; height:52px;
                    background:#f3eaf4;
                    border-radius:10px;
                    display:flex; flex-direction:column;
                    align-items:center; justify-content:center;
                ">
                    <span style="font-family:'Inter',sans-serif; font-size:18px; font-weight:700; color:#6a0f70; line-height:1;">{{ $ev['day'] }}</span>
                    <span style="font-family:'Inter',sans-serif; font-size:10px; font-weight:500; color:#9b6aad; text-transform:uppercase; letter-spacing:0.05em;">Jun</span>
                </div>

                {{-- Info --}}
                <div style="flex:1; min-width:0;">
                    <p style="font-family:'Inter',sans-serif; font-size:13.5px; font-weight:600; color:#1e0a2c; margin:0 0 6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $ev['name'] }}</p>
                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                        {{-- Category badge --}}
                        <span style="
                            height:20px; padding:0 8px;
                            background:{{ $cc['bg'] }}; color:{{ $cc['text'] }};
                            border-radius:4px;
                            font-family:'Inter',sans-serif; font-size:10.5px; font-weight:600;
                            display:inline-flex; align-items:center;
                            letter-spacing:0.03em;
                        ">{{ $ev['category'] }}</span>
                        {{-- Content type badge --}}
                        <span style="
                            height:20px; padding:0 8px;
                            background:{{ $tc['bg'] }}; color:{{ $tc['text'] }};
                            border-radius:4px;
                            font-family:'Inter',sans-serif; font-size:10.5px; font-weight:500;
                            display:inline-flex; align-items:center;
                        ">{{ $ev['content_type'] }}</span>
                    </div>
                </div>

                {{-- Create Idea button --}}
                <button
                    type="button"
                    @click="activeTab = 'quick-idea'"
                    style="
                        flex-shrink:0;
                        display:inline-flex; align-items:center; gap:6px;
                        height:32px; padding:0 14px;
                        background:linear-gradient(135deg,#6a0f70 0%,#9b3da0 100%);
                        border:none; border-radius:6px;
                        font-family:'Inter',sans-serif; font-size:12px; font-weight:600; color:#fff;
                        cursor:pointer; transition:opacity 150ms;
                        white-space:nowrap;
                    "
                    onmouseover="this.style.opacity='0.88'"
                    onmouseout="this.style.opacity='1'"
                >
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Create Idea
                </button>
            </div>
            @endforeach
        </div>
        {{-- /festival entries --}}

    </div>
    {{-- /RIGHT LIST --}}

</div>{{-- /two-column grid --}}
</div>
{{-- /FESTIVAL PLANNER TAB --}}


{{-- ── DETAIL PANEL (slide-in from right, Phase 2.2-B) ─────────────── --}}
@include('marketing.brainstorm.partials._idea-detail-panel')

{{-- Overlay backdrop when panel is open --}}
<div
    x-show="panelOpen"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @click="closePanel()"
    style="
        position: fixed;
        inset: 0;
        background: rgba(30,10,44,0.25);
        z-index: 39;
        display: none;
    "
></div>

</div>{{-- /root Alpine scope --}}

@endsection
