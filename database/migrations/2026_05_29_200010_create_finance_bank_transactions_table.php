<?php
// =============================================================================
// Finance Bank Transactions — Every debit/credit per bank account.
// =============================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bank_account_id');
            $table->unsignedBigInteger('clinic_id')->default(1);
            $table->unsignedBigInteger('transaction_id')->nullable(); // links to master

            $table->enum('type', ['deposit','withdrawal','transfer','upi','emi','interest','charge']);
            $table->enum('direction', ['credit','debit']);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2)->nullable();

            $table->date('txn_date');
            $table->string('description')->nullable();
            $table->string('reference')->nullable();

            $table->boolean('is_reconciled')->default(false);
            $table->enum('status', ['active','voided'])->default('active');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['bank_account_id', 'txn_date']);
        });
    }

    public function down(): void { Schema::dropIfExists('finance_bank_transactions'); }
};
