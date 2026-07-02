<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ABDM Phase 1 · Wave 1 — Per-facility ABDM configuration.
 *
 * Each branch is its own ABDM facility (HIP/HIU) with its own ids, endpoints and
 * keys. IMPORTANT: credential columns hold *references* to the secret store
 * (e.g. a vault key name), NEVER the secret value. `is_enabled` is the per-branch
 * kill switch and defaults to FALSE so nothing is live until you turn it on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_abdm_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->unique()->constrained('branches')->cascadeOnDelete();

            $table->string('environment', 20)->default('sandbox'); // sandbox|production

            $table->string('hip_id')->nullable();        // Health Information Provider id
            $table->string('hiu_id')->nullable();        // Health Information User id
            $table->string('hfr_id')->nullable();        // mirror of branches.hfr_id
            $table->string('gateway_base_url')->nullable();

            // Secret-store REFERENCES only — not the actual secrets
            $table->string('client_id_ref')->nullable();
            $table->string('client_secret_ref')->nullable();
            $table->string('signing_key_ref')->nullable();

            $table->unsignedInteger('consent_default_expiry_days')->default(180);
            $table->boolean('is_enabled')->default(false); // per-facility kill switch

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_abdm_config');
    }
};
