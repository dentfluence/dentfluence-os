<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <title>Wallet Transaction Register</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; font-size: 11px; color: #333; padding: 20px; }
        h1 { font-size: 16px; color: #6a0f70; margin-bottom: 4px; }
        .sub { font-size: 10px; color: #777; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        thead th {
            background: #6a0f70; color: white;
            padding: 6px 8px; text-align: left; font-size: 10px;
        }
        tbody tr:nth-child(even) { background: #f9f4fa; }
        tbody td { padding: 5px 8px; border-bottom: 1px solid #eee; vertical-align: top; }
        .credit { color: #16a34a; font-weight: bold; }
        .debit  { color: #dc2626; font-weight: bold; }
        .tfoot td { background: #f3f4f6; font-weight: bold; padding: 6px 8px; }
        @media print {
            body { padding: 10px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <h1>Wallet Transaction Register</h1>
    <div class="sub">
        Generated: {{ now()->format('d M Y, h:i A') }}
        @if(request()->filled('from') || request()->filled('to'))
            &nbsp;·&nbsp; Period: {{ request('from', '—') }} to {{ request('to', '—') }}
        @endif
        &nbsp;·&nbsp; {{ $transactions->count() }} transactions
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Patient</th>
                <th>Phone</th>
                <th style="text-align:right">Credit (Rs. )</th>
                <th style="text-align:right">Debit (Rs. )</th>
                <th>Type</th>
                <th>Source</th>
                <th>Invoice No</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $tx)
            <tr>
                <td>{{ $tx->created_at->format('d-m-Y') }}</td>
                <td>{{ $tx->patient?->name ?? '—' }}</td>
                <td>{{ $tx->patient?->phone ?? '' }}</td>
                <td style="text-align:right" class="credit">
                    {{ $tx->direction === 'credit' ? 'Rs. '.number_format($tx->amount, 2) : '' }}
                </td>
                <td style="text-align:right" class="debit">
                    {{ $tx->direction === 'debit' ? 'Rs. '.number_format($tx->amount, 2) : '' }}
                </td>
                <td>{{ ucfirst($tx->credit_type ?? '') }}{{ $tx->campaign_name ? ' — '.$tx->campaign_name : '' }}</td>
                <td>{{ ucwords(str_replace('_', ' ', $tx->source ?? '')) }}</td>
                <td>{{ $tx->invoice_number ?? $tx->invoice?->invoice_number ?? '' }}</td>
                <td>{{ $tx->notes ?? '' }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="tfoot">
                <td colspan="3">TOTAL</td>
                <td style="text-align:right">Rs. {{ number_format($transactions->where('direction','credit')->sum('amount'), 2) }}</td>
                <td style="text-align:right">Rs. {{ number_format($transactions->where('direction','debit')->sum('amount'), 2) }}</td>
                <td colspan="4"></td>
            </tr>
        </tfoot>
    </table>

    <script>window.onload = () => window.print();</script>
</body>
</html>
