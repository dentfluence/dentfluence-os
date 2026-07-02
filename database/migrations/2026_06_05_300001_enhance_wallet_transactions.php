<?php
// =============================================================================
// Wallet Transactions — Phase 2 enhancements
// 1. campaign_name   : label for promotional campaign (e.g. "Diwali Offer")
// 2. applicable_treatments : JSON array of Treatment IDs this promo applies to
//                            NULL means valid for ALL treatments (unrestricted)
// 3. source enum     : add 'campaign' as a valid source
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            // Campaign label — only meaningful for promotional credits
            $table->string('campaign_name', 200)->nullable()->after('source');

            // Treatment restriction — JSON array of treatment IDs, NULL = all
            $table->json('applicable_treatments')->nullable()->after('campaign_name');
        });

        // Add 'campaign' to the source enum via raw SQL (Blueprint can't modify enums in MySQL)
        DB::statement("
            ALTER TABLE wallet_transactions
            MODIFY COLUMN source ENUM(
                'admin_credit',
                'campaign',
                'refund',
                'invoice_debit',
                'expiry_forfeit',
                'adjustment'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        // Remove 'campaign' from enum first, then drop columns
        DB::statement("
            ALTER TABLE wallet_transactions
            MODIFY COLUMN source ENUM(
                'admin_credit',
                'refund',
                'invoice_debit',
                'expiry_forfeit',
                'adjustment'
            ) NOT NULL
        ");

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropColumn(['campaign_name', 'applicable_treatments']);
        });
    }
};
