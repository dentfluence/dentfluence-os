@extends('relationship.layouts.app')
@section('page-title', 'Relationship Analytics')

@section('head-extra')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
@endsection

@section('relationship-content')

{{-- ─────────────────────────────────────────────────────────────────────
     One-screen layout: this page intentionally overrides the shared
     content padding and tightens every card so the whole dashboard fits
     in a normal laptop viewport without scrolling. The Staff KPI table is
     the one place that can grow unbounded (more staff over time), so it
     keeps its own small internal scroll instead of growing the page.
──────────────────────────────────────────────────────────────────────── --}}
<style>
    #df-content-inner { padding: 10px 24px 6px !important; }
    .an-row { display: grid; gap: 12px; margin-bottom: 12px; }
    .an-card { background: #fff; border: 1px solid rgba(185,92,183,0.12); border-radius: 3px; }
    .an-card-header {
        padding: 8px 14px; border-bottom: 1px solid rgba(185,92,183,0.08);
        display: flex; align-items: center; justify-content: space-between; background: #faf5fb;
    }
    .an-card-header .t { font-size: 12px; font-weight: 600; color: #1a0a24; }
    .an-card-header .s { font-size: 10.5px; color: #9e8fa0; }
    .an-kpi { padding: 12px 14px; }
    .an-kpi .label { font-size: 10px; font-weight: 600; color: #9e8fa0; letter-spacing: 0.05em; text-transform: uppercase; margin-bottom: 4px; }
    .an-kpi .value { font-size: 22px; font-weight: 700; font-family: 'Cormorant Garamond', serif; line-height: 1.1; }
    .an-kpi .meta { font-size: 10.5px; color: #9e8fa0; margin-top: 3px; }
    .an-clickable { transition: box-shadow .15s ease, transform .15s ease; text-decoration: none; color: inherit; display: flex; }
    .an-clickable:hover { box-shadow: 0 6px 18px rgba(106,15,112,0.14); transform: translateY(-1px); }
</style>

{{-- Header --}}
<div class="df-page-header" style="margin-bottom:10px;">
    <div>
        <h1 class="df-page-title" style="font-size:20px;">Relationship Analytics</h1>
        <p class="df-page-subtitle" style="margin-top:1px;">Pipeline health · Last 30 days · Cached hourly</p>
    </div>
    <p style="font-size:10.5px;color:#b0a4bc;margin:0;">
        Refreshed {{ now()->format('d M, h:i A') }} —
        <a href="{{ route('relationship.analytics') }}?bust={{ time() }}" style="color:#6a0f70;text-decoration:none;"
           onclick="event.preventDefault(); fetch('/relationship/analytics?bust=' + Date.now()); location.reload();">Refresh</a>
    </p>
</div>

{{-- Row 1: Summary KPI cards --}}
<div class="an-row" style="grid-template-columns:repeat(4,1fr);">
    <div class="an-card an-kpi">
        <p class="label">Total Relationships</p>
        <p class="value" style="color:#1e0a2c;">{{ number_format($totalRelations) }}</p>
        <p class="meta">All-time</p>
    </div>
    <div class="an-card an-kpi">
        <p class="label">Lead Conversion</p>
        <p class="value" style="color:#6a0f70;">{{ $conversion['rate'] }}%</p>
        <p class="meta">{{ number_format($conversion['converted']) }} of {{ number_format($conversion['total']) }} leads</p>
    </div>
    <div class="an-card an-kpi">
        <p class="label">Recall Success</p>
        <p class="value" style="color:{{ $recallSuccess['rate'] >= 50 ? '#1a7a45' : ($recallSuccess['rate'] >= 30 ? '#a05c00' : '#b52020') }};">{{ $recallSuccess['rate'] }}%</p>
        <p class="meta">{{ number_format($recallSuccess['successful']) }} of {{ number_format($recallSuccess['total']) }} recalls</p>
    </div>
    <div class="an-card an-kpi">
        <p class="label">Avg Lifetime Value</p>
        <p class="value" style="color:#1a7a45;">Rs. {{ number_format($avgLifetimeValue['avg']) }}</p>
        <p class="meta">across {{ number_format($avgLifetimeValue['patient_count']) }} patients</p>
    </div>
</div>

{{-- Row 2: Relationship Growth chart + Score Distribution --}}
<div class="an-row" style="grid-template-columns:2fr 1fr;">
    <div class="an-card">
        <div class="an-card-header">
            <span class="t">Relationship Growth</span>
            <span class="s">New relationships / month, last 6 months</span>
        </div>
        <div style="padding:8px 12px;height:120px;">
            <canvas id="growthChart"></canvas>
        </div>
    </div>

    <div class="an-card">
        <div class="an-card-header"><span class="t">Score Distribution</span></div>
        <div style="padding:10px 14px;">
            @php $scoreTotal = collect($scoreDistrib)->sum('count') ?: 1; @endphp
            @foreach($scoreDistrib as $band)
            <div style="margin-bottom:8px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:3px;">
                    <span style="font-size:11.5px;font-weight:500;color:#1a0a24;">{{ $band['label'] }}</span>
                    <span style="font-size:11.5px;font-weight:600;color:{{ $band['color'] }};">{{ number_format($band['count']) }} · {{ round($band['count'] / $scoreTotal * 100) }}%</span>
                </div>
                <div style="height:5px;background:#f0e8f8;border-radius:3px;overflow:hidden;">
                    <div style="height:100%;width:{{ round($band['count'] / $scoreTotal * 100) }}%;background:{{ $band['color'] }};border-radius:3px;"></div>
                </div>
            </div>
            @endforeach

            @if(collect($scoreDistrib)->sum('count') === 0)
            <p style="font-size:12px;color:#b0a4bc;text-align:center;padding:8px 0;">No scored relationships yet.</p>
            @endif
        </div>
    </div>
</div>

{{-- Row 3: Conversion + Recall — quick-glimpse rings, click through for detail --}}
@php
    $r  = 24; $circ = round(2 * pi() * $r);
    $convOffset   = round($circ * (1 - $conversion['rate'] / 100));
    $recallColor  = $recallSuccess['rate'] >= 50 ? '#1a7a45' : ($recallSuccess['rate'] >= 30 ? '#a05c00' : '#b52020');
    $recallOffset = round($circ * (1 - $recallSuccess['rate'] / 100));
@endphp
<div class="an-row" style="grid-template-columns:1fr 1fr;">
    <a href="{{ route('relationship.pipeline') }}" class="an-card an-clickable" style="padding:10px 14px;align-items:center;gap:12px;">
        <div style="position:relative;width:56px;height:56px;flex-shrink:0;">
            <svg width="56" height="56" viewBox="0 0 56 56" style="transform:rotate(-90deg);">
                <circle cx="28" cy="28" r="{{ $r }}" fill="none" stroke="#f0e8f8" stroke-width="6"/>
                <circle cx="28" cy="28" r="{{ $r }}" fill="none" stroke="#6a0f70" stroke-width="6" stroke-linecap="round"
                        stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $convOffset }}"/>
            </svg>
            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:12.5px;font-weight:700;color:#6a0f70;">{{ $conversion['rate'] }}%</div>
        </div>
        <div style="flex:1;min-width:0;">
            <p style="font-size:12.5px;font-weight:600;color:#1a0a24;margin:0 0 2px;">Lead Conversion Rate</p>
            <p style="font-size:11px;color:#9e8fa0;margin:0;">{{ number_format($conversion['converted']) }} of {{ number_format($conversion['total']) }} converted</p>
        </div>
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#c9b8d0" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M9 6l6 6-6 6"/></svg>
    </a>

    <a href="{{ route('relationship.recalls') }}" class="an-card an-clickable" style="padding:10px 14px;align-items:center;gap:12px;">
        <div style="position:relative;width:56px;height:56px;flex-shrink:0;">
            <svg width="56" height="56" viewBox="0 0 56 56" style="transform:rotate(-90deg);">
                <circle cx="28" cy="28" r="{{ $r }}" fill="none" stroke="#f8eaea" stroke-width="6"/>
                <circle cx="28" cy="28" r="{{ $r }}" fill="none" stroke="{{ $recallColor }}" stroke-width="6" stroke-linecap="round"
                        stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $recallOffset }}"/>
            </svg>
            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:12.5px;font-weight:700;color:{{ $recallColor }};">{{ $recallSuccess['rate'] }}%</div>
        </div>
        <div style="flex:1;min-width:0;">
            <p style="font-size:12.5px;font-weight:600;color:#1a0a24;margin:0 0 2px;">Recall Success Rate</p>
            <p style="font-size:11px;color:#9e8fa0;margin:0;">{{ number_format($recallSuccess['successful']) }} of {{ number_format($recallSuccess['total']) }} booked</p>
        </div>
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#c9b8d0" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M9 6l6 6-6 6"/></svg>
    </a>
</div>

{{-- Row 4: Staff KPIs — internal scroll so this never grows the page --}}
<div class="an-card">
    <div class="an-card-header">
        <span class="t">Staff KPIs</span>
        <span class="s">Last 30 days</span>
    </div>
    @if(count($staffKpis) > 0)
    <div style="max-height:190px;overflow-y:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
                <tr style="background:#faf5fb;position:sticky;top:0;">
                    <th style="padding:6px 14px;text-align:left;font-size:10px;font-weight:600;color:#9e8fa0;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #f0e8f8;">Staff</th>
                    <th style="padding:6px 12px;text-align:left;font-size:10px;font-weight:600;color:#9e8fa0;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #f0e8f8;">Role</th>
                    <th style="padding:6px 12px;text-align:right;font-size:10px;font-weight:600;color:#9e8fa0;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #f0e8f8;">Calls</th>
                    <th style="padding:6px 12px;text-align:right;font-size:10px;font-weight:600;color:#9e8fa0;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #f0e8f8;">Tasks</th>
                    <th style="padding:6px 14px;text-align:right;font-size:10px;font-weight:600;color:#9e8fa0;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #f0e8f8;">Converted</th>
                </tr>
            </thead>
            <tbody>
                @foreach($staffKpis as $row)
                <tr style="border-bottom:1px solid #f8f2fb;">
                    <td style="padding:6px 14px;font-weight:500;color:#1a0a24;">{{ $row['name'] }}</td>
                    <td style="padding:6px 12px;">
                        <span style="display:inline-block;padding:1px 7px;font-size:10px;font-weight:500;background:#f5eef9;color:#6a0f70;border:1px solid rgba(185,92,183,0.18);border-radius:2px;">
                            {{ ucwords(str_replace('_', ' ', $row['role'])) }}
                        </span>
                    </td>
                    <td style="padding:6px 12px;text-align:right;font-weight:600;color:#1a5ea8;">{{ number_format($row['calls_logged']) }}</td>
                    <td style="padding:6px 12px;text-align:right;font-weight:600;color:#1a7a45;">{{ number_format($row['tasks_done']) }}</td>
                    <td style="padding:6px 14px;text-align:right;font-weight:600;color:#6a0f70;">{{ number_format($row['leads_converted']) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div style="padding:20px;text-align:center;">
        <p style="font-size:12px;color:#b0a4bc;">No staff activity recorded in the last 30 days.</p>
    </div>
    @endif
</div>

@endsection

@push('scripts')
<script>
(function () {
    // ── Growth chart ──────────────────────────────────────────────────────
    var growthData = @json($growth);
    new Chart(document.getElementById('growthChart'), {
        type: 'bar',
        data: {
            labels:   growthData.map(function (d) { return d.month; }),
            datasets: [{
                label:           'New Relationships',
                data:            growthData.map(function (d) { return d.count; }),
                backgroundColor: 'rgba(106,15,112,0.75)',
                borderRadius:    2,
                hoverBackgroundColor: '#6a0f70',
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 10 } } },
                y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } } },
            },
        },
    });
    // Conversion + Recall rate rings are plain server-rendered SVG (see Row 3
    // above) — no Chart.js doughnut, which fixes the earlier sizing bug where
    // those charts rendered oversized with no maintainAspectRatio set.
})();
</script>
@endpush
