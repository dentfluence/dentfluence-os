{{--
    Tasks > Accountability — one table, one definitions block, no scores.

    Every number on this page is a count that can be traced back to rows in
    task_outcomes. There is no percentage and no ranking anywhere, on purpose:
    see App\Services\Tasks\TaskAccountabilityReport for the reasoning.
--}}
@extends('layouts.app')
@section('page-title', 'Task Accountability')

@section('content')
<div style="font-family:'Inter',sans-serif;padding:22px 28px;max-width:1150px;">

    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:6px;">
        <h1 style="font-family:'Cormorant Garamond',serif;font-size:25px;font-weight:700;color:#1a0320;margin:0;">Task Accountability</h1>
        <a href="{{ route('tasks.index') }}" style="font-size:13px;color:#6a0f70;text-decoration:none;">&larr; Back to tasks</a>
    </div>
    <p style="font-size:12.5px;color:#9a7aaa;margin:0 0 18px;">
        {{ $from->format('d M Y') }} &ndash; {{ $to->format('d M Y') }}.
        Activity is what happened in these dates. Open and overdue are where things stand right now.
    </p>

    {{-- ── range ─────────────────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('tasks.accountability') }}"
          style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap;margin-bottom:18px;padding:12px 14px;background:#faf7fc;border:1.5px solid #ede4f3;border-radius:9px;">
        <div>
            <label style="font-size:11px;font-weight:600;color:#6a0f70;display:block;margin-bottom:4px;">From</label>
            <input type="date" name="from" value="{{ $from->toDateString() }}"
                   style="padding:7px 10px;border:1.5px solid #ddd;border-radius:6px;font-size:12.5px;font-family:inherit;">
        </div>
        <div>
            <label style="font-size:11px;font-weight:600;color:#6a0f70;display:block;margin-bottom:4px;">To</label>
            <input type="date" name="to" value="{{ $to->toDateString() }}"
                   style="padding:7px 10px;border:1.5px solid #ddd;border-radius:6px;font-size:12.5px;font-family:inherit;">
        </div>
        <button type="submit"
                style="padding:8px 16px;background:#6a0f70;color:#fff;border:none;border-radius:6px;font-size:12.5px;font-weight:600;font-family:inherit;cursor:pointer;">Apply</button>

        <div style="display:flex;gap:6px;margin-left:auto;">
            @foreach($presets as $label => $range)
                <a href="{{ route('tasks.accountability', $range) }}"
                   style="padding:7px 12px;border:1.5px solid #ede4f3;border-radius:6px;font-size:12px;font-weight:600;color:#7a6088;text-decoration:none;background:#fff;">{{ $label }}</a>
            @endforeach
        </div>
    </form>

    {{-- ── per-person ────────────────────────────────────────────────────── --}}
    @if(empty($staff))
        <div style="padding:26px;text-align:center;border:1.5px dashed #ede4f3;border-radius:10px;color:#b0a0bb;font-size:13px;">
            Nothing was logged in these dates, and nobody has an open task.
        </div>
    @else
        <div style="border:1.5px solid #ede4f3;border-radius:10px;overflow:hidden;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="background:#faf7fc;color:#9a7aaa;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;">
                        <th style="text-align:left;padding:10px 16px;font-weight:600;">Staff</th>
                        <th style="text-align:center;padding:10px 8px;font-weight:600;" title="Outcome rows logged as done in this range">Done</th>
                        <th style="text-align:center;padding:10px 8px;font-weight:600;" title="Worked on but not finished — the task stayed open">Attempted</th>
                        <th style="text-align:center;padding:10px 8px;font-weight:600;" title="Moved to a later date">Moved</th>
                        <th style="text-align:center;padding:10px 8px;font-weight:600;" title="Closed without being done">Cancelled</th>
                        <th style="text-align:center;padding:10px 8px;font-weight:600;" title="Finished after the date it was originally due">Slipped</th>
                        <th style="text-align:center;padding:10px 8px;font-weight:600;border-left:1.5px solid #ede4f3;" title="Right now, not in this date range">Open now</th>
                        <th style="text-align:center;padding:10px 16px;font-weight:600;" title="Right now, against the original due date">Overdue now</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($staff as $row)
                    <tr style="border-top:1px solid #f3eef7;">
                        <td style="padding:10px 16px;font-weight:600;color:#1a0320;">{{ $row['name'] }}</td>
                        <td style="padding:10px 8px;text-align:center;color:#1d7a3c;font-weight:600;">{{ $row['done'] ?: '—' }}</td>
                        <td style="padding:10px 8px;text-align:center;color:#a05c00;">{{ $row['attempted'] ?: '—' }}</td>
                        <td style="padding:10px 8px;text-align:center;color:#7a6088;">{{ $row['rescheduled'] ?: '—' }}</td>
                        <td style="padding:10px 8px;text-align:center;color:#9a7aaa;">{{ $row['cancelled'] ?: '—' }}</td>
                        <td style="padding:10px 8px;text-align:center;color:{{ $row['slipped'] ? '#b52020' : '#c5b0d5' }};">{{ $row['slipped'] ?: '—' }}</td>
                        <td style="padding:10px 8px;text-align:center;border-left:1.5px solid #f3eef7;color:#1a0320;">{{ $row['open'] ?: '—' }}</td>
                        <td style="padding:10px 16px;text-align:center;font-weight:{{ $row['overdue'] ? '700' : '400' }};color:{{ $row['overdue'] ? '#b52020' : '#c5b0d5' }};">{{ $row['overdue'] ?: '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr style="border-top:1.5px solid #ede4f3;background:#faf7fc;font-weight:700;color:#1a0320;">
                        <td style="padding:10px 16px;">Clinic</td>
                        <td style="padding:10px 8px;text-align:center;">{{ $totals['done'] }}</td>
                        <td style="padding:10px 8px;text-align:center;">{{ $totals['attempted'] }}</td>
                        <td style="padding:10px 8px;text-align:center;">{{ $totals['rescheduled'] }}</td>
                        <td style="padding:10px 8px;text-align:center;">{{ $totals['cancelled'] }}</td>
                        <td style="padding:10px 8px;text-align:center;">{{ $totals['slipped'] }}</td>
                        <td style="padding:10px 8px;text-align:center;border-left:1.5px solid #ede4f3;">{{ $totals['open'] }}</td>
                        <td style="padding:10px 16px;text-align:center;">{{ $totals['overdue'] }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    {{-- ── what is getting in the way ────────────────────────────────────── --}}
    <h2 style="font-family:'Cormorant Garamond',serif;font-size:19px;font-weight:700;color:#1a0320;margin:24px 0 4px;">What stopped the work</h2>
    <p style="font-size:12px;color:#9a7aaa;margin:0 0 12px;">
        Reasons recorded on attempts — work that did not finish. Completed outcomes are not counted here.
    </p>

    @if(empty($reasons))
        <p style="font-size:12.5px;color:#b0a0bb;margin:0;">No attempts were logged with a reason in these dates.</p>
    @else
        <div style="border:1.5px solid #ede4f3;border-radius:10px;overflow:hidden;">
            @php $top = $reasons[0]['count'] ?: 1; @endphp
            @foreach($reasons as $r)
                <div style="display:flex;align-items:center;gap:12px;padding:9px 16px;border-top:{{ $loop->first ? 'none' : '1px solid #f3eef7' }};">
                    <span style="font-size:12.5px;color:#1a0320;min-width:220px;">{{ $r['label'] }}</span>
                    <div style="flex:1;height:7px;background:#f3eef7;border-radius:4px;overflow:hidden;">
                        <div style="width:{{ round($r['count'] / $top * 100) }}%;height:100%;background:#d9a7de;"></div>
                    </div>
                    <span style="font-size:12.5px;font-weight:700;color:#6a0f70;min-width:34px;text-align:right;">{{ $r['count'] }}</span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ── definitions ───────────────────────────────────────────────────────
         Printed on the page, not hidden in a tooltip. A number whose
         definition is not visible next to it gets misread, and a misread
         accountability number becomes an unfair conversation with a staff
         member. --}}
    <div style="margin-top:24px;padding:14px 16px;background:#faf7fc;border:1.5px solid #ede4f3;border-radius:9px;">
        <div style="font-size:12.5px;font-weight:700;color:#1a0320;margin-bottom:8px;">How to read this</div>
        <ul style="margin:0;padding-left:18px;font-size:12px;color:#7a6088;line-height:1.75;">
            <li><strong>Done / Attempted / Moved / Cancelled</strong> — counted from the outcome trail, credited to whoever logged the entry, within the selected dates.</li>
            <li><strong>Attempted</strong> means the work happened but the task stayed open, e.g. a call that did not connect. It is not a failure; a high attempted count next to a low done count usually means the patients are not picking up, not that the staff member is slow.</li>
            <li><strong>Slipped</strong> — finished, but after the date it was <em>originally</em> due. Rescheduling does not erase this.</li>
            <li><strong>Open now / Overdue now</strong> — today's position, by whoever the task is assigned to. These two ignore the date range.</li>
            <li>There are no percentages here on purpose. Forty recall calls and four lab chases are not the same work, and one number cannot compare them.</li>
        </ul>
    </div>

</div>
@endsection
