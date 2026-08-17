<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt {{ $receipt->receipt_number }}</title>
    <script>
    (function () {
        var C = {
            'default': ['#6a0f70','#3a0050','#f9f3fa'],
            'blue':    ['#1558b0','#0d3d80','#f0f5ff'],
            'teal':    ['#0d7a6a','#095a4e','#f0faf8'],
            'green':   ['#1a7a45','#0f5030','#f0faf4'],
            'rose':    ['#b52058','#821040','#fff0f5']
        };
        var key = 'default';
        try { key = (JSON.parse(localStorage.getItem('df_prefs') || '{}').color) || 'default'; } catch (e) {}
        var s = C[key] || C['default'], r = document.documentElement.style;
        r.setProperty('--df-color-primary', s[0]);
        r.setProperty('--df-color-hover',   s[1]);
        r.setProperty('--df-color-light',   s[2]);
    })();
    </script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            font-size: 13px; color: #1a1a1a; background: #fff;
            padding: 32px; max-width: 640px; margin: 0 auto;
        }
        .print-actions { display: flex; gap: 10px; margin-bottom: 24px; }
        .btn { padding: 8px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; }
        .btn-print { background: var(--df-color-primary, #6a0f70); color: #fff; }
        .btn-close { background: #f1f5f9; color: #475569; }
        .rcp-header { display: flex; justify-content: space-between; align-items: flex-start;
            padding-bottom: 16px; border-bottom: 2px solid var(--df-color-primary, #6a0f70); margin-bottom: 20px; }
        .clinic-name { font-size: 17px; font-weight: 700; color: var(--df-color-primary, #6a0f70); }
        .clinic-meta { font-size: 11px; color: #64748b; line-height: 1.6; margin-top: 4px; }
        .rcp-title { text-align: right; }
        .rcp-title h1 { font-size: 15px; letter-spacing: 1px; color: #1e293b; }
        .rcp-title p { font-size: 11px; color: #64748b; margin-top: 2px; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 18px; }
        .meta-box { background: #f8fafc; border-radius: 8px; padding: 10px 14px; }
        .meta-box .label { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #94a3b8; }
        .meta-box .value { font-size: 13px; font-weight: 600; color: #1e293b; margin-top: 2px; }
        .meta-box .sub { font-size: 11px; color: #64748b; }
        .amount-block { background: var(--df-color-light, #f9f3fa); border-radius: 10px;
            padding: 18px; text-align: center; margin-bottom: 18px; }
        .amount-block .lbl { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #64748b; }
        .amount-block .amt { font-size: 28px; font-weight: 700; color: var(--df-color-primary, #6a0f70); margin: 4px 0; }
        .amount-block .mode { font-size: 11px; color: #64748b; letter-spacing: .5px; }
        .alloc { border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; margin-bottom: 18px; }
        .alloc .sh { background: #f8fafc; padding: 9px 14px; font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .5px; color: #475569; border-bottom: 1px solid #e2e8f0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 9px 14px; font-size: 12px; text-align: left; border-bottom: 1px solid #f1f5f9; }
        th { font-size: 10px; text-transform: uppercase; letter-spacing: .4px; color: #94a3b8; font-weight: 600; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        tr.credit td { background: #fffbeb; color: #92400e; font-weight: 600; }
        tr.total td { background: #f8fafc; font-weight: 700; color: #1e293b; border-bottom: none; }
        .rcp-footer { margin-top: 22px; padding-top: 14px; border-top: 1px solid #e2e8f0;
            font-size: 11px; color: #94a3b8; text-align: center; }
        @media print { .print-actions { display: none; } body { padding: 0; } }
    </style>
</head>
<body>

@php
    // This view serves BOTH patient-level receipt kinds, because both are
    // documents that belong to the patient rather than to one invoice:
    //   PAY- : one tender allocated across N invoices (has a breakdown)
    //   ADV- : money received against no invoice at all (no breakdown)
    $isAdvance = $receipt->receipt_kind === 'advance';
    $b         = $receipt->allocation_breakdown ?? [];
    $lines     = $b['invoices'] ?? [];
    $credit    = $isAdvance ? (float) $receipt->amount : (float) ($b['patient_credit'] ?? 0);
    $settled   = (float) ($b['settled'] ?? 0);
    $outAfter  = (float) ($b['outstanding_after'] ?? $receipt->balance_after);
@endphp

<div class="print-actions">
    <button class="btn btn-print" onclick="window.print()">Print</button>
    <button class="btn btn-close" onclick="window.close()">Close</button>
</div>

<div class="rcp-header">
    <div>
        <div class="clinic-name">{{ $clinic['clinic_name'] ?? config('app.name') }}</div>
        <div class="clinic-meta">
            {{ $clinic['clinic_address'] ?? '' }}<br>
            @if($clinic['clinic_phone'] ?? false) Ph: {{ $clinic['clinic_phone'] }} @endif
            @if($clinic['clinic_email'] ?? false) | {{ $clinic['clinic_email'] }} @endif
        </div>
    </div>
    <div class="rcp-title">
        <h1>{{ $isAdvance ? 'ADVANCE RECEIPT' : 'PAYMENT RECEIPT' }}</h1>
        <p>{{ $receipt->receipt_number }}</p>
        <p>{{ $receipt->receipt_date->format('d M Y') }}</p>
    </div>
</div>

<div class="meta-grid">
    <div class="meta-box">
        <div class="label">Received From</div>
        <div class="value">{{ $receipt->patient->name }}</div>
        <div class="sub">{{ $receipt->patient->phone }}</div>
    </div>
    <div class="meta-box">
        <div class="label">Payment Mode</div>
        <div class="value" style="text-transform:capitalize">{{ str_replace('_', ' ', $receipt->payment_mode) }}</div>
        @if($receipt->reference_no)
        <div class="sub">Ref: {{ $receipt->reference_no }}</div>
        @endif
    </div>
</div>

<div class="amount-block">
    <div class="lbl">Total Amount Received</div>
    <div class="amt">Rs. {{ number_format($receipt->amount, 2) }}</div>
    <div class="mode">{{ strtoupper(str_replace('_', ' ', $receipt->payment_mode)) }}</div>
</div>

<div class="alloc">
    <div class="sh">{{ $isAdvance ? 'What this money is' : 'How this payment was applied' }}</div>
    <table>
        <thead>
            <tr>
                <th>Invoice</th>
                <th>Dated</th>
                <th class="num">Applied</th>
                <th class="num">Balance After</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lines as $line)
            <tr>
                <td style="font-family:monospace;font-weight:600;">{{ $line['invoice_number'] }}</td>
                <td>{{ !empty($line['invoice_date']) ? \Carbon\Carbon::parse($line['invoice_date'])->format('d M Y') : '—' }}</td>
                <td class="num">Rs. {{ number_format($line['amount'], 2) }}</td>
                <td class="num">
                    @if(($line['balance_after'] ?? 0) <= 0)
                        Paid in full
                    @else
                        Rs. {{ number_format($line['balance_after'], 2) }}
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="4" style="color:#94a3b8;">
                {{ $isAdvance
                    ? 'Received in advance — no invoice has been raised against it yet.'
                    : 'No outstanding invoice was settled by this payment.' }}
            </td></tr>
            @endforelse

            @if($credit > 0)
            <tr class="credit">
                <td colspan="3">Held as Patient Credit (available for future invoices)</td>
                <td class="num">Rs. {{ number_format($credit, 2) }}</td>
            </tr>
            @endif

            <tr class="total">
                <td colspan="2">Total Received</td>
                <td class="num">Rs. {{ number_format($receipt->amount, 2) }}</td>
                <td class="num">
                    @if($outAfter <= 0)
                        No dues
                    @else
                        Rs. {{ number_format($outAfter, 2) }} due
                    @endif
                </td>
            </tr>
        </tbody>
    </table>
</div>

@if($receipt->notes)
<div style="margin-bottom:16px;padding:10px 14px;background:#f8fafc;border-radius:8px;font-size:12px;color:#475569;">
    <strong style="color:#1e293b;">Notes:</strong> {{ $receipt->notes }}
</div>
@endif

<div class="rcp-footer">
    Thank you for your payment. This is a computer-generated receipt.
    &nbsp;|&nbsp; {{ $clinic['clinic_name'] ?? config('app.name') }}
</div>

</body>
</html>
