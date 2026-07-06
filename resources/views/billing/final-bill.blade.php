<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Final Bill {{ $finalBill->bill_number }}</title>
    @php $pm = \App\Models\AppSetting::printMargins(['top' => '12mm', 'bottom' => '12mm', 'left' => '12mm', 'right' => '12mm']); @endphp
    {{-- Apply the user's personalisation colour scheme (saved client-side in localStorage) --}}
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
            'Inter', sans-serif;
            font-size: 13px;
            color: #1a1a1a;
            background: #fff;
            padding: 32px;
            max-width: 800px;
            margin: 0 auto;
        }

        .print-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 24px;
        }
        .btn {
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }
        .btn-print { background: var(--df-color-primary, #6a0f70); color: #fff; }
        .btn-close  { background: #f1f5f9; color: #475569; }

        /* Header */
        .bill-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
        }
        .clinic-name { font-size: 20px; font-weight: 700; color: var(--df-color-primary, #6a0f70); }
        .clinic-sub  { font-size: 12px; color: #64748b; margin-top: 4px; line-height: 1.6; }

        .bill-title { text-align: right; }
        .bill-title h1 { font-size: 24px; font-weight: 700; color: var(--df-color-primary, #6a0f70); letter-spacing: 1px; }
        .bill-title p  { font-size: 12px; color: #64748b; margin-top: 4px; }
        .paid-stamp {
            display: inline-block;
            margin-top: 8px;
            padding: 4px 14px;
            background: var(--df-color-light, #f9f3fa);
            color: var(--df-color-primary, #6a0f70);
            font-size: 13px;
            font-weight: 800;
            border-radius: 99px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* Meta row */
        .meta-row {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }
        .meta-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
            flex: 1;
        }
        .meta-box .label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; margin-bottom: 4px; }
        .meta-box .value { font-size: 13px; font-weight: 600; color: #1e293b; }
        .meta-box .sub   { font-size: 11px; color: #64748b; }

        /* Items table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .items-table thead tr { background: var(--df-color-primary, #6a0f70); color: #fff; }
        .items-table thead th {
            padding: 10px 12px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            text-align: left;
        }
        .items-table thead th.right { text-align: right; }
        .items-table tbody tr { border-bottom: 1px solid #f1f5f9; }
        .items-table tbody tr:nth-child(even) { background: #f8fafc; }
        .items-table tbody td { padding: 9px 12px; font-size: 13px; }
        .items-table tbody td.right { text-align: right; }

        /* Totals */
        .totals { display: flex; justify-content: flex-end; margin-bottom: 24px; }
        .totals-box { width: 300px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 16px;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9;
        }
        .totals-row.grand {
            background: var(--df-color-primary, #6a0f70);
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            border-bottom: none;
        }
        .totals-row .amt { font-weight: 600; }

        /* Payments received */
        .section-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 8px;
            margin-top: 20px;
        }
        .pmt-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .pmt-table th {
            background: #f1f5f9;
            padding: 7px 12px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #64748b;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .pmt-table th.right { text-align: right; }
        .pmt-table td { padding: 7px 12px; font-size: 12px; border-bottom: 1px solid #f8fafc; }
        .pmt-table td.right { text-align: right; font-weight: 600; color: var(--df-color-primary, #6a0f70); }

        /* Settled bar */
        .settled-bar {
            background: var(--df-color-light, #f9f3fa);
            border: 1px solid var(--df-color-primary, #6a0f70);
            border-radius: 8px;
            padding: 14px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .settled-bar .lbl { font-size: 12px; color: var(--df-color-primary, #6a0f70); font-weight: 600; }
        .settled-bar .amt { font-size: 18px; font-weight: 800; color: var(--df-color-primary, #6a0f70); }

        /* Footer */
        .bill-footer {
            border-top: 1px solid #e2e8f0;
            padding-top: 16px;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
        }

        @media print {
            body { padding: {{ $pm['top'] }} {{ $pm['right'] }} {{ $pm['bottom'] }} {{ $pm['left'] }}; }
            .print-actions { display: none !important; }
            @page { margin: 0; }
        }
    </style>
</head>
<body>

    {{-- Print / Close --}}
    <div class="print-actions">
        <button class="btn btn-print" onclick="window.print()">Print / Save as PDF</button>
        <button class="btn btn-close" onclick="window.close()">Close</button>
    </div>

    {{-- Clinic + Bill header --}}
    <div class="bill-header">
        <div>
            <div class="clinic-name">{{ $clinic['clinic_name'] ?? config('app.name') }}</div>
            <div class="clinic-sub">
                {{ $clinic['clinic_address'] ?? '' }}<br>
                @if($clinic['clinic_phone'] ?? false) Ph: {{ $clinic['clinic_phone'] }} @endif
                @if($clinic['clinic_email'] ?? false) | {{ $clinic['clinic_email'] }} @endif
                @if($clinic['clinic_gst_no'] ?? false) | GST: {{ $clinic['clinic_gst_no'] }} @endif
            </div>
        </div>
        <div class="bill-title">
            <h1>FINAL BILL</h1>
            <p>{{ $finalBill->bill_number }}</p>
            <p>Against Invoice: {{ $invoice->invoice_number }}</p>
            <p>{{ $finalBill->generated_date->format('d M Y') }}</p>
            <span class="paid-stamp">✓ Fully Paid</span>
        </div>
    </div>

    {{-- Patient + Invoice meta --}}
    <div class="meta-row">
        <div class="meta-box">
            <div class="label">Patient</div>
            <div class="value">{{ $invoice->patient->name }}</div>
            <div class="sub">{{ $invoice->patient->phone }}</div>
            @if($invoice->patient->email ?? false)
            <div class="sub">{{ $invoice->patient->email }}</div>
            @endif
        </div>
        <div class="meta-box">
            <div class="label">Invoice Date</div>
            <div class="value">{{ $invoice->invoice_date->format('d M Y') }}</div>
        </div>
        <div class="meta-box">
            <div class="label">Bill Generated</div>
            <div class="value">{{ $finalBill->generated_date->format('d M Y') }}</div>
        </div>
    </div>

    {{-- Line items --}}
    <table class="items-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Description</th>
                <th>Tooth</th>
                <th class="right">Price</th>
                <th class="right">Qty</th>
                <th class="right">Disc%</th>
                <th class="right">GST%</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->description }}</td>
                <td style="font-size:11px;color:#64748b">{{ $item->tooth_number ?: '—' }}</td>
                <td class="right">Rs. {{ number_format($item->unit_price, 2) }}</td>
                <td class="right">{{ $item->qty }}</td>
                <td class="right">{{ $item->disc_pct }}%</td>
                <td class="right">{{ $item->gst_pct }}%</td>
                <td class="right">Rs. {{ number_format($item->total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals --}}
    <div class="totals">
        <div class="totals-box">
            <div class="totals-row">
                <span>Subtotal</span>
                <span class="amt">Rs. {{ number_format($finalBill->subtotal, 2) }}</span>
            </div>
            @if(($finalBill->membership_discount ?? $invoice->membership_discount ?? 0) > 0)
            <div class="totals-row">
                <span>AOCP Discount</span>
                <span class="amt" style="color:var(--df-color-primary, #6a0f70)">−Rs. {{ number_format($finalBill->membership_discount ?? $invoice->membership_discount, 2) }}</span>
            </div>
            @endif
            @if(($finalBill->coupon_discount ?? $invoice->coupon_discount ?? 0) > 0)
            <div class="totals-row">
                <span>Additional Discount</span>
                <span class="amt" style="color:#2563eb">−Rs. {{ number_format($finalBill->coupon_discount ?? $invoice->coupon_discount, 2) }}</span>
            </div>
            @endif
            @if(($finalBill->wallet_applied ?? $invoice->wallet_applied ?? 0) > 0)
            <div class="totals-row">
                <span>Wallet Credit</span>
                <span class="amt" style="color:var(--df-color-primary, #6a0f70)">−Rs. {{ number_format($finalBill->wallet_applied ?? $invoice->wallet_applied, 2) }}</span>
            </div>
            @endif
            @if(($finalBill->gst_amount ?? 0) > 0)
            <div class="totals-row">
                <span>Tax (GST)</span>
                <span class="amt">Rs. {{ number_format($finalBill->gst_amount, 2) }}</span>
            </div>
            @endif
            <div class="totals-row grand">
                <span>Grand Total</span>
                <span class="amt">Rs. {{ number_format($finalBill->total_amount, 2) }}</span>
            </div>
        </div>
    </div>

    {{-- Payments received --}}
    @if($invoice->payments->count())
    <div class="section-title">Payments Received</div>
    <table class="pmt-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Mode</th>
                <th>Reference</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->payments as $pmt)
            <tr>
                <td>{{ $pmt->payment_date->format('d M Y') }}</td>
                <td style="text-transform:capitalize">{{ $pmt->payment_mode }}</td>
                <td style="font-family:monospace;font-size:11px;color:#64748b">{{ $pmt->reference_no ?: '—' }}</td>
                <td class="right">Rs. {{ number_format($pmt->amount, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Settled bar --}}
    <div class="settled-bar">
        <span class="lbl">Total Paid — Account Settled</span>
        <span class="amt">Rs. {{ number_format($finalBill->total_paid, 2) }}</span>
    </div>

    {{-- Footer --}}
    <div class="bill-footer">
        This is the final bill confirming full payment. Thank you for choosing {{ $clinic['clinic_name'] ?? config('app.name') }}.
        &nbsp;|&nbsp; This is a computer-generated document.
    </div>

</body>
</html>
