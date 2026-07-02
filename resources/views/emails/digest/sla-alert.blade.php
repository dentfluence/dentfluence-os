<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width">
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; font-size: 14px; color: #1e293b; background: #f8fafc; margin: 0; padding: 16px; }
  .wrap { max-width: 620px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; }
  .top-bar { background: #dc2626; color: #fff; padding: 14px 20px; }
  .top-bar h1 { margin: 0; font-size: 16px; font-weight: 700; }
  .top-bar .sub { font-size: 12px; opacity: .85; margin-top: 2px; }
  .body { padding: 20px; }

  .summary-row { display: flex; gap: 16px; margin-bottom: 20px; }
  .stat { flex: 1; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; text-align: center; }
  .stat .num { font-size: 28px; font-weight: 800; color: #dc2626; }
  .stat .lbl { font-size: 11px; color: #64748b; margin-top: 2px; }

  .section-head { font-size: 11px; font-weight: 700; color: #dc2626; text-transform: uppercase; letter-spacing: .07em; margin: 20px 0 8px; }

  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th { text-align: left; font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; padding: 5px 8px; background: #f8fafc; }
  td { padding: 9px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
  tr:last-child td { border-bottom: none; }

  .badge { display: inline-block; font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 8px; }
  .badge-high   { background: #fff1f2; color: #dc2626; }
  .badge-medium { background: #fffbeb; color: #d97706; }
  .badge-low    { background: #f1f5f9; color: #64748b; }

  .value { font-weight: 700; color: #6a0f70; }
  .empty { color: #94a3b8; font-size: 13px; padding: 8px 0; }
  .footer { background: #f8fafc; padding: 12px 20px; font-size: 11px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; }
</style>
</head>
<body>
<div class="wrap">
  <div class="top-bar">
    <h1>2PM SLA Alert</h1>
    <div class="sub">{{ $date }}</div>
  </div>
  <div class="body">

    <div class="summary-row">
      <div class="stat">
        <div class="num">{{ $totalBreached }}</div>
        <div class="lbl">SLA Breaches Open</div>
      </div>
      <div class="stat">
        <div class="num" style="color:{{ count($highValueLeads) > 0 ? '#dc2626' : '#16a34a' }}">{{ count($highValueLeads) }}</div>
        <div class="lbl">High-Value Leads At Risk</div>
      </div>
    </div>

    {{-- SLA Breaches --}}
    <div class="section-head">SLA Breaches ({{ $totalBreached }})</div>
    @if(count($breachedComms) > 0)
    <table>
      <tr>
        <th>Name</th>
        <th>Phone</th>
        <th>Assigned To</th>
        <th>Priority</th>
        <th>Attempts</th>
        <th>Value (Rs. )</th>
      </tr>
      @foreach($breachedComms as $item)
      <tr>
        <td><strong>{{ $item['person_name'] }}</strong></td>
        <td>{{ $item['phone'] ?: '—' }}</td>
        <td>{{ $item['assigned_to'] ?: 'Unassigned' }}</td>
        <td><span class="badge badge-{{ $item['priority'] }}">{{ strtoupper($item['priority']) }}</span></td>
        <td>{{ $item['attempt_count'] }}</td>
        <td class="value">
          {{ $item['opportunity_value'] ? 'Rs. '.number_format($item['opportunity_value']) : '—' }}
        </td>
      </tr>
      @endforeach
    </table>
    @else
    <div class="empty">No open SLA breaches right now.</div>
    @endif

    {{-- High-value leads at risk --}}
    @if(count($highValueLeads) > 0)
    <div class="section-head">High-Value Leads Not Contacted (Rs. 30k+)</div>
    <table>
      <tr>
        <th>Name</th>
        <th>Phone</th>
        <th>Value</th>
        <th>Assigned To</th>
        <th>Source</th>
        <th>Last Updated</th>
      </tr>
      @foreach($highValueLeads as $lead)
      <tr>
        <td><strong>{{ $lead['name'] }}</strong></td>
        <td>{{ $lead['phone'] ?: '—' }}</td>
        <td class="value">Rs. {{ number_format($lead['lead_value']) }}</td>
        <td>{{ $lead['assigned_to'] ?: 'Unassigned' }}</td>
        <td>{{ $lead['lead_source'] ?? '—' }}</td>
        <td style="color:#dc2626;font-size:12px;">
          {{ $lead['updated_at'] ? \Carbon\Carbon::parse($lead['updated_at'])->diffForHumans() : 'Never' }}
        </td>
      </tr>
      @endforeach
    </table>
    @endif

  </div>
  <div class="footer">Dentfluence · Automated 2PM Alert · Action required before 6PM.</div>
</div>
</body>
</html>
