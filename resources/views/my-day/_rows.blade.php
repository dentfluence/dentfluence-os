{{--
    MY DAY — the summary rows, rendered as the SAME TABLE the calls board uses.

    ── WHY (CEO ruling, 23 Sep) ────────────────────────────────────────────────
    "keep all ui same like calls… UI is different for lab and calls."

    He is right, and the reason is not decoration. Calls came from the real
    board and rendered as a dense table; lab and payments were My Day's
    own card rows. One page, two visual languages, so the eye re-learns how to
    read halfway down — which is the scatter this page exists to end.

    These rows reuse the board's own classes (.taw-wrap, .taw-t, .taw-pr,
    .taw-do, .taw-why, .taw-tag, .taw-st), so they are not "styled to match" —
    they ARE the same styles. The stylesheet is already on the page, pushed by
    the calls board.

    Expects: $rows (MyDayQueue rows).
--}}
<div class="taw-wrap">
    <table class="taw-t">
        <thead>
            <tr>
                <th style="width:78px;">Priority</th>
                <th style="width:190px;">Who</th>
                <th>What to do</th>
                <th style="width:110px;">Type</th>
                <th style="width:104px;">Status</th>
                <th style="width:150px;"></th>
            </tr>
        </thead>
        <tbody>
        @foreach($rows as $row)
            @php
                // Two levels, not three. A summary row is either late enough to
                // shout about or it is not; inventing a middle band would be a
                // judgement this page has no basis for.
                $pr = $row['urgent'] ? 'high' : 'low';

                $type = match ($row['kind']) {
                    'call'        => 'Call',
                    'task'        => 'Task',
                    'lab'         => 'Lab',
                    'payment'     => 'Payment',
                    'stock'       => 'Stock',
                    'appointment' => 'Diary',
                    default       => ucfirst($row['kind']),
                };
            @endphp
            <tr>
                {{-- 1 · PRIORITY --}}
                <td>
                    <span class="taw-pr taw-pr--{{ $pr }}">
                        <span class="taw-dot"></span>{{ $row['urgent'] ? 'High' : 'Normal' }}
                    </span>
                </td>

                {{-- 2 · WHO — patient, vendor, or nobody (a bill has no person) --}}
                <td>
                    @if($row['who'])
                        <div class="taw-name" title="{{ $row['who'] }}">{{ $row['who'] }}</div>
                    @else
                        <span class="taw-due--none">—</span>
                    @endif
                </td>

                {{-- 3 · WHAT TO DO — the verb leads, the detail sits under it,
                       exactly as "Call for" does on the calls board. --}}
                <td>
                    <a href="{{ $row['url'] }}" style="text-decoration:none;color:inherit;">
                        <div class="taw-do" title="{{ $row['do'] }}">{{ $row['do'] }}</div>
                        @if($row['note'])
                            <span class="taw-why {{ $row['urgent'] ? 'taw-why--try' : '' }}"
                                  title="{{ $row['note'] }}">{{ $row['note'] }}</span>
                        @endif
                    </a>
                </td>

                {{-- 4 · TYPE --}}
                <td><span class="taw-tag">{{ $type }}</span></td>

                {{-- 5 · STATUS --}}
                <td>
                    <span class="taw-st {{ $row['urgent'] ? 'taw-st--over' : 'taw-st--open' }}">
                        {{ $row['urgent'] ? 'Overdue' : 'Open' }}
                    </span>
                </td>

                {{-- 6 · THE ONE BUTTON, where there is one. Posts to the
                       module's own endpoint — see MyDayQueue::action(). --}}
                <td>
                    <div class="taw-acts">
                        @if(!empty($row['action']))
                            <form method="POST" action="{{ $row['action']['url'] }}" style="margin:0;"
                                  @if(!empty($row['action']['confirm'])) onsubmit="return confirm('{{ $row['action']['confirm'] }}')" @endif>
                                @csrf
                                <button type="submit"
                                        style="padding:4px 10px;font-size:11px;font-weight:600;color:#6a0f70;background:#faf6fc;border:1px solid #e2d6ea;border-radius:6px;cursor:pointer;font-family:inherit;white-space:nowrap;">
                                    {{ $row['action']['label'] }}
                                </button>
                            </form>
                        @endif
                        <a href="{{ $row['url'] }}" title="Open"
                           style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:24px;border:1px solid #e2d6ea;border-radius:6px;color:#8a6f92;text-decoration:none;">
                            &rsaquo;
                        </a>
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
