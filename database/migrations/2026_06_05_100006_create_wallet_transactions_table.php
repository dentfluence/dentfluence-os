<?php
// =============================================================================
// F1 — Wallet Transactions
// Full ledger of all wallet credits and debits per patient.
// Two credit types: promotional (has expiry) and permanent (no expiry).
// System always consumes expiring credits first (FIFO by expiry_date).
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wallet_id')
                  ->constrained('wallets')
                  ->cascadeOnDelete();

            $table->foreignId('patient_id')
                  ->constrained('patients')
                  ->cascadeOnDelete();

            // Direction
            $table->enum('direction', ['credit', 'debit']);

            // Credit type (relevant for credits; null for debits)
            $table->enum('credit_type', ['promotional', 'permanent'])->nullable();

            // Source of this transaction
            $table->enum('source', [
                'admin_credit',     // admin manually added funds
                'refund',           // refund from cancelled invoice
                'invoice_debit',    // used against an invoice
                'expiry_forfeit',   // promotional credit expired (auto-debit)
                'adjustment',       // manual correction
            ]);

            $table->decimal('amount', 12, 2);

            // Expiry only applies to promotional credits
            $table->date('expiry_date')->nullable();

            // Link to the invoice this debit was applied to (if applicable)
            $table->foreignId('invoice_id')
                  ->nullable()
                  ->constrained('invoices')
                  ->nullOnDelete();

            $table->string('notes', 300)->nullable();
            $table->unsignedBigInteger('created_by')->nullable(); // null = system

            $table->timestamps();

            $table->index(['wallet_id', 'direction']);
            $table->index(['patient_id']);
            $table->index(['credit_type', 'expiry_date']); // for FIFO expiry queries
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
