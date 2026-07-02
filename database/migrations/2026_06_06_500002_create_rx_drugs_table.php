<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drug Master — comprehensive drug registry with clinical safety fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rx_drugs', function (Blueprint $table) {
            $table->id();

            // ── Identity ──────────────────────────────────────────────────────
            $table->string('drug_code')->unique()->nullable();
            $table->string('brand_name');
            $table->foreignId('generic_id')->nullable()->constrained('rx_generics')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('rx_drug_categories')->nullOnDelete();
            $table->string('strength')->nullable();           // 500mg, 0.1%, etc.
            $table->string('dosage_form')->nullable();        // Tablet, Capsule, Syrup, Gel...
            $table->string('composition')->nullable();        // full ingredient list
            $table->foreignId('route_id')->nullable()->constrained('rx_routes_of_admin')->nullOnDelete();

            // ── Defaults ──────────────────────────────────────────────────────
            $table->string('default_dose')->nullable();
            $table->integer('default_duration')->nullable();
            $table->enum('default_duration_unit', ['days', 'weeks', 'months'])->default('days');
            $table->foreignId('default_food_instruction_id')->nullable()->constrained('rx_food_instructions')->nullOnDelete();
            $table->text('default_instructions')->nullable();

            // ── Safety ────────────────────────────────────────────────────────
            $table->string('max_daily_dose')->nullable();
            $table->string('duplicate_molecule_group')->nullable(); // paracetamol, ibuprofen...
            $table->string('antibiotic_class')->nullable();         // penicillin, cephalosporin...
            $table->boolean('is_controlled')->default(false);
            $table->enum('pregnancy_category', ['A','B','C','D','X','N'])->nullable();
            $table->enum('breastfeeding_safety', ['safe','caution','avoid','unknown'])->nullable();
            $table->enum('pediatric_safety', ['safe','caution','avoid','unknown'])->nullable();
            $table->enum('geriatric_caution', ['normal','caution','avoid'])->nullable();
            $table->string('renal_dose_adjustment')->nullable();
            $table->string('hepatic_dose_adjustment')->nullable();
            $table->text('contraindications')->nullable();
            $table->text('drug_interactions_note')->nullable();

            // ── Dental context ────────────────────────────────────────────────
            $table->text('common_dental_uses')->nullable();
            $table->text('notes')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // ── Search indexes ────────────────────────────────────────────────
            $table->index('brand_name');
            $table->index('duplicate_molecule_group');
            $table->index('antibiotic_class');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rx_drugs');
    }
};
