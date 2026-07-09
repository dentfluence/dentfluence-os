{{--
| Marketing — Analytics & ROI
| File: resources/views/marketing/analytics/index.blade.php
|
| Re-metriced per docs/marketing-module-reengineering-plan.md (V2b):
| ROI (revenue/leads/appointments/spend) leads the page instead of posting
| volume — dentists care about what marketing produced, not how many posts
| went out. Posting-volume detail is kept (nothing removed), just demoted
| to a smaller secondary row. Note: this file was previously truncated
| mid-markup (cut off inside an SVG tag with no closing sections) and the
| campaign ROI table / trend / platform / insights panels — despite the
| controller already computing all of them — were never actually rendered.
| This rewrite completes it using the existing controller data untouched.
--}}
@extends('marketing.layouts.app')

@php $marketingPageTitle = 'Analytics'; @endphp

@section('page-title', 'Marketing — Analytics & ROI')

@section('marketing-content')

{{-- ── Page Header ──────────────────────────────────────────────────── --}}
<div class="df-page-header" style="margin-bottom:24px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
    <div>
        <h1 class="df-page-title" style="display:inline-flex; align-items:center; gap:10px;">
            Analytics &amp; ROI
            <span style="
                display:inline-flex; align-items:center;
                background: linear-gradient(135deg, #7a1fa2, #6a0f70);
                color:#fff; border-radius:20px;
                font-family:'Inter',sans-serif; font-size:11px; font-weight:700;
                letter-spacing:.5px; padding:3px 10px; vertical-align:middle;
            ">LIVE</span>
        </h1>
        <p class="df-page-subtitle">What marketing produced — leads, appointments, revenue, and ROI.</p>
    </div>

    {{-- Marketing Score pill --}}
    <div style="
        display:inline-flex; align-items:center; gap:10px;
        background:#fff; border:1px solid #e9d5f5; border-radius:12px; padding:10px 18px;
    ">
        <div style="position:relative; width:36px; height:36px;">
            <svg width="36" height="36" viewBox="0 0 36 36" style="transform:rotate(-90deg);">
                <circle cx="18" cy="18" r="15.9155" fill="transparent" stroke="#f3f4f6" stroke-width="3.5"/>
                <circle cx="18" cy="18" r="15.9155" fill="transparent" stroke="#7a1fa2" stroke-width="3.5"
                    stroke-dasharray="{{ $scoreData['total'] }} {{ 100 - $scoreData['total'] }}"
                    stroke-dashoffset="0" stroke-linecap="round"/>
            </svg>
            <span style="
                position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
                font-family:'Inter',sans-serif; font-size:9px; font-weight:700; color:#7a1fa2;
            ">{{ $scoreData['total'] }}</span>
        </div>
        <div>
            <div style="font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:#1e0a2c;">Marketing Score</div>
            <div style="font-family:'Inter',sans-serif; font-size:10px; color:#9ca3af;">out of 100</div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     PRIMARY — ROI (leads the page, per re-engineering plan)
═══════════════════════════════════════════════════════════════ --}}
@php
    // V4: contextual upgrade hints instead of a banner — see
    // docs/marketing-module-reengineering-plan.md V4 / "Upgrade Strategy".
    // Revenue/Appointments here still come from manually-entered Campaign
    // Goals either way; the hint just names what connecting Dentfluence OS
    // would remove, next to the exact field it affects.
    $integrated = \App\Support\Features\Feature::enabled('marketing.integrated_providers');

    $roiCards = [
        ['label' => 'Total Revenue',     'value' => 'Rs. ' . number_format($roiTotals['total_revenue'], 0),                                   'sub' => 'from campaign goals', 'color' => '#16a34a', 'bg' => '#f0fdf4', 'hint' => 'This can be calculated automatically using Dentfluence OS.'],
        ['label' => 'Overall ROI',       'value' => $roiTotals['overall_roi'] !== null ? $roiTotals['overall_roi'] . '%' : '—',                'sub' => 'revenue vs. spend',   'color' => '#7a1fa2', 'bg' => '#fdf4ff'],
        ['label' => 'Total Leads',       'value' => number_format($roiTotals['total_leads']),                                                  'sub' => 'from all campaigns',  'color' => '#ea580c', 'bg' => '#fff7ed'],
        ['label' => 'Total Appointments','value' => number_format($roiTotals['total_appointments']),                                           'sub' => 'booked from campaigns','color' => '#2563eb', 'bg' => '#eff6ff', 'hint' => 'This becomes automatic once appointments are connected.'],
        ['label' => 'Marketing Spend',   'value' => 'Rs. ' . number_format($roiTotals['total_spent'], 0),                                      'sub' => 'total utilised',      'color' => '#dc2626', 'bg' => '#fef2f2'],
        ['label' => 'Cost per Lead',     'value' => $roiTotals['cost_per_lead'] !== null ? 'Rs. ' . number_format($roiTotals['cost_per_lead']) : '—', 'sub' => 'average',      'color' => '#9333ea', 'bg' => '#faf5ff'],
    ];
@endphp
<div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; margin-bottom:20px;">
    @foreach ($roiCards as $card)
    <div style="background:#fff; border:1px solid #f0eaf5; border-radius:12px; padding:20px;">
        <span style="font-family:'Inter',sans-serif; font-size:12px; font-weight:500; color:#9ca3af;">{{ $card['label'] }}</span>
        <div style="font-family:'Inter',sans-serif; font-size:26px; font-weight:700; color:#1e0a2c; margin:8px 0 4px;">{{ $card['value'] }}</div>
        <div style="font-family:'Inter',sans-serif; font-size:11px; color:#9ca3af;">{{ $card['sub'] }}</div>
        @if (!$integrated && !empty($card['hint']))
            <div style="font-family:'Inter',sans-serif; font-size:10.5px; color:#9333ea; margin-top:6px;">{{ $card['hint'] }}</div>
        @endif
    </div>
    @endforeach
</div>

{{-- ══════════════════════════════════════════════════════════════
     SECONDARY — Content activity (posting volume; demoted, not removed)
═══════════════════════════════════════════════════════════════ --}}
<div style="background:#fff; border:1px solid #f0eaf5; border-radius:12px; padding:14px 20px; margin-bottom:24px; display:flex; align-items:center; gap:32px; flex-wrap:wrap;">
    <span style="font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.5px;">Content Activity</span>
    <div style="display:flex; align-items:baseline; gap:6px;">
        <span style="font-family:'Inter',sans-serif; font-size:18px; font-weight:700; color:#1e0a2c;">{{ $kpi['published'] }}</span>
        <span style="font-family:'Inter',sans-serif; font-size:12px; color:#9ca3af;">published this month</span>
        <span style="font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:{{ $kpi['trend_positive'] ? '#16a34a' : '#dc2626' }};">{{ $kpi['published_trend'] }}</span>
    </div>
    <div style="display:flex; align-items:baseline; gap:6px;">
        <span style="font-family:'Inter',sans-serif; font-size:18px; font-weight:700; color:#1e0a2c;">{{ $kpi['scheduled'] }}</span>
        <span style="font-family:'Inter',sans-serif; font-size:12px; color:#9ca3af;">scheduled</span>
    </div>
    <div style="display:flex; align-items:baseline; gap:6px;">
        <span style="font-family:'Inter',sans-serif; font-size:18px; font-weight:700; color:#1e0a2c;">{{ $kpi['active_campaigns'] }}</span>
        <span style="font-family:'Inter',sans-serif; font-size:12px; color:#9ca3af;">active campaigns</span>
    </div>
    <div style="display:flex; align-items:baseline; gap:6px;">
        <span style="font-family:'Inter',sans-serif; font-size:18px; font-weight:700; color:#1e0a2c;">{{ $kpi['completion_rate'] }}%</span>
        <span style="font-family:'Inter',sans-serif; font-size:12px; color:#9ca3af;">completion rate</span>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     CAMPAIGN ROI TABLE
═══════════════════════════════════════════════════════════════ --}}
<div style="background:#fff; border:1px solid #f0eaf5; border-radius:12px; padding:20px; margin-bottom:24px;">
    <h2 style="font-family:'Inter',sans-serif; font-size:14px; font-weight:600; color:#1e0a2c; margin:0 0 14px;">Campaign ROI</h2>

    @if (empty($campaignRoi))
        <p style="font-family:'Inter',sans-serif; font-size:13px; color:#7a6884; margin:0;">No active or completed campaigns yet.</p>
    @else
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-family:'Inter',sans-serif; font-size:12.5px;">
                <thead>
                    <tr>
                        <th style="text-align:left; padding:8px 10px; font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.4px; border-bottom:2px solid #f0eaf5;">Campaign</th>
                        <th style="text-align:right; padding:8px 10px; font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.4px; border-bottom:2px solid #f0eaf5;">Spend</th>
                        <th style="text-align:right; padding:8px 10px; font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.4px; border-bottom:2px solid #f0eaf5;">Leads</th>
                        <th style="text-align:right; padding:8px 10px; font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.4px; border-bottom:2px solid #f0eaf5;">Appointments</th>
                        <th style="text-align:right; padding:8px 10px; font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.4px; border-bottom:2px solid #f0eaf5;">Revenue</th>
                        <th style="text-align:right; padding:8px 10px; font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.4px; border-bottom:2px solid #f0eaf5;">ROI</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($campaignRoi as $c)
                    <tr>
                        <td style="padding:10px; border-bottom:1px solid #f5f0f8;">
                            <div style="font-weight:500; color:#1e0a2c;">{{ $c['name'] }}</div>
                            <div style="font-size:11px; color:#9ca3af; text-transform:capitalize;">{{ $c['status'] }}</div>
                        </td>
                        <td style="text-align:right; padding:10px; border-bottom:1px solid #f5f0f8; color:#1e0a2c;">Rs. {{ number_format($c['budget_spent']) }}</td>
                        <td style="text-align:right; padding:10px; border-bottom:1px solid #f5f0f8; color:#1e0a2c;">{{ $c['leads_actual'] }}</td>
                        <td style="text-align:right; padding:10px; border-bottom:1px solid #f5f0f8; color:#1e0a2c;">{{ $c['appts_actual'] }}</td>
                        <td style="text-align:right; padding:10px; border-bottom:1px solid #f5f0f8; color:#1e0a2c;">Rs. {{ number_format($c['revenue_actual']) }}</td>
                        <td style="text-align:right; padding:10px; border-bottom:1px solid #f5f0f8; font-weight:600; color:{{ $c['roi_pct'] === null ? '#9ca3af' : ($c['roi_pct'] >= 0 ? '#16a34a' : '#dc2626') }};">
                            {{ $c['roi_pct'] !== null ? $c['roi_pct'] . '%' : '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- ══════════════════════════════════════════════════════════════
     SECONDARY — Monthly trend | Platform breakdown (posting detail)
═══════════════════════════════════════════════════════════════ --}}
<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px;">

    <div style="background:#fff; border:1px solid #f0eaf5; border-radius:12px; padding:20px;">
        <h2 style="font-family:'Inter',sans-serif; font-size:13px; font-weight:600; color:#1e0a2c; margin:0 0 14px;">Posts Published — Last 6 Months</h2>
        <div style="display:flex; align-items:flex-end; gap:10px; height:80px;">
            @foreach ($monthlyTrend as $m)
            <div style="flex:1; display:flex; flex-direction:column; align-items:center; gap:6px;">
                <div style="width:100%; max-width:28px; height:{{ $m['pct'] }}%; background:#e9d5f5; border-radius:4px 4px 0 0;" title="{{ $m['count'] }} posts"></div>
                <span style="font-family:'Inter',sans-serif; font-size:10px; color:#9ca3af;">{{ $m['month'] }}</span>
            </div>
            @endforeach
        </div>
    </div>

    <div style="background:#fff; border:1px solid #f0eaf5; border-radius:12px; padding:20px;">
        <h2 style="font-family:'Inter',sans-serif; font-size:13px; font-weight:600; color:#1e0a2c; margin:0 0 14px;">Platform Breakdown</h2>
        @if (empty($platformBreakdown))
            <p style="font-family:'Inter',sans-serif; font-size:12px; color:#9ca3af; margin:0;">No published posts yet.</p>
        @else
            <div style="display:flex; flex-direction:column; gap:10px;">
                @foreach ($platformBreakdown as $p)
                <div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <span style="font-family:'Inter',sans-serif; font-size:12px; color:#1e0a2c;">{{ $p['label'] }}</span>
                        <span style="font-family:'Inter',sans-serif; font-size:12px; color:#9ca3af;">{{ $p['count'] }} ({{ $p['pct'] }}%)</span>
                    </div>
                    <div style="background:#f5f0f8; border-radius:6px; height:6px; overflow:hidden;">
                        <div style="width:{{ $p['pct'] }}%; height:100%; background:{{ $p['color'] }};"></div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

</div>

{{-- ══════════════════════════════════════════════════════════════
     INSIGHTS · RECENT ACTIVITY
═══════════════════════════════════════════════════════════════ --}}
<div style="display:grid; grid-template-columns:1.3fr 1fr; gap:20px;">

    <div style="background:#fff; border:1px solid #f0eaf5; border-radius:12px; padding:20px;">
        <h2 style="font-family:'Inter',sans-serif; font-size:13px; font-weight:600; color:#1e0a2c; margin:0 0 14px;">Insights</h2>
        @if (empty($insights))
            <p style="font-family:'Inter',sans-serif; font-size:12px; color:#9ca3af; margin:0;">Nothing to flag right now.</p>
        @else
            <div style="display:flex; flex-direction:column; gap:10px;">
                @foreach ($insights as $insight)
                @php
                    $tone = match ($insight['type']) {
                        'success'     => ['bg' => '#f0fdf4', 'color' => '#16a34a'],
                        'warning'     => ['bg' => '#fef2f2', 'color' => '#dc2626'],
                        'opportunity' => ['bg' => '#fdf4ff', 'color' => '#7a1fa2'],
                        default       => ['bg' => '#eff6ff', 'color' => '#2563eb'],
                    };
                @endphp
                <div style="background:{{ $tone['bg'] }}; border-radius:8px; padding:12px 14px;">
                    <p style="font-family:'Inter',sans-serif; font-size:12.5px; font-weight:600; color:{{ $tone['color'] }}; margin:0 0 3px;">{{ $insight['title'] }}</p>
                    <p style="font-family:'Inter',sans-serif; font-size:12px; color:#5a4868; margin:0;">{{ $insight['body'] }}</p>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    <div style="background:#fff; border:1px solid #f0eaf5; border-radius:12px; padding:20px;">
        <h2 style="font-family:'Inter',sans-serif; font-size:13px; font-weight:600; color:#1e0a2c; margin:0 0 14px;">Recent Activity</h2>
        @if (empty($recentActivity))
            <p style="font-family:'Inter',sans-serif; font-size:12px; color:#9ca3af; margin:0;">No activity logged yet.</p>
        @else
            <div style="display:flex; flex-direction:column; gap:0;">
                @foreach ($recentActivity as $i => $log)
                <div style="padding:8px 0; {{ $i < count($recentActivity) - 1 ? 'border-bottom:1px solid #f5f0f8;' : '' }}">
                    <p style="font-family:'Inter',sans-serif; font-size:12px; color:#1e0a2c; margin:0;">{{ $log['description'] }}</p>
                    <p style="font-family:'Inter',sans-serif; font-size:11px; color:#9ca3af; margin:2px 0 0;">{{ $log['user'] }} · {{ $log['time'] }}</p>
                </div>
                @endforeach
            </div>
        @endif
    </div>

</div>

@endsection
