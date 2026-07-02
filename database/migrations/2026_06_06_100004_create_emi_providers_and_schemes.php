<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── EMI Providers ────────────────────────────────────────────────────
        Schema::create('emi_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');                     // e.g. "Bajaj Finserv"
            $table->string('contact')->nullable();      // optional contact / rep
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });

        // ── EMI Schemes ─────────────────────────────────────────────────────
        // Each scheme is offered by one provider for a specific tenure.
        Schema::create('emi_schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emi_provider_id')->constrained('emi_providers')->onDelete('cascade');

            $table->string('scheme_name');              // e.g. "No Cost 12M (10+2)"
            $table->unsignedTinyInteger('tenure_months');

            // e.g. "10+2" means 10 EMIs to provider + 2 upfront from patient
            $table->unsignedTinyInteger('upfront_emis')->default(0);

            // Cost borne by clinic (% of invoice total)
            $table->decimal('clinic_interest_rate', 5, 2)->default(0);  // e.g. 13.00 %
            $table->decimal('gst_on_interest', 5, 2)->default(18.00);  // GST on interest charged to clinic

            // If true, clinic adds the cost to patient as convenience charge
            $table->boolean('pass_cost_to_patient')->default(false);

            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });

        // ── Extend invoice_payments for Provider EMI ─────────────────────
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->string('emi_type')->nullable()->after('emi_start_date');
            // 'direct'   = patient pays clinic in instalments (existing flow)
            // 'provider' = clinic gets paid upfront by provider

            $table->foreignId('emi_provider_scheme_id')
                  ->nullable()
                  ->constrained('emi_schemes')
                  ->nullOnDelete()
                  ->after('emi_type');

            $table->decimal('emi_upfront_amount', 10, 2)->nullable()
                  ->comment('Upfront EMIs patient pays on day-1')
                  ->after('emi_provider_scheme_id');

            $table->decimal('clinic_net_amount', 10, 2)->nullable()
                  ->comment('Amount clinic actually receives after provider deduction')
                  ->after('emi_upfront_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropForeign(['emi_provider_scheme_id']);
            $table->dropColumn(['emi_type', 'emi_provider_scheme_id', 'emi_upfront_amount', 'clinic_net_amount']);
        });
        Schema::dropIfExists('emi_schemes');
        Schema::dropIfExists('emi_providers');
    }
};
