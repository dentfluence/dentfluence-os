<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            // Clinic account the payment was received in (FK + cached name for display)
            $table->unsignedBigInteger('clinic_account_id')
                  ->nullable()
                  ->after('payment_date')
                  ->comment('FK to finance_bank_accounts');
            $table->string('clinic_account_name', 150)
                  ->nullable()
                  ->after('clinic_account_id')
                  ->comment('Cached account name for display even if account is deleted');

            $table->foreign('clinic_account_id')
                  ->references('id')
                  ->on('finance_bank_accounts')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropForeign(['clinic_account_id']);
            $table->dropColumn(['clinic_account_id', 'clinic_account_name']);
        });
    }
};
