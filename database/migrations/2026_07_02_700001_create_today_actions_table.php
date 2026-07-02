<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 · Workstream E (slice E1) — Today's Actions projection.
 *
 * A pre-computed read model for the Today's Actions page. The architecture
 * forbids "god readers" that query a dozen domains at request time; instead the
 * page reads ONE derived view. This table holds that view: TodayActionsProjector
 * rebuilds it from the (existing) TodayActionsEngine, and the page reads from
 * here behind the `today.projection` flag.
 *
 * Purely additive and derived — it is safe to truncate and rebuild at any time
 * (it is a view of live data, never a source of truth). No existing table is
 * touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('today_actions', function (Blueprint $table) {
            $table->id();

            // Which of the ~12 categories this item belongs to (e.g. lead_followups).
            $table->string('category', 60)->index();

            // Display priority: high | medium | low.
            $table->string('priority', 10)->default('medium');

            // Subject references (any may be null depending on category).
            $table->unsignedBigInteger('patient_id')->nullable()->index();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('relationship_id')->nullable()->index();

            // Denormalised display fields (so the read needs no joins).
            $table->string('patient_name', 191)->default('Unknown');
            $table->string('reason', 500)->nullable();
            $table->string('suggested_action', 500)->nullable();
            $table->string('link', 500)->nullable();

            // Extra context for the drawer.
            $table->json('meta')->nullable();

            // When this projection row was materialised (whole table shares a stamp
            // per rebuild — handy for staleness checks in later slices).
            $table->timestamp('generated_at')->nullable();

            $table->timestamps();

            $table->index(['category', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('today_actions');
    }
};
