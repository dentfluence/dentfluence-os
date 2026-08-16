<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * U8 — ADVANCE RECEIPT (frozen rule 3: an advance must produce a receipt).
 *
 * Today `receipts.invoice_id` and `receipts.invoice_payment_id` are both
 * NOT NULL foreign keys (2026_06_05_100003), which is exactly why
 * WalletService::receiveAdvance() writes no receipt: real cash is collected
 * with no document. An advance has no invoice and no invoice-payment, so the
 * linkage must be optional for the document to exist at all.
 *
 * MySQL accepts MODIFY ... NULL with the FK in place (NULL never violates a FK).
 *
 * `payment_mode` is also widened here. This is U8-necessary, not scope creep:
 * receiveAdvance() already accepts cash/card/debit_card/upi/cheque/netbanking/
 * bank_transfer/other, and the receipts enum was never widened alongside
 * invoice_payments (2026_06_06_100002), so an advance receipt paid by debit
 * card or bank transfer could not be stored at all. 'wallet' is added for the
 * patient-credit tender receipt (rule 8).
 *
 * IDEMPOTENT BY DESIGN. MySQL does not roll DDL back, so an interrupted run
 * leaves the schema half-applied while the migrations table stays unwritten —
 * and the retry then dies on "Duplicate column". Every step below is guarded so
 * this migration can be re-run safely from any partial state. (Hit for real on
 * the Laragon dev database, 2026-08-15.)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Advance receipts have no invoice and no invoice-payment.
        //    MODIFY is naturally idempotent — re-running is a no-op.
        DB::statement('ALTER TABLE receipts MODIFY invoice_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE receipts MODIFY invoice_payment_id BIGINT UNSIGNED NULL');

        // 2. Distinguish the two documents. Existing rows are all payments.
        if (! Schema::hasColumn('receipts', 'receipt_kind')) {
            Schema::table('receipts', function (Blueprint $table) {
                $table->enum('receipt_kind', ['payment', 'advance'])
                      ->default('payment')
                      ->after('receipt_number');
            });
        }

        // 3. Widen payment_mode to every mode the app already accepts, + wallet.
        DB::statement("ALTER TABLE receipts MODIFY payment_mode ENUM(
            'cash','card','debit_card','upi','cheque','netbanking','bank_transfer','emi','wallet','other'
        ) NOT NULL DEFAULT 'cash'");

        // 4. Index — guarded the same way.
        if (! $this->indexExists('receipts', 'receipts_kind_idx')) {
            Schema::table('receipts', function (Blueprint $table) {
                $table->index('receipt_kind', 'receipts_kind_idx');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('receipts', 'receipts_kind_idx')) {
            Schema::table('receipts', function (Blueprint $table) {
                $table->dropIndex('receipts_kind_idx');
            });
        }

        if (Schema::hasColumn('receipts', 'receipt_kind')) {
            Schema::table('receipts', function (Blueprint $table) {
                $table->dropColumn('receipt_kind');
            });
        }

        DB::statement("ALTER TABLE receipts MODIFY payment_mode ENUM(
            'cash','card','upi','cheque','netbanking','emi','other'
        ) NOT NULL DEFAULT 'cash'");

        // Only safe if no advance receipts exist.
        DB::statement('ALTER TABLE receipts MODIFY invoice_payment_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE receipts MODIFY invoice_id BIGINT UNSIGNED NOT NULL');
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
