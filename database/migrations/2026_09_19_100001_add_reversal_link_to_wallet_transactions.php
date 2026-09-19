<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wallet credit reversal — the link between a mistaken credit and the debit
 * that cancels it.
 *
 * A money ledger is never deleted from. When a credit was entered by mistake,
 * a DEBIT is written against it and both rows stay visible; this column is what
 * lets the ledger say "reversed" instead of showing two unexplained entries,
 * and what stops the same credit being reversed twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('reversal_of_transaction_id')->nullable()->after('invoice_number');
            $table->index('reversal_of_transaction_id', 'wallet_tx_reversal_of_idx');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex('wallet_tx_reversal_of_idx');
            $table->dropColumn('reversal_of_transaction_id');
        });
    }
};
