<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * patient_consents
 * ----------------
 * The CURRENT consent state for each patient, one row per (patient + purpose).
 *
 * This answers the everyday question "does this patient currently allow X?".
 * The full history of every grant/withdraw is kept separately in consent_logs
 * (append-only), so this table only holds the latest position.
 *
 * Additive table — safe to migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->index();          // who
            $table->foreignId('consent_purpose_id')->index();  // for what
            $table->string('status')->default('pending');      // granted | withdrawn | pending | expired
            $table->unsignedInteger('purpose_version')->default(1); // which wording they agreed to
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('expires_at')->nullable();       // optional time-boxed consent
            $table->string('capture_method')->nullable();      // web | portal | paper | mobile | import
            $table->foreignId('captured_by')->nullable()->index(); // staff user who recorded it (null = patient self / system)
            $table->text('notes')->nullable();
            $table->timestamps();

            // one current-state row per patient per purpose
            $table->unique(['patient_id', 'consent_purpose_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_consents');
    }
};
