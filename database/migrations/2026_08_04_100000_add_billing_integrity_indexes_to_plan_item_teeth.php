<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S1 (CEO Directive #006) — database backstop for plan billing integrity.
 *
 * PURELY ADDITIVE. Creates one unique index on treatment_plan_item_teeth.
 * No column is added, dropped or retyped; no row is written, moved or deleted.
 *
 * UNIQUE (treatment_plan_item_id, tooth_number)
 *   ensureTeeth() is reached from GET handlers, so two concurrent requests
 *   could both find zero teeth and both insert a full set — doubling the
 *   denominator in refreshItemProgress() so the item could never reach
 *   'invoiced' and the plan could never close. The application fix
 *   (transaction + row lock + firstOrCreate) closes the window; this index
 *   makes the invariant impossible to violate at all.
 *
 *   NULL tooth_number is intentionally NOT constrained: a non-tooth item gets
 *   one generic row per unit, and both MySQL and SQLite treat each NULL as
 *   distinct in a unique index, so those rows coexist correctly.
 *
 * DELIBERATELY NOT ADDED — unique(invoice_item_id).
 *   The S1 plan proposed it; reading createInvoiceFromSelection() shows it
 *   would be wrong. Teeth are grouped by plan item into ONE invoice line with
 *   qty = tooth count, so several teeth legitimately share an invoice_item_id.
 *   A unique index there would break every multi-tooth invoice. The duplicate
 *   protection billing actually needs is the conditional status flip in the
 *   service, which is what S1 implements.
 *
 * PRE-FLIGHT REQUIRED. Run `php artisan billing:integrity-check` on the target
 * database FIRST. If duplicates exist this migration aborts with a
 * RuntimeException naming them rather than silently deduplicating — deciding
 * which of two conflicting clinical rows to keep is a human judgement, never a
 * migration's.
 */
return new class extends Migration
{
    private const INDEX = 'tpit_item_tooth_unique';

    public function up(): void
    {
        $this->guardAgainstExistingDuplicates();

        if ($this->hasIndex()) {
            return;
        }

        Schema::table('treatment_plan_item_teeth', function (Blueprint $table) {
            $table->unique(['treatment_plan_item_id', 'tooth_number'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! $this->hasIndex()) {
            return;
        }

        Schema::table('treatment_plan_item_teeth', function (Blueprint $table) {
            $table->dropUnique(self::INDEX);
        });
    }

    /**
     * Refuse to run against data the index would reject. Report and stop —
     * never auto-fix.
     */
    private function guardAgainstExistingDuplicates(): void
    {
        $duplicates = DB::table('treatment_plan_item_teeth')
            ->select('treatment_plan_item_id', 'tooth_number', DB::raw('COUNT(*) as row_count'))
            ->whereNotNull('tooth_number')
            ->groupBy('treatment_plan_item_id', 'tooth_number')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isEmpty()) {
            return;
        }

        throw new RuntimeException(
            'Cannot add ' . self::INDEX . ': ' . $duplicates->count()
            . ' duplicate (plan item, tooth) pair(s) already exist. '
            . 'Run `php artisan billing:integrity-check` for the full list and resolve them manually first. '
            . 'First offender: plan item #' . $duplicates->first()->treatment_plan_item_id
            . ', tooth ' . $duplicates->first()->tooth_number . '.'
        );
    }

    private function hasIndex(): bool
    {
        return Schema::hasIndex('treatment_plan_item_teeth', self::INDEX);
    }
};
