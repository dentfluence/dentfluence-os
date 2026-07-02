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
        if ($item->teeth()->exists()) {
            return;
        }

        $teeth = collect(explode(',', (string) $item->tooth_number))
            ->map(fn ($t) => trim($t))
            ->filter()
            ->values();

        if ($teeth->isEmpty()) {
            $count = max((int) $item->units, 1);
            for ($i = 0; $i < $count; $i++) {
                $item->teeth()->create([
                    'tooth_number' => null,
                    'status'       => TreatmentPlanItemTooth::STATUS_PENDING,
                ]);
            }
            return;
        }

        foreach ($teeth as $tooth) {
            $item->teeth()->create([
                'tooth_number' => $tooth,
                'status'       => TreatmentPlanItemTooth::STATUS_PENDING,
            ]);
        }
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
            $teeth = TreatmentPlanItemTooth::whereIn('id', $toothIds)
                ->where('status', TreatmentPlanItemTooth::STATUS_PENDING)
                ->whereHas('planItem', fn ($q) => $q->where('treatment_plan_id', $plan->id))
                ->with('planItem')
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
                foreach ($group as $tooth) {
                    $tooth->update([
                        'status'          => TreatmentPlanItemTooth::STATUS_INVOICED,
                        'invoice_item_id' => $line->id,
                        'invoiced_at'     => now(),
                    ]);
                }

                $this->refreshItemProgress($item);
            }

            $invoice->recalculate();

            // Close the plan only once EVERY item is fully invoiced.
            $plan->load('items');
            if ($plan->items->isNotEmpty()
                && $plan->items->every(fn ($it) => $it->billing_progress === TreatmentPlanItem::PROGRESS_INVOICED)
                && $plan->status !== 'completed') {
                $plan->update(['status' => 'completed']);
            }

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
