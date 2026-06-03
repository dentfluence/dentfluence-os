{{--
|==========================================================================
| Implant Registry — Catalog + Placement Traceability
| Two-tab view: Catalog (components) | Placements (per-patient history)
|==========================================================================
--}}
@extends('layouts.app')
@section('title', 'Implant Registry')

@section('content')
@include('inventory.partials.subnav')

{{-- ── Page header ── --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
    <div>
        <h1 style="font-family:'Cormorant Garamond',serif;font-size:26px;font-weight:600;
                   color:#1a0a1e;margin:0 0 2px;">Implant Registry</h1>
        <p style="font-family:'DM Sans',sans-serif;font-size:13px;color:#7a6884;margin:0;">
            Catalog of implant components + per-patient placement traceability
        </p>
    </div>
    <div style="display:flex;gap:8px;">
        <button onclick="document.getElementById('modal-add-catalog').style.display='flex'"
                style="background:#6a0f70;color:#fff;border:none;border-radius:6px;
                       padding:8px 16px;font-size:13px;font-family:'DM Sans',sans-serif;
                       font-weight:500;cursor:pointer;display:flex;align-items:center;gap:6px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add to Catalog
        </button>
        <button onclick="document.getElementById('modal-add-placement').style.display='flex'"
                style="background:#1a7a45;color:#fff;border:none;border-radius:6px;
                       padding:8px 16px;font-size:13px;font-family:'DM Sans',sans-serif;
                       font-weight:500;cursor:pointer;display:flex;align-items:center;gap:6px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Record Placement
        </button>
    </div>
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
    @foreach($errors->all() as $err) <div>⚠ {{ $err }}</div> @endforeach
</div>
@endif

{{-- ── Tab strip ── --}}
@php $activeTab = $tab ?? 'catalog'; @endphp
<div style="display:flex;gap:2px;border-bottom:2px solid #f0e8f4;margin-bottom:20px;">
    <a href="?tab=catalog"
       style="padding:8px 18px;font-family:'DM Sans',sans-serif;font-size:13px;
              font-weight:{{ $activeTab==='catalog' ? '600' : '400' }};
              color:{{ $activeTab==='catalog' ? '#6a0f70' : '#7a6884' }};
              border-bottom:2px solid {{ $activeTab==='catalog' ? '#6a0f70' : 'transparent' }};
              text-decoration:none;margin-bottom:-2px;">
        Component Catalog
        <span style="background:#f0e8f4;color:#6a0f70;font-size:10px;padding:1px 7px;
                     border-radius:10px;margin-left:6px;">{{ $catalog->total() }}</span>
    </a>
    <a href="?tab=placements"
       style="padding:8px 18px;font-family:'DM Sans',sans-serif;font-size:13px;
              font-weight:{{ $activeTab==='placements' ? '600' : '400' }};
              color:{{ $activeTab==='placements' ? '#6a0f70' : '#7a6884' }};
              border-bottom:2px solid {{ $activeTab==='placements' ? '#6a0f70' : 'transparent' }};
              text-decoration:none;margin-bottom:-2px;">
        Placement History
        <span style="background:#e8f7ee;color:#1a7a45;font-size:10px;padding:1px 7px;
                     border-radius:10px;margin-left:6px;">{{ $placements->total() }}</span>
    </a>
</div>


{{-- ═══════════════════════════════════════════════════════════
     TAB: CATALOG
════════════════════════════════════════════════════════════ --}}
@if($activeTab === 'catalog')

@php
$typeColors = [
    'fixture'          => ['bg'=>'#e6f0fb','fg'=>'#1a5ea8','label'=>'Fixture'],
    'abutment'         => ['bg'=>'#f0e8f4','fg'=>'#6a0f70','label'=>'Abutment'],
    'healing_abutment' => ['bg'=>'#fff4e0','fg'=>'#a05c00','label'=>'Healing Ab.'],
    'analogue'         => ['bg'=>'#e8f7ee','fg'=>'#1a7a45','label'=>'Analogue'],
    'scan_body'        => ['bg'=>'#fef3e0','fg'=>'#b06a00','label'=>'Scan Body'],
    'coping'           => ['bg'=>'#fdeaea','fg'=>'#b52020','label'=>'Coping'],
    'graft'            => ['bg'=>'#e8f4fb','fg'=>'#1a5ea8','label'=>'Bone Graft'],
    'other'            => ['bg'=>'#f4f4f4','fg'=>'#666','label'=>'Other'],
];
@endphp

<div style="background:#fff;border:1px solid rgba(185,92,183,0.12);border-radius:8px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="background:#faf5fb;border-bottom:1px solid rgba(185,92,183,0.10);">
                <th style="padding:10px 18px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;width:60px;">Photo</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Brand / System</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Type</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Product Code</th>
                <th style="padding:10px 14px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Dimensions</th>
                <th style="padding:10px 14px;text-align:right;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Price</th>
                <th style="padding:10px 14px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Placements</th>
                <th style="padding:10px 18px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($catalog as $item)
            @php $tc = $typeColors[$item->component_type] ?? $typeColors['other']; @endphp
            <tr style="border-bottom:1px solid rgba(185,92,183,0.05);transition:background 120ms;"
                onmouseover="this.style.background='#faf5fb'" onmouseout="this.style.background=''">
                <td style="padding:10px 18px;">
                    @if($item->photo_path)
                    <img src="{{ asset('storage/' . $item->photo_path) }}"
                         alt="{{ $item->brand }}"
                         style="width:40px;height:40px;object-fit:contain;border-radius:4px;
                                border:1px solid #f0e8f4;cursor:pointer;"
                         onclick="showPhoto('{{ asset('storage/' . $item->photo_path) }}', '{{ addslashes($item->getFullName()) }}')">
                    @else
                    <div style="width:40px;height:40px;border-radius:4px;border:1px dashed #d8c8e4;
                                display:flex;align-items:center;justify-content:center;
                                font-size:16px;color:#d8c8e4;">
                        🦷
                    </div>
                    @endif
                </td>
                <td style="padding:10px 14px;">
                    <div style="font-weight:600;color:#1a0a1e;font-family:'DM Sans',sans-serif;">{{ $item->brand }}</div>
                    @if($item->system)<div style="font-size:11px;color:#9070a0;">{{ $item->system }}</div>@endif
                </td>
                <td style="padding:10px 14px;">
                    <span style="padding:2px 8px;border-radius:12px;font-size:11.5px;font-weight:500;
                                 background:{{ $tc['bg'] }};color:{{ $tc['fg'] }};">
                        {{ $tc['label'] }}
                    </span>
                </td>
                <td style="padding:10px 14px;font-family:'DM Mono',monospace;font-size:12px;color:#4e2060;">
                    {{ $item->product_code ?? '—' }}
                    @if($item->description)<div style="font-size:10.5px;color:#9070a0;font-family:'DM Sans',sans-serif;">{{ Str::limit($item->description, 40) }}</div>@endif
                </td>
                <td style="padding:10px 14px;text-align:center;font-size:12px;color:#2e1040;">
                    @php
                        $dims = [];
                        if ($item->diameter_mm) $dims[] = 'ø' . $item->diameter_mm;
                        if ($item->length_mm)   $dims[] = $item->length_mm . 'mm';
                    @endphp
                    @if(count($dims))
                        <span style="font-family:'DM Mono',monospace;font-size:11.5px;">{{ implode(' × ', $dims) }}</span>
                    @else
                        <span style="color:#9a85aa;">—</span>
                    @endif
                </td>
                <td style="padding:10px 14px;text-align:right;font-family:'DM Mono',monospace;font-size:13px;color:#1e0a2c;">
                    @if($item->unit_price)
                        ₹{{ number_format($item->unit_price, 0) }}
                    @else
                        <span style="color:#9a85aa;">—</span>
                    @endif
                </td>
                <td style="padding:10px 14px;text-align:center;">
                    <span style="background:#f0e8f4;color:#6a0f70;padding:2px 8px;border-radius:10px;
                                 font-size:11px;font-weight:500;font-family:'DM Mono',monospace;">
                        {{ $item->placements_count }}
                    </span>
                </td>
                <td style="padding:10px 18px;text-align:center;">
                    <button onclick='openEditCatalog({{ $item->toJson() }})'
                            style="background:#f5f0f8;border:1px solid rgba(106,15,112,0.12);
                                   border-radius:4px;padding:5px 12px;font-size:12px;
                                   font-family:'DM Sans',sans-serif;color:#6a0f70;cursor:pointer;">
                        Edit
                    </button>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" style="padding:56px;text-align:center;font-family:'DM Sans',sans-serif;font-size:13px;color:#9070a0;">
                    <div style="font-size:32px;margin-bottom:10px;">🦷</div>
                    No implant components in catalog yet.<br>
                    <span style="font-size:12px;">Click "Add to Catalog" to register your first component.</span>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @if($catalog->hasPages())
    <div style="padding:14px 18px;border-top:1px solid rgba(185,92,183,0.07);">{{ $catalog->links() }}</div>
    @endif
</div>

@endif {{-- end catalog tab --}}


{{-- ═══════════════════════════════════════════════════════════
     TAB: PLACEMENTS
════════════════════════════════════════════════════════════ --}}
@if($activeTab === 'placements')

<div style="background:#fff;border:1px solid rgba(185,92,183,0.12);border-radius:8px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="background:#faf5fb;border-bottom:1px solid rgba(185,92,183,0.10);">
                <th style="padding:10px 18px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Patient</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Implant</th>
                <th style="padding:10px 14px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Tooth</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Lot / Serial</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Surgeon</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Date</th>
                <th style="padding:10px 14px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Label</th>
                <th style="padding:10px 14px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Status</th>
                <th style="padding:10px 18px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($placements as $pl)
            @php
                $statusColor = $pl->getStatusColor();
                $statusBgs   = ['placed'=>'#e6f0fb','osseointegrating'=>'#fff4e0','loaded'=>'#e8f7ee','failed'=>'#fdeaea','explanted'=>'#f4f4f4'];
                $statusBg    = $statusBgs[$pl->status] ?? '#f4f4f4';
            @endphp
            <tr style="border-bottom:1px solid rgba(185,92,183,0.05);transition:background 120ms;"
                onmouseover="this.style.background='#faf5fb'" onmouseout="this.style.background=''">
                <td style="padding:11px 18px;">
                    @if($pl->patient)
                    <a href="{{ route('patients.show', $pl->patient) }}"
                       style="font-weight:500;color:#6a0f70;text-decoration:none;">
                        {{ $pl->patient->first_name }} {{ $pl->patient->last_name }}
                    </a>
                    <div style="font-size:11px;color:#9a85aa;">{{ $pl->patient->patient_id ?? '' }}</div>
                    @else <span style="color:#9a85aa;">—</span> @endif
                </td>
                <td style="padding:11px 14px;">
                    <div style="font-size:13px;font-weight:500;color:#1e0a2c;">
                        {{ $pl->getImplantName() }}
                    </div>
                    @if($pl->catalogItem?->product_code)
                    <div style="font-size:11px;color:#9a85aa;font-family:'DM Mono',monospace;">
                        {{ $pl->catalogItem->product_code }}
                    </div>
                    @elseif($pl->implant_code_freetext)
                    <div style="font-size:11px;color:#9a85aa;font-family:'DM Mono',monospace;">
                        {{ $pl->implant_code_freetext }}
                    </div>
                    @endif
                </td>
                <td style="padding:11px 14px;text-align:center;">
                    @if($pl->tooth_position)
                    <span style="background:#f0e8f4;color:#6a0f70;padding:3px 8px;
                                 border-radius:4px;font-family:'DM Mono',monospace;
                                 font-size:12px;font-weight:600;">
                        {{ $pl->tooth_position }}
                    </span>
                    @else <span style="color:#9a85aa;">—</span> @endif
                </td>
                <td style="padding:11px 14px;font-family:'DM Mono',monospace;font-size:11.5px;">
                    @if($pl->lot_number)
                    <div><span style="color:#9a85aa;font-size:10px;">LOT:</span> {{ $pl->lot_number }}</div>
                    @endif
                    @if($pl->serial_number)
                    <div><span style="color:#9a85aa;font-size:10px;">S/N:</span> {{ $pl->serial_number }}</div>
                    @endif
                    @if(!$pl->lot_number && !$pl->serial_number)<span style="color:#9a85aa;">—</span>@endif
                </td>
                <td style="padding:11px 14px;font-size:12.5px;color:#2e1040;">
                    {{ $pl->surgeon?->name ?? '—' }}
                </td>
                <td style="padding:11px 14px;font-size:12.5px;color:#2e1040;">
                    {{ $pl->surgery_date?->format('d M Y') ?? '—' }}
                </td>
                <td style="padding:11px 14px;text-align:center;">
                    @if($pl->label_photo_path)
                    <img src="{{ asset('storage/' . $pl->label_photo_path) }}"
                         alt="Label"
                         style="width:36px;height:36px;object-fit:cover;border-radius:4px;
                                border:1px solid #f0e8f4;cursor:pointer;"
                         onclick="showPhoto('{{ asset('storage/' . $pl->label_photo_path) }}', 'Implant Label')">
                    @else
                    <span style="font-size:18px;color:#d8c8e4;" title="No label photo">📷</span>
                    @endif
                </td>
                <td style="padding:11px 14px;text-align:center;">
                    <span style="padding:2px 9px;border-radius:12px;font-size:11.5px;font-weight:500;
                                 background:{{ $statusBg }};color:{{ $statusColor }};">
                        {{ $pl->getStatusLabel() }}
                    </span>
                </td>
                <td style="padding:11px 18px;text-align:center;">
                    <button onclick='openEditPlacement({{ $pl->toJson() }})'
                            style="background:#f5f0f8;border:1px solid rgba(106,15,112,0.12);
                                   border-radius:4px;padding:5px 12px;font-size:12px;
                                   font-family:'DM Sans',sans-serif;color:#6a0f70;cursor:pointer;">
                        Edit
                    </button>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" style="padding:56px;text-align:center;font-family:'DM Sans',sans-serif;font-size:13px;color:#9070a0;">
                    <div style="font-size:32px;margin-bottom:10px;">🗂️</div>
                    No implant placements recorded yet.<br>
                    <span style="font-size:12px;">Click "Record Placement" to log the first case.</span>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @if($placements->hasPages())
    <div style="padding:14px 18px;border-top:1px solid rgba(185,92,183,0.07);">{{ $placements->links() }}</div>
    @endif
</div>

@endif {{-- end placements tab --}}


{{-- ═══════════════════════════════════════════════════════════
     MODAL — Add Catalog Item
════════════════════════════════════════════════════════════ --}}
<div id="modal-add-catalog"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);
            z-index:1000;align-items:flex-start;justify-content:center;
            padding:24px 16px;overflow-y:auto;">
    <div style="background:#fff;border-radius:8px;width:100%;max-width:640px;
                box-shadow:0 20px 60px rgba(0,0,0,0.2);">

        <div style="padding:18px 24px;border-bottom:1px solid #f0e8f4;
                    display:flex;align-items:center;justify-content:space-between;
                    background:#faf5fb;">
            <h3 style="font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:600;
                       color:#1a0a1e;margin:0;">Add Implant Component</h3>
            <button onclick="document.getElementById('modal-add-catalog').style.display='none'"
                    style="background:none;border:none;cursor:pointer;font-size:22px;color:#9070a0;">&times;</button>
        </div>

        <form method="POST" action="{{ route('inventory.implants.catalog.store') }}"
              enctype="multipart/form-data" style="padding:22px 24px;">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div>
                    <label class="imp-label">Brand *</label>
                    <input type="text" name="brand" required placeholder="e.g. Nobel Biocare"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;">
                </div>
                <div>
                    <label class="imp-label">System / Product Line</label>
                    <input type="text" name="system" placeholder="e.g. NobelActive, BLX"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;">
                </div>
                <div>
                    <label class="imp-label">Component Type *</label>
                    <select name="component_type" required
                            style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;">
                        <option value="fixture">Fixture</option>
                        <option value="abutment">Abutment</option>
                        <option value="healing_abutment">Healing Abutment</option>
                        <option value="analogue">Analogue</option>
                        <option value="scan_body">Scan Body</option>
                        <option value="coping">Coping</option>
                        <option value="graft">Bone Graft</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="imp-label">Product Code</label>
                    <input type="text" name="product_code" placeholder="Manufacturer's code"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;">
                </div>
                <div>
                    <label class="imp-label">Diameter (mm)</label>
                    <input type="text" name="diameter_mm" placeholder="e.g. 4.1"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;">
                </div>
                <div>
                    <label class="imp-label">Length (mm)</label>
                    <input type="text" name="length_mm" placeholder="e.g. 10"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;">
                </div>
                <div>
                    <label class="imp-label">Platform / Connection</label>
                    <input type="text" name="platform" placeholder="e.g. RP, NP, Internal Hex"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;">
                </div>
                <div>
                    <label class="imp-label">Material</label>
                    <input type="text" name="material" placeholder="e.g. Ti Grade IV, Zirconia"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;">
                </div>
                <div>
                    <label class="imp-label">Unit Price (₹)</label>
                    <input type="number" name="unit_price" min="0" step="0.01"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;">
                </div>
                <div>
                    <label class="imp-label">Product Photo</label>
                    <input type="file" name="photo" accept="image/*"
                           style="width:100%;padding:6px 0;font-size:12px;font-family:'DM Sans',sans-serif;">
                </div>
                <div style="grid-column:1/-1;">
                    <label class="imp-label">Description</label>
                    <input type="text" name="description" maxlength="255"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;">
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:18px;
                        padding-top:14px;border-top:1px solid #f0e8f4;">
                <button type="button"
                        onclick="document.getElementById('modal-add-catalog').style.display='none'"
                        style="background:#f5f0f8;border:none;border-radius:6px;padding:9px 18px;
                               font-size:13px;font-family:'DM Sans',sans-serif;cursor:pointer;color:#6a0f70;">
                    Cancel
                </button>
                <button type="submit"
                        style="background:#6a0f70;color:#fff;border:none;border-radius:6px;
                               padding:9px 18px;font-size:13px;font-family:'DM Sans',sans-serif;
                               font-weight:500;cursor:pointer;">
                    Add Component
                </button>
            </div>
        </form>
    </div>
</div>


{{-- ═══════════════════════════════════════════════════════════
     MODAL — Edit Catalog Item
════════════════════════════════════════════════════════════ --}}
<div id="modal-edit-catalog"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);
            z-index:1000;align-items:flex-start;justify-content:center;
            padding:24px 16px;overflow-y:auto;">
    <div style="background:#fff;border-radius:8px;width:100%;max-width:640px;
                box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="padding:18px 24px;border-bottom:1px solid #f0e8f4;
                    display:flex;align-items:center;justify-content:space-between;
                    background:#faf5fb;">
            <h3 style="font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:600;
                       color:#1a0a1e;margin:0;">Edit Component</h3>
            <button onclick="document.getElementById('modal-edit-catalog').style.display='none'"
                    style="background:none;border:none;cursor:pointer;font-size:22px;color:#9070a0;">&times;</button>
        </div>
        <form id="form-edit-catalog" method="POST" action=""
              enctype="multipart/form-data" style="padding:22px 24px;">
            @csrf @method('PUT')
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div>
                    <label class="imp-label">Brand *</label>
                    <input type="text" id="ec-brand" name="brand" required
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;">
                </div>
                <div>
                    <label class="imp-label">System</label>
                    <input type="text" id="ec-system" name="system"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;">
                </div>
                <div>
                    <label class="imp-label">Component Type *</label>
                    <select id="ec-type" name="component_type" required
                            style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;">
                        <option value="fixture">Fixture</option>
                        <option value="abutment">Abutment</option>
                        <option value="healing_abutment">Healing Abutment</option>
                        <option value="analogue">Analogue</option>
                        <option value="scan_body">Scan Body</option>
                        <option value="coping">Coping</option>
                        <option value="graft">Bone Graft</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="imp-label">Product Code</label>
                    <input type="text" id="ec-code" name="product_code"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;">
                </div>
                <div>
                    <label class="imp-label">Diameter (mm)</label>
                    <input type="text" id="ec-diam" name="diameter_mm"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;">
                </div>
                <div>
                    <label class="imp-label">Length (mm)</label>
                    <input type="text" id="ec-len" name="length_mm"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;">
                </div>
                <div>
                    <label class="imp-label">Platform</label>
                    <input type="text" id="ec-platform" name="platform"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;">
                </div>
                <div>
                    <label class="imp-label">Material</label>
                    <input type="text" id="ec-material" name="material"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;">
                </div>
                <div>
                    <label class="imp-label">Unit Price (₹)</label>
                    <input type="number" id="ec-price" name="unit_price" min="0" step="0.01"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;">
                </div>
                <div>
                    <label class="imp-label">Replace Photo</label>
                    <input type="file" name="photo" accept="image/*"
                           style="width:100%;padding:6px 0;font-size:12px;">
                </div>
                <div style="grid-column:1/-1;">
                    <label class="imp-label">Description</label>
                    <input type="text" id="ec-desc" name="description" maxlength="255"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;">
                </div>
                <div style="grid-column:1/-1;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" id="ec-active" name="is_active" value="1"
                               style="accent-color:#6a0f70;">
                        <span style="font-size:13px;font-family:'DM Sans',sans-serif;color:#1a0a1e;">Active</span>
                    </label>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:18px;
                        padding-top:14px;border-top:1px solid #f0e8f4;">
                <button type="button"
                        onclick="document.getElementById('modal-edit-catalog').style.display='none'"
                        style="background:#f5f0f8;border:none;border-radius:6px;padding:9px 18px;
                               font-size:13px;font-family:'DM Sans',sans-serif;cursor:pointer;color:#6a0f70;">
                    Cancel
                </button>
                <button type="submit"
                        style="background:#6a0f70;color:#fff;border:none;border-radius:6px;
                               padding:9px 18px;font-size:13px;font-family:'DM Sans',sans-serif;
                               font-weight:500;cursor:pointer;">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>


{{-- ═══════════════════════════════════════════════════════════
     MODAL — Record Placement
════════════════════════════════════════════════════════════ --}}
<div id="modal-add-placement"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);
            z-index:1000;align-items:flex-start;justify-content:center;
            padding:24px 16px;overflow-y:auto;">
    <div style="background:#fff;border-radius:8px;width:100%;max-width:660px;
                box-shadow:0 20px 60px rgba(0,0,0,0.2);">

        <div style="padding:18px 24px;border-bottom:1px solid rgba(26,122,69,0.12);
                    display:flex;align-items:center;justify-content:space-between;
                    background:#f0faf4;">
            <div>
                <h3 style="font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:600;
                           color:#0d3d22;margin:0;">Record Implant Placement</h3>
                <p style="font-family:'DM Sans',sans-serif;font-size:12px;color:#5a8a6a;margin:4px 0 0;">
                    Attach to a patient — upload label photo for traceability
                </p>
            </div>
            <button onclick="document.getElementById('modal-add-placement').style.display='none'"
                    style="background:none;border:none;cursor:pointer;font-size:22px;color:#5a8a6a;">&times;</button>
        </div>

        <form method="POST" action="{{ route('inventory.implants.placements.store') }}"
              enctype="multipart/form-data" style="padding:22px 24px;">
            @csrf

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">

                {{-- Patient picker with live search --}}
                <div style="grid-column:1/-1;">
                    <label class="imp-label">
                        Patient *
                        <span style="font-weight:400;color:#9a85aa;font-size:11px;">
                            ({{ $patients->count() }} patients)
                        </span>
                    </label>
                    {{-- Search box ── filters the select below in real-time --}}
                    <input type="text" id="patient-search"
                           placeholder="🔍  Type name or phone to filter…"
                           oninput="filterPatients(this.value)"
                           autocomplete="off"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px 5px 0 0;
                                  font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;
                                  border-bottom:none;background:#faf8fc;">
                    <select name="patient_id" id="patient-select" required size="5"
                            style="width:100%;padding:4px;border:1px solid #d8c8e4;border-radius:0 0 5px 5px;
                                   font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;
                                   box-sizing:border-box;min-height:100px;">
                        <option value="">— Select patient —</option>
                        @forelse($patients as $pt)
                        <option value="{{ $pt->id }}" data-search="{{ strtolower($pt->name . ' ' . $pt->phone) }}">
                            {{ $pt->name }}{{ $pt->phone ? ' · ' . $pt->phone : '' }}
                        </option>
                        @empty
                        <option value="" disabled>No patients found — please add patients in the Patient module first.</option>
                        @endforelse
                    </select>
                    <script>
                    function filterPatients(q) {
                        const sel = document.getElementById('patient-select');
                        const term = q.toLowerCase().trim();
                        let first = null;
                        Array.from(sel.options).forEach(opt => {
                            if (!opt.value) return; // keep the "select" placeholder
                            const match = !term || (opt.dataset.search || '').includes(term);
                            opt.style.display = match ? '' : 'none';
                            if (match && !first) first = opt;
                        });
                    }
                    </script>
                </div>

                {{-- Catalog item --}}
                <div style="grid-column:1/-1;">
                    <label class="imp-label">Implant Component (Catalog)</label>
                    <select name="implant_catalog_id"
                            style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;">
                        <option value="">— Select from catalog (or fill brand/code below) —</option>
                        @foreach(\App\Models\Inventory\ImplantCatalog::active()->orderBy('brand')->get() as $ci)
                        <option value="{{ $ci->id }}">{{ $ci->getFullName() }} {{ $ci->product_code ? '('.$ci->product_code.')' : '' }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Freetext fallback --}}
                <div>
                    <label class="imp-label">Brand (if not in catalog)</label>
                    <input type="text" name="implant_brand_freetext" placeholder="e.g. Straumann"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;">
                </div>
                <div>
                    <label class="imp-label">Product Code (freetext)</label>
                    <input type="text" name="implant_code_freetext" placeholder="e.g. 048.522S"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;">
                </div>

                <div>
                    <label class="imp-label">Lot Number</label>
                    <input type="text" name="lot_number"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;">
                </div>
                <div>
                    <label class="imp-label">Serial Number</label>
                    <input type="text" name="serial_number"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;">
                </div>
                <div>
                    <label class="imp-label">Tooth Position (FDI)</label>
                    <input type="text" name="tooth_position" placeholder="e.g. 16, 46"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;">
                </div>
                <div>
                    <label class="imp-label">Surgery Date *</label>
                    <input type="date" name="surgery_date" required value="{{ date('Y-m-d') }}"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;box-sizing:border-box;">
                </div>
                <div>
                    <label class="imp-label">Surgeon</label>
                    <select name="surgeon_id"
                            style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;">
                        <option value="">— Select surgeon —</option>
                        @foreach(\App\Models\User::orderBy('name')->get() as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="imp-label">Status</label>
                    <select name="status"
                            style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;">
                        <option value="placed">Placed</option>
                        <option value="osseointegrating">Osseointegrating</option>
                        <option value="loaded">Loaded</option>
                        <option value="failed">Failed</option>
                        <option value="explanted">Explanted</option>
                    </select>
                </div>

                {{-- Label / QR photo upload --}}
                <div style="grid-column:1/-1;">
                    <label class="imp-label">Implant Label / QR Code Photo</label>
                    <div style="border:2px dashed #d8c8e4;border-radius:6px;padding:16px;
                                text-align:center;cursor:pointer;position:relative;"
                         onclick="document.getElementById('label-photo-input').click()">
                        <input type="file" id="label-photo-input" name="label_photo"
                               accept="image/*" style="display:none;"
                               onchange="previewLabelPhoto(this)">
                        <div id="label-photo-preview" style="margin-bottom:8px;display:none;">
                            <img id="label-photo-img" src="" alt="Preview"
                                 style="max-width:120px;max-height:80px;border-radius:4px;border:1px solid #f0e8f4;">
                        </div>
                        <div style="font-size:28px;color:#d8c8e4;margin-bottom:6px;">📷</div>
                        <div style="font-size:13px;color:#9070a0;font-family:'DM Sans',sans-serif;">
                            Click to upload the label or QR scan from the implant packaging
                        </div>
                        <div style="font-size:11px;color:#b0a0c0;margin-top:3px;">JPG, PNG, WebP — max 4MB</div>
                    </div>
                </div>

                <div style="grid-column:1/-1;">
                    <label class="imp-label">Notes</label>
                    <textarea name="notes" rows="2"
                              style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;resize:vertical;"></textarea>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:18px;
                        padding-top:14px;border-top:1px solid #f0e8f4;">
                <button type="button"
                        onclick="document.getElementById('modal-add-placement').style.display='none'"
                        style="background:#f5f0f8;border:none;border-radius:6px;padding:9px 18px;
                               font-size:13px;font-family:'DM Sans',sans-serif;cursor:pointer;color:#6a0f70;">
                    Cancel
                </button>
                <button type="submit"
                        style="background:#1a7a45;color:#fff;border:none;border-radius:6px;
                               padding:9px 18px;font-size:13px;font-family:'DM Sans',sans-serif;
                               font-weight:500;cursor:pointer;">
                    Record Placement
                </button>
            </div>
        </form>
    </div>
</div>


{{-- ═══════════════════════════════════════════════════════════
     MODAL — Edit Placement Status
════════════════════════════════════════════════════════════ --}}
<div id="modal-edit-placement"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);
            z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:8px;width:500px;max-width:94vw;
                box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="padding:18px 24px;border-bottom:1px solid rgba(26,122,69,0.12);
                    display:flex;align-items:center;justify-content:space-between;background:#f0faf4;">
            <h3 style="font-family:'Cormorant Garamond',serif;font-size:19px;font-weight:600;
                       color:#0d3d22;margin:0;">Update Placement</h3>
            <button onclick="document.getElementById('modal-edit-placement').style.display='none'"
                    style="background:none;border:none;cursor:pointer;font-size:22px;color:#5a8a6a;">&times;</button>
        </div>
        <form id="form-edit-placement" method="POST" action="" enctype="multipart/form-data"
              style="padding:20px 24px;">
            @csrf @method('PUT')
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
                <div>
                    <label class="imp-label">Status</label>
                    <select id="ep-status" name="status"
                            style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;">
                        <option value="placed">Placed</option>
                        <option value="osseointegrating">Osseointegrating</option>
                        <option value="loaded">Loaded</option>
                        <option value="failed">Failed</option>
                        <option value="explanted">Explanted</option>
                    </select>
                </div>
                <div>
                    <label class="imp-label">Tooth Position</label>
                    <input type="text" id="ep-tooth" name="tooth_position"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;">
                </div>
                <div>
                    <label class="imp-label">Lot Number</label>
                    <input type="text" id="ep-lot" name="lot_number"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;">
                </div>
                <div>
                    <label class="imp-label">Serial Number</label>
                    <input type="text" id="ep-serial" name="serial_number"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Mono',monospace;box-sizing:border-box;">
                </div>
                <div style="grid-column:1/-1;">
                    <label class="imp-label">Upload / Replace Label Photo</label>
                    <input type="file" name="label_photo" accept="image/*"
                           style="font-size:12px;font-family:'DM Sans',sans-serif;">
                </div>
                <div style="grid-column:1/-1;">
                    <label class="imp-label">Notes</label>
                    <textarea id="ep-notes" name="notes" rows="2"
                              style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'DM Sans',sans-serif;box-sizing:border-box;resize:vertical;"></textarea>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;padding-top:12px;border-top:1px solid #f0e8f4;">
                <button type="button"
                        onclick="document.getElementById('modal-edit-placement').style.display='none'"
                        style="background:#f5f0f8;border:none;border-radius:6px;padding:9px 18px;
                               font-size:13px;font-family:'DM Sans',sans-serif;cursor:pointer;color:#6a0f70;">
                    Cancel
                </button>
                <button type="submit"
                        style="background:#1a7a45;color:#fff;border:none;border-radius:6px;
                               padding:9px 18px;font-size:13px;font-family:'DM Sans',sans-serif;
                               font-weight:500;cursor:pointer;">
                    Save Update
                </button>
            </div>
        </form>
    </div>
</div>


{{-- ── Full-screen photo viewer ── --}}
<div id="photo-viewer"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.92);z-index:2000;
            align-items:center;justify-content:center;flex-direction:column;"
     onclick="this.style.display='none'">
    <div style="font-size:12px;color:rgba(255,255,255,0.5);margin-bottom:12px;font-family:'DM Sans',sans-serif;"
         id="photo-viewer-title"></div>
    <img id="photo-viewer-img" src="" alt="" style="max-width:90vw;max-height:80vh;border-radius:4px;">
    <div style="margin-top:12px;font-size:11px;color:rgba(255,255,255,0.4);font-family:'DM Sans',sans-serif;">
        Click anywhere to close
    </div>
</div>


<style>
.imp-label {
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
// ── Backdrop closers ───────────────────────────────────────────
['modal-add-catalog','modal-edit-catalog','modal-add-placement','modal-edit-placement'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('click', e => { if (e.target === el) el.style.display = 'none'; });
});

// ── Edit Catalog Item ──────────────────────────────────────────
function openEditCatalog(item) {
    document.getElementById('ec-brand').value    = item.brand || '';
    document.getElementById('ec-system').value   = item.system || '';
    document.getElementById('ec-code').value     = item.product_code || '';
    document.getElementById('ec-diam').value     = item.diameter_mm || '';
    document.getElementById('ec-len').value      = item.length_mm || '';
    document.getElementById('ec-platform').value = item.platform || '';
    document.getElementById('ec-material').value = item.material || '';
    document.getElementById('ec-price').value    = item.unit_price || '';
    document.getElementById('ec-desc').value     = item.description || '';
    document.getElementById('ec-active').checked = !!item.is_active;

    const typeSelect = document.getElementById('ec-type');
    for (let opt of typeSelect.options) opt.selected = (opt.value === item.component_type);

    document.getElementById('form-edit-catalog').action =
        '/inventory/implants/catalog/' + item.id;
    document.getElementById('modal-edit-catalog').style.display = 'flex';
}

// ── Edit Placement ─────────────────────────────────────────────
function openEditPlacement(pl) {
    document.getElementById('ep-status').value = pl.status || 'placed';
    document.getElementById('ep-tooth').value  = pl.tooth_position || '';
    document.getElementById('ep-lot').value    = pl.lot_number || '';
    document.getElementById('ep-serial').value = pl.serial_number || '';
    document.getElementById('ep-notes').value  = pl.notes || '';

    document.getElementById('form-edit-placement').action =
        '/inventory/implants/placements/' + pl.id;
    document.getElementById('modal-edit-placement').style.display = 'flex';
}


// ── Photo preview for label upload ────────────────────────────
function previewLabelPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('label-photo-img').src = e.target.result;
            document.getElementById('label-photo-preview').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// ── Full-screen photo viewer ───────────────────────────────────
function showPhoto(src, title) {
    document.getElementById('photo-viewer-img').src = src;
    document.getElementById('photo-viewer-title').textContent = title || '';
    document.getElementById('photo-viewer').style.display = 'flex';
}
</script>

@endsection
