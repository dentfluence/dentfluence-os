<?php
// =============================================================================
// Fix void_refund_method ENUM on invoice_payments
// Previous migration created it with 'card_upi' — now replaced with
// 'bank_transfer' (charge is auto-determined from original payment mode).
// Also fixes cancel_refund_method column on invoices if it was created similarly.
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Correct the enum on invoice_payments.void_refund_method
        DB::statement("
            ALTER TABLE `invoice_payments`
            MODIFY COLUMN `void_refund_method`
            ENUM('wallet','cash','bank_transfer','no_refund') NULL
        ");
    }

    public function down(): void
    {
        // Revert to the old enum that included card_upi
        DB::statement("
            ALTER TABLE `invoice_payments`
            MODIFY COLUMN `void_refund_method`
            ENUM('wallet','cash','card_upi','no_refund') NULL
        ");
    }
};
