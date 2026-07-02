<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<meta charset="UTF-8">
<title>Finance Report — {{ ucfirst($tab) }}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { 'Inter', sans-serif; font-size: 11px; color: #1a1a1a; background: #fff; }
  .page { padding: 24px 28px; }
  .header { border-bottom: 2px solid #6a0f70; padding-bottom: 10px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: flex-end; }
  .header h1 { font-size: 18px; color: #6a0f70; font-weight: 700; letter-spacing: 0.03em; }
  .header .sub { font-size: 10px; color: #777; }
  .meta { display: flex; gap: 24px; margin-bottom: 14px; }
  .meta span { font-size: 10px; color: #555; }
  .meta strong { color: #1a1a1a; }
  .kpi-row { display: flex; gap: 12px; margin-bottom: 16px; }
  .kpi { flex: 1; border: 1px solid #e5e7eb; padding: 8px 12px; }
  .kpi .label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.07em; color: #888; margin-bottom: 3px; }
  .kpi .value { font-size: 16px; font-weight: 700; }
  .value.green { color: #16a34a; }
  .value.red   { color: #dc2626; }
  .value.purple{ color: #6a0f70; }
  .value.orange{ color: #d97706; }
  .section-title { font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: #6a0f70; font-weight: 700; border-bottom: 1px solid #e8d5f0; padding-bottom: 4px; margin-bottom: 8px; margin-top: 16px; }
  table { width: 100%; border-collapse: collapse; font-size: 10px; }
  thead tr { background: #f5f3ff; }
  th { padding: 5px 8px; text-align: left; font-weight: 600; color: #4b5563; font-size: 9px; text-transform: uppercase; letter-spacing: 0.04em; }
  th.r, td.r { text-align: right; }
  td { padding: 4px 8px; border-bottom: 1px solid #f3f4f6; }
  tr:hover td { background: #fafafa; }
  tfoot tr td { font-weight: 700; background: #f9fafb; border-top: 2px solid #e5e7eb; }
  .badge { display: inline-block; padding: 1px 5px; border-radius: 2px; font-size: 9px; font-weight: 600; }
  .badge-red { background: #fee2e2; color: #b91c1c; }
  .badge-amber { background: #fef3c7; color: #b45309; }
  .row-warn td { background: #fff7ed; }
  .row-danger td { background: #fef2f2; }
  .footer { margin-top: 20px; border-top: 1px solid #e5e7eb; padding-top: 8px; font-size: 9px; color: #aaa; text-align: right; }
  @media print {
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .no-print { display: none !important; }
  }
</style>
</head>
<body>
<div class="page">

  {{-- HEADER --}}
  <div class="header">
    <div>
      <h1>Finance Report — {{ ucfirst($tab) }}</h1>
      <div class="sub">Dentfluence Dental Clinic Management</div>
    </div>
    <div class="sub" style="text-align:right;">
      Generated: {{ now()->format('d M Y, H:i') }}<br>
      Period: {{ ($from instanceof \Carbon\Carbon) ? $from->format('d M Y') : $from }}
      &mdash;
      {{ ($to instanceof \Carbon\Carbon) ? $to->format('d M Y') : $to }}
    </div>
  </div>

  {{-- ── INCOME SUMMARY ── --}}
  @if($tab === 'income')
  <div class="kpi-row">
    <div class="kpi">
      <div class="label">Total Collected</div>
      <div class="value green">&#8377;{{ number_format($data['total'], 0) }}</div>
    </div>
    <div class="kpi">
      <div class="label">Transactions</div>
      <div class="value">{{ $data['byMonth']->sum('cnt') }}</div>
    </div>
  </div>

  <div class="section-title">Monthly Breakdown</div>
  <table>
    <thead><tr><th>Month</th><th class="r">Txns</th><th class="r">Amount (&#8377;)</th></tr></thead>
    <tbody>
    @foreach($data['byMonth'] as $m)
    <tr><td>{{ $m->month }}</td><td class="r">{{ $m->cnt }}</td><td class="r">{{ number_format($m->total, 0) }}</td></tr>
    @endforeach
    </tbody>
    <tfoot><tr><td>Total</td><td class="r">{{ $data['byMonth']->sum('cnt') }}</td><td class="r">{{ number_format($data['total'], 0) }}</td></tr></tfoot>
  </table>

  <div class="section-title">By Payment Mode</div>
  <table>
    <thead><tr><th>Mode</th><th class="r">Count</th><th class="r">Amount (&#8377;)</th></tr></thead>
    <tbody>
    @foreach($data['byMode'] as $m)
    <tr><td>{{ ucfirst($m->payment_mode) }}</td><td class="r">{{ $m->cnt }}</td><td class="r">{{ number_format($m->total, 0) }}</td></tr>
    @endforeach
    </tbody>
  </table>

  <div class="section-title">Top Patients by Revenue</div>
  <table>
    <thead><tr><th>#</th><th>Patient</th><th>Phone</th><th class="r">Invoices</th><th class="r">Total Paid (&#8377;)</th></tr></thead>
    <tbody>
    @foreach($data['topPatients'] as $i => $p)
    <tr><td>{{ $i+1 }}</td><td>{{ $p->name }}</td><td>{{ $p->phone }}</td><td class="r">{{ $p->invoices }}</td><td class="r">{{ number_format($p->total_paid, 0) }}</td></tr>
    @endforeach
    </tbody>
  </table>

  {{-- ── EXPENSE SUMMARY ── --}}
  @elseif($tab === 'expense')
  <div class="kpi-row">
    <div class="kpi">
      <div class="label">Total Expenses</div>
      <div class="value red">&#8377;{{ number_format($data['total'], 0) }}</div>
    </div>
    <div class="kpi">
      <div class="label">Categories</div>
      <div class="value">{{ $data['byCategory']->count() }}</div>
    </div>
  </div>

  <div class="section-title">By Category</div>
  <table>
    <thead><tr><th>Category</th><th class="r">Count</th><th class="r">Total (&#8377;)</th></tr></thead>
    <tbody>
    @foreach($data['byCategory'] as $c)
    <tr><td>{{ $c->category }}</td><td class="r">{{ $c->cnt }}</td><td class="r">{{ number_format($c->total, 0) }}</td></tr>
    @endforeach
    </tbody>
  </table>

  <div class="section-title">Top Vendors</div>
  <table>
    <thead><tr><th>Vendor</th><th class="r">Bills</th><th class="r">Total (&#8377;)</th></tr></thead>
    <tbody>
    @foreach($data['topVendors'] as $v)
    <tr><td>{{ $v->vendor_name }}</td><td class="r">{{ $v->cnt }}</td><td class="r">{{ number_format($v->total, 0) }}</td></tr>
    @endforeach
    </tbody>
  </table>

  {{-- ── RECEIVABLES ── --}}
  @elseif($tab === 'receivables')
  <div class="kpi-row">
    <div class="kpi"><div class="label">Outstanding</div><div class="value orange">&#8377;{{ number_format($data['total'], 0) }}</div></div>
    <div class="kpi"><div class="label">Over 30 Days</div><div class="value red">&#8377;{{ number_format($data['over30'], 0) }}</div></div>
    <div class="kpi"><div class="label">Over 90 Days</div><div class="value red">&#8377;{{ number_format($data['over90'], 0) }}</div></div>
  </div>

  <div class="section-title">Outstanding Invoices</div>
  <table>
    <thead><tr><th>Invoice No</th><th>Patient</th><th>Date</th><th class="r">Total</th><th class="r">Balance (&#8377;)</th><th class="r">Age</th></tr></thead>
    <tbody>
    @foreach($data['invoices'] as $inv)
    <tr class="{{ $inv->age_days > 90 ? 'row-danger' : ($inv->age_days > 30 ? 'row-warn' : '') }}">
      <td>{{ $inv->invoice_number }}</td>
      <td>{{ $inv->patient?->name }}</td>
      <td>{{ $inv->invoice_date?->format('d-m-Y') }}</td>
      <td class="r">{{ number_format($inv->total_amount, 0) }}</td>
      <td class="r"><strong>{{ number_format($inv->balance_due, 0) }}</strong></td>
      <td class="r">{{ $inv->age_days }}d
        @if($inv->age_days > 90) <span class="badge badge-red">90+</span>
        @elseif($inv->age_days > 30) <span class="badge badge-amber">30+</span>@endif
      </td>
    </tr>
    @endforeach
    </tbody>
  </table>

  {{-- ── PAYABLES ── --}}
  @elseif($tab === 'payables')
  <div class="kpi-row">
    <div class="kpi"><div class="label">Total Payable</div><div class="value orange">&#8377;{{ number_format($data['total'], 0) }}</div></div>
    <div class="kpi"><div class="label">Overdue</div><div class="value red">&#8377;{{ number_format($data['overdue'], 0) }}</div></div>
    <div class="kpi"><div class="label">Over 30 Days</div><div class="value red">&#8377;{{ number_format($data['over30'], 0) }}</div></div>
  </div>

  <div class="section-title">Outstanding Bills</div>
  <table>
    <thead><tr><th>Title</th><th>Vendor</th><th>Date</th><th>Due</th><th class="r">Amount (&#8377;)</th><th class="r">Age</th></tr></thead>
    <tbody>
    @foreach($data['bills'] as $b)
    <tr class="{{ $b->overdue ? 'row-danger' : '' }}">
      <td>{{ $b->title }}</td>
      <td>{{ $b->vendor?->vendor_name ?? '—' }}</td>
      <td>{{ $b->expense_date?->format('d-m-Y') }}</td>
      <td>{{ $b->due_date ? $b->due_date->format('d-m-Y') : '—' }}
        @if($b->overdue) <span class="badge badge-red">Overdue</span>@endif
      </td>
      <td class="r"><strong>{{ number_format($b->total_amount, 0) }}</strong></td>
      <td class="r">{{ $b->age_days }}d</td>
    </tr>
    @endforeach
    </tbody>
  </table>

  {{-- ── MEMBERSHIP ── --}}
  @elseif($tab === 'membership')
  <div class="kpi-row">
    <div class="kpi"><div class="label">Revenue</div><div class="value purple">&#8377;{{ number_format($data['total'], 0) }}</div></div>
    <div class="kpi"><div class="label">New Subscriptions</div><div class="value">{{ $data['subscriptions']->count() }}</div></div>
    <div class="kpi"><div class="label">Active</div><div class="value green">{{ $data['active'] }}</div></div>
    <div class="kpi"><div class="label">Expired</div><div class="value">{{ $data['expired'] }}</div></div>
  </div>

  <div class="section-title">Revenue by Plan</div>
  <table>
    <thead><tr><th>Plan</th><th class="r">Count</th><th class="r">Revenue (&#8377;)</th></tr></thead>
    <tbody>
    @foreach($data['byPlan'] as $plan => $stats)
    <tr><td>{{ $plan }}</td><td class="r">{{ $stats['count'] }}</td><td class="r">{{ number_format($stats['revenue'], 0) }}</td></tr>
    @endforeach
    </tbody>
  </table>

  <div class="section-title">Subscriptions in Period</div>
  <table>
    <thead><tr><th>Date</th><th>Patient</th><th>Plan</th><th class="r">Amount (&#8377;)</th></tr></thead>
    <tbody>
    @foreach($data['subscriptions'] as $s)
    <tr>
      <td>{{ $s->created_at?->format('d-m-Y') }}</td>
      <td>{{ $s->patient?->name }}</td>
      <td>{{ $s->plan?->name }}</td>
      <td class="r">{{ number_format($s->amount_paid, 0) }}</td>
    </tr>
    @endforeach
    </tbody>
  </table>

  {{-- ── WALLET ── --}}
  @elseif($tab === 'wallet')
  <div class="kpi-row">
    <div class="kpi"><div class="label">Credits Issued</div><div class="value green">&#8377;{{ number_format($data['credits'], 0) }}</div></div>
    <div class="kpi"><div class="label">Utilized</div><div class="value red">&#8377;{{ number_format($data['debits'], 0) }}</div></div>
    <div class="kpi"><div class="label">Outstanding Balance</div><div class="value purple">&#8377;{{ number_format($data['outstanding'], 0) }}</div></div>
    <div class="kpi"><div class="label">Patients w/ Balance</div><div class="value">{{ $data['patients'] }}</div></div>
  </div>

  <div class="section-title">Monthly Wallet Activity</div>
  @php $months = $data['monthly']->groupBy('month'); @endphp
  <table>
    <thead><tr><th>Month</th><th class="r">Credits (&#8377;)</th><th class="r">Debits (&#8377;)</th></tr></thead>
    <tbody>
    @foreach($months as $month => $rows)
    <tr>
      <td>{{ $month }}</td>
      <td class="r" style="color:#16a34a;">{{ number_format($rows->where('direction','credit')->sum('total'), 0) }}</td>
      <td class="r" style="color:#dc2626;">{{ number_format($rows->where('direction','debit')->sum('total'), 0) }}</td>
    </tr>
    @endforeach
    </tbody>
  </table>

  {{-- ── COUPONS ── --}}
  @elseif($tab === 'coupon')
  <div class="kpi-row">
    <div class="kpi"><div class="label">Total Uses</div><div class="value purple">{{ $data['totalUsed'] }}</div></div>
    <div class="kpi"><div class="label">Total Discount</div><div class="value orange">&#8377;{{ number_format($data['totalDiscount'], 0) }}</div></div>
    <div class="kpi"><div class="label">Coupons Used</div><div class="value">{{ $data['byCoupon']->where('used_count', '>', 0)->count() }}</div></div>
  </div>

  <div class="section-title">Coupon Performance</div>
  <table>
    <thead><tr><th>Coupon Code</th><th>Type</th><th class="r">Uses</th><th class="r">Total Discount (&#8377;)</th></tr></thead>
    <tbody>
    @foreach($data['byCoupon']->where('used_count', '>', 0) as $c)
    <tr>
      <td><strong>{{ $c->code }}</strong></td>
      <td>{{ ucfirst($c->discount_type ?? '') }}</td>
      <td class="r">{{ $c->used_count }}</td>
      <td class="r">{{ number_format($c->total_discount ?? 0, 0) }}</td>
    </tr>
    @endforeach
    </tbody>
  </table>
  @endif

  <div class="footer">Dentfluence &mdash; Confidential &mdash; {{ now()->format('d M Y') }}</div>
</div>

<script>
window.onload = function() { window.print(); };
</script>
</body>
</html>
