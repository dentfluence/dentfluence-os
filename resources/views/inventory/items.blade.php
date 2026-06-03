{{--
|===========================================================================
| Inventory Stock View
| Shows current stock levels per product/location with quick +/- adjust.
| Products are managed in Product Master, not here.
|===========================================================================
--}}
@extends('layouts.app')
@section('title', 'Inventory Stock')

@section('content')
@include('inventory.partials.subnav')

{{-- ── Page header ── --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
    <div>
        <h1 style="font-family:'Cormorant Garamond',serif;font-size:26px;font-weight:600;
                   color:#1a0a1e;margin:0 0 2px;">Inventory Stock</h1>
        <p style="font-family:'DM Sans',sans-serif;font-size:13px;color:#7a6884;margin:0;">
            Current stock levels — use +/− to make quick adjustments
        </p>
    </div>
    <a href="{{ route('inventory.products') }}"
       style="border:1px solid rgba(106,15,112,0.25);color:#6a0f70;border-radius:6px;
              padding:8px 16px;font-size:13px;font-family:'DM Sans',sans-serif;
              text-decoration:none;display:flex;align-items:center;gap:6px;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 12h8M12 8v8"/></svg>
        Manage Products
    </a>
</div>

{{-- Flash messages --}}
@if(session('success'))
<div style="background:#e8f7ee;border:1px solid #a3d9b8;color:#1a7a45;padding:10px 16px;
            border-radius:6px;font-size:13px;font-family:'DM Sans',sans-serif;margin-bottom:16px;">
    ✓ {{ session('success') }}
</div>
@endif
@if($errors->any())
<div style="background:#fdeaea;border:1px solid #f5c6c6;color:#b52020;padding:10px 16px;
            border-radius:6px;font-size:13px;font-family:'DM Sans',sans-serif;margin-bottom:16px;">
    @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
</div>
@endif

{{-- ── Legend ── --}}
<div style="display:flex;gap:16px;margin-bottom:14px;align-items:center;flex-wrap:wrap;">
    <span style="font-size:12px;font-family:'DM Sans',sans-serif;color:#9a85aa;">Stock levels:</span>
    <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-family:'DM Sans',sans-serif;">
        <span style="width:10px;height:10px;border-radius:50%;background:#1a7a45;display:inline-block;"></span>
        <span style="color:#1a7a45;">Healthy</span>
    </span>
    <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-family:'DM Sans',sans-serif;">
        <span style="width:10px;height:10px;border-radius:50%;background:#a05c00;display:inline-block;"></span>
        <span style="color:#a05c00;">Low (at or below minimum)</span>
    </span>
    <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-family:'DM Sans',sans-serif;">
        <span style="width:10px;height:10px;border-radius:50%;background:#b52020;display:inline-block;"></span>
        <span style="color:#b52020;">Critical / Out of stock</span>
    </span>
</div>

{{-- ── Stock table ── --}}
<div style="background:#fff;border:1px solid rgba(185,92,183,0.12);border-radius:8px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="background:#faf5fb;border-bottom:1px solid rgba(185,92,183,0.10);">
                <th style="padding:10px 18px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Product</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Location</th>
                <th style="padding:10px 14px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Current Stock</th>
                <th style="padding:10px 14px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Min / Reorder</th>
                <th style="padding:10px 18px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Adjust</th>
            </tr>
        </thead>
        <tbody>
            @forelse($stockRows as $row)
            @php
                $qty     = (float) ($row->available_qty ?? 0);
                $minQty  = (float) ($row->minimum_qty ?? 0);
                $reorder = (float) ($row->reorder_level ?? 0);

                if ($qty <= 0) {
                    $dot = '#b52020'; $bg = '#fdeaea'; $fg = '#b52020'; $label = 'Out of Stock';
                } elseif ($minQty > 0 && $qty <= ($minQty / 2)) {
                    $dot = '#b52020'; $bg = '#fdeaea'; $fg = '#b52020'; $label = 'Critical';
                } elseif ($minQty > 0 && $qty <= $minQty) {
                    $dot = '#a05c00'; $bg = '#fff4e0'; $fg = '#a05c00'; $label = 'Low Stock';
                } else {
                    $dot = '#1a7a45'; $bg = '#e8f7ee'; $fg = '#1a7a45'; $label = 'Healthy';
                }
            @endphp
            <tr style="border-bottom:1px solid rgba(185,92,183,0.05);transition:background 120ms;"
                onmouseover="this.style.background='#faf5fb'" onmouseout="this.style.background=''">

                <td style="padding:11px 18px;">
                    <div style="font-weight:500;color:#1e0a2c;font-family:'DM Sans',sans-serif;">
                        {{ $row->product_name }}
                    </div>
                    @if(!empty($row->generic_name) && $row->generic_name !== $row->product_name)
                    <div style="font-size:11px;color:#9a85aa;">{{ $row->generic_name }}</div>
                    @endif
                    <div style="font-size:10.5px;color:#c0b0d0;font-family:'DM Mono',monospace;">
                        {{ $row->category_name ?? '' }}{{ !empty($row->sub_type_name) ? ' › '.$row->sub_type_name : '' }}
                    </div>
                </td>

                <td style="padding:11px 14px;font-size:12.5px;color:#4e2060;">
                    {{ $row->location_name ?? '—' }}
                </td>

                <td style="padding:11px 14px;text-align:center;">
                    <div style="display:inline-flex;align-items:center;gap:7px;">
                        <span style="width:9px;height:9px;border-radius:50%;background:{{ $dot }};
                                     flex-shrink:0;display:inline-block;"></span>
                        <span style="font-family:'DM Mono',monospace;font-size:17px;font-weight:700;
                                     color:{{ $fg }};">
                            {{ number_format($qty, ($qty == intval($qty)) ? 0 : 2) }}
                        </span>
                        @if(!empty($row->consumption_unit))
                        <span style="font-size:11px;color:#9a85aa;">{{ $row->consumption_unit }}</span>
                        @endif
                    </div>
                    <div style="margin-top:3px;">
                        <span style="padding:2px 9px;border-radius:10px;font-size:10.5px;
                                     font-weight:500;background:{{ $bg }};color:{{ $fg }};">
                            {{ $label }}
                        </span>
                    </div>
                </td>

                <td style="padding:11px 14px;text-align:center;font-size:12px;
                           color:#7a6884;font-family:'DM Mono',monospace;">
                    <div>Min: <strong>{{ number_format($minQty, ($minQty == intval($minQty)) ? 0 : 1) }}</strong></div>
                    @if($reorder > 0)
                    <div style="font-size:11px;color:#9a85aa;">Reorder: {{ number_format($reorder, 0) }}</div>
                    @endif
                </td>

                <td style="padding:11px 18px;text-align:center;">
                    <div style="display:flex;gap:6px;justify-content:center;">
                        <button onclick="openAdjust({{ $row->item_id }}, {{ $row->location_id ?? 0 }},
                                         '{{ addslashes($row->product_name) }}',
                                         '{{ addslashes($row->location_name ?? 'Default') }}', 'add')"
                                title="Add stock"
                                style="width:34px;height:34px;border-radius:6px;border:1.5px solid #a3d9b8;
                                       background:#e8f7ee;color:#1a7a45;font-size:20px;font-weight:700;
                                       cursor:pointer;display:flex;align-items:center;justify-content:center;
                                       line-height:1;transition:background 120ms;"
                                onmouseover="this.style.background='#c8edda'"
                                onmouseout="this.style.background='#e8f7ee'">+</button>
                        <button onclick="openAdjust({{ $row->item_id }}, {{ $row->location_id ?? 0 }},
                                         '{{ addslashes($row->product_name) }}',
                                         '{{ addslashes($row->location_name ?? 'Default') }}', 'remove')"
                                title="Remove stock"
                                style="width:34px;height:34px;border-radius:6px;border:1.5px solid #f5c6c6;
                                       background:#fdeaea;color:#b52020;font-size:20px;font-weight:700;
                                       cursor:pointer;display:flex;align-items:center;justify-content:center;
                                       line-height:1;transition:background 120ms;"
                                onmouseover="this.style.background='#fac8c8'"
                                onmouseout="this.style.background='#fdeaea'">−</button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="padding:60px;text-align:center;">
                    <div style="font-size:36px;margin-bottom:12px;">📦</div>
                    <div style="font-family:'DM Sans',sans-serif;font-size:14px;color:#9070a0;">
                        No stock records yet.<br>
                        <a href="{{ route('inventory.products') }}" style="color:#6a0f70;">
                            Add products to the master list
                        </a> then use Stock In to record initial stock.
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- ═══════════════════════════════════════════════════════════
     MODAL — Quick Adjust
════════════════════════════════════════════════════════════ --}}
<div id="modal-adjust"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);
            z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:8px;width:100%;max-width:400px;
                box-shadow:0 16px 48px rgba(0,0,0,0.18);margin:20px;">

        <div id="adjust-header"
             style="padding:16px 22px;border-bottom:1px solid #f0e8f4;border-radius:8px 8px 0 0;
                    background:#faf5fb;">
            <h3 id="adjust-title"
                style="font-family:'DM Sans',sans-serif;font-size:16px;font-weight:600;
                       color:#1e0a2c;margin:0 0 3px;"></h3>
            <p id="adjust-subtitle"
               style="font-family:'DM Sans',sans-serif;font-size:12px;color:#9a85aa;margin:0;"></p>
        </div>

        <form id="form-adjust" method="POST" style="padding:20px 22px;">
            @csrf
            <input type="hidden" name="type" id="adj-type">
            <input type="hidden" name="location_id" id="adj-location">

            <div style="margin-bottom:14px;">
                <label style="display:block;font-family:'DM Sans',sans-serif;font-size:11.5px;
                              font-weight:600;color:#666;text-transform:uppercase;
                              letter-spacing:.05em;margin-bottom:6px;">Quantity</label>
                <input type="number" name="qty" id="adj-qty"
                       min="0.01" step="0.01" required placeholder="0"
                       style="width:100%;padding:10px 12px;border:2px solid #d8c8e4;border-radius:6px;
                              font-size:22px;font-family:'DM Mono',monospace;box-sizing:border-box;
                              text-align:center;outline:none;">
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block;font-family:'DM Sans',sans-serif;font-size:11.5px;
                              font-weight:600;color:#666;text-transform:uppercase;
                              letter-spacing:.05em;margin-bottom:6px;">Note (Optional)</label>
                <input type="text" name="note"
                       placeholder="e.g. Manual count correction, received from supplier…"
                       style="width:100%;padding:8px 12px;border:1px solid #d8c8e4;border-radius:6px;
                              font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;">
            </div>
            <div style="display:flex;gap:8px;">
                <button type="button" onclick="closeAdjust()"
                        style="flex:1;padding:10px;border:1px solid #d8c8e4;border-radius:6px;
                               font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;
                               color:#6a0f70;cursor:pointer;">Cancel</button>
                <button type="submit" id="adj-submit"
                        style="flex:2;padding:10px;border:none;border-radius:6px;
                               font-size:13px;font-family:'DM Sans',sans-serif;font-weight:600;
                               color:#fff;cursor:pointer;background:#1a7a45;">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAdjust(itemId, locationId, productName, locationName, type) {
    const isAdd   = type === 'add';
    const color   = isAdd ? '#1a7a45' : '#b52020';
    const bgColor = isAdd ? '#e8f7ee' : '#fdeaea';

    document.getElementById('adjust-title').textContent    = (isAdd ? '+ Add Stock' : '− Remove Stock') + ' — ' + productName;
    document.getElementById('adjust-subtitle').textContent = 'Location: ' + locationName;
    document.getElementById('adjust-header').style.background = bgColor;
    document.getElementById('adj-type').value              = type;
    document.getElementById('adj-location').value          = locationId;
    document.getElementById('adj-qty').value               = '';
    document.getElementById('adj-qty').style.borderColor   = color;
    document.getElementById('adj-submit').style.background = color;
    document.getElementById('adj-submit').textContent      = isAdd ? 'Add Stock' : 'Remove Stock';
    document.getElementById('form-adjust').action          = `/inventory/items/${itemId}/adjust`;
    document.getElementById('modal-adjust').style.display  = 'flex';
    setTimeout(() => document.getElementById('adj-qty').focus(), 80);
}

function closeAdjust() {
    document.getElementById('modal-adjust').style.display = 'none';
}

document.getElementById('modal-adjust').addEventListener('click', function(e) {
    if (e.target === this) closeAdjust();
});
</script>

@endsection
