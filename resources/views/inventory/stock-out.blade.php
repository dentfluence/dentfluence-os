@extends('layouts.app')
@section('page-title', 'Inventory — Stock Out')

@section('content')
<div class="df-page-header">
    <div>
        <div class="df-page-title" style="font-size:22px;">Inventory</div>
        <div class="df-page-subtitle">Stock Out — Dispense / Consume</div>
    </div>
</div>

@include('inventory.partials.subnav')

@if(session('success'))
<div style="padding:10px 16px;background:#e8f7ef;border:1px solid rgba(26,122,69,0.2);border-left:3px solid #1a7a45;border-radius:3px;font-size:13px;color:#0e4a28;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;">
    {{ session('success') }}<button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:#1a7a45;">✕</button>
</div>
@endif
@if($errors->any())
<div style="padding:10px 16px;background:#fdeaea;border:1px solid rgba(181,32,32,0.2);border-left:3px solid #b52020;border-radius:3px;font-size:13px;color:#6b1010;margin-bottom:16px;">
    @foreach($errors->all() as $e){{ $e }}<br>@endforeach
</div>
@endif

<div style="max-width:560px;margin:0 auto;">
    <div class="df-card">
        <div class="df-card-header">
            <div>
                <div style="font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:600;color:#1e0a2c;">Dispense / Use Stock</div>
                <div style="font-size:12px;color:#9a85aa;margin-top:2px;">Record outgoing goods — updates live stock ledger</div>
            </div>
        </div>
        <div class="df-card-body">
            <form action="{{ route('inventory.stock-out.store') }}" method="POST">
                @csrf
                <div style="display:flex;flex-direction:column;gap:16px;">

                    <div>
                        <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:6px;">Item *</label>
                        <select name="inventory_item_id" required id="stockout-item"
                            onchange="loadStockInfo(this)"
                            style="width:100%;padding:9px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;color:#1e0a2c;outline:none;">
                            <option value="">— Select inventory item —</option>
                            @foreach($items as $item)
                            <option value="{{ $item->id }}" data-unit="{{ $item->consumption_unit }}">
                                {{ $item->product_name }}
                                @if($item->generic_name) ({{ $item->generic_name }})@endif
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:6px;">From Location *</label>
                        <select name="from_location_id" required id="stockout-location"
                            onchange="loadStockInfo()"
                            style="width:100%;padding:9px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;color:#1e0a2c;outline:none;">
                            <option value="">— Select location —</option>
                            @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" {{ $loc->type === 'main_store' ? 'selected' : '' }}>{{ $loc->name }}</option>
                            @endforeach
                        </select>
                        {{-- Live stock availability chip --}}
                        <div id="available-qty-info" style="font-size:12px;margin-top:6px;min-height:20px;"></div>
                    </div>

                    <div>
                        <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:6px;">Reason *</label>
                        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;" id="reason-btns">
                            @php $reasons = [
                                ['val'=>'stock_out',       'label'=>'Dispensed',     'color'=>'#6a0f70'],
                                ['val'=>'treatment_usage', 'label'=>'Treatment Use', 'color'=>'#1a5ea8'],
                                ['val'=>'damaged',         'label'=>'Damaged',       'color'=>'#b52020'],
                                ['val'=>'expired',         'label'=>'Expired',       'color'=>'#a05c00'],
                                ['val'=>'adjustment',      'label'=>'Adjustment',    'color'=>'#555'],
                            ]; @endphp
                            @foreach($reasons as $r)
                            <label style="cursor:pointer;">
                                <input type="radio" name="movement_type" value="{{ $r['val'] }}" {{ $loop->first ? 'checked' : '' }}
                                    style="display:none;" onchange="updateReasonStyle(this)">
                                <div class="reason-btn" data-val="{{ $r['val'] }}"
                                    style="padding:8px 6px;border:1px solid rgba(185,92,183,0.20);border-radius:3px;font-size:12px;text-align:center;color:#7a6884;transition:all 150ms;{{ $loop->first ? 'background:#f9f3fa;border-color:#6a0f70;color:#6a0f70;font-weight:500;' : '' }}">
                                    {{ $r['label'] }}
                                </div>
                            </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:6px;">Quantity *</label>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <input type="number" name="qty" required min="0.01" step="0.01" placeholder="0"
                                style="flex:1;padding:9px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter', sans-serif;outline:none;">
                            <span id="stockout-unit" style="font-size:12px;color:#9a85aa;white-space:nowrap;">units</span>
                        </div>
                    </div>

                    <div>
                        <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:6px;">Notes</label>
                        <textarea name="notes" rows="2" placeholder="Patient name, treatment, remarks…"
                            style="width:100%;padding:9px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;outline:none;resize:vertical;box-sizing:border-box;"></textarea>
                    </div>

                    <div style="display:flex;gap:10px;padding-top:4px;">
                        <button type="submit"
                            style="flex:1;padding:11px;background:#b52020;color:#fff;border:none;border-radius:3px;font-size:13px;font-weight:500;cursor:pointer;font-family:'Inter',sans-serif;display:flex;align-items:center;justify-content:center;gap:6px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            Confirm Stock Out
                        </button>
                        <a href="{{ route('inventory.index') }}"
                            style="padding:11px 20px;background:#fff;color:#6a0f70;border:1px solid rgba(106,15,112,0.25);border-radius:3px;font-size:13px;cursor:pointer;font-family:'Inter',sans-serif;text-decoration:none;display:flex;align-items:center;">
                            Cancel
                        </a>
                    </div>

                </div>
            </form>
        </div>
    </div>

    {{-- Recent stock-out feed --}}
    <div style="margin-top:20px;">
        <div style="font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;margin-bottom:10px;">Recent Stock Out</div>
        @php
            $recentOut = \App\Models\Inventory\StockMovement::with(['item','fromLocation'])
                ->whereIn('movement_type',['stock_out','treatment_usage','damaged','expired','adjustment'])
                ->latest()->limit(5)->get();
        @endphp
        @forelse($recentOut as $mv)
        <div style="background:#fff;border:1px solid rgba(185,92,183,0.08);border-radius:4px;padding:11px 16px;margin-bottom:8px;display:flex;align-items:center;justify-content:space-between;">
            <div>
                <div style="font-size:13px;font-weight:500;color:#1e0a2c;">{{ $mv->item?->product_name ?? '—' }}</div>
                <div style="font-size:11px;color:#9a85aa;margin-top:2px;">
                    {{ $mv->fromLocation?->name ?? '—' }} ·
                    <span style="color:#6a0f70;">{{ $mv->getMovementLabel() }}</span> ·
                    {{ $mv->created_at->diffForHumans() }}
                </div>
            </div>
            <div style="font-family:'Inter', sans-serif;font-weight:600;color:#b52020;font-size:15px;">{{ number_format($mv->qty,1) }}</div>
        </div>
        @empty
        <div style="font-size:13px;color:#9a85aa;padding:16px;text-align:center;">No stock-out recorded yet.</div>
        @endforelse
    </div>
</div>
@endsection

@push('scripts')
<script>
// ── Live stock check ──────────────────────────────────────────────
function loadStockInfo() {
    const itemId     = document.getElementById('stockout-item').value;
    const locationId = document.getElementById('stockout-location').value;
    const infoBox    = document.getElementById('available-qty-info');

    // Update unit label from item select
    const itemSel = document.getElementById('stockout-item');
    const opt     = itemSel.options[itemSel.selectedIndex];
    document.getElementById('stockout-unit').textContent = opt?.dataset?.unit || 'units';

    if (!itemId) {
        infoBox.innerHTML = '';
        return;
    }

    infoBox.innerHTML = '<span style="color:#9a85aa;">Checking stock…</span>';

    const url = '{{ route("inventory.stock-check") }}?item_id=' + itemId
              + (locationId ? '&location_id=' + locationId : '');

    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            const available = parseFloat(data.available) || 0;
            const unit      = data.unit || 'units';
            const minimum   = parseFloat(data.minimum) || 0;

            let color = '#1a7a45';
            let bg    = '#e8f7ee';
            let icon  = '✓';

            if (available <= 0) {
                color = '#b52020'; bg = '#fdeaea'; icon = '✕';
            } else if (available <= minimum) {
                color = '#a05c00'; bg = '#fff4e0'; icon = '';
            }

            infoBox.innerHTML = `
                <span style="display:inline-flex;align-items:center;gap:5px;
                             padding:3px 10px;border-radius:20px;font-size:11.5px;
                             background:${bg};color:${color};font-weight:500;">
                    ${icon} Available: <strong style="font-family:'Inter', sans-serif;">
                        ${available % 1 === 0 ? available : available.toFixed(2)}
                    </strong> ${unit}
                    ${locationId ? '' : '(across all locations)'}
                </span>`;

            // Warn if entering more than available
            const qtyInput = document.querySelector('input[name="qty"]');
            if (qtyInput) {
                qtyInput.max = available > 0 ? available : '';
            }
        })
        .catch(() => { infoBox.innerHTML = ''; });
}

// Trigger on item change too
document.getElementById('stockout-item').addEventListener('change', loadStockInfo);

// Fire on page load if item pre-selected
window.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('stockout-item').value) loadStockInfo();
});

// ── Reason radio styling ──────────────────────────────────────────
function updateReasonStyle(radio) {
    document.querySelectorAll('.reason-btn').forEach(btn => {
        btn.style.background  = '';
        btn.style.borderColor = 'rgba(185,92,183,0.20)';
        btn.style.color       = '#7a6884';
        btn.style.fontWeight  = '400';
    });
    const selected = document.querySelector('.reason-btn[data-val="' + radio.value + '"]');
    if (selected) {
        selected.style.background  = '#f9f3fa';
        selected.style.borderColor = '#6a0f70';
        selected.style.color       = '#6a0f70';
        selected.style.fontWeight  = '500';
    }
}
</script>
@endpush
                                                                                                                                                                                                                                                                                                                                                                      