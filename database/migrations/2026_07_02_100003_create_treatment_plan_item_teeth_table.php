<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chunk 1 — Billing Workflow Update
 * One row per TOOTH per treatment-plan item. This is the "Invoice Line Tooth
 * Allocation" + "Treatment Plan Item Progress" backbone: it lets a doctor
 * invoice only tooth 24 today and leave tooth 36 pending until the next visit.
 *
 * Each tooth carries its own status and, once billed, points at the invoice item
 * that billed it. Population/back-fill of existing plans happens in Chunk 5 —
 * this migration only creates the table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_plan_item_teeth', function (Blueprint $table) {
            $table->id();

            $table->foreignId('treatment_plan_item_id')
                  ->constrained('treatment_plan_items')->cascadeOnDelete();

            // FDI tooth code, e.g. "24". Nullable for non-tooth-specific work
            // (e.g. full-mouth scaling) so those items still get one row.
            $table->string('tooth_number', 10)->nullable();

            // pending | completed | invoiced
            $table->string('status', 20)->default('pending');

            // The invoice line that billed this tooth (null until invoiced)
            $table->foreignId('invoice_item_id')->nullable()
                  ->constrained('invoice_items')->nullOnDelete();

            $table->timestamp('invoiced_at')->nullable();

            $table->timestamps();

            $table->index(['treatment_plan_item_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_plan_item_teeth');
    }
};
