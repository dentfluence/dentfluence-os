<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CDSS Rules — warning rules, drug-interaction rules, medical condition rules.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Medical Condition Warning Rules ───────────────────────────────────
        // "If patient has condition X and drug Y is prescribed → show alert"
        Schema::create('rx_warning_rules', function (Blueprint $table) {
            $table->id();
            $table->string('condition_keyword');   // diabetes, ckd, gastric ulcer, pregnancy...
            $table->foreignId('drug_id')->nullable()->constrained('rx_drugs')->nullOnDelete();
            $table->string('molecule_group')->nullable(); // or match by molecule group
            $table->string('drug_class')->nullable();     // or match by drug class (NSAIDs...)
            $table->enum('severity', ['info', 'warning', 'critical'])->default('warning');
            $table->text('alert_message');
            $table->string('suggestion')->nullable();
            $table->boolean('blockable')->default(false); // true = requires override reason
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Drug-Drug Interaction Rules ───────────────────────────────────────
        Schema::create('rx_drug_interaction_rules', function (Blueprint $table) {
            $table->id();
            $table->string('drug_a_molecule')->nullable();
            $table->string('drug_a_class')->nullable();
            $table->string('drug_b_molecule')->nullable();
            $table->string('drug_b_class')->nullable();
            $table->enum('severity', ['info', 'warning', 'critical'])->default('warning');
            $table->text('alert_message');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Allergy Rules ─────────────────────────────────────────────────────
        // Maps allergy keywords to molecule groups / antibiotic classes
        Schema::create('rx_allergy_rules', function (Blueprint $table) {
            $table->id();
            $table->string('allergy_keyword');       // penicillin, sulfa, aspirin...
            $table->string('blocks_molecule')->nullable();
            $table->string('blocks_class')->nullable();
            $table->enum('severity', ['warning', 'critical'])->default('critical');
            $table->text('alert_message');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rx_allergy_rules');
        Schema::dropIfExists('rx_drug_interaction_rules');
        Schema::dropIfExists('rx_warning_rules');
    }
};
