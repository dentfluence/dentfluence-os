<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab Module v2 — Lab Case Line Items
 *
 * One row per tooth/unit. Populated automatically when teeth are
 * picked on the FDI tooth chart. Allows bridges and complex
 * prosthetic cases under a single lab case, e.g.:
 *   11 | Crown  | Zirconia | A2
 *   12 | Crown  | Zirconia | A2
 *   13 | Pontic | Zirconia | A2
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_case_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_case_id')->constrained()->cascadeOnDelete();

            $table->string('tooth_number', 10)->nullable(); // FDI notation: 11–48 (null = arch-level work, e.g. denture)
            $table->string('work_type');                    // Crown, Pontic, Implant Crown, Veneer...
            $table->string('material')->nullable();         // Zirconia, PFM, Emax...
            $table->string('shade', 20)->nullable();        // A2, B1, BL2...
            $table->string('notes')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['lab_case_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_case_items');
    }
};
