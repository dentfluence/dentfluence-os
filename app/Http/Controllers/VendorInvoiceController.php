<?php

namespace App\Http\Controllers;

use App\Models\Finance\FinanceExpense;
use App\Models\Finance\FinanceExpenseCategory;
use App\Models\Finance\FinanceVendor;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Procurement\VendorInvoice;
use App\Models\Procurement\VendorInvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

    public function store(Request $request)
    {
        $data = $request->validate([
            'purchase_order_id'   => 'required|exists:purchase_orders,id',
            'finance_vendor_id'   => 'nullable|exists:finance_vendors,id',
            'invoice_number'      => 'nullable|string|max:100',
            'invoice_date'        => 'required|date',
            'due_date'            => 'nullable|date|after_or_equal:invoice_date',
            'payment_terms'       => 'nullable|string|max:100',
            'invoice_amount'      => 'required|numeric|min:0.01',
            'gst_amount'          => 'nullable|numeric|min:0',
            'notes'               => 'nullable|string|max:1000',
            'bill_attachment'     => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            // Line items
            'items'               => 'nullable|array',
            'items.*.inventory_item_id' => 'nullable|exists:inventory_items,id',
            'items.*.description'       => 'nullable|string|max:200',
            'items.*.qty'               => 'nullable|numeric|min:0.01',
            'items.*.unit_price'        => 'nullable|numeric|min:0',
            'items.*.gst_rate'          => 'nullable|numeric|min:0|max:100',
        ]);

        $po          = PurchaseOrder::findOrFail($data['purchase_order_id']);
        $gstAmount   = (float) ($data['gst_amount'] ?? 0);
        $totalAmount = (float) $data['invoice_amount'] + $gstAmount;

        // Handle bill attachment upload
        $attachmentPath = null;
        if ($request->hasFile('bill_attachment')) {
            $attachmentPath = $request->file('bill_attachment')
                ->store('vendor-invoices', 'public');
        }

        // Resolve finance_vendor_id — use from form if provided, else from PO
        $financeVendorId = $data['finance_vendor_id']
            ?? $po->finance_vendor_id
            ?? $po->resolveFinanceVendor()?->id;

        DB::transaction(function () use (
            $data, $po, $gstAmount, $totalAmount, $attachmentPath,
            $financeVendorId, $request
        ) {
            // ── 1. Create the Vendor Invoice ──────────────────────────────
            $invoice = VendorInvoice::create([
                'invoice_ref'         => VendorInvoice::generateRef(),
                'purchase_order_id'   => $po->id,
                'finance_vendor_id'   => $financeVendorId,
                'inventory_vendor_id' => $po->vendor_id,
                'invoice_number'      => $data['invoice_number'] ?? null,
                'invoice_date'        => $data['invoice_date'],
                'due_date'            => $data['due_date'] ?? null,
                'payment_terms'       => $data['payment_terms'] ?? null,
                'invoice_amount'      => $data['invoice_amount'],
                'gst_amount'          => $gstAmount,
                'total_amount'        => $totalAmount,
                'bill_attachment'     => $attachmentPath,
                'notes'               => $data['notes'] ?? null,
                'status'              => 'pending',
                'created_by'          => auth()->id(),
            ]);

            // ── 2. Save line items (if provided) ─────────────────────────
            foreach ($data['items'] ?? [] as $line) {
                $lineQty   = (float) ($line['qty'] ?? 1);
                $linePrice = (float) ($line['unit_price'] ?? 0);
                $lineGst   = (float) ($line['gst_rate'] ?? 0);
                $lineTotal = $lineQty * $linePrice * (1 + $lineGst / 100);

                $invoice->items()->create([
                    'inventory_item_id' => $line['inventory_item_id'] ?? null,
                    'description'       => $line['description'] ?? null,
                    'qty'               => $lineQty,
                    'unit_price'        => $linePrice,
                    'gst_rate'          => $lineGst,
                    'total_price'       => $lineTotal,
                ]);
            }

            // ── 3. Accounts Payable entry in Finance ─────────────────────
            //    Finance is the single source of truth for payments.
            //
            //    DOUBLE-AP GUARD (2026-07-14): receiving goods against a PO
            //    ALREADY books an unpaid vendor bill (InventoryService, on GRN
            //    receipt, source_type='PurchaseOrder'). Entering the vendor's
            //    actual invoice for that same PO then created a SECOND unpaid
            //    expense for the same liability — payables were double-counted
            //    in Finance > Expenses and the bill could be paid twice.
            //
            //    So: if a GRN-sourced unpaid expense already exists for this PO,
            //    take it over — re-point it at this invoice and correct its
            //    amounts to the invoice's (authoritative) figures — instead of
            //    creating a second bill. Note the GRN path writes the bare
            //    string 'PurchaseOrder' while everything else uses the FQCN, so
            //    both forms are matched here.
            $suppliesCategory = FinanceExpenseCategory::where('name', 'like', '%Suppl%')
                ->orWhere('name', 'like', '%Dental%')
                ->orWhere('name', 'like', '%Inventory%')
                ->orWhere('name', 'like', '%Purchase%')
                ->first();

            $existingGrnExpense = FinanceExpense::whereIn('source_type', ['PurchaseOrder', PurchaseOrder::class])
                ->where('source_id', $po->id)
                ->where('payment_status', 'unpaid')
                ->first();

            $expenseFields = [
                'title'          => 'Vendor Invoice ' . $invoice->invoice_ref
                                    . ' — PO# ' . $po->order_no,
                'description'    => 'Vendor bill: '
                                    . ($invoice->invoice_number ? 'Invoice# ' . $invoice->invoice_number . '. ' : '')
                                    . 'Auto-created from Procurement → Vendor Invoice.',
                'expense_date'   => $data['invoice_date'],
                'due_date'       => $data['due_date'] ?? null,
                'amount'         => $data['invoice_amount'],
                'gst_applicable' => $gstAmount > 0,
                'gst_amount'     => $gstAmount,
                'total_amount'   => $totalAmount,
                'category_id'    => $suppliesCategory?->id,
                'vendor_id'      => $financeVendorId,
                'payment_status' => 'unpaid',
                // payment_mode intentionally omitted — DB default applies;
                // will be set when the bill is paid in Finance
                'status'         => 'approved',
                'source_type'    => VendorInvoice::class,
                'source_id'      => $invoice->id,
                'notes'          => $data['notes'] ?? null,
            ];

            // Delta the vendor's cached outstanding by the CHANGE in the bill,
            // not the full amount, when we're taking over an existing GRN bill.
            $previousTotal = 0.0;

            if ($existingGrnExpense) {
                $previousTotal = (float) $existingGrnExpense->total_amount;
                $existingGrnExpense->update($expenseFields);
                $expense = $existingGrnExpense;
            } else {
                $expense = FinanceExpense::create($expenseFields + ['created_by' => auth()->id()]);
            }

            // ── 4. Link the Finance expense back to the invoice ───────────
            $invoice->update(['finance_expense_id' => $expense->id]);

            // ── 5. Update PO invoice_status + running invoiced_amount ─────
            $po->recalculateInvoiceStatus();

            // ── 6. Update Finance vendor outstanding (cached field) ────────
            if ($financeVendorId) {
                $delta = $totalAmount - $previousTotal;
                if (abs($delta) >= 0.01) {
                    \App\Models\Finance\FinanceVendor::where('id', $financeVendorId)
                        ->increment('outstanding_amount', $delta);
                }
            }
        });

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

    public function destroy(VendorInvoice $vendorInvoice)
    {
        if ($vendorInvoice->status === 'paid') {
            return back()->with('error', 'Cannot delete a paid invoice.');
        }

        DB::transaction(function () use ($vendorInvoice) {
            // Reverse the Finance AP entry
            if ($vendorInvoice->financeExpense) {
                $vendorInvoice->financeExpense->delete();
            }

            // Reverse Finance vendor outstanding
            if ($vendorInvoice->finance_vendor_id) {
                \App\Models\Finance\FinanceVendor::where('id', $vendorInvoice->finance_vendor_id)
                    ->decrement('outstanding_amount', $vendorInvoice->total_amount);
            }

            $vendorInvoice->delete();

            // Recalculate PO invoice status
            $vendorInvoice->purchaseOrder->recalculateInvoiceStatus();
        });

        return back()->with('success', 'Invoice cancelled and Finance AP entry reversed.');
    }
}
