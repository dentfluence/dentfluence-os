<?php
// =============================================================================
// F1 — Coupon Codes
// Admin-created discount codes. Rules stored as JSON for flexibility.
// Max 1 coupon per invoice. Can be single-use or multi-use per patient.
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_codes', function (Blueprint $table) {
            $table->id();

            $table->string('code', 50)->unique();           // e.g. SAVE200, WELCOME10
            $table->string('description', 200)->nullable(); // shown to front desk

            // Discount type
            $table->enum('discount_type', ['flat', 'percentage']);
            $table->decimal('discount_value', 10, 2);       // ₹ or %

            // Usage limits
            $table->unsignedSmallInteger('max_uses_global')->default(0);    // 0 = unlimited
            $table->unsignedSmallInteger('max_uses_per_patient')->default(1); // default: single-use per patient

            $table->unsignedInteger('uses_count')->default(0); // running total

            // Validity
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();

            // Scope: restrict to certain treatments (JSON array of treatment names, empty = all)
            $table->json('applicable_treatments')->nullable();

            // Minimum invoice amount to apply
            $table->decimal('min_invoice_amount', 10, 2)->default(0);

            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['is_active', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_codes');
    }
};
