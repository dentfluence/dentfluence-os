<?php
// =============================================================================
// Finance Payroll — Staff salary disbursement records.
// =============================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_payroll', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id')->default(1);
            $table->unsignedBigInteger('user_id');          // staff member
            $table->unsignedBigInteger('transaction_id')->nullable();

            // Period
            $table->integer('month');                        // 1-12
            $table->integer('year');
            $table->date('payment_date')->nullable();

            // Salary components
            $table->decimal('fixed_salary', 10, 2)->default(0);
            $table->decimal('incentives', 10, 2)->default(0);
            $table->decimal('bonus', 10, 2)->default(0);
            $table->decimal('deductions', 10, 2)->default(0);
            $table->decimal('advance_adjusted', 10, 2)->default(0);
            $table->decimal('net_salary', 10, 2);

            // Payment
            $table->enum('payment_mode', ['cash','bank_transfer','upi','cheque'])->default('bank_transfer');
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->string('reference_number')->nullable();

            $table->text('notes')->nullable();
            $table->enum('status', ['pending','paid','cancelled'])->default('pending');

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['clinic_id', 'user_id', 'month', 'year']);
            $table->index(['clinic_id', 'year', 'month']);
        });
    }

    public function down(): void { Schema::dropIfExists('finance_payroll'); }
};
