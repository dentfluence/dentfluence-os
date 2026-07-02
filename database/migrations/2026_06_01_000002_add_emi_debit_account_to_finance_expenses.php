<?php
// =============================================================================
// Adds EMI debit account FK so we know which bank account gets auto-debited,
// enabling daily huddle reminders on EMI due dates.
// =============================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('finance_expenses', function (Blueprint $table) {
            // Which bank account auto-debits this EMI instalment
            $table->unsignedBigInteger('emi_debit_account_id')
                  ->nullable()
                  ->after('emi_interest_rate')
                  ->comment('FK to finance_bank_accounts — account that auto-debits EMI');
        });
    }

    public function down(): void
    {
        Schema::table('finance_expenses', function (Blueprint $table) {
            $table->dropColumn('emi_debit_account_id');
        });
    }
};
