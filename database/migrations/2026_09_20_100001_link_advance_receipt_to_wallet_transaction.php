<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Link an advance receipt back to the wallet credit it created.
 *
 * receiveAdvance() writes three records — a wallet credit, an ADV- receipt and
 * a FinanceTransaction. Only the FinanceTransaction carried a link back
 * (source_type/source_id); the receipt stood alone. That left the wallet ledger
 * unable to say WHICH receipt an advance row came from, so it could not send an
 * operator to the one screen that can correctly reverse it.
 *
 * Matching by patient + amount + date would be a guess, and a guess that points
 * at the wrong receipt is worse than no link at all. So the link is stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->unsignedBigInteger('wallet_transaction_id')->nullable()->after('invoice_payment_id');
            $table->index('wallet_transaction_id', 'receipts_wallet_tx_idx');
        });

        // Backfill ONLY where the answer is unambiguous: exactly one advance
        // receipt and exactly one wallet advance credit for that patient, on
        // that date, for that amount. Anything with more than one candidate is
        // left null — an unlinked row degrades to the old behaviour, a wrongly
        // linked row sends someone to reverse the wrong receipt.
        DB::statement("
            UPDATE receipts r
            JOIN (
                SELECT r2.id AS receipt_id, MIN(wt.id) AS wallet_tx_id, COUNT(*) AS matches
                FROM receipts r2
                JOIN wallet_transactions wt
                  ON wt.patient_id = r2.patient_id
                 AND wt.direction  = 'credit'
                 AND wt.source     = 'advance'
                 AND wt.amount     = r2.amount
                 AND DATE(wt.created_at) = DATE(r2.receipt_date)
                WHERE r2.receipt_kind = 'advance'
                  AND r2.invoice_id IS NULL
                GROUP BY r2.id
                HAVING matches = 1
            ) m ON m.receipt_id = r.id
            SET r.wallet_transaction_id = m.wallet_tx_id
        ");
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropIndex('receipts_wallet_tx_idx');
            $table->dropColumn('wallet_transaction_id');
        });
    }
};
