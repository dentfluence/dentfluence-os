<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 · Slice 2 — Analytics Engine (incremental aggregate projections).
 *
 * One additive table for every dashboard metric, discriminated by `metric`
 * (growth | conversion | recall_success | avg_ltv | score_distribution |
 * staff_kpis | total_relationships). Same "one store, several classes"
 * pattern already used by `insight_signals` (Slice 1) and `tasks.class`.
 *
 * Purely additive and derived — safe to truncate/rebuild at any time. No
 * existing table touched. Nothing reads this table yet in Slice 2 — the
 * live `/relationship/analytics` dashboard keeps rendering from
 * AnalyticsController's own (now-public) methods until a later cutover.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_snapshots', function (Blueprint $table) {
            $table->id();

            // Which dashboard metric this row is (see AnalyticsProjector::METRICS).
            $table->string('metric', 60)->unique();

            // The metric's value in whatever shape it naturally has (array of
            // months, a {total,converted,rate} triple, a list of user rows…).
            $table->json('value')->nullable();

            // When THIS row was computed, and the shared stamp for the rebuild
            // run that produced it (staleness checks, same idea as
            // today_actions.generated_at / insight_signals.generated_at).
            $table->timestamp('computed_at')->nullable();
            $table->timestamp('generated_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_snapshots');
    }
};
