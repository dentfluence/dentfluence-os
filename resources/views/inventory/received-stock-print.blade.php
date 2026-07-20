<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Received Stock {{ $grn->grn_number }}</title>
    @php $pm = \App\Models\AppSetting::printMargins(['top' => '12mm', 'bottom' => '12mm', 'left' => '12mm', 'right' => '12mm']); @endphp
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
        body { font-family: 'Inter', sans-serif; font-size: 13px; color: #1a1a1a; background: #fff; padding: 32px; max-width: 800px; margin: 0 auto; }
        .print-actions { display: flex; gap: 10px; margin-bottom: 24px; }
        .btn { padding: 8px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; }
        .btn-print { background: var(--df-color-primary, #6a0f70); color: #fff; }
        .btn-close { background: #f1f5f9; color: #475569; }
        .doc-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px; padding-bottom: 20px; border-bottom: 2px solid #e2e8f0; }
        .clinic-name { font-size: 20px; font-weight: 700; color: var(--df-color-primary, #6a0f70); }
        .clinic-sub { font-size: 12px; color: #64748b; margin-top: 4px; line-height: 1.6; }
        .doc-title { text-align: right; }
        .doc-title h1 { font-size: 20px; font-weight: 700; color: var(--df-color-primary, #6a0f70); letter-spacing: 1px; }
        .doc-title p { font-size: 12px; color: #64748b; margin-top: 4px; }
        .meta-row { display: flex; justify-content: space-between; margin-bottom: 24px; gap: 20px; }
        .meta-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; flex: 1; }
        .meta-box .label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; margin-bottom: 4px; }
        .meta-box .value { font-size: 13px; font-weight: 600; color: #1e293b; }
        .meta-box .sub { font-size: 11px; color: #64748b; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .items-table thead tr { background: var(--df-color-primary, #6a0f70); color: #fff; }
        .items-table thead th { padding: 10px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; text-align: left; }
        .items-table thead th.right { text-align: right; }
        .items-table tbody tr { border-bottom: 1px solid #f1f5f9; }
        .items-table tbody tr:nth-child(even) { background: #f8fafc; }
        .items-table tbody td { padding: 9px 12px; font-size: 13px; vertical-align: top; }
        .items-table tbody td.right { text-align: right; }
        .item-code { font-size: 11px; color: #64748b; }
        .totals { display: flex; justify-content: flex-end; margin-bottom: 24px; }
        .totals-box { width: 280px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
        .totals-row { display: flex; justify-content: space-between; padding: 8px 16px; font-size: 13px; border-bottom: 1px solid #f1f5f9; }
        .totals-row.grand { background: var(--df-color-primary, #6a0f70); color: #fff; font-size: 15px; font-weight: 700; border-bottom: none; }
        .totals-row .amt { font-weight: 600; }
        .doc-footer { border-top: 1px solid #e2e8f0; padding-top: 16px; text-align: center; font-size: 11px; color: #94a3b8; }
        @media print {
            body { padding: {{ $pm['top'] }} {{ $pm['right'] }} {{ $pm['bottom'] }} {{ $pm['left'] }}; }
            .print-actions { display: none !important; }
            @page { margin: 0; }
        }
    </style>
</head>
<body>

    <div class="print-actions">
        <button class="btn btn-print" onclick="window.print()">Print / Save as PDF</button>
        <button class="btn btn-close"
                onclick="window.close(); window.location.replace('{{ route('inventory.received-stock') }}');">
            Close
        </button>
    </div>

    <div class="doc-header">
        <div>
            <div class="clinic-name">{{ $clinic['clinic_name'] ?? config('app.name') }}</div>
            <div class="clinic-sub">
                {{ $clinic['clinic_address'] ?? '' }}<br>
                @if($clinic['clinic_phone'] ?? false) Ph: {{ $clinic['clinic_phone'] }} @endif
                @if($clinic['clinic_email'] ?? false) | {{ $clinic['clinic_email'] }} @endif
                @if($clinic['clinic_gst_no'] ?? false) | GST: {{ $clinic['clinic_gst_no'] }} @endif
            </div>
        </div>
        <div class="doc-title">
            <h1>RECEIVED STOCK</h1>
            <p>{{ $grn->grn_number }}</p>
            <p>{{ optional($grn->received_date)->format('d M Y') }}</p>
        </div>
    </div>

    <div class="meta-row">
        <div class="meta-box">
            <div class="label">Supplier</div>
            <div class="value">{{ $grn->vendor->vendor_name ?? '—' }}</div>
            @if($grn->vendor?->phone)<div class="sub">{{ $grn->vendor->phone }}</div>@endif
        </div>
        <div class="meta-box">
            <div class="label">Against Purchase Order</div>
            <div class="value">{{ $grn->purchaseOrder->order_no ?? '—' }}</div>
        </div>
        <div class="meta-box">
            <div class="label">Received By</div>
            <div class="value">{{ $grn->createdBy->name ?? '—' }}</div>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>Batch</th>
                <th>Expiry</th>
                <th class="right">Qty</th>
                <th class="right">Unit Price</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @php $total = 0; @endphp
            @foreach($grn->items as $i => $line)
                @php $total += $line->total_price; @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        {{ $line->item->product_name ?? 'Item #'.$line->inventory_item_id }}
                        @if($line->item?->item_code)<div class="item-code">{{ $line->item->item_code }}</div>@endif
                    </td>
                    <td>{{ $line->batch_no ?: '—' }}</td>
                    <td>{{ optional($line->expiry_date)->format('d M Y') ?: '—' }}</td>
                    <td class="right">{{ rtrim(rtrim(number_format($line->qty_received, 2), '0'), '.') }}</td>
                    <td class="right">Rs. {{ number_format($line->unit_price, 2) }}</td>
                    <td class="right">Rs. {{ number_format($line->total_price, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="totals-box">
            <div class="totals-row grand">
                <span>Total Received Value</span>
                <span class="amt">Rs. {{ number_format($total, 2) }}</span>
            </div>
        </div>
    </div>

    <div class="doc-footer">
        {{ $clinic['clinic_name'] ?? config('app.name') }} — Received Stock {{ $grn->grn_number }}. This is a computer-generated document.
    </div>

</body>
</html>
