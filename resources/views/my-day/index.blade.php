{{--
    MY DAY — one list, top to bottom.

    Deliberately NOT a dashboard. Five panels would be the same scatter with
    more scrolling and still no order. One column, worked downwards, and when
    it is empty the day's admin is done.

    Nothing here is a second source of truth: every row points at a record
    that lives in a module and is changed there.
--}}
@extends('layouts.app')
@section('page-title', 'My Day')

@section('content')
<div style="font-family:'Inter',sans-serif;padding:22px 28px;max-width:820px;">

    <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:4px;">
        <h1 style="font-family:'Cormorant Garamond',serif;font-size:26px;font-weight:700;color:#1a0320;margin:0;">
            My Day
        </h1>
        <span style="font-size:13px;color:#9a7aaa;">{{ today()->format('l, d F') }}</span>
    </div>
    <p style="font-size:12.5px;color:#9a7aaa;margin:0 0 20px;">
        {{ auth()->user()->name }} ·
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
                <span style="font-size:11.5px;color:#9a7aaa;">{{ count($band['rows']) }}</span>
            </div>

            @foreach($band['rows'] as $row)
                {{-- The whole row is the link. A staff member reaching for a
                     small chevron with one hand while holding a file is how
                     a list stops getting used. --}}
                <a href="{{ $row['url'] }}"
                   style="display:flex;align-items:flex-start;gap:12px;text-decoration:none;padding:11px 13px;margin-bottom:6px;background:#fff;border:1.5px solid {{ $row['urgent'] ? '#f0d5d5' : '#ede4f3' }};border-left:3px solid {{ $row['urgent'] ? '#b52020' : '#d9c7e4' }};border-radius:9px;">

                    {{-- The kind of work, as a word. Icons would need learning;
                         four short words do not. --}}
                    <span style="flex:0 0 58px;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#9a7aaa;padding-top:2px;">
                        @switch($row['kind'])
                            @case('call')        Call @break
                            @case('task')        Task @break
                            @case('lab')         Lab @break
                            @case('stock')       Stock @break
                            @case('appointment') Diary @break
                            @default {{ $row['kind'] }}
                        @endswitch
                    </span>

                    <span style="flex:1 1 auto;min-width:0;">
                        {{-- The verb leads. "Send lab case DF-104", not
                             "DF-104" — you should know what to DO without
                             reading the second line. --}}
                        <span style="display:block;font-size:14px;font-weight:600;color:#1a0320;">
                            {{ $row['do'] }}
                        </span>
                        @if($row['who'] || $row['note'])
                            <span style="display:block;font-size:12px;color:#7a6088;margin-top:2px;">
                                @if($row['who'])<strong style="font-weight:600;">{{ $row['who'] }}</strong>@endif
                                @if($row['who'] && $row['note']) · @endif
                                @if($row['note'])<span style="color:{{ $row['urgent'] ? '#b52020' : '#9a7aaa' }};">{{ $row['note'] }}</span>@endif
                            </span>
                        @endif
                    </span>

                    <span style="flex:0 0 auto;color:#c5b0d5;font-size:16px;line-height:1.2;">&rsaquo;</span>
                </a>
            @endforeach
        </div>
    @endforeach

    @if($total > 0)
    <p style="font-size:11.5px;color:#b0a0bb;margin:18px 0 0;line-height:1.7;">
        The order of the day is set once for the whole clinic in
        <code style="font-size:11px;background:#faf7fc;padding:1px 5px;border-radius:4px;">config/my_day.php</code>,
        so it is the same every day for everyone. Opening a row takes you to the
        module that owns it — nothing is finished from this page yet.
    </p>
    @endif

</div>
@endsection
