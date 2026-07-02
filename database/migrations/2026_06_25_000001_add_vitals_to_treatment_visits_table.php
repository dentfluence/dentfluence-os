<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds optional patient vitals to each treatment visit.
 * All columns are nullable — vitals are recorded only when the doctor/assistant
 * chooses to (the form section is collapsed/optional by default).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_visits', function (Blueprint $table) {
            // Blood pressure (mmHg) — stored as two numbers so we can chart trends later
            $table->unsignedSmallInteger('bp_systolic')->nullable()->after('next_visit_type');
            $table->unsignedSmallInteger('bp_diastolic')->nullable()->after('bp_systolic');

            // Pulse / heart rate (beats per minute)
            $table->unsignedSmallInteger('pulse_rate')->nullable()->after('bp_diastolic');

            // Oxygen saturation (SpO2, %)
            $table->unsignedTinyInteger('spo2')->nullable()->after('pulse_rate');

            // Body temperature (°C) — relevant for infection / abscess screening
            $table->decimal('temperature', 4, 1)->nullable()->after('spo2');

            // Blood sugar (mg/dL) + how it was taken (random / fasting / post-prandial)
            $table->unsignedSmallInteger('blood_sugar')->nullable()->after('temperature');
            $table->string('blood_sugar_type', 20)->nullable()->after('blood_sugar');

            // Body weight (kg) — used for anaesthetic / paediatric dosing
            $table->decimal('weight', 5, 2)->nullable()->after('blood_sugar_type');

            // Free-text remark for anything else worth noting
            $table->string('vitals_notes', 255)->nullable()->after('weight');
        });
    }

    public function down(): void
    {
        Schema::table('treatment_visits', function (Blueprint $table) {
            $table->dropColumn([
                'bp_systolic',
                'bp_diastolic',
                'pulse_rate',
                'spo2',
                'temperature',
                'blood_sugar',
                'blood_sugar_type',
                'weight',
                'vitals_notes',
            ]);
        });
    }
};
