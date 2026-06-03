<?php
// =============================================================================
// Finance Cashbook — Daily cash register. One row per day per clinic.
// =============================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_cashbook', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id')->default(1);
            $table->date('book_date')->unique();

            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->decimal('cash_in', 12, 2)->default(0);       // total cash received
            $table->decimal('cash_out', 12, 2)->default(0);      // total cash paid
            $table->decimal('closing_balance', 12, 2)->default(0); // computed: opening + in - out

            $table->decimal('physical_count', 12, 2)->nullable(); // actual cash counted
            $table->decimal('difference', 12, 2)->default(0);    // closing - physical

            $table->enum('status', ['open','reconciled','mismatch'])->default('open');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('reconciled_by')->nullable();
            $table->timestamp('reconciled_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'book_date']);
        });
    }

    public function down(): void { Schema::dropIfExists('finance_cashbook'); }
};
