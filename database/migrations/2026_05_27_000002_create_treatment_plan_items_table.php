<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_plan_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('treatment_plan_id')
                  ->constrained('treatment_plans')
                  ->cascadeOnDelete();

            // Which tooth (FDI notation, e.g. "36", "14", "Full Mouth")
            $table->string('tooth_number', 20)->nullable();

            // Treatment name (links to treatments table by name or id)
            $table->string('treatment_name', 150);

            // Pricing
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->unsignedSmallInteger('units')->default(1);
            $table->decimal('disc_pct', 5, 2)->default(0);
            $table->decimal('disc_amount', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2)->default(0);
            $table->decimal('gst_pct', 5, 2)->default(0);
            $table->decimal('gst_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            // AOCP auto-discount flag
            $table->boolean('aocp_applied')->default(false);

            // Doctor's rank for this option
            $table->enum('option_rank', ['best', 'acceptable', 'alternative'])->default('best');

            // Item-level status
            $table->enum('status', ['pending', 'ongoing', 'completed', 'cancelled'])->default('pending');

            // Notes
            $table->text('notes')->nullable();

            // Sort order within the plan
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index('treatment_plan_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_plan_items');
    }
};
