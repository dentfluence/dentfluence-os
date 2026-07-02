{{--
|==========================================================================
| Publish Panel — Panel 3 (right ~20%)
| File: resources/views/marketing/publish/partials/_publish-panel.blade.php
|
| Sections:
|   1. AI Assistant Beta — Content Score donut + checklist + Improve button
|   2. Publishing Summary — stats row
|   3. Schedule — radio options + date/time picker
|   4. Publish All button
|==========================================================================
--}}

{{-- Alpine sub-scope for schedule mode --}}
<div
    x-data="{ scheduleMode: 'schedule' }"
    style="padding: 20px 18px; display: flex; flex-direction: column; gap: 0;"
>

    {{-- ── SECTION LABEL ─────────────────────────────────────────────── --}}
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:20px;">
        <span style="
            display:inline-flex;align-items:center;justify-content:center;
            width:20px;height:20px;background:#6a0f70;border-radius:50%;
            font-family:'Inter',sans-serif;font-size:10px;font-weight:700;color:#fff;flex-shrink:0;
        ">③</span>
        <span style="font-family:'Inter',sans-serif;font-size:13px;font-weight:600;color:#1e0a2c;">
            Publish Settings
        </span>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         1. AI ASSISTANT BETA
    ══════════════════════════════════════════════════════════════════ --}}
    <div style="
        background: linear-gradient(135deg, #faf3fb 0%, #f3e8ff 100%);
        border: 1px solid rgba(106,15,112,0.14);
        border-radius: 10px;
        padding: 16px;
        margin-bottom: 16px;
    ">
        {{-- Header --}}
        <div style="display:flex;align-items:center;gap:6px;margin-bottom:14px;">
            <span style="font-size:13px;">✨</span>
            <span style="font-family:'Inter',sans-serif;font-size:12px;font-weight:700;color:#6a0f70;letter-spacing:0.04em;text-transform:uppercase;">
                AI Assistant
            </span>
            <span style="
                font-family:'Inter',sans-serif;font-size:9px;font-weight:600;
                color:#ffffff;background:#9b3da0;border-radius:20px;padding:1px 7px;
                letter-spacing:0.05em;
            ">BETA</span>
        </div>

        {{-- Donut gauge + checklist row --}}
        <div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:14px;">

            {{-- Donut SVG — score 92 --}}
            <div style="flex-shrink:0;position:relative;width:72px;height:72px;">
                <svg viewBox="0 0 72 72" width="72" height="72" style="transform:rotate(-90deg);">
                    {{-- Track --}}
                    <circle cx="36" cy="36" r="28"
                        fill="none"
                        stroke="rgba(106,15,112,0.10)"
                        stroke-width="8"
                    />
                    {{-- Progress: 92% of circumference = 2π×28 ≈ 175.93; 92% = 161.86 --}}
                    <circle cx="36" cy="36" r="28"
                        fill="none"
                        stroke="url(#scoreGrad)"
                        stroke-width="8"
                        stroke-linecap="round"
                        stroke-dasharray="161.86 175.93"
                    />
                    <defs>
                        <linearGradient id="scoreGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" stop-color="#6a0f70"/>
                            <stop offset="100%" stop-color="#c76dd2"/>
                        </linearGradient>
                    </defs>
                </svg>
                {{-- Score label centred over SVG --}}
                <div style="
                    position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
                    text-align:center;line-height:1.1;
                ">
                    <div style="font-family:'Inter',sans-serif;font-size:18px;font-weight:700;color:#6a0f70;">92</div>
                    <div style="font-family:'Inter',sans-serif;font-size:8.5px;font-weight:500;color:#9b3da0;">Excellent</div>
                </div>
            </div>

            {{-- Checklist --}}
            <div style="flex:1;display:flex;flex-direction:column;gap:5px;padding-top:2px;">
                @foreach([
                    'Engaging headline',
                    'Strong message',
                    'Clear CTA',
                    'Optimal length',
                    'Hashtags included',
                ] as $check)
                <div style="display:flex;align-items:center;gap:6px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <span style="font-family:'Inter',sans-serif;font-size:11px;color:#1e0a2c;">{{ $check }}</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Improve with AI button --}}
        <button type="button" style="
            display:inline-flex;align-items:center;justify-content:center;gap:6px;
            width:100%;height:34px;
            font-family:'Inter',sans-serif;font-size:12px;font-weight:600;
            color:#6a0f70;
            background:transparent;
            border:1.5px solid #6a0f70;
            border-radius:6px;
            cursor:pointer;
            transition:background 150ms;
        "
        onmouseover="this.style.background='rgba(106,15,112,0.07)'"
        onmouseout="this.style.background='transparent'"
        >
            <span>✨</span> Improve with AI
        </button>
    </div>
    {{-- /AI ASSISTANT --}}

    {{-- ── DIVIDER ─────────────────────────────────────────────────────── --}}
    <div style="height:1px;background:rgba(185,92,183,0.12);margin-bottom:16px;"></div>

    {{-- ══════════════════════════════════════════════════════════════════
         2. PUBLISHING SUMMARY
    ══════════════════════════════════════════════════════════════════ --}}
    <div style="margin-bottom:16px;">
        <div style="font-family:'Inter',sans-serif;font-size:11.5px;font-weight:700;color:#1e0a2c;letter-spacing:0.03em;text-transform:uppercase;margin-bottom:10px;">
            Publishing Summary
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
            @foreach([
                ['label'=>'Platforms',         'value'=>'6'],
                ['label'=>'Total variations',   'value'=>'6'],
                ['label'=>'Estimated reach',    'value'=>'12.4K'],
                ['label'=>'Best time to post',  'value'=>'Today, 7:00 PM'],
            ] as $stat)
            <div style="
                background:#f9f4fb;
                border:1px solid rgba(185,92,183,0.12);
                border-radius:7px;
                padding:9px 10px;
            ">
                <div style="font-family:'Inter',sans-serif;font-size:10px;font-weight:500;color:#7a6884;margin-bottom:2px;">
                    {{ $stat['label'] }}
                </div>
                <div style="font-family:'Inter',sans-serif;font-size:14px;font-weight:700;color:#6a0f70;">
                    {{ $stat['value'] }}
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ── DIVIDER ─────────────────────────────────────────────────────── --}}
    <div style="height:1px;background:rgba(185,92,183,0.12);margin-bottom:16px;"></div>

    {{-- ══════════════════════════════════════════════════════════════════
         3. SCHEDULE
    ══════════════════════════════════════════════════════════════════ --}}
    <div style="margin-bottom:18px;">
        <div style="font-family:'Inter',sans-serif;font-size:11.5px;font-weight:700;color:#1e0a2c;letter-spacing:0.03em;text-transform:uppercase;margin-bottom:10px;">
            Schedule
        </div>

        {{-- Radio options --}}
        <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:12px;">
            @foreach([
                ['key'=>'now',    'label'=>'Publish Now'],
                ['key'=>'schedule','label'=>'Schedule'],
                ['key'=>'queue',  'label'=>'Add to Queue'],
                ['key'=>'draft',  'label'=>'Save as Draft'],
            ] as $opt)
            <label style="
                display:flex;align-items:center;gap:8px;
                cursor:pointer;padding:7px 10px;
                border-radius:6px;
                border:1.5px solid transparent;
                transition:border-color 150ms, background 150ms;
            "
            :style="scheduleMode === '{{ $opt['key'] }}'
                ? 'border-color:rgba(106,15,112,0.30);background:rgba(106,15,112,0.05);'
                : 'border-color:rgba(185,92,183,0.12);background:transparent;'"
            >
                <input
                    type="radio"
                    name="scheduleMode"
                    value="{{ $opt['key'] }}"
                    x-model="scheduleMode"
                    style="accent-color:#6a0f70;width:14px;height:14px;flex-shrink:0;"
                >
                <span style="font-family:'Inter',sans-serif;font-size:12.5px;color:#1e0a2c;">
                    {{ $opt['label'] }}
                </span>
            </label>
            @endforeach
        </div>

        {{-- Date + Time pickers (only when 'schedule' selected) --}}
        <div x-show="scheduleMode === 'schedule'" x-transition style="display:flex;flex-direction:column;gap:8px;">
            {{-- Date --}}
            <div>
                <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;display:block;margin-bottom:4px;">
                    Date
                </label>
                <input type="date" value="2026-06-18" style="
                    width:100%;box-sizing:border-box;
                    padding:8px 10px;
                    border:1.5px solid rgba(185,92,183,0.22);
                    border-radius:6px;
                    font-family:'Inter',sans-serif;font-size:12.5px;color:#1e0a2c;
                    background:#faf3fb;outline:none;
                ">
            </div>
            {{-- Time --}}
            <div>
                <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;display:block;margin-bottom:4px;">
                    Time
                </label>
                <input type="time" value="19:00" style="
                    width:100%;box-sizing:border-box;
                    padding:8px 10px;
                    border:1.5px solid rgba(185,92,183,0.22);
                    border-radius:6px;
                    font-family:'Inter',sans-serif;font-size:12.5px;color:#1e0a2c;
                    background:#faf3fb;outline:none;
                ">
            </div>
            {{-- Timezone --}}
            <div style="
                display:flex;align-items:center;gap:5px;
                font-family:'Inter',sans-serif;font-size:10.5px;color:#7a6884;
            ">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
                (GMT+05:30) Asia/Kolkata
            </div>
        </div>
    </div>
    {{-- /SCHEDULE --}}

    {{-- ══════════════════════════════════════════════════════════════════
         4. PUBLISH ALL BUTTON
    ══════════════════════════════════════════════════════════════════ --}}
    <div style="position:relative;">
        <button type="button" style="
            display:inline-flex;align-items:center;justify-content:center;gap:8px;
            width:100%;height:42px;
            font-family:'Inter',sans-serif;font-size:13px;font-weight:700;
            color:#ffffff;
            background:linear-gradient(135deg,#6a0f70 0%,#9b3da0 100%);
            border:none;border-radius:8px;
            cursor:pointer;
            box-shadow:0 3px 10px rgba(106,15,112,0.30);
            transition:opacity 150ms,box-shadow 150ms;
            padding-right:42px;  /* room for the caret pill */
        "
        onmouseover="this.style.opacity='0.9';this.style.boxShadow='0 5px 16px rgba(106,15,112,0.40)'"
        onmouseout="this.style.opacity='1';this.style.boxShadow='0 3px 10px rgba(106,15,112,0.30)'"
        >
           
            <span x-text="scheduleMode === 'now' ? 'Publish All' : scheduleMode === 'queue' ? 'Add All to Queue' : scheduleMode === 'draft' ? 'Save All as Draft' : 'Schedule / Publish All'">
                Schedule / Publish All
            </span>
        </button>

        {{-- Dropdown caret pill --}}
        <button type="button" style="
            position:absolute;right:0;top:0;height:42px;width:38px;
            display:inline-flex;align-items:center;justify-content:center;
            background:rgba(255,255,255,0.18);
            border:none;border-left:1px solid rgba(255,255,255,0.20);
            border-radius:0 8px 8px 0;
            cursor:pointer;
            color:#fff;
        ">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"/>
            </svg>
        </button>
    </div>
    {{-- /PUBLISH ALL BUTTON --}}

</div>
{{-- /publish panel x-data --}}
