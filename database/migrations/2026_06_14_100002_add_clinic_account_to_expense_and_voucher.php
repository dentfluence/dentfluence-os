<?php
// =============================================================================
// Phase 3 — Expense Module
// Adds clinic_account fields to finance_expenses and finance_vouchers so that
// the "Received In" account is captured on every payment and reflected on the
// auto-generated Payment Voucher.
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // finance_expenses — capture which clinic account the payment went out from
        Schema::table('finance_expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('paid_clinic_account_id')
                  ->nullable()
                  ->after('paid_reference')
                  ->comment('FK to finance_bank_accounts — account used for payment');
            $table->string('paid_clinic_account_name', 150)
                  ->nullable()
                  ->after('paid_clinic_account_id')
                  ->comment('Cached account name for display');
            $table->string('paid_cheque_number', 50)
                  ->nullable()
                  ->after('paid_clinic_account_name')
                  ->comment('Cheque number when payment_mode = cheque');

            $table->foreign('paid_clinic_account_id')
                  ->references('id')
                  ->on('finance_bank_accounts')
                  ->nullOnDelete();
        });

        // finance_vouchers — mirror clinic account on the voucher document
        Schema::table('finance_vouchers', function (Blueprint $table) {
            $table->unsignedBigInteger('clinic_account_id')
                  ->nullable()
                  ->after('reference')
                  ->comment('FK to finance_bank_accounts');
            $table->string('clinic_account_name', 150)
                  ->nullable()
                  ->after('clinic_account_id')
                  ->comment('Cached account name');
            $table->string('cheque_number', 50)
                  ->nullable()
                  ->after('clinic_account_name')
                  ->comment('Cheque number when mode = cheque');

            $table->foreign('clinic_account_id')
                  ->references('id')
                  ->on('finance_bank_accounts')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('finance_expenses', function (Blueprint $table) {
            $table->dropForeign(['paid_clinic_account_id']);
            $table->dropColumn(['paid_clinic_account_id', 'paid_clinic_account_name', 'paid_cheque_number']);
        });

        Schema::table('finance_vouchers', function (Blueprint $table) {
            $table->dropForeign(['clinic_account_id']);
            $table->dropColumn(['clinic_account_id', 'clinic_account_name', 'cheque_number']);
        });
    }
};
