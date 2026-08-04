<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * P0-7 hardening (2026-08-04): InventoryService::reverseLastGrn() has
     * always written payment_status => 'void' when voiding the Finance bill
     * for a reversed GRN, but the finance_expenses.payment_status column was
     * only ever ENUM('unpaid','paid') — the same class of bug as the stock
     * count movement_type issue fixed earlier this sprint. On strict-mode
     * MySQL this throws and the whole reversal (including the stock-side
     * correction) rolls back; on lenient MySQL it silently truncates to an
     * empty string, leaving the bill neither unpaid, paid, nor void. Additive
     * only — existing 'unpaid'/'paid' rows are untouched.
     */
    public function up(): void
    {
        // Preserve the original column default ('paid') exactly — this migration
        // only widens the allowed value set, it does not change existing behaviour.
        DB::statement("ALTER TABLE finance_expenses MODIFY payment_status ENUM('unpaid','paid','void') DEFAULT 'paid'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE finance_expenses MODIFY payment_status ENUM('unpaid','paid') DEFAULT 'paid'");
    }
};
