<?php
// =============================================================================
// finance_expenses.payment_mode was NOT NULL, but expenseStore() intentionally
// saves it as null for unpaid/pending bills (payment mode isn't known until the
// bill is actually paid). That mismatch caused a 500 on save. This makes the
// column nullable to match the app's actual behaviour — no data is altered.
// =============================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE finance_expenses MODIFY COLUMN payment_mode ENUM(
            'cash','upi','card','bank_transfer','cheque','emi','other'
        ) NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE finance_expenses MODIFY COLUMN payment_mode ENUM(
            'cash','upi','card','bank_transfer','cheque','emi','other'
        ) NOT NULL DEFAULT 'cash'");
    }
};
