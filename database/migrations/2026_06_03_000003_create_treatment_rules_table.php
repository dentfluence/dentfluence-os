<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Treatment rules / requirements.
     * Each row is one rule attached to a treatment.
     * rule_type is an enum of known rule keys the app can act on.
     * value stores flexible config (e.g. min_visits = {"count":2}).
     */
    public function up(): void
    {
        Schema::create('treatment_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_id')->constrained('treatments')->cascadeOnDelete();

            $table->enum('rule_type', [
                'xray_required',         // Requires X-ray before starting
                'consent_required',      // Consent form must be signed
                'lab_required',          // Creates a lab case
                'min_visits',            // Minimum number of visits needed
                'max_visits',            // Maximum visits in treatment
                'anesthesia_required',   // Local / general anesthesia
                'referral_required',     // Requires specialist referral
                'max_discount_pct',      // Maximum allowed discount %
                'age_restriction',       // Min/max patient age
                'medical_clearance',     // Medical clearance needed
                'follow_up_days',        // Follow-up appointment within N days
                'custom',                // Free-form custom rule
            ]);

            $table->json('value')->nullable();   // {"count": 2} or {"pct": 10} etc.
            $table->string('note')->nullable();  // Human-readable explanation
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_rules');
    }
};
