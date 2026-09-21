@extends('layouts.app')

@section('title', 'Huddle — ' . $day->format('d M Y'))

@section('content')
<div class="hs">

    <div class="hs-head">
        <div>
            <h1 class="hs-title">Daily Huddle</h1>
            <div class="hs-sub">{{ $day->format('l, d F Y') }}</div>
        </div>
        <a href="{{ route('huddle.history') }}" class="hs-back">← Huddle Log</a>
    </div>

    @if($snapshot)
        <div class="hs-meta">
            Marked done by <strong>{{ $closedBy ?? '—' }}</strong>
            @if($board?->locked_at) at {{ $board->locked_at->format('H:i, d M Y') }} @endif
            <span class="hs-frozen">frozen snapshot — not recalculated</span>
        </div>

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
        <div class="hs-empty">
            <strong>No briefing for this day.</strong>
            The huddle was never marked done, so nothing was frozen. The board is rebuilt
            live from current data every time it loads, so this day cannot be reconstructed
            after the fact — showing it would put today's stock, tasks and lab queue under
            {{ $day->format('d M Y') }}.
        </div>
    @endif
</div>

<style>
.hs { font-size:13px; color:#1a1d2e; }
.hs-head { display:flex; align-items:flex-end; justify-content:space-between; gap:1rem; margin-bottom:.8rem; }
.hs-title { font-size:1.15rem; font-weight:700; margin:0; }
.hs-sub { font-size:.78rem; color:#6b7280; margin-top:.15rem; }
.hs-back { font-size:.78rem; color:#4f46e5; text-decoration:none; font-weight:600; }
.hs-meta { font-size:.76rem; color:#6b7280; background:#fff; border:1px solid #e4e8f0; border-radius:8px; padding:.5rem .8rem; margin-bottom:.9rem; }
.hs-frozen { display:inline-block; margin-left:.5rem; font-size:.66rem; text-transform:uppercase; letter-spacing:.05em; background:#f3f4f6; color:#6b7280; border-radius:5px; padding:.1rem .4rem; font-weight:600; }
.hs-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:.7rem; }
.hs-card { background:#fff; border:1px solid #e4e8f0; border-radius:8px; padding:.6rem .8rem; }
.hs-card-head { display:flex; align-items:baseline; justify-content:space-between; gap:.5rem; border-bottom:1px solid #f0f2f7; padding-bottom:.35rem; margin-bottom:.4rem; }
.hs-card-title { font-size:.74rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
.hs-card-headline { font-size:.72rem; color:#4f46e5; font-weight:600; white-space:nowrap; }
.hs-lines { margin:0; padding-left:1rem; }
.hs-lines li { font-size:.8rem; line-height:1.55; }
.hs-empty { background:#fff; border:1px solid #e4e8f0; border-radius:8px; padding:1rem; font-size:.82rem; line-height:1.6; color:#6b7280; max-width:680px; }
.hs-empty strong { display:block; color:#1a1d2e; margin-bottom:.25rem; }
</style>
@endsection
