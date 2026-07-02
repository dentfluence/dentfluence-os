@extends('layouts.app')

@section('title', 'Vendor Invoices')

@section('content')
{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- VENDOR INVOICES — Phase 1                                  --}}
{{-- PO → GRN → Invoice → Accounts Payable                     --}}
{{-- ═══════════════════════════════════════════════════════════ --}}

<div style="padding:24px 28px;max-width:1200px;margin:0 auto;">

    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
        <div>
            <h1 style="font-family:'Cormorant Garamond',serif;font-size:26px;font-weight:600;
                        color:#0d3d22;margin:0 0 4px 0;">Vendor Invoices</h1>
            <p style="font-size:12px;color:#5a8a6a;margin:0;">
                Each invoice auto-creates an Accounts Payable entry in Finance.
            </p>
        </div>
        <a href="{{ route('inventory.vendor-invoices.create') }}"
           style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;
                  background:#1a7a45;color:#fff;border-radius:4px;text-decoration:none;
                  font-size:13px;font-weight:500;font-family:'Inter',sans-serif;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            New Invoice
        </a>
    </div>

    {{-- KPI Cards --}}
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;">
        @php
            $kpiCards = [
                ['label'=>'Pending Invoices',  'value'=>$kpis['total_pending'],   'color'=>'#1a5ea8','bg'=>'#e6f0fb'],
                ['label'=>'Outstanding Amount','value'=>'Rs. '.number_format($kpis['total_amount'],0), 'color'=>'#6a0f70','bg'=>'#f9f3fa'],
                ['label'=>'Overdue',           'value'=>$kpis['overdue_count'],   'color'=>'#b52020','bg'=>'#fdeaea'],
                ['label'=>'Paid This Month',   'value'=>'Rs. '.number_format($kpis['paid_this_month'],0),'color'=>'#1a7a45','bg'=>'#f0faf4'],
            ];
        @endphp
        @foreach($kpiCards as $card)
        <div style="background:{{ $card['bg'] }};border-radius:6px;padding:16px 20px;
                    border-left:3px solid {{ $card['color'] }};">
            <div style="font-size:11px;font-weight:600;letter-spacing:.06em;
                        text-transform:uppercase;color:{{ $card['color'] }};margin-bottom:6px;">
                {{ $card['label'] }}
            </div>
            <div style="font-size:22px;font-weight:700;color:#1e0a2c;
                        font-family:'Cormorant Garamond',serif;">
                {{ $card['value'] }}
            </div>
        </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <form method="GET" style="display:flex;gap:10px;margin-bottom:20px;align-items:flex-end;">
        <div style="flex:2;">
            <input type="text" name="search" value="{{ $search ?? '' }}"
                   placeholder="Search invoice ref or vendor invoice number..."
                   style="width:100%;padding:8px 12px;border:1px solid rgba(26,122,69,0.25);
                          border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;
                          box-sizing:border-box;">
        </div>
        <div>
            <select name="status" style="padding:8px 12px;border:1px solid rgba(26,122,69,0.25);
                    border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;">
                <option value="">All Statuses</option>
                @foreach(['draft'=>'Draft','pending'=>'Pending','approved'=>'Approved','paid'=>'Paid','cancelled'=>'Cancelled'] as $val=>$lbl)
                <option value="{{ $val }}" {{ ($status??'')===$val?'selected':'' }}>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <select name="vendor_id" style="padding:8px 12px;border:1px solid rgba(26,122,69,0.25);
                    border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;">
                <option value="">All Vendors</option>
                @foreach($vendors as $v)
                <option value="{{ $v->id }}" {{ ($vendorId??'')==$v->id?'selected':'' }}>{{ $v->vendor_name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" style="padding:8px 16px;background:#1a7a45;color:#fff;border:none;
                border-radius:3px;font-size:13px;cursor:pointer;font-family:'Inter',sans-serif;">
            Filter
        </button>
        <a href="{{ route('inventory.vendor-invoices.index') }}"
           style="padding:8px 14px;background:#fff;color:#5a8a6a;border:1px solid rgba(26,122,69,0.25);
                  border-radius:3px;font-size:13px;text-decoration:none;font-family:'Inter',sans-serif;">
            Clear
        </a>
    </form>

    {{-- Invoices Table --}}
    <div style="background:#fff;border-radius:6px;border:1px solid rgba(26,122,69,0.12);overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;font-family:'Inter',sans-serif;">
            <thead>
                <tr style="background:#f0faf4;border-bottom:1px solid rgba(26,122,69,0.12);">
                    <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#1a7a45;">Invoice Ref</th>
                    <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#1a7a45;">Vendor Invoice #</th>
                    <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#1a7a45;">PO</th>
                    <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#1a7a45;">Vendor</th>
                    <th style="padding:10px 14px;text-align:right;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#1a7a45;">Amount</th>
                    <th style="padding:10px 14px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#1a7a45;">Invoice Date</th>
                    <th style="padding:10px 14px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#1a7a45;">Due Date</th>
                    <th style="padding:10px 14px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#1a7a45;">Status</th>
                    <th style="padding:10px 14px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#1a7a45;">AP Entry</th>
                    <th style="padding:10px 8px;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $inv)
                @php
                    $statusColors = [
                        'draft'    =>['bg'=>'#fff4e0','color'=>'#a05c00'],
                        'pending'  =>['bg'=>'#e6f0fb','color'=>'#1a5ea8'],
                        'approved' =>['bg'=>'#fffbe6','color'=>'#5a6a00'],
                        'paid'     =>['bg'=>'#e6f9f0','color'=>'#1a7a45'],
                        'cancelled'=>['bg'=>'#fdeaea','color'=>'#b52020'],
                    ];
                    $sc = $statusColors[$inv->status] ?? ['bg'=>'#f5f5f5','color'=>'#555'];
                    $isOverdue = $inv->due_date && $inv->due_date->isPast() && !in_array($inv->status,['paid','cancelled']);
                @endphp
                <tr style="border-bottom:1px solid rgba(26,122,69,0.07);">
                    <td style="padding:10px 14px;font-family:'Inter', sans-serif;font-size:12px;color:#1a5ea8;font-weight:600;">
                        <a href="{{ route('inventory.vendor-invoices.show',$inv) }}"
                           style="text-decoration:none;color:#1a5ea8;">{{ $inv->invoice_ref }}</a>
                    </td>
                    <td style="padding:10px 14px;color:#555;">{{ $inv->invoice_number ?? '—' }}</td>
                    <td style="padding:10px 14px;font-family:'Inter', sans-serif;font-size:12px;color:#444;">
                        {{ $inv->purchaseOrder?->order_no ?? '—' }}
                    </td>
                    <td style="padding:10px 14px;color:#1e0a2c;">
                        {{ $inv->financeVendor?->vendor_name ?? $inv->inventoryVendor?->vendor_name ?? '—' }}
                    </td>
                    <td style="padding:10px 14px;text-align:right;font-weight:600;color:#1e0a2c;">
                        Rs. {{ number_format($inv->total_amount,0) }}
                    </td>
                    <td style="padding:10px 14px;text-align:center;color:#555;">
                        {{ $inv->invoice_date?->format('d M Y') ?? '—' }}
                    </td>
                    <td style="padding:10px 14px;text-align:center;
                                color:{{ $isOverdue ? '#b52020' : '#555' }};
                                font-weight:{{ $isOverdue ? '600' : '400' }};">
                        {{ $inv->due_date?->format('d M Y') ?? '—' }}
                        @if($isOverdue)
                            <span style="display:block;font-size:10px;color:#b52020;">OVERDUE</span>
                        @endif
                    </td>
                    <td style="padding:10px 14px;text-align:center;">
                        <span style="display:inline-block;padding:3px 10px;border-radius:3px;
                                     font-size:11px;font-weight:600;
                                     background:{{ $sc['bg'] }};color:{{ $sc['color'] }};">
                            {{ ucfirst($inv->status) }}
                        </span>
                    </td>
                    <td style="padding:10px 14px;text-align:center;">
                        @if($inv->finance_expense_id)
                            <span style="display:inline-block;padding:3px 8px;border-radius:3px;
                                         font-size:11px;background:#e6f9f0;color:#1a7a45;">✓ Created</span>
                        @else
                            <span style="font-size:11px;color:#aaa;">—</span>
                        @endif
                    </td>
                    <td style="padding:10px 8px;text-align:center;">
                        @if(!in_array($inv->status,['paid','cancelled']))
                        <form method="POST" action="{{ route('inventory.vendor-invoices.destroy',$inv) }}"
                              onsubmit="return confirm('Cancel this invoice and reverse the AP entry?')"
                              style="display:inline;">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    style="background:none;border:none;cursor:pointer;color:#b52020;padding:2px 4px;"
                                    title="Cancel Invoice">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="15" y1="9" x2="9" y2="15"/>
                                    <line x1="9" y1="9" x2="15" y2="15"/>
                                </svg>
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" style="padding:40px;text-align:center;color:#888;font-style:italic;">
                        No vendor invoices found.
                        <a href="{{ route('inventory.vendor-invoices.create') }}"
                           style="color:#1a7a45;text-decoration:none;margin-left:6px;">Create your first invoice →</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($invoices->hasPages())
    <div style="margin-top:16px;">{{ $invoices->links() }}</div>
    @endif

</div>
@endsection
