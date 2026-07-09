<?php
// =============================================================================
// Vouchers are permanent financial documents — they are never edited or hard
// deleted. To correct a mistake, a voucher is voided (with a required reason)
// and a fresh, correct voucher is issued separately. This keeps the audit
// trail intact for CA export / reconciliation.
// =============================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('finance_vouchers', function (Blueprint $table) {
            $table->enum('status', ['active', 'voided'])->default('active')->after('approved_at');
            $table->text('void_reason')->nullable()->after('status');
            $table->timestamp('voided_at')->nullable()->after('void_reason');
            $table->foreignId('voided_by')->nullable()->after('voided_at')
                  ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('finance_vouchers', function (Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropColumn(['status', 'void_reason', 'voided_at', 'voided_by']);
        });
    }
};
