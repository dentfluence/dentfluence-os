@extends('relationship.layouts.app')
@section('page-title', 'Relationship Analytics')

@section('head-extra')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
@endsection

@section('relationship-content')

{{-- ─────────────────────────────────────────────────────────────────────
     Page header
──────────────────────────────────────────────────────────────────────── --}}
<div class="df-page-header">
    <div>
        <h1 class="df-page-title">Relationship Analytics</h1>
        <p class="df-page-subtitle">
            Pipeline health · Last 30 days · Cached hourly
        </p>
    </div>
</div>

{{-- ─────────────────────────────────────────────────────────────────────
     Row 1: Summary KPI cards
──────────────────────────────────────────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;">

    {{-- Total Relationships --}}
    <div class="df-card" style="padding:20px;">
        <p style="font-size:11px;font-weight:600;color:#9e8fa0;letter-spacing:0.06em;text-transform:uppercase;margin-bottom:8px;">Total Relationships</p>
        <p style="font-size:28px;font-weight:700;color:#1e0a2c;font-family:'Cormorant Garamond',serif;">{{ number_format($totalRelations) }}</p>
        <p style="font-size:11.5px;color:#9e8fa0;margin-top:4px;">All-time</p>
    </div>

    {{-- Lead Conversion Rate --}}
    <div class="df-card" style="padding:20px;">
        <p style="font-size:11px;font-weight:600;color:#9e8fa0;letter-spacing:0.06em;text-transform:uppercase;margin-bottom:8px;">Lead Conversion</p>
        <p style="font-size:28px;font-weight:700;color:#6a0f70;font-family:'Cormorant Garamond',serif;">{{ $conversion['rate'] }}%</p>
        <p style="font-size:11.5px;color:#9e8fa0;margin-top:4px;">{{ number_format($conversion['converted']) }} of {{ number_format($conversion['total']) }} leads</p>
    </div>

    {{-- Recall Success Rate --}}
    <div class="df-card" style="padding:20px;">
        <p style="font-size:11px;font-weight:600;color:#9e8fa0;letter-spacing:0.06em;text-transform:uppercase;margin-bottom:8px;">Recall Success</p>
        <p style="font-size:28px;font-weight:700;color:{{ $recallSuccess['rate'] >= 50 ? '#1a7a45' : ($recallSuccess['rate'] >= 30 ? '#a05c00' : '#b52020') }};font-family:'Cormorant Garamond',serif;">{{ $recallSuccess['rate'] }}%</p>
        <p style="font-size:11.5px;color:#9e8fa0;margin-top:4px;">{{ number_format($recallSuccess['successful']) }} of {{ number_format($recallSuccess['total']) }} recalls</p>
    </div>

    {{-- Avg Lifetime Value --}}
    <div class="df-card" style="padding:20px;">
        <p style="font-size:11px;font-weight:600;color:#9e8fa0;letter-spacing:0.06em;text-transform:uppercase;margin-bottom:8px;">Avg Lifetime Value</p>
        <p style="font-size:28px;font-weight:700;color:#1a7a45;font-family:'Cormorant Garamond',serif;">Rs. {{ number_format($avgLifetimeValue['avg']) }}</p>
        <p style="font-size:11.5px;color:#9e8fa0;margin-top:4px;">across {{ number_format($avgLifetimeValue['patient_count']) }} patients</p>
    </div>

</div>

{{-- ─────────────────────────────────────────────────────────────────────
     Row 2: Relationship Growth chart + Score Distribution
──────────────────────────────────────────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:24px;">

    {{-- Relationship Growth — bar chart --}}
    <div class="df-card">
        <div class="df-card-header">
            <span style="font-size:13px;font-weight:600;color:#1a0a24;">Relationship Growth</span>
            <span style="font-size:11.5px;color:#9e8fa0;">New relationships per month, last 6 months</span>
        </div>
        <div class="df-card-body">
            <canvas id="growthChart" height="80"></canvas>
        </div>
    </div>

    {{-- Score Distribution --}}
    <div class="df-card">
        <div class="df-card-header">
            <span style="font-size:13px;font-weight:600;color:#1a0a24;">Score Distribution</span>
        </div>
        <div class="df-card-body">
            @php $scoreTotal = collect($scoreDistrib)->sum('count') ?: 1; @endphp
            @foreach($scoreDistrib as $band)
            <div style="margin-bottom:16px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;">
                    <span style="font-size:12.5px;font-weight:500;color:#1a0a24;">{{ $band['label'] }}</span>
                    <span style="font-size:12.5px;font-weight:600;color:{{ $band['color'] }};">{{ number_format($band['count']) }}</span>
                </div>
                <div style="height:6px;background:#f0e8f8;border-radius:3px;overflow:hidden;">
                    <div style="height:100%;width:{{ round($band['count'] / $scoreTotal * 100) }}%;background:{{ $band['color'] }};border-radius:3px;transition:width 600ms ease;"></div>
                </div>
                <p style="font-size:10.5px;color:#b0a4bc;margin-top:3px;">{{ round($band['count'] / $scoreTotal * 100) }}% of relationships</p>
            </div>
            @endforeach

            @if(collect($scoreDistrib)->sum('count') === 0)
            <p style="font-size:13px;color:#b0a4bc;text-align:center;padding:16px 0;">No scored relationships yet. Scores are calculated as patients engage.</p>
            @endif
        </div>
    </div>

</div>

{{-- ─────────────────────────────────────────────────────────────────────
     Row 3: Conversion funnel + Recall success visual
──────────────────────────────────────────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">

    {{-- Lead Conversion Funnel (simple CSS bars) --}}
    <div class="df-card">
        <div class="df-card-header">
            <span style="font-size:13px;font-weight:600;color:#1a0a24;">Lead Conversion Rate</span>
        </div>
        <div class="df-card-body">
            <canvas id="conversionChart" height="120"></canvas>
            <div style="margin-top:16px;display:flex;gap:20px;justify-content:center;">
                <div style="text-align:center;">
                    <p style="font-size:10.5px;color:#9e8fa0;text-transform:uppercase;letter-spacing:0.05em;">Total Leads</p>
                    <p style="font-size:20px;font-weight:700;color:#1a0a24;font-family:'Cormorant Garamond',serif;">{{ number_format($conversion['total']) }}</p>
                </div>
                <div style="text-align:center;">
                    <p style="font-size:10.5px;color:#9e8fa0;text-transform:uppercase;letter-spacing:0.05em;">Converted</p>
                    <p style="font-size:20px;font-weight:700;color:#6a0f70;font-family:'Cormorant Garamond',serif;">{{ number_format($conversion['converted']) }}</p>
                </div>
                <div style="text-align:center;">
                    <p style="font-size:10.5px;color:#9e8fa0;text-transform:uppercase;letter-spacing:0.05em;">Conversion %</p>
                    <p style="font-size:20px;font-weight:700;color:#1a7a45;font-family:'Cormorant Garamond',serif;">{{ $conversion['rate'] }}%</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Recall Success --}}
    <div class="df-card">
        <div class="df-card-header">
            <span style="font-size:13px;font-weight:600;color:#1a0a24;">Recall Success Rate</span>
        </div>
        <div class="df-card-body">
            <canvas id="recallChart" height="120"></canvas>
            <div style="margin-top:16px;display:flex;gap:20px;justify-content:center;">
                <div style="text-align:center;">
                    <p style="font-size:10.5px;color:#9e8fa0;text-transform:uppercase;letter-spacing:0.05em;">Total Recalls</p>
                    <p style="font-size:20px;font-weight:700;color:#1a0a24;font-family:'Cormorant Garamond',serif;">{{ number_format($recallSuccess['total']) }}</p>
                </div>
                <div style="text-align:center;">
                    <p style="font-size:10.5px;color:#9e8fa0;text-transform:uppercase;letter-spacing:0.05em;">Successful</p>
                    <p style="font-size:20px;font-weight:700;color:#1a7a45;font-family:'Cormorant Garamond',serif;">{{ number_format($recallSuccess['successful']) }}</p>
                </div>
                <div style="text-align:center;">
                    <p style="font-size:10.5px;color:#9e8fa0;text-transform:uppercase;letter-spacing:0.05em;">Success %</p>
                    <p style="font-size:20px;font-weight:700;color:{{ $recallSuccess['rate'] >= 50 ? '#1a7a45' : '#a05c00' }};font-family:'Cormorant Garamond',serif;">{{ $recallSuccess['rate'] }}%</p>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- ─────────────────────────────────────────────────────────────────────
     Row 4: Staff KPIs table
──────────────────────────────────────────────────────────────────────── --}}
<div class="df-card" style="margin-bottom:24px;">
    <div class="df-card-header">
        <span style="font-size:13px;font-weight:600;color:#1a0a24;">Staff KPIs</span>
        <span style="font-size:11.5px;color:#9e8fa0;">Last 30 days</span>
    </div>
    @if(count($staffKpis) > 0)
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:#faf5fb;">
                    <th style="padding:10px 20px;text-align:left;font-size:11px;font-weight:600;color:#9e8fa0;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #f0e8f8;">Staff Member</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:#9e8fa0;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #f0e8f8;">Role</th>
                    <th style="padding:10px 16px;text-align:right;font-size:11px;font-weight:600;color:#9e8fa0;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #f0e8f8;">Calls Logged</th>
                    <th style="padding:10px 16px;text-align:right;font-size:11px;font-weight:600;color:#9e8fa0;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #f0e8f8;">Tasks Completed</th>
                    <th style="padding:10px 16px;text-align:right;font-size:11px;font-weight:600;color:#9e8fa0;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #f0e8f8;">Leads Converted</th>
                </tr>
            </thead>
            <tbody>
                @foreach($staffKpis as $row)
                <tr style="border-bottom:1px solid #f8f2fb;">
                    <td style="padding:11px 20px;font-weight:500;color:#1a0a24;">{{ $row['name'] }}</td>
                    <td style="padding:11px 16px;">
                        <span style="display:inline-block;padding:2px 8px;font-size:10.5px;font-weight:500;background:#f5eef9;color:#6a0f70;border:1px solid rgba(185,92,183,0.18);border-radius:2px;">
                            {{ ucwords(str_replace('_', ' ', $row['role'])) }}
                        </span>
                    </td>
                    <td style="padding:11px 16px;text-align:right;font-weight:600;color:#1a5ea8;">{{ number_format($row['calls_logged']) }}</td>
                    <td style="padding:11px 16px;text-align:right;font-weight:600;color:#1a7a45;">{{ number_format($row['tasks_done']) }}</td>
                    <td style="padding:11px 16px;text-align:right;font-weight:600;color:#6a0f70;">{{ number_format($row['leads_converted']) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div style="padding:40px;text-align:center;">
        <p style="font-size:13px;color:#b0a4bc;">No staff activity recorded in the last 30 days.</p>
    </div>
    @endif
</div>

{{-- ─────────────────────────────────────────────────────────────────────
     Cache note
──────────────────────────────────────────────────────────────────────── --}}
<p style="font-size:11px;color:#b0a4bc;text-align:center;margin-bottom:32px;">
    All metrics cached for 1 hour. Last refreshed: {{ now()->format('d M Y, h:i A') }}.
    <a href="{{ route('relationship.analytics') }}?bust={{ time() }}"
       style="color:#6a0f70;text-decoration:none;"
       onclick="event.preventDefault(); fetch('/relationship/analytics?bust=' + Date.now()); location.reload();"
    >Refresh now</a>
</p>

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
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                y: { beginAtZero: true, ticks: { precision: 0, font: { size: 11 } } },
            },
        },
    });

    // ── Conversion doughnut ───────────────────────────────────────────────
    var conv = @json($conversion);
    new Chart(document.getElementById('conversionChart'), {
        type: 'doughnut',
        data: {
            labels:   ['Converted', 'Not Yet'],
            datasets: [{
                data:            [conv.converted, conv.total - conv.converted],
                backgroundColor: ['#6a0f70', '#f0e8f8'],
                borderWidth:     0,
                hoverOffset:     4,
            }],
        },
        options: {
            responsive: true,
            cutout: '72%',
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 12, padding: 12 } },
                tooltip: { callbacks: { label: function (ctx) { return ' ' + ctx.label + ': ' + ctx.parsed; } } },
            },
        },
    });

    // ── Recall success doughnut ───────────────────────────────────────────
    var recall = @json($recallSuccess);
    new Chart(document.getElementById('recallChart'), {
        type: 'doughnut',
        data: {
            labels:   ['Successful', 'Unsuccessful'],
            datasets: [{
                data:            [recall.successful, recall.total - recall.successful],
                backgroundColor: ['#1a7a45', '#fdeaea'],
                borderWidth:     0,
                hoverOffset:     4,
            }],
        },
        options: {
            responsive: true,
            cutout: '72%',
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 12, padding: 12 } },
                tooltip: { callbacks: { label: function (ctx) { return ' ' + ctx.label + ': ' + ctx.parsed; } } },
            },
        },
    });
})();
</script>
@endpush
