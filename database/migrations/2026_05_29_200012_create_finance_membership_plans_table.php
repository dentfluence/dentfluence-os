<?php
// =============================================================================
// Finance Membership Plans & Subscriptions
// =============================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_membership_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id')->default(1);
            $table->string('plan_name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->enum('duration', ['monthly','quarterly','half_yearly','yearly'])->default('yearly');
            $table->json('benefits')->nullable();           // list of included treatments/discounts
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('finance_patient_memberships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id')->default(1);
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('transaction_id')->nullable();

            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('amount_paid', 10, 2);
            $table->enum('status', ['active','expired','cancelled'])->default('active');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_patient_memberships');
        Schema::dropIfExists('finance_membership_plans');
    }
};
