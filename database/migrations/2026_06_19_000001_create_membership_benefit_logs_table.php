<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * membership_benefit_logs
 *
 * Audit trail of every free / discounted benefit a patient
 * has availed through their AOCP membership.
 *
 * Logged automatically by MembershipBenefitService::log()
 * whenever an invoice saves with membership benefits applied.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_benefit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id')->default(1);

            // Which patient availed the benefit
            $table->unsignedBigInteger('patient_id');

            // Which membership enrollment covered this benefit
            $table->unsignedBigInteger('membership_id');

            // Invoice that triggered the benefit (nullable — can be manual log)
            $table->unsignedBigInteger('invoice_id')->nullable();

            // What kind of benefit
            // free_consultation | free_xray | free_scaling | free_treatment | pct_discount
            $table->string('benefit_type', 50);

            // Human-readable label, e.g. "Free Consultation", "20% off (Rs. 450 saved)"
            $table->string('benefit_label', 200);

            // Rupees saved / value of benefit
            $table->decimal('amount_saved', 10, 2)->default(0);

            // Optional extra note (e.g. treatment name for free_treatment)
            $table->string('notes', 500)->nullable();

            // Who logged it (staff user ID)
            $table->unsignedBigInteger('created_by')->nullable();

            // When the benefit was availed
            $table->timestamp('availed_at')->useCurrent();

            $table->timestamps();

            // Indexes
            $table->index(['patient_id', 'availed_at']);
            $table->index('membership_id');
            $table->index('invoice_id');

            // FK constraints
            $table->foreign('patient_id')
                  ->references('id')->on('patients')
                  ->onDelete('cascade');

            $table->foreign('membership_id')
                  ->references('id')->on('finance_patient_memberships')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_benefit_logs');
    }
};
