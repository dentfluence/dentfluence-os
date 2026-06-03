<?php
// =============================================================================
// Finance Settings — Per-clinic config including GST toggle.
// =============================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id')->default(1)->unique();

            // GST
            $table->boolean('gst_enabled')->default(false);
            $table->string('gstin', 20)->nullable();
            $table->string('gst_state_code', 5)->nullable();
            $table->string('business_type')->nullable(); // proprietorship, llp, partnership, pvt_ltd

            // Clinic financial identity
            $table->string('ca_name')->nullable();
            $table->string('ca_email')->nullable();
            $table->string('ca_phone', 20)->nullable();
            $table->string('ca_whatsapp', 20)->nullable();

            // Daily targets
            $table->decimal('daily_collection_target', 12, 2)->default(0);
            $table->decimal('monthly_revenue_target', 12, 2)->default(0);

            // Financial year
            $table->enum('fy_start_month', ['1','4'])->default('4'); // April (India) or January

            // Currency
            $table->string('currency', 5)->default('INR');
            $table->string('currency_symbol', 5)->default('₹');

            // Invoice settings
            $table->string('invoice_prefix', 20)->default('INV');
            $table->integer('invoice_start_number')->default(1);
            $table->boolean('show_gst_on_invoice')->default(false);
            $table->text('invoice_footer_text')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('finance_settings'); }
};
