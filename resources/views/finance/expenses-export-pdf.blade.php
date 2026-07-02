<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <title>Expense Report {{ $from }} to {{ $to }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; font-size: 12px; color: #1a1a1a; background: #fff; padding: 24px; }
        h1 { font-size: 20px; color: #6a0f70; 'Inter', sans-serif; margin-bottom: 4px; }
        .sub { color: #888; font-size: 11px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        thead { background: #6a0f70; color: white; }
        th { padding: 8px 10px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        th.right { text-align: right; }
        td { padding: 7px 10px; border-bottom: 1px solid #f0e0f8; }
        td.right { text-align: right; }
        tr:hover { background: #fdf8ff; }
        .totals { background: #f9f0fb; font-weight: bold; }
        .totals td { border-top: 2px solid #6a0f70; padding-top: 10px; }
        .badge { display: inline-block; padding: 2px 7px; border-radius: 3px; font-size: 10px; }
        .paid { background: #dcfce7; color: #15803d; }
        .unpaid { background: #ffedd5; color: #c2410c; }
        .print-btn { position: fixed; top: 20px; right: 20px; background: #6a0f70; color: white;
                     padding: 8px 16px; border: none; cursor: pointer; font-size: 13px; }
        @media print { .print-btn { display: none; } }
    </style>
</head>
<body>

<button class="print-btn" onclick="window.print()">Print / Save PDF</button>

<h1>Expense Report</h1>
<p class="sub">
    Period: {{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
    &nbsp;&bull;&nbsp; Generated: {{ now()->format('d M Y, h:i A') }}
    &nbsp;&bull;&nbsp; {{ $expenses->count() }} records
</p>

<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Title</th>
            <th>Category</th>
            <th>Vendor</th>
            <th class="right">Amount</th>
            <th class="right">GST</th>
            <th class="right">Total</th>
            <th>Mode</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach($expenses as $e)
        <tr>
            <td>{{ $e->expense_date->format('d M Y') }}</td>
            <td>{{ $e->title }}</td>
            <td>{{ $e->category?->name ?? '—' }}</td>
            <td>{{ $e->vendor?->vendor_name ?? '—' }}</td>
            <td class="right">Rs. {{ number_format($e->amount, 2) }}</td>
            <td class="right">Rs. {{ number_format($e->gst_amount ?? 0, 2) }}</td>
            <td class="right">Rs. {{ number_format($e->total_amount, 2) }}</td>
            <td>{{ ucfirst(str_replace('_', ' ', $e->payment_mode ?? 'N/A')) }}</td>
            <td>
                <span class="badge {{ $e->payment_status === 'paid' ? 'paid' : 'unpaid' }}">
                    {{ ucfirst($e->payment_status) }}
                </span>
            </td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr class="totals">
            <td colspan="4">TOTAL ({{ $expenses->count() }} records)</td>
            <td class="right">Rs. {{ number_format($totals['subtotal'], 2) }}</td>
            <td class="right">Rs. {{ number_format($totals['gst'], 2) }}</td>
            <td class="right">Rs. {{ number_format($totals['total'], 2) }}</td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
</table>

</body>
</html>
