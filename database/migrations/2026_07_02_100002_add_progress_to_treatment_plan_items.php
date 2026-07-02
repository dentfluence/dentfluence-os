<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chunk 1 — Billing Workflow Update
 * Adds billing-progress tracking to treatment plan items so a multi-tooth item
 * (e.g. Implant on 24 & 36) can be invoiced tooth-by-tooth. Also links the item
 * back to the Treatment master (single source of truth) so the invoice can pull
 * price / GST / lab / category / notes directly.
 *
 * NOTE: The existing `status` column is left untouched (it carries the clinical
 * option/acceptance state). Billing progress lives in its own column so the two
 * concerns never collide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_plan_items', function (Blueprint $table) {
            // Link to Treatment master — single source of truth for pricing/rules
            $table->foreignId('treatment_id')->nullable()->after('treatment_plan_id')
                  ->constrained('treatments')->nullOnDelete();

            // Billing lifecycle: pending -> partially_completed -> completed / invoiced
            $table->string('billing_progress', 30)->default('pending')->after('status');

            // How many of the item's units have already been invoiced
            $table->unsignedSmallInteger('invoiced_units')->default(0)->after('billing_progress');
        });
    }

    public function down(): void
    {
        Schema::table('treatment_plan_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('treatment_id');
            $table->dropColumn(['billing_progress', 'invoiced_units']);
        });
    }
};
