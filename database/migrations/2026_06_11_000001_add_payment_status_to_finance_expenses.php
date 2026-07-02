<?php
// =============================================================================
// Finance Expenses — add payment tracking fields
// Separates "payment status" (unpaid/paid) from the approval workflow status.
// Also adds due_date, paid_* fields and source polymorphic link for auto-bills.
// =============================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('finance_expenses', function (Blueprint $table) {
            // Payment status — separate from approval workflow
            $table->enum('payment_status', ['unpaid', 'paid'])
                  ->default('paid')
                  ->after('status');

            // When the bill is due (for unpaid/scheduled expenses)
            $table->date('due_date')->nullable()->after('payment_status');

            // Details filled in when marking as paid
            $table->date('paid_at')->nullable()->after('due_date');
            $table->decimal('paid_amount', 12, 2)->nullable()->after('paid_at');
            $table->string('paid_mode', 30)->nullable()->after('paid_amount');   // payment mode used when settling
            $table->string('paid_reference', 100)->nullable()->after('paid_mode'); // ref/UTR when settling

            // Polymorphic source — auto-bills created from PO receive / lab orders
            $table->string('source_type', 100)->nullable()->after('paid_reference');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');

            $table->index(['payment_status']);
            $table->index(['due_date']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('finance_expenses', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['due_date']);
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropColumn([
                'payment_status', 'due_date',
                'paid_at', 'paid_amount', 'paid_mode', 'paid_reference',
                'source_type', 'source_id',
            ]);
        });
    }
};
