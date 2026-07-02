{{--
|==========================================================================
| Campaign Show — Performance Tab  (Phase 2.3-C)
| File: resources/views/marketing/campaigns/partials/_performance-tab.blade.php
|
| 6 metric cards + integration placeholder banner + 2 chart placeholder boxes.
| Mock data only — no live analytics.
|==========================================================================
--}}

@php
$metrics = [
    ['label'=>'Total Reach',         'value'=>'48,230',  'change'=>'+12.4%', 'up'=>true,  'icon'=>'reach'],
    ['label'=>'Impressions',         'value'=>'1,24,500','change'=>'+8.1%',  'up'=>true,  'icon'=>'eye'],
    ['label'=>'Clicks / Taps',       'value'=>'3,870',   'change'=>'+5.3%',  'up'=>true,  'icon'=>'cursor'],
    ['label'=>'Leads Generated',     'value'=>'102',     'change'=>'-2.0%',  'up'=>false, 'icon'=>'users'],
    ['label'=>'Appointments Booked', 'value'=>'28',      'change'=>'+18.2%', 'up'=>true,  'icon'=>'calendar'],
    ['label'=>'Cost Per Lead',       'value'=>'Rs. 58',     'change'=>'-6.7%',  'up'=>true,  'icon'=>'rupee'],
];
@endphp

{{-- ─── 6 METRIC CARDS ──────────────────────────────────────────────────── --}}
<div style="
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:14px;
    margin-bottom:20px;
">
@foreach($metrics as $m)
<div style="
    background:#ffffff;
    border:1px solid rgba(185,92,183,0.14);
    border-radius:10px;
    padding:16px 18px 14px;
">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:10px;">
        <span style="
            font-family:'Inter',sans-serif; font-size:12px; font-weight:400; color:#9b6aad;
        ">{{ $m['label'] }}</span>
        {{-- Change badge --}}
        <span style="
            background:{{ $m['up'] ? '#e8f5e9' : '#fce4ec' }};
            color:{{ $m['up'] ? '#1b5e20' : '#b71c1c' }};
            font-family:'Inter',sans-serif; font-size:10.5px; font-weight:600;
            padding:2px 7px; border-radius:10px;
        ">{{ $m['change'] }}</span>
    </div>

    <div style="
        font-family:'Cormorant Garamond',serif;
        font-size:28px; font-weight:700;
        color:#1e0a2c; line-height:1; margin-bottom:12px;
    ">{{ $m['value'] }}</div>

    {{-- Stubbed mini chart bar --}}
    <div style="display:flex; align-items:flex-end; gap:3px; height:30px; opacity:0.35;">
        @php $bars = [55,40,70,50,80,65,90,75,85,100,72,88]; @endphp
        @foreach($bars as $h)
        <div style="
            flex:1; height:{{ $h }}%;
            background:{{ $m['up'] ? 'linear-gradient(180deg,#6a0f70,#b95cb7)' : 'linear-gradient(180deg,#e74c3c,#f1948a)' }};
            border-radius:2px 2px 0 0;
        "></div>
        @endforeach
    </div>
    <p style="
        font-family:'Inter',sans-serif; font-size:10px;
        color:#c9b0d4; margin:4px 0 0; text-align:right;
    ">Last 30 days</p>
</div>
@endforeach
</div>

{{-- ─── INTEGRATION PLACEHOLDER BANNER ─────────────────────────────────── --}}
<div style="
    background:linear-gradient(135deg,#faf5ff 0%,#f0eeff 100%);
    border:1.5px dashed rgba(185,92,183,0.35);
    border-radius:10px;
    padding:20px 24px;
    display:flex; align-items:center; gap:18px;
    margin-bottom:20px;
">
    <div style="
        width:48px; height:48px; border-radius:10px;
        background:linear-gradient(135deg,#6a0f70,#b95cb7);
        display:flex; align-items:center; justify-content:center;
        flex-shrink:0;
    ">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
        </svg>
    </div>
    <div style="flex:1;">
        <p style="
            font-family:'Inter',sans-serif; font-size:14px; font-weight:600;
            color:#1e0a2c; margin:0 0 4px;
        ">Connect platforms to see real analytics</p>
        <p style="
            font-family:'Inter',sans-serif; font-size:12.5px; font-weight:300;
            color:#7a6884; margin:0;
        ">
            Numbers above are estimated. Connect Instagram, Facebook, Google Ads, and WhatsApp
            in <strong style="color:#6a0f70;">Integrations</strong> to pull live reach, impressions, and engagement data.
        </p>
    </div>
    <button style="
        padding:8px 18px; border-radius:7px;
        background:linear-gradient(135deg,#6a0f70,#b95cb7); border:none;
        font-family:'Inter',sans-serif; font-size:12.5px; font-weight:500;
        color:#fff; cursor:pointer; white-space:nowrap; flex-shrink:0;
    "
    onmouseover="this.style.opacity='0.88'"
    onmouseout="this.style.opacity='1'"
    >Go to Integrations</button>
</div>

{{-- ─── CHART PLACEHOLDER BOXES ─────────────────────────────────────────── --}}
<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">

    {{-- BAR CHART placeholder --}}
    <div style="
        background:#ffffff;
        border:1px solid rgba(185,92,183,0.14);
        border-radius:10px;
        overflow:hidden;
    ">
        <div style="padding:14px 16px 12px; border-bottom:1px solid rgba(185,92,183,0.08);">
            <p style="font-family:'Inter',sans-serif; font-size:13px; font-weight:600; color:#1e0a2c; margin:0 0 2px;">
                Channel Performance
            </p>
            <p style="font-family:'Inter',sans-serif; font-size:11.5px; font-weight:300; color:#9b6aad; margin:0;">
                Leads per channel — bar chart
            </p>
        </div>
        <div style="
            padding:24px 20px 20px;
            display:flex; flex-direction:column; align-items:center; justify-content:center;
            min-height:180px;
        ">
            {{-- Placeholder bar chart outline --}}
            <div style="
                display:flex; align-items:flex-end; gap:10px;
                width:100%; justify-content:center; opacity:0.18;
            ">
                @foreach([60,90,45,110,75,130,55] as $bh)
                <div style="
                    width:28px; background:#6a0f70;
                    height:{{ $bh }}px; border-radius:4px 4px 0 0;
                "></div>
                @endforeach
            </div>
            <div style="
                width:100%; height:2px; background:rgba(185,92,183,0.2);
                margin-bottom:16px; opacity:0.5;
            "></div>
            <div style="
                display:flex; align-items:center; gap:8px;
                background:#f9f3fa; border-radius:8px;
                padding:8px 14px;
            ">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#b95cb7" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span style="font-family:'Inter',sans-serif; font-size:12px; color:#6a0f70; font-weight:500;">
                    Coming soon — connect integrations
                </span>
            </div>
        </div>
    </div>

    {{-- LINE CHART placeholder --}}
    <div style="
        background:#ffffff;
        border:1px solid rgba(185,92,183,0.14);
        border-radius:10px;
        overflow:hidden;
    ">
        <div style="padding:14px 16px 12px; border-bottom:1px solid rgba(185,92,183,0.08);">
            <p style="font-family:'Inter',sans-serif; font-size:13px; font-weight:600; color:#1e0a2c; margin:0 0 2px;">
                Lead Trend Over Time
            </p>
            <p style="font-family:'Inter',sans-serif; font-size:11.5px; font-weight:300; color:#9b6aad; margin:0;">
                Daily lead count — line chart
            </p>
        </div>
        <div style="
            padding:24px 20px 20px;
            display:flex; flex-direction:column; align-items:center; justify-content:center;
            min-height:180px;
        ">
            {{-- Placeholder wavy line --}}
            <div style="width:100%; opacity:0.15; margin-bottom:12px;">
                <svg viewBox="0 0 260 100" xmlns="http://www.w3.org/2000/svg" style="width:100%; height:auto;">
                    <polyline
                        points="0,80 30,60 60,70 90,40 120,55 150,25 180,45 210,20 240,35 260,15"
                        fill="none" stroke="#6a0f70" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"
                    />
                    <polyline
                        points="0,80 30,60 60,70 90,40 120,55 150,25 180,45 210,20 240,35 260,15 260,100 0,100"
                        fill="#b95cb7" opacity="0.2"
                    />
                </svg>
            </div>
            <div style="
                display:flex; align-items:center; gap:8px;
                background:#f9f3fa; border-radius:8px;
                padding:8px 14px;
            ">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#b95cb7" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span style="font-family:'Inter',sans-serif; font-size:12px; color:#6a0f70; font-weight:500;">
                    Coming soon — connect integrations
                </span>
            </div>
        </div>
    </div>

</div>
