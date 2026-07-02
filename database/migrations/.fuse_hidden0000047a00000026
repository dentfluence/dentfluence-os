<?php
// =============================================================================
// F1 — Coupon Usage
// Tracks which patient used which coupon on which invoice.
// Used to enforce max_uses_per_patient and max_uses_global limits.
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_usage', function (Blueprint $table) {
            $table->id();

            $table->foreignId('coupon_code_id')
                  ->constrained('coupon_codes')
                  ->cascadeOnDelete();

            $table->foreignId('patient_id')
                  ->constrained('patients')
                  ->cascadeOnDelete();

            $table->foreignId('invoice_id')
                  ->constrained('invoices')
                  ->cascadeOnDelete();

            $table->decimal('discount_applied', 10, 2)->default(0); // actual ₹ saved
            $table->timestamp('used_at')->useCurrent();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            $table->index(['coupon_code_id', 'patient_id']);
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usage');
    }
};
