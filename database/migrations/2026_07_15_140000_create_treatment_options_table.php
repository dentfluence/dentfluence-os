<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Case Acceptance Engine — PREREQUISITE (Milestone 1).
 * See docs/plan-case-acceptance-engine.md §4.1 +
 *     docs/plan-case-acceptance-engine-implementation.md (Milestone 1).
 *
 * Purely additive: new table only, no existing table touched.
 *
 * Today a `treatment` carries a single/range price (default_price / min /
 * max). It cannot express "this implant system costs X, that crown material
 * costs Y" as a reusable, priced catalog. `treatment_options` adds that
 * structured, priced catalog — OWNED BY THE TREATMENT MODULE (the single
 * source of truth for money). The Case Acceptance Engine only ever reads
 * these via TreatmentPricingService / the pricing API; it never writes prices
 * and never caches them.
 *
 * Shape aligns with the ad-hoc `treatment_plan_items.material_variants` JSON
 * (`[{label, price, selected}]`) so the two can converge later: `name` == label,
 * `price` == price, `is_default` == selected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_options', function (Blueprint $table) {
            $table->id();

            $table->foreignId('treatment_id')
                  ->constrained('treatments')
                  ->cascadeOnDelete();

            // Which decision a patient makes at this option (implant_system,
            // crown_material, addon, …). Kept as a string (not a hard enum) so
            // new option families can be added without a migration — the
            // Treatment Module owns this vocabulary. Maps 1:1 onto a decision
            // tree node's `treatment_option_group` in the engine.
            $table->string('group', 50);

            // Patient-facing choice label, e.g. "Straumann BLX", "Zirconia".
            $table->string('name', 150);

            // The price for THIS option. Decimal:2, same as treatments.*_price.
            $table->decimal('price', 10, 2)->default(0);

            // Pre-selected default for its group (mirrors material_variants
            // `selected`). At most one default per (treatment, group) is the
            // intended convention; enforced in the app, not the DB.
            $table->boolean('is_default')->default(false);

            $table->boolean('is_active')->default(true);

            // Display order within a group.
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            // One row per (treatment, group, name) — re-pricing edits the
            // existing row instead of creating a duplicate (idempotent seeds).
            $table->unique(['treatment_id', 'group', 'name'], 'treatment_options_unique');

            // The engine's hot read path: options for a treatment, by group,
            // active only.
            $table->index(['treatment_id', 'group', 'is_active'], 'treatment_options_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_options');
    }
};
