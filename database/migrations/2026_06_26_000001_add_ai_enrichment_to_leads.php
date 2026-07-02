<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRM AI — Phase 0
 * ----------------------------------------------------------------------------
 * Adds the columns the AI lead-enrichment layer writes to. ALL nullable, so
 * this is 100% additive — existing leads and the board keep working unchanged.
 *
 * Filled automatically (by the local AI) when a lead is created. Empty until
 * then, so every read must treat these as "may be null".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // A short, human-glance summary of the enquiry (≈5 words).
            $table->string('ai_summary', 120)->nullable()->after('notes');

            // Treatment the AI inferred from the enquiry (mapped to our list).
            $table->string('ai_treatment_label', 80)->nullable()->after('ai_summary');

            // AI-inferred urgency: low | medium | high.
            $table->string('ai_urgency', 10)->nullable()->after('ai_treatment_label');

            // Rough ₹ value — looked up from config bands by treatment (NOT
            // guessed by the model), so the number is always deterministic.
            $table->decimal('ai_estimated_value', 12, 2)->nullable()->after('ai_urgency');

            // Reserved for multi-clinic branch detection (Phase 4). Unused now.
            $table->string('ai_branch', 100)->nullable()->after('ai_estimated_value');

            // When enrichment last ran. Null = never enriched yet.
            $table->timestamp('ai_enriched_at')->nullable()->after('ai_branch');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'ai_summary',
                'ai_treatment_label',
                'ai_urgency',
                'ai_estimated_value',
                'ai_branch',
                'ai_enriched_at',
            ]);
        });
    }
};
