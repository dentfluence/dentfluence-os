{{--
|==========================================================================
| Campaign Right Sidebar — Phase 2.3-B Part B
| File: resources/views/marketing/campaigns/partials/_sidebar.blade.php
|
| Cards:
|   1. Campaign Performance  — 6 metric rows with mini sparklines
|   2. Top Performing Content — 3 content rows with type badge
|   3. Team Members           — 4 members with avatar, name, role + Manage
|==========================================================================
--}}

@php
    $perf     = $campaign['performance'];
    $topContent = $campaign['top_content'];
    $team     = $campaign['team'];

    // Mini sparkline paths (pre-computed, 7 points, width=60 height=22)
    // Each is a rough upward trend to match "+% up" metrics
    $sparklines = [
        'M0,18 L10,16 L20,14 L30,10 L40,8 L50,5 L60,4',   // Reach
        'M0,20 L10,17 L20,15 L30,11 L40,7 L50,6 L60,3',   // Impressions
        'M0,19 L10,18 L20,15 L30,13 L40,10 L50,8 L60,6',  // Engagement
        'M0,20 L10,16 L20,14 L30,9 L40,6 L50,5 L60,3',    // Leads
        'M0,20 L10,17 L20,15 L30,11 L40,8 L50,7 L60,4',   // Appointments
        'M0,20 L10,18 L20,16 L30,12 L40,9 L50,6 L60,4',   // Revenue
    ];

    $platColors = [
        'instagram' => ['bg' => 'linear-gradient(135deg,#f09433 0%,#dc2743 50%,#bc1888 100%)', 'label' => 'IG'],
        'facebook'  => ['bg' => '#1877F2',  'label' => 'f'],
        'google'    => ['bg' => '#4285F4',  'label' => 'G'],
        'wordpress' => ['bg' => '#21759b',  'label' => 'W'],
    ];

    $roleColors = [
        'Owner'       => ['bg' => '#f5eef8', 'text' => '#6a0f70'],
        'Content Lead'=> ['bg' => '#eaf4fb', 'text' => '#2980b9'],
        'Designer'    => ['bg' => '#e9f7ef', 'text' => '#1e8449'],
        'Ads Manager' => ['bg' => '#fef5ec', 'text' => '#ca6f1e'],
    ];
@endphp

{{-- ── CARD 1: CAMPAIGN PERFORMANCE ───────────────────────── --}}
<div style="
    background:#ffffff;
    border:1px solid rgba(185,92,183,0.14); border-radius:10px;
    padding:18px 18px 16px; margin-bottom:14px;
">
    {{-- Header --}}
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
        <h3 style="
            font-family:'Inter',sans-serif; font-size:12.5px;
            font-weight:600; color:#1e0a2c; margin:0;
            display:flex; align-items:center; gap:6px;
        ">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="20" x2="18" y2="10"/>
                <line x1="12" y1="20" x2="12" y2="4"/>
                <line x1="6"  y1="20" x2="6"  y2="14"/>
            </svg>
            Performance
        </h3>
        {{-- Toggle: vs last 30 days --}}
        <span style="
            font-family:'Inter',sans-serif; font-size:10.5px;
            color:#9b6aad; background:#f9f3fa;
            border:1px solid rgba(185,92,183,0.2);
            padding:2px 8px; border-radius:10px; cursor:pointer; white-space:nowrap;
        ">vs last 30d</span>
    </div>

    {{-- Metric rows --}}
    <div style="display:flex; flex-direction:column; gap:0;">
        @foreach($perf as $i => $row)
        @php $isLast = $i === count($perf) - 1; @endphp
        <div style="
            display:flex; align-items:center; justify-content:space-between;
            padding:9px 0;
            {{ !$isLast ? 'border-bottom:1px solid rgba(185,92,183,0.07);' : '' }}
        ">
            {{-- Metric name --}}
            <div style="
                font-family:'Inter',sans-serif; font-size:12px;
                color:#5a4868; min-width:80px;
            ">{{ $row['metric'] }}</div>

            {{-- Mini sparkline --}}
            <svg width="60" height="22" viewBox="0 0 60 22" style="flex-shrink:0;">
                {{-- Gradient fill area (just the line for now) --}}
                <polyline
                    points="{{ str_replace('M', '', str_replace(' L', ' ', $sparklines[$i])) }}"
                    fill="none"
                    stroke="{{ $row['up'] ? '#2ecc71' : '#e74c3c' }}"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>

            {{-- Value + change --}}
            <div style="text-align:right; min-width:60px;">
                <div style="
                    font-family:'Inter',sans-serif; font-size:13px;
                    font-weight:600; color:#1e0a2c;
                ">{{ $row['value'] }}</div>
                <div style="
                    display:inline-flex; align-items:center; gap:2px;
                    font-family:'Inter',sans-serif; font-size:10.5px; font-weight:600;
                    color:{{ $row['up'] ? '#1a7a3c' : '#c0392b' }};
                ">
                    <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        @if($row['up'])
                            <polyline points="18 15 12 9 6 15"/>
                        @else
                            <polyline points="6 9 12 15 18 9"/>
                        @endif
                    </svg>
                    {{ $row['change'] }}
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>{{-- /performance card --}}


{{-- ── CARD 2: TOP PERFORMING CONTENT ─────────────────────── --}}
<div style="
    background:#ffffff;
    border:1px solid rgba(185,92,183,0.14); border-radius:10px;
    padding:18px 18px 16px; margin-bottom:14px;
">
    <h3 style="
        font-family:'Inter',sans-serif; font-size:12.5px;
        font-weight:600; color:#1e0a2c; margin:0 0 14px;
        display:flex; align-items:center; gap:6px;
    ">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
        </svg>
        Top Performing Content
    </h3>

    <div style="display:flex; flex-direction:column; gap:0;">
        @foreach($topContent as $i => $item)
        @php
            $isLast = $i === count($topContent) - 1;
            $pc = $platColors[$item['platform']] ?? ['bg' => '#888', 'label' => '?'];
            $typeColor = ['Reel'=>'#6a0f70','Post'=>'#2980b9','Blog'=>'#1e8449'][$item['type']] ?? '#555';
            $typeBg    = ['Reel'=>'#f5eef8','Post'=>'#eaf4fb','Blog'=>'#e9f7ef'][$item['type']] ?? '#f2f2f2';
        @endphp
        <div style="
            display:flex; align-items:center; gap:10px;
            padding:10px 0;
            {{ !$isLast ? 'border-bottom:1px solid rgba(185,92,183,0.07);' : '' }}
        ">
            {{-- Thumbnail placeholder --}}
            <div style="
                width:40px; height:40px; border-radius:6px; flex-shrink:0;
                background:{{ $typeBg }};
                border:1px solid rgba(185,92,183,0.12);
                display:flex; align-items:center; justify-content:center;
            ">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="{{ $typeColor }}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    @if($item['type'] === 'Reel')
                        <polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
                    @elseif($item['type'] === 'Post')
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                    @else
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                    @endif
                </svg>
            </div>

            {{-- Info --}}
            <div style="flex:1; min-width:0;">
                <div style="display:flex; align-items:center; gap:5px; margin-bottom:3px;">
                    <span style="
                        background:{{ $typeBg }}; color:{{ $typeColor }};
                        font-family:'Inter',sans-serif; font-size:9.5px; font-weight:600;
                        padding:1px 6px; border-radius:8px;
                    ">{{ $item['type'] }}</span>
                    {{-- Platform circle --}}
                    <span style="
                        width:16px; height:16px; border-radius:50%;
                        background:{{ $pc['bg'] }};
                        display:inline-flex; align-items:center; justify-content:center;
                        font-size:8px; font-weight:700; color:#fff;
                        flex-shrink:0;
                    ">{{ $pc['label'] }}</span>
                </div>
                <div style="
                    font-family:'Inter',sans-serif; font-size:11.5px;
                    font-weight:500; color:#1e0a2c;
                    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
                ">{{ $item['title'] }}</div>
            </div>

            {{-- Engagement --}}
            <div style="
                text-align:right; flex-shrink:0;
                font-family:'Inter',sans-serif;
            ">
                <div style="font-size:12px; font-weight:600; color:#1e0a2c;">{{ $item['engagement'] }}</div>
                <div style="font-size:10px; color:#9b6aad;">eng.</div>
            </div>
        </div>
        @endforeach
    </div>
</div>{{-- /top content --}}


{{-- ── CARD 3: TEAM MEMBERS ─────────────────────────────────── --}}
<div style="
    background:#ffffff;
    border:1px solid rgba(185,92,183,0.14); border-radius:10px;
    padding:18px 18px 16px;
">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
        <h3 style="
            font-family:'Inter',sans-serif; font-size:12.5px;
            font-weight:600; color:#1e0a2c; margin:0;
            display:flex; align-items:center; gap:6px;
        ">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
            </svg>
            Team Members
        </h3>
        <a href="#" style="
            font-family:'Inter',sans-serif; font-size:11px;
            color:#6a0f70; text-decoration:none; font-weight:500;
        ">Manage</a>
    </div>

    <div style="display:flex; flex-direction:column; gap:0;">
        @foreach($team as $i => $member)
        @php
            $isLast = $i === count($team) - 1;
            $rc = $roleColors[$member['role']] ?? ['bg' => '#f2f2f2', 'text' => '#555'];
        @endphp
        <div style="
            display:flex; align-items:center; gap:10px;
            padding:9px 0;
            {{ !$isLast ? 'border-bottom:1px solid rgba(185,92,183,0.07);' : '' }}
        ">
            {{-- Avatar --}}
            <div style="
                width:30px; height:30px; border-radius:50%; flex-shrink:0;
                background:linear-gradient(135deg,#6a0f70 0%,#b95cb7 100%);
                display:flex; align-items:center; justify-content:center;
                font-family:'Inter',sans-serif; font-size:10px; font-weight:700; color:#fff;
            ">{{ $member['initials'] }}</div>

            {{-- Name + role --}}
            <div style="flex:1; min-width:0;">
                <div style="
                    font-family:'Inter',sans-serif; font-size:12px;
                    font-weight:500; color:#1e0a2c;
                    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
                ">{{ $member['name'] }}</div>
                <span style="
                    display:inline-block;
                    background:{{ $rc['bg'] }}; color:{{ $rc['text'] }};
                    font-family:'Inter',sans-serif; font-size:9.5px; font-weight:600;
                    padding:1px 6px; border-radius:8px; margin-top:2px;
                ">{{ $member['role'] }}</span>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Add member --}}
    <button style="
        display:flex; align-items:center; gap:6px; margin-top:12px;
        background:none; border:1.5px dashed rgba(185,92,183,0.3);
        border-radius:6px; padding:7px 12px; cursor:pointer; width:100%;
        font-family:'Inter',sans-serif; font-size:12px; color:#9b6aad;
        transition:border-color 150ms, background 150ms;
    "
    onmouseover="this.style.background='#f9f3fa';this.style.borderColor='#b95cb7';"
    onmouseout="this.style.background='none';this.style.borderColor='rgba(185,92,183,0.3)';"
    >
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
            <circle cx="8.5" cy="7" r="4"/>
            <line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>
        </svg>
        Add team member
    </button>
</div>{{-- /team --}}
