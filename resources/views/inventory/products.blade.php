{{--
|===========================================================================
| Product Master List
| Full catalogue of inventory products — add/edit here, not from stock view
|===========================================================================
--}}
@extends('layouts.app')
@section('title', 'Product Master List')

@section('content')
@include('inventory.partials.subnav')

{{-- ── Page header ── --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
    <div>
        <h1 style="font-family:'Cormorant Garamond',serif;font-size:26px;font-weight:600;
                   color:#1a0a1e;margin:0 0 2px;">Product Master List</h1>
        <p style="font-family:'DM Sans',sans-serif;font-size:13px;color:#7a6884;margin:0;">
            Manage all products in your inventory catalogue
        </p>
    </div>
    <button onclick="openAddProduct()"
            style="background:#6a0f70;color:#fff;border:none;border-radius:6px;
                   padding:9px 18px;font-size:13px;font-family:'DM Sans',sans-serif;
                   font-weight:500;cursor:pointer;display:flex;align-items:center;gap:7px;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add New Product
    </button>
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
      style="display:flex;gap:10px;margin-bottom:16px;align-items:center;">
    <input type="text" name="q" value="{{ $search }}"
           placeholder="Search by name, brand, company…"
           style="flex:1;padding:8px 12px;border:1px solid #d8c8e4;border-radius:6px;
                  font-size:13px;font-family:'DM Sans',sans-serif;max-width:340px;">
    <select name="category_id"
            style="padding:8px 12px;border:1px solid #d8c8e4;border-radius:6px;
                   font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;">
        <option value="">All Categories</option>
        @foreach($categories as $cat)
        <option value="{{ $cat->id }}" @selected($catId == $cat->id)>{{ $cat->name }}</option>
        @endforeach
    </select>
    <button type="submit"
            style="padding:8px 16px;background:#6a0f70;color:#fff;border:none;border-radius:6px;
                   font-size:13px;font-family:'DM Sans',sans-serif;cursor:pointer;">Filter</button>
    @if($search || $catId)
    <a href="{{ route('inventory.products') }}"
       style="padding:8px 14px;border:1px solid #d8c8e4;border-radius:6px;
              font-size:13px;font-family:'DM Sans',sans-serif;color:#6a0f70;text-decoration:none;">Clear</a>
    @endif
</form>

{{-- ── Products table ── --}}
<div style="background:#fff;border:1px solid rgba(185,92,183,0.12);border-radius:8px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="background:#faf5fb;border-bottom:1px solid rgba(185,92,183,0.10);">
                <th style="padding:10px 18px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;width:40px;"></th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Product Name</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Category / Sub-type</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Brand / Company</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Dealer</th>
                <th style="padding:10px 14px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Treatment Tags</th>
                <th style="padding:10px 14px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Status</th>
                <th style="padding:10px 18px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $product)
            @php
                $tags     = $product->treatment_tags ?? [];
                $dealers  = $product->dealers;
                $primary  = $dealers->firstWhere('pivot.is_primary', true);
                $altCount = $dealers->where('pivot.is_alternate', true)->count();
            @endphp
            <tr style="border-bottom:1px solid rgba(185,92,183,0.05);transition:background 120ms;"
                onmouseover="this.style.background='#faf5fb'" onmouseout="this.style.background=''">
                <td style="padding:10px 18px;width:44px;">
                    @if($product->image)
                    <img src="{{ asset('storage/'.$product->image) }}" alt=""
                         style="width:36px;height:36px;object-fit:cover;border-radius:5px;
                                border:1px solid #f0e8f4;">
                    @else
                    <div style="width:36px;height:36px;border-radius:5px;background:#f5f0f8;
                                display:flex;align-items:center;justify-content:center;
                                font-size:16px;">💊</div>
                    @endif
                </td>
                <td style="padding:10px 14px;">
                    <div style="font-weight:500;color:#1e0a2c;font-family:'DM Sans',sans-serif;">
                        {{ $product->product_name }}
                    </div>
                    @if($product->generic_name && $product->generic_name !== $product->product_name)
                    <div style="font-size:11.5px;color:#9a85aa;">{{ $product->generic_name }}</div>
                    @endif
                    <div style="font-size:10.5px;color:#c0b0d0;font-family:'DM Mono',monospace;margin-top:1px;">
                        {{ $product->item_code }}
                    </div>
                </td>
                <td style="padding:10px 14px;">
                    <span style="font-size:12px;color:#4e2060;">{{ $product->category?->name ?? '—' }}</span>
                    @if($product->subType)
                    <div style="font-size:11px;color:#9a85aa;margin-top:1px;">{{ $product->subType->name }}</div>
                    @endif
                </td>
                <td style="padding:10px 14px;">
                    <div style="font-size:12.5px;color:#1e0a2c;">{{ $product->brand ?: '—' }}</div>
                    @if($product->company_name)
                    <div style="font-size:11px;color:#9a85aa;">{{ $product->company_name }}</div>
                    @endif
                </td>
                <td style="padding:10px 14px;font-size:12.5px;">
                    @if($primary)
                    <span style="color:#4e2060;">{{ $primary->name }}</span>
                    @if($altCount > 0)
                    <span style="font-size:10.5px;color:#9a85aa;margin-left:4px;">+{{ $altCount }}</span>
                    @endif
                    @else
                    <span style="color:#c0b0d0;">—</span>
                    @endif
                </td>
                <td style="padding:10px 14px;text-align:center;">
                    @if(count($tags))
                    @foreach(array_slice($tags, 0, 2) as $tag)
                    <span style="display:inline-block;background:#f0e8f4;color:#6a0f70;padding:2px 7px;
                                 border-radius:10px;font-size:10.5px;margin:1px;white-space:nowrap;">
                        {{ $tag }}
                    </span>
                    @endforeach
                    @if(count($tags) > 2)
                    <span style="font-size:10.5px;color:#9a85aa;">+{{ count($tags) - 2 }}</span>
                    @endif
                    @else
                    <span style="color:#c0b0d0;font-size:12px;">—</span>
                    @endif
                </td>
                <td style="padding:10px 14px;text-align:center;">
                    @if($product->is_active)
                    <span style="background:#e8f7ee;color:#1a7a45;padding:2px 10px;border-radius:10px;font-size:11px;font-weight:500;">Active</span>
                    @else
                    <span style="background:#f5f5f5;color:#888;padding:2px 10px;border-radius:10px;font-size:11px;font-weight:500;">Inactive</span>
                    @endif
                </td>
                <td style="padding:10px 18px;text-align:center;">
                    <div style="display:flex;gap:6px;justify-content:center;">
                        <button onclick='openEditProduct({{ $product->load("dealers")->toJson() }})'
                                style="background:#f5f0f8;border:1px solid rgba(106,15,112,0.12);
                                       border-radius:4px;padding:5px 12px;font-size:12px;
                                       font-family:'DM Sans',sans-serif;color:#6a0f70;cursor:pointer;">
                            Edit
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" style="padding:60px;text-align:center;">
                    <div style="font-size:36px;margin-bottom:12px;">📦</div>
                    <div style="font-family:'DM Sans',sans-serif;font-size:14px;color:#9070a0;">
                        No products in master list yet.<br>
                        <span style="font-size:12px;">Click "Add New Product" to create your first product.</span>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @if($products->hasPages())
    <div style="padding:14px 18px;border-top:1px solid rgba(185,92,183,0.07);">{{ $products->links() }}</div>
    @endif
</div>

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
        <div style="padding:18px 28px;border-bottom:1px solid #f0e8f4;
                    display:flex;align-items:flex-start;justify-content:space-between;
                    background:#faf5fb;border-radius:10px 10px 0 0;">
            <div>
                <h3 id="modal-product-title"
                    style="font-family:'Cormorant Garamond',serif;font-size:22px;font-weight:600;
                           color:#1a0a1e;margin:0 0 2px;">Add New Product</h3>
                <p style="font-family:'DM Sans',sans-serif;font-size:12px;color:#9070a0;margin:0;">
                    Create a new product in your inventory
                </p>
            </div>
            <button onclick="closeProductModal()"
                    style="background:none;border:none;cursor:pointer;font-size:24px;
                           color:#9070a0;line-height:1;padding:0;">&times;</button>
        </div>

        <form id="form-product" method="POST" action="{{ route('inventory.products.store') }}"
              enctype="multipart/form-data" style="padding:24px 28px;">
            @csrf
            <input type="hidden" id="fp-method" name="_method" value="POST">

            {{-- ── 3-column grid ── --}}
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;">

                {{-- COL 1: Basic Information --}}
                <div style="background:#faf5fb;border:1px solid rgba(185,92,183,0.10);
                            border-radius:8px;padding:18px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
                        <span style="font-size:16px;">📋</span>
                        <span style="font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;color:#4e2060;">
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
                            <label class="pml-label">Sub Type *</label>
                            <select name="sub_type_id" id="fp-sub_type_id"
                                    style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;">
                                <option value="">— Select sub type —</option>
                                @foreach($subTypes as $st)
                                <option value="{{ $st->id }}" data-cat="{{ $st->category_id }}">{{ $st->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="pml-label">Usage</label>
                            <div style="display:flex;gap:16px;margin-top:4px;">
                                <label style="display:flex;align-items:center;gap:6px;font-size:13px;font-family:'DM Sans',sans-serif;cursor:pointer;">
                                    <input type="radio" name="usage_type" id="fp-usage-single" value="single_use">
                                    Single Use
                                </label>
                                <label style="display:flex;align-items:center;gap:6px;font-size:13px;font-family:'DM Sans',sans-serif;cursor:pointer;">
                                    <input type="radio" name="usage_type" id="fp-usage-multiple" value="multiple_use" checked>
                                    Multiple Use
                                </label>
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
                <div style="background:#f5fafe;border:1px solid rgba(26,94,168,0.10);
                            border-radius:8px;padding:18px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
                        <span style="font-size:16px;">📦</span>
                        <span style="font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;color:#1a5ea8;">
                            Packaging Details
                        </span>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:11px;">
                        <div>
                            <label class="pml-label">Packaging Type *</label>
                            <select name="packaging_type" id="fp-packaging_type"
                                    style="width:100%;padding:8px 10px;border:1px solid #c8d8ea;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;">
                                <option value="">— Select —</option>
                                @foreach(['Syringe','Bottle','Box','Strip','Vial','Sachet','Kit','Capsule','Tube','Jar','Blister Pack'] as $pt)
                                <option value="{{ $pt }}">{{ $pt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="pml-label">Qty in Packaging *</label>
                            <div style="display:flex;gap:6px;">
                                <input type="number" name="qty_in_packaging" id="fp-qty_in_packaging"
                                       min="0" step="0.01" placeholder="e.g. 4"
                                       style="flex:1;padding:8px 10px;border:1px solid #c8d8ea;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;"
                                       oninput="calcCostPerUnit()">
                                <select name="packaging_unit_label" id="fp-packaging_unit_label"
                                        style="width:70px;padding:8px 6px;border:1px solid #c8d8ea;border-radius:5px;font-size:12px;font-family:'DM Sans',sans-serif;background:#fff;">
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
                                   style="width:100%;padding:8px 10px;border:1px solid #c8d8ea;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;">
                        </div>
                        <div>
                            <label class="pml-label">Shelf Life (Optional)</label>
                            <div style="display:flex;gap:6px;align-items:center;">
                                <input type="number" name="shelf_life_months" id="fp-shelf_life_months"
                                       min="0" placeholder="e.g. 36"
                                       style="flex:1;padding:8px 10px;border:1px solid #c8d8ea;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;">
                                <span style="font-size:12px;color:#9a85aa;white-space:nowrap;">Months</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- COL 3: Company & Brand + Product Image --}}
                <div style="display:flex;flex-direction:column;gap:14px;">
                    <div style="background:#fff8f0;border:1px solid rgba(160,92,0,0.12);
                                border-radius:8px;padding:18px;flex:1;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
                            <span style="font-size:16px;">🏢</span>
                            <span style="font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;color:#a05c00;">
                                Company & Brand
                            </span>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:11px;">
                            <div>
                                <label class="pml-label">Company Name *</label>
                                <input type="text" name="company_name" id="fp-company_name"
                                       placeholder="e.g. 3M, Ivoclar, GC"
                                       style="width:100%;padding:8px 10px;border:1px solid #e8d8c0;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;">
                            </div>
                            <div>
                                <label class="pml-label">Brand Name *</label>
                                <input type="text" name="brand" id="fp-brand"
                                       placeholder="e.g. Filtek Z250 XT"
                                       style="width:100%;padding:8px 10px;border:1px solid #e8d8c0;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;">
                            </div>
                            <div>
                                <label class="pml-label">Alternative Brands (Optional)</label>
                                <div id="alt-brands-list" style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:5px;"></div>
                                <div style="display:flex;gap:6px;">
                                    <input type="text" id="alt-brand-input"
                                           placeholder="Type and press Enter…"
                                           style="flex:1;padding:7px 10px;border:1px solid #e8d8c0;border-radius:5px;font-size:12px;font-family:'DM Sans',sans-serif;box-sizing:border-box;"
                                           onkeydown="if(event.key==='Enter'){event.preventDefault();addAltBrand();}">
                                    <button type="button" onclick="addAltBrand()"
                                            style="padding:7px 12px;background:#f5e8d0;border:1px solid #e8d8c0;border-radius:5px;font-size:12px;cursor:pointer;color:#a05c00;">+</button>
                                </div>
                                <div id="alt-brands-hidden"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Product Image --}}
                    <div style="background:#f8f8f8;border:1px solid #e8e8e8;border-radius:8px;padding:14px;">
                        <div style="font-family:'DM Sans',sans-serif;font-size:12px;font-weight:600;color:#555;margin-bottom:10px;">
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
                <div style="background:#f5fbf7;border:1px solid rgba(26,122,69,0.10);
                            border-radius:8px;padding:18px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
                        <span style="font-size:16px;">💰</span>
                        <span style="font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;color:#1a7a45;">
                            Pricing & Cost
                        </span>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:11px;">
                        <div>
                            <label class="pml-label">Purchase Price (₹) *</label>
                            <input type="number" name="last_purchase_price" id="fp-purchase_price"
                                   min="0" step="0.01" placeholder="0.00"
                                   style="width:100%;padding:8px 10px;border:1px solid #c0d8c8;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;"
                                   oninput="calcCostPerUnit()">
                        </div>
                        <div>
                            <label class="pml-label">MRP (₹) (Optional)</label>
                            <input type="number" name="mrp" id="fp-mrp"
                                   min="0" step="0.01" placeholder="0.00"
                                   style="width:100%;padding:8px 10px;border:1px solid #c0d8c8;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;">
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
                <div style="background:#f8f5fb;border:1px solid rgba(106,15,112,0.10);
                            border-radius:8px;padding:18px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
                        <span style="font-size:16px;">📍</span>
                        <span style="font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;color:#6a0f70;">
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
                        <div>
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
                <div style="background:#fdf5f5;border:1px solid rgba(181,32,32,0.10);
                            border-radius:8px;padding:18px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
                        <span style="font-size:16px;">🏪</span>
                        <span style="font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;color:#b52020;">
                            Dealer / Supplier
                        </span>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:11px;">
                        <div>
                            <label class="pml-label">Dealer / Supplier *</label>
                            <select name="primary_vendor_id" id="fp-primary_vendor"
                                    style="width:100%;padding:8px 10px;border:1px solid #f0c8c8;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;">
                                <option value="">— Select dealer —</option>
                                @foreach($vendors as $v)
                                <option value="{{ $v->id }}">{{ $v->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="pml-label">Alternate Dealers (Optional)</label>
                            <select name="alternate_vendor_ids[]" id="fp-alt_vendors" multiple
                                    style="width:100%;padding:8px 10px;border:1px solid #f0c8c8;border-radius:5px;font-size:12px;font-family:'DM Sans',sans-serif;background:#fff;height:80px;">
                                @foreach($vendors as $v)
                                <option value="{{ $v->id }}">{{ $v->name }}</option>
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
                <div style="background:#faf5fb;border:1px solid rgba(185,92,183,0.10);border-radius:8px;padding:18px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
                        <span style="font-size:16px;">🏷️</span>
                        <span style="font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;color:#6a0f70;">
                            Treatment Tags
                            <span style="font-weight:400;color:#9a85aa;font-size:11px;margin-left:4px;">(Select all that apply)</span>
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
                <div style="background:#fafafa;border:1px solid #eee;border-radius:8px;padding:18px;
                            display:flex;flex-direction:column;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
                        <span style="font-size:16px;">📝</span>
                        <span style="font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;color:#555;">
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
    font-family:'DM Sans',sans-serif;
    font-size:11.5px;
    font-weight:600;
    color:#666;
    letter-spacing:.03em;
    text-transform:uppercase;
    margin-bottom:4px;
}
</style>

<script>
// ── All sub-types as JS for dynamic filtering ──────────────────
const allSubTypes = @json($subTypes->map(fn($st) => ['id'=>$st->id,'name'=>$st->name,'cat'=>$st->category_id]));

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
    document.getElementById('modal-product').style.display = 'flex';
}

// ── Open Edit modal ────────────────────────────────────────────
function openEditProduct(p) {
    document.getElementById('modal-product-title').textContent = 'Edit Product';
    document.getElementById('fp-submit-label').textContent = 'Update Product';
    document.getElementById('form-product').action = `/inventory/products/${p.id}`;
    document.getElementById('fp-method').value = 'PUT';

    // Fill basic fields
    document.getElementById('fp-product_name').value    = p.product_name    || '';
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

@endsection
