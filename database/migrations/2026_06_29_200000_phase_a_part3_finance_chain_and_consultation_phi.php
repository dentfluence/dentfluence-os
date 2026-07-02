<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase A — Part 3.
 *
 *  1. finance_audit_log → add prev_hash + hash so the FinanceAuditLog model can
 *     be tamper-evident (hash-chained), matching the other audit tables.
 *  2. consultations → widen the structured clinical JSON columns to longText so
 *     they can hold encrypted ciphertext (the EncryptedArray cast). These hold
 *     sensitive clinical data and are never queried via SQL JSON functions.
 *
 * RUN ORDER:  php artisan migrate
 *          →  php artisan patients:encrypt-phi   (encrypts the new columns too)
 *          →  php artisan audit:verify --backfill
 */
return new class extends Migration
{
    private array $consultationJsonCols = [
        'clinical_data', 'chart_data', 'radio_data', 'dbm_checklist',
        'investigations', 'investigation_details', 'specialty_findings',
        'accepted_specialties', 'treatment_plan_best', 'treatment_plan_acceptable',
        'tx_emergency', 'tx_protective', 'tx_transformative', 'tx_teeth',
        'prescriptions', 'instructions',
    ];

    public function up(): void
    {
        if (Schema::hasTable('finance_audit_log')) {
            Schema::table('finance_audit_log', function (Blueprint $t) {
                if (! Schema::hasColumn('finance_audit_log', 'prev_hash')) {
                    $t->string('prev_hash', 64)->nullable()->after('id');
                }
                if (! Schema::hasColumn('finance_audit_log', 'hash')) {
                    $t->string('hash', 64)->nullable()->after('prev_hash');
                }
            });
        }

        if (Schema::hasTable('consultations')) {
            Schema::table('consultations', function (Blueprint $t) {
                foreach ($this->consultationJsonCols as $col) {
                    if (Schema::hasColumn('consultations', $col)) {
                        $t->longText($col)->nullable()->change();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // NOTE: rolling back after encrypting will corrupt data — see the
        // patient PHI migration note. Only roll back on a fresh/unencrypted DB.
        if (Schema::hasTable('finance_audit_log')) {
            Schema::table('finance_audit_log', function (Blueprint $t) {
                foreach (['hash', 'prev_hash'] as $col) {
                    if (Schema::hasColumn('finance_audit_log', $col)) {
                        $t->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('consultations')) {
            Schema::table('consultations', function (Blueprint $t) {
                foreach ($this->consultationJsonCols as $col) {
                    if (Schema::hasColumn('consultations', $col)) {
                        $t->json($col)->nullable()->change();
                    }
                }
            });
        }
    }
};
