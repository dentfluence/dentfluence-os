<?php

namespace App\Console\Commands;

use App\Models\TreatmentPlanItem;
use App\Models\TreatmentPlanItemTooth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * billing:integrity-check — READ-ONLY audit of treatment-plan billing state.
 *
 * S1 (CEO Directive #006). Two jobs:
 *
 *  1. PRE-FLIGHT for 2026_08_04_100000_add_billing_integrity_indexes...:
 *     that migration refuses to run if duplicate (item, tooth) rows exist.
 *     Check 1 below tells you whether it will pass before you attempt it.
 *
 *  2. DAMAGE REPORT for the stranding bug S1 fixes. Before this slice, every
 *     invoice cancellation, deletion or line-edit left the teeth it had billed
 *     stuck on 'invoiced' with no live invoice behind them — permanently
 *     unbillable through the UI — and left plans closed on voided invoices.
 *     Checks 2–4 quantify how much of that already exists in a given database.
 *
 * THIS COMMAND NEVER WRITES. It has no --fix flag by design: every finding is
 * a clinical/financial judgement (re-bill? write off? was the cancellation
 * itself the mistake?) that a human must make with the patient's chart open.
 *
 * Exit 0 = clean. Exit 1 = findings exist (safe to run in CI).
 */
class BillingIntegrityCheck extends Command
{
    protected $signature = 'billing:integrity-check {--limit=25 : Max example rows to print per finding}';

    protected $description = 'Read-only audit of treatment-plan billing integrity (duplicate teeth, stranded invoiced teeth, progress drift, stale plan completion).';

    public function handle(): int
    {
        $limit  = max(1, (int) $this->option('limit'));
        $issues = 0;

        $this->info('Billing integrity check — READ ONLY. Nothing will be modified.');
        $this->newLine();

        $issues += $this->checkDuplicateTeeth($limit);
        $issues += $this->checkStrandedInvoicedTeeth($limit);
        $issues += $this->checkItemProgressDrift($limit);
        $issues += $this->checkStalePlanCompletion($limit);

        $this->newLine();

        if ($issues === 0) {
            $this->info('CLEAN — no billing integrity issues found.');
            return self::SUCCESS;
        }

        $this->warn($issues . ' finding group(s) reported above. Nothing was changed.');
        $this->line('Resolve manually before running the billing integrity index migration.');

        return self::FAILURE;
    }

    /**
     * 1. Duplicate (plan item, tooth) rows.
     *
     * Blocks the unique index. Caused by the pre-S1 unguarded check-then-create
     * in ensureTeeth() being reachable from GET handlers.
     */
    private function checkDuplicateTeeth(int $limit): int
    {
        $rows = DB::table('treatment_plan_item_teeth')
            ->select('treatment_plan_item_id', 'tooth_number', DB::raw('COUNT(*) as row_count'))
            ->whereNotNull('tooth_number')
            ->groupBy('treatment_plan_item_id', 'tooth_number')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('row_count')
            ->get();

        if ($rows->isEmpty()) {
            $this->line('  [1/4] Duplicate (plan item, tooth) rows ......... none');
            return 0;
        }

        $this->error('  [1/4] Duplicate (plan item, tooth) rows ......... ' . $rows->count() . ' — BLOCKS THE INDEX MIGRATION');
        $this->table(
            ['plan_item_id', 'tooth', 'rows'],
            $rows->take($limit)->map(fn ($r) => [
                $r->treatment_plan_item_id,
                $r->tooth_number,
                $r->row_count,
            ])->all()
        );
        $this->line('        → Keep the row carrying the invoice link (if any); delete the surplus pending duplicates.');

        return 1;
    }

    /**
     * 2. Teeth marked 'invoiced' with no live invoice behind them.
     *
     * The core stranding bug: the invoice line is gone (hard-deleted by an
     * invoice edit) or its invoice is cancelled/trashed, but the tooth still
     * claims to be invoiced, so it can never be selected for billing again.
     */
    private function checkStrandedInvoicedTeeth(int $limit): int
    {
        $rows = DB::table('treatment_plan_item_teeth as t')
            ->leftJoin('invoice_items as ii', 'ii.id', '=', 't.invoice_item_id')
            ->leftJoin('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->where('t.status', TreatmentPlanItemTooth::STATUS_INVOICED)
            ->where(function ($q) {
                $q->whereNull('t.invoice_item_id')      // link nulled by FK on line delete
                  ->orWhereNull('ii.id')                // line hard-deleted
                  ->orWhereNull('i.id')                 // invoice row gone
                  ->orWhereNotNull('i.deleted_at')      // invoice soft-deleted / trashed
                  ->orWhere('i.status', 'cancelled');   // invoice cancelled in place
            })
            ->select(
                't.id',
                't.treatment_plan_item_id',
                't.tooth_number',
                't.invoice_item_id',
                'i.invoice_number',
                'i.status as invoice_status',
                'i.deleted_at as invoice_deleted_at'
            )
            ->orderBy('t.id')
            ->get();

        if ($rows->isEmpty()) {
            $this->line('  [2/4] Stranded invoiced teeth .................. none');
            return 0;
        }

        $this->error('  [2/4] Stranded invoiced teeth .................. ' . $rows->count() . ' — these are UNBILLABLE today');
        $this->table(
            ['tooth_row', 'plan_item', 'tooth', 'invoice_item', 'invoice', 'inv_status', 'inv_deleted'],
            $rows->take($limit)->map(fn ($r) => [
                $r->id,
                $r->treatment_plan_item_id,
                $r->tooth_number ?? '(generic)',
                $r->invoice_item_id ?? '—',
                $r->invoice_number ?? '—',
                $r->invoice_status ?? '—',
                $r->invoice_deleted_at ? 'yes' : 'no',
            ])->all()
        );
        $this->line('        → These pre-date S1. Releasing them makes the work billable again; confirm per patient before doing so.');

        return 1;
    }

    /**
     * 3. Items whose invoiced_units / billing_progress disagree with their teeth.
     *
     * Drift left behind by writes that bypassed refreshItemProgress().
     */
    private function checkItemProgressDrift(int $limit): int
    {
        $rows = DB::table('treatment_plan_items as pi')
            ->join('treatment_plan_item_teeth as t', 't.treatment_plan_item_id', '=', 'pi.id')
            ->whereNull('pi.deleted_at')
            ->groupBy('pi.id', 'pi.treatment_plan_id', 'pi.treatment_name', 'pi.invoiced_units', 'pi.billing_progress')
            ->select(
                'pi.id',
                'pi.treatment_plan_id',
                'pi.treatment_name',
                'pi.invoiced_units',
                'pi.billing_progress',
                DB::raw('COUNT(t.id) as tooth_total'),
                DB::raw("SUM(CASE WHEN t.status = 'invoiced' THEN 1 ELSE 0 END) as tooth_invoiced")
            )
            ->get()
            ->filter(function ($r) {
                $expectedProgress = match (true) {
                    (int) $r->tooth_invoiced === 0                      => TreatmentPlanItem::PROGRESS_PENDING,
                    (int) $r->tooth_invoiced >= (int) $r->tooth_total   => TreatmentPlanItem::PROGRESS_INVOICED,
                    default                                             => TreatmentPlanItem::PROGRESS_PARTIAL,
                };

                return (int) $r->invoiced_units !== (int) $r->tooth_invoiced
                    || $r->billing_progress !== $expectedProgress;
            })
            ->values();

        if ($rows->isEmpty()) {
            $this->line('  [3/4] Item billing-progress drift ............... none');
            return 0;
        }

        $this->error('  [3/4] Item billing-progress drift ............... ' . $rows->count());
        $this->table(
            ['plan_item', 'plan', 'treatment', 'stored_units', 'actual_invoiced', 'teeth', 'stored_progress'],
            $rows->take($limit)->map(fn ($r) => [
                $r->id,
                $r->treatment_plan_id,
                mb_strimwidth((string) $r->treatment_name, 0, 28, '…'),
                $r->invoiced_units,
                $r->tooth_invoiced,
                $r->tooth_total,
                $r->billing_progress,
            ])->all()
        );
        $this->line('        → Recomputable with TreatmentPlanBillingService::refreshItemProgress(); not done here (read-only).');

        return 1;
    }

    /**
     * 4. Plans sitting on status='completed' whose items are not fully invoiced.
     *
     * Either billing closed the plan and the invoice was later voided, or the
     * visit checkbox closed it (the second writer S2 addresses). Reported here
     * because the number is visible on reports today.
     */
    private function checkStalePlanCompletion(int $limit): int
    {
        $rows = DB::table('treatment_plans as p')
            ->join('treatment_plan_items as pi', 'pi.treatment_plan_id', '=', 'p.id')
            ->whereNull('p.deleted_at')
            ->whereNull('pi.deleted_at')
            ->where('p.status', 'completed')
            ->groupBy('p.id', 'p.plan_name', 'p.patient_id', 'p.accepted_at')
            ->havingRaw("SUM(CASE WHEN pi.billing_progress = 'invoiced' THEN 0 ELSE 1 END) > 0")
            ->select(
                'p.id',
                'p.plan_name',
                'p.patient_id',
                'p.accepted_at',
                DB::raw('COUNT(pi.id) as item_total'),
                DB::raw("SUM(CASE WHEN pi.billing_progress = 'invoiced' THEN 1 ELSE 0 END) as item_invoiced")
            )
            ->get();

        if ($rows->isEmpty()) {
            $this->line('  [4/4] Plans completed but not fully invoiced .... none');
            return 0;
        }

        $this->warn('  [4/4] Plans completed but not fully invoiced .... ' . $rows->count());
        $this->table(
            ['plan', 'patient', 'name', 'items_invoiced', 'items_total', 'accepted'],
            $rows->take($limit)->map(fn ($r) => [
                $r->id,
                $r->patient_id,
                mb_strimwidth((string) $r->plan_name, 0, 28, '…'),
                $r->item_invoiced,
                $r->item_total,
                $r->accepted_at ? 'yes' : 'no',
            ])->all()
        );
        $this->line('        → Expected for visit-checkbox completions (S2 scope). Unexpected for billing-closed plans whose invoice was voided.');

        return 1;
    }
}
