@extends('layouts.app')
@section('page-title', 'Inventory — Purchase Orders')
@section('content')

<div class="df-page-header">
    <div>
        <div class="df-page-title" style="font-size:22px;">Inventory</div>
        <div class="df-page-subtitle">Purchase Orders · {{ $orders->total() }} orders</div>
    </div>
    <div class="df-page-actions">
        <a href="{{ route('inventory.vendors') }}"
            style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#fff;color:#6a0f70;border:1px solid rgba(106,15,112,0.25);border-radius:3px;font-size:13px;font-weight:500;text-decoration:none;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Manage Vendors
        </a>
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

                // GRN correction window check — is the latest GRN still within the window?
                $canUndo = false;
                if ($grnWindowHours > 0 && in_array($po->status, ['partially_received', 'completed'])) {
                    $latestGrn = $po->grns()->latest()->first();
                    if ($latestGrn && $latestGrn->created_at->gt(now()->subHours($grnWindowHours))) {
                        $canUndo = true;
                    }
                }
            @endphp
            <tr style="border-bottom:1px solid rgba(185,92,183,0.05);transition:background 120ms;"
                onmouseover="this.style.background='#faf5fb'" onmouseout="this.style.background=''">
                <td style="padding:11px 18px;cursor:pointer;" onclick="openPoDetail({{ $po->id }})">
                    <div style="font-family:'Inter', sans-serif;font-weight:600;color:#6a0f70;font-size:13px;">{{ $po->order_no }}</div>
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
                <td style="padding:11px 14px;text-align:right;font-family:'Inter', sans-serif;font-weight:600;color:#1e0a2c;font-size:13px;cursor:pointer;" onclick="openPoDetail({{ $po->id }})">
                    Rs. {{ number_format($po->total_amount, 0) }}
                    @if($po->gst_amount > 0)<div style="font-size:10.5px;color:#9a85aa;font-weight:400;">incl. GST Rs. {{ number_format($po->gst_amount,0) }}</div>@endif
                </td>
                <td style="padding:11px 18px;text-align:center;">
                    @if($po->status === 'draft')
                        {{-- Clickable dashed badge → mark as Ordered --}}
                        <form method="POST" action="{{ route('inventory.purchase.markOrdered', $po) }}" style="display:inline;">
                            @csrf @method('PATCH')
                            <button type="submit" title="Click to mark as Ordered"
                                style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:500;background:{{ $statusBg }};color:{{ $statusColor }};border:1.5px dashed {{ $statusColor }};cursor:pointer;font-family:inherit;">
                                {{ $po->getStatusLabel() }}
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:.7;"><polyline points="20 6 9 17 4 12"/></svg>
                            </button>
                        </form>
                    @else
                        <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:500;background:{{ $statusBg }};color:{{ $statusColor }};">
                            {{ $po->getStatusLabel() }}
                        </span>
                    @endif
                </td>
                <td style="padding:11px 14px;text-align:center;">
                    <div style="display:flex;gap:6px;justify-content:center;align-items:center;">
                        @if($canReceive)
                        <button onclick='openGrn({{ $po->toJson() }})'
                                style="background:#e8f7ee;border:1px solid rgba(26,122,69,0.2);
                                       border-radius:4px;padding:5px 12px;font-size:12px;
                                       font-family:'Inter',sans-serif;color:#1a7a45;
                                       cursor:pointer;font-weight:500;white-space:nowrap;">
                            ↓ Receive
                        </button>
                        @endif
                        @if(in_array($po->status, ['draft','ordered']) && $po->vendor?->whatsapp)
                        <button onclick="sendWhatsApp({{ $po->id }})" title="Send order via WhatsApp"
                                style="background:#e8f7ef;border:1px solid rgba(37,211,102,0.3);
                                       border-radius:4px;padding:5px 10px;font-size:12px;
                                       color:#128C7E;cursor:pointer;display:flex;align-items:center;gap:4px;white-space:nowrap;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.122.554 4.11 1.522 5.835L.057 23.625a.5.5 0 0 0 .618.618l5.79-1.465A11.95 11.95 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22a9.963 9.963 0 0 1-5.03-1.358l-.36-.214-3.733.945.962-3.63-.235-.374A9.96 9.96 0 0 1 2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>
                            WA
                        </button>
                        @endif

                        {{-- Three-dot trigger --}}
                        {{-- deleteMode: 'none' | 'draft' | 'ordered_admin' --}}
                        @php
                            $deleteMode = match($po->status) {
                                'draft'              => 'draft',
                                'ordered'            => ($isAdmin ? 'ordered_admin' : 'none'),
                                default              => 'none',
                            };
                        @endphp
                        <button onclick="togglePoMenu(event, {{ $po->id }},
                                    {{ (int)($po->status !== 'cancelled') }},
                                    '{{ $deleteMode }}',
                                    {{ (int)$canUndo }},
                                    '{{ $po->vendor_id }}',
                                    '{{ $po->order_date?->format('Y-m-d') }}',
                                    '{{ $po->expected_date?->format('Y-m-d') }}',
                                    {{ json_encode($po->notes) }},
                                    '{{ $po->order_no }}')"
                                style="background:#f5f0f8;border:1px solid #ede4f3;border-radius:4px;
                                       padding:5px 9px;cursor:pointer;color:#6a0f70;font-size:16px;
                                       line-height:1;display:flex;align-items:center;font-weight:700;">
                            ⋮
                        </button>
                    </div>
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
                {{-- Always saves as Draft — status promoted from the list --}}
                <input type="hidden" name="status" value="draft">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:20px;">
                    <div style="grid-column:1/3;">
                        <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">Vendor *</label>
                        <select name="vendor_id" required
                            style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;color:#1e0a2c;outline:none;box-sizing:border-box;">
                            <option value="">— Select vendor —</option>
                            @foreach($vendors as $v)
                            <option value="{{ $v->id }}">{{ $v->vendor_name }}{{ $v->contact_person ? ' · '.$v->contact_person : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:center;">
                        <div style="padding:8px 12px;background:#fff4e0;border:1px solid rgba(160,92,0,0.2);border-radius:3px;font-size:12px;color:#a05c00;display:flex;align-items:center;gap:6px;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            Saves as <strong>Draft</strong>
                        </div>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">Order Date *</label>
                        <input type="date" name="order_date" required value="{{ date('Y-m-d') }}"
                            style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">Expected Delivery</label>
                        <input type="date" name="expected_date"
                            style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:5px;">Notes</label>
                        <input type="text" name="notes" placeholder="Remarks or reference…"
                            style="width:100%;padding:8px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;">
                    </div>
                </div>

                {{-- Line items --}}
                <div style="border-top:1px solid rgba(185,92,183,0.10);padding-top:18px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span style="font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#4e0a53;">Order Items</span>
                            {{-- Low/critical stock filter toggle --}}
                            <label style="display:flex;align-items:center;gap:5px;font-size:11.5px;color:#9a85aa;cursor:pointer;user-select:none;" title="Show only items at or below reorder/minimum level">
                                <input type="checkbox" id="show-all-items" onchange="toggleItemFilter()"
                                    style="accent-color:#6a0f70;cursor:pointer;">
                                Show healthy stock too
                            </label>
                            <span id="low-stock-badge" style="font-size:10.5px;background:#fdeaea;color:#b52020;padding:2px 7px;border-radius:10px;font-weight:600;"></span>
                        </div>
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
                            <span id="po-subtotal" style="font-family:'Inter', sans-serif;min-width:80px;text-align:right;">Rs. 0</span>
                        </div>
                        <div style="display:flex;gap:48px;font-size:12.5px;color:#9a85aa;">
                            <span>GST</span>
                            <span id="po-gst" style="font-family:'Inter', sans-serif;min-width:80px;text-align:right;">Rs. 0</span>
                        </div>
                        <div style="display:flex;gap:48px;font-size:14px;font-weight:700;color:#1e0a2c;border-top:1px solid rgba(185,92,183,0.10);padding-top:6px;margin-top:4px;">
                            <span>Grand Total</span>
                            <span id="po-grand" style="font-family:'Inter', sans-serif;min-width:80px;text-align:right;color:#6a0f70;">Rs. 0</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer actions --}}
            <div style="padding:16px 24px;border-top:1px solid rgba(185,92,183,0.08);background:#faf5fb;border-radius:0 0 6px 6px;display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-create-po').style.display='none'"
                    style="padding:9px 20px;background:#fff;color:#6a0f70;border:1px solid rgba(106,15,112,0.25);border-radius:3px;font-size:13px;cursor:pointer;font-family:'Inter',sans-serif;">
                    Cancel
                </button>
                <button type="submit" id="po-submit-btn"
                    style="padding:9px 22px;background:#6a0f70;color:#fff;border:none;border-radius:3px;font-size:13px;font-weight:500;cursor:pointer;font-family:'Inter',sans-serif;">
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
{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- SHARED FLOATING THREE-DOT MENU (position:fixed, outside table) --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div id="po-floating-menu"
     style="display:none;position:fixed;background:#fff;border:1px solid #ede4f3;
            border-radius:8px;box-shadow:0 6px 24px rgba(14,1,24,.15);z-index:9999;
            min-width:148px;overflow:hidden;padding:4px 0;">
    <button id="pmenu-edit"
            style="display:none;width:100%;text-align:left;padding:10px 16px;background:none;
                   border:none;font-size:13px;color:#1e0a2c;cursor:pointer;font-family:'Inter',sans-serif;"
            onmouseover="this.style.background='#faf5fb'" onmouseout="this.style.background='none'">
        ✏️ Edit
    </button>
    <button id="pmenu-print"
            style="width:100%;text-align:left;padding:10px 16px;background:none;
                   border:none;font-size:13px;color:#1e0a2c;cursor:pointer;font-family:'Inter',sans-serif;"
            onmouseover="this.style.background='#faf5fb'" onmouseout="this.style.background='none'">
        Print
    </button>
    <button id="pmenu-undo"
            style="display:none;width:100%;text-align:left;padding:10px 16px;background:none;
                   border:none;font-size:13px;color:#a05c00;cursor:pointer;font-family:'Inter',sans-serif;"
            onmouseover="this.style.background='#fff4e0'" onmouseout="this.style.background='none'">
        ↩ Undo Last Receipt
    </button>
    <div id="pmenu-divider" style="display:none;height:1px;background:#f0eaf5;margin:4px 0;"></div>
    <button id="pmenu-delete"
            style="display:none;width:100%;text-align:left;padding:10px 16px;background:none;
                   border:none;font-size:13px;color:#b52020;cursor:pointer;font-family:'Inter',sans-serif;"
            onmouseover="this.style.background='#fdeaea'" onmouseout="this.style.background='none'">
        Delete
    </button>
</div>

{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- EDIT PO MODAL (header fields: vendor, dates, notes)        --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div id="modal-edit-po" style="display:none;position:fixed;inset:0;z-index:60;
     align-items:center;justify-content:center;padding:24px 16px;
     background:rgba(14,1,24,0.55);backdrop-filter:blur(3px);">
    <div style="background:#fff;border-radius:6px;width:100%;max-width:520px;
                box-shadow:0 20px 60px rgba(14,1,24,0.25);">

        <div style="display:flex;align-items:center;justify-content:space-between;
                    padding:16px 22px;border-bottom:1px solid #ede4f3;background:#faf5fb;border-radius:6px 6px 0 0;">
            <div style="font-family:'Cormorant Garamond',serif;font-size:19px;font-weight:600;color:#1a0320;">
                Edit Purchase Order
            </div>
            <button onclick="document.getElementById('modal-edit-po').style.display='none'"
                    style="background:none;border:none;cursor:pointer;color:#9a7aaa;">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <form id="edit-po-form" method="POST" action="" style="padding:22px;">
            @csrf
            @method('PATCH')

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                <div style="grid-column:1/-1;">
                    <label style="display:block;font-size:12px;font-weight:500;color:#6a0f70;margin-bottom:5px;">Vendor *</label>
                    <select name="vendor_id" id="edit-po-vendor" required
                            style="width:100%;padding:8px 12px;border:1px solid #ede4f3;border-radius:4px;
                                   font-size:13px;font-family:'Inter',sans-serif;color:#1e0a2c;outline:none;box-sizing:border-box;">
                        @foreach(\App\Models\Inventory\InventoryVendor::orderBy('vendor_name')->get() as $v)
                        <option value="{{ $v->id }}">{{ $v->vendor_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#6a0f70;margin-bottom:5px;">Order Date *</label>
                    <input type="date" name="order_date" id="edit-po-order-date" required
                           style="width:100%;padding:8px 12px;border:1px solid #ede4f3;border-radius:4px;
                                  font-size:13px;font-family:'Inter',sans-serif;color:#1e0a2c;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#6a0f70;margin-bottom:5px;">Expected Date</label>
                    <input type="date" name="expected_date" id="edit-po-expected-date"
                           style="width:100%;padding:8px 12px;border:1px solid #ede4f3;border-radius:4px;
                                  font-size:13px;font-family:'Inter',sans-serif;color:#1e0a2c;outline:none;box-sizing:border-box;">
                </div>
                <div style="grid-column:1/-1;">
                    <label style="display:block;font-size:12px;font-weight:500;color:#6a0f70;margin-bottom:5px;">Notes</label>
                    <textarea name="notes" id="edit-po-notes" rows="3"
                              style="width:100%;padding:8px 12px;border:1px solid #ede4f3;border-radius:4px;
                                     font-size:13px;font-family:'Inter',sans-serif;color:#1e0a2c;
                                     outline:none;box-sizing:border-box;resize:vertical;"></textarea>
                </div>
            </div>

            <p style="font-size:11.5px;color:#9a7aaa;margin:0 0 16px;">
                ℹ️ Only header fields (vendor, dates, notes) can be changed. Line items are locked after PO creation.
            </p>

            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-edit-po').style.display='none'"
                        style="padding:8px 18px;background:#fff;color:#6a0f70;border:1px solid #ede4f3;
                               border-radius:4px;font-size:13px;cursor:pointer;font-family:'Inter',sans-serif;">
                    Cancel
                </button>
                <button type="submit"
                        style="padding:8px 20px;background:#6a0f70;color:#fff;border:none;
                               border-radius:4px;font-size:13px;font-weight:500;cursor:pointer;font-family:'Inter',sans-serif;">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Hidden delete form (submitted via JS) --}}
<form id="delete-po-form" method="POST" action="" style="display:none;">
    @csrf
    @method('DELETE')
    <input type="hidden" name="delete_reason" id="delete-po-reason-val">
</form>

{{-- Hidden undo-GRN form (submitted via JS) --}}
<form id="undo-grn-form" method="POST" action="" style="display:none;">
    @csrf
    @method('DELETE')
</form>

{{-- DELETE PO CONFIRMATION MODAL --}}
<div id="modal-delete-po" style="display:none;position:fixed;inset:0;z-index:9998;
     align-items:center;justify-content:center;padding:24px 16px;
     background:rgba(14,1,24,0.60);backdrop-filter:blur(3px);">
    <div style="background:#fff;border-radius:8px;width:100%;max-width:440px;
                box-shadow:0 20px 60px rgba(14,1,24,0.25);">

        {{-- Header --}}
        <div style="display:flex;align-items:center;gap:12px;
                    padding:16px 20px;border-bottom:1px solid #fde8e8;
                    background:#fff8f8;border-radius:8px 8px 0 0;">
            <div>
                <div style="font-size:15px;font-weight:600;color:#1e0a2c;">Delete Purchase Order</div>
                <div id="del-po-subtitle" style="font-size:12px;color:#b52020;margin-top:2px;"></div>
            </div>
        </div>

        <div style="padding:20px;">
            <div id="del-po-warning" style="font-size:13px;color:#4a2a2a;background:#fdf4f4;
                 border:1px solid #f5cccc;border-radius:6px;padding:12px 14px;margin-bottom:16px;line-height:1.5;">
            </div>

            <label style="display:block;font-size:12px;font-weight:600;color:#b52020;margin-bottom:6px;">
                Reason for deletion <span style="color:#b52020;">*</span>
            </label>
            <textarea id="del-po-reason" rows="3" placeholder="Explain why this PO is being deleted…"
                      style="width:100%;padding:9px 12px;border:1.5px solid #f5cccc;border-radius:5px;
                             font-size:13px;font-family:'Inter',sans-serif;color:#1e0a2c;
                             outline:none;box-sizing:border-box;resize:vertical;"
                      oninput="document.getElementById('del-po-submit').disabled=this.value.trim().length<5"></textarea>
            <div style="font-size:11px;color:#9a7aaa;margin-top:4px;">Minimum 5 characters required</div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:18px;">
                <button type="button"
                        onclick="document.getElementById('modal-delete-po').style.display='none'"
                        style="padding:8px 18px;background:#fff;color:#6a0f70;border:1px solid #ede4f3;
                               border-radius:5px;font-size:13px;cursor:pointer;font-family:'Inter',sans-serif;">
                    Cancel
                </button>
                <button id="del-po-submit" disabled onclick="submitDeletePo()"
                        style="padding:8px 20px;background:#b52020;color:#fff;border:none;
                               border-radius:5px;font-size:13px;font-weight:600;
                               cursor:pointer;font-family:'Inter',sans-serif;
                               opacity:0.5;transition:opacity 150ms;"
                        onmouseover="if(!this.disabled)this.style.background='#8a1818'"
                        onmouseout="this.style.background='#b52020'">
                    Delete PO
                </button>
            </div>
        </div>
    </div>
</div>

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
                    Receive Stock — <span id="grn-po-no" style="font-family:'Inter', sans-serif;"></span>
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
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:20px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#1a7a45;margin-bottom:5px;">
                        Receive Into Location *
                    </label>
                    <select name="location_id" required
                            style="width:100%;padding:8px 12px;border:1px solid rgba(26,122,69,0.25);
                                   border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;
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
                                  border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;
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

            {{-- Vendor Invoice Section — highlighted so staff can't miss it --}}
            <div style="background:#fffbf0;border:1.5px solid #f0d080;border-radius:8px;
                        padding:16px 18px;margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                    <svg width="16" height="16" fill="none" stroke="#a05c00" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                    <span style="font-size:13px;font-weight:600;color:#7a4500;">Vendor Invoice</span>
                    <span style="font-size:11px;color:#b07a30;background:#fff4d0;padding:2px 8px;border-radius:10px;">
                        Creates unpaid expense in Finance
                    </span>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#7a4500;margin-bottom:5px;">
                            Vendor Invoice No.
                            <span style="font-weight:400;color:#b07a30;">(leave blank if not received yet)</span>
                        </label>
                        <input type="text" name="vendor_invoice_no" id="grn-invoice-no"
                               placeholder="e.g. INV-2026-089" maxlength="80"
                               style="width:100%;padding:9px 12px;border:1.5px solid #e8c860;
                                      border-radius:5px;font-size:13px;font-family:'Inter',sans-serif;
                                      color:#1e0a2c;outline:none;box-sizing:border-box;background:#fff;">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#7a4500;margin-bottom:5px;">
                            Invoice Amount (₹)
                            <span style="font-weight:400;color:#b07a30;">(auto-calculated if blank)</span>
                        </label>
                        <input type="number" name="vendor_invoice_amount" id="grn-invoice-amount"
                               placeholder="Auto" min="0" step="0.01"
                               style="width:100%;padding:9px 12px;border:1.5px solid #e8c860;
                                      border-radius:5px;font-size:13px;font-family:'Inter',sans-serif;
                                      color:#1e0a2c;outline:none;box-sizing:border-box;background:#fff;">
                        <div style="font-size:11px;color:#b07a30;margin-top:4px;">
                            If vendor invoice total differs from PO price, enter the actual amount billed.
                        </div>
                    </div>
                </div>
                <div id="grn-no-invoice-notice" style="display:none;margin-top:10px;font-size:12px;color:#b07a30;">
                    No invoice number entered — expense will be created as a draft in Finance. You can add the invoice number later.
                </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button"
                        onclick="document.getElementById('modal-grn').style.display='none'"
                        style="padding:9px 20px;background:#fff;color:#1a7a45;
                               border:1px solid rgba(26,122,69,0.3);border-radius:3px;
                               font-size:13px;cursor:pointer;font-family:'Inter',sans-serif;">
                    Cancel
                </button>
                <button type="submit"
                        style="padding:9px 22px;background:#1a7a45;color:#fff;border:none;
                               border-radius:3px;font-size:13px;font-weight:500;cursor:pointer;
                               font-family:'Inter',sans-serif;display:flex;align-items:center;gap:6px;">
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
const LOW_STOCK_IDS = new Set(@json($lowStockItemIds));
const ALL_VENDORS   = @json($vendors);

ALL_ITEMS.forEach(it => { it._isLow = LOW_STOCK_IDS.has(it.id); });
let showAllItems = false;

// ─── Populate low-stock badge ──────────────────────────────────
(function() {
    const lowCount = ALL_ITEMS.filter(it => it._isLow).length;
    const badge    = document.getElementById('low-stock-badge');
    if (badge) badge.textContent = lowCount > 0 ? lowCount + ' need reorder' : '';
})();

// ─── Toggle: show healthy stock too ───────────────────────────
function toggleItemFilter() {
    showAllItems = document.getElementById('show-all-items').checked;
    document.querySelectorAll('.po-line select').forEach(sel => {
        const currentVal = sel.value;
        sel.innerHTML = buildItemOptions();
        sel.value = currentVal;
    });
}

// Distinguish items that share a base name (e.g. three "Gloves" rows for
// different sub-type/size combos) — same "Category › SubType · Variant" style
// used on the Products list, plus the item code.
function itemLabel(it) {
    let label = it.product_name;
    const details = [];
    if (it.sub_type?.name) details.push(it.sub_type.name);
    if (it.variant?.name) details.push(it.variant.name);
    if (details.length) label += ' (' + details.join(' · ') + ')';
    return label;
}

function buildItemOptions() {
    const items = showAllItems ? ALL_ITEMS : ALL_ITEMS.filter(it => it._isLow);
    const list = items.length === 0 ? ALL_ITEMS : items;
    return list.map(it =>
        `<option value="${it.id}" data-price="${it.last_purchase_price || 0}" data-unit="${it.purchase_unit || ''}">${itemLabel(it)}</option>`
    ).join('');
}

// ─── PO Line management ────────────────────────────────────────
let lineIndex = 0;

function addPoLine() {
    const container = document.getElementById('po-lines');
    const idx = lineIndex++;
    const row = document.createElement('div');
    row.className = 'po-line';
    row.dataset.idx = idx;
    row.style.cssText = 'display:grid;grid-template-columns:1fr 90px 100px 72px 88px 32px;gap:8px;align-items:center;margin-bottom:6px;';

    const itemOptions = buildItemOptions();
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

    const statusColors = { draft:'#a05c00', ordered:'#1a5ea8', partially_received:'#7a4a00', completed:'#1a7a45', cancelled:'#b52020' };
    const statusBgs    = { draft:'#fff4e0', ordered:'#e6f0fb', partially_received:'#fff8ec',  completed:'#e8f7ef',  cancelled:'#fdeaea' };
    const statusLabels = { draft:'Draft',   ordered:'Ordered', partially_received:'Partial',  completed:'Completed',cancelled:'Cancelled' };
    const color = statusColors[po.status] || '#555';
    const bg    = statusBgs[po.status]    || '#f4f4f4';
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
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
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
                    <span style="font-family:'DM Mono',monospace;min-width:90px;text-align:right;">₹${Math.round(parseFloat(po.gst_amount||0)).toLocaleString()}</span>
                </div>
                <div style="display:flex;gap:48px;font-size:14px;font-weight:700;color:#1e0a2c;">
                    <span>Grand Total</span>
                    <span style="font-family:'DM Mono',monospace;min-width:90px;text-align:right;color:#6a0f70;">₹${Math.round(parseFloat(po.total_amount||0)).toLocaleString()}</span>
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
    document.getElementById('grn-po-no').textContent       = po.order_no || '';
    document.getElementById('grn-vendor-name').textContent = po.vendor ? po.vendor.vendor_name : '';

    // Reset vendor invoice fields (IDs as in HTML)
    const invNoEl     = document.getElementById('grn-invoice-no');
    const invAmtEl    = document.getElementById('grn-invoice-amount');
    const noInvNotice = document.getElementById('grn-no-invoice-notice');
    if (invNoEl)     invNoEl.value     = '';
    if (invAmtEl)    invAmtEl.value    = '';
    if (noInvNotice) noInvNotice.style.display = 'none';

    // Set form action
    document.getElementById('grn-form').action = '/inventory/purchase/' + po.id + '/receive';

    // Build line-item rows — integers only for received qty
    const lines   = po.items || [];
    const linesEl = document.getElementById('grn-lines');

    linesEl.innerHTML = lines.map((line, idx) => {
        const remaining = Math.floor(parseFloat(line.qty_ordered) - parseFloat(line.qty_received || 0));
        const itemName  = line.item ? line.item.product_name : 'Item #' + line.inventory_item_id;
        return `
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr;gap:8px;
                    padding:10px 14px;border-bottom:1px solid rgba(26,122,69,0.06);align-items:center;">
            <div>
                <div style="font-size:13px;font-weight:500;color:#1e0a2c;">${itemName}</div>
                <input type="hidden" name="lines[${idx}][item_id]" value="${line.inventory_item_id}">
                ${remaining < Math.floor(parseFloat(line.qty_ordered))
                    ? `<div style="font-size:10.5px;color:#a05c00;">Already received: ${Math.floor(parseFloat(line.qty_received||0))}</div>`
                    : ''}
            </div>
            <div style="text-align:right;font-family:'DM Mono',monospace;font-size:12.5px;color:#6a0f70;">
                ${Math.floor(parseFloat(line.qty_ordered))}
            </div>
            <div style="text-align:right;">
                <input type="number" name="lines[${idx}][qty]"
                       value="${remaining > 0 ? remaining : 0}"
                       min="0" max="${remaining}" step="1"
                       oninput="this.value=Math.floor(Math.abs(this.value))||''"
                       style="width:70px;padding:5px 8px;border:1px solid rgba(26,122,69,0.25);
                              border-radius:3px;font-size:12px;font-family:'DM Mono',monospace;text-align:right;">
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

// ─── Invoice No. blur: show notice if left empty ──────────────
document.addEventListener('blur', function(e) {
    if (e.target && e.target.id === 'grn-invoice-no') {
        const notice = document.getElementById('grn-no-invoice-notice');
        if (notice) notice.style.display = e.target.value.trim() === '' ? 'block' : 'none';
    }
}, true);

// ─── Three-dot PO menu ────────────────────────────────────────
let _menuPoId = null, _menuCanEdit = false, _menuDeleteMode = 'none',
    _menuCanUndo = false, _menuVendorId = null,
    _menuOrderDate = '', _menuExpectedDate = '', _menuNotes = '', _menuOrderNo = '';

// Wire up the static menu buttons to their handler functions
document.addEventListener('DOMContentLoaded', function() {
    const btnEdit  = document.getElementById('pmenu-edit');
    const btnPrint = document.getElementById('pmenu-print');
    const btnUndo  = document.getElementById('pmenu-undo');
    const btnDel   = document.getElementById('pmenu-delete');
    if (btnEdit)  btnEdit.addEventListener('click',  () => openPoEdit());
    if (btnPrint) btnPrint.addEventListener('click', () => printPo(_menuPoId));
    if (btnUndo)  btnUndo.addEventListener('click',  () => undoLastGrn());
    if (btnDel)   btnDel.addEventListener('click',   () => openDeletePoModal());

    // Delete reason enables submit
    const delReason = document.getElementById('del-po-reason');
    if (delReason) {
        delReason.addEventListener('input', function() {
            const btn = document.getElementById('del-po-submit');
            if (btn) {
                btn.disabled = this.value.trim().length < 5;
                btn.style.opacity = btn.disabled ? '0.5' : '1';
            }
        });
    }
});

function togglePoMenu(e, id, canEdit, deleteMode, canUndo, vendorId, orderDate, expectedDate, notes, orderNo) {
    e.stopPropagation();
    const menu = document.getElementById('po-floating-menu');
    // Toggle close if same button clicked again
    if (menu.style.display === 'block' && _menuPoId === id) {
        menu.style.display = 'none';
        return;
    }

    _menuPoId         = id;
    _menuCanEdit      = canEdit;
    _menuDeleteMode   = deleteMode;
    _menuCanUndo      = canUndo;
    _menuVendorId     = vendorId;
    _menuOrderDate    = orderDate;
    _menuExpectedDate = expectedDate;
    _menuNotes        = notes;
    _menuOrderNo      = orderNo;

    // Position the floating menu below the trigger button
    const rect = e.currentTarget.getBoundingClientRect();
    menu.style.top  = (rect.bottom + window.scrollY + 4) + 'px';
    menu.style.left = (rect.right  - 160 + window.scrollX) + 'px';

    // Show/hide items based on permissions
    const editBtn = document.getElementById('pmenu-edit');
    const undoBtn = document.getElementById('pmenu-undo');
    const divider = document.getElementById('pmenu-divider');
    const delBtn  = document.getElementById('pmenu-delete');

    if (editBtn) editBtn.style.display = canEdit ? 'block' : 'none';
    if (undoBtn) undoBtn.style.display = canUndo  ? 'block' : 'none';
    if (delBtn)  delBtn.style.display  = (deleteMode !== 'none') ? 'block' : 'none';
    if (divider) divider.style.display = (deleteMode !== 'none') ? 'block' : 'none';

    menu.style.display = 'block';
}

// Close floating menu when clicking outside
document.addEventListener('click', function(e) {
    const menu = document.getElementById('po-floating-menu');
    if (menu && !menu.contains(e.target)) menu.style.display = 'none';
});

function printPo(id) {
    document.getElementById('po-floating-menu').style.display = 'none';
    window.open('/inventory/purchase/' + id + '/print', '_blank');
}

function openPoEdit() {
    document.getElementById('po-floating-menu').style.display = 'none';
    if (!_menuPoId) return;

    // Populate vendor select
    const vendorSel = document.getElementById('edit-po-vendor');
    if (vendorSel) {
        vendorSel.innerHTML = ALL_VENDORS.map(v =>
            `<option value="${v.id}" ${v.id == _menuVendorId ? 'selected' : ''}>${v.vendor_name}</option>`
        ).join('');
    }

    const setVal = (elId, val) => { const el = document.getElementById(elId); if (el) el.value = val || ''; };
    setVal('edit-po-order-date',    _menuOrderDate);
    setVal('edit-po-expected-date', _menuExpectedDate);
    setVal('edit-po-notes',         _menuNotes);

    const form = document.getElementById('edit-po-form');
    if (form) form.action = '/inventory/purchase/' + _menuPoId;

    document.getElementById('modal-edit-po').style.display = 'flex';
}

function openDeletePoModal() {
    document.getElementById('po-floating-menu').style.display = 'none';
    if (!_menuPoId || _menuDeleteMode === 'none') return;

    // Reset reason field and disable submit
    const reasonEl = document.getElementById('del-po-reason');
    const submitEl = document.getElementById('del-po-submit');
    if (reasonEl) reasonEl.value = '';
    if (submitEl) { submitEl.disabled = true; submitEl.style.opacity = '0.5'; }

    const form = document.getElementById('delete-po-form');
    if (form) form.action = '/inventory/purchase/' + _menuPoId;

    document.getElementById('modal-delete-po').style.display = 'flex';
}

function submitDeletePo() {
    const reason = (document.getElementById('del-po-reason')?.value || '').trim();
    if (reason.length < 5) { alert('Please enter a reason (at least 5 characters).'); return; }
    // Copy reason into the hidden form input before submitting
    const hiddenInput = document.getElementById('delete-po-reason-val');
    if (hiddenInput) hiddenInput.value = reason;
    document.getElementById('delete-po-form').submit();
}

function undoLastGrn() {
    document.getElementById('po-floating-menu').style.display = 'none';
    if (!_menuPoId) return;
    if (!confirm('Undo the last goods receipt for ' + _menuOrderNo + '?\n\nThis will reverse the stock movement and void the related Finance expense.')) return;
    const form = document.getElementById('undo-grn-form');
    if (form) {
        form.action = '/inventory/purchase/' + _menuPoId + '/grn/last';
        form.submit();
    }
}

// ─── WhatsApp order sender ────────────────────────────────────
function sendWhatsApp(poId) {
    const po = PO_DATA.find(p => p.id === poId);
    if (!po) return;

    const vendor   = po.vendor;
    const rawPhone = vendor?.whatsapp || vendor?.phone || '';
    if (!rawPhone) {
        alert('No WhatsApp number found for this vendor.\nAdd one via Inventory → Vendors.');
        return;
    }

    const phone = rawPhone.replace(/[\s\-\(\)]/g, '').replace(/^\+/, '');
    const lines = (po.items || []).map(line => {
        const name  = line.item ? line.item.product_name : 'Item #' + line.inventory_item_id;
        const qty   = parseFloat(line.qty_ordered || 0);
        const price = parseFloat(line.unit_price  || 0);
        return `• ${name} — Qty: ${qty}${price > 0 ? ' @ ₹' + price.toFixed(2) : ''}`;
    }).join('\n');

    const contactName = vendor.contact_person || vendor.vendor_name || 'Sir/Madam';
    const total = Math.round(parseFloat(po.total_amount || 0)).toLocaleString('en-IN');

    const msg =
`Hi ${contactName},

Please process the following Purchase Order from our clinic:

*PO No:* ${po.order_no}
*Date:* ${po.order_date || '—'}

*Order Items:*
${lines}

*Grand Total:* ₹${total}${po.notes ? '\n\n*Notes:* ' + po.notes : ''}

Kindly confirm receipt and expected delivery date.

Thank you,
Dentfluence Dental Clinic`;

    window.open('https://wa.me/' + phone + '?text=' + encodeURIComponent(msg), '_blank');
}

// ─── Modal backdrop closers ───────────────────────────────────
['modal-create-po','modal-po-detail','modal-grn','modal-edit-po','modal-delete-po'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('click', function(e){ if (e.target === this) this.style.display = 'none'; });
});

// ─── Form guard: require at least 1 line item ─────────────────
document.getElementById('po-form').addEventListener('submit', function(e) {
    if (document.querySelectorAll('.po-line').length === 0) {
        e.preventDefault();
        alert('Please add at least one item to the purchase order.');
    }
});
</script>
@endpush
                                                                                                                                                                                                                                                                                                                                                                                                                                                                      