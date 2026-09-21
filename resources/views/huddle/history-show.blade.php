@extends('layouts.app')

@section('title', 'Huddle — ' . $day->format('d M Y'))

@php
    $hsColour = function ($hex) {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $hex) ? $hex : '#94a3b8';
    };
@endphp

@section('content')
<div class="hs">

    <div class="hs-head">
        <div>
            <h1 class="hs-title">Daily Huddle</h1>
            <div class="hs-sub">{{ $day->format('l, d F Y') }}</div>
        </div>
        <a href="{{ route('huddle.history') }}" class="hs-back">← Huddle Log</a>
    </div>

    <div class="hs-meta">
        @if($board?->is_locked)
            <span class="hs-tick">✓ Huddle held</span>
            marked done by <strong>{{ $closedBy ?? '—' }}</strong>
            @if($board->locked_at) at {{ $board->locked_at->format('H:i') }} @endif
        @else
            <span class="hs-tick miss">Huddle not marked done</span>
            the day's record below is still complete
        @endif
    </div>

    {{-- ── That day's record: read back from dated rows ───────────────────── --}}
    <div class="hs-section-label">
        That day's record
        <span class="hs-tag">from dated records</span>
    </div>

    <div class="hs-strip">
        <div class="hs-stat"><div class="hs-stat-n">{{ $record['counts']['booked'] }}</div><div class="hs-stat-l">Appointments</div></div>
        <div class="hs-stat"><div class="hs-stat-n">{{ $record['counts']['done'] }}</div><div class="hs-stat-l">Completed</div></div>
        <div class="hs-stat"><div class="hs-stat-n">{{ $record['counts']['cancelled'] + $record['counts']['no_show'] }}</div><div class="hs-stat-l">Cancelled / no-show</div></div>
        <div class="hs-stat"><div class="hs-stat-n">{{ $record['counts']['visits'] }}</div><div class="hs-stat-l">Treatment visits</div></div>
        <div class="hs-stat"><div class="hs-stat-n">{{ $record['counts']['consultations'] }}</div><div class="hs-stat-l">Consultations</div></div>
        <div class="hs-stat"><div class="hs-stat-n">₹{{ number_format($record['collected'], 0) }}</div><div class="hs-stat-l">Collected ({{ $record['collection_events'] }})</div></div>
    </div>

    @if($record['appointments']->isNotEmpty())
        <table class="hs-table">
            <thead>
                <tr>
                    <th style="width:70px;">Time</th>
                    <th>Patient</th>
                    <th style="width:180px;">Doctor</th>
                    <th>Treatment</th>
                    <th style="width:120px;">Status</th>
                </tr>
            </thead>
            <tbody>
            @foreach($record['appointments'] as $a)
                @php $hex = $hsColour($a->doctor_color); @endphp
                <tr style="border-left:3px solid {{ $hex }};">
                    <td class="hs-muted">{{ $a->appointment_time ? \Carbon\Carbon::parse($a->appointment_time)->format('H:i') : '—' }}</td>
                    <td>
                        <a href="{{ route('patients.show', $a->patient_id) }}" class="hs-name">{{ $a->patient_name }}</a>
                        @if($a->is_walkin)<span class="hs-chip">walk-in</span>@endif
                    </td>
                    <td>
                        <span class="hs-dot" style="background:{{ $hex }};"></span>
                        {{ $a->doctor_name ?? 'Unassigned' }}
                    </td>
                    <td class="hs-muted">{{ $a->treatment_name ?? ($a->type ? ucfirst(str_replace('_',' ', $a->type)) : '—') }}</td>
                    <td>
                        <span class="hs-pill hs-p-{{ $a->status }}">{{ ucfirst(str_replace('_',' ', $a->status)) }}</span>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <div class="hs-none">No appointments booked on this date.</div>
    @endif

    @if($record['visits']->isNotEmpty())
        <div class="hs-section-label">Treatment visits ({{ $record['visits']->count() }})</div>
        <table class="hs-table">
            <tbody>
            @foreach($record['visits'] as $v)
                @php $hex = $hsColour($v->doctor_color); @endphp
                <tr style="border-left:3px solid {{ $hex }};">
                    <td><a href="{{ route('patients.show', $v->patient_id) }}" class="hs-name">{{ $v->patient_name }}</a></td>
                    <td style="width:180px;"><span class="hs-dot" style="background:{{ $hex }};"></span>{{ $v->doctor_name ?? 'Unassigned' }}</td>
                    <td class="hs-muted">{{ $v->treatment_name ?? ucfirst(str_replace('_',' ', (string) $v->visit_type)) }}</td>
                    <td style="width:120px;"><span class="hs-pill hs-p-done">{{ ucfirst(str_replace('_',' ', (string) $v->status)) }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if($record['consultations']->isNotEmpty())
        <div class="hs-section-label">Consultations ({{ $record['consultations']->count() }})</div>
        <table class="hs-table">
            <tbody>
            @foreach($record['consultations'] as $c)
                @php $hex = $hsColour($c->doctor_color); @endphp
                <tr style="border-left:3px solid {{ $hex }};">
                    <td><a href="{{ route('patients.show', $c->patient_id) }}" class="hs-name">{{ $c->patient_name }}</a></td>
                    <td style="width:180px;"><span class="hs-dot" style="background:{{ $hex }};"></span>{{ $c->doctor_name ?? 'Unassigned' }}</td>
                    <td class="hs-muted">{{ $c->chief_complaint ?: ucfirst(str_replace('_',' ', (string) $c->visit_type)) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    {{-- ── The frozen briefing, only for days that were ticked ─────────────── --}}
    <div class="hs-section-label">
        Morning briefing
        <span class="hs-tag">{{ $snapshot ? 'frozen at tick — not recalculated' : 'not available' }}</span>
    </div>

    @if($snapshot)
        <div class="hs-grid">
            @foreach($snapshot['sections'] ?? [] as $section)
                <div class="hs-card">
                    <div class="hs-card-head">
                        <span class="hs-card-title">{{ $section['title'] }}</span>
                        @if(!empty($section['headline']))
                            <span class="hs-card-headline">{{ $section['headline'] }}</span>
                        @endif
                    </div>
                    <ul class="hs-lines">
                        @foreach($section['lines'] ?? [] as $line)
                            <li>{{ ltrim($line, '- ') }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @else
        <div class="hs-none">
            The huddle was not marked done on this date, so no briefing was frozen.
            Stock, tasks, the lab queue and the call pipeline hold no date of their own —
            rebuilding them now would show today's position under {{ $day->format('d M Y') }}.
            The record above is the part of the day that can be read back truthfully.
        </div>
    @endif
</div>

<style>
.hs { font-size:13px; color:#1a1d2e; }
.hs-head { display:flex; align-items:flex-end; justify-content:space-between; gap:1rem; margin-bottom:.7rem; }
.hs-title { font-size:1.15rem; font-weight:700; margin:0; }
.hs-sub { font-size:.78rem; color:#6b7280; margin-top:.15rem; }
.hs-back { font-size:.78rem; color:#4f46e5; text-decoration:none; font-weight:600; }

.hs-meta { font-size:.76rem; color:#6b7280; background:#fff; border:1px solid #e4e8f0; border-radius:8px; padding:.5rem .8rem; margin-bottom:1rem; }
.hs-tick { display:inline-block; font-size:.7rem; font-weight:700; background:#dcfce7; color:#16a34a; border:1px solid #bbf7d0; border-radius:5px; padding:.1rem .4rem; margin-right:.4rem; }
.hs-tick.miss { background:#fef2f2; color:#dc2626; border-color:#fecaca; }

.hs-section-label { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:#6b7280; margin:1.2rem 0 .45rem; display:flex; align-items:center; gap:.5rem; }
.hs-tag { font-size:.62rem; font-weight:600; text-transform:none; letter-spacing:0; background:#f3f4f6; color:#9aa2b1; border-radius:5px; padding:.08rem .38rem; }

.hs-strip { display:flex; gap:.5rem; flex-wrap:wrap; }
.hs-stat { background:#fff; border:1px solid #e4e8f0; border-radius:8px; padding:.5rem .8rem; min-width:105px; }
.hs-stat-n { font-size:1.1rem; font-weight:700; line-height:1.15; }
.hs-stat-l { font-size:.66rem; color:#6b7280; text-transform:uppercase; letter-spacing:.04em; margin-top:.1rem; }

.hs-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid #e4e8f0; border-radius:8px; overflow:hidden; margin-top:.5rem; }
.hs-table th { text-align:left; font-size:.66rem; text-transform:uppercase; letter-spacing:.06em; color:#6b7280; font-weight:600; padding:.45rem .7rem; background:#f7f8fb; border-bottom:1px solid #e4e8f0; }
.hs-table td { padding:.42rem .7rem; border-bottom:1px solid #f0f2f7; font-size:.8rem; vertical-align:middle; }
.hs-table tr:last-child td { border-bottom:none; }
.hs-name { color:#1a1d2e; font-weight:600; text-decoration:none; }
.hs-name:hover { color:#4f46e5; }
.hs-muted { color:#6b7280; }
.hs-dot { display:inline-block; width:7px; height:7px; border-radius:50%; margin-right:.35rem; vertical-align:middle; }
.hs-chip { font-size:.62rem; background:#f3f4f6; color:#6b7280; border-radius:4px; padding:.05rem .3rem; margin-left:.3rem; }

.hs-pill { display:inline-block; font-size:.68rem; font-weight:600; padding:.1rem .4rem; border-radius:5px; border:1px solid transparent; background:#f3f4f6; color:#6b7280; }
.hs-p-done { background:#dcfce7; color:#16a34a; border-color:#bbf7d0; }
.hs-p-cancelled, .hs-p-no_show { background:#fef2f2; color:#dc2626; border-color:#fecaca; }
.hs-p-scheduled { background:#eff6ff; color:#2563eb; border-color:#bfdbfe; }

.hs-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:.7rem; }
.hs-card { background:#fff; border:1px solid #e4e8f0; border-radius:8px; padding:.6rem .8rem; }
.hs-card-head { display:flex; align-items:baseline; justify-content:space-between; gap:.5rem; border-bottom:1px solid #f0f2f7; padding-bottom:.35rem; margin-bottom:.4rem; }
.hs-card-title { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
.hs-card-headline { font-size:.72rem; color:#4f46e5; font-weight:600; white-space:nowrap; }
.hs-lines { margin:0; padding-left:1rem; }
.hs-lines li { font-size:.8rem; line-height:1.55; }

.hs-none { background:#fff; border:1px solid #e4e8f0; border-radius:8px; padding:.8rem; font-size:.8rem; line-height:1.6; color:#6b7280; max-width:720px; }
</style>
@endsection
