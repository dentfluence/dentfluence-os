{{--
    MY DAY — one list, top to bottom.

    Deliberately NOT a dashboard. Five panels would be the same scatter with
    more scrolling and still no order. One column, worked downwards, and when
    it is empty the day's admin is done.

    Nothing here is a second source of truth: every row points at a record
    that lives in a module and is changed there.

    23 Sep — SOME BANDS NOW HOST A REAL BOARD. A summary row that links out
    was honest and useless: you lost your place, and nothing could be closed
    from here. Where a band declares a board in config/my_day.php it renders
    the module's own board, drawer and all, so the work is finished where it
    is listed. Only ONE board per page — see tasks/_board.blade.php.
--}}
@extends('layouts.app')
@section('page-title', 'My Day')

@section('content')
<div style="font-family:'Inter',sans-serif;padding:22px 28px;">

    <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:4px;">
        <h1 style="font-family:'Cormorant Garamond',serif;font-size:26px;font-weight:700;color:#1a0320;margin:0;">
            My Day
        </h1>
        <span style="font-size:13px;color:#9a7aaa;">{{ today()->format('l, d F') }}</span>
    </div>
    <p style="font-size:12.5px;color:#9a7aaa;margin:0 0 20px;">
        {{ auth()->user()->name }} ·
        @if(session('success') || session('error'))
        @php $isErr = (bool) session('error'); @endphp
        <div style="margin-bottom:16px;padding:10px 14px;border-radius:9px;font-size:13px;
                    background:{{ $isErr ? '#fdeaea' : '#eef8f1' }};
                    border:1.5px solid {{ $isErr ? '#f0d5d5' : '#cfe8d8' }};
                    color:{{ $isErr ? '#b52020' : '#1d7a3c' }};">
            {{ session('error') ?: session('success') }}
        </div>
    @endif

    @if($total === 0)
            nothing outstanding
        @else
            {{ $total }} {{ Str::plural('thing', $total) }} to do
        @endif
    </p>

    @if($total === 0)
        {{-- Said like a finish line, not like an error. An empty list is the
             goal of the page, and it should feel like one. --}}
        <div style="padding:44px 26px;text-align:center;border:1.5px dashed #ede4f3;border-radius:12px;background:#fdfcfe;">
            <div style="font-size:15px;font-weight:600;color:#1d7a3c;margin-bottom:5px;">You're clear.</div>
            <div style="font-size:13px;color:#9a7aaa;">
                No calls, tasks, lab cases or stock needing you right now.
            </div>
        </div>
    @endif

    @if($total > 0)
    @foreach($bands as $band)
        <div style="margin-bottom:22px;">
            <div style="display:flex;align-items:baseline;gap:9px;margin-bottom:9px;">
                <span style="font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#6a0f70;">
                    {{ $band['label'] }}
                </span>
                @if($band['hint'])
                    <span style="font-size:11.5px;color:#c5b0d5;">{{ $band['hint'] }}</span>
                @endif
                <span style="flex:1 1 auto;border-bottom:1px solid #f0e9f5;"></span>
                {{-- The board's rows count towards the band. A heading reading
                     "3" above eleven visible things is worse than no number. --}}
                @php
                    $bandCount = count($band['rows']);
                    foreach ($band['boards'] ?? [] as $bk) {
                        $bandCount += match ($bk) {
                            'calls' => (int) ($boards['calls']['totalCount'] ?? 0),
                            'tasks' => ($boards['tasks']['tasks'] ?? null)?->total() ?? 0,
                            default => 0,
                        };
                    }
                @endphp
                <span style="font-size:11.5px;color:#9a7aaa;">{{ $bandCount }}</span>
            </div>

            {{-- The ONE thing the board's own footer carried that is worth
                 keeping: people with call debt and nothing due today. It is a
                 line on this page's band, not a strip of bookkeeping under an
                 embedded board. --}}
            @if(in_array('calls', $band['boards'] ?? [], true) && ($boards['calls']['pendingCount'] ?? 0) > 0)
                <a href="{{ route('relationship.today.pending') }}"
                   style="display:inline-block;margin:-4px 0 9px;font-size:12px;color:#b52020;text-decoration:none;">
                    {{ $boards['calls']['pendingCount'] }} {{ Str::plural('patient', $boards['calls']['pendingCount']) }}
                    with overdue calls and nothing today &rarr;
                </a>
            @endif


            {{-- The module's own boards, inline and ABOVE this band's summary
                 rows — a board is the work, a row is a pointer at it. Not a
                 preview: the same rows and the same drawer, so a call is
                 logged and a task closed here without leaving the page and
                 losing your place in the list. --}}
            @foreach($band['boards'] ?? [] as $boardKey)
                @if(isset(($boards ?? [])[$boardKey]))
                    <div style="margin-bottom:18px;">
                        @switch($boardKey)
                            @case('calls')
                                @include('relationship.today._board', array_merge($boards['calls'], ['compact' => true]))
                                @break
                            @case('tasks')
                                @include('tasks._board', array_merge($boards['tasks'], ['compact' => true]))
                                @break
                        @endswitch
                    </div>
                @endif
            @endforeach
            @if(!empty($band['rows']))
                @include('my-day._rows', ['rows' => $band['rows']])
            @endif
        </div>
    @endforeach
    @endif

    @if($total > 0)
    <p style="font-size:11.5px;color:#b0a0bb;margin:18px 0 0;line-height:1.7;">
        The order of the day is set once for the whole clinic in
        <code style="font-size:11px;background:#faf7fc;padding:1px 5px;border-radius:4px;">config/my_day.php</code>,
        so it is the same every day for everyone. Calls and tasks are worked
        here, on the boards themselves; the remaining rows still open the
        module that owns them.
    </p>
    @endif

</div>
@endsection
