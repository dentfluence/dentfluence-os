<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt {{ $receipt->receipt_number }}</title>
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
            max-width: 600px;
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
        .rcp-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
            padding-bottom: 18px;
            border-bottom: 2px solid #e2e8f0;
        }
        .clinic-name { font-size: 18px; font-weight: 700; color: var(--df-color-primary, #6a0f70); }
        .clinic-sub  { font-size: 11px; color: #64748b; margin-top: 4px; line-height: 1.6; }

        .rcp-title h1 { font-size: 22px; font-weight: 700; color: var(--df-color-primary, #6a0f70); letter-spacing: 1px; text-align: right; }
        .rcp-title p  { font-size: 12px; color: #64748b; text-align: right; margin-top: 3px; }

        /* Meta boxes */
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }
        .meta-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
        }
        .meta-box .label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; margin-bottom: 3px; }
        .meta-box .value { font-size: 13px; font-weight: 600; color: #1e293b; }
        .meta-box .sub   { font-size: 11px; color: #64748b; }

        /* Amount spotlight */
        .amount-block {
            background: var(--df-color-light, #f9f3fa);
            border: 2px solid var(--df-color-primary, #6a0f70);
            border-radius: 12px;
            padding: 20px 24px;
            text-align: center;
            margin-bottom: 20px;
        }
        .amount-block .lbl { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--df-color-primary, #6a0f70); margin-bottom: 6px; }
        .amount-block .amt { font-size: 36px; font-weight: 800; color: var(--df-color-primary, #6a0f70); }
        .amount-block .mode { font-size: 12px; color: var(--df-color-hover, #3a0050); margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Invoice summary */
        .summary-box {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .summary-box .sh {
            background: var(--df-color-primary, #6a0f70);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 14px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 14px;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9;
        }
        .summary-row:last-child { border-bottom: none; }
        .summary-row .lbl { color: #64748b; }
        .summary-row .val { font-weight: 600; }
        .summary-row.balance .val { color: {{ ($receipt->balance_after ?? 0) > 0 ? '#b45309' : 'var(--df-color-primary, #6a0f70)' }}; font-size: 15px; }

        /* Footer */
        .rcp-footer {
            border-top: 1px solid #e2e8f0;
            padding-top: 14px;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
            margin-top: 20px;
        }

        @media print {
            body { padding: 16px; }
            .print-actions { display: none !important; }
            @page { margin: 12mm; size: A5; }
        }
    </style>
</head>
<body>

    {{-- Print / Close --}}
    <div class="print-actions">
        <button class="btn btn-print" onclick="window.print()">Print / Save as PDF</button>
        <button class="btn btn-close" onclick="window.close()">Close</button>
    </div>

    {{-- Clinic + Receipt header --}}
    <div class="rcp-header">
        <div>
            <div class="clinic-name">{{ $clinic['clinic_name'] ?? config('app.name') }}</div>
            <div class="clinic-sub">
                {{ $clinic['clinic_address'] ?? '' }}<br>
                @if($clinic['clinic_phone'] ?? false) Ph: {{ $clinic['clinic_phone'] }} @endif
                @if($clinic['clinic_email'] ?? false) | {{ $clinic['clinic_email'] }} @endif
            </div>
        </div>
        <div class="rcp-title">
            <h1>RECEIPT</h1>
            <p>{{ $receipt->receipt_number }}</p>
            <p>{{ $receipt->receipt_date->format('d M Y') }}</p>
        </div>
    </div>

    {{-- Patient + Invoice meta --}}
    <div class="meta-grid">
        <div class="meta-box">
            <div class="label">Received From</div>
            <div class="value">{{ $receipt->invoice->patient->name }}</div>
            <div class="sub">{{ $receipt->invoice->patient->phone }}</div>
        </div>
        <div class="meta-box">
            <div class="label">Against Invoice</div>
            <div class="value">{{ $receipt->invoice->invoice_number }}</div>
            <div class="sub">Dated {{ $receipt->invoice->invoice_date->format('d M Y') }}</div>
        </div>
        <div class="meta-box">
            <div class="label">Payment Mode</div>
            <div class="value" style="text-transform:capitalize">{{ $receipt->payment_mode }}</div>
            @if($receipt->reference_no)
            <div class="sub">Ref: {{ $receipt->reference_no }}</div>
            @endif
        </div>
        <div class="meta-box">
            <div class="label">Receipt Date</div>
            <div class="value">{{ $receipt->receipt_date->format('d M Y') }}</div>
        </div>
    </div>

    {{-- Amount spotlight --}}
    <div class="amount-block">
        <div class="lbl">Amount Received</div>
        <div class="amt">Rs. {{ number_format($receipt->amount, 2) }}</div>
        <div class="mode">{{ strtoupper($receipt->payment_mode) }}</div>
    </div>

    {{-- Invoice summary --}}
    <div class="summary-box">
        <div class="sh">Invoice Summary</div>
        <div class="summary-row">
            <span class="lbl">Invoice Total</span>
            <span class="val">Rs. {{ number_format($receipt->invoice_total, 2) }}</span>
        </div>
        <div class="summary-row">
            <span class="lbl">Previously Paid</span>
            <span class="val">Rs. {{ number_format($receipt->amount_paid_before, 2) }}</span>
        </div>
        <div class="summary-row">
            <span class="lbl">This Payment</span>
            <span class="val" style="color:var(--df-color-primary, #6a0f70)">Rs. {{ number_format($receipt->amount, 2) }}</span>
        </div>
        <div class="summary-row balance">
            <span class="lbl">Balance Remaining</span>
            <span class="val">
                @if(($receipt->balance_after ?? 0) <= 0)
                    Rs. 0.00 — Fully Paid ✓
                @else
                    Rs. {{ number_format($receipt->balance_after, 2) }}
                @endif
            </span>
        </div>
    </div>

    @if($receipt->notes)
    <div style="margin-bottom:16px;padding:10px 14px;background:#f8fafc;border-radius:8px;font-size:12px;color:#475569;">
        <strong style="color:#1e293b;">Notes:</strong> {{ $receipt->notes }}
    </div>
    @endif

    {{-- Footer --}}
    <div class="rcp-footer">
        Thank you for your payment. This is a computer-generated receipt.
        &nbsp;|&nbsp; {{ $clinic['clinic_name'] ?? config('app.name') }}
    </div>

</body>
</html>
