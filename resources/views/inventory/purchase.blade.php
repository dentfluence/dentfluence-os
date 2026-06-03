@extends('layouts.app')
@section('page-title', 'Inventory — Purchase Orders')
@section('content')

<div class="df-page-header">
    <div>
        <div class="df-page-title" style="font-size:22px;">Inventory</div>
        <div class="df-page-subtitle">Purchase Orders · {{ $orders->total() }} orders</div>
    </div>
    <div class="df-page-actions">
        <button onclick="document.getElementById('modal-create-po').style.display='flex'"
            style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#6a0f70;color:#fff;border-radius:3px;font-size:13px;font-weight:500;border:none;cursor:pointer;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Create PO
        </button>
    </div>
</div>

@include('inventory.partials.subnav')

@if(session('success'))
<div style="padding:10px 16px;background:#e8f7ef;border-left:3px solid #1a7a45;border-radius:3px;font-size:13px;color:#0e4a28;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">
    {{ session('success') }}<button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;">✕</button>
</div>
@endif
@if($errors->any())
<div style="padding:10px 16px;background:#fdeaea;border-left:3px solid #b52020;border-radius:3px;font-size:13px;color:#6b1010;margin-bottom:16px;">
    @foreach($errors->all() as $e){{ $e }}<br>@endforeach
</div>
@endif

{{-- Status filter strip --}}
@php
$statusFilter = request('status', 'all');
$statusTabs = [
    ['val'=>'all',                'label'=>'All',       'color'=>'#6a0f70'],
    ['val'=>'draft',              'label'=>'Draft',     'color'=>'#a05c00'],
    ['val'=>'ordered',            'label'=>'Ordered',   'color'=>'#1a5ea8'],
    ['val'=>'partially_received', 'label'=>'Partial',   'color'=>'#7a4a00'],
    ['val'=>'completed',          'label'=>'Completed', 'color'=>'#1a7a45'],
    ['val'=>'cancelled',          'label'=>'Cancelled', 'color'=>'#b52020'],
];
@endphp
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
    @foreach($statusTabs as $tab)
    <a href="?status={{ $tab['val'] }}"
        style="padding:5px 13px;border-radius:3px;font-size:12.5px;font-weight:500;text-decoration:none;
        {{ $statusFilter===$tab['val'] ? 'background:'.$tab['color'].';color:#fff;' : 'background:#fff;color:#4e2060;border:1px solid rgba(185,92,183,0.18);' }}">
        {{ $tab['label'] }}
    </a>
    @endforeach
</div>

{{-- PO Table --}}
<div class="df-card" style="overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="background:#faf5fb;border-bottom:1px solid rgba(185,92,183,0.10);">
                <th style="padding:10px 18px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Order No.</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Vendor</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Order Date</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Expected</th>
                <th style="padding:10px 14px;text-align:right;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Total</th>
                <th style="padding:10px 18px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Status</th>
                <th style="padding:10px 14px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $po)
            @php
                $statusBg    = match($po->status) {
                    'draft'              => '#fff4e0',
                    'ordered'            => '#e6f0fb',
                    'partially_received' => '#fff8ec',
                    'completed'          => '#e8f7ef',
                    'cancelled'          => '#fdeaea',
                    default              => '#f4f4f4',
                };
                $statusColor = $po->getStatusColor();
                $canReceive  = in_array($po->status, ['ordered', 'partially_received']);
            @endphp
            <tr style="border-bottom:1px solid rgba(185,92,183,0.05);transition:background 120ms;"
                onmouseover="this.style.background='#faf5fb'" onmouseout="this.style.background=''">
                <td style="padding:11px 18px;cursor:pointer;" onclick="openPoDetail({{ $po->id }})">
                    <div style="font-family:'DM Mono',monospace;font-weight:600;color:#6a0f70;font-size:13px;">{{ $po->order_no }}</div>
                    <div style="font-size:11px;color:#9a85aa;margin-top:2px;">{{ $po->items->count() }} item{{ $po->items->count() != 1 ? 's' : '' }}</div>
                </td>
                <td style="padding:11px 14px;cursor:pointer;" onclick="openPoDetail({{ $po->id }})">
                    <div style="font-weight:500;color:#1e0a2c;">{{ $po->vendor?->vendor_name ?? '—' }}</div>
                    @if($po->vendor?->contact_person)<div style="font-size:11px;color:#9a85aa;">{{ $po->vendor->contact_person }}</div>@endif
                </td>
                <td style="padding:11px 14px;font-size:12.5px;color:#2e1040;cursor:pointer;" onclick="openPoDetail({{ $po->id }})">{{ $po->order_date?->format('d M Y') ?? '—' }}</td>
                <td style="padding:11px 14px;font-size:12.5px;color:#2e1040;cursor:pointer;" onclick="openPoDetail({{ $po->id }})">
                    @if($po->expected_date)
                        @php $daysAway = now()->diffInDays($po->expected_date, false); @endphp
                        <span style="{{ $daysAway < 0 ? 'color:#b52020;' : ($daysAway <= 3 ? 'color:#a05c00;' : 'color:#2e1040;') }}">
                            {{ $po->expected_date->format('d M Y') }}
                        </span>
                    @else
                        <span style="color:#9a85aa;">—</span>
                    @endif
                </td>
                <td style="padding:11px 14px;text-align:right;font-family:'DM Mono',monospace;font-weight:600;color:#1e0a2c;font-size:13px;cursor:pointer;" onclick="openPoDetail({{ $po->id }})">
                    ₹{{ number_format($po->total_amount, 0) }}
                    @if($po->gst_amount > 0)<div style="font-size:10.5px;color:#9a85aa;font-weight:400;">incl. GST ₹{{ number_format($po->gst_amount,0) }}</div>@endif
                </td>
                <td style="padding:11px 18px;text-align:center;">
                    <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:500;background:{{ $statusBg }};color:{{ $statusColor }};">
                        {{ $po->getStatusLabel() }}
                    </span>
                </td>
                <td style="padding:11px 14px;text-align:center;">
                    @if($canReceive)
                    <button onclick='openGrn({{ $po->toJson() }})'
                            style="background:#e8f7ee;border:1px solid rgba(26,122,69,0.2);
                                   border-radius:4px;padding:5px 12px;font-size:12px;
                                   font-family:'DM Sans',sans-serif;color:#1a7a45;
                                   cursor:pointer;font-weight:500;white-space:nowrap;">
                        ↓ Receive
                    </button>
                    @else
                    <span style="font-size:11px;color:#c0b8c8;">—</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="padding:56px;text-align:center;color:#9a85aa;">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="rgba(106,15,112,0.18)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto 12px;display:block;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                No purchase orders yet.<br>
                <span style="font-size:12px;">Click "Create PO" to raise your first order.</span>
            </td></tr>
            @endforelse
        </tbody>
    </table>
    @if($orders->hasPages())
    <div style="padding:14px 18px;border-top:1px solid rgba(185,92,183,0.07);">{{ $orders->links() }}</div>
    @endif
</div>


{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- CREATE PO MODAL                                             --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div id="modal-create-po" style="display:none;position:fixed;inset:0;z-index:60;align-items:flex-start;justify-content:center;padding:24px 16px;background:rgba(14,1,24,0.55);backdrop-filter:blur(3px);overflow-y:auto;">
    <div style="background:#fff;border-radius:6px;width:100%;max-width:760px;box-shadow:0 20px 60px rgba(14,1,24,0.25);">

        {{-- Modal header --}}
        <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 24px;border-bottom:1px solid rgba(185,92,183,0.10);background:#faf5fb;border-radius:6px 6px 0 0;">
            <div>
                <div style="font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:600;color:#1e0a2c;">Create Purchase Order</div>
                <div style="font-size:12px;color:#9a85aa;margin-top:2px;">Raise a new supplier order with line items</div>
            </div>
            <button onclick="document.getElementById('modal-create-po').style.display='none'" style="background:none;border:none;cursor:pointer;color:#9a85aa;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <form id="po-form" action="{{ route('inventory.purchase.store') }}" method="POST">
            @csrf
            <div style="padding:24px;">

                {{-- Header fields --}}
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:20px;">
                    <div style="grid-column:1/3;">
                        <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">Vendor *</label>
                        <select name="vendor_id" required
                            style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'DM Sans',sans-serif;color:#1e0a2c;outline:none;box-sizing:border-box;">
                            <option value="">— Select vendor —</option>
                            @foreach($vendors as $v)
                            <option value="{{ $v->id }}">{{ $v->vendor_name }}{{ $v->city ? ' · '.$v->city : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">Save as</label>
                        <select name="status"
                            style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'DM Sans',sans-serif;color:#1e0a2c;outline:none;box-sizing:border-box;">
                            <option value="draft">Draft</option>
                            <option value="ordered">Ordered (sent to vendor)</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">Order Date *</label>
                        <input type="date" name="order_date" required value="{{ date('Y-m-d') }}"
                            style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'DM Sans',sans-serif;outline:none;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">Expected Delivery</label>
                        <input type="date" name="expected_date"
                            style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'DM Sans',sans-serif;outline:none;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">Notes</label>
                        <input type="text" name="notes" placeholder="Remarks or reference…"
                            style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'DM Sans',sans-serif;outline:none;box-sizing:border-box;">
                    </div>
                </div>

                {{-- Line items --}}
                <div style="border-top:1px solid rgba(185,92,183,0.10);padding-top:18px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                        <span style="font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#4e0a53;">Order Items</span>
                        <button type="button" onclick="addPoLine()"
                            style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;background:#f9f3fa;color:#6a0f70;border:1px solid rgba(106,15,112,0.25);border-radius:3px;font-size:12px;font-weight:500;cursor:pointer;">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add Item
                        </button>
                    </div>

                    {{-- Column headers --}}
                    <div style="display:grid;grid-template-columns:1fr 90px 100px 72px 88px 32px;gap:8px;align-items:center;padding:0 0 6px;border-bottom:1px solid rgba(185,92,183,0.08);">
                        <span style="font-size:10.5px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#9a85aa;">Item</span>
                        <span style="font-size:10.5px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#9a85aa;">Qty</span>
                        <span style="font-size:10.5px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#9a85aa;">Unit Price</span>
                        <span style="font-size:10.5px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#9a85aa;">GST %</span>
                        <span style="font-size:10.5px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#9a85aa;text-align:right;">Total</span>
                        <span></span>
                    </div>

                    <div id="po-lines" style="margin-top:6px;"></div>

                    {{-- Running total --}}
                    <div style="border-top:1px solid rgba(185,92,183,0.10);margin-top:12px;padding-top:12px;display:flex;flex-direction:column;align-items:flex-end;gap:4px;">
                        <div style="display:flex;gap:48px;font-size:12.5px;color:#9a85aa;">
                            <span>Subtotal</span>
                            <span id="po-subtotal" style="font-family:'DM Mono',monospace;min-width:80px;text-align:right;">₹0</span>
                        </div>
                        <div style="display:flex;gap:48px;font-size:12.5px;color:#9a85aa;">
                            <span>GST</span>
                            <span id="po-gst" style="font-family:'DM Mono',monospace;min-width:80px;text-align:right;">₹0</span>
                        </div>
                        <div style="display:flex;gap:48px;font-size:14px;font-weight:700;color:#1e0a2c;border-top:1px solid rgba(185,92,183,0.10);padding-top:6px;margin-top:4px;">
                            <span>Grand Total</span>
                            <span id="po-grand" style="font-family:'DM Mono',monospace;min-width:80px;text-align:right;color:#6a0f70;">₹0</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer actions --}}
            <div style="padding:16px 24px;border-top:1px solid rgba(185,92,183,0.08);background:#faf5fb;border-radius:0 0 6px 6px;display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-create-po').style.display='none'"
                    style="padding:9px 20px;background:#fff;color:#6a0f70;border:1px solid rgba(106,15,112,0.25);border-radius:3px;font-size:13px;cursor:pointer;font-family:'DM Sans',sans-serif;">
                    Cancel
                </button>
                <button type="submit" id="po-submit-btn"
                    style="padding:9px 22px;background:#6a0f70;color:#fff;border:none;border-radius:3px;font-size:13px;font-weight:500;cursor:pointer;font-family:'DM Sans',sans-serif;">
                    Save Purchase Order
                </button>
            </div>
        </form>
    </div>
</div>

{{-- PO DETAIL MODAL (view-only) --}}
<div id="modal-po-detail" style="display:none;position:fixed;inset:0;z-index:60;align-items:flex-start;justify-content:center;padding:32px 16px;background:rgba(14,1,24,0.55);backdrop-filter:blur(3px);overflow-y:auto;">
    <div style="background:#fff;border-radius:6px;width:100%;max-width:680px;box-shadow:0 20px 60px rgba(14,1,24,0.25);">
        <div id="po-detail-inner" style="padding:0;">
            <div style="padding:48px;text-align:center;color:#9a85aa;font-size:13px;">Loading…</div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- GRN / RECEIVE AGAINST PO MODAL                               --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div id="modal-grn" style="display:none;position:fixed;inset:0;z-index:60;
     align-items:flex-start;justify-content:center;padding:24px 16px;
     background:rgba(14,1,24,0.55);backdrop-filter:blur(3px);overflow-y:auto;">
    <div style="background:#fff;border-radius:6px;width:100%;max-width:760px;
                box-shadow:0 20px 60px rgba(14,1,24,0.25);">

        {{-- Header --}}
        <div style="display:flex;align-items:center;justify-content:space-between;
                    padding:18px 24px;border-bottom:1px solid rgba(26,122,69,0.12);
                    background:#f0faf4;border-radius:6px 6px 0 0;">
            <div>
                <div style="font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:600;color:#0d3d22;">
                    Receive Stock — <span id="grn-po-no" style="font-family:'DM Mono',monospace;"></span>
                </div>
                <div id="grn-vendor-name" style="font-size:12px;color:#5a8a6a;margin-top:2px;"></div>
            </div>
            <button onclick="document.getElementById('modal-grn').style.display='none'"
                    style="background:none;border:none;cursor:pointer;color:#5a8a6a;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <form id="grn-form" method="POST" action="" style="padding:24px;">
            @csrf

            {{-- Top meta fields --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#1a7a45;margin-bottom:5px;">
                        Receive Into Location *
                    </label>
                    <select name="location_id" required
                            style="width:100%;padding:8px 12px;border:1px solid rgba(26,122,69,0.25);
                                   border-radius:3px;font-size:13px;font-family:'DM Sans',sans-serif;
                                   color:#1e0a2c;outline:none;box-sizing:border-box;">
                        @foreach(\App\Models\Inventory\InventoryLocation::active()->get() as $loc)
                        <option value="{{ $loc->id }}" {{ $loc->type === 'main_store' ? 'selected' : '' }}>
                            {{ $loc->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#1a7a45;margin-bottom:5px;">
                        Received Date *
                    </label>
                    <input type="date" name="received_date" required
                           value="{{ date('Y-m-d') }}"
                           style="width:100%;padding:8px 12px;border:1px solid rgba(26,122,69,0.25);
                                  border-radius:3px;font-size:13px;font-family:'DM Sans',sans-serif;
                                  color:#1e0a2c;outline:none;box-sizing:border-box;">
                </div>
            </div>

            {{-- Line items table --}}
            <div style="border:1px solid rgba(26,122,69,0.12);border-radius:4px;overflow:hidden;margin-bottom:20px;">
                <div style="background:#f0faf4;padding:8px 14px;font-size:11px;font-weight:600;
                            letter-spacing:.06em;text-transform:uppercase;color:#1a7a45;
                            display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr;gap:8px;">
                    <span>Item</span>
                    <span style="text-align:right;">Ordered</span>
                    <span style="text-align:right;">Receive Qty</span>
                    <span style="text-align:center;">Batch No</span>
                    <span style="text-align:center;">Expiry</span>
                </div>
                <div id="grn-lines" style=""></div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button"
                        onclick="document.getElementById('modal-grn').style.display='none'"
                        style="padding:9px 20px;background:#fff;color:#1a7a45;
                               border:1px solid rgba(26,122,69,0.3);border-radius:3px;
                               font-size:13px;cursor:pointer;font-family:'DM Sans',sans-serif;">
                    Cancel
                </button>
                <button type="submit"
                        style="padding:9px 22px;background:#1a7a45;color:#fff;border:none;
                               border-radius:3px;font-size:13px;font-weight:500;cursor:pointer;
                               font-family:'DM Sans',sans-serif;display:flex;align-items:center;gap:6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    Confirm GRN &amp; Update Stock
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Item data for JS autocomplete --}}
<script id="all-items-data" type="application/json">
@json($orders->getCollection()->map(fn($po) => ['id'=>$po->id, 'order_no'=>$po->order_no]))
</script>

@endsection

@push('scripts')
<script>
// ─── Item catalogue for line selects ──────────────────────────
const ALL_ITEMS = @json($allItems);

// ─── PO Line management ────────────────────────────────────────
let lineIndex = 0;

function addPoLine() {
    const container = document.getElementById('po-lines');
    const idx = lineIndex++;

    const row = document.createElement('div');
    row.className = 'po-line';
    row.dataset.idx = idx;
    row.style.cssText = 'display:grid;grid-template-columns:1fr 90px 100px 72px 88px 32px;gap:8px;align-items:center;margin-bottom:6px;';

    const itemOptions = ALL_ITEMS.map(it =>
        `<option value="${it.id}" data-price="${it.last_purchase_price || 0}" data-unit="${it.purchase_unit || ''}">${it.product_name}</option>`
    ).join('');

    const inputStyle = 'width:100%;padding:7px 9px;border:1px solid rgba(185,92,183,0.22);border-radius:3px;font-size:12.5px;font-family:inherit;outline:none;box-sizing:border-box;';

    row.innerHTML = `
        <select name="items[${idx}][item_id]" required onchange="onItemChange(this)" style="${inputStyle}color:#1e0a2c;">
            <option value="">— Item —</option>${itemOptions}
        </select>
        <input type="number" name="items[${idx}][qty]" required min="0.01" step="0.01" placeholder="Qty"
            oninput="recalcLine(this.closest('.po-line'))" style="${inputStyle}font-family:'DM Mono',monospace;text-align:right;">
        <input type="number" name="items[${idx}][price]" required min="0" step="0.01" placeholder="0.00"
            oninput="recalcLine(this.closest('.po-line'))" style="${inputStyle}font-family:'DM Mono',monospace;text-align:right;">
        <input type="number" name="items[${idx}][gst]" min="0" max="100" step="0.1" placeholder="0"
            oninput="recalcLine(this.closest('.po-line'))" style="${inputStyle}font-family:'DM Mono',monospace;text-align:right;">
        <div class="line-total" style="font-family:'DM Mono',monospace;font-size:12.5px;color:#1e0a2c;text-align:right;font-weight:600;">₹0</div>
        <button type="button" onclick="removeLine(this)" style="background:none;border:none;cursor:pointer;color:#b52020;padding:4px;display:flex;align-items:center;justify-content:center;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    `;
    container.appendChild(row);
    recalcTotals();
}

function onItemChange(select) {
    const opt   = select.options[select.selectedIndex];
    const price = opt.dataset.price || 0;
    const row   = select.closest('.po-line');
    const priceInput = row.querySelectorAll('input')[1];
    if (price > 0) priceInput.value = parseFloat(price).toFixed(2);
    recalcLine(row);
}

function recalcLine(row) {
    const inputs   = row.querySelectorAll('input');
    const qty      = parseFloat(inputs[0].value) || 0;
    const price    = parseFloat(inputs[1].value) || 0;
    const gst      = parseFloat(inputs[2].value) || 0;
    const subtotal = qty * price;
    const total    = subtotal * (1 + gst / 100);
    row.querySelector('.line-total').textContent = '₹' + total.toLocaleString('en-IN', {maximumFractionDigits:0});
    recalcTotals();
}

function recalcTotals() {
    let subtotal = 0, gstAmt = 0;
    document.querySelectorAll('.po-line').forEach(row => {
        const inputs = row.querySelectorAll('input');
        const qty    = parseFloat(inputs[0].value) || 0;
        const price  = parseFloat(inputs[1].value) || 0;
        const gst    = parseFloat(inputs[2].value) || 0;
        const sub    = qty * price;
        subtotal += sub;
        gstAmt   += sub * (gst / 100);
    });
    const fmt = v => '₹' + Math.round(v).toLocaleString('en-IN');
    document.getElementById('po-subtotal').textContent = fmt(subtotal);
    document.getElementById('po-gst').textContent      = fmt(gstAmt);
    document.getElementById('po-grand').textContent    = fmt(subtotal + gstAmt);
}

function removeLine(btn) {
    btn.closest('.po-line').remove();
    recalcTotals();
}

// Add first line automatically
addPoLine();

// ─── PO Detail modal ──────────────────────────────────────────
const PO_DATA = @json($orders->getCollection()->load('items.item'));

function openPoDetail(id) {
    const po = PO_DATA.find(p => p.id === id);
    if (!po) return;

    const statusColors = {
        draft: '#a05c00', ordered: '#1a5ea8', partially_received: '#7a4a00',
        completed: '#1a7a45', cancelled: '#b52020'
    };
    const statusBgs = {
        draft: '#fff4e0', ordered: '#e6f0fb', partially_received: '#fff8ec',
        completed: '#e8f7ef', cancelled: '#fdeaea'
    };
    const statusLabels = {
        draft: 'Draft', ordered: 'Ordered', partially_received: 'Partial',
        completed: 'Completed', cancelled: 'Cancelled'
    };
    const color = statusColors[po.status] || '#555';
    const bg    = statusBgs[po.status] || '#f4f4f4';
    const label = statusLabels[po.status] || po.status;

    const itemRows = (po.items || []).map(line => `
        <tr style="border-bottom:1px solid rgba(185,92,183,0.05);">
            <td style="padding:9px 16px;font-size:12.5px;font-weight:500;color:#1e0a2c;">${line.item ? line.item.product_name : '—'}</td>
            <td style="padding:9px 12px;text-align:right;font-family:'DM Mono',monospace;font-size:12.5px;color:#6a0f70;">${parseFloat(line.qty_ordered).toLocaleString()}</td>
            <td style="padding:9px 12px;text-align:right;font-family:'DM Mono',monospace;font-size:12.5px;color:#2e1040;">₹${parseFloat(line.unit_price).toFixed(2)}</td>
            <td style="padding:9px 12px;text-align:right;font-size:12px;color:#9a85aa;">${line.gst_rate}%</td>
            <td style="padding:9px 16px;text-align:right;font-family:'DM Mono',monospace;font-size:12.5px;font-weight:600;color:#1e0a2c;">₹${Math.round(parseFloat(line.total_price)).toLocaleString()}</td>
        </tr>
    `).join('');

    const html = `
        <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 24px;border-bottom:1px solid rgba(185,92,183,0.10);background:#faf5fb;border-radius:6px 6px 0 0;">
            <div>
                <div style="font-family:'DM Mono',monospace;font-size:18px;font-weight:700;color:#6a0f70;">${po.order_no}</div>
                <div style="font-size:12px;color:#9a85aa;margin-top:3px;">${po.vendor ? po.vendor.vendor_name : '—'} · ${po.order_date || '—'}</div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <span style="padding:3px 12px;border-radius:20px;font-size:12px;font-weight:500;background:${bg};color:${color};">${label}</span>
                <button onclick="document.getElementById('modal-po-detail').style.display='none'" style="background:none;border:none;cursor:pointer;color:#9a85aa;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>
        <div style="padding:20px 24px;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead><tr style="background:#faf5fb;">
                    <th style="padding:8px 16px;text-align:left;font-size:10.5px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Item</th>
                    <th style="padding:8px 12px;text-align:right;font-size:10.5px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Qty</th>
                    <th style="padding:8px 12px;text-align:right;font-size:10.5px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Unit Price</th>
                    <th style="padding:8px 12px;text-align:right;font-size:10.5px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">GST</th>
                    <th style="padding:8px 16px;text-align:right;font-size:10.5px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Total</th>
                </tr></thead>
                <tbody>${itemRows || '<tr><td colspan="5" style="padding:24px;text-align:center;color:#9a85aa;">No items on this order.</td></tr>'}</tbody>
            </table>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;margin-top:14px;padding-top:14px;border-top:1px solid rgba(185,92,183,0.08);">
                <div style="display:flex;gap:48px;font-size:12.5px;color:#9a85aa;">
                    <span>GST Amount</span>
                    <span style="font-family:'DM Mono',monospace;min-width:90px;text-align:right;">₹${Math.round(parseFloat(po.gst_amount || 0)).toLocaleString()}</span>
                </div>
                <div style="display:flex;gap:48px;font-size:14px;font-weight:700;color:#1e0a2c;">
                    <span>Grand Total</span>
                    <span style="font-family:'DM Mono',monospace;min-width:90px;text-align:right;color:#6a0f70;">₹${Math.round(parseFloat(po.total_amount || 0)).toLocaleString()}</span>
                </div>
            </div>
            ${po.notes ? `<div style="margin-top:14px;padding:10px 14px;background:#faf5fb;border-radius:3px;font-size:12.5px;color:#4e2060;border-left:3px solid rgba(185,92,183,0.3);">${po.notes}</div>` : ''}
        </div>
    `;

    document.getElementById('po-detail-inner').innerHTML = html;
    document.getElementById('modal-po-detail').style.display = 'flex';
}

// ─── GRN Modal ────────────────────────────────────────────────
function openGrn(po) {
    document.getElementById('grn-po-no').textContent      = po.order_no || '';
    document.getElementById('grn-vendor-name').textContent = po.vendor ? po.vendor.vendor_name : '';

    // Set form action
    document.getElementById('grn-form').action = '/inventory/purchase/' + po.id + '/receive';

    // Build line-item rows
    const lines   = po.items || [];
    const linesEl = document.getElementById('grn-lines');

    linesEl.innerHTML = lines.map((line, idx) => {
        const remaining = parseFloat(line.qty_ordered) - parseFloat(line.qty_received || 0);
        const itemName  = line.item ? line.item.product_name : 'Item #' + line.inventory_item_id;
        return `
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr;gap:8px;
                    padding:10px 14px;border-bottom:1px solid rgba(26,122,69,0.06);
                    align-items:center;">
            <div>
                <div style="font-size:13px;font-weight:500;color:#1e0a2c;">${itemName}</div>
                <input type="hidden" name="lines[${idx}][item_id]" value="${line.inventory_item_id}">
                ${remaining < parseFloat(line.qty_ordered)
                    ? `<div style="font-size:10.5px;color:#a05c00;">Already received: ${parseFloat(line.qty_received || 0)}</div>`
                    : ''}
            </div>
            <div style="text-align:right;font-family:'DM Mono',monospace;font-size:12.5px;color:#6a0f70;">
                ${parseFloat(line.qty_ordered)}
            </div>
            <div style="text-align:right;">
                <input type="number" name="lines[${idx}][qty]"
                       value="${remaining > 0 ? remaining : 0}"
                       min="0" max="${remaining}" step="0.01"
                       style="width:70px;padding:5px 8px;border:1px solid rgba(26,122,69,0.25);
                              border-radius:3px;font-size:12px;font-family:'DM Mono',monospace;
                              text-align:right;">
            </div>
            <div style="text-align:center;">
                <input type="text" name="lines[${idx}][batch_no]" placeholder="Batch"
                       style="width:80px;padding:5px 8px;border:1px solid rgba(185,92,183,0.2);
                              border-radius:3px;font-size:11px;font-family:'DM Mono',monospace;">
            </div>
            <div style="text-align:center;">
                <input type="date" name="lines[${idx}][expiry]"
                       style="width:120px;padding:5px 8px;border:1px solid rgba(185,92,183,0.2);
                              border-radius:3px;font-size:11px;">
            </div>
        </div>`;
    }).join('') || '<div style="padding:24px;text-align:center;color:#9a85aa;font-size:13px;">No items on this PO.</div>';

    document.getElementById('modal-grn').style.display = 'flex';
}

// ─── Modal backdrop close ─────────────────────────────────────
document.getElementById('modal-create-po').addEventListener('click', function(e){
    if(e.target === this) this.style.display='none';
});
document.getElementById('modal-po-detail').addEventListener('click', function(e){
    if(e.target === this) this.style.display='none';
});
document.getElementById('modal-grn').addEventListener('click', function(e){
    if(e.target === this) this.style.display='none';
});

// ─── Form guard: require at least 1 line item ─────────────────
document.getElementById('po-form').addEventListener('submit', function(e) {
    const lines = document.querySelectorAll('.po-line');
    if (lines.length === 0) {
        e.preventDefault();
        alert('Please add at least one item to the purchase order.');
    }
});
</script>
@endpush
