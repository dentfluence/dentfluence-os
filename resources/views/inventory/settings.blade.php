{{--
|==========================================================================
| Inventory Settings — Admin Only
| Sections: Categories, Locations, Alert Thresholds
|==========================================================================
--}}
@extends('layouts.app')

@section('title', 'Inventory Settings')

@section('content')
@include('inventory.partials.subnav')

{{-- ── Page header ── --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
    <div>
        <h1 style="font-family:'Cormorant Garamond',serif;font-size:26px;font-weight:600;color:#1a0a1e;margin:0 0 2px;">
            Inventory Settings
        </h1>
        <p style="font-family:'DM Sans',sans-serif;font-size:13px;color:#7a6884;margin:0;">
            Manage categories, storage locations and global alert thresholds. Admin access only.
        </p>
    </div>
    <span style="background:#fdeaea;color:#b52020;font-size:11px;font-weight:600;
                 padding:4px 10px;border-radius:20px;font-family:'DM Sans',sans-serif;
                 border:1px solid #f5c6c6;">
        ⚙ Admin Only
    </span>
</div>

{{-- ── Flash messages ── --}}
@if(session('success'))
<div style="background:#e8f7ee;border:1px solid #a3d9b8;color:#1a7a45;padding:10px 16px;
            border-radius:6px;font-size:13px;font-family:'DM Sans',sans-serif;margin-bottom:20px;">
    ✓ {{ session('success') }}
</div>
@endif
@if($errors->any())
<div style="background:#fdeaea;border:1px solid #f5c6c6;color:#b52020;padding:10px 16px;
            border-radius:6px;font-size:13px;font-family:'DM Sans',sans-serif;margin-bottom:20px;">
    @foreach($errors->all() as $err) <div>⚠ {{ $err }}</div> @endforeach
</div>
@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

    {{-- ═══════════════════════════════════════════════
         SECTION 1 — CATEGORIES
    ═══════════════════════════════════════════════ --}}
    <div style="background:#fff;border:1px solid rgba(185,92,183,0.12);border-radius:8px;overflow:hidden;">

        {{-- Header --}}
        <div style="background:linear-gradient(135deg,#f9f3fa,#f3e8f7);padding:16px 20px;
                    border-bottom:1px solid rgba(185,92,183,0.12);
                    display:flex;align-items:center;justify-content:space-between;">
            <div>
                <h2 style="font-family:'DM Sans',sans-serif;font-size:14px;font-weight:600;
                           color:#4e0a53;margin:0 0 2px;">Item Categories</h2>
                <p style="font-family:'DM Sans',sans-serif;font-size:12px;color:#9070a0;margin:0;">
                    {{ $categories->count() }} categories
                </p>
            </div>
            <button onclick="document.getElementById('modal-add-category').style.display='flex'"
                    style="background:#6a0f70;color:#fff;border:none;border-radius:6px;
                           padding:7px 14px;font-size:12px;font-family:'DM Sans',sans-serif;
                           font-weight:500;cursor:pointer;display:flex;align-items:center;gap:5px;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add
            </button>
        </div>

        {{-- Category list --}}
        <div style="padding:0;">
            @forelse($categories as $cat)
            <div style="display:flex;align-items:center;gap:12px;padding:11px 20px;
                        border-bottom:1px solid #f5f0f8;
                        {{ !$cat->is_active ? 'opacity:0.5;' : '' }}">
                {{-- Colour swatch --}}
                <span style="width:10px;height:10px;border-radius:50%;flex-shrink:0;
                             background:{{ $cat->color ?: '#ccc' }};
                             border:1px solid rgba(0,0,0,0.08);"></span>
                <div style="flex:1;min-width:0;">
                    <div style="font-family:'DM Sans',sans-serif;font-size:13px;font-weight:500;
                                color:#1a0a1e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        {{ $cat->name }}
                        @if(!$cat->is_active)
                            <span style="font-size:10px;color:#9070a0;margin-left:4px;">(inactive)</span>
                        @endif
                    </div>
                    @if($cat->description)
                    <div style="font-family:'DM Sans',sans-serif;font-size:11px;color:#9070a0;
                                white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        {{ $cat->description }}
                    </div>
                    @endif
                </div>
                <span style="font-family:'DM Mono',monospace;font-size:11px;color:#9070a0;flex-shrink:0;">
                    {{ $cat->items_count }} items
                </span>
                <div style="display:flex;gap:4px;flex-shrink:0;">
                    <button onclick='openEditCategory({{ $cat->toJson() }})'
                            style="background:#f5f0f8;border:none;border-radius:4px;
                                   padding:5px 9px;cursor:pointer;font-size:11px;color:#6a0f70;">
                        Edit
                    </button>
                    @if($cat->items_count == 0)
                    <form method="POST" action="{{ route('inventory.settings.categories.destroy', $cat) }}"
                          onsubmit="return confirm('Delete category \'{{ $cat->name }}\'?')">
                        @csrf @method('DELETE')
                        <button type="submit"
                                style="background:#fdeaea;border:none;border-radius:4px;
                                       padding:5px 9px;cursor:pointer;font-size:11px;color:#b52020;">
                            ✕
                        </button>
                    </form>
                    @endif
                </div>
            </div>
            @empty
            <div style="padding:32px;text-align:center;font-family:'DM Sans',sans-serif;
                        font-size:13px;color:#9070a0;">
                No categories yet. Add one above.
            </div>
            @endforelse
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════
         SECTION 1b — SUB-TYPES
    ═══════════════════════════════════════════════ --}}
    <div style="background:#fff;border:1px solid rgba(185,92,183,0.12);border-radius:8px;overflow:hidden;">

        <div style="background:linear-gradient(135deg,#f5fbf7,#e8f7ee);padding:16px 20px;
                    border-bottom:1px solid rgba(26,122,69,0.10);
                    display:flex;align-items:center;justify-content:space-between;">
            <div>
                <h3 style="font-family:'DM Sans',sans-serif;font-size:14px;font-weight:600;
                           color:#1a7a45;margin:0 0 2px;">Sub-types</h3>
                <p style="font-family:'DM Sans',sans-serif;font-size:12px;color:#5a9a6a;margin:0;">
                    Product sub-types per category (e.g. Restorative → Composite, GIC)
                </p>
            </div>
            <button onclick="document.getElementById('modal-add-subtype').style.display='flex'"
                    style="background:#1a7a45;color:#fff;border:none;border-radius:5px;
                           padding:7px 14px;font-size:12px;font-family:'DM Sans',sans-serif;
                           font-weight:500;cursor:pointer;">+ Add Sub-type</button>
        </div>

        <div style="padding:16px 20px;">
            @forelse($subTypes->groupBy(fn($st) => $st->category?->name ?? 'Uncategorised') as $catName => $group)
            <div style="margin-bottom:16px;">
                <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;
                            color:#1a7a45;font-family:'DM Sans',sans-serif;margin-bottom:8px;">
                    {{ $catName }}
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                    @foreach($group as $st)
                    <div style="display:inline-flex;align-items:center;gap:6px;
                                background:{{ $st->is_active ? '#e8f7ee' : '#f5f5f5' }};
                                border:1px solid {{ $st->is_active ? '#a3d9b8' : '#ddd' }};
                                border-radius:20px;padding:4px 10px 4px 12px;font-size:12.5px;
                                font-family:'DM Sans',sans-serif;
                                color:{{ $st->is_active ? '#1a7a45' : '#888' }};">
                        {{ $st->name }}
                        <button onclick="openEditSubType({{ $st->id }}, '{{ addslashes($st->name) }}', {{ $st->category_id }}, {{ $st->is_active ? 'true' : 'false' }})"
                                style="background:none;border:none;cursor:pointer;font-size:11px;
                                       color:#888;padding:0;line-height:1;" title="Edit">✎</button>
                        <form method="POST" action="{{ route('inventory.settings.sub-types.destroy', $st) }}"
                              style="display:inline;"
                              onsubmit="return confirm('Delete sub-type {{ addslashes($st->name) }}?')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    style="background:none;border:none;cursor:pointer;font-size:13px;
                                           color:#b52020;padding:0;line-height:1;" title="Delete">×</button>
                        </form>
                    </div>
                    @endforeach
                </div>
            </div>
            @empty
            <div style="padding:24px;text-align:center;font-family:'DM Sans',sans-serif;
                        font-size:13px;color:#9070a0;">
                No sub-types yet. Add one above.
            </div>
            @endforelse
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════
         SECTION 2 — LOCATIONS
    ═══════════════════════════════════════════════ --}}
    <div style="background:#fff;border:1px solid rgba(185,92,183,0.12);border-radius:8px;overflow:hidden;">

        <div style="background:linear-gradient(135deg,#f9f3fa,#f3e8f7);padding:16px 20px;
                    border-bottom:1px solid rgba(185,92,183,0.12);
                    display:flex;align-items:center;justify-content:space-between;">
            <div>
                <h2 style="font-family:'DM Sans',sans-serif;font-size:14px;font-weight:600;
                           color:#4e0a53;margin:0 0 2px;">Storage Locations</h2>
                <p style="font-family:'DM Sans',sans-serif;font-size:12px;color:#9070a0;margin:0;">
                    {{ $locations->count() }} locations
                </p>
            </div>
            <button onclick="document.getElementById('modal-add-location').style.display='flex'"
                    style="background:#6a0f70;color:#fff;border:none;border-radius:6px;
                           padding:7px 14px;font-size:12px;font-family:'DM Sans',sans-serif;
                           font-weight:500;cursor:pointer;display:flex;align-items:center;gap:5px;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add
            </button>
        </div>

        <div style="padding:0;">
            @forelse($locations as $loc)
            <div style="display:flex;align-items:center;gap:12px;padding:11px 20px;
                        border-bottom:1px solid #f5f0f8;
                        {{ !$loc->is_active ? 'opacity:0.5;' : '' }}">
                <span style="font-size:14px;flex-shrink:0;">
                    @switch($loc->type)
                        @case('main_store') 🏪 @break
                        @case('operatory') 🦷 @break
                        @case('sterilization') 🧪 @break
                        @case('lab') 🔬 @break
                        @case('implant_drawer') 🗂️ @break
                        @case('storage') 📦 @break
                        @default 📍
                    @endswitch
                </span>
                <div style="flex:1;min-width:0;">
                    <div style="font-family:'DM Sans',sans-serif;font-size:13px;font-weight:500;
                                color:#1a0a1e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        {{ $loc->name }}
                        @if(!$loc->is_active)
                            <span style="font-size:10px;color:#9070a0;margin-left:4px;">(inactive)</span>
                        @endif
                    </div>
                    <div style="font-family:'DM Sans',sans-serif;font-size:11px;color:#9070a0;">
                        {{ $loc->getTypeLabel() }}
                        @if($loc->code) · <span style="font-family:'DM Mono',monospace;">{{ $loc->code }}</span>@endif
                    </div>
                </div>
                <div style="display:flex;gap:4px;flex-shrink:0;">
                    <button onclick='openEditLocation({{ $loc->toJson() }})'
                            style="background:#f5f0f8;border:none;border-radius:4px;
                                   padding:5px 9px;cursor:pointer;font-size:11px;color:#6a0f70;">
                        Edit
                    </button>
                    <form method="POST" action="{{ route('inventory.settings.locations.destroy', $loc) }}"
                          onsubmit="return confirm('Deactivate location \'{{ $loc->name }}\'?')">
                        @csrf @method('DELETE')
                        <button type="submit"
                                style="background:#fdeaea;border:none;border-radius:4px;
                                       padding:5px 9px;cursor:pointer;font-size:11px;color:#b52020;">
                            ✕
                        </button>
                    </form>
                </div>
            </div>
            @empty
            <div style="padding:32px;text-align:center;font-family:'DM Sans',sans-serif;
                        font-size:13px;color:#9070a0;">
                No locations yet. Add one above.
            </div>
            @endforelse
        </div>
    </div>

</div>{{-- end grid --}}

{{-- ══════════════════════════════════════════════════════════
     MODAL — Add Category
══════════════════════════════════════════════════════════ --}}
<div id="modal-add-category"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);
            z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:10px;width:420px;max-width:94vw;
                box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="padding:18px 24px;border-bottom:1px solid #f0e8f4;
                    display:flex;align-items:center;justify-content:space-between;">
            <h3 style="font-family:'DM Sans',sans-serif;font-size:15px;font-weight:600;
                       color:#1a0a1e;margin:0;">Add Category</h3>
            <button onclick="document.getElementById('modal-add-category').style.display='none'"
                    style="background:none;border:none;cursor:pointer;font-size:20px;
                           color:#9070a0;line-height:1;">&times;</button>
        </div>
        <form method="POST" action="{{ route('inventory.settings.categories.store') }}"
              style="padding:20px 24px;">
            @csrf
            <div style="margin-bottom:14px;">
                <label class="inv-label">Category Name *</label>
                <input type="text" name="name" required
                       style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;
                              border-radius:6px;font-size:13px;font-family:'DM Sans',sans-serif;
                              box-sizing:border-box;">
            </div>
            <div style="margin-bottom:14px;">
                <label class="inv-label">Colour Tag</label>
                <div style="display:flex;align-items:center;gap:10px;">
                    <input type="color" name="color" value="#6a0f70"
                           style="width:36px;height:36px;border:1px solid #d8c8e4;
                                  border-radius:4px;cursor:pointer;padding:2px;">
                    <span style="font-size:12px;color:#9070a0;font-family:'DM Sans',sans-serif;">
                        Pick a colour for dashboard charts
                    </span>
                </div>
            </div>
            <div style="margin-bottom:18px;">
                <label class="inv-label">Description</label>
                <input type="text" name="description" maxlength="255"
                       style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;
                              border-radius:6px;font-size:13px;font-family:'DM Sans',sans-serif;
                              box-sizing:border-box;">
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;">
                <button type="button"
                        onclick="document.getElementById('modal-add-category').style.display='none'"
                        style="background:#f5f0f8;border:none;border-radius:6px;padding:8px 18px;
                               font-size:13px;font-family:'DM Sans',sans-serif;cursor:pointer;
                               color:#6a0f70;">
                    Cancel
                </button>
                <button type="submit"
                        style="background:#6a0f70;color:#fff;border:none;border-radius:6px;
                               padding:8px 18px;font-size:13px;font-family:'DM Sans',sans-serif;
                               font-weight:500;cursor:pointer;">
                    Add Category
                </button>
            </div>
        </form>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════
     MODAL — Edit Category
══════════════════════════════════════════════════════════ --}}
<div id="modal-edit-category"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);
            z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:10px;width:420px;max-width:94vw;
                box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="padding:18px 24px;border-bottom:1px solid #f0e8f4;
                    display:flex;align-items:center;justify-content:space-between;">
            <h3 style="font-family:'DM Sans',sans-serif;font-size:15px;font-weight:600;
                       color:#1a0a1e;margin:0;">Edit Category</h3>
            <button onclick="document.getElementById('modal-edit-category').style.display='none'"
                    style="background:none;border:none;cursor:pointer;font-size:20px;
                           color:#9070a0;line-height:1;">&times;</button>
        </div>
        <form id="form-edit-category" method="POST" action="" style="padding:20px 24px;">
            @csrf @method('PUT')
            <div style="margin-bottom:14px;">
                <label class="inv-label">Category Name *</label>
                <input type="text" id="edit-cat-name" name="name" required
                       style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;
                              border-radius:6px;font-size:13px;font-family:'DM Sans',sans-serif;
                              box-sizing:border-box;">
            </div>
            <div style="margin-bottom:14px;">
                <label class="inv-label">Colour Tag</label>
                <input type="color" id="edit-cat-color" name="color"
                       style="width:36px;height:36px;border:1px solid #d8c8e4;
                              border-radius:4px;cursor:pointer;padding:2px;">
            </div>
            <div style="margin-bottom:14px;">
                <label class="inv-label">Description</label>
                <input type="text" id="edit-cat-desc" name="description" maxlength="255"
                       style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;
                              border-radius:6px;font-size:13px;font-family:'DM Sans',sans-serif;
                              box-sizing:border-box;">
            </div>
            <div style="margin-bottom:18px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" id="edit-cat-active" name="is_active" value="1"
                           style="accent-color:#6a0f70;">
                    <span style="font-size:13px;font-family:'DM Sans',sans-serif;color:#1a0a1e;">Active</span>
                </label>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;">
                <button type="button"
                        onclick="document.getElementById('modal-edit-category').style.display='none'"
                        style="background:#f5f0f8;border:none;border-radius:6px;padding:8px 18px;
                               font-size:13px;font-family:'DM Sans',sans-serif;cursor:pointer;
                               color:#6a0f70;">
                    Cancel
                </button>
                <button type="submit"
                        style="background:#6a0f70;color:#fff;border:none;border-radius:6px;
                               padding:8px 18px;font-size:13px;font-family:'DM Sans',sans-serif;
                               font-weight:500;cursor:pointer;">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════
     MODAL — Add Location
══════════════════════════════════════════════════════════ --}}
<div id="modal-add-location"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);
            z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:10px;width:440px;max-width:94vw;
                box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="padding:18px 24px;border-bottom:1px solid #f0e8f4;
                    display:flex;align-items:center;justify-content:space-between;">
            <h3 style="font-family:'DM Sans',sans-serif;font-size:15px;font-weight:600;
                       color:#1a0a1e;margin:0;">Add Storage Location</h3>
            <button onclick="document.getElementById('modal-add-location').style.display='none'"
                    style="background:none;border:none;cursor:pointer;font-size:20px;
                           color:#9070a0;line-height:1;">&times;</button>
        </div>
        <form method="POST" action="{{ route('inventory.settings.locations.store') }}"
              style="padding:20px 24px;">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                <div>
                    <label class="inv-label">Location Name *</label>
                    <input type="text" name="name" required
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;
                                  border-radius:6px;font-size:13px;font-family:'DM Sans',sans-serif;
                                  box-sizing:border-box;">
                </div>
                <div>
                    <label class="inv-label">Code (optional)</label>
                    <input type="text" name="code" maxlength="20"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;
                                  border-radius:6px;font-size:13px;font-family:'DM Sans',sans-serif;
                                  box-sizing:border-box;font-family:'DM Mono',monospace;"
                           placeholder="e.g. OP-1">
                </div>
            </div>
            <div style="margin-bottom:14px;">
                <label class="inv-label">Type *</label>
                <select name="type" required
                        style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;
                               border-radius:6px;font-size:13px;font-family:'DM Sans',sans-serif;
                               background:#fff;">
                    <option value="main_store">🏪 Main Store</option>
                    <option value="operatory">🦷 Operatory</option>
                    <option value="sterilization">🧪 Sterilization</option>
                    <option value="lab">🔬 Lab</option>
                    <option value="implant_drawer">🗂️ Implant Drawer</option>
                    <option value="storage">📦 Storage</option>
                    <option value="other">📍 Other</option>
                </select>
            </div>
            <div style="margin-bottom:18px;">
                <label class="inv-label">Description</label>
                <input type="text" name="description" maxlength="255"
                       style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;
                              border-radius:6px;font-size:13px;font-family:'DM Sans',sans-serif;
                              box-sizing:border-box;">
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;">
                <button type="button"
                        onclick="document.getElementById('modal-add-location').style.display='none'"
                        style="background:#f5f0f8;border:none;border-radius:6px;padding:8px 18px;
                               font-size:13px;font-family:'DM Sans',sans-serif;cursor:pointer;
                               color:#6a0f70;">
                    Cancel
                </button>
                <button type="submit"
                        style="background:#6a0f70;color:#fff;border:none;border-radius:6px;
                               padding:8px 18px;font-size:13px;font-family:'DM Sans',sans-serif;
                               font-weight:500;cursor:pointer;">
                    Add Location
                </button>
            </div>
        </form>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════
     MODAL — Edit Location
══════════════════════════════════════════════════════════ --}}
<div id="modal-edit-location"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);
            z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:10px;width:440px;max-width:94vw;
                box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="padding:18px 24px;border-bottom:1px solid #f0e8f4;
                    display:flex;align-items:center;justify-content:space-between;">
            <h3 style="font-family:'DM Sans',sans-serif;font-size:15px;font-weight:600;
                       color:#1a0a1e;margin:0;">Edit Location</h3>
            <button onclick="document.getElementById('modal-edit-location').style.display='none'"
                    style="background:none;border:none;cursor:pointer;font-size:20px;
                           color:#9070a0;line-height:1;">&times;</button>
        </div>
        <form id="form-edit-location" method="POST" action="" style="padding:20px 24px;">
            @csrf @method('PUT')
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                <div>
                    <label class="inv-label">Location Name *</label>
                    <input type="text" id="edit-loc-name" name="name" required
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;
                                  border-radius:6px;font-size:13px;font-family:'DM Sans',sans-serif;
                                  box-sizing:border-box;">
                </div>
                <div>
                    <label class="inv-label">Code</label>
                    <input type="text" id="edit-loc-code" name="code" maxlength="20"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;
                                  border-radius:6px;font-size:13px;font-family:'DM Mono',monospace;
                                  box-sizing:border-box;">
                </div>
            </div>
            <div style="margin-bottom:14px;">
                <label class="inv-label">Type *</label>
                <select id="edit-loc-type" name="type" required
                        style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;
                               border-radius:6px;font-size:13px;font-family:'DM Sans',sans-serif;
                               background:#fff;">
                    <option value="main_store">🏪 Main Store</option>
                    <option value="operatory">🦷 Operatory</option>
                    <option value="sterilization">🧪 Sterilization</option>
                    <option value="lab">🔬 Lab</option>
                    <option value="implant_drawer">🗂️ Implant Drawer</option>
                    <option value="storage">📦 Storage</option>
                    <option value="other">📍 Other</option>
                </select>
            </div>
            <div style="margin-bottom:14px;">
                <label class="inv-label">Description</label>
                <input type="text" id="edit-loc-desc" name="description" maxlength="255"
                       style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;
                              border-radius:6px;font-size:13px;font-family:'DM Sans',sans-serif;
                              box-sizing:border-box;">
            </div>
            <div style="margin-bottom:18px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" id="edit-loc-active" name="is_active" value="1"
                           style="accent-color:#6a0f70;">
                    <span style="font-size:13px;font-family:'DM Sans',sans-serif;color:#1a0a1e;">Active</span>
                </label>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;">
                <button type="button"
                        onclick="document.getElementById('modal-edit-location').style.display='none'"
                        style="background:#f5f0f8;border:none;border-radius:6px;padding:8px 18px;
                               font-size:13px;font-family:'DM Sans',sans-serif;cursor:pointer;
                               color:#6a0f70;">
                    Cancel
                </button>
                <button type="submit"
                        style="background:#6a0f70;color:#fff;border:none;border-radius:6px;
                               padding:8px 18px;font-size:13px;font-family:'DM Sans',sans-serif;
                               font-weight:500;cursor:pointer;">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>


<style>
.inv-label {
    display: block;
    font-family: 'DM Sans', sans-serif;
    font-size: 11px;
    font-weight: 600;
    color: #6a0f70;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 5px;
}
</style>

<script>
// ── Edit Category ──────────────────────────────────────────────
function openEditCategory(cat) {
    document.getElementById('edit-cat-name').value  = cat.name || '';
    document.getElementById('edit-cat-color').value = cat.color || '#6a0f70';
    document.getElementById('edit-cat-desc').value  = cat.description || '';
    document.getElementById('edit-cat-active').checked = !!cat.is_active;

    // Build route: /inventory/settings/categories/{id}
    document.getElementById('form-edit-category').action =
        '/inventory/settings/categories/' + cat.id;

    document.getElementById('modal-edit-category').style.display = 'flex';
}

// ── Edit Location ──────────────────────────────────────────────
function openEditLocation(loc) {
    document.getElementById('edit-loc-name').value   = loc.name || '';
    document.getElementById('edit-loc-code').value   = loc.code || '';
    document.getElementById('edit-loc-desc').value   = loc.description || '';
    document.getElementById('edit-loc-active').checked = !!loc.is_active;

    const typeSelect = document.getElementById('edit-loc-type');
    for (let opt of typeSelect.options) {
        opt.selected = (opt.value === loc.type);
    }

    document.getElementById('form-edit-location').action =
        '/inventory/settings/locations/' + loc.id;

    document.getElementById('modal-edit-location').style.display = 'flex';
}

// ── Sub-type edit ──────────────────────────────────────────────
function openEditSubType(id, name, catId, isActive) {
    document.getElementById('est-name').value    = name;
    document.getElementById('est-active').checked = isActive;
    document.getElementById('est-cat').value     = catId;
    document.getElementById('form-edit-subtype').action = `/inventory/settings/sub-types/${id}`;
    document.getElementById('modal-edit-subtype').style.display = 'flex';
}

// Close modals on backdrop click
['modal-add-category','modal-edit-category','modal-add-location','modal-edit-location',
 'modal-add-subtype','modal-edit-subtype']
    .forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('click', e => {
            if (e.target === el) el.style.display = 'none';
        });
    });
</script>

{{-- ── Modal: Add Sub-type ── --}}
<div id="modal-add-subtype"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);
            z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:8px;width:100%;max-width:400px;
                box-shadow:0 16px 48px rgba(0,0,0,0.18);margin:20px;">
        <div style="padding:16px 22px;border-bottom:1px solid #e8f7ee;background:#f5fbf7;
                    display:flex;justify-content:space-between;align-items:center;border-radius:8px 8px 0 0;">
            <h3 style="font-family:'DM Sans',sans-serif;font-size:16px;font-weight:600;color:#1a7a45;margin:0;">
                Add Sub-type
            </h3>
            <button onclick="document.getElementById('modal-add-subtype').style.display='none'"
                    style="background:none;border:none;cursor:pointer;font-size:22px;color:#5a9a6a;">&times;</button>
        </div>
        <form method="POST" action="{{ route('inventory.settings.sub-types.store') }}"
              style="padding:20px 22px;">
            @csrf
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#555;
                              text-transform:uppercase;letter-spacing:.04em;margin-bottom:5px;">Category *</label>
                <select name="category_id" required
                        style="width:100%;padding:8px 10px;border:1px solid #c0d8c8;border-radius:5px;
                               font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;">
                    <option value="">— Select category —</option>
                    @foreach(\App\Models\Inventory\InventoryCategory::orderBy('name')->get() as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="margin-bottom:18px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#555;
                              text-transform:uppercase;letter-spacing:.04em;margin-bottom:5px;">Sub-type Name *</label>
                <input type="text" name="name" required placeholder="e.g. Composite, GIC, Amalgam"
                       style="width:100%;padding:8px 10px;border:1px solid #c0d8c8;border-radius:5px;
                              font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;">
            </div>
            <div style="display:flex;gap:8px;">
                <button type="button" onclick="document.getElementById('modal-add-subtype').style.display='none'"
                        style="flex:1;padding:9px;border:1px solid #c0d8c8;border-radius:5px;font-size:13px;
                               font-family:'DM Sans',sans-serif;background:#fff;color:#1a7a45;cursor:pointer;">Cancel</button>
                <button type="submit"
                        style="flex:2;padding:9px;background:#1a7a45;border:none;border-radius:5px;
                               font-size:13px;font-family:'DM Sans',sans-serif;color:#fff;cursor:pointer;">Add Sub-type</button>
            </div>
        </form>
    </div>
</div>

{{-- ── Modal: Edit Sub-type ── --}}
<div id="modal-edit-subtype"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);
            z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:8px;width:100%;max-width:400px;
                box-shadow:0 16px 48px rgba(0,0,0,0.18);margin:20px;">
        <div style="padding:16px 22px;border-bottom:1px solid #e8f7ee;background:#f5fbf7;
                    display:flex;justify-content:space-between;align-items:center;border-radius:8px 8px 0 0;">
            <h3 style="font-family:'DM Sans',sans-serif;font-size:16px;font-weight:600;color:#1a7a45;margin:0;">
                Edit Sub-type
            </h3>
            <button onclick="document.getElementById('modal-edit-subtype').style.display='none'"
                    style="background:none;border:none;cursor:pointer;font-size:22px;color:#5a9a6a;">&times;</button>
        </div>
        <form id="form-edit-subtype" method="POST" action=""
              style="padding:20px 22px;">
            @csrf @method('PUT')
            <input type="hidden" name="category_id" id="est-cat">
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#555;
                              text-transform:uppercase;letter-spacing:.04em;margin-bottom:5px;">Sub-type Name *</label>
                <input type="text" name="name" id="est-name" required
                       style="width:100%;padding:8px 10px;border:1px solid #c0d8c8;border-radius:5px;
                              font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;">
            </div>
            <div style="margin-bottom:18px;display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="is_active" id="est-active" value="1"
                       style="width:14px;height:14px;accent-color:#1a7a45;">
                <label for="est-active" style="font-size:13px;font-family:'DM Sans',sans-serif;cursor:pointer;">Active</label>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="button" onclick="document.getElementById('modal-edit-subtype').style.display='none'"
                        style="flex:1;padding:9px;border:1px solid #c0d8c8;border-radius:5px;font-size:13px;
                               font-family:'DM Sans',sans-serif;background:#fff;color:#1a7a45;cursor:pointer;">Cancel</button>
                <button type="submit"
                        style="flex:2;padding:9px;background:#1a7a45;border:none;border-radius:5px;
                               font-size:13px;font-family:'DM Sans',sans-serif;color:#fff;cursor:pointer;">Update</button>
            </div>
        </form>
    </div>
</div>

@endsection
