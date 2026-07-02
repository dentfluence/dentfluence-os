<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase A — Encrypt finance / HR / consultation PHI at rest (column widening).
 *
 * Companion to 2026_06_29_160000 (patients). Widens the short string columns
 * that will now hold Base64 ciphertext (see app/Casts/Encrypted.php) to
 * longText. Columns already typed text/longText need no change.
 *
 * RUN ORDER:  php artisan migrate  →  php artisan patients:encrypt-phi
 */
return new class extends Migration
{
    public function up(): void
    {
        // finance_bank_accounts — account_number (string), ifsc_code(15), upi_id(255)
        if (Schema::hasTable('finance_bank_accounts')) {
            Schema::table('finance_bank_accounts', function (Blueprint $table) {
                foreach (['account_number', 'ifsc_code', 'upi_id'] as $col) {
                    if (Schema::hasColumn('finance_bank_accounts', $col)) {
                        $table->longText($col)->nullable()->change();
                    }
                }
            });
        }

        // hr_staff_profiles — account_number(50), ifsc_code(20)
        if (Schema::hasTable('hr_staff_profiles')) {
            Schema::table('hr_staff_profiles', function (Blueprint $table) {
                foreach (['account_number', 'ifsc_code'] as $col) {
                    if (Schema::hasColumn('hr_staff_profiles', $col)) {
                        $table->longText($col)->nullable()->change();
                    }
                }
            });
        }

        // consultations — only the two string-typed note fields need widening;
        // every other encrypted field is already text/longText.
        if (Schema::hasTable('consultations')) {
            Schema::table('consultations', function (Blueprint $table) {
                foreach (['follow_up_note', 'risk_assessment'] as $col) {
                    if (Schema::hasColumn('consultations', $col)) {
                        $table->longText($col)->nullable()->change();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // NOTE: rolling back AFTER the backfill will TRUNCATE encrypted values.
        // Only roll back if you have not yet run patients:encrypt-phi.
        if (Schema::hasTable('finance_bank_accounts')) {
            Schema::table('finance_bank_accounts', function (Blueprint $table) {
                if (Schema::hasColumn('finance_bank_accounts', 'account_number')) {
                    $table->string('account_number')->nullable()->change();
                }
                if (Schema::hasColumn('finance_bank_accounts', 'ifsc_code')) {
                    $table->string('ifsc_code', 15)->nullable()->change();
                }
                if (Schema::hasColumn('finance_bank_accounts', 'upi_id')) {
                    $table->string('upi_id')->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('hr_staff_profiles')) {
            Schema::table('hr_staff_profiles', function (Blueprint $table) {
                if (Schema::hasColumn('hr_staff_profiles', 'account_number')) {
                    $table->string('account_number', 50)->nullable()->change();
                }
                if (Schema::hasColumn('hr_staff_profiles', 'ifsc_code')) {
                    $table->string('ifsc_code', 20)->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('consultations')) {
            Schema::table('consultations', function (Blueprint $table) {
                if (Schema::hasColumn('consultations', 'follow_up_note')) {
                    $table->string('follow_up_note', 300)->nullable()->change();
                }
                if (Schema::hasColumn('consultations', 'risk_assessment')) {
                    $table->string('risk_assessment')->nullable()->change();
                }
            });
        }
    }
};
