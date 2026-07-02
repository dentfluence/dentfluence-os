<?php
// =============================================================================
// F1 — Alter Invoices
// Adds wallet, coupon, and membership columns needed for the full billing flow.
// Also adds final_bill_id back-reference so Invoice knows if it has been closed.
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Wallet credit applied to this invoice (deducted before coupon)
            $table->decimal('wallet_applied', 12, 2)->default(0)->after('gst_amount');

            // Coupon applied (FK to coupon_codes)
            $table->unsignedBigInteger('coupon_id')->nullable()->after('wallet_applied');
            $table->decimal('coupon_discount', 12, 2)->default(0)->after('coupon_id');

            // AOCP membership active at billing time (snapshot, not live lookup)
            $table->unsignedBigInteger('membership_id')->nullable()->after('coupon_discount');
            $table->decimal('membership_discount', 12, 2)->default(0)->after('membership_id');

            // Final bill back-reference
            $table->unsignedBigInteger('final_bill_id')->nullable()->after('membership_discount');

            $table->index('coupon_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['coupon_id']);
            $table->dropColumn([
                'wallet_applied',
                'coupon_id',
                'coupon_discount',
                'membership_id',
                'membership_discount',
                'final_bill_id',
            ]);
        });
    }
};
