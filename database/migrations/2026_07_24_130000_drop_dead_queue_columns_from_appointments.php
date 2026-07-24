<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slice 11 (dead-code cleanup) — drop the two dead queue columns.
 *
 * `queue_position` and `estimated_wait_minutes` were added (2026_05_19) for a
 * queue/ETA feature that never shipped. Forensic + Slice-11 verification: they
 * are referenced ONLY in Appointment::$fillable — no reads, no writes, no API
 * resource, no report, no seeder, no mobile consumer. The corrective plan (I2)
 * explicitly approved their removal.
 *
 * Fully reversible — down() restores them exactly as originally defined
 * (unsignedSmallInteger, nullable). Guarded so a re-run is safe. `chair_number`,
 * added by the same original migration, IS live and is deliberately left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            foreach (['queue_position', 'estimated_wait_minutes'] as $col) {
                if (Schema::hasColumn('appointments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'queue_position')) {
                $table->unsignedSmallInteger('queue_position')->nullable()->after('completed_at');
            }
            if (! Schema::hasColumn('appointments', 'estimated_wait_minutes')) {
                $table->unsignedSmallInteger('estimated_wait_minutes')->nullable()->after('queue_position');
            }
        });
    }
};
