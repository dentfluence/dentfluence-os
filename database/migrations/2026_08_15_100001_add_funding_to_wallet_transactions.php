<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * U8 — ADVANCE / PATIENT CREDIT (frozen business model, 2026-08-15).
 *
 * Adds an explicit funding discriminator to the wallet ledger.
 *
 * WHY A NEW COLUMN AND NOT `credit_type` OR `source`:
 * `source = 'admin_credit'` is currently written by BOTH
 * WalletService::reverseInvoiceDebit() (restoring the patient's own money) and
 * the Finance > Add Credit gift path (clinic-funded). One value, two opposite
 * economic meanings — so `source` cannot answer "did cash enter the clinic?".
 * `credit_type` answers "does it expire?", which is a different question.
 *
 * U8 rule 6/12/13: patient-funded credit is a liability and is cash-refundable;
 * clinic-funded credit is a concession and is not.
 *
 * BACKFILL POLICY (CEO decision B2, 2026-08-15): historical rows are NOT
 * reclassified. They are mapped so that today's behaviour is preserved exactly —
 * permanent -> patient (withdraw() draws on balance_permanent today),
 * promotional -> clinic. Reclassifying genuinely clinic-funded historical
 * 'permanent' rows (referral rewards, admin gifts) is deferred to U2/U7.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->enum('funding', ['patient', 'clinic'])
                  ->nullable()
                  ->after('credit_type');
        });

        // Preserve current behaviour exactly. NOT a reclassification.
        DB::table('wallet_transactions')
            ->where('credit_type', 'permanent')
            ->update(['funding' => 'patient']);

        DB::table('wallet_transactions')
            ->where('credit_type', 'promotional')
            ->update(['funding' => 'clinic']);

        // Debits carry credit_type = the pot they drew from; same mapping applies.
        DB::table('wallet_transactions')
            ->whereNull('funding')
            ->update(['funding' => 'patient']);

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->index(['patient_id', 'funding'], 'wallet_tx_patient_funding_idx');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex('wallet_tx_patient_funding_idx');
            $table->dropColumn('funding');
        });
    }
};
