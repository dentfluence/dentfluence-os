<?php
// =============================================================================
// Finance Vendors — Supplier/vendor profiles.
// Note: Inventory already has product_dealers. This is the finance-first vendor
// master that handles payments, outstanding, and CA reporting.
// =============================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_vendors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id')->default(1);

            $table->string('vendor_name');
            $table->string('company_name')->nullable();
            $table->enum('vendor_type', [
                'lab', 'implant_company', 'dental_supplier', 'marketing_agency',
                'software_vendor', 'consultant', 'ca', 'utility_provider',
                'equipment_supplier', 'other'
            ])->default('other');

            // Contact
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode', 10)->nullable();

            // Financial
            $table->string('gstin', 20)->nullable();
            $table->string('pan', 15)->nullable();
            $table->integer('credit_days')->default(0);
            $table->decimal('credit_limit', 12, 2)->default(0);

            // Computed / cached (updated via job/event)
            $table->decimal('total_purchases', 12, 2)->default(0);
            $table->decimal('total_paid', 12, 2)->default(0);
            $table->decimal('outstanding_amount', 12, 2)->default(0);
            $table->date('last_purchase_date')->nullable();

            // Bank for NEFT
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('account_name')->nullable();

            $table->json('documents')->nullable();    // GST cert, PAN scan paths
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['clinic_id', 'vendor_type']);
        });
    }

    public function down(): void { Schema::dropIfExists('finance_vendors'); }
};
