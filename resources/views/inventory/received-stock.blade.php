@extends('layouts.app')
@section('page-title', 'Inventory — Received Stock')
@section('content')

<div class="df-page-header">
    <div>
        <div class="df-page-title" style="font-size:22px;">Inventory</div>
        <div class="df-page-subtitle">Received Stock · {{ $receipts->total() }} {{ Str::plural('receipt', $receipts->total()) }}</div>
    </div>
    <div class="df-page-actions">
        <form method="GET" action="{{ route('inventory.received-stock') }}" style="display:flex;gap:8px;">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search receipt, supplier, PO…"
                   style="padding:8px 12px;border:1px solid rgba(106,15,112,0.20);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;color:#1e0a2c;outline:none;min-width:230px;">
            <button type="submit"
                    style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#6a0f70;color:#fff;border-radius:3px;font-size:13px;font-weight:500;border:none;cursor:pointer;">
                Search
            </button>
        </form>
    </div>
</div>

@include('inventory.partials.subnav')

<div style="font-size:12.5px;color:#7a6884;margin-bottom:16px;line-height:1.6;">
    Every time stock is received against a purchase order, it is recorded here — with the supplier, items, quantity and value. This is your record of what has physically come into the clinic.
</div>

<div style="background:#fff;border:1px solid rgba(185,92,183,0.10);border-radius:4px;overflow:hidden;">
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;min-width:820px;">
            <thead>
                <tr style="background:#faf5fb;border-bottom:1px solid rgba(185,92,183,0.12);">
                    <th style="padding:11px 18px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Receipt No</th>
                    <th style="padding:11px 18px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Date</th>
                    <th style="padding:11px 18px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Supplier</th>
                    <th style="padding:11px 18px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Purchase Order</th>
                    <th style="padding:11px 18px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Items</th>
                    <th style="padding:11px 18px;text-align:right;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Qty</th>
                    <th style="padding:11px 18px;text-align:right;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Amount</th>
                    <th style="padding:11px 18px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Status</th>
                    <th style="padding:11px 18px;text-align:right;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($receipts as $r)
                    @php
                        $totalQty = $r->items->sum('qty_received');
                        $totalAmt = $r->items->sum('total_price');
                        $isReversed = $r->status === 'reversed';
                    @endphp
                    <tr style="border-bottom:1px solid rgba(185,92,183,0.06);{{ $isReversed ? 'opacity:.55;' : '' }}">
                        <td style="padding:12px 18px;font-size:13px;font-weight:600;color:#1e0a2c;font-family:'Inter',sans-serif;">{{ $r->grn_number }}</td>
                        <td style="padding:12px 18px;font-size:13px;color:#4e2060;">{{ optional($r->received_date)->format('d M Y') ?: '—' }}</td>
                        <td style="padding:12px 18px;font-size:13px;color:#4e2060;">{{ $r->vendor->vendor_name ?? '—' }}</td>
                        <td style="padding:12px 18px;font-size:13px;color:#4e2060;">{{ $r->purchaseOrder->order_no ?? '—' }}</td>
                        <td style="padding:12px 18px;font-size:13px;color:#4e2060;text-align:center;">{{ $r->items_count }}</td>
                        <td style="padding:12px 18px;font-size:13px;color:#4e2060;text-align:right;">{{ rtrim(rtrim(number_format($totalQty, 2), '0'), '.') }}</td>
                        <td style="padding:12px 18px;font-size:13px;color:#1e0a2c;text-align:right;font-weight:500;">Rs. {{ number_format($totalAmt, 2) }}</td>
                        <td style="padding:12px 18px;text-align:center;">
                            <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;
                                {{ $isReversed ? 'background:#fdeaea;color:#b52020;' : 'background:#e8f7ef;color:#1a7a45;' }}">
                                {{ $isReversed ? 'Reversed' : 'Received' }}
                            </span>
                        </td>
                        <td style="padding:12px 18px;text-align:right;">
                            <a href="{{ route('inventory.received-stock.print', $r) }}" target="_blank"
                               style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;background:#fff;color:#6a0f70;border:1px solid rgba(106,15,112,0.22);border-radius:3px;font-size:12px;font-weight:500;text-decoration:none;">
                                View / Print
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="padding:48px;text-align:center;color:#9a85aa;font-size:13px;">
                            No stock received yet. When you receive items against a purchase order, they appear here.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($receipts->hasPages())
<div style="margin-top:16px;">
    {{ $receipts->links() }}
</div>
@endif

@endsection
