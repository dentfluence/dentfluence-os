<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * U8 rule 8 — Patient Credit settles an invoice as a PAYMENT/TENDER, exactly
 * like Cash/UPI/Card. That requires 'wallet' to be a storable payment mode.
 *
 * finance_transactions.payment_mode ALREADY contains 'wallet'
 * (2026_05_29_200001) and finance_transactions.type ALREADY contains 'advance'.
 * Both were defined and never written. No change is needed there.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE invoice_payments MODIFY COLUMN payment_mode ENUM(
            'cash','card','debit_card','upi','cheque','netbanking','bank_transfer','emi','wallet','other'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE invoice_payments MODIFY COLUMN payment_mode ENUM(
            'cash','card','debit_card','upi','cheque','netbanking','bank_transfer','emi','other'
        ) NOT NULL");
    }
};
