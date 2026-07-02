<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_incentive_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Compensation model
            // fixed            → basic only, no incentive
            // fixed_revenue    → basic + % of revenue above a target
            // pure_revenue     → % of all revenue (no fixed basic via incentive)
            // per_patient      → fixed ₹ per patient seen
            // fixed_bonus      → fixed + target-based bonus (front desk)
            $table->enum('compensation_type', [
                'fixed', 'fixed_revenue', 'pure_revenue', 'per_patient', 'fixed_bonus'
            ])->default('fixed');

            // For fixed_revenue: revenue threshold before % kicks in
            $table->decimal('revenue_target', 12, 2)->nullable();

            // For fixed_revenue + pure_revenue: incentive % rate
            $table->decimal('incentive_rate', 5, 2)->nullable(); // e.g. 30.00 = 30%

            // For per_patient: ₹ per patient
            $table->decimal('per_patient_rate', 10, 2)->nullable();

            // For pure_revenue + per_patient: minimum guarantee floor
            $table->decimal('minimum_guarantee', 10, 2)->nullable();

            // For fixed_bonus (front desk): monthly appointment target + bonus amount
            $table->unsignedInteger('target_appointments')->nullable();
            $table->decimal('bonus_amount', 10, 2)->nullable();

            // Notes / custom terms
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->unique('user_id'); // One incentive rule per staff member
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_incentive_rules');
    }
};
