<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add guardian / parental-consent fields to patient_consents (DPDP item 5.5).
 *
 * When the patient is a minor, consent is given by a parent or guardian. We
 * record who gave it and their relationship, so the record shows it was
 * lawfully obtained on the minor's behalf.
 *
 * Additive — safe to migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_consents', function (Blueprint $table) {
            $table->string('on_behalf_of')->default('self')->after('capture_method'); // self | guardian
            $table->string('guardian_name')->nullable()->after('on_behalf_of');
            $table->string('guardian_relationship')->nullable()->after('guardian_name'); // parent | legal guardian | ...
        });
    }

    public function down(): void
    {
        Schema::table('patient_consents', function (Blueprint $table) {
            $table->dropColumn(['on_behalf_of', 'guardian_name', 'guardian_relationship']);
        });
    }
};
