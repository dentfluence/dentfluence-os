<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_staff_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('reason', 255)->nullable();           // Purpose of advance
            $table->decimal('principal', 10, 2);                 // Amount given
            $table->date('given_date');                          // When given
            $table->boolean('with_interest')->default(false);
            $table->decimal('interest_rate', 5, 2)->default(0); // Annual % if with_interest
            $table->unsignedInteger('tenure_months');            // Repayment months
            $table->decimal('emi_amount', 10, 2);               // Calculated EMI
            $table->decimal('total_payable', 10, 2);            // Principal + interest
            $table->decimal('amount_paid', 10, 2)->default(0);  // Recovered so far
            $table->enum('status', ['active', 'closed', 'waived'])->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_staff_advances');
    }
};
