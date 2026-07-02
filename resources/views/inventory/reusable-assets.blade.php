{{--
|==========================================================================
| Reusable Assets — instrument lifecycle tracking
| Tracks usage count, sterilization history, maintenance schedule per asset
|==========================================================================
--}}
@extends('layouts.app')
@section('title', 'Reusable Assets')

@section('content')
@include('inventory.partials.subnav')

{{-- ── Page header ── --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
    <div>
        <h1 style="font-family:'Cormorant Garamond',serif;font-size:26px;font-weight:600;color:#1a0a1e;margin:0 0 2px;">
            Reusable Assets
        </h1>
        <p style="font-family:'Inter',sans-serif;font-size:13px;color:#7a6884;margin:0;">
            Track instruments, drills & equipment — usage cycles, sterilization & maintenance
        </p>
    </div>
    <button onclick="document.getElementById('modal-add-asset').style.display='flex'"
            style="background:#6a0f70;color:#fff;border:none;border-radius:6px;padding:9px 18px;
                   font-size:13px;font-family:'Inter',sans-serif;font-weight:500;cursor:pointer;
                   display:flex;align-items:center;gap:6px;">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Add Asset
    </button>
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
    @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
</div>
@endif

{{-- ── Status summary cards ── --}}
@php
$statusConfig = [
    'available'             => ['label'=>'Available',   'bg'=>'#e8f7ee','fg'=>'#1a7a45','border'=>'#a3d9b8'],
    'in_use'                => ['label'=>'In Use',      'bg'=>'#e6f0fb','fg'=>'#1a5ea8','border'=>'#a3c0e8'],
    'sterilization_pending' => ['label'=>'Needs Sterilization','bg'=>'#fff4e0','fg'=>'#a05c00','border'=>'#f5d89a'],
    'under_maintenance'     => ['label'=>'Maintenance', 'bg'=>'#f0e8f4','fg'=>'#6a0f70','border'=>'#d8b8e4'],
    'retired'               => ['label'=>'Retired',     'bg'=>'#f4f4f4','fg'=>'#666','border'=>'#ddd'],
];
@endphp
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:20px;">
    @foreach($statusConfig as $key => $cfg)
    <a href="{{ route('inventory.reusable-assets', array_merge(request()->query(), ['status' => $key === ($status ?? '') ? '' : $key])) }}"
       style="background:{{ ($status ?? '') === $key ? $cfg['bg'] : '#fff' }};
              border:1px solid {{ ($status ?? '') === $key ? $cfg['border'] : 'rgba(185,92,183,0.12)' }};
              border-radius:8px;padding:14px 16px;text-decoration:none;display:block;">
        <div style="font-family:'Inter',sans-serif;font-size:22px;font-weight:700;color:{{ $cfg['fg'] }};">
            {{ $statusCounts[$key] ?? 0 }}
        </div>
        <div style="font-family:'Inter',sans-serif;font-size:11px;color:#7a6884;margin-top:2px;">
            {{ $cfg['label'] }}
        </div>
    </a>
    @endforeach
</div>

{{-- ── Filter bar ── --}}
<form method="GET" style="display:flex;gap:10px;margin-bottom:16px;align-items:center;flex-wrap:wrap;">
    <input type="text" name="search" value="{{ $search ?? '' }}"
           placeholder="Search asset code, serial no., item name…"
           style="flex:1;min-width:220px;padding:8px 12px;border:1px solid #d8c8e4;border-radius:6px;
                  font-size:13px;font-family:'Inter',sans-serif;">
    <select name="location_id"
            style="padding:8px 10px;border:1px solid #d8c8e4;border-radius:6px;font-size:13px;
                   font-family:'Inter',sans-serif;background:#fff;">
        <option value="">All Locations</option>
        @foreach($locations as $loc)
        <option value="{{ $loc->id }}" {{ ($locId ?? '') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
        @endforeach
    </select>
    <select name="status"
            style="padding:8px 10px;border:1px solid #d8c8e4;border-radius:6px;font-size:13px;
                   font-family:'Inter',sans-serif;background:#fff;">
        <option value="">All Statuses</option>
        @foreach($statusConfig as $key => $cfg)
        <option value="{{ $key }}" {{ ($status ?? '') === $key ? 'selected' : '' }}>{{ $cfg['label'] }}</option>
        @endforeach
    </select>
    <button type="submit"
            style="padding:8px 16px;background:#6a0f70;color:#fff;border:none;border-radius:6px;
                   font-size:13px;font-family:'Inter',sans-serif;cursor:pointer;">Filter</button>
    @if($search || $status || $locId)
    <a href="{{ route('inventory.reusable-assets') }}"
       style="padding:8px 14px;background:#f5f0f8;color:#6a0f70;border-radius:6px;font-size:13px;
              font-family:'Inter',sans-serif;text-decoration:none;">Clear</a>
    @endif
</form>

{{-- ── Assets table ── --}}
<div style="background:#fff;border:1px solid rgba(185,92,183,0.12);border-radius:8px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-family:'Inter',sans-serif;font-size:13px;">
        <thead>
            <tr style="background:#faf8fc;border-bottom:1px solid rgba(185,92,183,0.12);">
                <th style="padding:11px 14px;text-align:left;font-size:11px;font-weight:600;color:#7a6884;text-transform:uppercase;letter-spacing:.04em;">Asset Code</th>
                <th style="padding:11px 14px;text-align:left;font-size:11px;font-weight:600;color:#7a6884;text-transform:uppercase;letter-spacing:.04em;">Item / Category</th>
                <th style="padding:11px 14px;text-align:left;font-size:11px;font-weight:600;color:#7a6884;text-transform:uppercase;letter-spacing:.04em;">Location</th>
                <th style="padding:11px 14px;text-align:left;font-size:11px;font-weight:600;color:#7a6884;text-transform:uppercase;letter-spacing:.04em;">Status</th>
                <th style="padding:11px 14px;text-align:left;font-size:11px;font-weight:600;color:#7a6884;text-transform:uppercase;letter-spacing:.04em;">Usage</th>
                <th style="padding:11px 14px;text-align:left;font-size:11px;font-weight:600;color:#7a6884;text-transform:uppercase;letter-spacing:.04em;">Last Sterilized</th>
                <th style="padding:11px 14px;text-align:left;font-size:11px;font-weight:600;color:#7a6884;text-transform:uppercase;letter-spacing:.04em;">Next Service</th>
                <th style="padding:11px 14px;text-align:center;font-size:11px;font-weight:600;color:#7a6884;text-transform:uppercase;letter-spacing:.04em;">Actions</th>
            </tr>
        </thead>
        <tbody>
        @forelse($assets as $asset)
        @php
            $cfg = $statusConfig[$asset->status] ?? ['label'=>$asset->status,'bg'=>'#f4f4f4','fg'=>'#555','border'=>'#ddd'];
            $usagePct = $asset->usage_percent;
            $usageBar = $usagePct >= 90 ? '#b52020' : ($usagePct >= 70 ? '#a05c00' : '#1a7a45');
            $nextSvcOverdue = $asset->next_maintenance_due && $asset->next_maintenance_due->isPast();
        @endphp
        <tr style="border-bottom:1px solid rgba(185,92,183,0.07);"
            onmouseover="this.style.background='#faf8fc'" onmouseout="this.style.background=''">
            <td style="padding:11px 14px;">
                <span style="font-weight:600;color:#1a0a1e;">{{ $asset->asset_code }}</span>
                @if($asset->serial_number)
                <div style="font-size:11px;color:#9a85aa;">S/N: {{ $asset->serial_number }}</div>
                @endif
            </td>
            <td style="padding:11px 14px;">
                <div style="font-weight:500;color:#2d1a35;">{{ $asset->item?->product_name ?? '—' }}</div>
                @if($asset->item?->category)
                <div style="font-size:11px;color:#9a85aa;">{{ $asset->item->category->name }}</div>
                @endif
            </td>
            <td style="padding:11px 14px;color:#5a4a6a;">{{ $asset->location?->name ?? '—' }}</td>
            <td style="padding:11px 14px;">
                <span style="background:{{ $cfg['bg'] }};color:{{ $cfg['fg'] }};border:1px solid {{ $cfg['border'] }};
                             border-radius:20px;padding:2px 10px;font-size:11px;font-weight:500;white-space:nowrap;">
                    {{ $cfg['label'] }}
                </span>
            </td>
            <td style="padding:11px 14px;">
                @if($asset->max_usage_count)
                <div style="font-size:12px;color:#5a4a6a;margin-bottom:4px;">
                    {{ $asset->current_usage_count }} / {{ $asset->max_usage_count }}
                </div>
                <div style="background:#f0e8f4;border-radius:4px;height:5px;width:80px;">
                    <div style="background:{{ $usageBar }};height:5px;border-radius:4px;width:{{ $usagePct }}%;"></div>
                </div>
                @else
                <span style="color:#9a85aa;font-size:12px;">{{ $asset->current_usage_count }} uses</span>
                @endif
            </td>
            <td style="padding:11px 14px;font-size:12px;color:#5a4a6a;">
                @if($asset->last_sterilized_at)
                    {{ $asset->last_sterilized_at->format('d M Y') }}
                    <div style="font-size:11px;color:#9a85aa;">{{ $asset->sterilization_count }}× total</div>
                @else
                    <span style="color:#9a85aa;">Never</span>
                @endif
            </td>
            <td style="padding:11px 14px;font-size:12px;">
                @if($asset->next_maintenance_due)
                    <span style="color:{{ $nextSvcOverdue ? '#b52020' : '#5a4a6a' }};font-weight:{{ $nextSvcOverdue ? '600' : '400' }};">
                        {{ $asset->next_maintenance_due->format('d M Y') }}
                    </span>
                    @if($nextSvcOverdue)
                    <div style="font-size:11px;color:#b52020;">Overdue</div>
                    @endif
                @else
                    <span style="color:#9a85aa;">—</span>
                @endif
            </td>
            <td style="padding:11px 14px;text-align:center;">
                <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap;">
                    {{-- Quick action buttons based on current status --}}
                    @if($asset->status === 'sterilization_pending')
                    <form method="POST" action="{{ route('inventory.reusable-assets.status', $asset) }}" style="display:inline;">
                        @csrf
                        <input type="hidden" name="action" value="sterilized">
                        <button type="submit" title="Mark Sterilized"
                                style="background:#e8f7ee;color:#1a7a45;border:1px solid #a3d9b8;border-radius:5px;
                                       padding:4px 10px;font-size:11px;cursor:pointer;font-family:'Inter',sans-serif;">
                            ✓ Sterilized
                        </button>
                    </form>
                    @elseif($asset->status === 'under_maintenance')
                    <form method="POST" action="{{ route('inventory.reusable-assets.status', $asset) }}" style="display:inline;">
                        @csrf
                        <input type="hidden" name="action" value="maintained">
                        <button type="submit" title="Mark Maintained"
                                style="background:#e6f0fb;color:#1a5ea8;border:1px solid #a3c0e8;border-radius:5px;
                                       padding:4px 10px;font-size:11px;cursor:pointer;font-family:'Inter',sans-serif;">
                            ✓ Serviced
                        </button>
                    </form>
                    @elseif($asset->status === 'available')
                    <form method="POST" action="{{ route('inventory.reusable-assets.status', $asset) }}" style="display:inline;">
                        @csrf
                        <input type="hidden" name="action" value="mark_in_use">
                        <button type="submit" title="Mark In Use"
                                style="background:#e6f0fb;color:#1a5ea8;border:1px solid #a3c0e8;border-radius:5px;
                                       padding:4px 10px;font-size:11px;cursor:pointer;font-family:'Inter',sans-serif;">
                            Use
                        </button>
                    </form>
                    @elseif($asset->status === 'in_use')
                    <form method="POST" action="{{ route('inventory.reusable-assets.status', $asset) }}" style="display:inline;">
                        @csrf
                        <input type="hidden" name="action" value="sterilized">
                        <button type="submit" title="Mark Sterilized & Available"
                                style="background:#e8f7ee;color:#1a7a45;border:1px solid #a3d9b8;border-radius:5px;
                                       padding:4px 10px;font-size:11px;cursor:pointer;font-family:'Inter',sans-serif;">
                            ✓ Sterilized
                        </button>
                    </form>
                    @endif

                    {{-- Edit button --}}
                    @if($asset->status !== 'retired')
                    <button onclick="openEditAsset({{ json_encode($asset) }})"
                            style="background:#f5f0f8;color:#6a0f70;border:1px solid #d8b8e4;border-radius:5px;
                                   padding:4px 10px;font-size:11px;cursor:pointer;font-family:'Inter',sans-serif;">
                        Edit
                    </button>
                    @endif

                    {{-- Retire button --}}
                    @if($asset->status !== 'retired')
                    <form method="POST" action="{{ route('inventory.reusable-assets.status', $asset) }}" style="display:inline;"
                          onsubmit="return confirm('Retire {{ addslashes($asset->asset_code) }}? This cannot be undone.')">
                        @csrf
                        <input type="hidden" name="action" value="retire">
                        <button type="submit"
                                style="background:#fdeaea;color:#b52020;border:1px solid #f5c6c6;border-radius:5px;
                                       padding:4px 10px;font-size:11px;cursor:pointer;font-family:'Inter',sans-serif;">
                            Retire
                        </button>
                    </form>
                    @endif
                </div>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="8" style="padding:48px;text-align:center;color:#9a85aa;font-family:'Inter',sans-serif;">
                <div style="font-family:'Cormorant Garamond',serif;font-size:20px;color:#6a0f70;margin-bottom:6px;">No assets found</div>
                @if($search || $status || $locId)
                    Try adjusting your filters.
                @else
                    Click <strong>Add Asset</strong> to register your first instrument.
                @endif
            </td>
        </tr>
        @endforelse
        </tbody>
    </table>
</div>

{{-- Pagination --}}
@if($assets->hasPages())
<div style="margin-top:16px;">{{ $assets->links() }}</div>
@endif


{{-- ═══════════════════════════════════════════════════════════
     MODAL — Add Asset
════════════════════════════════════════════════════════════ --}}
<div id="modal-add-asset"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);
            z-index:1000;align-items:flex-start;justify-content:center;padding:24px 16px;overflow-y:auto;">
    <div style="background:#fff;border-radius:10px;width:100%;max-width:580px;box-shadow:0 8px 40px rgba(0,0,0,0.18);">
        <div style="display:flex;align-items:center;justify-content:space-between;
                    padding:18px 24px;border-bottom:1px solid #f0e8f4;">
            <div>
                <h2 style="font-family:'Cormorant Garamond',serif;font-size:20px;color:#1a0a1e;margin:0 0 2px;">Add Reusable Asset</h2>
                <p style="font-family:'Inter',sans-serif;font-size:12px;color:#9a85aa;margin:0;">
                    Register an instrument or equipment item for lifecycle tracking
                </p>
            </div>
            <button onclick="document.getElementById('modal-add-asset').style.display='none'"
                    style="background:none;border:none;cursor:pointer;font-size:22px;color:#9a85aa;">&times;</button>
        </div>
        <form method="POST" action="{{ route('inventory.reusable-assets.store') }}"
              style="padding:22px 24px;">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">

                <div style="grid-column:1/-1;">
                    <label style="display:block;font-family:'Inter',sans-serif;font-size:11px;font-weight:600;
                                  color:#6a0f70;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;">
                        Inventory Item *
                    </label>
                    <select name="inventory_item_id" required
                            style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;
                                   font-size:13px;font-family:'Inter',sans-serif;background:#fff;">
                        <option value="">— Select item —</option>
                        @foreach($items as $it)
                        <option value="{{ $it->id }}">{{ $it->product_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="display:block;font-family:'Inter',sans-serif;font-size:11px;font-weight:600;
                                  color:#6a0f70;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;">
                        Asset Code *
                    </label>
                    <input type="text" name="asset_code" required placeholder="e.g. DRILL-001"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;
                                  font-size:13px;font-family:'Inter',sans-serif;box-sizing:border-box;">
                </div>

                <div>
                    <label style="display:block;font-family:'Inter',sans-serif;font-size:11px;font-weight:600;
                                  color:#6a0f70;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;">
                        Serial Number
                    </label>
                    <input type="text" name="serial_number" placeholder="Optional"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;
                                  font-size:13px;font-family:'Inter',sans-serif;box-sizing:border-box;">
                </div>

                <div>
                    <label style="display:block;font-family:'Inter',sans-serif;font-size:11px;font-weight:600;
                                  color:#6a0f70;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;">
                        Tracking Type *
                    </label>
                    <select name="tracking_type" required
                            style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;
                                   font-size:13px;font-family:'Inter',sans-serif;background:#fff;">
                        <option value="usage_based">Usage Based (count uses)</option>
                        <option value="sterilization_based">Sterilization Based</option>
                        <option value="time_based">Time Based (interval)</option>
                    </select>
                </div>

                <div>
                    <label style="display:block;font-family:'Inter',sans-serif;font-size:11px;font-weight:600;
                                  color:#6a0f70;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;">
                        Max Uses (before retire)
                    </label>
                    <input type="number" name="max_usage_count" min="1" placeholder="e.g. 150"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;
                                  font-size:13px;font-family:'Inter',sans-serif;box-sizing:border-box;">
                </div>

                <div>
                    <label style="display:block;font-family:'Inter',sans-serif;font-size:11px;font-weight:600;
                                  color:#6a0f70;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;">
                        Warn At (uses)
                    </label>
                    <input type="number" name="retirement_threshold" min="1" placeholder="e.g. 130"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;
                                  font-size:13px;font-family:'Inter',sans-serif;box-sizing:border-box;">
                </div>

                <div>
                    <label style="display:block;font-family:'Inter',sans-serif;font-size:11px;font-weight:600;
                                  color:#6a0f70;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;">
                        Service Every (days)
                    </label>
                    <input type="number" name="maintenance_interval" min="1" placeholder="e.g. 90"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;
                                  font-size:13px;font-family:'Inter',sans-serif;box-sizing:border-box;">
                </div>

                <div>
                    <label style="display:block;font-family:'Inter',sans-serif;font-size:11px;font-weight:600;
                                  color:#6a0f70;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;">
                        Location
                    </label>
                    <select name="location_id"
                            style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;
                                   font-size:13px;font-family:'Inter',sans-serif;background:#fff;">
                        <option value="">— Select location —</option>
                        @foreach($locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="display:block;font-family:'Inter',sans-serif;font-size:11px;font-weight:600;
                                  color:#6a0f70;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;">
                        Purchase Date
                    </label>
                    <input type="date" name="purchase_date"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;
                                  font-size:13px;font-family:'Inter',sans-serif;box-sizing:border-box;">
                </div>

                <div>
                    <label style="display:block;font-family:'Inter',sans-serif;font-size:11px;font-weight:600;
                                  color:#6a0f70;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;">
                        Status *
                    </label>
                    <select name="status" required
                            style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;
                                   font-size:13px;font-family:'Inter',sans-serif;background:#fff;">
                        <option value="available">Available</option>
                        <option value="in_use">In Use</option>
                        <option value="sterilization_pending">Needs Sterilization</option>
                        <option value="under_maintenance">Under Maintenance</option>
                    </select>
                </div>

                <div style="grid-column:1/-1;display:flex;align-items:center;gap:8px;padding:4px 0;">
                    <input type="checkbox" name="sterilization_required" id="steril-req" value="1" checked
                           style="width:15px;height:15px;accent-color:#6a0f70;">
                    <label for="steril-req" style="font-family:'Inter',sans-serif;font-size:13px;color:#2d1a35;cursor:pointer;">
                        Sterilization required after each use
                    </label>
                </div>

                <div style="grid-column:1/-1;">
                    <label style="display:block;font-family:'Inter',sans-serif;font-size:11px;font-weight:600;
                                  color:#6a0f70;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;">
                        Notes
                    </label>
                    <textarea name="notes" rows="2" placeholder="Optional notes…"
                              style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;
                                     font-size:13px;font-family:'Inter',sans-serif;box-sizing:border-box;resize:vertical;"></textarea>
                </div>

            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:18px;
                        padding-top:14px;border-top:1px solid #f0e8f4;">
                <button type="button" onclick="document.getElementById('modal-add-asset').style.display='none'"
                        style="background:#f5f0f8;border:none;border-radius:6px;padding:9px 18px;
                               font-size:13px;font-family:'Inter',sans-serif;cursor:pointer;color:#6a0f70;">
                    Cancel
                </button>
                <button type="submit"
                        style="background:#6a0f70;color:#fff;border:none;border-radius:6px;padding:9px 20px;
                               font-size:13px;font-family:'Inter',sans-serif;font-weight:500;cursor:pointer;">
                    Add Asset
                </button>
            </div>
        </form>
    </div>
</div>


{{-- ═══════════════════════════════════════════════════════════
     MODAL — Edit Asset
════════════════════════════════════════════════════════════ --}}
<div id="modal-edit-asset"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);
            z-index:1000;align-items:flex-start;justify-content:center;padding:24px 16px;overflow-y:auto;">
    <div style="background:#fff;border-radius:10px;width:100%;max-width:580px;box-shadow:0 8px 40px rgba(0,0,0,0.18);">
        <div style="display:flex;align-items:center;justify-content:space-between;
                    padding:18px 24px;border-bottom:1px solid #f0e8f4;">
            <div>
                <h2 style="font-family:'Cormorant Garamond',serif;font-size:20px;color:#1a0a1e;margin:0 0 2px;">Edit Asset</h2>
                <p id="edit-asset-subtitle" style="font-family:'Inter',sans-serif;font-size:12px;color:#9a85aa;margin:0;"></p>
            </div>
            <button onclick="document.getElementById('modal-edit-asset').style.display='none'"
                    style="background:none;border:none;cursor:pointer;font-size:22px;color:#9a85aa;">&times;</button>
        </div>
        <form id="edit-asset-form" method="POST" style="padding:22px 24px;">
            @csrf @method('PUT')
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">

                <div>
                    <label style="display:block;font-family:'Inter',sans-serif;font-size:11px;font-weight:600;
                                  color:#6a0f70;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;">Asset Code *</label>
                    <input type="text" id="edit-asset-code" name="asset_code" required
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'Inter',sans-serif;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-family:'Inter',sans-serif;font-size:11px;font-weight:600;
                                  color:#6a0f70;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;">Serial Number</label>
                    <input type="text" id="edit-serial" name="serial_number"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'Inter',sans-serif;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-family:'Inter',sans-serif;font-size:11px;font-weight:600;
                                  color:#6a0f70;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;">Tracking Type *</label>
                    <select id="edit-tracking" name="tracking_type" required
                            style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'Inter',sans-serif;background:#fff;">
                        <option value="usage_based">Usage Based</option>
                        <option value="sterilization_based">Sterilization Based</option>
                        <option value="time_based">Time Based</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-family:'Inter',sans-serif;font-size:11px;font-weight:600;
                                  color:#6a0f70;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;">Status *</label>
                    <select id="edit-status" name="status" required
                            style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'Inter',sans-serif;background:#fff;">
                        <option value="available">Available</option>
                        <option value="in_use">In Use</option>
                        <option value="sterilization_pending">Needs Sterilization</option>
                        <option value="under_maintenance">Under Maintenance</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-family:'Inter',sans-serif;font-size:11px;font-weight:600;
                                  color:#6a0f70;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;">Max Uses</label>
                    <input type="number" id="edit-max-usage" name="max_usage_count" min="1"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'Inter',sans-serif;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-family:'Inter',sans-serif;font-size:11px;font-weight:600;
                                  color:#6a0f70;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;">Warn At (uses)</label>
                    <input type="number" id="edit-threshold" name="retirement_threshold" min="1"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'Inter',sans-serif;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-family:'Inter',sans-serif;font-size:11px;font-weight:600;
                                  color:#6a0f70;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;">Service Every (days)</label>
                    <input type="number" id="edit-maint-interval" name="maintenance_interval" min="1"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'Inter',sans-serif;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-family:'Inter',sans-serif;font-size:11px;font-weight:600;
                                  color:#6a0f70;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;">Location</label>
                    <select id="edit-location" name="location_id"
                            style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'Inter',sans-serif;background:#fff;">
                        <option value="">— None —</option>
                        @foreach($locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-family:'Inter',sans-serif;font-size:11px;font-weight:600;
                                  color:#6a0f70;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;">Purchase Date</label>
                    <input type="date" id="edit-purchase-date" name="purchase_date"
                           style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;font-size:13px;font-family:'Inter',sans-serif;box-sizing:border-box;">
                </div>
                <div style="grid-column:1/-1;display:flex;align-items:center;gap:8px;padding:4px 0;">
                    <input type="checkbox" id="edit-steril-req" name="sterilization_required" value="1"
                           style="width:15px;height:15px;accent-color:#6a0f70;">
                    <label for="edit-steril-req" style="font-family:'Inter',sans-serif;font-size:13px;color:#2d1a35;cursor:pointer;">
                        Sterilization required after each use
                    </label>
                </div>
                <div style="grid-column:1/-1;">
                    <label style="display:block;font-family:'Inter',sans-serif;font-size:11px;font-weight:600;
                                  color:#6a0f70;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;">Notes</label>
                    <textarea id="edit-notes" name="notes" rows="2"
                              style="width:100%;padding:8px 10px;border:1px solid #d8c8e4;border-radius:5px;
                                     font-size:13px;font-family:'Inter',sans-serif;box-sizing:border-box;resize:vertical;"></textarea>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:18px;padding-top:14px;border-top:1px solid #f0e8f4;">
                <button type="button" onclick="document.getElementById('modal-edit-asset').style.display='none'"
                        style="background:#f5f0f8;border:none;border-radius:6px;padding:9px 18px;font-size:13px;font-family:'Inter',sans-serif;cursor:pointer;color:#6a0f70;">
                    Cancel
                </button>
                <button type="submit"
                        style="background:#6a0f70;color:#fff;border:none;border-radius:6px;padding:9px 20px;font-size:13px;font-family:'Inter',sans-serif;font-weight:500;cursor:pointer;">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditAsset(asset) {
    document.getElementById('edit-asset-form').action =
        '/inventory/reusable-assets/' + asset.id;
    document.getElementById('edit-asset-subtitle').textContent = asset.asset_code;
    document.getElementById('edit-asset-code').value        = asset.asset_code || '';
    document.getElementById('edit-serial').value            = asset.serial_number || '';
    document.getElementById('edit-tracking').value          = asset.tracking_type || 'usage_based';
    document.getElementById('edit-status').value            = asset.status || 'available';
    document.getElementById('edit-max-usage').value         = asset.max_usage_count || '';
    document.getElementById('edit-threshold').value         = asset.retirement_threshold || '';
    document.getElementById('edit-maint-interval').value    = asset.maintenance_interval || '';
    document.getElementById('edit-location').value          = asset.location_id || '';
    document.getElementById('edit-purchase-date').value     = asset.purchase_date || '';
    document.getElementById('edit-steril-req').checked      = !!asset.sterilization_required;
    document.getElementById('edit-notes').value             = asset.notes || '';
    document.getElementById('modal-edit-asset').style.display = 'flex';
}

// Backdrop closers
['modal-add-asset','modal-edit-asset'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('click', e => { if (e.target === el) el.style.display = 'none'; });
});
</script>

@endsection
