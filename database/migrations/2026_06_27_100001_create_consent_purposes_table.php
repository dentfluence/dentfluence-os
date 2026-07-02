<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * consent_purposes
 * ----------------
 * The CATALOGUE of things a patient can consent to, one row per purpose.
 *
 * DPDP requires consent to be PURPOSE-SPECIFIC — you cannot ask for one
 * blanket "I agree" checkbox. So each purpose (treatment, WhatsApp messages,
 * sharing to ABDM, marketing, etc.) lives here as its own item, and the
 * patient grants or withdraws each one independently.
 *
 * When the wording of a purpose changes you bump `version`; that lets us tell
 * which patients consented to the OLD wording and need to re-consent.
 *
 * Additive table — nothing else is touched. Safe to migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_purposes', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();                 // machine slug, e.g. "whatsapp_comms"
            $table->string('name');                          // human label shown to staff/patient
            $table->text('description')->nullable();         // exactly what the patient agrees to
            $table->string('category')->default('general');  // clinical | communication | data_sharing | research | general
            $table->boolean('is_mandatory')->default(false); // needed to receive care (withdrawal has consequences)
            $table->boolean('requires_explicit')->default(true); // explicit opt-in (DPDP default for health data)
            $table->unsignedInteger('version')->default(1);  // bump when wording changes -> triggers re-consent
            $table->unsignedInteger('retention_days')->nullable(); // optional purge hint (used later by 5.4 retention)
            $table->boolean('active')->default(true);        // hide retired purposes without deleting them
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_purposes');
    }
};
