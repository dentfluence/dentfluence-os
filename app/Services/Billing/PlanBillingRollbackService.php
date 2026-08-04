<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\TreatmentPlanItemTooth;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * S1 (CEO Directive #006) — the REVERSE door for plan billing.
 *
 * TreatmentPlanBillingService moves teeth pending → invoiced when an invoice is
 * raised. Until this class existed there was no way back: cancelling, deleting
 * or re-editing an invoice left every tooth it touched stuck on 'invoiced'
 * forever, so the clinic could never re-bill that treatment, and a plan closed
 * by billing stayed 'completed' on top of a voided invoice.
 *
 * CEO decision D2 (2026-08-04): FULL ROLLBACK. Releasing the teeth restores the
 * plan to the exact billing state it held before the invoice was raised.
 *
 * Ownership boundary (unchanged): billing records financial events. This class
 * only un-does the *billing* projection (tooth billing status, invoiced_units,
 * billing_progress) and reverses the completion that billing itself wrote. It
 * never touches accepted_at, presented_at, or the decision ledger.
 */
class PlanBillingRollbackService
{
    public function __construct(
        private readonly TreatmentPlanBillingService $billing,
    ) {
    }

    /**
     * Release every plan tooth billed by this invoice.
     *
     * Safe to call on any invoice — one with no plan linkage is a no-op, so the
     * cancel/delete paths can call it unconditionally.
     */
    public function rollbackInvoice(Invoice $invoice): void
    {
        $itemIds = $invoice->items()->pluck('id')->all();

        if (empty($itemIds)) {
            return;
        }

        $this->releaseByInvoiceItemIds($itemIds);
    }

    /**
     * Release the teeth billed by a specific set of invoice lines.
     *
     * Used by BillingController@update, which wholesale deletes and recreates
     * its lines — without this the old lines' teeth stayed 'invoiced' while
     * their invoice_item_id was nulled by the FK, leaving teeth that claimed to
     * be invoiced with no invoice at all.
     *
     * @param  Collection<int,\App\Models\InvoiceItem>|array<int,\App\Models\InvoiceItem>  $invoiceItems
     */
    public function rollbackInvoiceItems($invoiceItems): void
    {
        $itemIds = collect($invoiceItems)->pluck('id')->filter()->all();

        if (empty($itemIds)) {
            return;
        }

        $this->releaseByInvoiceItemIds($itemIds);
    }

    /**
     * The single release path.
     *
     * Wrapped in its own transaction: most callers already run inside one (the
     * nested call becomes a savepoint), but BillingController@destroy,
     * @cancel and @destroyWithAuth do not, and a half-released plan is worse
     * than none.
     *
     * @param  array<int,int>  $invoiceItemIds
     */
    private function releaseByInvoiceItemIds(array $invoiceItemIds): void
    {
        DB::transaction(function () use ($invoiceItemIds) {
            // Lock the rows we are about to release so a concurrent
            // createInvoiceFromSelection cannot re-bill a tooth mid-rollback.
            $teeth = TreatmentPlanItemTooth::whereIn('invoice_item_id', $invoiceItemIds)
                ->lockForUpdate()
                ->get();

            if ($teeth->isEmpty()) {
                return;
            }

            TreatmentPlanItemTooth::whereIn('id', $teeth->pluck('id'))->update([
                'status'          => TreatmentPlanItemTooth::STATUS_PENDING,
                'invoice_item_id' => null,
                'invoiced_at'     => null,
            ]);

            $planItemIds = $teeth->pluck('treatment_plan_item_id')->unique()->filter();

            foreach (TreatmentPlanItem::whereIn('id', $planItemIds)->get() as $item) {
                $this->billing->refreshItemProgress($item);
            }

            // F1 — BILLING DOES NOT WRITE PLAN LIFECYCLE.
            // An earlier draft of this service reopened a plan whose billing
            // had been undone. Canonical Treatment Lifecycle V1 §10 forbids it:
            // plan lifecycle is derived from clinical facts by the Treatment
            // Plan module, and cancelling an invoice changes no clinical fact.
            // Releasing the billing projection is the whole of billing's job.
        });
    }
}
