<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab Prescription Templates — reusable clinical presets.
 *
 * Examples: "Posterior Zirconia", "Anterior Layered E-max", "All-on-X Hybrid"
 * Doctors apply a template and everything fills automatically.
 * Must be created BEFORE lab_case_prescriptions (template_id FK).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_prescription_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->string('name', 100);                        // "Posterior Zirconia"
            $table->string('category', 100)->nullable();        // must match LabCase::WORK_CATEGORIES key
            $table->string('subtype', 100)->nullable();         // optional narrower match

            // Pre-filled values
            $table->string('material', 100)->nullable();
            $table->string('shade', 30)->nullable();
            $table->json('clinical_fields')->nullable();        // same schema as prescription

            $table->text('notes')->nullable();                  // template description / usage hints
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_prescription_templates');
    }
};
