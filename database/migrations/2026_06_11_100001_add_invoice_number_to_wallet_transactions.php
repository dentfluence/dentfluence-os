<?php
// =============================================================================
// Add invoice_number (denormalized) to wallet_transactions.
// Stored alongside invoice_id so exports/ledgers work without extra joins.
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            // Denormalized invoice number — populated by WalletService on debit/refund
            $table->string('invoice_number', 50)->nullable()->after('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropColumn('invoice_number');
        });
    }
};
