<?php

namespace App\Services\Procurement;

use App\Models\Finance\FinanceExpense;
use App\Models\Finance\FinanceExpenseCategory;
use App\Models\Finance\FinanceVendor;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Procurement\GoodsReceiptNote;
use App\Models\Procurement\VendorInvoice;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * VendorInvoiceService — the single brain for vendor-invoice creation and
 * cancellation. Extracted 2026-07-14 from the (previously duplicated)
 * web VendorInvoiceController::store()/destroy() and
 * Api\V1\VendorInvoiceController::store()/destroy().
 *
 * Why this exists: the two controllers had drifted — the web copy gained the
 * DOUBLE-AP GUARD on 2026-07-14 while the API copy kept creating a second
 * unpaid FinanceExpense for POs whose GRN had already booked one, silently
 * double-counting payables. One brain, one guard, both channels.
 *
 * Side effects (identical for web and mobile):
 *   1. VendorInvoice + line items created
 *   2. FinanceExpense (unpaid vendor bill / AP) created — or the existing
 *      GRN-sourced bill for the same PO is taken over (double-AP guard)
 *   3. PO invoice_status + invoiced_amount recalculated
 *   4. FinanceVendor.outstanding_amount adjusted by the DELTA
 */
class VendorInvoiceService
{
    /**
     * Shared validation rules — both controllers must validate with these.
     */
    public static function rules(): array
    {
        return [
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
            'items'               => 'nullable|array',
            'items.*.inventory_item_id' => 'nullable|exists:inventory_items,id',
            'items.*.description'       => 'nullable|string|max:200',
            'items.*.qty'               => 'nullable|numeric|min:0.01',
            'items.*.unit_price'        => 'nullable|numeric|min:0',
            'items.*.gst_rate'          => 'nullable|numeric|min:0|max:100',
        ];
    }

    /**
     * Create a vendor invoice + the full Finance/PO side-effect chain.
     *
     * @param array         $data       validated via self::rules()
     * @param User          $user       acting user (created_by)
     * @param UploadedFile|null $attachment optional bill attachment
     */
    public function create(array $data, User $user, ?UploadedFile $attachment = null): VendorInvoice
    {
        $po          = PurchaseOrder::findOrFail($data['purchase_order_id']);
        $gstAmount   = (float) ($data['gst_amount'] ?? 0);
        $totalAmount = (float) $data['invoice_amount'] + $gstAmount;

        $attachmentPath = $attachment?->store('vendor-invoices', 'public');

        // Resolve finance_vendor_id — from form if provided, else from PO.
        $financeVendorId = $data['finance_vendor_id']
            ?? $po->finance_vendor_id
            ?? $po->resolveFinanceVendor()?->id;

        return DB::transaction(function () use (
            $data, $po, $gstAmount, $totalAmount, $attachmentPath, $financeVendorId, $user
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
                'created_by'          => $user->id,
            ]);

            // ── 2. Save line items (if provided) ─────────────────────────
            foreach ($data['items'] ?? [] as $line) {
                $lineQty   = (float) ($line['qty'] ?? 1);
                $linePrice = (float) ($line['unit_price'] ?? 0);
                $lineGst   = (float) ($line['gst_rate'] ?? 0);

                $invoice->items()->create([
                    'inventory_item_id' => $line['inventory_item_id'] ?? null,
                    'description'       => $line['description'] ?? null,
                    'qty'               => $lineQty,
                    'unit_price'        => $linePrice,
                    'gst_rate'          => $lineGst,
                    'total_price'       => $lineQty * $linePrice * (1 + $lineGst / 100),
                ]);
            }

            // ── 3. Accounts Payable entry in Finance ─────────────────────
            //    DOUBLE-AP GUARD: receiving goods against a PO already books
            //    an unpaid vendor bill (GRN receipt). Entering the vendor's
            //    actual invoice for the same PO must take over that bill —
            //    re-point it at this invoice with the invoice's authoritative
            //    figures — instead of creating a second one.
            $suppliesCategory = FinanceExpenseCategory::where('name', 'like', '%Suppl%')
                ->orWhere('name', 'like', '%Dental%')
                ->orWhere('name', 'like', '%Inventory%')
                ->orWhere('name', 'like', '%Purchase%')
                ->first();

            $existingGrnExpense = $this->findExistingGrnExpense($po);

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
            // not the full amount, when taking over an existing GRN bill.
            $previousTotal = 0.0;

            if ($existingGrnExpense) {
                $previousTotal = (float) $existingGrnExpense->total_amount;
                $existingGrnExpense->update($expenseFields);
                $expense = $existingGrnExpense;
            } else {
                $expense = FinanceExpense::create($expenseFields + ['created_by' => $user->id]);
            }

            // ── 4. Link the Finance expense back to the invoice ───────────
            $invoice->update(['finance_expense_id' => $expense->id]);

            // ── 5. Update PO invoice_status + running invoiced_amount ─────
            $po->recalculateInvoiceStatus();

            // ── 6. Update Finance vendor outstanding (cached field) ───────
            if ($financeVendorId) {
                $delta = $totalAmount - $previousTotal;
                if (abs($delta) >= 0.01) {
                    FinanceVendor::where('id', $financeVendorId)
                        ->increment('outstanding_amount', $delta);
                }
            }

            return $invoice;
        });
    }

    /**
     * Cancel (soft-delete) a vendor invoice and reverse its Finance side effects.
     *
     * @throws \RuntimeException if the invoice is already paid
     */
    public function cancel(VendorInvoice $vendorInvoice): void
    {
        if ($vendorInvoice->status === 'paid') {
            throw new \RuntimeException('Cannot delete a paid invoice.');
        }

        DB::transaction(function () use ($vendorInvoice) {
            // Reverse the Finance AP entry
            if ($vendorInvoice->financeExpense) {
                $vendorInvoice->financeExpense->delete();
            }

            // Reverse Finance vendor outstanding
            if ($vendorInvoice->finance_vendor_id) {
                FinanceVendor::where('id', $vendorInvoice->finance_vendor_id)
                    ->decrement('outstanding_amount', $vendorInvoice->total_amount);
            }

            $vendorInvoice->delete();

            // Recalculate PO invoice status
            $vendorInvoice->purchaseOrder->recalculateInvoiceStatus();
        });
    }

    /**
     * Find an unpaid GRN-sourced AP expense for this PO, matching every
     * historical source_type form:
     *   - InventoryService (API GRN path) wrote  'PurchaseOrder' / $po->id
     *   - web InventoryController::receivePO wrote GoodsReceiptNote::class / $grn->id
     * Both must be caught or the double-AP guard is fragile per channel.
     */
    private function findExistingGrnExpense(PurchaseOrder $po): ?FinanceExpense
    {
        $grnIds = GoodsReceiptNote::where('purchase_order_id', $po->id)->pluck('id');

        return FinanceExpense::where('payment_status', 'unpaid')
            ->where(function ($q) use ($po, $grnIds) {
                $q->where(fn ($qq) => $qq
                    ->whereIn('source_type', ['PurchaseOrder', PurchaseOrder::class])
                    ->where('source_id', $po->id));

                if ($grnIds->isNotEmpty()) {
                    $q->orWhere(fn ($qq) => $qq
                        ->whereIn('source_type', ['GoodsReceiptNote', GoodsReceiptNote::class])
                        ->whereIn('source_id', $grnIds));
                }
            })
            ->first();
    }
}
