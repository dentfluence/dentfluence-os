<?php

namespace App\Http\Controllers;

use App\Models\Finance\FinanceVendor;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Procurement\VendorInvoice;
use App\Services\Procurement\VendorInvoiceService;
use Illuminate\Http\Request;

/**
 * VendorInvoiceController — Phase 1 Vendor Invoice Management.
 *
 * Routes:
 *   GET    /inventory/vendor-invoices               index()
 *   GET    /inventory/vendor-invoices/create        create()
 *   POST   /inventory/vendor-invoices               store()
 *   GET    /inventory/vendor-invoices/{invoice}     show()
 *   DELETE /inventory/vendor-invoices/{invoice}     destroy()
 *
 * Each saved invoice automatically creates:
 *   1. FinanceExpense (Accounts Payable / Unpaid Vendor Bill)
 *   2. Updates PO invoice_status + invoiced_amount
 *
 * Finance remains the single source of truth for payments.
 * When a Finance expense is marked paid, the invoice status updates
 * (wired via the payment_status column on finance_expenses).
 */
class VendorInvoiceController extends Controller
{
    /* ══════════════════════════════════════════════════════════
       INDEX
    ══════════════════════════════════════════════════════════ */

    public function index(Request $request)
    {
        $query = VendorInvoice::with(['purchaseOrder', 'financeVendor', 'inventoryVendor'])
            ->orderByDesc('invoice_date');

        if ($search = $request->search) {
            $query->where(fn($q) => $q
                ->where('invoice_ref', 'like', "%{$search}%")
                ->orWhere('invoice_number', 'like', "%{$search}%"));
        }

        if ($status = $request->status) {
            $query->where('status', $status);
        }

        if ($vendorId = $request->vendor_id) {
            $query->where('finance_vendor_id', $vendorId);
        }

        $invoices = $query->paginate(20)->withQueryString();

        // Dashboard KPIs
        $kpis = [
            'total_pending'   => VendorInvoice::where('status', 'pending')->count(),
            'total_amount'    => VendorInvoice::whereIn('status', ['pending', 'approved'])->sum('total_amount'),
            'overdue_count'   => VendorInvoice::where('status', 'pending')
                                    ->whereNotNull('due_date')
                                    ->where('due_date', '<', today())
                                    ->count(),
            'paid_this_month' => VendorInvoice::where('status', 'paid')
                                    ->whereMonth('updated_at', now()->month)
                                    ->sum('total_amount'),
        ];

        $vendors = FinanceVendor::where('is_active', true)->orderBy('vendor_name')->get(['id', 'vendor_name']);

        return view('inventory.vendor-invoices', compact('invoices', 'kpis', 'vendors', 'search', 'status', 'vendorId'));
    }

    /* ══════════════════════════════════════════════════════════
       CREATE FORM
    ══════════════════════════════════════════════════════════ */

    public function create(Request $request)
    {
        // Pre-select PO if passed as query param
        $po = $request->po_id ? PurchaseOrder::with(['vendor', 'items.item'])->find($request->po_id) : null;

        $openPOs = PurchaseOrder::with('vendor')
            ->whereIn('status', ['ordered', 'partially_received', 'completed'])
            ->where('invoice_status', '!=', 'fully_invoiced')
            ->orderByDesc('order_date')
            ->get();

        $vendors = FinanceVendor::where('is_active', true)->orderBy('vendor_name')->get(['id', 'vendor_name']);

        return view('inventory.vendor-invoice-form', compact('po', 'openPOs', 'vendors'));
    }

    /* ══════════════════════════════════════════════════════════
       STORE — creates invoice + AP entry automatically
    ══════════════════════════════════════════════════════════ */

    public function store(Request $request, VendorInvoiceService $service)
    {
        $data = $request->validate(VendorInvoiceService::rules());

        $service->create($data, $request->user(), $request->file('bill_attachment'));

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Invoice saved and AP entry created.']);
        }

        return redirect()->route('inventory.vendor-invoices.index')
            ->with('success', 'Vendor invoice saved. Unpaid bill added to Finance → Expenses automatically.');
    }

    /* ══════════════════════════════════════════════════════════
       SHOW
    ══════════════════════════════════════════════════════════ */

    public function show(VendorInvoice $vendorInvoice)
    {
        $vendorInvoice->load(['purchaseOrder.vendor', 'financeVendor', 'items.item', 'financeExpense', 'createdBy']);

        return view('inventory.vendor-invoice-show', compact('vendorInvoice'));
    }

    /* ══════════════════════════════════════════════════════════
       DESTROY (soft delete)
    ══════════════════════════════════════════════════════════ */

    public function destroy(VendorInvoice $vendorInvoice, VendorInvoiceService $service)
    {
        try {
            $service->cancel($vendorInvoice);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Invoice cancelled and Finance AP entry reversed.');
    }
}
