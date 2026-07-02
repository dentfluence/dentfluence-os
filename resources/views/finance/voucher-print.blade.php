<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher {{ $voucher->voucher_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            color: #1a1a1a;
            background: #fff;
            padding: 30px;
        }
        .voucher {
            max-width: 680px;
            margin: 0 auto;
            border: 2px solid #6a0f70;
        }
        .header {
            background: #6a0f70;
            color: white;
            padding: 18px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.8; }
        .header .vno { font-size: 20px; font-weight: bold; font-family: monospace; margin-top: 4px; }
        .header .date-block { text-align: right; font-size: 12px; }
        .header .date-block .label { font-size: 10px; opacity: 0.7; text-transform: uppercase; }
        .amount-band {
            background: #fdf8ff;
            border-bottom: 2px solid #e8d5f0;
            padding: 16px 24px;
        }
        .amount-band .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #666; }
        .amount-band .amount { font-size: 28px; font-weight: bold; color: #6a0f70; margin-top: 4px; }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            padding: 0;
        }
        .cell {
            padding: 14px 24px;
            border-bottom: 1px solid #f0e0f8;
        }
        .cell .label { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 3px; }
        .cell .value { font-size: 13px; font-weight: 600; color: #1a1a1a; }
        .cell .sub { font-size: 11px; color: #666; margin-top: 2px; }
        .notes-row { padding: 14px 24px; border-bottom: 1px solid #f0e0f8; }
        .linked-expense {
            padding: 14px 24px;
            background: #f9f9f9;
            border-top: 1px solid #e8d5f0;
            font-size: 11px;
            color: #555;
        }
        .footer {
            padding: 18px 24px;
            display: flex;
            justify-content: space-between;
            border-top: 1px solid #e8d5f0;
            margin-top: 8px;
        }
        .sig-box {
            flex: 1;
            min-width: 0;
            text-align: center;
        }
        .sig-box .sig-line {
            border-top: 1px solid #333;
            padding-top: 6px;
            font-size: 11px;
            color: #555;
            margin-top: 30px;
        }
        .print-actions {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 8px 16px;
            font-size: 13px;
            cursor: pointer;
            border: none;
        }
        .btn-print { background: #6a0f70; color: white; }
        .btn-close { background: #e5e7eb; color: #333; }
        @page {
            size: A4 portrait;
            margin: 15mm 12mm;
        }
        @media print {
            .print-actions { display: none; }
            body { padding: 0; background: #fff; }
            .voucher {
                max-width: 100%;
                width: 100%;
                border: 1.5pt solid #6a0f70;
                page-break-inside: avoid;
            }
            .footer { page-break-inside: avoid; }
            .sig-box { page-break-inside: avoid; }
            /* Prevent grid cells from splitting across pages */
            .cell { page-break-inside: avoid; }
            .notes-row { page-break-inside: avoid; }
            .linked-expense { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

<div class="print-actions">
    <button class="btn btn-print" onclick="window.print()">Print / Save PDF</button>
    <button class="btn btn-close" onclick="window.close()">Close</button>
</div>

<div class="voucher">

    {{-- Header --}}
    <div class="header">
        <div>
            <h1>Payment Voucher</h1>
            <div class="vno">{{ $voucher->voucher_number }}</div>
        </div>
        <div class="date-block">
            <div class="label">Date</div>
            <div>{{ $voucher->voucher_date->format('d M Y') }}</div>
        </div>
    </div>

    {{-- Amount --}}
    <div class="amount-band">
        <div class="label">Amount Paid</div>
        <div class="amount">Rs. {{ number_format($voucher->amount, 2) }}</div>
    </div>

    {{-- Details grid --}}
    <div class="grid">
        <div class="cell">
            <div class="label">Vendor / Payee</div>
            <div class="value">{{ $voucher->vendor_name ?? ($voucher->vendor?->vendor_name ?? '—') }}</div>
            @if($voucher->vendor?->vendor_type)
                <div class="sub">{{ $voucher->vendor->vendor_type }}</div>
            @endif
        </div>
        <div class="cell">
            <div class="label">Payment Mode</div>
            <div class="value">{{ $voucher->getPaymentModeLabel() }}</div>
        </div>
        <div class="cell">
            <div class="label">Clinic Account Used</div>
            <div class="value">{{ $voucher->clinic_account_name ?? '—' }}</div>
        </div>
        <div class="cell">
            <div class="label">Purpose</div>
            <div class="value">{{ $voucher->purpose ?? '—' }}</div>
        </div>
        <div class="cell">
            @if($voucher->payment_mode === 'cheque')
                <div class="label">Cheque Number</div>
                <div class="value" style="font-family:monospace;">{{ $voucher->cheque_number ?? $voucher->reference ?? '—' }}</div>
            @else
                <div class="label">UTR / Transaction Ref</div>
                <div class="value" style="font-family:monospace;">{{ $voucher->reference ?? '—' }}</div>
            @endif
        </div>
        <div class="cell">
            <div class="label">Created By</div>
            <div class="value">{{ $voucher->createdBy?->name ?? '—' }}</div>
            <div class="sub">{{ $voucher->created_at->format('d M Y, h:i A') }}</div>
        </div>
        <div class="cell">
            <div class="label">Approved By</div>
            <div class="value">{{ $voucher->approvedBy?->name ?? '—' }}</div>
            @if($voucher->approved_at)
                <div class="sub">{{ $voucher->approved_at->format('d M Y') }}</div>
            @endif
        </div>
    </div>

    @if($voucher->notes)
    <div class="notes-row">
        <div style="font-size:9px;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:4px;">Notes</div>
        <div>{{ $voucher->notes }}</div>
    </div>
    @endif

    @if($voucher->expense)
    <div class="linked-expense">
        <strong>Linked Expense:</strong>
        {{ $voucher->expense->title }}
        ({{ $voucher->expense->expense_date->format('d M Y') }})
        @if($voucher->expense->category)
            — {{ $voucher->expense->category->name }}
        @endif
    </div>
    @endif

    {{-- Signature footer --}}
    <div class="footer">
        <div class="sig-box">
            <div class="sig-line">Prepared By</div>
        </div>
        <div class="sig-box">
            <div class="sig-line">Approved By</div>
        </div>
        <div class="sig-box">
            <div class="sig-line">Received By</div>
        </div>
    </div>

</div>

<script>
    // Auto-trigger print dialog when opened via PDF button (?pdf=1)
    if (new URLSearchParams(window.location.search).get('pdf') === '1') {
        window.addEventListener('load', function () { window.print(); });
    }
</script>
</body>
</html>
