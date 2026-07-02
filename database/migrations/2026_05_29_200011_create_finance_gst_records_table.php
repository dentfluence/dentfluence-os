<?php
// =============================================================================
// Finance GST Records — GST-ready from Day 1. Inactive until toggle ON.
// Captures tax details for every transaction that has gst_applicable = true.
// =============================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_gst_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id')->default(1);
            $table->unsignedBigInteger('transaction_id');

            $table->enum('gst_type', ['output','input']); // output=sales, input=purchases
            $table->string('gstin_clinic')->nullable();
            $table->string('gstin_party')->nullable();    // customer or vendor GSTIN

            $table->string('hsn_sac', 20)->nullable();
            $table->string('description')->nullable();
            $table->decimal('taxable_amount', 12, 2);
            $table->decimal('gst_rate', 5, 2);
            $table->decimal('cgst_rate', 5, 2)->default(0);
            $table->decimal('sgst_rate', 5, 2)->default(0);
            $table->decimal('igst_rate', 5, 2)->default(0);
            $table->decimal('cgst_amount', 12, 2)->default(0);
            $table->decimal('sgst_amount', 12, 2)->default(0);
            $table->decimal('igst_amount', 12, 2)->default(0);
            $table->decimal('total_tax', 12, 2);
            $table->decimal('invoice_total', 12, 2);

            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();

            // GSTR filing tracking
            $table->string('gstr_period')->nullable(); // e.g. "2024-04"
            $table->boolean('filed')->default(false);
            $table->timestamp('filed_at')->nullable();

            $table->timestamps();
            $table->index(['clinic_id', 'gst_type']);
            $table->index(['gstr_period']);
        });
    }

    public function down(): void { Schema::dropIfExists('finance_gst_records'); }
};
