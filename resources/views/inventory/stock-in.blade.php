@extends('layouts.app')
@section('page-title', 'Inventory — Stock In')

@section('content')
<div class="df-page-header">
    <div>
        <div class="df-page-title" style="font-size:22px;">Inventory</div>
        <div class="df-page-subtitle">Stock In — Receive Goods</div>
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

{{-- Centered action card --}}
<div style="max-width:560px;margin:0 auto;">
    <div class="df-card">
        <div class="df-card-header">
            <div>
                <div style="font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:600;color:#1e0a2c;">Receive Stock</div>
                <div style="font-size:12px;color:#9a85aa;margin-top:2px;">Record incoming goods — updates live stock ledger</div>
            </div>
        </div>
        <div class="df-card-body">
            <form action="{{ route('inventory.stock-in.store') }}" method="POST">
                @csrf
                <div style="display:flex;flex-direction:column;gap:16px;">

                    <div>
                        <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:6px;">Item *</label>
                        <select name="inventory_item_id" required id="stockin-item"
                            style="width:100%;padding:9px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;color:#1e0a2c;outline:none;">
                            <option value="">— Select inventory item —</option>
                            @foreach($items as $item)
                            <option value="{{ $item->id }}"
                                data-unit="{{ $item->consumption_unit }}"
                                data-price="{{ $item->last_purchase_price }}">
                                {{ $item->product_name }}
                                @if($item->generic_name) ({{ $item->generic_name }})@endif
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div>
                            <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:6px;">Quantity Received *</label>
                            <div style="display:flex;align-items:center;gap:6px;">
                                <input type="number" name="qty" required min="0.01" step="0.01" placeholder="0"
                                    style="flex:1;padding:9px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter', sans-serif;outline:none;">
                                <span id="stockin-unit" style="font-size:12px;color:#9a85aa;white-space:nowrap;min-width:30px;">units</span>
                            </div>
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:6px;">Unit Cost (Rs. ) *</label>
                            <input type="number" name="unit_cost" id="stockin-price" required min="0.01" step="0.01" placeholder="0.00"
                                style="width:100%;padding:9px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter', sans-serif;outline:none;box-sizing:border-box;">
                        </div>
                    </div>

                    <div>
                        <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:6px;">Receive Into Location *</label>
                        <select name="to_location_id" required
                            style="width:100%;padding:9px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;color:#1e0a2c;outline:none;">
                            <option value="">— Select location —</option>
                            @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" {{ $loc->type === 'main_store' ? 'selected' : '' }}>{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div>
                            <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:6px;">Batch Number</label>
                            <input type="text" name="batch_no" placeholder="e.g. BT-2024-001"
                                style="width:100%;padding:9px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter', sans-serif;outline:none;box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:6px;">Expiry Date</label>
                            <input type="date" name="expiry_date"
                                style="width:100%;padding:9px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;">
                        </div>
                    </div>

                    <div>
                        <label style="display:block;font-size:12px;font-weight:500;color:#4e2060;margin-bottom:6px;">Notes</label>
                        <textarea name="notes" rows="2" placeholder="Supplier, invoice no., remarks…"
                            style="width:100%;padding:9px 12px;border:1px solid rgba(185,92,183,0.25);border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;outline:none;resize:vertical;box-sizing:border-box;"></textarea>
                    </div>

                    <div style="display:flex;gap:10px;padding-top:4px;">
                        <button type="submit"
                            style="flex:1;padding:11px;background:#1a7a45;color:#fff;border:none;border-radius:3px;font-size:13px;font-weight:500;cursor:pointer;font-family:'Inter',sans-serif;display:flex;align-items:center;justify-content:center;gap:6px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            Confirm Stock In
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

    {{-- Recent stock-in movements --}}
    <div style="margin-top:20px;">
        <div style="font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;margin-bottom:10px;">Recent Stock In</div>
        @php
            $recentIn = \App\Models\Inventory\StockMovement::with(['item','toLocation'])
                ->whereIn('movement_type',['stock_in','opening_stock'])
                ->latest()->limit(5)->get();
        @endphp
        @forelse($recentIn as $mv)
        <div style="background:#fff;border:1px solid rgba(185,92,183,0.08);border-radius:4px;padding:11px 16px;margin-bottom:8px;display:flex;align-items:center;justify-content:space-between;">
            <div>
                <div style="font-size:13px;font-weight:500;color:#1e0a2c;">{{ $mv->item?->product_name ?? '—' }}</div>
                <div style="font-size:11px;color:#9a85aa;margin-top:2px;">{{ $mv->toLocation?->name ?? '—' }} @if($mv->batch_no) · {{ $mv->batch_no }}@endif · {{ $mv->created_at->diffForHumans() }}</div>
            </div>
            <div style="font-family:'Inter', sans-serif;font-weight:600;color:#1a7a45;font-size:15px;">+{{ number_format($mv->qty,1) }}</div>
        </div>
        @empty
        <div style="font-size:13px;color:#9a85aa;padding:16px;text-align:center;">No stock-in recorded yet.</div>
        @endforelse
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('stockin-item').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    document.getElementById('stockin-unit').textContent  = opt.dataset.unit  || 'units';
    document.getElementById('stockin-price').value       = opt.dataset.price || '';
});
</scrip