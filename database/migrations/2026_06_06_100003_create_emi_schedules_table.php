<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emi_schedules', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('invoice_payment_id');
            $table->foreign('invoice_payment_id')->references('id')->on('invoice_payments')->onDelete('cascade');

            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('patient_id');

            $table->unsignedTinyInteger('instalment_no');     // 1, 2, 3…
            $table->date('due_date');                          // auto-debit date
            $table->decimal('principal', 10, 2);              // principal component
            $table->decimal('interest', 10, 2)->default(0);   // interest component
            $table->decimal('emi_amount', 10, 2);             // total instalment

            $table->enum('status', ['pending', 'paid', 'bounced', 'waived'])->default('pending');
            $table->date('paid_date')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
            $table->index(['patient_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emi_schedules');
    }
};
