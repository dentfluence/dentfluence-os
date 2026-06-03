<?php
// =============================================================================
// Finance Bank Accounts — Clinic's bank accounts tracker.
// =============================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id')->default(1);

            $table->string('account_name');           // e.g. "HDFC Current Account"
            $table->string('bank_name');
            $table->string('account_number');
            $table->string('ifsc_code', 15)->nullable();
            $table->string('branch')->nullable();
            $table->enum('account_type', ['current','savings','od','cc'])->default('current');

            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->decimal('current_balance', 12, 2)->default(0); // cached, updated on each txn

            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('upi_id')->nullable();
            $table->string('notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['clinic_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('finance_bank_accounts'); }
};
