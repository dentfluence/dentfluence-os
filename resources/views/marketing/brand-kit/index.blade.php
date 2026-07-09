{{--
|==========================================================================
| Marketing — Brand Kit
| File: resources/views/marketing/brand-kit/index.blade.php
|
| Phase 2.7 — Full UI build (UI-only, mock data from BrandKitController).
| Layout: 2-column — sticky vertical section nav (left) + form cards (right).
| 8 Sections: Logo | Brand Colors | Typography | Clinic Info |
|             Social Links | Default CTA | Default Hashtags | AI Settings
|==========================================================================
--}}
@extends('marketing.layouts.app')

@php $marketingPageTitle = 'Brand Kit'; @endphp
@section('page-title', 'Marketing — Brand Kit')

@section('marketing-content')

{{-- ── Page Header ─────────────────────────────────────────────────────── --}}
<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px;">
    <div>
        <h1 style="font-family:'Cormorant Garamond',serif; font-size:26px; font-weight:700; color:#1e0a2c; margin:0 0 4px;">Brand Kit</h1>
        <p style="font-family:'Inter',sans-serif; font-size:13px; font-weight:300; color:#7a6884; margin:0;">
            Logos, colours, fonts, and AI voice guidelines for <strong style="font-weight:500; color:#4a3060;">{{ $brandKit['clinic_name'] }}</strong>
        </p>
    </div>
    {{-- Last synced badge --}}
    <span style="
        font-family:'Inter',sans-serif; font-size:11.5px; font-weight:400; color:#7a6884;
        background:#f9f3fa; border:1px solid rgba(185,92,183,0.18);
        border-radius:4px; padding:5px 12px;
    ">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:4px;">
            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
        Last synced: 2 hours ago
    </span>
</div>

{{-- ── 2-Panel Layout ───────────────────────────────────────────────────── --}}
<div style="display:flex; align-items:flex-start; gap:24px;">

    {{-- ══ LEFT: Sticky Section Nav ══════════════════════════════════════ --}}
    <nav id="bk-sidenav" style="
        width:196px;
        flex-shrink:0;
        position:sticky;
        top:72px; /* clears marketing subnav */
        background:#fff;
        border:1px solid rgba(185,92,183,0.14);
        border-radius:10px;
        overflow:hidden;
        box-shadow:0 1px 4px rgba(106,15,112,0.06);
    ">
        <div style="padding:14px 0 10px; border-bottom:1px solid rgba(185,92,183,0.1); text-align:center;">
            <span style="font-family:'Inter',sans-serif; font-size:10.5px; font-weight:600; letter-spacing:.06em; color:#9b6aad; text-transform:uppercase;">Sections</span>
        </div>

        @php
        $navItems = [
            ['id' => 'logo',       'label' => 'Logo',             'icon' => '<path d="M21 16V8a2 2 0 00-1-1.73L13 2.27a2 2 0 00-2 0L4 6.27A2 2 0 003 8v8a2 2 0 001 1.73L11 21.73a2 2 0 002 0l7-4.05A2 2 0 0021 16z"/>'],
            ['id' => 'colors',     'label' => 'Brand Colors',     'icon' => '<circle cx="13.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="10.5" r="2.5"/><circle cx="8.5" cy="7.5" r="2.5"/><circle cx="6.5" cy="12.5" r="2.5"/><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/>'],
            ['id' => 'typography', 'label' => 'Typography',       'icon' => '<polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/>'],
            ['id' => 'clinic',     'label' => 'Clinic Info',      'icon' => '<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>'],
            ['id' => 'social',     'label' => 'Social Links',     'icon' => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>'],
            ['id' => 'cta',        'label' => 'Default CTA',      'icon' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M9 12l2 2 4-4"/>'],
            ['id' => 'hashtags',   'label' => 'Default Hashtags', 'icon' => '<line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/><line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/>'],
            ['id' => 'ai',         'label' => 'AI Settings',      'icon' => '<path d="M12 2a10 10 0 100 20A10 10 0 0012 2zm0 0v4m0 14v-4M2 12h4m14 0h-4"/><circle cx="12" cy="12" r="3"/>'],
        ];
        @endphp

        @foreach($navItems as $item)
        <a
            href="#bk-{{ $item['id'] }}"
            data-bk-nav="{{ $item['id'] }}"
            onclick="bkSetActive('{{ $item['id'] }}')"
            style="
                display:flex; align-items:center; gap:9px;
                padding:10px 16px;
                font-family:'Inter',sans-serif; font-size:12.5px; font-weight:400;
                color:#5a4868; text-decoration:none;
                border-left:3px solid transparent;
                transition:all 140ms;
            "
            class="bk-nav-link"
            id="bk-nav-{{ $item['id'] }}"
        >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                {!! $item['icon'] !!}
            </svg>
            {{ $item['label'] }}
        </a>
        @endforeach
    </nav>

    {{-- ══ RIGHT: Form Sections ════════════════════════════════════════════ --}}
    <div style="flex:1; min-width:0; display:flex; flex-direction:column; gap:20px;">

        {{-- ── SECTION CARD MACRO (repeated pattern) ───────────────────── --}}
        {{-- Each card: white box, top label bar, content, footer save row --}}

        {{-- ─── 1. LOGO ──────────────────────────────────────────────── --}}
        <div id="bk-logo" class="bk-section-card" style="
            background:#fff;
            border:1px solid rgba(185,92,183,0.14);
            border-radius:10px;
            box-shadow:0 1px 4px rgba(106,15,112,0.05);
            overflow:hidden;
        ">
            {{-- Card header --}}
            <div style="padding:16px 22px 14px; border-bottom:1px solid rgba(185,92,183,0.09); display:flex; align-items:center; gap:10px;">
                <div style="width:32px; height:32px; border-radius:8px; background:rgba(108,63,232,0.09); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6C3FE8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 00-1-1.73L13 2.27a2 2 0 00-2 0L4 6.27A2 2 0 003 8v8a2 2 0 001 1.73L11 21.73a2 2 0 002 0l7-4.05A2 2 0 0021 16z"/>
                    </svg>
                </div>
                <div>
                    <div style="font-family:'Inter',sans-serif; font-size:14px; font-weight:600; color:#1e0a2c;">Logo</div>
                    <div style="font-family:'Inter',sans-serif; font-size:11.5px; font-weight:300; color:#9b6aad;">Upload PNG/SVG logos. Used in posts, documents, and reports.</div>
                </div>
            </div>

            <div style="padding:22px;">
                {{-- Hidden file inputs — one per logo slot --}}
                <input type="file" id="file-logo_light" accept=".png,.jpg,.jpeg,.svg" style="display:none"
                    onchange="bkUploadLogo(this, 'logo_light', 'zone-logo_light')">
                <input type="file" id="file-logo_dark"  accept=".png,.jpg,.jpeg,.svg" style="display:none"
                    onchange="bkUploadLogo(this, 'logo_dark',  'zone-logo_dark')">
                <input type="file" id="file-logo_icon"  accept=".png,.jpg,.jpeg,.svg" style="display:none"
                    onchange="bkUploadLogo(this, 'logo_icon',  'zone-logo_icon')">

                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px;">
                    @php
                        $logoSlots = [
                            ['field' => 'logo_light', 'label' => 'Primary Logo (Light)', 'bg' => '#fff',    'text' => '#1e0a2c', 'border' => 'rgba(185,92,183,0.2)', 'hint' => 'For light backgrounds',  'current' => $brandKit['logo_light']  ?? null],
                            ['field' => 'logo_dark',  'label' => 'Primary Logo (Dark)',  'bg' => '#1e0a2c', 'text' => '#fff',    'border' => 'rgba(255,255,255,0.1)', 'hint' => 'For dark backgrounds',  'current' => $brandKit['logo_dark']   ?? null],
                            ['field' => 'logo_icon',  'label' => 'Secondary Logo',       'bg' => '#f9f3fa', 'text' => '#1e0a2c', 'border' => 'rgba(185,92,183,0.2)', 'hint' => 'Watermark or small usage','current' => $brandKit['logo_icon']   ?? null],
                        ];
                    @endphp

                    @foreach($logoSlots as $slot)
                    <div>
                        <div style="font-family:'Inter',sans-serif; font-size:12px; font-weight:500; color:#4a3060; margin-bottom:8px;">
                            {{ $slot['label'] }}
                        </div>

                        {{-- Upload zone — clicking triggers the hidden input --}}
                        <div id="zone-{{ $slot['field'] }}"
                            onclick="document.getElementById('file-{{ $slot['field'] }}').click()"
                            style="
                                background:{{ $slot['bg'] }};
                                border:2px dashed {{ $slot['border'] }};
                                border-radius:8px;
                                height:120px;
                                display:flex; flex-direction:column;
                                align-items:center; justify-content:center;
                                gap:8px; cursor:pointer;
                                transition:border-color 150ms;
                                position:relative; overflow:hidden;
                            "
                            onmouseover="this.style.borderColor='#6C3FE8'"
                            onmouseout="this.style.borderColor='{{ $slot['border'] }}'">

                            @if($slot['current'])
                            {{-- Show existing logo --}}
                            <img src="{{ $slot['current'] }}" alt="{{ $slot['label'] }}"
                                style="max-height:90px; max-width:100%; object-fit:contain; border-radius:4px;">
                            <span style="
                                position:absolute; bottom:6px; right:8px;
                                font-family:'Inter',sans-serif; font-size:9px;
                                color:{{ $slot['text'] }}; opacity:0.5;
                            ">Click to replace</span>
                            @else
                            {{-- Empty state --}}
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="{{ $slot['text'] }}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.35;">
                                <polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/>
                                <path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3"/>
                            </svg>
                            <span style="font-family:'Inter',sans-serif; font-size:11.5px; color:{{ $slot['text'] }}; opacity:0.5;">Click to upload</span>
                            @endif

                            {{-- Upload progress overlay (hidden until uploading) --}}
                            <div id="progress-{{ $slot['field'] }}" style="
                                display:none; position:absolute; inset:0;
                                background:rgba(108,63,232,0.85);
                                align-items:center; justify-content:center;
                                flex-direction:column; gap:6px;
                            ">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation:bkSpin 1s linear infinite;">
                                    <path d="M21 12a9 9 0 11-6.219-8.56"/>
                                </svg>
                                <span style="font-family:'Inter',sans-serif; font-size:11px; color:#fff;">Uploading…</span>
                            </div>
                        </div>

                        <div style="font-family:'Inter',sans-serif; font-size:10.5px; color:#9b6aad; margin-top:6px;">
                            {{ $slot['hint'] }}
                        </div>
                    </div>
                    @endforeach
                </div>

                <div style="margin-top:14px; font-family:'Inter',sans-serif; font-size:11px; color:#b0a0bb; background:#faf7fd; border-radius:6px; padding:8px 12px;">
                    Accepted formats: <strong style="font-weight:500; color:#7a6884;">PNG, SVG, JPG</strong> &nbsp;·&nbsp;
                    Max size: <strong style="font-weight:500; color:#7a6884;">5 MB</strong> &nbsp;·&nbsp;
                    Recommended: 400×200 px minimum
                </div>

                {{-- Notification bar (shown after upload) --}}
                <div id="bk-logo-notice" style="display:none; margin-top:10px; padding:8px 14px; border-radius:6px; font-family:'Inter',sans-serif; font-size:12px;"></div>
            </div>

            {{-- Save footer --}}
            <div style="padding:14px 22px; border-top:1px solid rgba(185,92,183,0.09); display:flex; align-items:center; justify-content:flex-end; gap:14px;">
                <span id="bk-logo-saved-time" style="font-family:'Inter',sans-serif; font-size:11px; color:#b0a0bb;">
                    {{ $brandKit['logo_primary'] || ($brandKit['logo_light'] ?? false) || ($brandKit['logo_dark'] ?? false) ? 'Logos saved' : 'No logos uploaded yet' }}
                </span>
            </div>
        </div>

        @push('scripts')
        <style>
            @keyframes bkSpin { to { transform: rotate(360deg); } }
        </style>
        <script>
        function bkUploadLogo(input, field, zoneId) {
            const file = input.files[0];
            if (!file) return;

            // Show spinner overlay
            const prog = document.getElementById('progress-' + field);
            prog.style.display = 'flex';

            const form = new FormData();
            form.append('file', file);
            form.append('field', field);
            form.append('_token', '{{ csrf_token() }}');

            fetch('{{ route("marketing.brand-kit.logo") }}', {
                method: 'POST',
                body: form,
            })
            .then(r => r.json())
            .then(data => {
                prog.style.display = 'none';

                if (data.success) {
                    // Replace zone contents with preview
                    const zone = document.getElementById(zoneId);
                    zone.innerHTML =
                        '<img src="' + data.url + '" alt="Logo" style="max-height:90px;max-width:100%;object-fit:contain;border-radius:4px;">' +
                        '<span style="position:absolute;bottom:6px;right:8px;font-family:\'Inter\',sans-serif;font-size:9px;opacity:0.5;">Click to replace</span>' +
                        document.getElementById('progress-' + field).outerHTML;
                    zone.onclick = () => document.getElementById('file-' + field).click();

                    // Show success notice
                    const notice = document.getElementById('bk-logo-notice');
                    notice.style.display = 'block';
                    notice.style.background = '#f0fdf4';
                    notice.style.color = '#16a34a';
                    notice.style.border = '1px solid #bbf7d0';
                    notice.textContent = '✓ Logo uploaded successfully.';
                    document.getElementById('bk-logo-saved-time').textContent = 'Saved just now';
                    setTimeout(() => notice.style.display = 'none', 4000);
                } else {
                    prog.style.display = 'none';
                    alert(data.message || 'Upload failed. Please try again.');
                }
            })
            .catch(() => {
                prog.style.display = 'none';
                alert('Upload failed. Check your connection and try again.');
            });
        }
        </script>
        @endpush

        {{-- ─── 2. BRAND COLORS ────────────────────────────────────────── --}}
        <div id="bk-colors" class="bk-section-card" style="
            background:#fff; border:1px solid rgba(185,92,183,0.14);
            border-radius:10px; box-shadow:0 1px 4px rgba(106,15,112,0.05); overflow:hidden;
        ">
            <div style="padding:16px 22px 14px; border-bottom:1px solid rgba(185,92,183,0.09); display:flex; align-items:center; gap:10px;">
                <div style="width:32px; height:32px; border-radius:8px; background:rgba(108,63,232,0.09); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6C3FE8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><path d="M8.56 2.75c4.37 6.03 6.02 9.42 8.03 17.72"/>
                    </svg>
                </div>
                <div>
                    <div style="font-family:'Inter',sans-serif; font-size:14px; font-weight:600; color:#1e0a2c;">Brand Colors</div>
                    <div style="font-family:'Inter',sans-serif; font-size:11.5px; font-weight:300; color:#9b6aad;">Define your clinic's colour palette. Used in all AI-generated visuals.</div>
                </div>
            </div>

            <div style="padding:22px;">
                <div style="display:flex; flex-wrap:wrap; gap:16px; align-items:flex-start;" id="bk-color-list">
                    @foreach($brandKit['colors'] as $color)
                    <div style="
                        background:#faf7fd;
                        border:1px solid rgba(185,92,183,0.14);
                        border-radius:10px;
                        padding:16px;
                        display:flex;
                        flex-direction:column;
                        align-items:center;
                        gap:10px;
                        min-width:140px;
                    ">
                        {{-- Color circle --}}
                        <div style="
                            width:56px; height:56px; border-radius:50%;
                            background:{{ $color['hex'] }};
                            box-shadow:0 2px 8px {{ $color['hex'] }}55;
                            border:3px solid #fff;
                            cursor:pointer;
                        " title="Click to pick colour"></div>
                        {{-- Hex input --}}
                        <input
                            type="text"
                            value="{{ $color['hex'] }}"
                            maxlength="7"
                            style="
                                width:90px; text-align:center;
                                font-family:'Inter',sans-serif; font-size:12.5px; font-weight:500;
                                color:#1e0a2c; border:1px solid rgba(185,92,183,0.2);
                                border-radius:5px; padding:5px 8px;
                                background:#fff; outline:none;
                            "
                            onfocus="this.style.borderColor='#6C3FE8'"
                            onblur="this.style.borderColor='rgba(185,92,183,0.2)'"
                        >
                        <span style="font-family:'Inter',sans-serif; font-size:11px; font-weight:500; color:#7a6884; text-transform:uppercase; letter-spacing:.03em;">{{ $color['label'] }}</span>
                    </div>
                    @endforeach

                    {{-- Add Color --}}
                    <div style="
                        display:flex; flex-direction:column; align-items:center; justify-content:center;
                        min-width:140px; padding:16px; gap:8px;
                        border:2px dashed rgba(185,92,183,0.25); border-radius:10px;
                        cursor:pointer; transition:border-color 150ms, background 150ms;
                        background:transparent;
                    " onmouseover="this.style.borderColor='#6C3FE8'; this.style.background='rgba(108,63,232,0.04)'"
                       onmouseout="this.style.borderColor='rgba(185,92,183,0.25)'; this.style.background='transparent'">
                        <div style="width:40px; height:40px; border-radius:50%; background:rgba(185,92,183,0.1); display:flex; align-items:center; justify-content:center;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                        </div>
                        <span style="font-family:'Inter',sans-serif; font-size:12px; color:#9b6aad; font-weight:500;">+ Add Color</span>
                    </div>
                </div>
            </div>

            <div style="padding:14px 22px; border-top:1px solid rgba(185,92,183,0.09); display:flex; align-items:center; justify-content:flex-end; gap:14px;">
                <span style="font-family:'Inter',sans-serif; font-size:11px; color:#b0a0bb;">Last saved: 1 day ago</span>
                <button type="button" style="
                    background:#6C3FE8; color:#fff; border:none; border-radius:6px;
                    padding:7px 18px; font-family:'Inter',sans-serif; font-size:12.5px; font-weight:500;
                    cursor:pointer; transition:background 150ms;
                " onmouseover="this.style.background='#5A2ED6'" onmouseout="this.style.background='#6C3FE8'">
                    Save Colors
                </button>
            </div>
        </div>

        {{-- ─── 3. TYPOGRAPHY ──────────────────────────────────────────── --}}
        <div id="bk-typography" class="bk-section-card" style="
            background:#fff; border:1px solid rgba(185,92,183,0.14);
            border-radius:10px; box-shadow:0 1px 4px rgba(106,15,112,0.05); overflow:hidden;
        ">
            <div style="padding:16px 22px 14px; border-bottom:1px solid rgba(185,92,183,0.09); display:flex; align-items:center; gap:10px;">
                <div style="width:32px; height:32px; border-radius:8px; background:rgba(108,63,232,0.09); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6C3FE8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/>
                    </svg>
                </div>
                <div>
                    <div style="font-family:'Inter',sans-serif; font-size:14px; font-weight:600; color:#1e0a2c;">Typography</div>
                    <div style="font-family:'Inter',sans-serif; font-size:11.5px; font-weight:300; color:#9b6aad;">Select fonts for headings and body text in generated materials.</div>
                </div>
            </div>

            <div style="padding:22px;">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:22px;">
                    <div>
                        <label style="display:block; font-family:'Inter',sans-serif; font-size:12px; font-weight:500; color:#4a3060; margin-bottom:7px;">Heading Font</label>
                        <select id="bk-heading-font" style="
                            width:100%; padding:9px 12px;
                            font-family:'Inter',sans-serif; font-size:13px; color:#1e0a2c;
                            border:1px solid rgba(185,92,183,0.2); border-radius:6px;
                            background:#fff; outline:none; appearance:none;
                            background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%239b6aad' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E\");
                            background-repeat:no-repeat; background-position:right 10px center;
                        " onfocus="this.style.borderColor='#6C3FE8'" onblur="this.style.borderColor='rgba(185,92,183,0.2)'"
                          onchange="document.getElementById('bk-preview-heading').style.fontFamily=this.value+',sans-serif'">
                            @foreach(['Inter','Inter','Poppins','Montserrat','Lato','Nunito','Raleway','Open Sans','Cormorant Garamond'] as $font)
                            <option value="{{ $font }}" {{ $brandKit['heading_font'] === $font ? 'selected' : '' }}>{{ $font }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-family:'Inter',sans-serif; font-size:12px; font-weight:500; color:#4a3060; margin-bottom:7px;">Body Font</label>
                        <select id="bk-body-font" style="
                            width:100%; padding:9px 12px;
                            font-family:'Inter',sans-serif; font-size:13px; color:#1e0a2c;
                            border:1px solid rgba(185,92,183,0.2); border-radius:6px;
                            background:#fff; outline:none; appearance:none;
                            background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%239b6aad' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E\");
                            background-repeat:no-repeat; background-position:right 10px center;
                        " onfocus="this.style.borderColor='#6C3FE8'" onblur="this.style.borderColor='rgba(185,92,183,0.2)'"
                          onchange="document.getElementById('bk-preview-body').style.fontFamily=this.value+',sans-serif'">
                            @foreach(['Inter','Inter','Poppins','Montserrat','Lato','Nunito','Raleway','Open Sans'] as $font)
                            <option value="{{ $font }}" {{ $brandKit['body_font'] === $font ? 'selected' : '' }}>{{ $font }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Font preview --}}
                <div style="background:#faf7fd; border:1px solid rgba(185,92,183,0.12); border-radius:8px; padding:18px 20px;">
                    <div style="font-family:'Inter',sans-serif; font-size:10px; font-weight:600; letter-spacing:.08em; text-transform:uppercase; color:#9b6aad; margin-bottom:12px;">Preview</div>
                    <div id="bk-preview-heading" style="font-family:'{{ $brandKit['heading_font'] }}',sans-serif; font-size:22px; font-weight:700; color:#1e0a2c; margin-bottom:8px; line-height:1.2;">
                        The quick brown fox jumps over the lazy dog
                    </div>
                    <div id="bk-preview-body" style="font-family:'{{ $brandKit['body_font'] }}',sans-serif; font-size:13.5px; font-weight:400; color:#5a4868; line-height:1.6;">
                        The quick brown fox jumps over the lazy dog. Bright smiles start with healthy habits and regular dental visits.
                    </div>
                </div>
            </div>

            <div style="padding:14px 22px; border-top:1px solid rgba(185,92,183,0.09); display:flex; align-items:center; justify-content:flex-end; gap:14px;">
                <span style="font-family:'Inter',sans-serif; font-size:11px; color:#b0a0bb;">Last saved: 5 days ago</span>
                <button type="button" style="
                    background:#6C3FE8; color:#fff; border:none; border-radius:6px;
                    padding:7px 18px; font-family:'Inter',sans-serif; font-size:12.5px; font-weight:500;
                    cursor:pointer; transition:background 150ms;
                " onmouseover="this.style.background='#5A2ED6'" onmouseout="this.style.background='#6C3FE8'">
                    Save Typography
                </button>
            </div>
        </div>

        {{-- ─── 4. CLINIC INFO ─────────────────────────────────────────── --}}
        <div id="bk-clinic" class="bk-section-card" style="
            background:#fff; border:1px solid rgba(185,92,183,0.14);
            border-radius:10px; box-shadow:0 1px 4px rgba(106,15,112,0.05); overflow:hidden;
        ">
            <div style="padding:16px 22px 14px; border-bottom:1px solid rgba(185,92,183,0.09); display:flex; align-items:center; gap:10px;">
                <div style="width:32px; height:32px; border-radius:8px; background:rgba(108,63,232,0.09); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6C3FE8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                </div>
                <div>
                    <div style="font-family:'Inter',sans-serif; font-size:14px; font-weight:600; color:#1e0a2c;">Clinic Info</div>
                    <div style="font-family:'Inter',sans-serif; font-size:11.5px; font-weight:300; color:#9b6aad;">Core contact details auto-filled in posts, flyers, and email templates.</div>
                </div>
            </div>

            <div style="padding:22px;">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    @php
                    $clinicFields = [
                        ['key' => 'clinic_name', 'label' => 'Clinic Name',     'type' => 'text',     'placeholder' => 'e.g. Tulip Dental Clinic', 'span' => 2],
                        ['key' => 'phone',        'label' => 'Phone Number',    'type' => 'tel',      'placeholder' => '+91 98765 43210',          'span' => 1],
                        ['key' => 'email',        'label' => 'Email Address',   'type' => 'email',    'placeholder' => 'hello@clinic.in',          'span' => 1],
                        ['key' => 'website',      'label' => 'Website URL',     'type' => 'url',      'placeholder' => 'https://www.clinic.in',    'span' => 1],
                        ['key' => 'whatsapp',     'label' => 'WhatsApp Number', 'type' => 'tel',      'placeholder' => '+91 98765 43210',          'span' => 1],
                        ['key' => 'address',      'label' => 'Address',         'type' => 'textarea', 'placeholder' => 'Full clinic address',       'span' => 2],
                    ];
                    @endphp

                    @foreach($clinicFields as $field)
                    <div style="{{ $field['span'] == 2 ? 'grid-column:span 2;' : '' }}">
                        <label style="display:block; font-family:'Inter',sans-serif; font-size:12px; font-weight:500; color:#4a3060; margin-bottom:6px;">
                            {{ $field['label'] }}
                        </label>
                        @if($field['type'] === 'textarea')
                        <textarea
                            rows="3"
                            placeholder="{{ $field['placeholder'] }}"
                            style="
                                width:100%; box-sizing:border-box;
                                padding:9px 12px; resize:vertical;
                                font-family:'Inter',sans-serif; font-size:13px; color:#1e0a2c;
                                border:1px solid rgba(185,92,183,0.2); border-radius:6px;
                                background:#fff; outline:none; line-height:1.5;
                            "
                            onfocus="this.style.borderColor='#6C3FE8'" onblur="this.style.borderColor='rgba(185,92,183,0.2)'"
                        >{{ $brandKit[$field['key']] }}</textarea>
                        @else
                        <input
                            type="{{ $field['type'] }}"
                            value="{{ $brandKit[$field['key']] }}"
                            placeholder="{{ $field['placeholder'] }}"
                            style="
                                width:100%; box-sizing:border-box;
                                padding:9px 12px;
                                font-family:'Inter',sans-serif; font-size:13px; color:#1e0a2c;
                                border:1px solid rgba(185,92,183,0.2); border-radius:6px;
                                background:#fff; outline:none;
                            "
                            onfocus="this.style.borderColor='#6C3FE8'" onblur="this.style.borderColor='rgba(185,92,183,0.2)'"
                        >
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>

            <div style="padding:14px 22px; border-top:1px solid rgba(185,92,183,0.09); display:flex; align-items:center; justify-content:flex-end; gap:14px;">
                <span style="font-family:'Inter',sans-serif; font-size:11px; color:#b0a0bb;">Last saved: 2 days ago</span>
                <button type="button" style="
                    background:#6C3FE8; color:#fff; border:none; border-radius:6px;
                    padding:7px 18px; font-family:'Inter',sans-serif; font-size:12.5px; font-weight:500;
                    cursor:pointer; transition:background 150ms;
                " onmouseover="this.style.background='#5A2ED6'" onmouseout="this.style.background='#6C3FE8'">
                    Save Clinic Info
                </button>
            </div>
        </div>

        {{-- ─── 5. SOCIAL LINKS ────────────────────────────────────────── --}}
        <div id="bk-social" class="bk-section-card" style="
            background:#fff; border:1px solid rgba(185,92,183,0.14);
            border-radius:10px; box-shadow:0 1px 4px rgba(106,15,112,0.05); overflow:hidden;
        ">
            <div style="padding:16px 22px 14px; border-bottom:1px solid rgba(185,92,183,0.09); display:flex; align-items:center; gap:10px;">
                <div style="width:32px; height:32px; border-radius:8px; background:rgba(108,63,232,0.09); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6C3FE8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
                        <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                    </svg>
                </div>
                <div>
                    <div style="font-family:'Inter',sans-serif; font-size:14px; font-weight:600; color:#1e0a2c;">Social Links</div>
                    <div style="font-family:'Inter',sans-serif; font-size:11.5px; font-weight:300; color:#9b6aad;">Links auto-appended to published posts and campaign footers.</div>
                </div>
            </div>

            <div style="padding:22px; display:flex; flex-direction:column; gap:14px;">
                @php
                $socialFields = [
                    ['key' => 'instagram', 'label' => 'Instagram Handle', 'type' => 'text', 'icon' => '<rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>', 'color' => '#E1306C'],
                    ['key' => 'facebook_url', 'label' => 'Facebook Page URL', 'type' => 'url', 'icon' => '<path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/>', 'color' => '#1877F2'],
                    ['key' => 'google_biz_url', 'label' => 'Google Business URL', 'type' => 'url', 'icon' => '<circle cx="12" cy="12" r="10"/><path d="M8.56 2.75c4.37 6.03 6.02 9.42 8.03 17.72"/>', 'color' => '#4285F4'],
                ];
                @endphp

                @foreach($socialFields as $sf)
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="
                        width:36px; height:36px; border-radius:8px;
                        background:{{ $sf['color'] }}18;
                        display:flex; align-items:center; justify-content:center; flex-shrink:0;
                    ">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="{{ $sf['color'] }}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            {!! $sf['icon'] !!}
                        </svg>
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-family:'Inter',sans-serif; font-size:11.5px; font-weight:500; color:#4a3060; margin-bottom:4px;">{{ $sf['label'] }}</label>
                        <input
                            type="{{ $sf['type'] }}"
                            value="{{ $brandKit[$sf['key']] }}"
                            style="
                                width:100%; box-sizing:border-box;
                                padding:8px 12px;
                                font-family:'Inter',sans-serif; font-size:13px; color:#1e0a2c;
                                border:1px solid rgba(185,92,183,0.2); border-radius:6px;
                                background:#fff; outline:none;
                            "
                            onfocus="this.style.borderColor='#6C3FE8'" onblur="this.style.borderColor='rgba(185,92,183,0.2)'"
                        >
                    </div>
                </div>
                @endforeach
            </div>

            <div style="padding:14px 22px; border-top:1px solid rgba(185,92,183,0.09); display:flex; align-items:center; justify-content:flex-end; gap:14px;">
                <span style="font-family:'Inter',sans-serif; font-size:11px; color:#b0a0bb;">Last saved: 4 hours ago</span>
                <button type="button" style="
                    background:#6C3FE8; color:#fff; border:none; border-radius:6px;
                    padding:7px 18px; font-family:'Inter',sans-serif; font-size:12.5px; font-weight:500;
                    cursor:pointer; transition:background 150ms;
                " onmouseover="this.style.background='#5A2ED6'" onmouseout="this.style.background='#6C3FE8'">
                    Save Social Links
                </button>
            </div>
        </div>

        {{-- ─── 6. DEFAULT CTA ─────────────────────────────────────────── --}}
        <div id="bk-cta" class="bk-section-card" style="
            background:#fff; border:1px solid rgba(185,92,183,0.14);
            border-radius:10px; box-shadow:0 1px 4px rgba(106,15,112,0.05); overflow:hidden;
        ">
            <div style="padding:16px 22px 14px; border-bottom:1px solid rgba(185,92,183,0.09); display:flex; align-items:center; gap:10px;">
                <div style="width:32px; height:32px; border-radius:8px; background:rgba(108,63,232,0.09); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6C3FE8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="5" width="18" height="14" rx="2"/><path d="M9 12l2 2 4-4"/>
                    </svg>
                </div>
                <div>
                    <div style="font-family:'Inter',sans-serif; font-size:14px; font-weight:600; color:#1e0a2c;">Default CTA</div>
                    <div style="font-family:'Inter',sans-serif; font-size:11.5px; font-weight:300; color:#9b6aad;">The default call-to-action button used when AI creates posts for you.</div>
                </div>
            </div>

            <div style="padding:22px;">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">
                    <div>
                        <label style="display:block; font-family:'Inter',sans-serif; font-size:12px; font-weight:500; color:#4a3060; margin-bottom:6px;">Button Text</label>
                        <input
                            type="text"
                            id="bk-cta-text"
                            value="{{ $brandKit['cta_text'] }}"
                            placeholder="e.g. Book Appointment"
                            maxlength="40"
                            style="
                                width:100%; box-sizing:border-box; padding:9px 12px;
                                font-family:'Inter',sans-serif; font-size:13px; color:#1e0a2c;
                                border:1px solid rgba(185,92,183,0.2); border-radius:6px;
                                background:#fff; outline:none;
                            "
                            onfocus="this.style.borderColor='#6C3FE8'" onblur="this.style.borderColor='rgba(185,92,183,0.2)'"
                            oninput="document.getElementById('bk-cta-preview').textContent=this.value||'Button Text'"
                        >
                    </div>
                    <div>
                        <label style="display:block; font-family:'Inter',sans-serif; font-size:12px; font-weight:500; color:#4a3060; margin-bottom:6px;">Destination URL</label>
                        <input
                            type="url"
                            value="{{ $brandKit['cta_url'] }}"
                            placeholder="https://www.yourclinic.in/book"
                            style="
                                width:100%; box-sizing:border-box; padding:9px 12px;
                                font-family:'Inter',sans-serif; font-size:13px; color:#1e0a2c;
                                border:1px solid rgba(185,92,183,0.2); border-radius:6px;
                                background:#fff; outline:none;
                            "
                            onfocus="this.style.borderColor='#6C3FE8'" onblur="this.style.borderColor='rgba(185,92,183,0.2)'"
                        >
                    </div>
                </div>

                {{-- CTA Preview --}}
                <div style="background:#faf7fd; border:1px solid rgba(185,92,183,0.12); border-radius:8px; padding:18px 20px; text-align:center;">
                    <div style="font-family:'Inter',sans-serif; font-size:10px; font-weight:600; letter-spacing:.08em; text-transform:uppercase; color:#9b6aad; margin-bottom:14px;">Preview</div>
                    <button
                        id="bk-cta-preview"
                        type="button"
                        style="
                            background:#6C3FE8; color:#fff;
                            border:none; border-radius:8px;
                            padding:12px 28px;
                            font-family:'Inter',sans-serif; font-size:14px; font-weight:600;
                            cursor:default;
                            box-shadow:0 4px 14px rgba(108,63,232,0.35);
                        "
                    >{{ $brandKit['cta_text'] }}</button>
                </div>
            </div>

            <div style="padding:14px 22px; border-top:1px solid rgba(185,92,183,0.09); display:flex; align-items:center; justify-content:flex-end; gap:14px;">
                <span style="font-family:'Inter',sans-serif; font-size:11px; color:#b0a0bb;">Last saved: 6 hours ago</span>
                <button type="button" style="
                    background:#6C3FE8; color:#fff; border:none; border-radius:6px;
                    padding:7px 18px; font-family:'Inter',sans-serif; font-size:12.5px; font-weight:500;
                    cursor:pointer; transition:background 150ms;
                " onmouseover="this.style.background='#5A2ED6'" onmouseout="this.style.background='#6C3FE8'">
                    Save CTA
                </button>
            </div>
        </div>

        {{-- ─── 7. DEFAULT HASHTAGS ─────────────────────────────────────── --}}
        <div id="bk-hashtags" class="bk-section-card" style="
            background:#fff; border:1px solid rgba(185,92,183,0.14);
            border-radius:10px; box-shadow:0 1px 4px rgba(106,15,112,0.05); overflow:hidden;
        ">
            <div style="padding:16px 22px 14px; border-bottom:1px solid rgba(185,92,183,0.09); display:flex; align-items:center; gap:10px;">
                <div style="width:32px; height:32px; border-radius:8px; background:rgba(108,63,232,0.09); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6C3FE8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/>
                        <line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/>
                    </svg>
                </div>
                <div>
                    <div style="font-family:'Inter',sans-serif; font-size:14px; font-weight:600; color:#1e0a2c;">Default Hashtags</div>
                    <div style="font-family:'Inter',sans-serif; font-size:11.5px; font-weight:300; color:#9b6aad;">Auto-appended to every post. Add up to 15 hashtags.</div>
                </div>
            </div>

            <div style="padding:22px;">
                {{-- Tag chips --}}
                <div id="bk-tag-container" style="
                    display:flex; flex-wrap:wrap; gap:8px;
                    background:#faf7fd; border:1px solid rgba(185,92,183,0.2);
                    border-radius:8px; padding:12px 14px; min-height:56px;
                    align-items:center;
                " onclick="document.getElementById('bk-tag-input').focus()">
                    @foreach($brandKit['hashtags'] as $tag)
                    <span class="bk-tag" style="
                        display:inline-flex; align-items:center; gap:5px;
                        background:#fff; border:1px solid rgba(108,63,232,0.25);
                        border-radius:20px; padding:4px 10px;
                        font-family:'Inter',sans-serif; font-size:12px; font-weight:500; color:#6C3FE8;
                    ">
                        {{ $tag }}
                        <span onclick="this.parentElement.remove()" style="cursor:pointer; color:#9b6aad; line-height:1; font-size:14px;">&times;</span>
                    </span>
                    @endforeach
                    <input
                        id="bk-tag-input"
                        type="text"
                        placeholder="Type #hashtag and press Enter…"
                        style="
                            border:none; background:transparent; outline:none;
                            font-family:'Inter',sans-serif; font-size:12.5px; color:#1e0a2c;
                            min-width:180px; flex:1;
                        "
                        onkeydown="bkAddTag(event)"
                    >
                </div>
                <div style="font-family:'Inter',sans-serif; font-size:11px; color:#b0a0bb; margin-top:7px;">
                    Type a hashtag and press <kbd style="background:#f0e8f5; border:1px solid rgba(185,92,183,0.2); border-radius:3px; padding:1px 5px; font-size:10px;">Enter</kbd> to add. Click &times; to remove.
                </div>
            </div>

            <div style="padding:14px 22px; border-top:1px solid rgba(185,92,183,0.09); display:flex; align-items:center; justify-content:flex-end; gap:14px;">
                <span style="font-family:'Inter',sans-serif; font-size:11px; color:#b0a0bb;">Last saved: 1 hour ago</span>
                <button type="button" style="
                    background:#6C3FE8; color:#fff; border:none; border-radius:6px;
                    padding:7px 18px; font-family:'Inter',sans-serif; font-size:12.5px; font-weight:500;
                    cursor:pointer; transition:background 150ms;
                " onmouseover="this.style.background='#5A2ED6'" onmouseout="this.style.background='#6C3FE8'">
                    Save Hashtags
                </button>
            </div>
        </div>

        {{-- ─── 8. AI SETTINGS ─────────────────────────────────────────── --}}
        <div id="bk-ai" class="bk-section-card" style="
            background:#fff; border:1px solid rgba(185,92,183,0.14);
            border-radius:10px; box-shadow:0 1px 4px rgba(106,15,112,0.05); overflow:hidden;
        ">
            <div style="padding:16px 22px 14px; border-bottom:1px solid rgba(185,92,183,0.09); display:flex; align-items:center; gap:10px;">
                <div style="width:32px; height:32px; border-radius:8px; background:rgba(108,63,232,0.09); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6C3FE8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2a10 10 0 100 20A10 10 0 0012 2zm0 0v4m0 14v-4M2 12h4m14 0h-4"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                </div>
                <div>
                    <div style="font-family:'Inter',sans-serif; font-size:14px; font-weight:600; color:#1e0a2c;">AI Settings</div>
                    <div style="font-family:'Inter',sans-serif; font-size:11.5px; font-weight:300; color:#9b6aad;">Define the tone and voice the AI uses when creating content for your clinic.</div>
                </div>
            </div>

            <div style="padding:22px;">
                {{-- Tone selector — 4 radio cards --}}
                <div style="font-family:'Inter',sans-serif; font-size:12px; font-weight:500; color:#4a3060; margin-bottom:12px;">Tone of Voice</div>
                <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:24px;">
                    @php
                    $tones = [
                        ['value' => 'friendly',      'label' => 'Friendly',      'emoji' => '', 'desc' => 'Warm, approachable, and conversational'],
                        ['value' => 'professional',  'label' => 'Professional',  'emoji' => '', 'desc' => 'Formal, credible, and authoritative'],
                        ['value' => 'educational',   'label' => 'Educational',   'emoji' => '', 'desc' => 'Informative, clear, and helpful'],
                        ['value' => 'promotional',   'label' => 'Promotional',   'emoji' => '', 'desc' => 'Exciting, action-driven, and persuasive'],
                    ];
                    @endphp

                    @foreach($tones as $tone)
                    <label style="cursor:pointer;">
                        <input type="radio" name="bk_ai_tone" value="{{ $tone['value'] }}" {{ $brandKit['ai_tone'] === $tone['value'] ? 'checked' : '' }}
                            style="display:none;"
                            onchange="bkSetTone(this)"
                        >
                        <div class="bk-tone-card" data-tone="{{ $tone['value'] }}" style="
                            border:2px solid {{ $brandKit['ai_tone'] === $tone['value'] ? '#6C3FE8' : 'rgba(185,92,183,0.18)' }};
                            border-radius:10px; padding:14px 12px;
                            background:{{ $brandKit['ai_tone'] === $tone['value'] ? 'rgba(108,63,232,0.04)' : '#faf7fd' }};
                            text-align:center;
                            transition:all 150ms;
                        ">
                            <div style="font-size:22px; margin-bottom:6px;">{{ $tone['emoji'] }}</div>
                            <div style="font-family:'Inter',sans-serif; font-size:12.5px; font-weight:600; color:{{ $brandKit['ai_tone'] === $tone['value'] ? '#6C3FE8' : '#1e0a2c' }}; margin-bottom:4px;">
                                {{ $tone['label'] }}
                            </div>
                            <div style="font-family:'Inter',sans-serif; font-size:11px; font-weight:300; color:#7a6884; line-height:1.4;">
                                {{ $tone['desc'] }}
                            </div>
                        </div>
                    </label>
                    @endforeach
                </div>

                {{-- Brand Description textarea --}}
                <div>
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                        <label style="font-family:'Inter',sans-serif; font-size:12px; font-weight:500; color:#4a3060;">Brand Description</label>
                        <span id="bk-desc-count" style="font-family:'Inter',sans-serif; font-size:11px; color:#b0a0bb;">{{ strlen($brandKit['brand_description']) }}/300</span>
                    </div>
                    <textarea
                        id="bk-brand-desc"
                        rows="4"
                        maxlength="300"
                        placeholder="Your brand voice used when AI generates content for you"
                        style="
                            width:100%; box-sizing:border-box;
                            padding:10px 12px; resize:vertical;
                            font-family:'Inter',sans-serif; font-size:13px; color:#1e0a2c;
                            border:1px solid rgba(185,92,183,0.2); border-radius:6px;
                            background:#fff; outline:none; line-height:1.5;
                        "
                        onfocus="this.style.borderColor='#6C3FE8'" onblur="this.style.borderColor='rgba(185,92,183,0.2)'"
                        oninput="document.getElementById('bk-desc-count').textContent = this.value.length + '/300'"
                    >{{ $brandKit['brand_description'] }}</textarea>
                </div>
            </div>

            {{-- Save footer --}}
            <div style="padding:14px 22px; border-top:1px solid rgba(185,92,183,0.09); display:flex; align-items:center; justify-content:flex-end; gap:14px;">
                <span style="font-family:'Inter',sans-serif; font-size:11px; color:#b0a0bb;">Last saved: 3 hours ago</span>
                <button type="button" style="
                    background:#6C3FE8; color:#fff; border:none; border-radius:6px;
                    padding:7px 18px; font-family:'Inter',sans-serif; font-size:12.5px; font-weight:500;
                    cursor:pointer; transition:background 150ms;
                " onmouseover="this.style.background='#5A2ED6'" onmouseout="this.style.background='#6C3FE8'">
                    Save AI Settings
                </button>
            </div>
        </div>
        {{-- /#bk-ai — end of 8 section cards --}}

    </div>
    {{-- /RIGHT: Form Sections --}}

</div>
{{-- /2-Panel Layout --}}

<script>
// Section-nav active-state highlighter (scrollspy-lite).
function bkSetActive(id) {
    document.querySelectorAll('.bk-nav-link').forEach(el => {
        el.style.background = 'transparent';
        el.style.borderLeftColor = 'transparent';
        el.style.color = '#5a4868';
        el.style.fontWeight = '400';
    });
    const active = document.getElementById('bk-nav-' + id);
    if (active) {
        active.style.background = '#faf5ff';
        active.style.borderLeftColor = '#6C3FE8';
        active.style.color = '#6C3FE8';
        active.style.fontWeight = '500';
    }
}

// Hashtag chip input — Enter to add.
function bkAddTag(event) {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    const input = event.target;
    let value = input.value.trim().replace(/^#/, '');
    if (!value) return;

    const chip = document.createElement('span');
    chip.className = 'bk-tag';
    chip.style.cssText = 'display:inline-flex; align-items:center; gap:5px; background:#fff; border:1px solid rgba(108,63,232,0.25); border-radius:20px; padding:4px 10px; font-family:\'Inter\',sans-serif; font-size:12px; font-weight:500; color:#6C3FE8;';
    chip.innerHTML = '#' + value + ' <span onclick="this.parentElement.remove()" style="cursor:pointer; color:#9b6aad; line-height:1; font-size:14px;">&times;</span>';
    input.parentElement.insertBefore(chip, input);
    input.value = '';
}

// AI tone card selector visuals.
function bkSetTone(radio) {
    document.querySelectorAll('.bk-tone-card').forEach(card => {
        card.style.border = '2px solid rgba(185,92,183,0.18)';
        card.style.background = '#faf7fd';
        const label = card.querySelector('div:nth-child(2)');
        if (label) label.style.color = '#1e0a2c';
    });
    const selected = document.querySelector('.bk-tone-card[data-tone="' + radio.value + '"]');
    if (selected) {
        selected.style.border = '2px solid #6C3FE8';
        selected.style.background = 'rgba(108,63,232,0.04)';
        const label = selected.querySelector('div:nth-child(2)');
        if (label) label.style.color = '#6C3FE8';
    }
}
</script>

@endsection