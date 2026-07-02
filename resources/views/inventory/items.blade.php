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
        <p style="font-family:'Inter',sans-serif;font-size:13px;color:#7a6884;margin:0;">
            Current stock levels — use +/− to make quick adjustments
        </p>
    </div>
    <a href="{{ route('inventory.products') }}"
       style="border:1px solid rgba(106,15,112,0.25);color:#6a0f70;border-radius:6px;
              padding:8px 16px;font-size:13px;font-family:'Inter',sans-serif;
              text-decoration:none;display:flex;align-items:center;gap:6px;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 12h8M12 8v8"/></svg>
        Manage Products
    </a>
</div>

{{-- Flash messages --}}
@if(session('success'))
<div style="background:#e8f7ee;border:1px solid #a3d9b8;color:#1a7a45;padding:10px 16px;
            border-radius:6px;font-size:13px;font-family:'Inter',sans-serif;margin-bottom:16px;">
    ✓ {{ session('success') }}
</div>
@endif
@if($errors->any())
<div style="background:#fdeaea;border:1px solid #f5c6c6;color:#b52020;padding:10px 16px;
            border-radius:6px;font-size:13px;font-family:'Inter',sans-serif;margin-bottom:16px;">
    @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
</div>
@endif

{{-- ── Filters bar ── --}}
<form method="GET" action="{{ route('inventory.items') }}"
      style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:14px;">

    {{-- Keep current sort/dir when re-filtering --}}
    @if($sort !== 'product_name') <input type="hidden" name="sort" value="{{ $sort }}"> @endif
    @if($dir  !== 'asc')          <input type="hidden" name="dir"  value="{{ $dir }}">  @endif

    <div style="display:flex;flex-direction:column;gap:4px;">
        <label style="font-size:10.5px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;
                      color:#9a85aa;font-family:'Inter',sans-serif;">Category</label>
        <select name="category_id" onchange="this.form.submit()"
                style="padding:7px 10px;border:1.5px solid #d8c8e4;border-radius:6px;font-size:12.5px;
                       font-family:'Inter',sans-serif;color:#1e0a2c;background:#fff;min-width:150px;cursor:pointer;">
            <option value="">All Categories</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" {{ $categoryId == $cat->id ? 'selected' : '' }}>
                    {{ $cat->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div style="display:flex;flex-direction:column;gap:4px;">
        <label style="font-size:10.5px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;
                      color:#9a85aa;font-family:'Inter',sans-serif;">Location</label>
        <select name="location_id" onchange="this.form.submit()"
                style="padding:7px 10px;border:1.5px solid #d8c8e4;border-radius:6px;font-size:12.5px;
                       font-family:'Inter',sans-serif;color:#1e0a2c;background:#fff;min-width:150px;cursor:pointer;">
            <option value="">All Locations</option>
            @foreach($locations as $loc)
                <option value="{{ $loc->id }}" {{ $locationId == $loc->id ? 'selected' : '' }}>
                    {{ $loc->name }}
                </option>
            @endforeach
        </select>
    </div>

    @if($categoryId || $locationId)
    <a href="{{ route('inventory.items') }}"
       style="padding:7px 12px;font-size:12px;font-family:'Inter',sans-serif;color:#7a6884;
              border:1.5px solid #d8c8e4;border-radius:6px;text-decoration:none;white-space:nowrap;
              display:inline-flex;align-items:center;gap:4px;">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M18 6L6 18M6 6l12 12"/>
        </svg>
        Clear filters
    </a>
    @endif

    {{-- Result count --}}
    <span style="margin-left:auto;font-size:12px;font-family:'Inter',sans-serif;color:#9a85aa;
                 align-self:center;">
        {{ $stockRows->count() }} {{ Str::plural('item', $stockRows->count()) }}
    </span>

</form>

{{-- ── Legend ── --}}
<div style="display:flex;gap:16px;margin-bottom:14px;align-items:center;flex-wrap:wrap;">
    <span style="font-size:12px;font-family:'Inter',sans-serif;color:#9a85aa;">Stock levels:</span>
    <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-family:'Inter',sans-serif;">
        <span style="width:10px;height:10px;border-radius:50%;background:#1a7a45;display:inline-block;"></span>
        <span style="color:#1a7a45;">Healthy</span>
    </span>
    <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-family:'Inter',sans-serif;">
        <span style="width:10px;height:10px;border-radius:50%;background:#a05c00;display:inline-block;"></span>
        <span style="color:#a05c00;">Low (at or below minimum)</span>
    </span>
    <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-family:'Inter',sans-serif;">
        <span style="width:10px;height:10px;border-radius:50%;background:#b52020;display:inline-block;"></span>
        <span style="color:#b52020;">Critical / Out of stock</span>
    </span>
</div>

{{-- ── Stock table ── --}}
@php
    /* Build sort URL helper — toggles dir if already sorted by this column */
    function sortUrl(string $col, string $currentSort, string $currentDir,
                     ?string $catId, ?string $locId): string {
        $newDir = ($currentSort === $col && $currentDir === 'asc') ? 'desc' : 'asc';
        $params = ['sort' => $col, 'dir' => $newDir];
        if ($catId) $params['category_id'] = $catId;
        if ($locId) $params['location_id'] = $locId;
        return route('inventory.items') . '?' . http_build_query($params);
    }

    function sortIcon(string $col, string $currentSort, string $currentDir): string {
        if ($currentSort !== $col) return '<span style="opacity:.3;font-size:9px;">⇅</span>';
        return $currentDir === 'asc'
            ? '<span style="color:#6a0f70;font-size:10px;">↑</span>'
            : '<span style="color:#6a0f70;font-size:10px;">↓</span>';
    }
@endphp

<div style="background:#fff;border:1px solid rgba(185,92,183,0.12);border-radius:8px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="background:#faf5fb;border-bottom:1px solid rgba(185,92,183,0.10);">
                {{-- Sortable: Product --}}
                <th style="padding:10px 18px;text-align:left;">
                    <a href="{{ sortUrl('product_name', $sort, $dir, $categoryId, $locationId) }}"
                       style="text-decoration:none;display:inline-flex;align-items:center;gap:5px;
                              font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;
                              color:#9a85aa;">
                        Product {!! sortIcon('product_name', $sort, $dir) !!}
                    </a>
                </th>
                {{-- Sortable: Location --}}
                <th style="padding:10px 14px;text-align:left;">
                    <a href="{{ sortUrl('location_name', $sort, $dir, $categoryId, $locationId) }}"
                       style="text-decoration:none;display:inline-flex;align-items:center;gap:5px;
                              font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;
                              color:#9a85aa;">
                        Location {!! sortIcon('location_name', $sort, $dir) !!}
                    </a>
                </th>
                {{-- Sortable: Current Stock --}}
                <th style="padding:10px 14px;text-align:center;">
                    <a href="{{ sortUrl('available_qty', $sort, $dir, $categoryId, $locationId) }}"
                       style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:5px;
                              font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;
                              color:#9a85aa;width:100%;">
                        Current Stock {!! sortIcon('available_qty', $sort, $dir) !!}
                    </a>
                </th>
                <th style="padding:10px 14px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;
                           text-transform:uppercase;color:#9a85aa;">Min / Reorder</th>
                <th style="padding:10px 18px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;
                           text-transform:uppercase;color:#9a85aa;">Adjust</th>
            </tr>
        </thead>
        <tbody>
            @forelse($stockRows as $row)
            @php
                $qty     = (int) floor((float) ($row->available_qty ?? 0));  // always integer
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
                    <div style="font-weight:500;color:#1e0a2c;font-family:'Inter',sans-serif;">
                        {{ $row->product_name }}
                    </div>
                    @if(!empty($row->generic_name) && $row->generic_name !== $row->product_name)
                    <div style="font-size:11px;color:#9a85aa;">{{ $row->generic_name }}</div>
                    @endif
                    <div style="font-size:10.5px;color:#c0b0d0;font-family:'Inter', sans-serif;">
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
                        <span style="font-family:'Inter', sans-serif;font-size:17px;font-weight:700;
                                     color:{{ $fg }};">
                            {{ number_format($qty) }}
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
                           color:#7a6884;font-family:'Inter', sans-serif;">
                    <div>Min: <strong>{{ number_format((int) $minQty) }}</strong></div>
                    @if($reorder > 0)
                    <div style="font-size:11px;color:#9a85aa;">Reorder: {{ number_format((int) $reorder) }}</div>
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
                    <div style="font-family:'Inter',sans-serif;font-size:14px;color:#9070a0;">
                        No stock records found.
                        @if($categoryId || $locationId)
                            <a href="{{ route('inventory.items') }}" style="color:#6a0f70;">Clear filters</a>
                        @else
                            <a href="{{ route('inventory.products') }}" style="color:#6a0f70;">
                                Add products to the master list
                            </a> then use Stock In to record initial stock.
                        @endif
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
                style="font-family:'Inter',sans-serif;font-size:16px;font-weight:600;
                       color:#1e0a2c;margin:0 0 3px;"></h3>
            <p id="adjust-subtitle"
               style="font-family:'Inter',sans-serif;font-size:12px;color:#9a85aa;margin:0;"></p>
        </div>

        <form id="form-adjust" method="POST" style="padding:20px 22px;">
            @csrf
            <input type="hidden" name="type" id="adj-type">
            {{-- Hidden field used when location is already known (existing stock row) --}}
            <input type="hidden" name="location_id" id="adj-location">

            {{-- Location picker — shown only when product has no stock record yet (location_id = 0) --}}
            <div id="adj-location-picker" style="display:none;margin-bottom:14px;">
                <label style="display:block;font-family:'Inter',sans-serif;font-size:11.5px;
                              font-weight:600;color:#666;text-transform:uppercase;
                              letter-spacing:.05em;margin-bottom:6px;">Location</label>
                <select id="adj-location-select"
                        onchange="document.getElementById('adj-location').value = this.value"
                        style="width:100%;padding:9px 12px;border:2px solid #d8c8e4;border-radius:6px;
                               font-size:13px;font-family:'Inter',sans-serif;box-sizing:border-box;
                               outline:none;background:#fff;">
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                    @endforeach
                </select>
                <p style="margin:5px 0 0;font-size:11px;color:#9a85aa;font-family:'Inter',sans-serif;">
                    This item has no stock record yet — select where to record it.
                </p>
            </div>

            <div style="margin-bottom:14px;">
                <label style="display:block;font-family:'Inter',sans-serif;font-size:11.5px;
                              font-weight:600;color:#666;text-transform:uppercase;
                              letter-spacing:.05em;margin-bottom:6px;">Quantity</label>
                <input type="number" name="qty" id="adj-qty"
                       min="1" step="1" required placeholder="0"
                       style="width:100%;padding:10px 12px;border:2px solid #d8c8e4;border-radius:6px;
                              font-size:22px;font-family:'Inter', sans-serif;box-sizing:border-box;
                              text-align:center;outline:none;">
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block;font-family:'Inter',sans-serif;font-size:11.5px;
                              font-weight:600;color:#666;text-transform:uppercase;
                              letter-spacing:.05em;margin-bottom:6px;">Note (Optional)</label>
                <input type="text" name="note"
                       placeholder="e.g. Manual count correction, received from supplier…"
                       style="width:100%;padding:8px 12px;border:1px solid #d8c8e4;border-radius:6px;
                              font-size:13px;font-family:'Inter',sans-serif;box-sizing:border-box;">
            </div>
            <div style="display:flex;gap:8px;">
                <button type="button" onclick="closeAdjust()"
                        style="flex:1;padding:10px;border:1px solid #d8c8e4;border-radius:6px;
                               font-size:13px;font-family:'Inter',sans-serif;background:#fff;
                               color:#6a0f70;cursor:pointer;">Cancel</button>
                <button type="submit" id="adj-submit"
                        style="flex:2;padding:10px;border:none;border-radius:6px;
                               font-size:13px;font-family:'Inter',sans-serif;font-weight:600;
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

    document.getElementById('adjust-title').textContent       = (isAdd ? '+ Add Stock' : '− Remove Stock') + ' — ' + productName;
    document.getElementById('adjust-header').style.background = bgColor;
    document.getElementById('adj-type').value                 = type;
    document.getElementById('adj-qty').value                  = '';
    document.getElementById('adj-qty').style.borderColor      = color;
    document.getElementById('adj-submit').style.background    = color;
    document.getElementById('adj-submit').textContent         = isAdd ? 'Add Stock' : 'Remove Stock';
    document.getElementById('form-adjust').action             = `/inventory/items/${itemId}/adjust`;

    const picker = document.getElementById('adj-location-picker');
    const select = document.getElementById('adj-location-select');

    if (!locationId || locationId === 0) {
        // No stock record yet — show location picker, use first location as default
        picker.style.display = 'block';
        document.getElementById('adjust-subtitle').textContent = 'No stock record yet — pick a location below';
        // Set hidden field to first option value so it's valid even without user interaction
        if (select && select.options.length > 0) {
            select.selectedIndex = 0;
            document.getElementById('adj-location').value = select.options[0].value;
        }
    } else {
        // Known location — hide picker, set hidden field directly
        picker.style.display = 'none';
        document.getElementById('adj-location').value          = locationId;
        document.getElementById('adjust-subtitle').textContent = 'Location: ' + locationName;
    }

    document.getElementById('modal-adjust').style.display = 'flex';
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
