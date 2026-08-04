<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\TreatmentPlanItemTooth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Chunk 5 — Partial multi-tooth invoicing from a treatment plan.
 *
 * A plan item like "Implant on 24 & 36" can be billed one tooth at a time. This
 * service (a) lazily populates one tooth-row per tooth (treatment_plan_item_teeth)
 * from the item's comma-separated tooth_number, and (b) creates an invoice for
 * ONLY the teeth the user selected — leaving the rest pending on the plan.
 */
class TreatmentPlanBillingService
{
    /**
     * Ensure a plan item has its per-tooth rows. Idempotent — only creates rows
     * the first time (when none exist). A non-tooth item (no tooth_number) gets
     * max(units,1) generic rows so it can still be billed unit-by-unit.
     */
    public function ensureTeeth(TreatmentPlanItem $item): void
    {
        // S1 — this is reached from GET handlers (billFromPlan, billableTeeth),
        // so a double-click or a browser prefetch used to run the check and the
        // create concurrently: both saw zero rows, both inserted, and the item
        // ended up with double the teeth it has. That inflated the denominator
        // in refreshItemProgress(), so the item could never reach 'invoiced'.
        // The transaction + row lock closes the window; the unique index added
        // in 2026_08_04_100000 is the hard backstop underneath it.
        DB::transaction(function () use ($item) {
            $locked = TreatmentPlanItem::whereKey($item->id)->lockForUpdate()->first();

            if (! $locked || $locked->teeth()->exists()) {
                return;
            }

            $teeth = collect(explode(',', (string) $locked->tooth_number))
                ->map(fn ($t) => trim($t))
                ->filter()
                ->values();

            if ($teeth->isEmpty()) {
                $count = max((int) $locked->units, 1);
                for ($i = 0; $i < $count; $i++) {
                    $locked->teeth()->create([
                        'tooth_number' => null,
                        'status'       => TreatmentPlanItemTooth::STATUS_PENDING,
                    ]);
                }
                return;
            }

            foreach ($teeth as $tooth) {
                $locked->teeth()->firstOrCreate(
                    ['tooth_number' => $tooth],
                    ['status' => TreatmentPlanItemTooth::STATUS_PENDING],
                );
            }
        });
    }

    /** Ensure teeth rows for every item on a plan. */
    public function ensurePlanTeeth(TreatmentPlan $plan): void
    {
        foreach ($plan->items as $item) {
            $this->ensureTeeth($item);
        }
    }

    /**
     * Create an invoice for the selected tooth-rows only.
     *
     * @param  array<int>  $toothIds  treatment_plan_item_teeth IDs the user ticked
     * @throws ValidationException  when nothing valid is selected
     */
    public function createInvoiceFromSelection(TreatmentPlan $plan, array $toothIds, ?int $userId = null): Invoice
    {
        return DB::transaction(function () use ($plan, $toothIds, $userId) {
            // Only PENDING teeth that belong to items on THIS plan.
            //
            // S1 — lockForUpdate is load-bearing, not defensive. Without it the
            // read is a consistent-snapshot read under InnoDB REPEATABLE READ,
            // so two simultaneous submissions of the same selection both saw
            // 'pending', both passed the guard below, and both raised an
            // invoice: the patient was charged twice for the same tooth and one
            // invoice line was left orphaned but fully payable.
            $teeth = TreatmentPlanItemTooth::whereIn('id', $toothIds)
                ->where('status', TreatmentPlanItemTooth::STATUS_PENDING)
                ->whereHas('planItem', fn ($q) => $q->where('treatment_plan_id', $plan->id))
                ->with('planItem')
                ->lockForUpdate()
                ->get();

            if ($teeth->isEmpty()) {
                throw ValidationException::withMessages([
                    'tooth_ids' => 'Select at least one pending tooth to invoice.',
                ]);
            }

            // Create the invoice header, linked back to the plan.
            $invoice = Invoice::create([
                'invoice_number'    => Invoice::nextNumber(),
                'patient_id'        => $plan->patient_id,
                'invoice_date'      => now()->toDateString(),
                'treatment_plan_id' => $plan->id,
                'status'            => 'draft',
                'created_by'        => $userId,
            ]);

            $sort = 0;

            // Group selected teeth by their plan item → one invoice line per item.
            foreach ($teeth->groupBy('treatment_plan_item_id') as $planItemId => $group) {
                /** @var TreatmentPlanItem $item */
                $item = $group->first()->planItem;
                $qty  = $group->count();

                $toothLabel = $group->pluck('tooth_number')->filter()->implode(', ');

                $line = new InvoiceItem([
                    'invoice_id'             => $invoice->id,
                    'treatment_id'           => $item->treatment_id,
                    'treatment_plan_item_id' => $item->id,
                    'description'            => $item->treatment_name,
                    'tooth_number'           => $toothLabel ?: null,
                    'unit_price'             => (float) $item->unit_price,
                    'qty'                    => $qty,
                    'disc_pct'               => 0,
                    'gst_pct'                => (float) $item->gst_pct,
                    'sort_order'             => $sort++,
                ]);
                $line->compute();
                $line->save();

                // Mark the selected teeth as invoiced + link them to this line.
                //
                // S1 — the flip is CONDITIONAL on the row still being pending and
                // asserts it actually moved. Previously this was an update by
                // primary key with no status predicate, so a tooth another
                // transaction had already invoiced was silently re-pointed at
                // this invoice's line. If the assertion fails the whole
                // transaction rolls back: no invoice, nothing partially billed.
                foreach ($group as $tooth) {
                    $flipped = TreatmentPlanItemTooth::whereKey($tooth->id)
                        ->where('status', TreatmentPlanItemTooth::STATUS_PENDING)
                        ->update([
                            'status'          => TreatmentPlanItemTooth::STATUS_INVOICED,
                            'invoice_item_id' => $line->id,
                            'invoiced_at'     => now(),
                        ]);

                    if ($flipped !== 1) {
                        throw ValidationException::withMessages([
                            'tooth_ids' => 'One of the selected teeth was billed by someone else just now. Nothing was invoiced — please reload and try again.',
                        ]);
                    }
                }

                $this->refreshItemProgress($item);
            }

            $invoice->recalculate();

            // F1 — BILLING DOES NOT WRITE PLAN LIFECYCLE.
            // Canonical Treatment Lifecycle V1 §10 and §12: completion is a
            // clinical determination scoped to what the patient accepted, and
            // full invoicing is a financial coincidence, not clinical delivery.
            // This service previously closed the plan here; that write now
            // belongs solely to PlanLifecycleService, driven by clinical facts.

            return $invoice;
        });
    }

    /**
     * Recompute a plan item's billing progress + invoiced_units from its teeth.
     * pending → partially_completed → invoiced (fully billed).
     */
    public function refreshItemProgress(TreatmentPlanItem $item): void
    {
        $total    = $item->teeth()->count();
        $invoiced = $item->teeth()->where('status', TreatmentPlanItemTooth::STATUS_INVOICED)->count();

        $progress = match (true) {
            $invoiced === 0            => TreatmentPlanItem::PROGRESS_PENDING,
            $invoiced >= $total        => TreatmentPlanItem::PROGRESS_INVOICED,
            default                    => TreatmentPlanItem::PROGRESS_PARTIAL,
        };

        $item->update([
            'invoiced_units'   => $invoiced,
            'billing_progress' => $progress,
        ]);
    }
}
