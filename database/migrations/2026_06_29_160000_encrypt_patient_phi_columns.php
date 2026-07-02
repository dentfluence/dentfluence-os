<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase A — Encrypt patient PHI at rest (column widening).
 *
 * App-level encryption (see app/Casts/Encrypted.php) stores Base64 ciphertext
 * that is FAR longer than the original value and is NOT valid JSON. So before we
 * can store encrypted data we must:
 *   1. Drop the plain index on `abha_number` (you can't index a TEXT column the
 *      same way, and an index over random ciphertext is useless anyway).
 *   2. Widen the short string columns (string(17)/string(20)/string(255)) to
 *      longText.
 *   3. Convert the JSON columns (medical_conditions / dental_conditions /
 *      allergies) to longText, since ciphertext isn't valid JSON.
 *
 * Columns already typed `text` (address, chief_complaint, medical_alert,
 * current_medications) and patient_identifiers.value (already text) need no
 * change — they hold ciphertext fine.
 *
 * RUN ORDER:  php artisan migrate  →  php artisan patients:encrypt-phi
 * The resilient casts read legacy plaintext safely in between.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Drop the abha_number index if it exists (name: patients_abha_number_index).
        if ($this->indexExists('patients', 'patients_abha_number_index')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->dropIndex('patients_abha_number_index');
            });
        }

        // 2 + 3) Widen / convert columns to longText so they can hold ciphertext.
        Schema::table('patients', function (Blueprint $table) {
            foreach ([
                'abha_number',
                'abha_address',
                'alternate_phone',
                'emergency_contact_number',
                'medical_conditions',
                'dental_conditions',
                'allergies',
            ] as $col) {
                if (Schema::hasColumn('patients', $col)) {
                    $table->longText($col)->nullable()->change();
                }
            }
        });
    }

    public function down(): void
    {
        // NOTE: rolling back AFTER the backfill will TRUNCATE encrypted values
        // back into short columns and corrupt data. Only roll back if you have
        // not yet run `patients:encrypt-phi` (or you have a fresh DB).
        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'abha_number')) {
                $table->string('abha_number', 17)->nullable()->change();
            }
            if (Schema::hasColumn('patients', 'abha_address')) {
                $table->string('abha_address')->nullable()->change();
            }
            if (Schema::hasColumn('patients', 'alternate_phone')) {
                $table->string('alternate_phone', 20)->nullable()->change();
            }
            if (Schema::hasColumn('patients', 'emergency_contact_number')) {
                $table->string('emergency_contact_number', 20)->nullable()->change();
            }
            foreach (['medical_conditions', 'dental_conditions', 'allergies'] as $col) {
                if (Schema::hasColumn('patients', $col)) {
                    $table->json($col)->nullable()->change();
                }
            }
        });

        if (! $this->indexExists('patients', 'patients_abha_number_index')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->index('abha_number');
            });
        }
    }

    /** True if the given index name exists on the table (MySQL). */
    private function indexExists(string $table, string $index): bool
    {
        try {
            return collect(DB::select("SHOW INDEXES FROM `{$table}` WHERE Key_name = ?", [$index]))->isNotEmpty();
        } catch (\Throwable $e) {
            return false;
        }
    }
};
