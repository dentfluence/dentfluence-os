<?php
// =============================================================================
// Finance Expenses — Every rupee leaving the clinic.
// =============================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id')->default(1);
            $table->unsignedBigInteger('transaction_id')->nullable();

            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable();

            $table->string('title');
            $table->text('description')->nullable();
            $table->date('expense_date');

            // Amounts
            $table->decimal('amount', 12, 2);
            $table->boolean('gst_applicable')->default(false);
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('gst_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);        // amount + gst

            // Payment
            $table->enum('payment_mode', [
                'cash', 'upi', 'card', 'bank_transfer', 'cheque', 'other'
            ])->default('cash');
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->string('payment_reference')->nullable();

            // Recurring
            $table->boolean('is_recurring')->default(false);
            $table->enum('recurring_period', ['daily','weekly','monthly','quarterly','yearly'])->nullable();
            $table->date('next_due_date')->nullable();

            // Attachments (invoice/receipt paths as JSON array)
            $table->json('attachments')->nullable();

            // Workflow
            $table->enum('status', ['pending_approval','approved','rejected','cancelled'])->default('approved');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason')->nullable();

            $table->string('notes')->nullable();

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('updated_reason')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['clinic_id', 'expense_date']);
            $table->index(['category_id']);
            $table->index(['vendor_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('finance_expenses'); }
};
