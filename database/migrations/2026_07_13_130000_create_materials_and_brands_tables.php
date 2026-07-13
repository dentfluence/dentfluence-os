<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — Material/Brand as real models (see
 * docs/gap-analysis-treatment-planning-knowledge-bank.md). Explicitly kept
 * minimal per the doc's own guidance ("don't build speculatively"): two
 * simple name-only master tables, following the exact same shape as the
 * existing Complaint/Diagnosis/Investigation masters — no premature
 * category/pricing/inventory-linkage fields until a real need shows up.
 *
 * Purely additive: new tables + nullable FK columns only. The existing
 * `material_variants` JSON field on treatment_plan_items (free-text
 * label/price comparison, patient picks one) is untouched and keeps
 * working exactly as before — these FKs are an optional, separate way to
 * tag an item's FINAL chosen material/brand once decided, not a
 * replacement for the variants comparison UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            // Optional — a brand can be scoped to a material (e.g. "Ivoclar"
            // under "Zirconia") or left unscoped (e.g. "Nobel Biocare" for
            // implants, where "material" isn't the natural grouping).
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('treatment_plan_items', function (Blueprint $table) {
            $table->foreignId('material_id')->nullable()->after('material_variants')
                  ->constrained('materials')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->after('material_id')
                  ->constrained('brands')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('treatment_plan_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
            $table->dropConstrainedForeignId('material_id');
        });

        Schema::dropIfExists('brands');
        Schema::dropIfExists('materials');
    }
};
