<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Finance\FinanceVendor;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Procurement\VendorInvoice;
use App\Services\Procurement\VendorInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Api\V1\VendorInvoiceController — mobile parity for Procurement Phase 1
 * Vendor Invoice management. Mirrors App\Http\Controllers\VendorInvoiceController
 * exactly for every side effect (Finance AP entry auto-create/reverse,
 * PO invoice_status recalculation, FinanceVendor outstanding_amount).
 *
 * Routes (routes/api.php, under auth:sanctum):
 *   GET    /api/v1/inventory/vendor-invoices                index()
 *   GET    /api/v1/inventory/vendor-invoices/form-options    formOptions()
 *   GET    /api/v1/inventory/vendor-invoices/{vendorInvoice} show()
 *   POST   /api/v1/inventory/vendor-invoices                store()
 *   DELETE /api/v1/inventory/vendor-invoices/{vendorInvoice} destroy()
 */
class VendorInvoiceController extends ApiController
{
    /* ══════════════════════════════════════════════════════════
       INDEX — paginated list + KPI block
    ══════════════════════════════════════════════════════════ */

    public function index(Request $request): JsonResponse
    {
        $query = VendorInvoice::with(['purchaseOrder', 'financeVendor', 'inventoryVendor'])
            ->orderByDesc('invoice_date');

        if ($search = $request->search) {
            $query->where(fn ($q) => $q
                ->where('invoice_ref', 'like', "%{$search}%")
                ->orWhere('invoice_number', 'like', "%{$search}%"));
        }

        if ($status = $request->status) {
            $query->where('status', $status);
        }

        if ($vendorId = $request->vendor_id) {
            $query->where('finance_vendor_id', $vendorId);
        }

        $limit = (int) $request->query('limit', 20);
        $limit = max(1, min($limit, 100));

        $page = $query->paginate($limit)->appends($request->query());

        $items = collect($page->items())->map(fn (VendorInvoice $v) => $this->mapSummary($v))->values();

        // Dashboard KPIs — identical to web index()
        $kpis = [
            'total_pending'   => VendorInvoice::where('status', 'pending')->count(),
            'total_amount'    => (float) VendorInvoice::whereIn('status', ['pending', 'approved'])->sum('total_amount'),
            'overdue_count'   => VendorInvoice::where('status', 'pending')
                                    ->whereNotNull('due_date')
                                    ->where('due_date', '<', today())
                                    ->count(),
            'paid_this_month' => (float) VendorInvoice::where('status', 'paid')
                                    ->whereMonth('updated_at', now()->month)
                                    ->sum('total_amount'),
        ];

        return $this->success([
            'invoices' => $items,
            'kpis'     => $kpis,
        ], '', 200, [
            'current_page' => $page->currentPage(),
            'per_page'     => $page->perPage(),
            'total'        => $page->total(),
            'last_page'    => $page->lastPage(),
        ]);
    }

    /* ══════════════════════════════════════════════════════════
       FORM OPTIONS — for the create screen
    ══════════════════════════════════════════════════════════ */

    public function formOptions(Request $request): JsonResponse
    {
        $openPOs = PurchaseOrder::with(['vendor', 'financeVendor', 'items.item'])
            ->whereIn('status', ['ordered', 'partially_received', 'completed'])
            ->where('invoice_status', '!=', 'fully_invoiced')
            ->orderByDesc('order_date')
            ->get()
            ->map(function (PurchaseOrder $po) {
                return [
                    'id'           => $po->id,
                    'order_no'     => $po->order_no,
                    'vendor_name'  => $po->resolveFinanceVendor()?->vendor_name ?? $po->vendor?->vendor_name,
                    'total_amount' => (float) $po->total_amount,
                    'gst_amount'   => (float) $po->gst_amount,
                    'finance_vendor_id' => $po->finance_vendor_id ?? $po->resolveFinanceVendor()?->id,
                    'items'        => $po->items->map(fn ($line) => [
                        'inventory_item_id' => $line->inventory_item_id,
                        'product_name'      => $line->item?->product_name,
                        'qty_ordered'       => (float) $line->qty_ordered,
                        'qty_received'      => (float) $line->qty_received,
                        'unit_price'        => (float) $line->unit_price,
                        'gst_rate'          => (float) $line->gst_rate,
                    ])->values(),
                ];
            })
            ->values();

        $vendors = FinanceVendor::where('is_active', true)
            ->orderBy('vendor_name')
            ->get(['id', 'vendor_name'])
            ->map(fn (FinanceVendor $v) => ['id' => $v->id, 'vendor_name' => $v->vendor_name])
            ->values();

        return $this->success([
            'open_pos'          => $openPOs,
            'vendors'           => $vendors,
            'preselected_po_id' => $request->po_id ? (int) $request->po_id : null,
        ]);
    }

    /* ══════════════════════════════════════════════════════════
       STORE — creates invoice + AP entry automatically
       (exact same side effects as web store())
    ══════════════════════════════════════════════════════════ */

    public function store(Request $request, VendorInvoiceService $service): JsonResponse
    {
        $data = $request->validate(VendorInvoiceService::rules());

        // Shared brain — identical side effects to web store(), including the
        // DOUBLE-AP GUARD (previously missing on this path: a mobile vendor
        // invoice against an already-received PO double-booked the payable).
        $invoice = $service->create($data, $request->user(), $request->file('bill_attachment'));

        return $this->success(
            $this->mapSummary($invoice->load(['purchaseOrder', 'financeVendor', 'inventoryVendor'])),
            'Invoice saved and AP entry created.',
            201
        );
    }

    /* ══════════════════════════════════════════════════════════
       SHOW — full detail
    ══════════════════════════════════════════════════════════ */

    public function show(VendorInvoice $vendorInvoice): JsonResponse
    {
        $vendorInvoice->load(['purchaseOrder.vendor', 'financeVendor', 'items.item', 'financeExpense', 'createdBy']);

        $po = $vendorInvoice->purchaseOrder;

        return $this->success([
            'id'               => $vendorInvoice->id,
            'invoice_ref'      => $vendorInvoice->invoice_ref,
            'invoice_number'   => $vendorInvoice->invoice_number,
            'invoice_date'     => optional($vendorInvoice->invoice_date)->toDateString(),
            'due_date'         => optional($vendorInvoice->due_date)->toDateString(),
            'payment_terms'    => $vendorInvoice->payment_terms,
            'invoice_amount'   => (float) $vendorInvoice->invoice_amount,
            'gst_amount'       => (float) $vendorInvoice->gst_amount,
            'total_amount'     => (float) $vendorInvoice->total_amount,
            'notes'            => $vendorInvoice->notes,
            'status'           => $vendorInvoice->status,
            'status_label'     => $vendorInvoice->getStatusLabel(),
            'po' => $po ? [
                'id'           => $po->id,
                'order_no'     => $po->order_no,
                'status'       => $po->status,
                'total_amount' => (float) $po->total_amount,
            ] : null,
            'vendor_name'      => $vendorInvoice->financeVendor?->vendor_name,
            'items' => $vendorInvoice->items->map(fn ($line) => [
                'inventory_item_id' => $line->inventory_item_id,
                'product_name'      => $line->item?->product_name,
                'description'       => $line->description,
                'qty'               => (float) $line->qty,
                'unit_price'        => (float) $line->unit_price,
                'gst_rate'          => (float) $line->gst_rate,
                'total_price'       => (float) $line->total_price,
            ])->values(),
            'finance_expense_status' => $vendorInvoice->financeExpense?->payment_status,
            'has_attachment'   => (bool) $vendorInvoice->bill_attachment,
            'attachment_url'   => $vendorInvoice->bill_attachment ? Storage::url($vendorInvoice->bill_attachment) : null,
            'created_by'       => $vendorInvoice->createdBy?->name,
            'created_at'       => optional($vendorInvoice->created_at)->toIso8601String(),
        ]);
    }

    /* ══════════════════════════════════════════════════════════
       DESTROY — reverses AP entry, same guard as web
    ══════════════════════════════════════════════════════════ */

    public function destroy(VendorInvoice $vendorInvoice, VendorInvoiceService $service): JsonResponse
    {
        try {
            $service->cancel($vendorInvoice);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), [], 422);
        }

        return $this->success(null, 'Invoice cancelled and Finance AP entry reversed.');
    }

    /* ══════════════════════════════════════════════════════════
       Helpers
    ══════════════════════════════════════════════════════════ */

    private function mapSummary(VendorInvoice $v): array
    {
        return [
            'id'              => $v->id,
            'invoice_ref'     => $v->invoice_ref,
            'invoice_number'  => $v->invoice_number,
            'invoice_date'    => optional($v->invoice_date)->toDateString(),
            'due_date'        => optional($v->due_date)->toDateString(),
            'po_id'           => $v->purchase_order_id,
            'po_order_no'     => $v->purchaseOrder?->order_no,
            'vendor_name'     => $v->financeVendor?->vendor_name,
            'invoice_amount'  => (float) $v->invoice_amount,
            'gst_amount'      => (float) $v->gst_amount,
            'total_amount'    => (float) $v->total_amount,
            'status'          => $v->status,
            'status_label'    => $v->getStatusLabel(),
            'has_attachment'  => (bool) $v->bill_attachment,
        ];
    }
}
