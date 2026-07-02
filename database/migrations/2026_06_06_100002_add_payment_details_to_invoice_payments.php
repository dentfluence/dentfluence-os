<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            // Cheque fields
            $table->string('bank_name')->nullable()->after('reference_no');
            $table->string('cheque_no')->nullable()->after('bank_name');
            $table->date('cheque_date')->nullable()->after('cheque_no');
            $table->enum('cheque_status', ['pending', 'realised', 'bounced'])->nullable()->after('cheque_date');

            // Credit-card convenience fee
            $table->decimal('convenience_fee', 10, 2)->default(0)->after('cheque_status');

            // EMI fields
            $table->string('emi_provider')->nullable()->after('convenience_fee');
            $table->unsignedTinyInteger('emi_tenure')->nullable()->comment('months')->after('emi_provider');
            $table->decimal('emi_interest_rate', 5, 2)->nullable()->comment('% per annum')->after('emi_tenure');
            $table->decimal('emi_amount', 10, 2)->nullable()->comment('monthly instalment')->after('emi_interest_rate');
            $table->date('emi_start_date')->nullable()->after('emi_amount');
        });

        // Add debit_card and bank_transfer to payment_mode enum
        DB::statement("ALTER TABLE invoice_payments MODIFY COLUMN payment_mode ENUM('cash','card','debit_card','upi','cheque','netbanking','bank_transfer','emi','other') NOT NULL");
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropColumn([
                'bank_name', 'cheque_no', 'cheque_date', 'cheque_status',
                'convenience_fee',
                'emi_provider', 'emi_tenure', 'emi_interest_rate', 'emi_amount', 'emi_start_date',
            ]);
        });
        DB::statement("ALTER TABLE invoice_payments MODIFY COLUMN payment_mode ENUM('cash','card','upi','cheque','netbanking','emi','other') NOT NULL");
    }
};
