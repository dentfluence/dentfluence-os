<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2C6 — Add provisional_diagnosis and differential_diagnosis to consultations.
 *
 * primary_diagnosis already exists (used as the "Final" diagnosis field).
 * This migration adds the two missing columns so all 3 stages persist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            if (!Schema::hasColumn('consultations', 'provisional_diagnosis')) {
                $table->text('provisional_diagnosis')->nullable()
                    ->after('diagnosis_notes')
                    ->comment('Working diagnosis at the start of the visit');
            }

            if (!Schema::hasColumn('consultations', 'differential_diagnosis')) {
                $table->text('differential_diagnosis')->nullable()
                    ->after('provisional_diagnosis')
                    ->comment('Alternative diagnoses considered and ruled out');
            }
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('consultations', 'provisional_diagnosis')) $drop[] = 'provisional_diagnosis';
            if (Schema::hasColumn('consultations', 'differential_diagnosis')) $drop[] = 'differential_diagnosis';
            if ($drop) $table->dropColumn($drop);
        });
    }
};
