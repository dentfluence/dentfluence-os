<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width">
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; font-size: 14px; color: #1e293b; background: #f8fafc; margin: 0; padding: 16px; }
  .wrap { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; }
  .top-bar { background: #6a0f70; color: #fff; padding: 14px 20px; }
  .top-bar h1 { margin: 0; font-size: 16px; font-weight: 700; }
  .top-bar .sub { font-size: 12px; opacity: .8; margin-top: 2px; }
  .body { padding: 20px; }
  .greeting { font-size: 15px; font-weight: 600; margin-bottom: 4px; }
  .summary { font-size: 13px; color: #64748b; margin-bottom: 20px; }

  .section-head { font-size: 11px; font-weight: 700; color: #6a0f70; text-transform: uppercase; letter-spacing: .07em; margin: 18px 0 8px; border-top: 1px solid #f1f5f9; padding-top: 12px; }
  .section-head:first-of-type { border-top: none; padding-top: 0; }

  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th { text-align: left; font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; padding: 5px 8px; background: #f8fafc; }
  td { padding: 9px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
  tr:last-child td { border-bottom: none; }

  .badge { display: inline-block; font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 8px; }
  .badge-high    { background: #fff1f2; color: #dc2626; }
  .badge-medium  { background: #fffbeb; color: #d97706; }
  .badge-low     { background: #f1f5f9; color: #64748b; }
  .badge-breached{ background: #fff1f2; color: #dc2626; }

  .alert-box { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 6px; padding: 10px 14px; font-size: 13px; color: #ea580c; font-weight: 600; margin-bottom: 16px; }
  .footer { background: #f8fafc; padding: 12px 20px; font-size: 11px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; }
  .tip { font-size: 12px; color: #94a3b8; font-style: italic; margin-top: 14px; }
  .empty { color: #94a3b8; font-size: 13px; padding: 8px 0; }
</style>
</head>
<body>
<div class="wrap">
  <div class="top-bar">
    <h1>Morning Briefing — {{ $date }}</h1>
    <div class="sub">Dentfluence · Communication OS</div>
  </div>
  <div class="body">

    <div class="greeting">Good morning, {{ $staffName }}</div>
    <div class="summary">You have <strong>{{ $totalToday }} call{{ $totalToday !== 1 ? 's' : '' }}</strong> scheduled for today.</div>

    @if($highValueCount > 0)
    <div class="alert-box">
      {{ $highValueCount }} high-value lead{{ $highValueCount > 1 ? 's' : '' }} (Rs. 30,000+) need your attention first.
    </div>
    @endif

    {{-- Today's Queue --}}
    <div class="section-head">Today's Queue ({{ $totalToday }})</div>
    @if(count($todayQueue) > 0)
    <table>
      <tr>
        <th>Name</th>
        <th>Phone</th>
        <th>Priority</th>
        <th>Status</th>
      </tr>
      @foreach($todayQueue as $item)
      <tr>
        <td><strong>{{ $item['person_name'] }}</strong></td>
        <td>{{ $item['phone'] ?: '—' }}</td>
        <td>
          <span class="badge badge-{{ $item['priority'] }}">
            {{ strtoupper($item['priority']) }}
          </span>
          @if($item['sla_breached'])
          <span class="badge badge-breached" style="margin-left:3px;">SLA</span>
          @endif
        </td>
        <td>{{ ucwords(str_replace('_', ' ', $item['status'])) }}</td>
      </tr>
      @endforeach
    </table>
    @else
    <div class="empty">No items due today — check if anything was carried over.</div>
    @endif

    {{-- Overdue --}}
    @if(count($overdueItems) > 0)
    <div class="section-head">Overdue ({{ count($overdueItems) }})</div>
    <table>
      <tr>
        <th>Name</th>
        <th>Follow-up Was</th>
        <th>Attempts</th>
      </tr>
      @foreach($overdueItems as $item)
      <tr>
        <td><strong>{{ $item['person_name'] }}</strong></td>
        <td style="color:#dc2626;">
          {{ $item['follow_up_date'] ? \Carbon\Carbon::parse($item['follow_up_date'])->format('d M') : '—' }}
        </td>
        <td>{{ $item['attempt_count'] }}</td>
      </tr>
      @endforeach
    </table>
    @endif

    <div class="tip">Log every call, even missed ones. It keeps the record clean for the whole team.</div>
  </div>
  <div class="footer">Dentfluence · {{ now()->format('d M Y') }} · This is an automated briefing.</div>
</div>
</body>
</html>
