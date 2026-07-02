{{--
|==========================================================================
| Campaign Overview Tab — Phase 2.3-B Part B
| File: resources/views/marketing/campaigns/partials/_overview-tab.blade.php
|
| Sections:
|   1. Two-column row: Campaign Progress card | Goals card
|   2. Full-width Content Plan mini-kanban (read-only, 6 cols × 3 cards)
|   3. Side-by-side: Campaign Assets | Campaign Notes
|==========================================================================
--}}

@php
    $prog = $campaign['progress'];

    // Progress bar helper: pct from done/total
    $pct = fn($done, $total) => $total > 0 ? round(($done / $total) * 100) : 0;

    $bars = [
        [
            'label'  => 'Content Planned',
            'done'   => $prog['content_planned']['done'],
            'total'  => $prog['content_planned']['total'],
            'color'  => '#6a0f70',
            'suffix' => 'pieces',
        ],
        [
            'label'  => 'Content Published',
            'done'   => $prog['content_published']['done'],
            'total'  => $prog['content_published']['total'],
            'color'  => '#2ecc71',
            'suffix' => 'pieces',
        ],
        [
            'label'  => 'Budget Utilized',
            'done'   => $prog['budget_utilized']['done'],
            'total'  => $prog['budget_utilized']['total'],
            'color'  => '#f39c12',
            'suffix' => '',
            'prefix' => 'Rs. ',
            'format' => true,
        ],
        [
            'label'  => 'Goals Achieved',
            'done'   => $prog['goals_achieved']['done'],
            'total'  => $prog['goals_achieved']['total'],
            'color'  => '#3498db',
            'suffix' => 'of ' . $prog['goals_achieved']['total'],
        ],
    ];

    // SVG circular ring math (r=44, viewBox 120×120, center 60,60)
    $radius       = 44;
    $circumf      = round(2 * M_PI * $radius, 2); // ≈ 276.46
    $overallPct   = $prog['overall_pct'];
    $dashOffset   = round($circumf * (1 - $overallPct / 100), 2);

    // Goals table
    $goals = $campaign['goals'];

    // Kanban columns with stubbed cards
    $kanban = [
        [
            'label' => 'Idea',       'color' => '#9b6aad', 'bg' => '#f5eef8', 'total' => 6,
            'cards' => [
                ['type' => 'Reel',  'title' => 'Smile journey testimonial',  'platform' => 'instagram'],
                ['type' => 'Post',  'title' => 'Did you know? veneer facts', 'platform' => 'facebook'],
                ['type' => 'Blog',  'title' => '10 myths about teeth whitening', 'platform' => 'wordpress'],
            ],
        ],
        [
            'label' => 'Scripting',  'color' => '#e67e22', 'bg' => '#fef5ec', 'total' => 4,
            'cards' => [
                ['type' => 'Reel',  'title' => '30-sec smile reveal',        'platform' => 'instagram'],
                ['type' => 'Story', 'title' => 'Poll: your smile goal?',     'platform' => 'instagram'],
                ['type' => 'Post',  'title' => 'Meet our smile team',        'platform' => 'facebook'],
            ],
        ],
        [
            'label' => 'Design',     'color' => '#2980b9', 'bg' => '#eaf4fb', 'total' => 5,
            'cards' => [
                ['type' => 'Post',  'title' => 'Before & after carousel',    'platform' => 'facebook'],
                ['type' => 'Story', 'title' => 'June offer countdown',       'platform' => 'instagram'],
                ['type' => 'Post',  'title' => 'Clinic interior showcase',   'platform' => 'wordpress'],
            ],
        ],
        [
            'label' => 'Scheduled',  'color' => '#16a085', 'bg' => '#e8f8f5', 'total' => 3,
            'cards' => [
                ['type' => 'Reel',  'title' => 'Patient transformation',     'platform' => 'instagram'],
                ['type' => 'Ad',    'title' => 'Google search — veneers',    'platform' => 'google'],
                ['type' => 'Blog',  'title' => 'Smile design process',       'platform' => 'wordpress'],
            ],
        ],
        [
            'label' => 'Published',  'color' => '#27ae60', 'bg' => '#e9f7ef', 'total' => 8,
            'cards' => [
                ['type' => 'Reel',  'title' => 'Smile in 30 days',          'platform' => 'instagram'],
                ['type' => 'Post',  'title' => 'Before & after gallery',     'platform' => 'facebook'],
                ['type' => 'Blog',  'title' => 'Veneer myths busted',        'platform' => 'wordpress'],
            ],
        ],
        [
            'label' => 'Archived',   'color' => '#7f8c8d', 'bg' => '#f2f3f4', 'total' => 2,
            'cards' => [
                ['type' => 'Post',  'title' => 'May intro post',             'platform' => 'facebook'],
                ['type' => 'Story', 'title' => 'Launch day teaser',          'platform' => 'instagram'],
                ['type' => 'Ad',    'title' => 'May awareness ad',           'platform' => 'google'],
            ],
        ],
    ];

    // Platform label colors
    $platColors = [
        'instagram' => ['bg' => '#fce4f3', 'text' => '#9b1f6e'],
        'facebook'  => ['bg' => '#e3eefe', 'text' => '#1a56c8'],
        'google'    => ['bg' => '#e8f0fe', 'text' => '#185abc'],
        'wordpress' => ['bg' => '#e2f0f7', 'text' => '#21759b'],
    ];

    // Content type colors
    $typeColors = [
        'Reel'  => ['bg' => '#f5eef8', 'text' => '#6a0f70'],
        'Post'  => ['bg' => '#eaf4fb', 'text' => '#2980b9'],
        'Story' => ['bg' => '#fef9e7', 'text' => '#d4ac0d'],
        'Blog'  => ['bg' => '#e9f7ef', 'text' => '#1e8449'],
        'Ad'    => ['bg' => '#fef5ec', 'text' => '#ca6f1e'],
    ];
@endphp

{{-- ══════════════════════════════════════════════════════════════
     SECTION 1 — TWO-COLUMN ROW: PROGRESS + GOALS
══════════════════════════════════════════════════════════════ --}}
<div style="display:flex; gap:16px; margin-bottom:16px;">

    {{-- ── LEFT: CAMPAIGN PROGRESS CARD ──────────────────── --}}
    <div style="
        flex:1; background:#ffffff;
        border:1px solid rgba(185,92,183,0.14); border-radius:10px;
        padding:22px 22px 20px;
    ">
        <h3 style="
            font-family:'Inter',sans-serif; font-size:13px;
            font-weight:600; color:#1e0a2c; margin:0 0 18px;
            display:flex; align-items:center; gap:7px;
        ">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
            </svg>
            Campaign Progress
        </h3>

        {{-- Circular ring + bars side-by-side --}}
        <div style="display:flex; align-items:center; gap:20px;">

            {{-- SVG circular progress ring --}}
            <div style="flex-shrink:0; text-align:center;">
                <svg width="120" height="120" viewBox="0 0 120 120" style="display:block;">
                    {{-- Track --}}
                    <circle cx="60" cy="60" r="{{ $radius }}"
                        fill="none" stroke="#f0e4f4" stroke-width="10"/>
                    {{-- Progress arc --}}
                    <circle cx="60" cy="60" r="{{ $radius }}"
                        fill="none"
                        stroke="url(#progGrad)"
                        stroke-width="10"
                        stroke-linecap="round"
                        stroke-dasharray="{{ $circumf }}"
                        stroke-dashoffset="{{ $dashOffset }}"
                        transform="rotate(-90 60 60)"
                        style="transition:stroke-dashoffset 0.8s ease;"
                    />
                    <defs>
                        <linearGradient id="progGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" stop-color="#6a0f70"/>
                            <stop offset="100%" stop-color="#b95cb7"/>
                        </linearGradient>
                    </defs>
                    {{-- Centre text --}}
                    <text x="60" y="54" text-anchor="middle"
                        font-family="Inter, sans-serif"
                        font-size="22" font-weight="700" fill="#1e0a2c">
                        {{ $overallPct }}%
                    </text>
                    <text x="60" y="70" text-anchor="middle"
                        font-family="Inter, sans-serif"
                        font-size="10" fill="#9b6aad">
                        Complete
                    </text>
                </svg>
                <div style="
                    font-family:'Inter',sans-serif; font-size:11px;
                    color:#7a6884; margin-top:4px;
                ">{{ $campaign['start_date'] }} – {{ $campaign['end_date'] }}</div>
            </div>

            {{-- Progress bars --}}
            <div style="flex:1; display:flex; flex-direction:column; gap:13px;">
                @foreach($bars as $bar)
                @php
                    $barPct = $pct($bar['done'], $bar['total']);
                    $doneLabel = isset($bar['format'])
                        ? ($bar['prefix'] ?? '') . number_format($bar['done'])
                        : ($bar['prefix'] ?? '') . $bar['done'];
                    $totalLabel = isset($bar['format'])
                        ? ($bar['prefix'] ?? '') . number_format($bar['total'])
                        : ($bar['prefix'] ?? '') . $bar['total'];
                @endphp
                <div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                        <span style="
                            font-family:'Inter',sans-serif; font-size:11.5px;
                            font-weight:500; color:#3d2b4a;
                        ">{{ $bar['label'] }}</span>
                        <span style="
                            font-family:'Inter',sans-serif; font-size:11px; color:#7a6884;
                        ">{{ $doneLabel }} / {{ $totalLabel }}
                            @if($bar['suffix'] && !isset($bar['format']))
                                {{ $bar['suffix'] }}
                            @endif
                        </span>
                    </div>
                    {{-- Track --}}
                    <div style="
                        height:7px; background:#f0e4f4; border-radius:4px; overflow:hidden;
                    ">
                        {{-- Fill --}}
                        <div style="
                            height:100%; width:{{ $barPct }}%;
                            background:{{ $bar['color'] }};
                            border-radius:4px;
                            transition:width 0.6s ease;
                        "></div>
                    </div>
                    <div style="
                        font-family:'Inter',sans-serif; font-size:10px;
                        color:{{ $bar['color'] }}; margin-top:3px; font-weight:600;
                    ">{{ $barPct }}%</div>
                </div>
                @endforeach
            </div>

        </div>{{-- /ring + bars --}}
    </div>{{-- /progress card --}}


    {{-- ── RIGHT: GOALS CARD ───────────────────────────── --}}
    <div style="
        flex:1; background:#ffffff;
        border:1px solid rgba(185,92,183,0.14); border-radius:10px;
        padding:22px 22px 20px;
    ">
        <h3 style="
            font-family:'Inter',sans-serif; font-size:13px;
            font-weight:600; color:#1e0a2c; margin:0 0 16px;
            display:flex; align-items:center; gap:7px;
        ">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>
            </svg>
            Goals vs Actuals
        </h3>

        <table style="width:100%; border-collapse:collapse; font-family:'Inter',sans-serif; font-size:12.5px;">
            <thead>
                <tr>
                    <th style="text-align:left; padding:0 0 10px; color:#9b6aad; font-weight:500; font-size:11px; text-transform:uppercase; letter-spacing:0.04em;">Metric</th>
                    <th style="text-align:right; padding:0 0 10px; color:#9b6aad; font-weight:500; font-size:11px; text-transform:uppercase; letter-spacing:0.04em;">Target</th>
                    <th style="text-align:right; padding:0 0 10px; color:#9b6aad; font-weight:500; font-size:11px; text-transform:uppercase; letter-spacing:0.04em;">Actual</th>
                    <th style="text-align:right; padding:0 0 10px; color:#9b6aad; font-weight:500; font-size:11px; text-transform:uppercase; letter-spacing:0.04em;">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach($goals as $i => $g)
                @php
                    $goalPct = $g['target'] > 0 ? round(($g['actual'] / $g['target']) * 100) : 0;
                    $isLast  = $i === count($goals) - 1;
                    $fmt     = fn($v) => $g['unit_prefix'] ? $g['unit'] . number_format($v) : $g['unit'] . number_format($v);
                @endphp
                <tr style="{{ !$isLast ? 'border-bottom:1px solid rgba(185,92,183,0.08);' : '' }}">
                    <td style="padding:10px 0; color:#1e0a2c; font-weight:500;">{{ $g['label'] }}</td>
                    <td style="padding:10px 0; text-align:right; color:#5a4868;">{{ $fmt($g['target']) }}</td>
                    <td style="padding:10px 0; text-align:right; font-weight:600; color:#1e0a2c;">{{ $fmt($g['actual']) }}</td>
                    <td style="padding:10px 0; text-align:right;">
                        <span style="
                            display:inline-flex; align-items:center; gap:2px;
                            color:{{ $goalPct >= 80 ? '#1a7a3c' : ($goalPct >= 50 ? '#8a5c00' : '#8a1a1a') }};
                            font-weight:600; font-size:12px;
                        ">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="18 15 12 9 6 15"/>
                            </svg>
                            {{ $goalPct }}%
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Quick note --}}
        <div style="
            margin-top:14px; padding:10px 12px;
            background:rgba(46,204,113,0.07); border-radius:6px;
            border-left:3px solid #2ecc71;
            font-family:'Inter',sans-serif; font-size:11.5px; color:#1a7a3c;
        ">
            <strong>4 of 7</strong> campaign goals on track — revenue goal at 55% with 16 days remaining.
        </div>
    </div>{{-- /goals card --}}

</div>{{-- /two-column row --}}


{{-- ══════════════════════════════════════════════════════════════
     SECTION 2 — CONTENT PLAN MINI-KANBAN (read-only)
══════════════════════════════════════════════════════════════ --}}
<div style="
    background:#ffffff;
    border:1px solid rgba(185,92,183,0.14); border-radius:10px;
    padding:20px 22px; margin-bottom:16px;
">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
        <h3 style="
            font-family:'Inter',sans-serif; font-size:13px;
            font-weight:600; color:#1e0a2c; margin:0;
            display:flex; align-items:center; gap:7px;
        ">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="5" height="19" rx="1"/><rect x="10" y="3" width="5" height="13" rx="1"/><rect x="17" y="3" width="5" height="17" rx="1"/>
            </svg>
            Content Plan
        </h3>
        <a href="#" @click.prevent="$dispatch('switch-tab', 'content-plan')" style="
            font-family:'Inter',sans-serif; font-size:12px;
            color:#6a0f70; text-decoration:none; font-weight:500;
        ">View full plan →</a>
    </div>

    {{-- Kanban board --}}
    <div style="display:flex; gap:12px; overflow-x:auto; padding-bottom:4px; scrollbar-width:thin; scrollbar-color:rgba(185,92,183,0.2) transparent;">
        @foreach($kanban as $col)
        @php
            $moreCount = max(0, $col['total'] - count($col['cards']));
        @endphp
        <div style="
            flex:0 0 160px; min-width:160px;
        ">
            {{-- Column header --}}
            <div style="
                display:flex; align-items:center; justify-content:space-between;
                padding:7px 10px; border-radius:6px 6px 0 0;
                background:{{ $col['bg'] }};
                border:1px solid rgba(0,0,0,0.06); border-bottom:none;
                margin-bottom:0;
            ">
                <span style="
                    font-family:'Inter',sans-serif; font-size:11.5px;
                    font-weight:600; color:{{ $col['color'] }};
                ">{{ $col['label'] }}</span>
                <span style="
                    background:{{ $col['color'] }}22; color:{{ $col['color'] }};
                    font-family:'Inter',sans-serif; font-size:10px; font-weight:700;
                    padding:1px 6px; border-radius:10px;
                ">{{ $col['total'] }}</span>
            </div>

            {{-- Cards --}}
            <div style="
                border:1px solid rgba(0,0,0,0.06); border-top:none;
                border-radius:0 0 6px 6px;
                background:#fafafa;
                padding:6px;
                display:flex; flex-direction:column; gap:6px;
            ">
                @foreach($col['cards'] as $card)
                @php
                    $tc = $typeColors[$card['type']] ?? ['bg' => '#f2f2f2', 'text' => '#555'];
                    $pc = $platColors[$card['platform']] ?? ['bg' => '#f2f2f2', 'text' => '#555'];
                @endphp
                <div style="
                    background:#ffffff;
                    border:1px solid rgba(185,92,183,0.1);
                    border-radius:5px; padding:8px 9px;
                ">
                    {{-- Type badge --}}
                    <span style="
                        display:inline-block;
                        background:{{ $tc['bg'] }}; color:{{ $tc['text'] }};
                        font-family:'Inter',sans-serif; font-size:9.5px; font-weight:600;
                        padding:1px 6px; border-radius:8px; margin-bottom:5px;
                    ">{{ $card['type'] }}</span>
                    {{-- Title --}}
                    <p style="
                        font-family:'Inter',sans-serif; font-size:11.5px;
                        color:#1e0a2c; margin:0 0 6px; line-height:1.4;
                        display:-webkit-box; -webkit-line-clamp:2;
                        -webkit-box-orient:vertical; overflow:hidden;
                    ">{{ $card['title'] }}</p>
                    {{-- Platform --}}
                    <span style="
                        display:inline-block;
                        background:{{ $pc['bg'] }}; color:{{ $pc['text'] }};
                        font-family:'Inter',sans-serif; font-size:9px; font-weight:500;
                        padding:1px 6px; border-radius:8px; text-transform:capitalize;
                    ">{{ $card['platform'] }}</span>
                </div>
                @endforeach

                @if($moreCount > 0)
                <div style="
                    text-align:center; padding:5px 0;
                    font-family:'Inter',sans-serif; font-size:11px;
                    color:#9b6aad; font-weight:500; cursor:default;
                ">+{{ $moreCount }} more</div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>{{-- /kanban --}}


{{-- ══════════════════════════════════════════════════════════════
     SECTION 3 — SIDE-BY-SIDE: ASSETS | NOTES
══════════════════════════════════════════════════════════════ --}}
<div style="display:flex; gap:16px;">

    {{-- ── CAMPAIGN ASSETS ──────────────────────────────── --}}
    <div style="
        flex:1; background:#ffffff;
        border:1px solid rgba(185,92,183,0.14); border-radius:10px;
        padding:20px 22px;
    ">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
            <h3 style="
                font-family:'Inter',sans-serif; font-size:13px;
                font-weight:600; color:#1e0a2c; margin:0;
                display:flex; align-items:center; gap:7px;
            ">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                    <circle cx="8.5" cy="8.5" r="1.5"/>
                    <polyline points="21 15 16 10 5 21"/>
                </svg>
                Campaign Assets
            </h3>
            <span style="
                font-family:'Inter',sans-serif; font-size:11px; color:#9b6aad;
            ">12 files</span>
        </div>

        {{-- 5 image thumbs --}}
        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px;">
            @php
                $assetColors = ['#f0e4f4','#e4f0f8','#e4f8ec','#fef5ec','#fce4f3'];
                $assetIcons  = [
                    '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>',
                    '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
                    '<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>',
                    '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
                    '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>',
                ];
                $assetLabels = ['Cover.jpg','Banner.png','Reel-1.mp4','Story.jpg','Blog-hero.png'];
            @endphp
            @foreach($assetColors as $i => $aColor)
            <div title="{{ $assetLabels[$i] }}" style="
                width:62px; height:62px; border-radius:7px;
                background:{{ $aColor }};
                border:1px solid rgba(185,92,183,0.14);
                display:flex; align-items:center; justify-content:center;
                flex-direction:column; gap:4px; cursor:pointer;
                position:relative; overflow:hidden;
            "
            onmouseover="this.style.transform='scale(1.04)';this.style.transition='transform 0.15s';"
            onmouseout="this.style.transform='scale(1)';"
            >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    {!! $assetIcons[$i] !!}
                </svg>
                <span style="
                    font-family:'Inter',sans-serif; font-size:8.5px;
                    color:#6a0f70; font-weight:500; text-align:center;
                    padding:0 3px; word-break:break-all; line-height:1.2;
                ">{{ $assetLabels[$i] }}</span>
            </div>
            @endforeach

            {{-- Add asset button --}}
            <div style="
                width:62px; height:62px; border-radius:7px;
                border:1.5px dashed rgba(185,92,183,0.35);
                display:flex; align-items:center; justify-content:center;
                flex-direction:column; gap:4px; cursor:pointer;
            "
            onmouseover="this.style.background='#f9f3fa';this.style.borderColor='#b95cb7';"
            onmouseout="this.style.background='transparent';this.style.borderColor='rgba(185,92,183,0.35)';"
            >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#b95cb7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                <span style="font-family:'Inter',sans-serif; font-size:8.5px; color:#9b6aad;">Add</span>
            </div>
        </div>

        <a href="#" style="
            font-family:'Inter',sans-serif; font-size:12px;
            color:#6a0f70; text-decoration:none; font-weight:500;
        ">View all 12 assets →</a>
    </div>{{-- /assets --}}


    {{-- ── CAMPAIGN NOTES ───────────────────────────────── --}}
    <div style="
        flex:1; background:#ffffff;
        border:1px solid rgba(185,92,183,0.14); border-radius:10px;
        padding:20px 22px;
    ">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
            <h3 style="
                font-family:'Inter',sans-serif; font-size:13px;
                font-weight:600; color:#1e0a2c; margin:0;
                display:flex; align-items:center; gap:7px;
            ">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                    <polyline points="10 9 9 9 8 9"/>
                </svg>
                Campaign Notes
            </h3>
            <button style="
                font-family:'Inter',sans-serif; font-size:11.5px;
                color:#6a0f70; background:none; border:none; cursor:pointer; font-weight:500; padding:0;
            ">+ Add Note</button>
        </div>

        {{-- Note entries --}}
        @php $notes = [
            ['text' => 'Focus on Instagram Reels for the first 2 weeks — highest engagement in our local area audience.', 'author' => 'Dr. SF', 'time' => '2 days ago'],
            ['text' => 'Google Ads paused briefly 8 Jun due to landing page update. Resumed 10 Jun. Budget not affected.', 'author' => 'Anita S', 'time' => '4 days ago'],
            ['text' => 'Shortlist patients for before/after reel — confirm consent forms signed before filming.', 'author' => 'Rahul K', 'time' => '1 week ago'],
        ]; @endphp

        <div style="display:flex; flex-direction:column; gap:10px;">
            @foreach($notes as $note)
            <div style="
                padding:10px 12px;
                background:#f9f3fa;
                border-radius:7px;
                border-left:3px solid rgba(185,92,183,0.3);
            ">
                <p style="
                    font-family:'Inter',sans-serif; font-size:12px;
                    color:#1e0a2c; margin:0 0 6px; line-height:1.5;
                ">{{ $note['text'] }}</p>
                <div style="display:flex; justify-content:space-between;">
                    <span style="font-family:'Inter',sans-serif; font-size:10.5px; color:#9b6aad; font-weight:500;">{{ $note['author'] }}</span>
                    <span style="font-family:'Inter',sans-serif; font-size:10.5px; color:#b0a0b8;">{{ $note['time'] }}</span>
                </div>
            </div>
            @endforeach
        </div>

        <div style="
            margin-top:12px;
            font-family:'Inter',sans-serif; font-size:10.5px; color:#b0a0b8;
        ">Last updated 2 days ago</div>
    </div>{{-- /notes --}}

</div>{{-- /assets + notes row --}}
