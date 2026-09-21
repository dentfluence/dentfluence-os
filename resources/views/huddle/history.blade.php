@extends('layouts.app')

@section('title', 'Huddle Log')

@section('content')
<div class="hl">

    <div class="hl-head">
        <div>
            <h1 class="hl-title">Huddle Log</h1>
            <div class="hl-sub">{{ $from->format('d M Y') }} — {{ $to->format('d M Y') }}</div>
        </div>
        <div class="hl-actions">
            @foreach([30, 60, 90] as $d)
                <a href="{{ route('huddle.history', ['days' => $d]) }}"
                   class="hl-tab {{ $days === $d ? 'active' : '' }}">{{ $d }} days</a>
            @endforeach
            <a href="{{ route('huddle.index') }}" class="hl-tab">← Today's Huddle</a>
        </div>
    </div>

    <div class="hl-strip">
        <div class="hl-stat">
            <div class="hl-stat-n">{{ $doneCount }}/{{ $expected }}</div>
            <div class="hl-stat-l">Huddles held</div>
        </div>
        <div class="hl-stat {{ $pending > 0 ? 'bad' : 'good' }}">
            <div class="hl-stat-n">{{ $pending }}</div>
            <div class="hl-stat-l">Missed working days</div>
        </div>
        <div class="hl-stat">
            <div class="hl-stat-n">{{ $expected > 0 ? round($doneCount / $expected * 100) : 0 }}%</div>
            <div class="hl-stat-l">Consistency</div>
        </div>
    </div>

    <table class="hl-table">
        <thead>
            <tr>
                <th style="width:110px;">Date</th>
                <th style="width:90px;">Day</th>
                <th style="width:120px;">Status</th>
                <th>Marked done by</th>
                <th style="width:90px;">Time</th>
                <th style="width:100px;"></th>
            </tr>
        </thead>
        <tbody>
        @foreach($rows as $row)
            @php
                $isClosedDay = ! $row->is_expected;
                $isMissed    = $row->is_expected && ! $row->is_done && ! $row->date->isToday();
            @endphp
            <tr class="{{ $isClosedDay ? 'hl-off' : '' }}">
                <td class="hl-date">
                    {{ $row->date->format('d M Y') }}
                    @if($row->date->isToday())<span class="hl-today">today</span>@endif
                </td>
                <td class="hl-muted">{{ $row->date->format('D') }}</td>
                <td>
                    @if($isClosedDay)
                        <span class="hl-pill hl-p-off">Clinic closed</span>
                    @elseif($row->is_done)
                        <span class="hl-pill hl-p-done">✓ Done</span>
                    @elseif($row->date->isToday())
                        <span class="hl-pill hl-p-open">Not yet</span>
                    @else
                        <span class="hl-pill hl-p-miss">Missed</span>
                    @endif
                </td>
                <td class="hl-muted">{{ $row->closed_by_name ?? '—' }}</td>
                <td class="hl-muted">{{ $row->closed_at ? $row->closed_at->format('H:i') : '—' }}</td>
                <td>
                    @if($row->has_snapshot)
                        <a href="{{ route('huddle.history.show', $row->date->toDateString()) }}" class="hl-link">View briefing</a>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <p class="hl-note">
        A day is only listed as missed when the clinic was actually running. Briefings exist
        from the day the tick was introduced onwards — a huddle that was never marked done
        has no briefing to show, because the board is rebuilt live and cannot be reconstructed
        for a past date.
    </p>
</div>

<style>
.hl { font-size: 13px; color: #1a1d2e; }
.hl-head { display:flex; align-items:flex-end; justify-content:space-between; gap:1rem; flex-wrap:wrap; margin-bottom:1rem; }
.hl-title { font-size:1.15rem; font-weight:700; margin:0; }
.hl-sub { font-size:.78rem; color:#6b7280; margin-top:.15rem; }
.hl-actions { display:flex; gap:.3rem; flex-wrap:wrap; }
.hl-tab { padding:.3rem .7rem; border:1px solid #e4e8f0; border-radius:6px; font-size:.78rem; color:#6b7280; text-decoration:none; background:#fff; }
.hl-tab:hover { background:#f3f4f6; color:#1a1d2e; }
.hl-tab.active { background:#4f46e5; border-color:#4f46e5; color:#fff; }

.hl-strip { display:flex; gap:.6rem; margin-bottom:1rem; flex-wrap:wrap; }
.hl-stat { background:#fff; border:1px solid #e4e8f0; border-radius:8px; padding:.55rem .9rem; min-width:130px; }
.hl-stat-n { font-size:1.2rem; font-weight:700; line-height:1.1; }
.hl-stat-l { font-size:.7rem; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; margin-top:.15rem; }
.hl-stat.bad .hl-stat-n { color:#dc2626; }
.hl-stat.good .hl-stat-n { color:#16a34a; }

.hl-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid #e4e8f0; border-radius:8px; overflow:hidden; }
.hl-table th { text-align:left; font-size:.68rem; text-transform:uppercase; letter-spacing:.06em; color:#6b7280; font-weight:600; padding:.5rem .7rem; background:#f7f8fb; border-bottom:1px solid #e4e8f0; }
.hl-table td { padding:.45rem .7rem; border-bottom:1px solid #f0f2f7; font-size:.8rem; }
.hl-table tr:last-child td { border-bottom:none; }
.hl-off td { background:#fafbfd; color:#9aa2b1; }
.hl-date { font-weight:600; }
.hl-muted { color:#6b7280; }
.hl-today { font-size:.62rem; background:#ede9fe; color:#4f46e5; border-radius:4px; padding:.05rem .3rem; margin-left:.3rem; font-weight:600; }
.hl-link { color:#4f46e5; text-decoration:none; font-size:.76rem; font-weight:600; }
.hl-link:hover { text-decoration:underline; }

.hl-pill { display:inline-block; font-size:.7rem; font-weight:600; padding:.12rem .45rem; border-radius:5px; border:1px solid transparent; }
.hl-p-done { background:#dcfce7; color:#16a34a; border-color:#bbf7d0; }
.hl-p-miss { background:#fef2f2; color:#dc2626; border-color:#fecaca; }
.hl-p-open { background:#fef3c7; color:#b45309; border-color:#fde68a; }
.hl-p-off  { background:#f3f4f6; color:#9aa2b1; border-color:#e4e8f0; }

.hl-note { font-size:.72rem; color:#9aa2b1; margin-top:.8rem; max-width:760px; line-height:1.5; }
</style>
@endsection
