<?php
// =============================================================================
// Staff Advance Tracking — Advances given to staff, deducted from payroll.
// =============================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_staff_advances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id')->default(1);
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('transaction_id')->nullable();

            $table->decimal('amount', 10, 2);
            $table->date('advance_date');
            $table->string('reason')->nullable();
            $table->decimal('amount_recovered', 10, 2)->default(0);
            $table->decimal('balance', 10, 2);
            $table->enum('status', ['pending','partially_recovered','fully_recovered'])->default('pending');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void { Schema::dropIfExists('finance_staff_advances'); }
};
