{{--
|===========================================================================
| Inventory — Card + Table View (Phase 3 upgrade)
|===========================================================================
--}}
@extends('layouts.app')
@section('title', 'Inventory')

@section('head-extra')
<style>
/* ── Inventory card grid ── */
.inv-card-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;margin-bottom:24px;}
.inv-item-card{background:#fff;border:1px solid rgba(185,92,183,0.12);border-radius:10px;
               padding:14px;cursor:pointer;transition:box-shadow 140ms,border-color 140ms;position:relative;}
.inv-item-card:hover{box-shadow:0 4px 18px rgba(106,15,112,0.10);border-color:rgba(185,92,183,0.28);}
.inv-card-thumb{width:48px;height:48px;border-radius:8px;object-fit:cover;background:#f5f0f8;
                display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;
                border:1px solid #ede5f4;overflow:hidden;}
.inv-card-name{font-weight:600;font-size:13px;color:#1e0a2c;line-height:1.3;
               font-family:'Inter',sans-serif;margin:8px 0 2px;
               display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.inv-card-meta{font-size:11px;color:#9a85aa;font-family:'Inter',sans-serif;}
.inv-card-stock{display:flex;align-items:center;gap:5px;margin-top:8px;}
.inv-card-qty{font-size:18px;font-weight:700;font-family:'Inter',sans-serif;line-height:1;}
.inv-card-unit{font-size:11px;color:#9a85aa;font-family:'Inter',sans-serif;margin-top:1px;}
.inv-card-actions{display:flex;gap:5px;margin-top:10px;}
.inv-card-btn{flex:1;padding:5px 0;border-radius:5px;border:1px solid rgba(185,92,183,0.18);
              background:#faf5fb;color:#6a0f70;font-size:11px;font-family:'Inter',sans-serif;
              font-weight:500;cursor:pointer;text-align:center;text-decoration:none;
              transition:background 120ms;}
.inv-card-btn:hover{background:#f0e4f4;}
.inv-card-btn.primary{background:#6a0f70;color:#fff;border-color:#6a0f70;}
.inv-card-btn.primary:hover{background:#550c5b;}
.inv-section-hd{display:flex;align-items:center;justify-content:space-between;
                margin:0 0 10px;font-family:'Inter',sans-serif;}
.inv-section-hd h3{font-size:13px;font-weight:600;color:#4e2060;margin:0;
                    display:flex;align-items:center;gap:6px;}
.inv-search-wrap{position:relative;flex:1;max-width:460px;}
.inv-search-wrap input{width:100%;padding:10px 16px 10px 40px;border:1.5px solid rgba(185,92,183,0.25);
                        border-radius:8px;font-size:14px;font-family:'Inter',sans-serif;outline:none;
                        transition:border-color 150ms;box-sizing:border-box;}
.inv-search-wrap input:focus{border-color:#6a0f70;}
.inv-search-wrap svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9a85aa;}
.inv-view-toggle{display:flex;border:1px solid rgba(185,92,183,0.20);border-radius:6px;overflow:hidden;}
.inv-view-toggle button{padding:7px 12px;border:none;background:none;cursor:pointer;
                         color:#9a85aa;font-size:12px;display:flex;align-items:center;gap:5px;
                         font-family:'Inter',sans-serif;transition:background 120ms;}
.inv-view-toggle button.active{background:#6a0f70;color:#fff;}
</style>
@endsection

@section('content')
@include('inventory.partials.subnav')

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

{{-- ── Top bar: search + view toggle + add ── --}}
<div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;flex-wrap:wrap;">
    <form method="GET" action="{{ route('inventory.products') }}" style="display:flex;flex:1;min-width:200px;">
        <div class="inv-search-wrap">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
            </svg>
            <input type="text" name="q" value="{{ $search }}" placeholder="Search products, brands, codes…"
                   id="inv-search-input" autocomplete="off">
        </div>
        @if($catId)       <input type="hidden" name="category_id" value="{{ $catId }}">@endif
        @if($subTypeId)   <input type="hidden" name="sub_type_id" value="{{ $subTypeId }}">@endif
        @if($brandFilter) <input type="hidden" name="brand" value="{{ $brandFilter }}">@endif
        @if($locationId)  <input type="hidden" name="location_id" value="{{ $locationId }}">@endif
        @if($stockLevel)  <input type="hidden" name="stock_level" value="{{ $stockLevel }}">@endif
    </form>
    @if(!$search && !$catId && !$subTypeId && !$brandFilter && !$locationId && !$stockLevel)
    <div style="flex-shrink:0;">
        <span style="font-family:'Cormorant Garamond',serif;font-size:22px;font-weight:600;color:#1a0a1e;">Inventory</span>
        <span style="font-size:12px;color:#9a85aa;font-family:'Inter',sans-serif;margin-left:8px;">{{ $products->total() }} products</span>
    </div>
    @endif
    @if(auth()->user()?->role === 'admin')
    <button onclick="openAddProduct()"
            style="background:#6a0f70;color:#fff;border:none;border-radius:6px;padding:9px 16px;
                   font-size:13px;font-family:'Inter',sans-serif;font-weight:500;cursor:pointer;
                   display:flex;align-items:center;gap:6px;white-space:nowrap;">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>Add Product
    </button>
    @endif
</div>


{{-- ══════════════════════════════════════════════════
     TABLE VIEW
══════════════════════════════════════════════════ --}}
<div>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
    <div>
        <h1 style="font-family:'Cormorant Garamond',serif;font-size:22px;font-weight:600;color:#1a0a1e;margin:0 0 2px;">Inventory</h1>
        <p style="font-family:'Inter',sans-serif;font-size:13px;color:#7a6884;margin:0;">{{ $products->total() }} products · stock levels &amp; product catalogue</p>
    </div>
    @if(auth()->user()?->role === 'admin')
    <button onclick="openAddProduct()" style="background:#6a0f70;color:#fff;border:none;border-radius:6px;padding:9px 18px;font-size:13px;font-family:'Inter',sans-serif;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:7px;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Add Product
    </button>
    @endif
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

{{-- ── Filters bar ── --}}
<form method="GET" action="{{ route('inventory.products') }}"
      style="background:#fff;border:1px solid rgba(185,92,183,0.12);border-radius:8px;
             padding:14px 16px;margin-bottom:12px;">
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
        {{-- Search --}}
        <input type="text" name="q" value="{{ $search }}"
               placeholder="Search name, brand, code…"
               style="flex:1;min-width:180px;max-width:260px;padding:7px 11px;
                      border:1px solid #d8c8e4;border-radius:6px;font-size:13px;
                      font-family:'DM Sans',sans-serif;outline:none;">
        {{-- Category --}}
        <select name="category_id"
                style="padding:7px 11px;border:1px solid #d8c8e4;border-radius:6px;
                       font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;min-width:140px;">
            <option value="">All Categories</option>
            @foreach($categories as $cat)
            <option value="{{ $cat->id }}" @selected($catId == $cat->id)>{{ $cat->name }}</option>
            @endforeach
        </select>
        {{-- Sub-type --}}
        <select name="sub_type_id"
                style="padding:7px 11px;border:1px solid #d8c8e4;border-radius:6px;
                       font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;min-width:140px;">
            <option value="">All Sub-types</option>
            @foreach($subTypes as $st)
            <option value="{{ $st->id }}" @selected($subTypeId == $st->id)>{{ $st->name }}</option>
            @endforeach
        </select>
        {{-- Brand --}}
        <select name="brand"
                style="padding:7px 11px;border:1px solid #d8c8e4;border-radius:6px;
                       font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;min-width:120px;">
            <option value="">All Brands</option>
            @foreach($brands as $b)
            <option value="{{ $b }}" @selected($brandFilter == $b)>{{ $b }}</option>
            @endforeach
        </select>
        {{-- Stock Level --}}
        <select name="stock_level"
                style="padding:7px 11px;border:1px solid #d8c8e4;border-radius:6px;
                       font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;min-width:120px;">
            <option value="">All Stock</option>
            <option value="healthy"  @selected($stockLevel === 'healthy')>Healthy</option>
            <option value="low"      @selected($stockLevel === 'low')>Low</option>
            <option value="critical" @selected($stockLevel === 'critical')>Critical</option>
            <option value="out"      @selected($stockLevel === 'out')>Out of Stock</option>
        </select>
        {{-- Location --}}
        <select name="location_id"
                style="padding:7px 11px;border:1px solid #d8c8e4;border-radius:6px;
                       font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;min-width:120px;">
            <option value="">All Locations</option>
            @foreach($locations as $loc)
            <option value="{{ $loc->id }}" @selected($locationId == $loc->id)>{{ $loc->name }}</option>
            @endforeach
        </select>
        {{-- Per page --}}
        <select name="per_page"
                style="padding:7px 11px;border:1px solid #d8c8e4;border-radius:6px;
                       font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;width:76px;">
            <option value="25"  @selected($perPage == 25)>25</option>
            <option value="50"  @selected($perPage == 50)>50</option>
            <option value="100" @selected($perPage == 100)>100</option>
        </select>
        <button type="submit"
                style="padding:7px 16px;background:#6a0f70;color:#fff;border:none;border-radius:6px;
                       font-size:13px;font-family:'DM Sans',sans-serif;font-weight:500;cursor:pointer;">
            Filter
        </button>
        @if($search || $catId || $subTypeId || $brandFilter || $locationId || $stockLevel)
        <a href="{{ route('inventory.products') }}"
           style="padding:7px 13px;border:1px solid #d8c8e4;border-radius:6px;
                  font-size:13px;font-family:'DM Sans',sans-serif;color:#6a0f70;text-decoration:none;">
            Clear
        </a>
        @endif
    </div>
</form>

{{-- ── Stock legend ── --}}
<div style="display:flex;gap:16px;align-items:center;margin-bottom:12px;font-family:'DM Sans',sans-serif;font-size:11.5px;color:#9a85aa;">
    <span style="display:flex;align-items:center;gap:5px;">
        <span style="width:8px;height:8px;border-radius:50%;background:#1a7a45;display:inline-block;"></span> Healthy
    </span>
    <span style="display:flex;align-items:center;gap:5px;">
        <span style="width:8px;height:8px;border-radius:50%;background:#d97706;display:inline-block;"></span> Low
    </span>
    <span style="display:flex;align-items:center;gap:5px;">
        <span style="width:8px;height:8px;border-radius:50%;background:#dc2626;display:inline-block;"></span> Critical / Out
    </span>
</div>

{{-- ── Unified inventory table ── --}}
<div style="background:#fff;border:1px solid rgba(185,92,183,0.12);border-radius:8px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="background:#faf5fb;border-bottom:1px solid rgba(185,92,183,0.10);">
                <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;width:40px;"></th>
                <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Product</th>
                <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Brand</th>
                <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Location(s)</th>
                <th style="padding:10px 12px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Stock</th>
                <th style="padding:10px 12px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Status</th>
                <th style="padding:10px 12px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Adjust</th>
                <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;width:44px;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $product)
            @php
                $qty         = (float) ($product->total_qty ?? 0);
                $reorder     = (float) ($product->reorder_level ?? 0);
                $threshold   = $reorder > 0 ? $reorder : 5;
                if ($qty <= 0) {
                    $stockDot = '#dc2626'; $stockLabel = 'Out';
                } elseif ($qty <= $threshold) {
                    $stockDot = '#dc2626'; $stockLabel = 'Critical';
                } elseif ($qty <= $threshold * 2) {
                    $stockDot = '#d97706'; $stockLabel = 'Low';
                } else {
                    $stockDot = '#1a7a45'; $stockLabel = 'Healthy';
                }
                // Locations for this product
                $stockLocs  = $product->stocks->filter(fn($s) => $s->available_qty > 0);
                $firstLocId = $product->stocks->first()?->location_id ?? 0;
                $firstLocNm = $product->stocks->first()?->location?->name ?? 'Default';
            @endphp
            <tr style="border-bottom:1px solid rgba(185,92,183,0.05);transition:background 120ms;"
                onmouseover="this.style.background='#faf5fb'" onmouseout="this.style.background=''">

                {{-- Thumbnail --}}
                <td style="padding:10px 16px;width:44px;">
                    @if($product->image)
                    <img src="{{ asset('storage/'.$product->image) }}" alt=""
                         style="width:36px;height:36px;object-fit:cover;border-radius:5px;border:1px solid #f0e8f4;">
                    @else
                    <div style="width:36px;height:36px;border-radius:5px;background:#f5f0f8;
                                display:flex;align-items:center;justify-content:center;font-size:16px;">💊</div>
                    @endif
                </td>

                {{-- Product name / code --}}
                <td style="padding:10px 12px;max-width:220px;">
                    <div style="font-weight:500;color:#1e0a2c;font-family:'DM Sans',sans-serif;
                                white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        {{ $product->product_name }}
                    </div>
                    @if($product->subType)
                    <div style="font-size:11px;color:#9a85aa;margin-top:1px;">
                        {{ $product->category?->name }} › {{ $product->subType->name }}
                        @if($product->variant) · {{ $product->variant->name }}@endif
                    </div>
                    @endif
                    <div style="font-size:10.5px;color:#c0b0d0;font-family:'DM Mono',monospace;margin-top:1px;">
                        {{ $product->item_code }}
                    </div>
                </td>

                {{-- Brand --}}
                <td style="padding:10px 12px;font-size:12.5px;color:#1e0a2c;white-space:nowrap;">
                    {{ $product->brand ?: '—' }}
                </td>

                {{-- Location(s) --}}
                <td style="padding:10px 12px;font-size:12px;">
                    @if($stockLocs->count())
                        @foreach($stockLocs->take(2) as $sl)
                        <div style="color:#4e2060;white-space:nowrap;">
                            {{ $sl->location?->name ?? 'Default' }}
                            <span style="color:#9a85aa;font-size:11px;">({{ (int)$sl->available_qty }})</span>
                        </div>
                        @endforeach
                        @if($stockLocs->count() > 2)
                        <div style="font-size:11px;color:#9a85aa;">+{{ $stockLocs->count() - 2 }} more</div>
                        @endif
                    @else
                        <span style="color:#c0b0d0;">—</span>
                    @endif
                </td>

                {{-- Stock qty + colored dot --}}
                <td style="padding:10px 12px;text-align:center;white-space:nowrap;">
                    <span style="display:inline-flex;align-items:center;gap:5px;
                                 font-family:'DM Mono',monospace;font-size:13px;font-weight:600;
                                 color:{{ $stockDot }};">
                        <span style="width:8px;height:8px;border-radius:50%;background:{{ $stockDot }};
                                     flex-shrink:0;display:inline-block;"></span>
                        {{ $qty > 0 ? (int)$qty : '0' }}
                    </span>
                </td>

                {{-- Status badge --}}
                <td style="padding:10px 12px;text-align:center;">
                    @if($product->is_active)
                    <span style="background:#e8f7ee;color:#1a7a45;padding:2px 10px;border-radius:10px;
                                 font-size:11px;font-weight:500;white-space:nowrap;">Active</span>
                    @else
                    <span style="background:#f5f5f5;color:#888;padding:2px 10px;border-radius:10px;
                                 font-size:11px;font-weight:500;white-space:nowrap;">Inactive</span>
                    @endif
                </td>

                {{-- Adjust +/- --}}
                <td style="padding:8px 12px;text-align:center;">
                    <div style="display:flex;gap:5px;justify-content:center;">
                        <button onclick="openAdjust({{ $product->id }}, {{ $firstLocId }},
                                         '{{ addslashes($product->product_name) }}',
                                         '{{ addslashes($firstLocNm) }}', 'add')"
                                title="Add stock"
                                style="width:30px;height:30px;border-radius:5px;border:1.5px solid #a3d9b8;
                                       background:#e8f7ee;color:#1a7a45;font-size:18px;font-weight:700;
                                       cursor:pointer;display:flex;align-items:center;justify-content:center;
                                       line-height:1;transition:background 120ms;"
                                onmouseover="this.style.background='#c8edda'"
                                onmouseout="this.style.background='#e8f7ee'">+</button>
                        <button onclick="openAdjust({{ $product->id }}, {{ $firstLocId }},
                                         '{{ addslashes($product->product_name) }}',
                                         '{{ addslashes($firstLocNm) }}', 'remove')"
                                title="Remove stock"
                                style="width:30px;height:30px;border-radius:5px;border:1.5px solid #f5c6c6;
                                       background:#fdeaea;color:#b52020;font-size:18px;font-weight:700;
                                       cursor:pointer;display:flex;align-items:center;justify-content:center;
                                       line-height:1;transition:background 120ms;"
                                onmouseover="this.style.background='#fac8c8'"
                                onmouseout="this.style.background='#fdeaea'">−</button>
                    </div>
                </td>

                {{-- Three-dot menu --}}
                <td style="padding:8px 16px;text-align:center;position:relative;">
                    <button onclick="toggleDotMenu(event,'dmenu-{{ $product->id }}')"
                            style="width:28px;height:28px;border-radius:4px;border:1px solid transparent;
                                   background:none;color:#9a85aa;cursor:pointer;font-size:18px;
                                   display:flex;align-items:center;justify-content:center;
                                   transition:background 120ms;"
                            onmouseover="this.style.background='#f5f0f8'"
                            onmouseout="this.style.background='none'">⋯</button>
                    <div id="dmenu-{{ $product->id }}"
                         style="display:none;position:absolute;right:10px;top:36px;z-index:200;
                                background:#fff;border:1px solid rgba(185,92,183,0.15);border-radius:6px;
                                box-shadow:0 4px 16px rgba(0,0,0,0.10);min-width:110px;overflow:hidden;">
                        <button onclick='closeDotMenus();openEditProduct({{ $product->load("dealers")->toJson() }})'
                                style="width:100%;padding:8px 14px;border:none;background:none;text-align:left;
                                       font-size:13px;font-family:'DM Sans',sans-serif;color:#1e0a2c;cursor:pointer;"
                                onmouseover="this.style.background='#faf5fb'"
                                onmouseout="this.style.background='none'">
                            ✏️ Edit
                        </button>
                        @if(auth()->user()?->role === 'admin')
                        <form method="POST" action="{{ route('inventory.products.destroy', $product->id) }}"
                              onsubmit="return confirm('Delete {{ addslashes($product->product_name) }}? This cannot be undone.')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    style="width:100%;padding:8px 14px;border:none;background:none;text-align:left;
                                           font-size:13px;font-family:'DM Sans',sans-serif;color:#b52020;cursor:pointer;"
                                    onmouseover="this.style.background='#fdeaea'"
                                    onmouseout="this.style.background='none'">
                                🗑 Delete
                            </button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" style="padding:60px;text-align:center;">
                    <div style="font-size:36px;margin-bottom:12px;">📦</div>
                    <div style="font-family:'DM Sans',sans-serif;font-size:14px;color:#9070a0;">
                        No products found.
                        @if($search || $catId || $subTypeId || $brandFilter || $locationId || $stockLevel)
                            <a href="{{ route('inventory.products') }}" style="color:#6a0f70;">Clear filters</a>
                        @else
                            <br><span style="font-size:12px;">Click "Add Product" to create your first product.</span>
                        @endif
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Pagination — always show --}}
    <div style="padding:12px 16px;border-top:1px solid rgba(185,92,183,0.07);
                display:flex;align-items:center;justify-content:space-between;gap:12px;
                font-family:'DM Sans',sans-serif;font-size:12px;color:#9a85aa;flex-wrap:wrap;">
        <span>
            Showing {{ $products->firstItem() ?? 0 }}–{{ $products->lastItem() ?? 0 }}
            of {{ $products->total() }} products
        </span>
        @if($products->hasPages())
        <div>{{ $products->links() }}</div>
        @endif
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════
     MODAL — Quick Adjust Stock
════════════════════════════════════════════════════════════ --}}
<div id="modal-adjust"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);
            z-index:1100;align-items:center;justify-content:center;">
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
                       min="1" step="1" required placeholder="0"
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
// ── Quick Adjust modal ──────────────────────────────────────────────────────
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

// ── Three-dot menus ─────────────────────────────────────────────────────────
function closeDotMenus() {
    document.querySelectorAll('[id^="dmenu-"]').forEach(m => m.style.display = 'none');
}
function toggleDotMenu(e, id) {
    e.stopPropagation();
    const menu = document.getElementById(id);
    const wasOpen = menu.style.display === 'block';
    closeDotMenus();
    menu.style.display = wasOpen ? 'none' : 'block';
}
document.addEventListener('click', closeDotMenus);
</script>

{{-- ═══════════════════════════════════════════════════════════
     MODAL — Add / Edit Product
     Shared modal: form action + title change via JS
════════════════════════════════════════════════════════════ --}}
<div id="modal-product"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);
            z-index:1000;align-items:flex-start;justify-content:center;
            padding:20px 16px;overflow-y:auto;">
    <div style="background:#fff;border-radius:10px;width:100%;max-width:1080px;
                box-shadow:0 24px 64px rgba(0,0,0,0.22);">

        {{-- Modal header --}}
        <div style="padding:20px 28px;border-bottom:2px solid #f0e8f4;
                    display:flex;align-items:flex-start;justify-content:space-between;
                    background:linear-gradient(135deg,#faf5fb 0%,#f5f0f8 100%);
                    border-radius:10px 10px 0 0;">
            <div style="display:flex;align-items:center;gap:14px;">
                <div style="width:42px;height:42px;border-radius:10px;background:#6a0f70;
                            display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                    </svg>
                </div>
                <div>
                    <h3 id="modal-product-title"
                        style="font-family:'Cormorant Garamond',serif;font-size:22px;font-weight:700;
                               color:#1a0a1e;margin:0 0 2px;">Add New Product</h3>
                    <p style="font-family:'Inter',sans-serif;font-size:12px;color:#9070a0;margin:0;">
                        Fill in the details below to add to your inventory catalogue
                    </p>
                </div>
            </div>
            <button onclick="closeProductModal()"
                    style="background:#fff;border:1px solid #e8d8f0;cursor:pointer;font-size:18px;
                           color:#9070a0;line-height:1;padding:6px 10px;border-radius:6px;
                           transition:all 150ms;"
                    onmouseover="this.style.background='#f0e8f4';this.style.color='#6a0f70'"
                    onmouseout="this.style.background='#fff';this.style.color='#9070a0'">&times;</button>
        </div>

        <form id="form-product" method="POST" action="{{ route('inventory.products.store') }}"
              enctype="multipart/form-data" style="padding:24px 28px;">
            @csrf
            <input type="hidden" id="fp-method" name="_method" value="POST">

            {{-- ── 3-column grid ── --}}
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;">

                {{-- COL 1: Basic Information --}}
                <div style="background:#fff;border:1px solid #e8ddf2;border-radius:8px;padding:18px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;
                                padding-bottom:12px;border-bottom:1px solid #f5f0f8;">
                        <div style="width:28px;height:28px;border-radius:6px;background:#f0e8f4;
                                    display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                            </svg>
                        </div>
                        <span style="font-family:'Inter',sans-serif;font-size:12px;font-weight:700;
                                     color:#6a0f70;text-transform:uppercase;letter-spacing:.06em;">
                            Basic Information
                        </span>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:11px;">
                        <div>
                            <label class="pml-label">Product Generic Name *</label>
                            <input type="text" name="product_name" id="fp-product_name" required
                                   style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;">
                        </div>
                        <div>
                            <label class="pml-label">Category *</label>
                            <select name="category_id" id="fp-category_id" required
                                    onchange="loadSubTypes(this.value)"
                                    style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;">
                                <option value="">— Select category —</option>
                                @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="pml-label">Sub Type</label>
                            <select name="sub_type_id" id="fp-sub_type_id"
                                    onchange="loadVariants(this.value)"
                                    style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;">
                                <option value="">— Select sub type —</option>
                                @foreach($subTypes as $st)
                                <option value="{{ $st->id }}" data-cat="{{ $st->category_id }}">{{ $st->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;">
                                <label class="pml-label" style="margin-bottom:0;">Variant / Size / Shade</label>
                                <button type="button" id="fp-variant-add-btn"
                                        onclick="toggleVariantInline()"
                                        disabled
                                        style="background:none;border:none;cursor:pointer;
                                               font-size:11px;font-family:'DM Sans',sans-serif;
                                               color:#6a0f70;font-weight:600;padding:0;
                                               opacity:0.4;">
                                    + Add new
                                </button>
                            </div>
                            <select name="variant_id" id="fp-variant_id"
                                    style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;">
                                <option value="">— Select variant —</option>
                            </select>
                            {{-- Inline add-variant row (hidden until "+ Add new" clicked) --}}
                            <div id="fp-variant-inline"
                                 style="display:none;margin-top:6px;
                                        background:#f9f3fa;border:1px solid #d8c8e4;
                                        border-radius:6px;padding:8px 10px;">
                                <div style="font-size:11px;font-weight:600;color:#6a0f70;
                                            font-family:'DM Sans',sans-serif;margin-bottom:6px;
                                            text-transform:uppercase;letter-spacing:.04em;">
                                    New variant for: <span id="fp-variant-inline-label" style="font-style:italic;"></span>
                                </div>
                                <div style="display:flex;gap:6px;">
                                    <input type="text" id="fp-variant-new-name"
                                           placeholder="e.g. #10, #15, Shade A1"
                                           style="flex:1;padding:7px 9px;border:1px solid #d8c8e4;
                                                  border-radius:5px;font-size:12px;
                                                  font-family:'DM Sans',sans-serif;box-sizing:border-box;">
                                    <button type="button" onclick="saveInlineVariant()"
                                            style="background:#6a0f70;color:#fff;border:none;
                                                   border-radius:5px;padding:7px 12px;font-size:12px;
                                                   font-family:'DM Sans',sans-serif;cursor:pointer;
                                                   white-space:nowrap;font-weight:500;">
                                        Save
                                    </button>
                                    <button type="button" onclick="toggleVariantInline()"
                                            style="background:#f0e8f4;color:#6a0f70;border:none;
                                                   border-radius:5px;padding:7px 10px;font-size:12px;
                                                   font-family:'DM Sans',sans-serif;cursor:pointer;">
                                        ✕
                                    </button>
                                </div>
                                <div id="fp-variant-inline-msg"
                                     style="font-size:11px;font-family:'DM Sans',sans-serif;
                                            margin-top:5px;display:none;"></div>
                            </div>
                            <span id="fp-variant-hint"
                                  style="font-size:11px;color:#9070a0;font-family:'DM Sans',sans-serif;
                                         display:block;margin-top:3px;">
                                Select a sub-type first
                            </span>
                        </div>
                        <div>
                            <label class="pml-label">Usage</label>
                            <div style="display:flex;gap:16px;margin-top:4px;">
                                <label style="display:flex;align-items:center;gap:6px;font-size:13px;font-family:'DM Sans',sans-serif;cursor:pointer;">
                                    <input type="radio" name="usage_type" id="fp-usage-single" value="single_use"
                                           onchange="toggleUsageCount(this.value)">
                                    Single Use
                                </label>
                                <label style="display:flex;align-items:center;gap:6px;font-size:13px;font-family:'DM Sans',sans-serif;cursor:pointer;">
                                    <input type="radio" name="usage_type" id="fp-usage-multiple" value="multiple_use" checked
                                           onchange="toggleUsageCount(this.value)">
                                    Multiple Use
                                </label>
                            </div>
                            {{-- Shown only when Multiple Use is selected --}}
                            <div id="fp-usage-count-row" style="margin-top:8px;">
                                <label class="pml-label" style="margin-bottom:4px;">No. of Usages</label>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <input type="number" name="max_usage_count" id="fp-max_usage_count"
                                           min="1" step="1" placeholder="e.g. 5"
                                           style="width:100px;padding:7px 10px;border:1px solid #d8c8e4;
                                                  border-radius:5px;font-size:13px;
                                                  font-family:'DM Mono',monospace;box-sizing:border-box;">
                                    <span style="font-size:12px;color:#9070a0;font-family:'DM Sans',sans-serif;">
                                        times per item
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="pml-label">Description (Optional)</label>
                            <textarea name="description" id="fp-description" rows="3"
                                      style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;resize:vertical;"></textarea>
                        </div>
                    </div>
                </div>

                {{-- COL 2: Packaging Details --}}
                <div style="background:#fff;border:1px solid #e8ddf2;border-radius:8px;padding:18px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;
                                padding-bottom:12px;border-bottom:1px solid #f5f0f8;">
                        <div style="width:28px;height:28px;border-radius:6px;background:#f0e8f4;
                                    display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                            </svg>
                        </div>
                        <span style="font-family:'Inter',sans-serif;font-size:12px;font-weight:700;
                                     color:#6a0f70;text-transform:uppercase;letter-spacing:.06em;">
                            Packaging Details
                        </span>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:11px;">
                        <div>
                            <label class="pml-label">Packaging Type *</label>
                            <select name="packaging_type" id="fp-packaging_type"
                                    style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;">
                                <option value="">— Select —</option>
                                @foreach(['Piece','Box','Bottle','Tube','Jar','Vial','Cartridge','Syringe','Strip','Blister Pack','Capsule','Sachet','Kit','Pack','Pouch','Bag','Roll','Can','Spray Bottle','Ampoule','Set','Refill Pack','Coil','Wire Spool','Disc Pack','Wheel','Sheet','Block'] as $pt)
                                <option value="{{ $pt }}">{{ $pt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="pml-label">Qty in Packaging *</label>
                            <div style="display:flex;gap:6px;">
                                <input type="number" name="qty_in_packaging" id="fp-qty_in_packaging"
                                       min="0" step="0.01" placeholder="e.g. 4"
                                       style="flex:1;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;"
                                       oninput="calcCostPerUnit()">
                                <select name="packaging_unit_label" id="fp-packaging_unit_label"
                                        style="width:70px;padding:8px 6px;border:1px solid #d8c8e4;border-radius:5px;font-size:12px;font-family:'DM Sans',sans-serif;background:#fff;">
                                    @foreach(['g','ml','pcs','mg','L','kg','units'] as $u)
                                    <option value="{{ $u }}">{{ $u }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="pml-label">Pack Size</label>
                            <input type="text" name="pack_size_label" id="fp-pack_size_label"
                                   placeholder="e.g. 1 Syringe, 10 Strips of 10"
                                   style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;">
                        </div>
                        <div>
                            <label class="pml-label">Shelf Life (Optional)</label>
                            <div style="display:flex;gap:6px;align-items:center;">
                                <input type="number" name="shelf_life_months" id="fp-shelf_life_months"
                                       min="0" placeholder="e.g. 36"
                                       style="flex:1;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;">
                                <span style="font-size:12px;color:#9a85aa;white-space:nowrap;">Months</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- COL 3: Company & Brand + Product Image --}}
                <div style="display:flex;flex-direction:column;gap:14px;">
                    <div style="background:#fff;border:1px solid #e8ddf2;border-radius:8px;padding:18px;flex:1;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;
                                    padding-bottom:12px;border-bottom:1px solid #f5f0f8;">
                            <div style="width:28px;height:28px;border-radius:6px;background:#f0e8f4;
                                        display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2">
                                    <rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
                                </svg>
                            </div>
                            <span style="font-family:'Inter',sans-serif;font-size:12px;font-weight:700;
                                         color:#6a0f70;text-transform:uppercase;letter-spacing:.06em;">
                                Company & Brand
                            </span>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:11px;">
                            <div>
                                <label class="pml-label">Company Name *</label>
                                <input type="text" name="company_name" id="fp-company_name"
                                       placeholder="e.g. 3M, Ivoclar, GC"
                                       style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;">
                            </div>
                            <div>
                                <label class="pml-label">Brand Name *</label>
                                <input type="text" name="brand" id="fp-brand"
                                       placeholder="e.g. Filtek Z250 XT"
                                       style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;">
                            </div>
                            <div>
                                <label class="pml-label">Alternative Brands (Optional)</label>
                                <div id="alt-brands-list" style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:5px;"></div>
                                <div style="display:flex;gap:6px;">
                                    <input type="text" id="alt-brand-input"
                                           placeholder="Type and press Enter…"
                                           style="flex:1;padding:7px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:12px;font-family:'DM Sans',sans-serif;box-sizing:border-box;"
                                           onkeydown="if(event.key==='Enter'){event.preventDefault();addAltBrand();}">
                                    <button type="button" onclick="addAltBrand()"
                                            style="padding:7px 12px;background:#f5e8d0;border:1px solid #d8c8e4;border-radius:5px;font-size:12px;cursor:pointer;color:#a05c00;">+</button>
                                </div>
                                <div id="alt-brands-hidden"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Product Image --}}
                    <div style="background:#fff;border:1px solid #e8ddf2;border-radius:8px;padding:14px;">
                        <div style="font-family:'Inter',sans-serif;font-size:12px;font-weight:700;
                                    color:#6a0f70;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;">
                            Product Image
                        </div>
                        <div id="product-img-preview"
                             style="width:100%;height:90px;border-radius:6px;background:#fff;
                                    border:1px solid #e8e8e8;margin-bottom:10px;overflow:hidden;
                                    display:flex;align-items:center;justify-content:center;">
                            <img id="product-img-tag" src="" alt=""
                                 style="max-width:100%;max-height:90px;object-fit:contain;display:none;">
                            <span id="product-img-placeholder" style="color:#ccc;font-size:28px;">🖼️</span>
                        </div>
                        <label style="display:block;text-align:center;padding:8px;border:2px dashed #d0d0d0;
                                      border-radius:6px;cursor:pointer;font-size:12px;color:#888;
                                      font-family:'DM Sans',sans-serif;">
                            <input type="file" name="photo" id="fp-photo" accept="image/*"
                                   style="display:none;" onchange="previewProductImage(this)">
                            Drag & drop or click to upload<br>
                            <span style="font-size:10.5px;color:#bbb;">JPG, PNG up to 5MB</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- ── Row 2: Pricing | Location & Stock | Dealer/Supplier ── --}}
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-top:20px;">

                {{-- Pricing & Cost --}}
                <div style="background:#fff;border:1px solid #e8ddf2;border-radius:8px;padding:18px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;
                                padding-bottom:12px;border-bottom:1px solid #f5f0f8;">
                        <div style="width:28px;height:28px;border-radius:6px;background:#f0e8f4;
                                    display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2">
                                <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                        </div>
                        <span style="font-family:'Inter',sans-serif;font-size:12px;font-weight:700;
                                     color:#6a0f70;text-transform:uppercase;letter-spacing:.06em;">
                            Pricing & Cost
                        </span>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:11px;">
                        <div>
                            <label class="pml-label">Purchase Price (₹) *</label>
                            <input type="number" name="last_purchase_price" id="fp-purchase_price"
                                   min="0" step="0.01" placeholder="0.00"
                                   style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;"
                                   oninput="calcCostPerUnit()">
                        </div>
                        <div>
                            <label class="pml-label">MRP (₹) (Optional)</label>
                            <input type="number" name="mrp" id="fp-mrp"
                                   min="0" step="0.01" placeholder="0.00"
                                   style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;">
                        </div>
                        <div>
                            <label class="pml-label">Cost per Unit (Auto)</label>
                            <div id="fp-cost-per-unit"
                                 style="padding:8px 10px;background:#f0f0f0;border:1px solid #e0e0e0;
                                        border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;
                                        color:#555;min-height:36px;">
                                —
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Location & Stock --}}
                <div style="background:#fff;border:1px solid #e8ddf2;border-radius:8px;padding:18px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;
                                padding-bottom:12px;border-bottom:1px solid #f5f0f8;">
                        <div style="width:28px;height:28px;border-radius:6px;background:#f0e8f4;
                                    display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                            </svg>
                        </div>
                        <span style="font-family:'Inter',sans-serif;font-size:12px;font-weight:700;
                                     color:#6a0f70;text-transform:uppercase;letter-spacing:.06em;">
                            Location & Stock
                        </span>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:11px;">
                        <div>
                            <label class="pml-label">Primary Location *</label>
                            <select name="primary_location_id" id="fp-location"
                                    style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;">
                                <option value="">— Select location —</option>
                                @foreach($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                            <label class="pml-label">Minimum Stock (Alert)</label>
                            <div style="display:flex;gap:6px;">
                                <input type="number" name="minimum_qty" id="fp-minimum_qty"
                                       min="0" step="0.01" placeholder="0"
                                       style="flex:1;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;">
                                <div id="fp-unit-label"
                                     style="padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;
                                            font-size:12px;color:#9a85aa;background:#f5f0f8;white-space:nowrap;">
                                    units
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="pml-label">Reorder Level</label>
                            <input type="number" name="reorder_level" id="fp-reorder_level"
                                   min="0" step="0.01" placeholder="0"
                                   style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;">
                        </div>
                        <div>
                            <label class="pml-label">Preferred Brand</label>
                            <input type="text" name="preferred_brand" id="fp-preferred_brand"
                                   style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;">
                        </div>
                        <div>
                            <label class="pml-label">Last Purchase Date</label>
                            <input type="date" name="last_purchase_date" id="fp-last_purchase_date"
                                   style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;box-sizing:border-box;">
                        </div>
                    </div>
                </div>

                {{-- Dealer / Supplier --}}
                <div style="background:#fff;border:1px solid #e8ddf2;border-radius:8px;padding:18px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;
                                padding-bottom:12px;border-bottom:1px solid #f5f0f8;">
                        <div style="width:28px;height:28px;border-radius:6px;background:#f0e8f4;
                                    display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                            </svg>
                        </div>
                        <span style="font-family:'Inter',sans-serif;font-size:12px;font-weight:700;
                                     color:#6a0f70;text-transform:uppercase;letter-spacing:.06em;">
                            Dealer / Supplier
                        </span>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:11px;">
                        <div>
                            <label class="pml-label">Dealer / Supplier *</label>
                            <select name="primary_vendor_id" id="fp-primary_vendor"
                                    style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;">
                                <option value="">— Select dealer —</option>
                                @foreach($vendors as $v)
                                <option value="{{ $v->id }}">{{ $v->vendor_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="pml-label">Alternate Dealers (Optional)</label>
                            <select name="alternate_vendor_ids[]" id="fp-alt_vendors" multiple
                                    style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:12px;font-family:'DM Sans',sans-serif;background:#fff;height:80px;">
                                @foreach($vendors as $v)
                                <option value="{{ $v->id }}">{{ $v->vendor_name }}</option>
                                @endforeach
                            </select>
                            <div style="font-size:10.5px;color:#c08080;margin-top:3px;">Hold Ctrl/Cmd to select multiple</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Row 3: Treatment Tags | Notes ── --}}
            <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-top:20px;">

                {{-- Treatment Tags --}}
                <div style="background:#fff;border:1px solid #e8ddf2;border-radius:8px;padding:18px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;
                                padding-bottom:12px;border-bottom:1px solid #f5f0f8;">
                        <div style="width:28px;height:28px;border-radius:6px;background:#f0e8f4;
                                    display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2">
                                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>
                            </svg>
                        </div>
                        <span style="font-family:'Inter',sans-serif;font-size:12px;font-weight:700;
                                     color:#6a0f70;text-transform:uppercase;letter-spacing:.06em;">
                            Treatment Tags
                            <span style="font-weight:400;color:#9a85aa;font-size:11px;margin-left:4px;text-transform:none;letter-spacing:0;">(select all that apply)</span>
                        </span>
                    </div>
                    @php
                    $allTags = [
                        'Composite Filling','Posterior Restorations','Anterior Restorations',
                        'Indirect Restorations','Aesthetic Dentistry','Veneer','Core Build-up',
                        'Pediatric Dentistry','Class V Restoration','Sealant',
                        'RCT','Endodontics','Pulp Therapy',
                        'Scaling','Periodontics','Flap Surgery',
                        'Implant Placement','Bone Grafting','Sinus Lift',
                        'Impression','Crown','Bridge','Prosthodontics',
                        'Extraction','Oral Surgery','Surgical Extraction',
                        'Orthodontics','Cementation','Whitening',
                    ];
                    @endphp
                    <div style="display:flex;flex-wrap:wrap;gap:6px;" id="treatment-tags-container">
                        @foreach($allTags as $tag)
                        <label style="display:inline-flex;align-items:center;gap:5px;
                                      padding:4px 10px;border:1px solid #d8c8e4;border-radius:12px;
                                      font-size:12px;font-family:'DM Sans',sans-serif;cursor:pointer;
                                      background:#fff;transition:all 100ms;"
                               class="tag-chip">
                            <input type="checkbox" name="treatment_tags[]" value="{{ $tag }}"
                                   style="width:12px;height:12px;accent-color:#6a0f70;"
                                   onchange="this.closest('label').style.background=this.checked?'#f0e8f4':'#fff';
                                             this.closest('label').style.borderColor=this.checked?'#6a0f70':'#d8c8e4';
                                             this.closest('label').style.color=this.checked?'#6a0f70':'inherit';">
                            {{ $tag }}
                        </label>
                        @endforeach
                    </div>
                    <div style="margin-top:10px;display:flex;gap:7px;align-items:center;">
                        <input type="text" id="custom-tag-input" placeholder="+ Add Custom Tag"
                               style="padding:6px 10px;border:1px dashed #d8c8e4;border-radius:5px;
                                      font-size:12px;font-family:'DM Sans',sans-serif;width:200px;"
                               onkeydown="if(event.key==='Enter'){event.preventDefault();addCustomTag();}">
                        <button type="button" onclick="addCustomTag()"
                                style="padding:6px 12px;background:#f0e8f4;border:1px solid #d8c8e4;
                                       border-radius:5px;font-size:12px;cursor:pointer;color:#6a0f70;">Add</button>
                    </div>
                </div>

                {{-- Notes --}}
                <div style="background:#fff;border:1px solid #e8ddf2;border-radius:8px;padding:18px;
                            display:flex;flex-direction:column;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;
                                padding-bottom:12px;border-bottom:1px solid #f5f0f8;">
                        <div style="width:28px;height:28px;border-radius:6px;background:#f0e8f4;
                                    display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </div>
                        <span style="font-family:'Inter',sans-serif;font-size:12px;font-weight:700;
                                     color:#6a0f70;text-transform:uppercase;letter-spacing:.06em;">
                            Notes (Optional)
                        </span>
                    </div>
                    <textarea name="product_notes" id="fp-product_notes" rows="5"
                              placeholder="e.g. Preferred shade range A1-A4 stocked."
                              style="flex:1;width:100%;padding:8px 10px;border:1px solid #e0e0e0;
                                     border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;
                                     box-sizing:border-box;resize:vertical;"></textarea>
                    <div style="margin-top:12px;">
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;
                                      font-family:'DM Sans',sans-serif;cursor:pointer;">
                            <input type="checkbox" name="is_active" id="fp-is_active" value="1" checked
                                   style="width:14px;height:14px;accent-color:#1a7a45;">
                            <span style="color:#444;">Mark as Active</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- ── Footer buttons ── --}}
            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:24px;
                        padding-top:18px;border-top:1px solid #f0e8f4;">
                <button type="button" onclick="closeProductModal()"
                        style="padding:10px 24px;border:1px solid #d8c8e4;border-radius:6px;
                               font-size:13px;font-family:'DM Sans',sans-serif;
                               background:#fff;color:#6a0f70;cursor:pointer;">Cancel</button>
                <button type="submit"
                        style="padding:10px 28px;background:#6a0f70;color:#fff;border:none;
                               border-radius:6px;font-size:13px;font-family:'DM Sans',sans-serif;
                               font-weight:500;cursor:pointer;display:flex;align-items:center;gap:7px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <span id="fp-submit-label">Save Product</span>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.pml-label {
    display:block;
    font-family:'Inter',sans-serif;
    font-size:11px;
    font-weight:600;
    color:#7a6884;
    letter-spacing:.04em;
    text-transform:uppercase;
    margin-bottom:5px;
}
#modal-product input[type="text"],
#modal-product input[type="number"],
#modal-product input[type="date"],
#modal-product select,
#modal-product textarea {
    font-family:'Inter',sans-serif;
    font-size:13px;
    color:#2d1a35;
    transition: border-color 150ms, box-shadow 150ms;
}
#modal-product input[type="text"]:focus,
#modal-product input[type="number"]:focus,
#modal-product input[type="date"]:focus,
#modal-product select:focus,
#modal-product textarea:focus {
    outline: none;
    border-color: #6a0f70 !important;
    box-shadow: 0 0 0 3px rgba(106,15,112,0.08);
}
</style>

<script>
// ── All sub-types + variants as JS for dynamic filtering ───────
const allSubTypes = @json($subTypes->map(fn($st) => ['id'=>$st->id,'name'=>$st->name,'cat'=>$st->category_id]));
const allVariants = @json($variants->map(fn($v) => ['id'=>$v->id,'name'=>$v->name,'st'=>$v->sub_type_id]));

function loadSubTypes(catId) {
    const sel = document.getElementById('fp-sub_type_id');
    const current = sel.value;
    sel.innerHTML = '<option value="">— Select sub type —</option>';
    allSubTypes
        .filter(st => !catId || st.cat == catId)
        .forEach(st => {
            const o = document.createElement('option');
            o.value = st.id; o.textContent = st.name;
            if (st.id == current) o.selected = true;
            sel.appendChild(o);
        });
    loadVariants('');
}

// ── Variant dynamic filter ─────────────────────────────────────
function loadVariants(subTypeId) {
    const sel = document.getElementById('fp-variant_id');
    const btn = document.getElementById('fp-variant-add-btn');
    if (!sel) return;
    const current = sel.dataset.current || '';
    sel.innerHTML = '<option value="">— None / N/A —</option>';
    if (subTypeId) {
        allVariants
            .filter(v => v.st == subTypeId)
            .forEach(v => {
                const o = document.createElement('option');
                o.value = v.id; o.textContent = v.name;
                if (v.id == current) o.selected = true;
                sel.appendChild(o);
            });
        if (btn) btn.disabled = false;
    } else {
        if (btn) btn.disabled = true;
    }
    sel.dataset.current = '';
}

// ── Inline variant add ─────────────────────────────────────────
function toggleVariantInline() {
    const div = document.getElementById('fp-variant-inline');
    const subTypeId = document.getElementById('fp-sub_type_id').value;
    if (!div) return;
    const isOpen = div.style.display === 'flex';
    div.style.display = isOpen ? 'none' : 'flex';
    if (!isOpen) {
        const label = allSubTypes.find(s => s.id == subTypeId);
        document.getElementById('fp-variant-inline-label').textContent = label ? label.name : '';
        document.getElementById('fp-variant-new-name').value = '';
        document.getElementById('fp-variant-new-name').focus();
    }
}

function showVariantMsg(text, color) {
    const el = document.getElementById('fp-variant-inline-msg');
    if (!el) return;
    el.textContent = text;
    el.style.color = color || '#1a7a45';
    el.style.display = text ? 'block' : 'none';
}

function saveInlineVariant() {
    const subTypeId = document.getElementById('fp-sub_type_id').value;
    const nameEl    = document.getElementById('fp-variant-new-name');
    const name      = nameEl ? nameEl.value.trim() : '';
    if (!subTypeId) { showVariantMsg('Select a sub-type first.', '#b52020'); return; }
    if (!name)      { showVariantMsg('Enter a variant name.', '#b52020'); return; }
    fetch('{{ route("inventory.ajax.variants.store") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ sub_type_id: subTypeId, name: name })
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { showVariantMsg(data.error, '#b52020'); return; }
        // Add to in-memory array and select it
        allVariants.push({ id: data.id, name: data.name, st: data.sub_type_id });
        const sel = document.getElementById('fp-variant_id');
        const o   = document.createElement('option');
        o.value = data.id; o.textContent = data.name; o.selected = true;
        sel.appendChild(o);
        showVariantMsg('✓ Added', '#1a7a45');
        setTimeout(() => toggleVariantInline(), 700);
    })
    .catch(() => showVariantMsg('Error — try again.', '#b52020'));
}

// ── Usage count toggle ─────────────────────────────────────────
function toggleUsageCount(value) {
    const row = document.getElementById('fp-usage-count-row');
    if (!row) return;
    row.style.display = value === 'multiple_use' ? 'block' : 'none';
}

// ── Cost per unit auto-calc ────────────────────────────────────
function calcCostPerUnit() {
    const price = parseFloat(document.getElementById('fp-purchase_price').value);
    const qty   = parseFloat(document.getElementById('fp-qty_in_packaging').value);
    const unit  = document.getElementById('fp-packaging_unit_label').value;
    const el    = document.getElementById('fp-cost-per-unit');
    if (price > 0 && qty > 0) {
        el.textContent = '₹' + (price / qty).toFixed(2) + ' / ' + (unit || 'unit');
        el.style.color = '#1a7a45';
    } else {
        el.textContent = '—'; el.style.color = '#555';
    }
}

// ── Alternative brands tag chips ──────────────────────────────
let altBrands = [];
function addAltBrand() {
    const input = document.getElementById('alt-brand-input');
    const val = input.value.trim();
    if (!val || altBrands.includes(val)) { input.value=''; return; }
    altBrands.push(val);
    renderAltBrands();
    input.value = '';
}
function removeAltBrand(i) {
    altBrands.splice(i, 1);
    renderAltBrands();
}
function renderAltBrands() {
    const list = document.getElementById('alt-brands-list');
    const hidden = document.getElementById('alt-brands-hidden');
    list.innerHTML = altBrands.map((b, i) => `
        <span style="display:inline-flex;align-items:center;gap:4px;background:#f5e8d0;
                     color:#a05c00;padding:3px 8px;border-radius:10px;font-size:11.5px;
                     font-family:'DM Sans',sans-serif;">
            ${b}
            <button type="button" onclick="removeAltBrand(${i})"
                    style="background:none;border:none;cursor:pointer;color:#a05c00;font-size:13px;
                           line-height:1;padding:0;margin-left:2px;">&times;</button>
        </span>
    `).join('');
    hidden.innerHTML = altBrands.map(b =>
        `<input type="hidden" name="alternative_brands[]" value="${b}">`
    ).join('');
}

// ── Treatment tags — custom add ────────────────────────────────
function addCustomTag() {
    const input = document.getElementById('custom-tag-input');
    const val = input.value.trim();
    if (!val) return;
    const container = document.getElementById('treatment-tags-container');
    const label = document.createElement('label');
    label.className = 'tag-chip';
    label.style.cssText = 'display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border:1px solid #6a0f70;border-radius:12px;font-size:12px;font-family:\'DM Sans\',sans-serif;cursor:pointer;background:#f0e8f4;color:#6a0f70;';
    label.innerHTML = `<input type="checkbox" name="treatment_tags[]" value="${val}" checked style="width:12px;height:12px;accent-color:#6a0f70;"> ${val}`;
    container.appendChild(label);
    input.value = '';
}

// ── Product image preview ──────────────────────────────────────
function previewProductImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('product-img-tag').src = e.target.result;
            document.getElementById('product-img-tag').style.display = 'block';
            document.getElementById('product-img-placeholder').style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// ── Open Add modal ─────────────────────────────────────────────
function openAddProduct() {
    document.getElementById('modal-product-title').textContent = 'Add New Product';
    document.getElementById('fp-submit-label').textContent = 'Save Product';
    document.getElementById('form-product').action = '{{ route("inventory.products.store") }}';
    document.getElementById('fp-method').value = 'POST';
    document.getElementById('form-product').reset();
    // Reset alt brands
    altBrands = []; renderAltBrands();
    // Reset image preview
    document.getElementById('product-img-tag').style.display = 'none';
    document.getElementById('product-img-placeholder').style.display = 'block';
    // Reset cost/unit display
    document.getElementById('fp-cost-per-unit').textContent = '—';
    // Reset checkboxes style
    document.querySelectorAll('.tag-chip').forEach(l => {
        l.style.background = '#fff'; l.style.borderColor = '#d8c8e4'; l.style.color = '';
    });
    // Reset variant + usage count
    loadVariants('');
    toggleUsageCount('multiple_use');
    document.getElementById('modal-product').style.display = 'flex';
}

// ── Open Edit modal ────────────────────────────────────────────
function openEditProduct(p) {
    document.getElementById('modal-product-title').textContent = 'Edit Product';
    document.getElementById('fp-submit-label').textContent = 'Update Product';
    document.getElementById('form-product').action = `/inventory/products/${p.id}`;
    document.getElementById('fp-method').value = 'PUT';

    // Fill basic fields
    document.getElementById('fp-description').value     = p.description     || '';
    document.getElementById('fp-company_name').value    = p.company_name    || '';
    document.getElementById('fp-brand').value           = p.brand           || '';
    document.getElementById('fp-packaging_type').value  = p.packaging_type  || '';
    document.getElementById('fp-qty_in_packaging').value= p.qty_in_packaging|| '';
    document.getElementById('fp-pack_size_label').value = p.pack_size_label || '';
    document.getElementById('fp-shelf_life_months').value = p.shelf_life_months || '';
    document.getElementById('fp-purchase_price').value  = p.last_purchase_price || '';
    document.getElementById('fp-mrp').value             = p.mrp             || '';
    document.getElementById('fp-minimum_qty').value     = p.minimum_qty     || '';
    document.getElementById('fp-reorder_level').value   = p.reorder_level   || '';
    document.getElementById('fp-preferred_brand').value = p.preferred_brand || '';
    document.getElementById('fp-product_notes').value   = p.product_notes   || '';
    document.getElementById('fp-is_active').checked     = p.is_active;
    if (p.last_purchase_date) {
        document.getElementById('fp-last_purchase_date').value = p.last_purchase_date.substring(0, 10);
    }

    // Packaging unit
    const pul = document.getElementById('fp-packaging_unit_label');
    if (p.packaging_unit_label) pul.value = p.packaging_unit_label;

    // Category + sub-type
    document.getElementById('fp-category_id').value = p.category_id || '';
    loadSubTypes(p.category_id);
    setTimeout(() => {
        document.getElementById('fp-sub_type_id').value = p.sub_type_id || '';
    }, 50);

    // Variant + usage count
    const varSel = document.getElementById('fp-variant_id');
    if (varSel) varSel.dataset.current = p.variant_id || '';
    loadVariants(p.sub_type_id || '');
    setTimeout(() => {
        if (varSel) varSel.value = p.variant_id || '';
    }, 80);
    toggleUsageCount(p.usage_type || 'multiple_use');
    const usageCountEl = document.getElementById('fp-max_usage_count');
    if (usageCountEl) usageCountEl.value = p.max_usage_count || '';


    // Usage type
    document.querySelector(`input[name="usage_type"][value="${p.usage_type||'multiple_use'}"]`).checked = true;

    // Alt brands
    altBrands = Array.isArray(p.alternative_brands) ? [...p.alternative_brands] : [];
    renderAltBrands();

    // Treatment tags
    const tagInputs = document.querySelectorAll('input[name="treatment_tags[]"]');
    const activeTags = Array.isArray(p.treatment_tags) ? p.treatment_tags : [];
    tagInputs.forEach(inp => {
        inp.checked = activeTags.includes(inp.value);
        const lbl = inp.closest('label');
        lbl.style.background     = inp.checked ? '#f0e8f4' : '#fff';
        lbl.style.borderColor    = inp.checked ? '#6a0f70' : '#d8c8e4';
        lbl.style.color          = inp.checked ? '#6a0f70' : '';
    });

    // Dealers
    const primarySel = document.getElementById('fp-primary_vendor');
    const primary = (p.dealers || []).find(d => d.pivot && d.pivot.is_primary);
    if (primary) primarySel.value = primary.id;

    const altSel = document.getElementById('fp-alt_vendors');
    const altIds = (p.dealers || []).filter(d => d.pivot && d.pivot.is_alternate).map(d => d.id);
    Array.from(altSel.options).forEach(o => { o.selected = altIds.includes(parseInt(o.value)); });

    // Image preview
    if (p.image) {
        document.getElementById('product-img-tag').src = `/storage/${p.image}`;
        document.getElementById('product-img-tag').style.display = 'block';
        document.getElementById('product-img-placeholder').style.display = 'none';
    } else {
        document.getElementById('product-img-tag').style.display = 'none';
        document.getElementById('product-img-placeholder').style.display = 'block';
    }

    calcCostPerUnit();
    document.getElementById('modal-product').style.display = 'flex';
}

function closeProductModal() {
    document.getElementById('modal-product').style.display = 'none';
}

// Close on backdrop click
document.getElementById('modal-product').addEventListener('click', function(e) {
    if (e.target === this) closeProductModal();
});
</script>


</div>{{-- end #view-table --}}

<script>
// ── Live search: auto-submit after 400ms pause ─────────────────
let invSearchTimer;
const invSearchInput = document.getElementById('inv-search-input');
if (invSearchInput) {
    invSearchInput.addEventListener('input', function() {
        clearTimeout(invSearchTimer);
        invSearchTimer = setTimeout(() => this.closest('form').submit(), 400);
    });
}
</script>

@endsection
