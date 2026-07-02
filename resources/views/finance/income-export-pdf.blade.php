<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Income Report — {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { 'Inter', sans-serif; font-size: 12px; color: #1a1a2e; }
    .page { max-width: 960px; margin: 0 auto; padding: 30px; }
    .header { border-bottom: 2px solid #6a0f70; padding-bottom: 16px; margin-bottom: 20px; }
    .header h1 { font-size: 22px; color: #6a0f70; }
    .header p  { font-size: 11px; color: #666; margin-top: 4px; }
    .kpi-row { display: flex; gap: 16px; margin-bottom: 20px; }
    .kpi-box { flex: 1; border: 1px solid #e8d5f0; padding: 10px 14px; }
    .kpi-box .label { font-size: 10px; text-transform: uppercase; color: #888; letter-spacing: .04em; }
    .kpi-box .val   { font-size: 18px; font-weight: 700; color: #6a0f70; margin-top: 2px; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    thead th { background: #f9f4fb; border-bottom: 1px solid #d8b4e2; padding: 8px 10px;
               font-size: 10px; text-transform: uppercase; letter-spacing: .04em; text-align: left; color: #555; }
    tbody td { padding: 7px 10px; border-bottom: 1px solid #f3eafa; font-size: 11px; }
    tbody tr:last-child td { border-bottom: none; }
    .totals td { font-weight: 700; background: #f9f4fb; border-top: 2px solid #d8b4e2; }
    .badge { display: inline-block; padding: 1px 7px; border-radius: 9999px; font-size: 10px; }
    .badge-paid    { background: #dcfce7; color: #166534; }
    .badge-partial { background: #fef9c3; color: #854d0e; }
    .badge-draft   { background: #f3f4f6; color: #374151; }
    .text-right { text-align: right; }
    .footer { margin-top: 24px; font-size: 10px; color: #aaa; text-align: right; }
    @media print {
        body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        .no-print { display: none; }
    }
</style>
</head>
<body>
<div class="page">

    <div class="header">
        <h1>Income Report</h1>
        <p>Period: {{ $from->format('d M Y') }} — {{ $to->format('d M Y') }} &nbsp;|&nbsp;
           Generated: {{ now()->format('d M Y, h:i A') }}</p>
    </div>

    <div class="kpi-row">
        <div class="kpi-box">
            <div class="label">Total Payments</div>
            <div class="val">{{ $totals['count'] }}</div>
        </div>
        <div class="kpi-box">
            <div class="label">Total Collected</div>
            <div class="val">Rs. {{ number_format($totals['amount'], 0) }}</div>
        </div>
        <div class="kpi-box">
            <div class="label">Total Balance Due</div>
            <div class="val">Rs. {{ number_format($totals['balance'], 0) }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Patient</th>
                <th>Invoice</th>
                <th>Treatment</th>
                <th class="text-right">Invoice Total</th>
                <th class="text-right">Paid</th>
                <th class="text-right">Balance</th>
                <th>Mode</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $i => $p)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $p->payment_date?->format('d M Y') }}</td>
                <td>
                    {{ $p->invoice?->patient?->name ?? '—' }}<br>
                    <span style="color:#888;font-size:10px;">{{ $p->invoice?->patient?->phone ?? '' }}</span>
                </td>
                <td style="font-family:monospace;font-size:10px;">{{ $p->invoice?->invoice_number ?? '—' }}</td>
                <td style="font-size:10px;">{{ $p->invoice?->items?->pluck('treatment_name')->filter()->implode(', ') ?? '—' }}</td>
                <td class="text-right">Rs. {{ number_format($p->invoice?->total_amount ?? 0, 0) }}</td>
                <td class="text-right" style="font-weight:600;">Rs. {{ number_format($p->amount, 0) }}</td>
                <td class="text-right">Rs. {{ number_format($p->invoice?->balance_due ?? 0, 0) }}</td>
                <td style="text-transform:capitalize;">{{ $p->payment_mode ?? '—' }}</td>
                <td>
                    @php $st = $p->invoice?->status ?? ''; @endphp
                    <span class="badge badge-{{ $st === 'paid' ? 'paid' : ($st === 'partial' ? 'partial' : 'draft') }}">
                        {{ ucfirst($st ?: '—') }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="totals">
                <td colspan="5" style="text-align:right;">TOTALS</td>
                <td class="text-right">Rs. {{ number_format($payments->sum(fn($p) => $p->invoice?->total_amount ?? 0), 0) }}</td>
                <td class="text-right">Rs. {{ number_format($totals['amount'], 0) }}</td>
                <td class="text-right">Rs. {{ number_format($totals['balance'], 0) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">Dentfluence ERP &nbsp;|&nbsp; Confidential — Finance Report</div>

    <div class="no-print" style="margin-top:20px;text-align:center;">
        <button onclick="window.print()"
                style="background:#6a0f70;color:#fff;padding:8px 24px;border:none;cursor:pointer;font-size:13px;">
            Print / Save as PDF
        </button>
    </div>
</div>
</body>
</html>
