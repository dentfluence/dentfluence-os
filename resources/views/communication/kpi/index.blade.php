{{--
    KPI Dashboard — Phase 5 Communication OS
    Admin-only. Full complexity is intentional per UI rule.
--}}
@extends('layouts.communication')

@push('communication-styles')
<style>
/* ── Layout ── */
.kpi-page { padding:0 0 48px; }
.kpi-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; padding:14px 24px; background:#fff; border-bottom:1px solid #e2e8f0; }
.kpi-header h1 { font-size:16px; font-weight:700; color:#0f172a; margin:0; }
.kpi-header .date-range { font-size:12px; color:#64748b; }

/* Period filter */
.kpi-period-form { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.kpi-period-form select, .kpi-period-form input[type=date] { padding:5px 10px; font-size:12px; border:1px solid #e2e8f0; border-radius:6px; outline:none; }
.kpi-period-form button { padding:5px 12px; font-size:12px; font-weight:600; background:#6a0f70; color:#fff; border:none; border-radius:6px; cursor:pointer; }

/* Alert strip */
.kpi-alert-strip { display:flex; gap:10px; flex-wrap:wrap; padding:10px 24px; background:#fff7ed; border-bottom:1px solid #fed7aa; }
.kpi-alert-item { display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; color:#ea580c; }

/* Metric cards */
.kpi-section { padding:20px 24px 0; }
.kpi-section-title { font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.07em; margin-bottom:12px; }
.kpi-cards { display:grid; grid-template-columns:repeat(auto-fill, minmax(160px, 1fr)); gap:12px; }
.kpi-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:16px; }
.kpi-card__label { font-size:11px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px; }
.kpi-card__value { font-size:26px; font-weight:800; color:#0f172a; line-height:1; }
.kpi-card__sub { font-size:11px; color:#94a3b8; margin-top:4px; }
.kpi-card--green { border-left:3px solid #16a34a; }
.kpi-card--yellow { border-left:3px solid #d97706; }
.kpi-card--red    { border-left:3px solid #dc2626; }
.kpi-card--purple { border-left:3px solid #7c3aed; }
.kpi-card--blue   { border-left:3px solid #2563eb; }

/* Two-column grid for tables */
.kpi-two-col { display:grid; grid-template-columns:1fr 1fr; gap:16px; padding:16px 24px; }
@media(max-width:768px) { .kpi-two-col { grid-template-columns:1fr; } }

.kpi-panel { background:#fff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; }
.kpi-panel-head { padding:10px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0; font-size:12px; font-weight:700; color:#374151; }
.kpi-panel-body { padding:12px 16px; }

/* Tables */
table.kpi-t { width:100%; border-collapse:collapse; font-size:12px; }
table.kpi-t th { color:#94a3b8; font-size:10px; font-weight:700; text-transform:uppercase; padding:6px 8px; border-bottom:1px solid #f1f5f9; text-align:left; }
table.kpi-t td { padding:8px 8px; border-bottom:1px solid #f8fafc; }
table.kpi-t tr:last-child td { border-bottom:none; }
.kpi-bar-wrap { background:#f1f5f9; border-radius:4px; height:8px; overflow:hidden; min-width:60px; }
.kpi-bar { height:8px; border-radius:4px; background:#6a0f70; transition:width .3s; }
.kpi-bar--green  { background:#16a34a; }
.kpi-bar--yellow { background:#d97706; }
.kpi-bar--red    { background:#dc2626; }

/* Chart area */
.kpi-chart-wrap { padding:16px 24px; }
.kpi-chart-canvas { width:100%; height:180px; }
.kpi-bar-chart { display:flex; align-items:flex-end; gap:3px; height:140px; border-bottom:1px solid #e2e8f0; padding-bottom:4px; }
.kpi-bar-chart-bar { flex:1; background:#6a0f70; border-radius:3px 3px 0 0; min-height:2px; position:relative; cursor:pointer; }
.kpi-bar-chart-bar:hover { background:#4e0b52; }
.kpi-bar-chart-bar .tip { position:absolute; bottom:100%; left:50%; transform:translateX(-50%); background:#1e293b; color:#fff; font-size:10px; padding:2px 6px; border-radius:4px; white-space:nowrap; display:none; pointer-events:none; }
.kpi-bar-chart-bar:hover .tip { display:block; }
.kpi-chart-labels { display:flex; gap:3px; margin-top:4px; }
.kpi-chart-label { flex:1; font-size:9px; color:#94a3b8; text-align:center; overflow:hidden; }

/* Funnel */
.kpi-funnel { display:flex; flex-direction:column; gap:6px; }
.kpi-funnel-row { display:flex; align-items:center; gap:10px; }
.kpi-funnel-label { font-size:12px; color:#374151; width:120px; flex-shrink:0; }
.kpi-funnel-bar-wrap { flex:1; background:#f1f5f9; border-radius:4px; height:20px; overflow:hidden; }
.kpi-funnel-bar { height:20px; border-radius:4px; display:flex; align-items:center; padding:0 8px; }
.kpi-funnel-count { font-size:11px; font-weight:700; color:#fff; white-space:nowrap; }
.kpi-funnel-pct { font-size:11px; color:#64748b; width:40px; text-align:right; }

/* Staff table */
.staff-avatar { width:28px; height:28px; border-radius:50%; background:#6a0f70; color:#fff; font-size:11px; font-weight:700; display:inline-flex; align-items:center; justify-content:center; }
</style>
@endpush

@section('communication-content')
<div class="kpi-page">

{{-- Header --}}
<div class="kpi-header">
    <div>
        <h1>Communication KPI Dashboard</h1>
        <div class="date-range">
            {{ $from->format('d M Y') }} — {{ $to->format('d M Y') }}
        </div>
    </div>
    <form method="GET" action="{{ route('communication.kpi.index') }}" class="kpi-period-form">
        <select name="period" onchange="this.form.submit()">
            <option value="7"  {{ $period == '7'  ? 'selected' : '' }}>Last 7 days</option>
            <option value="14" {{ $period == '14' ? 'selected' : '' }}>Last 14 days</option>
            <option value="30" {{ $period == '30' ? 'selected' : '' }}>Last 30 days</option>
            <option value="90" {{ $period == '90' ? 'selected' : '' }}>Last 90 days</option>
        </select>
        <input type="date" name="from" value="{{ request('from', $from->format('Y-m-d')) }}">
        <span style="font-size:12px;color:#94a3b8;">to</span>
        <input type="date" name="to"   value="{{ request('to', $to->format('Y-m-d')) }}">
        <button type="submit">Apply</button>
    </form>
</div>

{{-- Alert strip — only shown if there are issues --}}
@if($highValueUncontacted > 0 || $inboundSlaViolations > 0)
<div class="kpi-alert-strip">
    @if($highValueUncontacted > 0)
    <div class="kpi-alert-item">
        {{ $highValueUncontacted }} high-value lead{{ $highValueUncontacted > 1 ? 's' : '' }} (Rs. 30k+) not contacted in 2+ hrs
    </div>
    @endif
    @if($inboundSlaViolations > 0)
    <div class="kpi-alert-item">
        {{ $inboundSlaViolations }} inbound SLA violation{{ $inboundSlaViolations > 1 ? 's' : '' }} in period
    </div>
    @endif
</div>
@endif

{{-- ── Section 1: Conversion Funnel ───────────────────────────────────────── --}}
<div class="kpi-section">
    <div class="kpi-section-title">Lead Conversion Funnel</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

        {{-- Funnel visual --}}
        <div class="kpi-panel">
            <div class="kpi-panel-head">Funnel — {{ $totalLeads }} total leads</div>
            <div class="kpi-panel-body">
                @php
                    $maxLeads = max($totalLeads, 1);
                    $funnelStages = [
                        ['label' => 'Total Leads',      'count' => $totalLeads, 'color' => '#6a0f70'],
                        ['label' => 'Appointment Set',  'count' => $apptLeads,  'color' => '#2563eb'],
                        ['label' => 'Won / Converted',  'count' => $wonLeads,   'color' => '#16a34a'],
                        ['label' => 'Lost',             'count' => $lostLeads,  'color' => '#dc2626'],
                    ];
                @endphp
                <div class="kpi-funnel">
                    @foreach($funnelStages as $fs)
                    @php $pct = $maxLeads > 0 ? round($fs['count'] / $maxLeads * 100) : 0; @endphp
                    <div class="kpi-funnel-row">
                        <div class="kpi-funnel-label">{{ $fs['label'] }}</div>
                        <div class="kpi-funnel-bar-wrap">
                            <div class="kpi-funnel-bar" style="width:{{ $pct }}%;background:{{ $fs['color'] }}">
                                @if($fs['count'] > 0)
                                <span class="kpi-funnel-count">{{ $fs['count'] }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="kpi-funnel-pct">{{ $pct }}%</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Conversion rate cards --}}
        <div style="display:flex;flex-direction:column;gap:12px;">
            <div class="kpi-card kpi-card--blue">
                <div class="kpi-card__label">Lead → Appointment %</div>
                <div class="kpi-card__value" style="color:#2563eb;">{{ $leadToAppt }}%</div>
                <div class="kpi-card__sub">{{ $apptLeads }} of {{ $totalLeads }} leads</div>
            </div>
            <div class="kpi-card kpi-card--green">
                <div class="kpi-card__label">Lead → Treatment Won %</div>
                <div class="kpi-card__value" style="color:#16a34a;">{{ $leadToTreatment }}%</div>
                <div class="kpi-card__sub">{{ $wonLeads }} converted · Rs. {{ number_format($totalWonValue) }} revenue</div>
            </div>
            <div class="kpi-card kpi-card--red">
                <div class="kpi-card__label">Lost Leads</div>
                <div class="kpi-card__value" style="color:#dc2626;">{{ $lostLeads }}</div>
                <div class="kpi-card__sub">{{ $totalLeads > 0 ? round($lostLeads/$totalLeads*100,1) : 0 }}% lost rate</div>
            </div>
        </div>
    </div>
</div>

{{-- ── Section 2: SLA & Response Time ────────────────────────────────────── --}}
<div class="kpi-section" style="margin-top:20px;">
    <div class="kpi-section-title">SLA & Response Time</div>
    <div class="kpi-two-col" style="padding:0;">
        <div style="display:flex;flex-direction:column;gap:12px;grid-column:1;">

            <div class="kpi-card {{ $slaBreachRate > 20 ? 'kpi-card--red' : ($slaBreachRate > 10 ? 'kpi-card--yellow' : 'kpi-card--green') }}">
                <div class="kpi-card__label">SLA Breach Rate</div>
                <div class="kpi-card__value" style="color:{{ $slaBreachRate > 20 ? '#dc2626' : ($slaBreachRate > 10 ? '#d97706' : '#16a34a') }}">
                    {{ $slaBreachRate }}%
                </div>
                <div class="kpi-card__sub">{{ $breachedCount }} of {{ $totalWithSla }} comms breached SLA</div>
            </div>

            <div class="kpi-card kpi-card--purple">
                <div class="kpi-card__label">Escalations</div>
                <div class="kpi-card__value" style="color:#7c3aed;">{{ $escalations }}</div>
                <div class="kpi-card__sub">Comms escalated to manager</div>
            </div>
        </div>

        <div class="kpi-panel" style="grid-column:2;">
            <div class="kpi-panel-head">Avg Response Time by Channel</div>
            <div class="kpi-panel-body">
                @forelse($avgResponseByChannel as $ch)
                @php
                    $mins = $ch->avg_minutes;
                    $color = $mins <= 30 ? '#16a34a' : ($mins <= 60 ? '#d97706' : '#dc2626');
                    $maxMins = $avgResponseByChannel->max('avg_minutes') ?: 1;
                    $barWidth = round($mins / $maxMins * 100);
                @endphp
                <div style="margin-bottom:10px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:3px;">
                        <span style="font-size:12px;font-weight:600;color:#374151;">
                            {{ \App\Models\CommunicationQueue::CHANNELS[$ch->channel] ?? ucfirst($ch->channel) }}
                        </span>
                        <span style="font-size:11px;font-weight:700;color:{{ $color }}">
                            {{ $mins < 60 ? $mins.'m' : round($mins/60,1).'h' }}
                            <span style="color:#94a3b8;font-weight:400;">({{ $ch->total }})</span>
                        </span>
                    </div>
                    <div class="kpi-bar-wrap">
                        <div class="kpi-bar" style="width:{{ $barWidth }}%;background:{{ $color }};"></div>
                    </div>
                </div>
                @empty
                <div style="color:#94a3b8;font-size:12px;padding:12px 0;text-align:center;">
                    No response time data in this period.
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ── Section 3: Staff Performance ──────────────────────────────────────── --}}
<div class="kpi-section" style="margin-top:20px;">
    <div class="kpi-section-title">Staff Performance</div>
    <div class="kpi-panel">
        <div class="kpi-panel-head">Activity by Staff Member</div>
        <div class="kpi-panel-body" style="padding:0;">
            @if($staffPerformance->isEmpty())
            <div style="padding:20px;text-align:center;color:#94a3b8;font-size:12px;">No data — assign comms to staff to track performance.</div>
            @else
            <table class="kpi-t">
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Comms Logged</th>
                        <th>Total Attempts</th>
                        <th>Closed</th>
                        <th>SLA Breaches</th>
                        <th>Close Rate</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($staffPerformance as $sp)
                @php
                    $closeRate = $sp->total_comms > 0 ? round($sp->closed_count / $sp->total_comms * 100) : 0;
                    $initials  = strtoupper(substr($sp->assigned_to, 0, 2));
                @endphp
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="staff-avatar">{{ $initials }}</div>
                            <span style="font-weight:600;color:#0f172a;">{{ $sp->assigned_to }}</span>
                        </div>
                    </td>
                    <td style="font-weight:700;color:#6a0f70;">{{ $sp->total_comms }}</td>
                    <td>{{ $sp->total_attempts }}</td>
                    <td style="color:#16a34a;font-weight:600;">{{ $sp->closed_count }}</td>
                    <td style="color:{{ $sp->breached_count > 0 ? '#dc2626' : '#94a3b8' }};font-weight:600;">
                        {{ $sp->breached_count }}
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <div class="kpi-bar-wrap" style="width:60px;">
                                <div class="kpi-bar {{ $closeRate >= 60 ? 'kpi-bar--green' : ($closeRate >= 30 ? 'kpi-bar--yellow' : 'kpi-bar--red') }}"
                                     style="width:{{ $closeRate }}%"></div>
                            </div>
                            <span style="font-size:11px;font-weight:700;color:#374151;">{{ $closeRate }}%</span>
                        </div>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
</div>

{{-- ── Section 4: Pipeline Rs.  by Stage + Won Sources ──────────────────────── --}}
<div class="kpi-section" style="margin-top:20px;">
    <div class="kpi-section-title">Pipeline & Revenue</div>
    <div class="kpi-two-col" style="padding:0;gap:16px;">

        <div class="kpi-panel">
            <div class="kpi-panel-head">Pipeline Rs.  by Stage — Rs. {{ number_format($totalPipelineValue) }} total open</div>
            <div class="kpi-panel-body">
                @forelse($pipelineByStage as $ps)
                @php
                    $pct = $totalPipelineValue > 0 ? round($ps->total_value / $totalPipelineValue * 100) : 0;
                @endphp
                <div style="margin-bottom:10px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:3px;">
                        <span style="font-size:12px;font-weight:600;color:#374151;">{{ ucwords(str_replace('_',' ',$ps->stage)) }}</span>
                        <span style="font-size:11px;">
                            <strong style="color:#6a0f70;">Rs. {{ number_format($ps->total_value) }}</strong>
                            <span style="color:#94a3b8;">({{ $ps->count }})</span>
                        </span>
                    </div>
                    <div class="kpi-bar-wrap">
                        <div class="kpi-bar" style="width:{{ $pct }}%"></div>
                    </div>
                </div>
                @empty
                <div style="color:#94a3b8;font-size:12px;text-align:center;padding:12px 0;">No open pipeline.</div>
                @endforelse
            </div>
        </div>

        <div class="kpi-panel">
            <div class="kpi-panel-head">Won by Source — Rs. {{ number_format($totalWonValue) }} won in period</div>
            <div class="kpi-panel-body">
                @forelse($wonBySource as $ws)
                @php
                    $pct = $totalWonValue > 0 ? round($ws->value / $totalWonValue * 100) : 0;
                @endphp
                <div style="margin-bottom:10px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:3px;">
                        <span style="font-size:12px;font-weight:600;color:#374151;">
                            {{ \App\Models\Lead::LEAD_SOURCES[$ws->lead_source] ?? ucfirst($ws->lead_source ?? '—') }}
                        </span>
                        <span style="font-size:11px;">
                            <strong style="color:#16a34a;">Rs. {{ number_format($ws->value) }}</strong>
                            <span style="color:#94a3b8;">({{ $ws->count }})</span>
                        </span>
                    </div>
                    <div class="kpi-bar-wrap">
                        <div class="kpi-bar kpi-bar--green" style="width:{{ $pct }}%"></div>
                    </div>
                </div>
                @empty
                <div style="color:#94a3b8;font-size:12px;text-align:center;padding:12px 0;">No won leads in period.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ── Section 5: Daily Volume Chart ─────────────────────────────────────── --}}
<div class="kpi-section" style="margin-top:20px;">
    <div class="kpi-section-title">Daily Communication Volume</div>
    <div class="kpi-panel">
        <div class="kpi-panel-head">Comms logged per day</div>
        <div class="kpi-panel-body">
            @php
                $maxVol = max(collect($dailyVolume)->pluck('count')->max(), 1);
            @endphp
            <div class="kpi-bar-chart">
                @foreach($dailyVolume as $dv)
                @php $h = round($dv['count'] / $maxVol * 130); @endphp
                <div class="kpi-bar-chart-bar" style="height:{{ max($h,2) }}px;">
                    <div class="tip">{{ $dv['date'] }}: {{ $dv['count'] }}</div>
                </div>
                @endforeach
            </div>
            <div class="kpi-chart-labels">
                @foreach($dailyVolume as $dv)
                <div class="kpi-chart-label">{{ $dv['date'] }}</div>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- ── Section 6: Lost Reasons ────────────────────────────────────────────── --}}
@if($lostComms->isNotEmpty())
<div class="kpi-section" style="margin-top:20px;">
    <div class="kpi-section-title">Lost / Closed Outcomes</div>
    <div class="kpi-panel">
        <div class="kpi-panel-head">Why comms are being closed without conversion</div>
        <div class="kpi-panel-body">
            <table class="kpi-t">
                <thead>
                    <tr>
                        <th>Outcome</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lostComms as $lc)
                    <tr>
                        <td>{{ \App\Models\CommunicationQueue::OUTCOMES[$lc->outcome] ?? ucfirst(str_replace('_',' ',$lc->outcome)) }}</td>
                        <td style="font-weight:700;color:#dc2626;">{{ $lc->count }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

</div>{{-- /kpi-page --}}
@endsection
