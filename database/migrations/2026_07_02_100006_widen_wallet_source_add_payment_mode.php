<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chunk 3 — Wallet advance / refund + payment allocation.
 *
 * 1. Widens wallet_transactions.source from a fixed ENUM to VARCHAR so new kinds
 *    ('advance', 'withdrawal') are allowed. This also fixes a latent issue where
 *    WalletService already writes source='campaign' (never listed in the old enum).
 * 2. Adds payment_mode to wallet_transactions so an advance/refund records HOW the
 *    money physically moved (cash / upi / card), for the ledger and reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Relax the source enum → varchar (preserves all existing values).
        DB::statement("ALTER TABLE wallet_transactions MODIFY source VARCHAR(30) NOT NULL");

        // 2. How the cash moved for advances/refunds (null for promo/invoice debits).
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->string('payment_mode', 30)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropColumn('payment_mode');
        });

        // Restore the original enum set (safe only if no new-kind rows exist).
        DB::statement("ALTER TABLE wallet_transactions MODIFY source ENUM(
            'admin_credit','refund','invoice_debit','expiry_forfeit','adjustment','campaign'
        ) NOT NULL");
    }
};
