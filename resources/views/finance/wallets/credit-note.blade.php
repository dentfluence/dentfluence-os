<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit Note — {{ $patient->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            'Inter', sans-serif;
            font-size: 13px;
            color: #1a1a1a;
            background: #fff;
        }

        .page {
            max-width: 680px;
            margin: 0 auto;
            padding: 40px 48px;
        }

        /* ── Header ─────────────────────────────────────────────────────── */
        .header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            border-bottom: 3px solid #6a0f70;
            padding-bottom: 20px;
            margin-bottom: 28px;
        }

        .clinic-name {
            font-size: 22px;
            font-weight: 700;
            color: #6a0f70;
            letter-spacing: -0.3px;
        }

        .clinic-meta {
            font-size: 11.5px;
            color: #555;
            margin-top: 4px;
            line-height: 1.6;
        }

        .doc-label {
            text-align: right;
        }

        .doc-label h1 {
            font-size: 20px;
            font-weight: 700;
            color: #6a0f70;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .doc-label .ref {
            font-size: 11.5px;
            color: #777;
            margin-top: 4px;
        }

        .doc-label .doc-date {
            font-size: 12px;
            color: #333;
            margin-top: 2px;
        }

        /* ── Patient block ──────────────────────────────────────────────── */
        .patient-block {
            background: #faf5ff;
            border: 1px solid #e9d8fd;
            border-radius: 6px;
            padding: 16px 20px;
            margin-bottom: 28px;
        }

        .patient-block .label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #6a0f70;
            margin-bottom: 6px;
        }

        .patient-block .name {
            font-size: 17px;
            font-weight: 700;
            color: #1a1a1a;
        }

        .patient-block .meta {
            font-size: 12px;
            color: #555;
            margin-top: 3px;
        }

        /* ── Credit details ─────────────────────────────────────────────── */
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 28px;
        }

        .detail-table th {
            text-align: left;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: #888;
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
        }

        .detail-table td {
            padding: 10px 10px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 13px;
            color: #333;
            vertical-align: top;
        }

        .detail-table tr:last-child td {
            border-bottom: none;
        }

        .detail-table .amount-row td {
            font-size: 24px;
            font-weight: 800;
            color: #6a0f70;
            padding-top: 16px;
            padding-bottom: 16px;
        }

        /* ── Validity banner ────────────────────────────────────────────── */
        .validity-banner {
            border: 1.5px solid #fbbf24;
            background: #fffbeb;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #92400e;
        }

        .validity-banner .icon { font-size: 18px; }

        .no-expiry-banner {
            border: 1.5px solid #a7f3d0;
            background: #ecfdf5;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 28px;
            font-size: 13px;
            color: #065f46;
        }

        /* ── Notes / conditions ─────────────────────────────────────────── */
        .conditions {
            font-size: 11px;
            color: #777;
            border-top: 1px solid #e5e7eb;
            padding-top: 16px;
            line-height: 1.7;
        }

        .conditions strong { color: #555; }

        /* ── Footer ─────────────────────────────────────────────────────── */
        .footer {
            margin-top: 36px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            font-size: 11px;
            color: #aaa;
        }

        .signature-block {
            text-align: right;
        }

        .signature-line {
            width: 180px;
            border-top: 1px solid #888;
            margin-left: auto;
            margin-bottom: 4px;
        }

        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            .page { padding: 24px 32px; }
        }
    </style>
</head>
<body>

{{-- Print button (hidden on print) --}}
<div class="no-print" style="text-align:center;padding:16px;background:#f9fafb;border-bottom:1px solid #e5e7eb;">
    <button onclick="window.print()"
            style="background:#6a0f70;color:white;border:none;padding:8px 24px;font-size:13px;cursor:pointer;border-radius:4px;font-weight:600;">
        Print / Save as PDF
    </button>
    <a href="{{ route('finance.wallets.show', $patient) }}"
       style="margin-left:12px;font-size:13px;color:#555;text-decoration:none;">← Back to Wallet</a>
</div>

<div class="page">

    {{-- ── Header ─────────────────────────────────────────────────────────── --}}
    <div class="header">
        <div>
            @if($clinicLogo)
                <img src="{{ asset('storage/' . $clinicLogo) }}" alt="Logo" style="height:48px;margin-bottom:8px;display:block;">
            @endif
            <div class="clinic-name">{{ $clinicName }}</div>
            <div class="clinic-meta">
                @if($clinicAddress){{ $clinicAddress }}<br>@endif
                @if($clinicPhone){{ $clinicPhone }}@endif
                @if($clinicEmail) &nbsp;·&nbsp; {{ $clinicEmail }}@endif
            </div>
        </div>
        <div class="doc-label">
            <h1>Credit Note</h1>
            <div class="ref">Ref: CN-{{ str_pad($transaction->id, 6, '0', STR_PAD_LEFT) }}</div>
            <div class="doc-date">Date: {{ $transaction->created_at->format('d F Y') }}</div>
        </div>
    </div>

    {{-- ── Patient block ────────────────────────────────────────────────────── --}}
    <div class="patient-block">
        <div class="label">Issued To</div>
        <div class="name">{{ $patient->name }}</div>
        <div class="meta">
            @if($patient->phone){{ $patient->phone }}@endif
            @if($patient->email) &nbsp;·&nbsp; {{ $patient->email }}@endif
            @if($patient->patient_id) &nbsp;·&nbsp; ID: {{ $patient->patient_id }}@endif
        </div>
    </div>

    {{-- ── Credit details ──────────────────────────────────────────────────── --}}
    <table class="detail-table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Type</th>
                <th style="text-align:right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>Credit Balance Added</strong>
                    @if($transaction->notes)
                        <br><span style="color:#777;font-size:12px;">{{ $transaction->notes }}</span>
                    @endif
                </td>
                <td>
                    <span style="background:#f3e8ff;color:#6a0f70;font-size:11px;padding:2px 8px;border-radius:99px;font-weight:600;">
                        Credit Balance
                    </span>
                </td>
                <td style="text-align:right;font-weight:700;color:#6a0f70;">
                    Rs. {{ number_format($transaction->amount, 2) }}
                </td>
            </tr>
            <tr class="amount-row">
                <td colspan="2" style="font-size:13px;color:#555;font-weight:600;">Total Credit</td>
                <td style="text-align:right;">Rs. {{ number_format($transaction->amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ── Validity ─────────────────────────────────────────────────────────── --}}
    @if($transaction->expiry_date)
        <div class="validity-banner">
            <div>
                <strong>Valid Until: {{ $transaction->expiry_date->format('d F Y') }}</strong>
                @if($transaction->expiry_date->isPast())
                    <span style="color:#dc2626;margin-left:8px;font-weight:600;">EXPIRED</span>
                @else
                    <span style="color:#6b7280;margin-left:8px;">({{ $transaction->expiry_date->diffForHumans() }})</span>
                @endif
                <div style="margin-top:2px;font-size:12px;">This credit note must be redeemed before the above date.</div>
            </div>
        </div>
    @else
        <div class="no-expiry-banner">
            <strong>No expiry</strong> — This credit balance carries over indefinitely and can be used at any future visit.
        </div>
    @endif

    {{-- ── Conditions ──────────────────────────────────────────────────────── --}}
    <div class="conditions">
        <strong>Terms & Conditions:</strong><br>
        • This credit note is non-transferable and valid only for {{ $patient->name }}.<br>
        • Credit balance can be applied against any treatment invoice at {{ $clinicName }}.<br>
        • Credit balance is not redeemable for cash.<br>
        @if($transaction->expiry_date && !$transaction->expiry_date->isPast())
            • This credit note expires on {{ $transaction->expiry_date->format('d F Y') }} and will be forfeited if unused.<br>
        @endif
        • This is a computer-generated document.
    </div>

    {{-- ── Footer / Signature ──────────────────────────────────────────────── --}}
    <div class="footer">
        <div>
            Generated on {{ now()->format('d M Y, h:i A') }}<br>
            {{ $clinicName }}
        </div>
        <div class="signature-block">
            <div class="signature-line"></div>
            <div>Authorised Signatory</div>
            <div style="margin-top:2px;color:#555;">{{ $clinicName }}</div>
        </div>
    </div>

</div>
</body>
</html>
