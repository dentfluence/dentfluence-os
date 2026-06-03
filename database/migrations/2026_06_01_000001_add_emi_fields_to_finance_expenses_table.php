<?php
// =============================================================================
// Adds EMI payment fields to finance_expenses.
// Allows recording instalment-based purchases (equipment, implant kits, etc.)
// =============================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('finance_expenses', function (Blueprint $table) {
            // Add EMI to the payment_mode enum
            // Laravel doesn't alter enums cleanly, so we use a raw DB statement below.

            // EMI detail columns
            $table->unsignedTinyInteger('emi_tenure_months')->nullable()->after('payment_reference')
                  ->comment('Total number of EMI instalments');
            $table->date('emi_start_date')->nullable()->after('emi_tenure_months')
                  ->comment('Date of first EMI deduction');
            $table->decimal('emi_amount', 12, 2)->nullable()->after('emi_start_date')
                  ->comment('Amount per instalment');
            $table->decimal('emi_interest_rate', 5, 2)->nullable()->after('emi_amount')
                  ->comment('Annual interest rate (%) — 0 for no-cost EMI');
        });

        // Extend payment_mode enum to include 'emi'
        DB::statement("ALTER TABLE finance_expenses MODIFY COLUMN payment_mode ENUM(
            'cash','upi','card','bank_transfer','cheque','emi','other'
        ) NOT NULL DEFAULT 'cash'");
    }

    public function down(): void
    {
        Schema::table('finance_expenses', function (Blueprint $table) {
            $table->dropColumn(['emi_tenure_months','emi_start_date','emi_amount','emi_interest_rate']);
        });

        DB::statement("ALTER TABLE finance_expenses MODIFY COLUMN payment_mode ENUM(
            'cash','upi','card','bank_transfer','cheque','other'
        ) NOT NULL DEFAULT 'cash'");
    }
};
