<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slice 10 (performance) — composite index for the doctor-scoped appointment
 * queries that run on every booking, reschedule and edit.
 *
 * The double-booking / overlap guard (AppointmentService::overlapConflict) and
 * the reschedule guard filter appointments by:
 *     branch_id = ?  AND  doctor_id = ?  AND  appointment_date = ?
 * (then a time-interval condition on the reduced set).
 *
 * The existing indexes are (branch_id, appointment_date) and
 * (appointment_date, status) — neither has doctor_id as a usable leading key
 * alongside the date, so the overlap query could not narrow to a single
 * doctor's day via an index. This composite covers that exact triple; its
 * (branch_id) and (branch_id, doctor_id) prefixes are usable too.
 *
 * PURELY ADDITIVE and reversible — creates one index, drops/changes nothing.
 * Idempotent via the information_schema guard so a re-run is safe on a database
 * that already has it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (! $this->hasIndex('appointments', 'appointments_branch_doctor_date_index')) {
                $table->index(
                    ['branch_id', 'doctor_id', 'appointment_date'],
                    'appointments_branch_doctor_date_index'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_branch_doctor_date_index');
        });
    }

    /** MySQL-safe "does this index already exist" check (matches the 07-14 index migration). */
    private function hasIndex(string $table, string $index): bool
    {
        $conn = Schema::getConnection();
        $db   = $conn->getDatabaseName();

        return (bool) $conn->selectOne(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$db, $table, $index]
        );
    }
};
