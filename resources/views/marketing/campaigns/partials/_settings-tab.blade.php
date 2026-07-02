{{--
|==========================================================================
| Campaign Show — Settings Tab  (Phase 2.3-C)
| File: resources/views/marketing/campaigns/partials/_settings-tab.blade.php
|
| Edit form: name, description, objective, treatment category, start/end dates,
| budget, target audience, channel icon checkboxes.
| "Save Changes" + "Archive Campaign" actions.
| UI only — no form submission wired.
|==========================================================================
--}}

@php
$categories = [
    'General Dentistry', 'Cosmetic Dentistry', 'Orthodontics',
    'Oral Surgery', 'Periodontics', 'Pediatric Dentistry',
    'Implants', 'Teeth Whitening', 'Invisalign', 'Root Canal',
];

$channels = [
    ['key'=>'instagram', 'label'=>'Instagram', 'bg'=>'linear-gradient(135deg,#f09433,#dc2743,#bc1888)', 'icon'=>'IG', 'checked'=>true],
    ['key'=>'facebook',  'label'=>'Facebook',  'bg'=>'#1877F2',  'icon'=>'f',  'checked'=>true],
    ['key'=>'google',    'label'=>'Google',    'bg'=>'#4285F4',  'icon'=>'G',  'checked'=>false],
    ['key'=>'whatsapp',  'label'=>'WhatsApp',  'bg'=>'#25d366',  'icon'=>'WA', 'checked'=>true],
    ['key'=>'blog',      'label'=>'Blog',      'bg'=>'#21759b',  'icon'=>'B',  'checked'=>true],
    ['key'=>'website',   'label'=>'Website',   'bg'=>'#34495e',  'icon'=>'W',  'checked'=>false],
];
@endphp

{{-- ─── SETTINGS FORM CARD ──────────────────────────────────────────────── --}}
<div style="
    background:#ffffff;
    border:1px solid rgba(185,92,183,0.14);
    border-radius:10px;
    overflow:hidden;
">

    {{-- Card Header --}}
    <div style="
        padding:14px 20px 13px;
        border-bottom:1px solid rgba(185,92,183,0.1);
        display:flex; align-items:center; gap:8px;
    ">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="3"/>
            <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>
        </svg>
        <span style="font-family:'Inter',sans-serif; font-size:14px; font-weight:600; color:#1e0a2c;">
            Campaign Settings
        </span>
    </div>

    {{-- Form Body --}}
    <div style="padding:24px 24px 8px;">

        @php
        /* Reusable label style */
        $labelStyle = "font-family:'Inter',sans-serif; font-size:12px; font-weight:600; color:#5a4868; display:block; margin-bottom:5px;";
        $inputStyle = "width:100%; padding:9px 12px; border:1px solid rgba(185,92,183,0.22); border-radius:7px; background:#fff; font-family:'Inter',sans-serif; font-size:13px; color:#1e0a2c; outline:none; box-sizing:border-box;";
        @endphp

        {{-- Row 1: Campaign Name --}}
        <div style="margin-bottom:18px;">
            <label style="{{ $labelStyle }}">Campaign Name</label>
            <input
                type="text"
                value="{{ $campaign['name'] ?? 'Monsoon Smile Campaign 2026' }}"
                style="{{ $inputStyle }}"
                onfocus="this.style.borderColor='#6a0f70'; this.style.boxShadow='0 0 0 3px rgba(106,15,112,0.08)'"
                onblur="this.style.borderColor='rgba(185,92,183,0.22)'; this.style.boxShadow='none'"
            >
        </div>

        {{-- Row 2: Description --}}
        <div style="margin-bottom:18px;">
            <label style="{{ $labelStyle }}">Description</label>
            <textarea
                rows="2"
                style="{{ $inputStyle }} resize:vertical;"
                onfocus="this.style.borderColor='#6a0f70'; this.style.boxShadow='0 0 0 3px rgba(106,15,112,0.08)'"
                onblur="this.style.borderColor='rgba(185,92,183,0.22)'; this.style.boxShadow='none'"
            >{{ $campaign['description'] ?? 'Drive awareness and bookings for dental check-ups and cosmetic treatments during the monsoon season.' }}</textarea>
        </div>

        {{-- Row 3: Objective --}}
        <div style="margin-bottom:18px;">
            <label style="{{ $labelStyle }}">Campaign Objective</label>
            <textarea
                rows="3"
                placeholder="Describe the goal of this campaign — e.g., increase bookings by 30% in July…"
                style="{{ $inputStyle }} resize:vertical;"
                onfocus="this.style.borderColor='#6a0f70'; this.style.boxShadow='0 0 0 3px rgba(106,15,112,0.08)'"
                onblur="this.style.borderColor='rgba(185,92,183,0.22)'; this.style.boxShadow='none'"
            >Generate 100+ qualified leads and convert 25+ into appointments for teeth whitening and scaling during June–July 2026, leveraging Instagram Reels and Facebook posts as primary channels.</textarea>
        </div>

        {{-- Row 4: Treatment Category --}}
        <div style="margin-bottom:18px;">
            <label style="{{ $labelStyle }}">Treatment Category</label>
            <select
                style="{{ $inputStyle }} cursor:pointer;"
                onfocus="this.style.borderColor='#6a0f70'; this.style.boxShadow='0 0 0 3px rgba(106,15,112,0.08)'"
                onblur="this.style.borderColor='rgba(185,92,183,0.22)'; this.style.boxShadow='none'"
            >
                @foreach($categories as $cat)
                <option {{ in_array($cat, ['Teeth Whitening','General Dentistry']) ? 'selected' : '' }}>{{ $cat }}</option>
                @endforeach
            </select>
        </div>

        {{-- Row 5: Start + End Date (side by side) --}}
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:18px;">
            <div>
                <label style="{{ $labelStyle }}">Start Date</label>
                <input
                    type="date"
                    value="{{ $campaign['start_date_raw'] ?? '2026-06-01' }}"
                    style="{{ $inputStyle }}"
                    onfocus="this.style.borderColor='#6a0f70'; this.style.boxShadow='0 0 0 3px rgba(106,15,112,0.08)'"
                    onblur="this.style.borderColor='rgba(185,92,183,0.22)'; this.style.boxShadow='none'"
                >
            </div>
            <div>
                <label style="{{ $labelStyle }}">End Date</label>
                <input
                    type="date"
                    value="{{ $campaign['end_date_raw'] ?? '2026-07-31' }}"
                    style="{{ $inputStyle }}"
                    onfocus="this.style.borderColor='#6a0f70'; this.style.boxShadow='0 0 0 3px rgba(106,15,112,0.08)'"
                    onblur="this.style.borderColor='rgba(185,92,183,0.22)'; this.style.boxShadow='none'"
                >
            </div>
        </div>

        {{-- Row 6: Budget --}}
        <div style="margin-bottom:18px;">
            <label style="{{ $labelStyle }}">Budget (Rs. )</label>
            <div style="position:relative;">
                <span style="
                    position:absolute; left:12px; top:50%; transform:translateY(-50%);
                    font-family:'Inter',sans-serif; font-size:14px; font-weight:500; color:#9b6aad;
                    pointer-events:none;
                ">Rs. </span>
                <input
                    type="number"
                    value="{{ $campaign['budget'] ?? 25000 }}"
                    style="{{ $inputStyle }} padding-left:26px;"
                    onfocus="this.style.borderColor='#6a0f70'; this.style.boxShadow='0 0 0 3px rgba(106,15,112,0.08)'"
                    onblur="this.style.borderColor='rgba(185,92,183,0.22)'; this.style.boxShadow='none'"
                >
            </div>
        </div>

        {{-- Row 7: Target Audience --}}
        <div style="margin-bottom:18px;">
            <label style="{{ $labelStyle }}">Target Audience</label>
            <input
                type="text"
                value="{{ $campaign['audience'] ?? 'Adults 25–55, Pune city, interested in dental care' }}"
                placeholder="e.g., Adults 25–55, families, new patients…"
                style="{{ $inputStyle }}"
                onfocus="this.style.borderColor='#6a0f70'; this.style.boxShadow='0 0 0 3px rgba(106,15,112,0.08)'"
                onblur="this.style.borderColor='rgba(185,92,183,0.22)'; this.style.boxShadow='none'"
            >
        </div>

        {{-- Row 8: Channels (icon checkboxes) --}}
        <div style="margin-bottom:24px;">
            <label style="{{ $labelStyle }}">Channels</label>
            <div style="display:flex; flex-wrap:wrap; gap:10px;">
                @foreach($channels as $ch)
                <label style="
                    display:inline-flex; align-items:center; gap:8px;
                    padding:7px 14px 7px 10px;
                    border:1.5px solid {{ $ch['checked'] ? '#6a0f70' : 'rgba(185,92,183,0.2)' }};
                    border-radius:8px;
                    background:{{ $ch['checked'] ? '#f9f3fa' : '#fff' }};
                    cursor:pointer; user-select:none;
                    transition:border-color 120ms;
                ">
                    {{-- Hidden real checkbox --}}
                    <input type="checkbox" {{ $ch['checked'] ? 'checked' : '' }} style="position:absolute; opacity:0; width:0; height:0;">

                    {{-- Platform bubble --}}
                    <span style="
                        width:22px; height:22px; border-radius:5px;
                        background:{{ $ch['bg'] }};
                        display:inline-flex; align-items:center; justify-content:center;
                        font-size:9px; font-weight:700; color:#fff;
                        flex-shrink:0;
                    ">{{ $ch['icon'] }}</span>

                    <span style="
                        font-family:'Inter',sans-serif; font-size:12.5px;
                        font-weight:{{ $ch['checked'] ? '500' : '400' }};
                        color:{{ $ch['checked'] ? '#1e0a2c' : '#7a6884' }};
                    ">{{ $ch['label'] }}</span>

                    {{-- Checkmark --}}
                    @if($ch['checked'])
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    @endif
                </label>
                @endforeach
            </div>
        </div>

        {{-- ── ACTION BUTTONS ──────────────────────────────────────── --}}
        <div style="
            display:flex; align-items:center; justify-content:space-between;
            padding:16px 0 18px;
            border-top:1px solid rgba(185,92,183,0.1);
        ">
            {{-- Archive (danger link) --}}
            <button style="
                background:transparent; border:none;
                font-family:'Inter',sans-serif; font-size:12.5px; font-weight:500;
                color:#c0392b; cursor:pointer;
                display:inline-flex; align-items:center; gap:5px;
            "
            onmouseover="this.style.textDecoration='underline'"
            onmouseout="this.style.textDecoration='none'"
            >
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/>
                    <line x1="10" y1="12" x2="14" y2="12"/>
                </svg>
                Archive Campaign
            </button>

            {{-- Save Changes --}}
            <button style="
                display:inline-flex; align-items:center; gap:7px;
                padding:9px 22px; border-radius:7px;
                background:linear-gradient(135deg,#6a0f70,#b95cb7); border:none;
                font-family:'Inter',sans-serif; font-size:13px; font-weight:600;
                color:#fff; cursor:pointer;
            "
            onmouseover="this.style.opacity='0.88'"
            onmouseout="this.style.opacity='1'"
            >
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/>
                    <polyline points="7 3 7 8 15 8"/>
                </svg>
                Save Changes
            </button>
        </div>

    </div>{{-- /form body --}}
</div>{{-- /settings card --}}
