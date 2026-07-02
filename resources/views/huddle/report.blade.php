@extends('layouts.app')

@section('title', $periodLabel . ' Report — Huddle')

@section('head-extra')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
@endsection

@push('styles')
<style>
/* ── Shared design tokens (mirrors the Daily Huddle) ───────────────────── */
.rp-escape { margin: -28px -32px -48px; }
@media(max-width:767px){ .rp-escape { margin: -20px -16px -40px; } }

.rp {
    --c-bg:     #f0f2f7;
    --c-white:  #ffffff;
    --c-border: #e4e8f0;
    --c-text:   #1a1d2e;
    --c-muted:  #6b7280;
    --c-accent: #4f46e5;
    --c-green:  #16a34a;
    --c-red:    #dc2626;
    --c-amber:  #d97706;
    --c-blue:   #2563eb;
    --c-teal:   #0891b2;

    font-family: 'Inter', sans-serif;
    background: var(--c-bg);
    min-height: 100vh;
    color: var(--c-text);
    font-size: 13px;
}

/* Top nav bar — same as Daily Huddle so the tabs line up */
.rp-topbar {
    background: var(--c-white);
    border-bottom: 1px solid var(--c-border);
    padding: 0 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    height: 52px;
    position: sticky;
    top: 0;
    z-index: 50;
}
.rp-brand {
    display: flex; align-items: center; gap: .5rem;
    font-weight: 700; font-size: .85rem; white-space: nowrap;
    padding-right: 1rem; border-right: 1px solid var(--c-border); margin-right: .25rem;
}
.rp-brand svg { color: var(--c-accent); }
.rp-tabs { display: flex; gap: .15rem; flex: 1; flex-wrap: wrap; }
.rp-tab {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .32rem .75rem; border-radius: 6px;
    font-size: .78rem; font-weight: 500; color: var(--c-muted);
    text-decoration: none; white-space: nowrap;
    border: 1px solid transparent; transition: background .12s, color .12s;
}
.rp-tab:hover { background: #f3f4f6; color: var(--c-text); }
.rp-tab.active { background: var(--c-accent); color: #fff; border-color: var(--c-accent); }

/* Header / range strip */
.rp-header {
    display: flex; align-items: center; flex-wrap: wrap; gap: 1rem;
    padding: 1.1rem 1.5rem .4rem;
}
.rp-title { font-size: 1.1rem; font-weight: 700; }
.rp-title span { font-size: .72rem; font-weight: 500; color: var(--c-muted); margin-left: .5rem; }
.rp-range-form { display: flex; align-items: center; gap: .4rem; margin-left: auto; flex-wrap: wrap; }
.rp-range-form input[type=date] {
    border: 1px solid var(--c-border); border-radius: 6px;
    padding: .3rem .5rem; font-family: inherit; font-size: .75rem; color: var(--c-text);
}
.rp-range-form button {
    background: var(--c-accent); color: #fff; border: none; border-radius: 6px;
    padding: .35rem .8rem; font-size: .75rem; font-weight: 600; cursor: pointer;
}
.rp-range-form label { font-size: .72rem; color: var(--c-muted); }

/* Section block */
.rp-section { padding: .6rem 1.5rem 0; }
.rp-section-head {
    display: flex; align-items: center; gap: .5rem;
    font-size: .82rem; font-weight: 700; margin: .9rem 0 .55rem;
}
.rp-section-head .dot { width: 8px; height: 8px; border-radius: 50%; }

/* Horizontally scrollable card row */
.rp-cards {
    display: flex; gap: .75rem; overflow-x: auto; padding-bottom: .65rem;
    scroll-snap-type: x proximity; -webkit-overflow-scrolling: touch;
}
.rp-cards::-webkit-scrollbar { height: 7px; }
.rp-cards::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 8px; }
.rp-card {
    flex: 0 0 auto; min-width: 160px;
    background: var(--c-white); border: 1px solid var(--c-border);
    border-radius: 11px; padding: .85rem .95rem;
    scroll-snap-align: start;
    box-shadow: 0 1px 2px rgba(16,24,40,.04);
}
.rp-card .lbl { font-size: .7rem; color: var(--c-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .02em; }
.rp-card .val { font-size: 1.5rem; font-weight: 700; margin: .25rem 0 .15rem; }
.rp-card .sub { font-size: .7rem; color: var(--c-muted); }

/* Trend chip (period vs previous period) */
.rp-card .row { display: flex; align-items: center; justify-content: space-between; gap: .4rem; margin: .25rem 0 .15rem; }
.rp-card .row .val { margin: 0; }
.rp-trend {
    display: inline-flex; align-items: center; gap: .15rem;
    font-size: .68rem; font-weight: 700; padding: .12rem .4rem;
    border-radius: 999px; white-space: nowrap;
}
.rp-trend.good { color: var(--c-green); background: #e7f6ee; }
.rp-trend.bad  { color: var(--c-red);   background: #fdeaea; }
.rp-trend.flat { color: var(--c-muted); background: #eef0f4; }
.rp-trend svg { width: 10px; height: 10px; }
.rp-card.green .val { color: var(--c-green); }
.rp-card.red   .val { color: var(--c-red); }
.rp-card.amber .val { color: var(--c-amber); }
.rp-card.blue  .val { color: var(--c-blue); }
.rp-card.teal  .val { color: var(--c-teal); }

/* Breakdown mini-table inside a wide card */
.rp-breakdown {
    flex: 0 0 auto; min-width: 240px; max-width: 320px;
    background: var(--c-white); border: 1px solid var(--c-border);
    border-radius: 11px; padding: .85rem .95rem;
}
.rp-breakdown .lbl { font-size: .7rem; color: var(--c-muted); font-weight: 600; text-transform: uppercase; margin-bottom: .5rem; }
.rp-brk-row { display: flex; justify-content: space-between; font-size: .76rem; padding: .22rem 0; border-bottom: 1px dashed var(--c-border); }
.rp-brk-row:last-child { border-bottom: none; }
.rp-brk-row .k { color: var(--c-text); text-transform: capitalize; }
.rp-brk-row .v { font-weight: 600; }
.rp-empty { font-size: .74rem; color: var(--c-muted); padding: .4rem 0; }
</style>
@endpush

@section('content')
<div class="rp rp-escape">

    {{-- ══ TOP NAV ══════════════════════════════════════════════════════════ --}}
    <div class="rp-topbar">
        <div class="rp-brand">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            Tulip Dental
        </div>
        <nav class="rp-tabs">
            <a href="{{ route('huddle.index') }}" class="rp-tab">Daily Huddle</a>
            <a href="{{ route('huddle.report', ['period' => 'week']) }}"    class="rp-tab {{ $period === 'week' ? 'active' : '' }}">Weekly Report</a>
            <a href="{{ route('huddle.report', ['period' => 'month']) }}"   class="rp-tab {{ $period === 'month' ? 'active' : '' }}">Monthly Report</a>
            <a href="{{ route('huddle.report', ['period' => 'quarter']) }}" class="rp-tab {{ $period === 'quarter' ? 'active' : '' }}">Quarterly Report</a>
            <a href="{{ route('huddle.report', ['period' => 'year']) }}"    class="rp-tab {{ $period === 'year' ? 'active' : '' }}">Annual Report</a>
            <a href="{{ route('huddle.report', ['period' => 'custom', 'from' => $fromDate, 'to' => $toDate]) }}" class="rp-tab {{ $period === 'custom' ? 'active' : '' }}">Custom</a>
        </nav>
    </div>

    {{-- ══ HEADER + CUSTOM RANGE ════════════════════════════════════════════ --}}
    <div class="rp-header">
        <div class="rp-title">
            {{ $periodLabel }}
            <span>{{ \Carbon\Carbon::parse($fromDate)->format('d M Y') }} → {{ \Carbon\Carbon::parse($toDate)->format('d M Y') }} · {{ $rangeDays }} days · trend vs previous {{ $rangeDays }} days</span>
        </div>
        <form method="GET" action="{{ route('huddle.report') }}" class="rp-range-form">
            <input type="hidden" name="period" value="custom">
            <label>From</label>
            <input type="date" name="from" value="{{ $fromDate }}" max="{{ now()->toDateString() }}">
            <label>To</label>
            <input type="date" name="to" value="{{ $toDate }}" max="{{ now()->toDateString() }}">
            <button type="submit">Apply</button>
        </form>
    </div>

    {{-- ══ 1. COLLECTIONS & REVENUE ═════════════════════════════════════════ --}}
    <div class="rp-section">
        <div class="rp-section-head"><span class="dot" style="background:var(--c-green)"></span>Collections &amp; Revenue</div>
        <div class="rp-cards">
            @foreach($collectionsCards as $c)
                <div class="rp-card {{ $c['tone'] }}">
                    <div class="lbl">{{ $c['label'] }}</div>
                    <div class="row">
                        <div class="val">{{ $c['value'] }}</div>
                        @isset($c['trend'])
                            @php $t = $c['trend']; $cls = $t['good'] === null ? 'flat' : ($t['good'] ? 'good' : 'bad'); @endphp
                            <span class="rp-trend {{ $cls }}" title="vs previous {{ $rangeDays }} days">
                                @if($t['dir'] === 'up')
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
                                @elseif($t['dir'] === 'down')
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                @endif
                                {{ $t['pct'] }}
                            </span>
                        @endisset
                    </div>
                    <div class="sub">{{ $c['sub'] }}</div>
                </div>
            @endforeach
            <div class="rp-breakdown">
                <div class="lbl">By Payment Mode</div>
                @forelse($byMode as $m)
                    <div class="rp-brk-row">
                        <span class="k">{{ str_replace('_', ' ', $m->payment_mode) }} <span style="color:var(--c-muted)">({{ $m->cnt }})</span></span>
                        <span class="v">₹{{ number_format((float) $m->total, 0) }}</span>
                    </div>
                @empty
                    <div class="rp-empty">No transactions in this period.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ══ 2. APPOINTMENTS & VISITS ═════════════════════════════════════════ --}}
    <div class="rp-section">
        <div class="rp-section-head"><span class="dot" style="background:var(--c-blue)"></span>Appointments &amp; Visits</div>
        <div class="rp-cards">
            @foreach($apptCards as $c)
                <div class="rp-card {{ $c['tone'] }}">
                    <div class="lbl">{{ $c['label'] }}</div>
                    <div class="row">
                        <div class="val">{{ $c['value'] }}</div>
                        @isset($c['trend'])
                            @php $t = $c['trend']; $cls = $t['good'] === null ? 'flat' : ($t['good'] ? 'good' : 'bad'); @endphp
                            <span class="rp-trend {{ $cls }}" title="vs previous {{ $rangeDays }} days">
                                @if($t['dir'] === 'up')
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
                                @elseif($t['dir'] === 'down')
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                @endif
                                {{ $t['pct'] }}
                            </span>
                        @endisset
                    </div>
                    <div class="sub">{{ $c['sub'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ══ 3. NEW PATIENTS & SOURCES ════════════════════════════════════════ --}}
    <div class="rp-section">
        <div class="rp-section-head"><span class="dot" style="background:var(--c-teal)"></span>New Patients &amp; Sources</div>
        <div class="rp-cards">
            @foreach($patientCards as $c)
                <div class="rp-card {{ $c['tone'] }}">
                    <div class="lbl">{{ $c['label'] }}</div>
                    <div class="row">
                        <div class="val">{{ $c['value'] }}</div>
                        @isset($c['trend'])
                            @php $t = $c['trend']; $cls = $t['good'] === null ? 'flat' : ($t['good'] ? 'good' : 'bad'); @endphp
                            <span class="rp-trend {{ $cls }}" title="vs previous {{ $rangeDays }} days">
                                @if($t['dir'] === 'up')
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
                                @elseif($t['dir'] === 'down')
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                @endif
                                {{ $t['pct'] }}
                            </span>
                        @endisset
                    </div>
                    <div class="sub">{{ $c['sub'] }}</div>
                </div>
            @endforeach
            <div class="rp-breakdown">
                <div class="lbl">By Source</div>
                @forelse($sourceBreakdown as $s)
                    <div class="rp-brk-row">
                        <span class="k">{{ str_replace('_', ' ', $s->source) }}</span>
                        <span class="v">{{ $s->cnt }}</span>
                    </div>
                @empty
                    <div class="rp-empty">No new patients in this period.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ══ 4. LAB & TASKS ═══════════════════════════════════════════════════ --}}
    <div class="rp-section" style="padding-bottom:1.5rem;">
        <div class="rp-section-head"><span class="dot" style="background:var(--c-amber)"></span>Lab &amp; Tasks</div>
        <div class="rp-cards">
            @foreach($labTaskCards as $c)
                <div class="rp-card {{ $c['tone'] }}">
                    <div class="lbl">{{ $c['label'] }}</div>
                    <div class="row">
                        <div class="val">{{ $c['value'] }}</div>
                        @isset($c['trend'])
                            @php $t = $c['trend']; $cls = $t['good'] === null ? 'flat' : ($t['good'] ? 'good' : 'bad'); @endphp
                            <span class="rp-trend {{ $cls }}" title="vs previous {{ $rangeDays }} days">
                                @if($t['dir'] === 'up')
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
                                @elseif($t['dir'] === 'down')
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                @endif
                                {{ $t['pct'] }}
                            </span>
                        @endisset
                    </div>
                    <div class="sub">{{ $c['sub'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ══ 5. PRACTICE PROTOCOL COMPLIANCE ══════════════════════════════════ --}}
    <div class="rp-section" style="padding-bottom:1.5rem;">
        <div class="rp-section-head"><span class="dot" style="background:var(--c-violet,#6a0f70)"></span>Practice Protocol Compliance</div>

        {{-- Summary tiles --}}
        <div class="rp-cards">
            <div class="rp-card blue"><div class="lbl">Protocol Tasks</div><div class="row"><div class="val">{{ $protocolTotals['total'] }}</div></div><div class="sub">Generated in period</div></div>
            <div class="rp-card green"><div class="lbl">Completed</div><div class="row"><div class="val">{{ $protocolTotals['done'] }}</div></div><div class="sub">Done in period</div></div>
            <div class="rp-card red"><div class="lbl">Missed</div><div class="row"><div class="val">{{ $protocolTotals['missed'] }}</div></div><div class="sub">Past due, not done</div></div>
            <div class="rp-card green"><div class="lbl">Completion Rate</div><div class="row"><div class="val">{{ $protocolTotals['rate'] }}%</div></div><div class="sub">Across all staff</div></div>
        </div>

        {{-- Per-person breakdown --}}
        @if($protocolCompliance->count())
        <div style="margin-top:14px;background:#fff;border:1.5px solid #ede4f3;border-radius:10px;overflow:hidden;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;font-family:'Inter',sans-serif;">
                <thead>
                    <tr style="background:#faf6fd;color:#7a6a85;font-size:11px;text-transform:uppercase;letter-spacing:.06em;">
                        <th style="text-align:left;padding:9px 14px;font-weight:600;">Staff</th>
                        <th style="text-align:center;padding:9px 14px;font-weight:600;">Assigned</th>
                        <th style="text-align:center;padding:9px 14px;font-weight:600;">Done</th>
                        <th style="text-align:center;padding:9px 14px;font-weight:600;">Missed</th>
                        <th style="text-align:left;padding:9px 14px;font-weight:600;min-width:140px;">Completion</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($protocolCompliance as $row)
                    <tr style="border-top:1px solid #f3eef7;">
                        <td style="padding:10px 14px;color:#1a0320;font-weight:500;">{{ $row->name }}</td>
                        <td style="padding:10px 14px;text-align:center;color:#7a6a85;">{{ $row->total }}</td>
                        <td style="padding:10px 14px;text-align:center;color:#1a7a45;font-weight:600;">{{ $row->done }}</td>
                        <td style="padding:10px 14px;text-align:center;color:{{ $row->missed > 0 ? '#b52020' : '#b0a0bb' }};font-weight:600;">{{ $row->missed }}</td>
                        <td style="padding:10px 14px;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="flex:1;height:7px;background:#f0e8f5;border-radius:4px;overflow:hidden;">
                                    <div style="height:100%;width:{{ $row->rate }}%;background:{{ $row->rate >= 80 ? '#1a7a45' : ($row->rate >= 50 ? '#a05c00' : '#b52020') }};"></div>
                                </div>
                                <span style="font-size:11.5px;color:#7a6a85;min-width:32px;text-align:right;">{{ $row->rate }}%</span>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <p style="margin-top:12px;font-size:12.5px;color:#b0a0bb;">No protocol tasks in this period.</p>
        @endif
    </div>

</div>
@endsection
