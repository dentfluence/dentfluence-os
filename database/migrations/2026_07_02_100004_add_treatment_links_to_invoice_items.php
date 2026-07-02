<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chunk 1 — Billing Workflow Update
 * Links each invoice line back to (a) the Treatment master, so the invoice is a
 * true reflection of the single source of truth, and (b) the treatment-plan item
 * it was generated from, so partial multi-tooth billing can update plan progress.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('treatment_id')->nullable()->after('invoice_id')
                  ->constrained('treatments')->nullOnDelete();

            $table->foreignId('treatment_plan_item_id')->nullable()->after('treatment_id')
                  ->constrained('treatment_plan_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('treatment_id');
            $table->dropConstrainedForeignId('treatment_plan_item_id');
        });
    }
};
