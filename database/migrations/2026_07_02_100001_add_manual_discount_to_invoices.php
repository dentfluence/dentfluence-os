<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chunk 1 — Billing Workflow Update
 * Adds a header-level MANUAL discount to invoices, separate from the existing
 * coupon / wallet / membership layers. Every manual discount is accountable:
 * it records the type, value, resolved amount, mandatory reason, who authorized
 * it and who applied it, plus a timestamp.
 *
 * NOTE: This migration only adds columns. No calculation logic changes here —
 * Invoice::recalculate() is wired to use these fields in Chunk 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // 'flat' | 'percentage' — null means no manual discount applied
            $table->string('manual_discount_type', 20)->nullable()->after('membership_discount');
            // The raw value entered by the user (₹ for flat, % for percentage)
            $table->decimal('manual_discount_value', 12, 2)->default(0)->after('manual_discount_type');
            // The resolved rupee amount actually deducted (computed in Chunk 2)
            $table->decimal('manual_discount_amount', 12, 2)->default(0)->after('manual_discount_value');
            // Mandatory justification for the discount
            $table->text('manual_discount_reason')->nullable()->after('manual_discount_amount');
            // Who authorized it (may differ from who applied it)
            $table->foreignId('manual_discount_authorized_by')->nullable()
                  ->after('manual_discount_reason')
                  ->constrained('users')->nullOnDelete();
            // Who actually entered it
            $table->foreignId('manual_discount_applied_by')->nullable()
                  ->after('manual_discount_authorized_by')
                  ->constrained('users')->nullOnDelete();
            // When it was applied
            $table->timestamp('manual_discount_at')->nullable()->after('manual_discount_applied_by');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manual_discount_authorized_by');
            $table->dropConstrainedForeignId('manual_discount_applied_by');
            $table->dropColumn([
                'manual_discount_type',
                'manual_discount_value',
                'manual_discount_amount',
                'manual_discount_reason',
                'manual_discount_at',
            ]);
        });
    }
};
