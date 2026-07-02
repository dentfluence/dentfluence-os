@extends('layouts.app')

@section('title', 'New Vendor Invoice')

@section('content')
{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- VENDOR INVOICE FORM — Phase 1                              --}}
{{-- ═══════════════════════════════════════════════════════════ --}}

<div style="padding:24px 28px;max-width:900px;margin:0 auto;">

    {{-- Header --}}
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
        <a href="{{ route('inventory.vendor-invoices.index') }}"
           style="color:#5a8a6a;text-decoration:none;display:flex;align-items:center;gap:4px;font-size:13px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Vendor Invoices
        </a>
        <span style="color:#ccc;">/</span>
        <h1 style="font-family:'Cormorant Garamond',serif;font-size:22px;font-weight:600;
                    color:#0d3d22;margin:0;">New Vendor Invoice</h1>
    </div>

    {{-- Info banner --}}
    <div style="background:#e6f9f0;border-left:3px solid #1a7a45;padding:12px 16px;
                border-radius:4px;margin-bottom:24px;font-size:13px;color:#0d3d22;">
        <strong>Auto-sync:</strong> Saving this invoice will automatically create an
        <strong>unpaid Accounts Payable entry</strong> in Finance → Expenses.
        No double entry required.
    </div>

    @if($errors->any())
    <div style="background:#fdeaea;border-left:3px solid #b52020;padding:12px 16px;
                border-radius:4px;margin-bottom:20px;">
        <ul style="margin:0;padding-left:18px;font-size:13px;color:#b52020;">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('inventory.vendor-invoices.store') }}"
          enctype="multipart/form-data">
        @csrf

        {{-- Section 1: PO Linkage --}}
        <div style="background:#fff;border:1px solid rgba(26,122,69,0.15);
                    border-radius:6px;padding:20px;margin-bottom:16px;">
            <h3 style="font-size:13px;font-weight:600;color:#1a7a45;margin:0 0 16px 0;
                        text-transform:uppercase;letter-spacing:.06em;">
                1. Purchase Order Linkage *
            </h3>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#444;margin-bottom:5px;">
                        Purchase Order *
                    </label>
                    <select name="purchase_order_id" id="po-select" required
                            style="width:100%;padding:8px 12px;border:1px solid rgba(26,122,69,0.25);
                                   border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;
                                   box-sizing:border-box;">
                        <option value="">— Select PO —</option>
                        @foreach($openPOs as $openPo)
                        <option value="{{ $openPo->id }}"
                                data-vendor="{{ $openPo->vendor?->vendor_name ?? '' }}"
                                data-amount="{{ $openPo->total_amount }}"
                                {{ old('purchase_order_id', $po?->id) == $openPo->id ? 'selected' : '' }}>
                            {{ $openPo->order_no }} —
                            {{ $openPo->vendor?->vendor_name ?? 'Unknown Vendor' }}
                            (Rs. {{ number_format($openPo->total_amount,0) }})
                            [{{ ucfirst($openPo->status) }}]
                        </option>
                        @endforeach
                    </select>
                    @if($openPOs->isEmpty())
                    <p style="font-size:11px;color:#b52020;margin:4px 0 0 0;">
                        No open POs available. Create a PO first.
                    </p>
                    @endif
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#444;margin-bottom:5px;">
                        Finance Vendor (auto-filled from PO)
                    </label>
                    <select name="finance_vendor_id"
                            style="width:100%;padding:8px 12px;border:1px solid rgba(26,122,69,0.25);
                                   border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;
                                   box-sizing:border-box;">
                        <option value="">— Auto from PO —</option>
                        @foreach($vendors as $v)
                        <option value="{{ $v->id }}" {{ old('finance_vendor_id')==$v->id?'selected':'' }}>
                            {{ $v->vendor_name }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Section 2: Invoice Details --}}
        <div style="background:#fff;border:1px solid rgba(26,122,69,0.15);
                    border-radius:6px;padding:20px;margin-bottom:16px;">
            <h3 style="font-size:13px;font-weight:600;color:#1a7a45;margin:0 0 16px 0;
                        text-transform:uppercase;letter-spacing:.06em;">
                2. Invoice Details
            </h3>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#444;margin-bottom:5px;">
                        Vendor's Invoice Number
                    </label>
                    <input type="text" name="invoice_number" value="{{ old('invoice_number') }}"
                           placeholder="e.g. INV-2026-0123"
                           style="width:100%;padding:8px 12px;border:1px solid rgba(26,122,69,0.25);
                                  border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;
                                  box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#444;margin-bottom:5px;">
                        Invoice Date *
                    </label>
                    <input type="date" name="invoice_date" required value="{{ old('invoice_date', date('Y-m-d')) }}"
                           style="width:100%;padding:8px 12px;border:1px solid rgba(26,122,69,0.25);
                                  border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;
                                  box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#444;margin-bottom:5px;">
                        Due Date
                    </label>
                    <input type="date" name="due_date" value="{{ old('due_date') }}"
                           style="width:100%;padding:8px 12px;border:1px solid rgba(26,122,69,0.25);
                                  border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;
                                  box-sizing:border-box;">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-top:14px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#444;margin-bottom:5px;">
                        Payment Terms
                    </label>
                    <input type="text" name="payment_terms" value="{{ old('payment_terms') }}"
                           placeholder="e.g. Net 30, Due on receipt"
                           style="width:100%;padding:8px 12px;border:1px solid rgba(26,122,69,0.25);
                                  border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;
                                  box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#444;margin-bottom:5px;">
                        Invoice Amount (Rs. ) *
                    </label>
                    <input type="number" name="invoice_amount" id="invoice-amount" required min="0.01" step="0.01"
                           value="{{ old('invoice_amount') }}"
                           oninput="recalcTotal()"
                           style="width:100%;padding:8px 12px;border:1px solid rgba(26,122,69,0.25);
                                  border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;
                                  box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#444;margin-bottom:5px;">
                        GST Amount (Rs. )
                    </label>
                    <input type="number" name="gst_amount" id="gst-amount" min="0" step="0.01"
                           value="{{ old('gst_amount', 0) }}"
                           oninput="recalcTotal()"
                           style="width:100%;padding:8px 12px;border:1px solid rgba(26,122,69,0.25);
                                  border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;
                                  box-sizing:border-box;">
                </div>
            </div>

            {{-- Total display --}}
            <div style="margin-top:14px;padding:12px 16px;background:#f0faf4;border-radius:4px;
                        display:flex;justify-content:flex-end;align-items:center;gap:8px;">
                <span style="font-size:13px;color:#1a7a45;font-weight:500;">Total Invoice Amount:</span>
                <span id="total-display"
                      style="font-size:20px;font-weight:700;color:#0d3d22;font-family:'Cormorant Garamond',serif;">
                    Rs. 0
                </span>
            </div>
        </div>

        {{-- Section 3: Attachment & Notes --}}
        <div style="background:#fff;border:1px solid rgba(26,122,69,0.15);
                    border-radius:6px;padding:20px;margin-bottom:16px;">
            <h3 style="font-size:13px;font-weight:600;color:#1a7a45;margin:0 0 16px 0;
                        text-transform:uppercase;letter-spacing:.06em;">
                3. Bill Attachment & Notes
            </h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#444;margin-bottom:5px;">
                        Upload Bill (PDF/JPG/PNG, max 5MB)
                    </label>
                    <input type="file" name="bill_attachment" accept=".pdf,.jpg,.jpeg,.png"
                           style="width:100%;padding:8px 0;font-size:13px;font-family:'Inter',sans-serif;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#444;margin-bottom:5px;">
                        Notes
                    </label>
                    <textarea name="notes" rows="3" placeholder="Any additional notes..."
                              style="width:100%;padding:8px 12px;border:1px solid rgba(26,122,69,0.25);
                                     border-radius:3px;font-size:13px;font-family:'Inter',sans-serif;
                                     box-sizing:border-box;resize:vertical;">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div style="display:flex;gap:10px;justify-content:flex-end;padding-top:8px;">
            <a href="{{ route('inventory.vendor-invoices.index') }}"
               style="padding:9px 20px;background:#fff;color:#1a7a45;
                      border:1px solid rgba(26,122,69,0.3);border-radius:3px;
                      font-size:13px;text-decoration:none;font-family:'Inter',sans-serif;">
                Cancel
            </a>
            <button type="submit"
                    style="padding:9px 24px;background:#1a7a45;color:#fff;border:none;
                           border-radius:3px;font-size:13px;font-weight:500;cursor:pointer;
                           font-family:'Inter',sans-serif;display:flex;align-items:center;gap:6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Save Invoice &amp; Create AP Entry
            </button>
        </div>

    </form>
</div>

<script>
function recalcTotal() {
    const amt = parseFloat(document.getElementById('invoice-amount').value) || 0;
    const gst = parseFloat(document.getElementById('gst-amount').value) || 0;
    const total = amt + gst;
    document.getElementById('total-display').textContent = 'Rs. ' + total.toLocaleString('en-IN', {maximumFractionDigits:0});
}
// Run on page load if values pre-filled
recalcTotal();
</script>
@endsection
