<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add nominee fields to patients (DPDP item 5.2 — nominee right).
 *
 * DPDP lets a patient nominate someone to exercise their data rights if they
 * die or become incapacitated. We store that nominee on the patient record;
 * it is set/updated by fulfilling a "nominee" data request.
 *
 * Additive — safe to migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('nominee_name')->nullable()->after('emergency_contact_number');
            $table->string('nominee_relationship')->nullable()->after('nominee_name');
            $table->string('nominee_contact')->nullable()->after('nominee_relationship');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['nominee_name', 'nominee_relationship', 'nominee_contact']);
        });
    }
};
