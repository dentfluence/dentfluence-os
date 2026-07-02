{{--
|==========================================================================
| Partial: _stat-row
| File: resources/views/marketing/overview/partials/_stat-row.blade.php
|
| Renders:
|   Left  — Marketing Score card with SVG donut gauge (compact)
|   Right — 5 icon-free stat cards in a row (no icons = shorter height,
|            no horizontal overflow risk)
|
| Variables expected ($stats array):
|   score, published, published_trend, scheduled, scheduled_trend,
|   missed, missed_trend, drafts, pending_approval, pending_trend
|==========================================================================
--}}

@php
    // ── Gauge maths ──────────────────────────────────────────────────
    // SVG donut: viewBox 160×160, r=60, circumference = 2π×60 ≈ 376.99
    $gaugeRadius  = 60;
    $gaugeCircumf = 2 * M_PI * $gaugeRadius;             // 376.99
    $gaugeDash    = ($stats['score'] / 100) * $gaugeCircumf; // filled arc
    $score        = $stats['score'];

    // Motivational text
    if ($score >= 80)     { $scoreLabel = 'Excellent work!'; }
    elseif ($score >= 60) { $scoreLabel = 'Good progress! Keep going'; }
    elseif ($score >= 40) { $scoreLabel = 'Room to grow — keep posting!'; }
    else                  { $scoreLabel = 'Let\'s build momentum!'; }

    // Score band badge
    if ($score >= 80)     { $bandColor = '#16a34a'; $bandBg = 'rgba(22,163,74,0.10)';   $bandLabel = 'Excellent'; }
    elseif ($score >= 60) { $bandColor = '#6a0f70'; $bandBg = 'rgba(106,15,112,0.08)';  $bandLabel = 'Good'; }
    elseif ($score >= 40) { $bandColor = '#d97706'; $bandBg = 'rgba(217,119,6,0.10)';   $bandLabel = 'Fair'; }
    else                  { $bandColor = '#dc2626'; $bandBg = 'rgba(220,38,38,0.08)';   $bandLabel = 'Weak'; }
@endphp

{{-- ══════════════════════════════════════════════════════════════
     OUTER ROW: Gauge card (fixed width left) + 5 stat cards (right)
     min-width:0 on grid prevents content blowing past flex container
══════════════════════════════════════════════════════════════ --}}
<div style="
    display: flex;
    gap: 16px;
    align-items: stretch;
">

    {{-- ─────────────────────────────────────────────────────────────
         MARKETING SCORE CARD — compact gauge
    ───────────────────────────────────────────────────────────── --}}
    <div style="
        width: 210px;
        flex-shrink: 0;
        background: #ffffff;
        border: 1px solid rgba(185,92,183,0.15);
        border-radius: 10px;
        box-shadow: 0 1px 4px rgba(106,15,112,0.06);
        padding: 16px 14px 14px;
        display: flex;
        flex-direction: column;
        align-items: center;
        font-family: 'Inter', sans-serif;
    ">
        {{-- Title row --}}
        <div style="
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        ">
            <span style="font-size: 12.5px; font-weight: 600; color: #1e0a2c;">Marketing Score</span>
            <span style="
                padding: 1px 7px;
                border-radius: 20px;
                background: {{ $bandBg }};
                font-size: 10.5px;
                font-weight: 700;
                color: {{ $bandColor }};
                letter-spacing: 0.02em;
            ">{{ $bandLabel }}</span>
        </div>

        {{-- SVG Donut Gauge — 120×120 rendered, viewBox 160×160 --}}
        <svg viewBox="0 0 160 160" width="120" height="120" style="display:block; overflow:visible;">
            <defs>
                <linearGradient id="mktScoreGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#6a0f70"/>
                    <stop offset="100%" stop-color="#b95cb7"/>
                </linearGradient>
            </defs>
            {{-- Track --}}
            <circle cx="80" cy="80" r="{{ $gaugeRadius }}"
                fill="none" stroke="rgba(185,92,183,0.12)" stroke-width="14"/>
            {{-- Filled arc --}}
            <circle cx="80" cy="80" r="{{ $gaugeRadius }}"
                fill="none"
                stroke="url(#mktScoreGrad)"
                stroke-width="14"
                stroke-linecap="round"
                stroke-dasharray="{{ number_format($gaugeDash, 2) }} {{ number_format($gaugeCircumf, 2) }}"
                transform="rotate(-90 80 80)"/>
            {{-- Score --}}
            <text x="80" y="72" text-anchor="middle"
                font-family="Inter, sans-serif" font-size="38" font-weight="700" fill="#1e0a2c">{{ $score }}</text>
            <text x="80" y="93" text-anchor="middle"
                font-family="Inter, sans-serif" font-size="12" fill="#9b6aad">/100</text>
        </svg>

        {{-- Motivational line --}}
        <p style="
            font-size: 12px;
            font-weight: 500;
            color: #1e0a2c;
            text-align: center;
            margin: 10px 0 8px;
            line-height: 1.4;
        ">{{ $scoreLabel }}</p>

        {{-- Progress bar + caption --}}
        <div style="width: 100%;">
            <div style="height: 5px; border-radius: 4px; background: rgba(185,92,183,0.12); overflow: hidden;">
                <div style="
                    height: 100%; width: {{ $score }}%; border-radius: 4px;
                    background: linear-gradient(90deg, #6a0f70 0%, #b95cb7 100%);
                "></div>
            </div>
            <p style="font-size: 10.5px; color: #9b6aad; text-align: center; margin: 5px 0 0; line-height: 1.3;">
                Based on publish rate, consistency &amp; reach
            </p>
        </div>
    </div>{{-- /gauge card --}}


    {{-- ─────────────────────────────────────────────────────────────
         5 STAT CARDS — no icons, compact height, flex:1 + min-width:0
         prevents horizontal overflow of the outer flex row
    ───────────────────────────────────────────────────────────── --}}
    <div style="
        flex: 1;
        min-width: 0;
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 12px;
        align-content: stretch;
    ">

        {{-- 1. Published ── green, trend up --}}
        <x-marketing.stat-card
            label="Published"
            value="{{ $stats['published'] }}"
            trend="{{ $stats['published_trend'] }}"
            trend_direction="up"
            color="#16a34a"
        />

        {{-- 2. Scheduled ── purple, trend up --}}
        <x-marketing.stat-card
            label="Scheduled"
            value="{{ $stats['scheduled'] }}"
            trend="{{ $stats['scheduled_trend'] }}"
            trend_direction="up"
            color="#7c3aed"
        />

        {{-- 3. Missed ── red, trend down (fewer missed = good, but we track raw number) --}}
        <x-marketing.stat-card
            label="Missed"
            value="{{ $stats['missed'] }}"
            trend="{{ $stats['missed_trend'] }}"
            trend_direction="down"
            color="#dc2626"
        />

        {{-- 4. Drafts ── slate, no trend --}}
        <x-marketing.stat-card
            label="Drafts"
            value="{{ $stats['drafts'] }}"
            color="#64748b"
        />

        {{-- 5. Pending Approval ── amber, trend down --}}
        <x-marketing.stat-card
            label="Pending Approval"
            value="{{ $stats['pending_approval'] }}"
            trend="{{ $stats['pending_trend'] }}"
            trend_direction="down"
            color="#d97706"
        />

    </div>{{-- /stat grid --}}

</div>{{-- /outer row --}}
