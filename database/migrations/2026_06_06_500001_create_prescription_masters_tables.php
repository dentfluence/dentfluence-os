<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prescription Masters — all lookup/master tables in one migration.
 * Tables: rx_drug_categories, rx_generics, rx_routes_of_admin,
 *         rx_food_instructions, rx_dose_templates, rx_duration_templates
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Drug Categories ──────────────────────────────────────────────────
        Schema::create('rx_drug_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Generic Names ────────────────────────────────────────────────────
        Schema::create('rx_generics', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('drug_class')->nullable();  // antibiotic, NSAID, etc.
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Routes of Administration ─────────────────────────────────────────
        Schema::create('rx_routes_of_admin', function (Blueprint $table) {
            $table->id();
            $table->string('name');          // Oral, Topical, IM, IV, etc.
            $table->string('abbreviation')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Food Instructions ────────────────────────────────────────────────
        Schema::create('rx_food_instructions', function (Blueprint $table) {
            $table->id();
            $table->string('code');          // BEFORE_FOOD, AFTER_FOOD, etc.
            $table->string('label');         // Before Food
            $table->string('label_mr')->nullable(); // Marathi
            $table->string('label_hi')->nullable(); // Hindi
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Dose Templates ───────────────────────────────────────────────────
        Schema::create('rx_dose_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');           // BD, TDS, OD, SOS, etc.
            $table->string('abbreviation');
            $table->tinyInteger('morning')->default(0);
            $table->tinyInteger('afternoon')->default(0);
            $table->tinyInteger('night')->default(0);
            $table->boolean('is_sos')->default(false);
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Duration Templates ───────────────────────────────────────────────
        Schema::create('rx_duration_templates', function (Blueprint $table) {
            $table->id();
            $table->string('label');          // 3 Days, 5 Days, 1 Week, etc.
            $table->integer('value');
            $table->enum('unit', ['days', 'weeks', 'months'])->default('days');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rx_duration_templates');
        Schema::dropIfExists('rx_dose_templates');
        Schema::dropIfExists('rx_food_instructions');
        Schema::dropIfExists('rx_routes_of_admin');
        Schema::dropIfExists('rx_generics');
        Schema::dropIfExists('rx_drug_categories');
    }
};
