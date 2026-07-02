<?php
// =============================================================================
// F1 — Treatment Visit Items
// What the doctor selected during a visit: procedure + material + tooth number.
// Auto-used by billing_prompts to tell front desk what to invoice.
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_visit_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('treatment_visit_id')
                  ->constrained('treatment_visits')
                  ->cascadeOnDelete();

            $table->foreignId('patient_id')
                  ->constrained('patients')
                  ->cascadeOnDelete();

            // What was done
            $table->string('treatment_name', 150);          // e.g. RCT, Crown
            $table->string('material_option', 100)->nullable(); // e.g. Ceramic, Zirconia
            $table->string('tooth_number', 20)->nullable();  // FDI notation e.g. 26, 14

            // Pricing hint from treatment master (can be overridden on invoice)
            $table->decimal('suggested_price', 10, 2)->default(0);

            // Link back to treatment plan item that was used (optional)
            $table->foreignId('treatment_plan_item_id')
                  ->nullable()
                  ->constrained('treatment_plan_items')
                  ->nullOnDelete();

            // Billing status — has this been invoiced yet?
            $table->enum('billing_status', ['pending', 'invoiced', 'waived'])->default('pending');

            // Which invoice line item covers this (set after invoice is created)
            $table->foreignId('invoice_item_id')
                  ->nullable()
                  ->constrained('invoice_items')
                  ->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['treatment_visit_id']);
            $table->index(['patient_id', 'billing_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_visit_items');
    }
};
