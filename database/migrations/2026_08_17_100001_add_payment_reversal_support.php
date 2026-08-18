<?php

// =============================================================================
// A2 — Payment correction / reversal support
// -----------------------------------------------------------------------------
// 1. invoice_payments.receipt_id — the missing link. A consolidated PAY- receipt
//    settles N invoices with N InvoicePayment rows, and nothing recorded which
//    payments belonged to which receipt (allocation_breakdown stores invoice ids
//    and amounts, never payment ids). Without this, "reverse every allocation,
//    exactly once" cannot be proven — only guessed at by matching amounts.
//
// 2. receipts.voided_at / void_reason / voided_by / void_correction_type — the
//    existing void flow SOFT-DELETES the receipt, which hides it. A correction
//    must preserve history and stay visible, struck through, as VOID.
//
// Additive and nullable throughout. Nothing is dropped, nothing is rewritten.
// Written idempotently: MySQL DDL is not transactional, so a half-applied
// migration leaves the schema changed with the migrations row unwritten (this
// bit us on 2026_08_15_100003).
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoice_payments', 'receipt_id')) {
            Schema::table('invoice_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('receipt_id')->nullable()->after('invoice_id');
            });
        }

        if (! $this->indexExists('invoice_payments', 'invoice_payments_receipt_idx')) {
            Schema::table('invoice_payments', function (Blueprint $table) {
                $table->index('receipt_id', 'invoice_payments_receipt_idx');
            });
        }

        foreach ([
            'voided_at'            => "ALTER TABLE receipts ADD COLUMN voided_at TIMESTAMP NULL AFTER allocation_breakdown",
            'void_reason'          => "ALTER TABLE receipts ADD COLUMN void_reason TEXT NULL AFTER voided_at",
            'voided_by'            => "ALTER TABLE receipts ADD COLUMN voided_by BIGINT UNSIGNED NULL AFTER void_reason",
            'void_correction_type' => "ALTER TABLE receipts ADD COLUMN void_correction_type VARCHAR(30) NULL AFTER voided_by",
        ] as $column => $sql) {
            if (! Schema::hasColumn('receipts', $column)) {
                DB::statement($sql);
            }
        }

        $this->backfillReceiptLinks();
    }

    /**
     * Link the InvoicePayment rows of PAY- receipts that were created before
     * receipt_id existed.
     *
     * Matched ONLY when a single unambiguous candidate exists for a breakdown
     * line: same invoice, same amount, still unlinked, not deleted, and written
     * by the allocator (its notes are prefixed "Allocated from patient payment").
     * Anything ambiguous is deliberately left NULL — the void service refuses to
     * act on an unlinked receipt rather than reverse a payment it guessed at.
     */
    private function backfillReceiptLinks(): void
    {
        $receipts = DB::table('receipts')
            ->whereNull('invoice_id')
            ->whereNull('deleted_at')
            ->whereNotNull('allocation_breakdown')
            ->get(['id', 'allocation_breakdown']);

        foreach ($receipts as $receipt) {
            $breakdown = json_decode($receipt->allocation_breakdown, true);
            foreach (($breakdown['invoices'] ?? []) as $line) {
                if (empty($line['invoice_id']) || ! isset($line['amount'])) {
                    continue;
                }

                $candidates = DB::table('invoice_payments')
                    ->where('invoice_id', $line['invoice_id'])
                    ->where('amount', $line['amount'])
                    ->whereNull('receipt_id')
                    ->whereNull('deleted_at')
                    ->where('notes', 'like', 'Allocated from patient payment%')
                    ->pluck('id');

                if ($candidates->count() === 1) {
                    DB::table('invoice_payments')
                        ->where('id', $candidates->first())
                        ->update(['receipt_id' => $receipt->id]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['void_correction_type', 'voided_by', 'void_reason', 'voided_at'] as $column) {
            if (Schema::hasColumn('receipts', $column)) {
                Schema::table('receipts', fn (Blueprint $t) => $t->dropColumn($column));
            }
        }

        if ($this->indexExists('invoice_payments', 'invoice_payments_receipt_idx')) {
            Schema::table('invoice_payments', fn (Blueprint $t) => $t->dropIndex('invoice_payments_receipt_idx'));
        }

        if (Schema::hasColumn('invoice_payments', 'receipt_id')) {
            Schema::table('invoice_payments', fn (Blueprint $t) => $t->dropColumn('receipt_id'));
        }
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
