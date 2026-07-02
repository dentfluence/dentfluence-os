<?php
// =============================================================================
// F1 — Wallets
// One wallet per patient. Holds total balance (promotional + permanent).
// Actual ledger is in wallet_transactions. Balance here is a running total
// kept in sync so we don't need to SUM on every invoice render.
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')
                  ->unique()                          // one wallet per patient
                  ->constrained('patients')
                  ->cascadeOnDelete();

            // Running totals — kept in sync by WalletService
            $table->decimal('balance_promotional', 12, 2)->default(0);   // has expiry
            $table->decimal('balance_permanent', 12, 2)->default(0);     // never expires
            $table->decimal('balance_total', 12, 2)->default(0);         // sum of both

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
