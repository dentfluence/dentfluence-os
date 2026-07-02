<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase A — Make audit tables tamper-evident (Sprint 2).
 *
 * Adds `prev_hash` + `hash` columns to the model-backed audit tables so the
 * HashChained trait can chain them like consent_logs. Existing rows get NULL
 * hashes until you run `php artisan audit:verify --backfill`, which computes the
 * chain over all historical rows.
 *
 * finance_audit_log is intentionally excluded — it has no Eloquent model yet
 * (written via raw DB). It's a separate follow-up.
 *
 * RUN ORDER:  php artisan migrate  →  php artisan audit:verify --backfill
 */
return new class extends Migration
{
    private array $tables = ['audit_logs', 'billing_audit_logs', 'prescription_audit_logs'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'prev_hash')) {
                    $t->string('prev_hash', 64)->nullable()->after('id');
                }
                if (! Schema::hasColumn($table, 'hash')) {
                    $t->string('hash', 64)->nullable()->after('prev_hash');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table) {
                foreach (['hash', 'prev_hash'] as $col) {
                    if (Schema::hasColumn($table, $col)) {
                        $t->dropColumn($col);
                    }
                }
            });
        }
    }
};
