<?php

// =============================================================================
// Patient-Level Payment Allocation — consolidated receipt breakdown
// -----------------------------------------------------------------------------
// One patient tender can now settle several invoices at once. The patient is
// handed ONE receipt, so that receipt has to carry its own allocation breakdown:
// which invoices it paid, how much went to each, and how much became Patient
// Credit.
//
// A receipt is a point-in-time document, so the breakdown is stored as an
// immutable JSON snapshot rather than being re-derived from live invoice rows
// (which keep changing as later payments land).
//
// Additive and nullable. No existing row is touched, no backfill, no behaviour
// change for RCP- or ADV- receipts. Written idempotently because MySQL DDL is
// not transactional — a half-applied migration leaves the schema changed but
// the migrations row unwritten (this bit us on 2026_08_15_100003).
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('receipts', 'allocation_breakdown')) {
            Schema::table('receipts', function (Blueprint $table) {
                $table->json('allocation_breakdown')->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('receipts', 'allocation_breakdown')) {
            Schema::table('receipts', function (Blueprint $table) {
                $table->dropColumn('allocation_breakdown');
            });
        }
    }
};
