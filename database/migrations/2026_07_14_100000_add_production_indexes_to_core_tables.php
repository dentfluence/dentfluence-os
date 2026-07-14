<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production hardening 2026-07-14 — performance indexes on the core tables.
 *
 * The older core tables (patients, appointments, communication_queue) predate
 * the indexing discipline used in the newer ones (activities, audit_logs,
 * invoices) and were never retrofitted. Every phone lookup, calendar render,
 * reports page and action-board load was doing a full table scan.
 *
 * PURELY ADDITIVE — this migration only creates indexes. It drops no columns,
 * changes no types, and deletes no data. Safe to run on live data.
 *
 * NOTE on patients.phone: a UNIQUE index is deliberately NOT added here.
 * Families legitimately share one mobile number, and the existing data may
 * already contain intentional duplicates — a plain index gives the performance
 * win without risking a failed migration or blocking valid records.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── patients ────────────────────────────────────────────────────────
        // phone: used by dedup, search, missed-call matching. No index at all.
        Schema::table('patients', function (Blueprint $table) {
            if (! $this->hasIndex('patients', 'patients_phone_index')) {
                $table->index('phone', 'patients_phone_index');
            }
            if (! $this->hasIndex('patients', 'patients_branch_id_phone_index')) {
                $table->index(['branch_id', 'phone'], 'patients_branch_id_phone_index');
            }
        });

        // ── appointments ────────────────────────────────────────────────────
        // appointment_date / status carry every calendar, huddle and report
        // query; only the FK columns were indexed.
        Schema::table('appointments', function (Blueprint $table) {
            if (! $this->hasIndex('appointments', 'appointments_branch_date_index')) {
                $table->index(['branch_id', 'appointment_date'], 'appointments_branch_date_index');
            }
            if (! $this->hasIndex('appointments', 'appointments_date_status_index')) {
                $table->index(['appointment_date', 'status'], 'appointments_date_status_index');
            }
        });

        // ── communication_queue ─────────────────────────────────────────────
        // One of the busiest tables (action board / missed calls) with no
        // supporting index on any of its filter columns.
        if (Schema::hasTable('communication_queue')) {
            Schema::table('communication_queue', function (Blueprint $table) {
                if (! $this->hasIndex('communication_queue', 'commq_status_index')) {
                    $table->index('status', 'commq_status_index');
                }
                if (Schema::hasColumn('communication_queue', 'due_at')
                    && ! $this->hasIndex('communication_queue', 'commq_status_due_index')) {
                    $table->index(['status', 'due_at'], 'commq_status_due_index');
                }
                if (! $this->hasIndex('communication_queue', 'commq_patient_id_index')) {
                    $table->index('patient_id', 'commq_patient_id_index');
                }
                if (Schema::hasColumn('communication_queue', 'phone')
                    && ! $this->hasIndex('communication_queue', 'commq_phone_index')) {
                    $table->index('phone', 'commq_phone_index');
                }
            });
        }

        // ── invoices ────────────────────────────────────────────────────────
        // "invoices for this appointment / plan" lookups had no index.
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'appointment_id')
                && ! $this->hasIndex('invoices', 'invoices_appointment_id_index')) {
                $table->index('appointment_id', 'invoices_appointment_id_index');
            }
            if (Schema::hasColumn('invoices', 'treatment_plan_id')
                && ! $this->hasIndex('invoices', 'invoices_treatment_plan_id_index')) {
                $table->index('treatment_plan_id', 'invoices_treatment_plan_id_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex('patients_phone_index');
            $table->dropIndex('patients_branch_id_phone_index');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_branch_date_index');
            $table->dropIndex('appointments_date_status_index');
        });

        if (Schema::hasTable('communication_queue')) {
            Schema::table('communication_queue', function (Blueprint $table) {
                $table->dropIndex('commq_status_index');
                $table->dropIndex('commq_status_due_index');
                $table->dropIndex('commq_patient_id_index');
                $table->dropIndex('commq_phone_index');
            });
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_appointment_id_index');
            $table->dropIndex('invoices_treatment_plan_id_index');
        });
    }

    /**
     * MySQL-safe "does this index already exist" check, so re-running the
     * migration on a database that partially has these indexes won't fail.
     */
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
