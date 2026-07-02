<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width">
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; font-size: 14px; color: #1e293b; background: #f8fafc; margin: 0; padding: 16px; }
  .wrap { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; }
  .top-bar { background: #1e293b; color: #fff; padding: 14px 20px; }
  .top-bar h1 { margin: 0; font-size: 16px; font-weight: 700; }
  .top-bar .sub { font-size: 12px; opacity: .7; margin-top: 2px; }
  .body { padding: 20px; }
  .greeting { font-size: 15px; font-weight: 600; margin-bottom: 16px; }

  .stats-row { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
  .stat { flex: 1; min-width: 90px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; text-align: center; }
  .stat .num { font-size: 24px; font-weight: 800; }
  .stat .lbl { font-size: 10px; color: #94a3b8; margin-top: 2px; text-transform: uppercase; letter-spacing: .05em; }
  .stat--green .num  { color: #16a34a; }
  .stat--purple .num { color: #6a0f70; }
  .stat--red .num    { color: #dc2626; }
  .stat--blue .num   { color: #2563eb; }

  .section-head { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .07em; margin: 18px 0 8px; border-top: 1px solid #f1f5f9; padding-top: 12px; }

  table { width: 100%; border-collapse: collapse; font-size: 12px; }
  th { text-align: left; font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; padding: 5px 8px; background: #f8fafc; }
  td { padding: 8px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
  tr:last-child td { border-bottom: none; }

  .badge { display: inline-block; font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 8px; }
  .badge-overdue  { background: #fff1f2; color: #dc2626; }
  .badge-pending  { background: #fffbeb; color: #d97706; }
  .badge-waiting  { background: #eff6ff; color: #2563eb; }

  .result-good  { color: #16a34a; font-weight: 700; }
  .result-warn  { color: #d97706; font-weight: 700; }
  .result-bad   { color: #dc2626; font-weight: 700; }

  .cta { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; font-size: 13px; margin-top: 16px; }
  .empty { color: #94a3b8; font-size: 13px; padding: 6px 0; }
  .footer { background: #f8fafc; padding: 12px 20px; font-size: 11px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; }
</style>
</head>
<body>
<div class="wrap">
  <div class="top-bar">
    <h1>Evening Summary — {{ $date }}</h1>
    <div class="sub">Dentfluence · End of Day Report</div>
  </div>
  <div class="body">

    <div class="greeting">Here's how your day went, {{ $staffName }}.</div>

    <div class="stats-row">
      <div class="stat stat--green">
        <div class="num">{{ $doneTodayCount }}</div>
        <div class="lbl">Done</div>
      </div>
      <div class="stat stat--purple">
        <div class="num">{{ $attemptsMade }}</div>
        <div class="lbl">Attempts</div>
      </div>
      <div class="stat stat--blue">
        <div class="num">{{ $wonToday }}</div>
        <div class="lbl">Won</div>
      </div>
      <div class="stat {{ $overdueCount > 0 ? 'stat--red' : 'stat--green' }}">
        <div class="num">{{ $overdueCount }}</div>
        <div class="lbl">Overdue</div>
      </div>
      <div class="stat">
        <div class="num" style="color:#374151">{{ $openCount }}</div>
        <div class="lbl">Still Open</div>
      </div>
    </div>

    {{-- Open items --}}
    @if(count($openItems) > 0)
    <div class="section-head">Still Open ({{ $openCount }})</div>
    <table>
      <tr>
        <th>Name</th>
        <th>Status</th>
        <th>Follow-up</th>
      </tr>
      @foreach($openItems as $item)
      <tr>
        <td><strong>{{ $item['person_name'] }}</strong></td>
        <td>
          <span class="badge badge-{{ str_replace('_for_patient','',$item['status']) }}">
            {{ ucwords(str_replace('_', ' ', $item['status'])) }}
          </span>
        </td>
        <td style="font-size:11px;color:#64748b;">
          {{ $item['follow_up_date'] ? \Carbon\Carbon::parse($item['follow_up_date'])->format('d M') : '—' }}
        </td>
      </tr>
      @endforeach
    </table>
    @if($openCount > 8)
    <div style="font-size:11px;color:#94a3b8;margin-top:6px;">...and {{ $openCount - 8 }} more.</div>
    @endif
    @else
    <div class="empty">Nothing open — great work today!</div>
    @endif

    @if($overdueCount > 0)
    <div class="cta">
      You have <strong class="result-bad">{{ $overdueCount }} overdue item{{ $overdueCount > 1 ? 's' : '' }}</strong>.
      These will appear at the top of tomorrow's briefing.
    </div>
    @endif

    {{-- Manager: team summary --}}
    @if($isManager && count($teamSummary) > 0)
    <div class="section-head">Team Summary</div>
    <table>
      <tr>
        <th>Staff</th>
        <th>Open</th>
        <th>Overdue</th>
        <th>SLA Breaches</th>
      </tr>
      @foreach($teamSummary as $ts)
      <tr>
        <td><strong>{{ $ts['assigned_to'] }}</strong></td>
        <td>{{ $ts['open'] }}</td>
        <td class="{{ $ts['overdue'] > 0 ? 'result-warn' : '' }}">{{ $ts['overdue'] }}</td>
        <td class="{{ $ts['breached'] > 0 ? 'result-bad' : '' }}">{{ $ts['breached'] }}</td>
      </tr>
      @endforeach
    </table>
    <div style="font-size:12px;margin-top:10px;color:#64748b;">
      Team total today: <strong>{{ $teamWon }}</strong> leads won.
      @if($teamBreaches > 0)
        <span class="result-bad">{{ $teamBreaches }} SLA breach{{ $teamBreaches > 1 ? 'es' : '' }} still open.</span>
      @endif
    </div>
    @endif

  </div>
  <div class="footer">Dentfluence · Automated 6PM Summary · {{ now()->format('d M Y') }}</div>
</div>
</body>
</html>
