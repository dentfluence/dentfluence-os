<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 · Slice 1 — Insights Engine (Health / LTV / Risk).
 *
 * One additive table for all Insight signals, discriminated by `signal`
 * (health | ltv | risk). This mirrors the Task Engine's "one store, several
 * classes" pattern already used elsewhere in this codebase (tasks.class):
 * one place to look, independent calculators own their own rows.
 *
 * Purely additive and derived — safe to truncate/rebuild at any time (it is
 * a view of live data, never a source of truth). No existing table touched.
 * Nothing reads this table yet in Slice 1 — it is populated shadow-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insight_signals', function (Blueprint $table) {
            $table->id();

            // Which Master Relationship this signal belongs to.
            $table->unsignedBigInteger('relationship_id')->index();

            // Which signal this row is: health | ltv | risk (more may be added later,
            // each as a new calculator — never a rewrite of this table).
            $table->string('signal', 20)->index();

            // Generic 0–100 score (health, risk). Null for signals that don't use it.
            $table->decimal('score', 5, 2)->nullable();

            // Human label for the score (e.g. health: warming/steady/cooling;
            // risk: low/medium/high/critical). Null where not applicable.
            $table->string('level', 20)->nullable();

            // LTV-only fields. Null for health/risk rows.
            $table->decimal('value_realized', 12, 2)->nullable();
            $table->decimal('value_projected', 12, 2)->nullable();

            // Explainable breakdown of the factors that produced this row — the
            // Insights equivalent of the Decision Log's "why". Never used to decide
            // anything; purely for transparency/debugging/future AI consumption.
            $table->json('factors')->nullable();

            // When THIS row was computed, and the shared stamp for the rebuild run
            // that produced it (handy for staleness checks, same idea as
            // today_actions.generated_at).
            $table->timestamp('computed_at')->nullable();
            $table->timestamp('generated_at')->nullable();

            $table->timestamps();

            $table->unique(['relationship_id', 'signal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insight_signals');
    }
};
