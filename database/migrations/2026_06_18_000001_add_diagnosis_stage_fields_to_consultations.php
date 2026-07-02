<?php
/**
 * P2C6 — Add diagnosis_risk and diagnosis_icd_code to consultations.
 *
 * diagnosis_notes already exists (from 2024 base migration).
 * This adds the two new Stage-3 fields from the 3-stage diagnosis rebuild.
 *
 * Run: php artisan migrate
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            if (!Schema::hasColumn('consultations', 'diagnosis_risk')) {
                $table->string('diagnosis_risk', 50)->nullable()
                      ->after('diagnosis_notes')
                      ->comment('Low Risk / Moderate Risk / High Risk / Very High Risk');
            }
            if (!Schema::hasColumn('consultations', 'diagnosis_icd_code')) {
                $table->string('diagnosis_icd_code', 30)->nullable()
                      ->after('diagnosis_risk')
                      ->comment('Optional ICD-10-CM code, e.g. K02.1');
            }
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('consultations', 'diagnosis_risk'))     $cols[] = 'diagnosis_risk';
            if (Schema::hasColumn('consultations', 'diagnosis_icd_code')) $cols[] = 'diagnosis_icd_code';
            if ($cols) $table->dropColumn($cols);
        });
    }
};
