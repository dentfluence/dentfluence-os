<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * U8 — Patient Credit running total.
 *
 * `balance_patient_credit` is the cash-backed, refundable, never-expiring
 * balance the clinic owes the patient (U8 rules 6, 11, 16).
 *
 * The three existing columns (balance_promotional / balance_permanent /
 * balance_total) are KEPT and still maintained, so nothing that reads them
 * breaks. This column is additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->decimal('balance_patient_credit', 12, 2)
                  ->default(0)
                  ->after('balance_permanent');
        });

        // Seed from the current permanent balance — matches the funding backfill
        // in the previous migration, so no patient's spendable/refundable
        // balance changes on deploy.
        DB::statement('UPDATE wallets SET balance_patient_credit = balance_permanent');
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn('balance_patient_credit');
        });
    }
};
