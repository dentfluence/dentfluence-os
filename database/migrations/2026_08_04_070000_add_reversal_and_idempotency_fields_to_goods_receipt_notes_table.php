<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * P0-4 / P0-7 hardening (2026-08-04):
     *
     * - idempotency_key: a deterministic fingerprint of a single receive
     *   request (PO + location + date + sorted lines), set by
     *   InventoryService::receivePurchaseOrder(). A double-click, browser
     *   back-button resubmit, or client retry after a timeout produces the
     *   same key, so the unique index below is the authoritative guard
     *   against a duplicate GRN / duplicate stock-in / duplicate Finance
     *   bill for the same delivery — the service checks for it first for a
     *   friendly short-circuit, and this constraint catches the race where
     *   two identical requests land at the same instant.
     *
     * - status gains 'reversed': reverseLastGrn() previously hard-deleted
     *   the GRN + grn_items when undoing a receipt, destroying the audit
     *   trail for the module's most financially significant action. It now
     *   marks the GRN 'reversed' (reversed_at/reversed_by) and leaves the
     *   record and its line items in place — matching the append-only
     *   pattern stock_movements already uses (see
     *   2026_07_07_000001_add_reversal_fields_to_stock_movements_table).
     */
    public function up(): void
    {
        Schema::table('goods_receipt_notes', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->unique()->after('grn_number');
            $table->timestamp('reversed_at')->nullable()->after('status');
            $table->foreignId('reversed_by')->nullable()->after('reversed_at')
                ->constrained('users')->nullOnDelete();
        });

        DB::statement("ALTER TABLE goods_receipt_notes MODIFY status ENUM('draft','confirmed','invoiced','reversed') DEFAULT 'confirmed'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE goods_receipt_notes MODIFY status ENUM('draft','confirmed','invoiced') DEFAULT 'confirmed'");

        Schema::table('goods_receipt_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversed_by');
            $table->dropColumn(['idempotency_key', 'reversed_at']);
        });
    }
};
