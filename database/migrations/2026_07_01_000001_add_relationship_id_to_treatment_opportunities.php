<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — Relationship Engine.
 *
 * Adds a nullable relationship_id column to treatment_opportunities so that
 * opportunities created for existing patients can be linked to their
 * Relationship record. Additive only — existing rows default to null.
 *
 * ── Ordering fix (2026-07-02) ─────────────────────────────────────────────
 * This migration is dated 000001, which runs BEFORE the relationships table
 * is created (100000). Adding the FK here fails on a fresh build
 * ("1824 Failed to open the referenced table 'relationships'"), which broke
 * every database-backed test. The column is therefore added unconditionally,
 * but the FOREIGN KEY is only attached if `relationships` already exists.
 * On a fresh build the FK is attached later by the companion migration
 * 2026_07_02_600001_add_treatment_opportunities_relationship_fk. Editing this
 * already-run migration does NOT affect existing databases (migrations do not
 * re-run) — it only makes fresh builds order-safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('treatment_opportunities', 'relationship_id')) {
            Schema::table('treatment_opportunities', function (Blueprint $table) {
                $table->unsignedBigInteger('relationship_id')
                      ->nullable()
                      ->after('patient_id')
                      ->comment('Phase 4: links this opportunity to the patient\'s Relationship record.');

                $table->index('relationship_id');
            });
        }

        // Attach the FK only when the referenced table already exists and the
        // FK is not already present. On a fresh build relationships does not
        // yet exist here, so this is skipped and completed by 600001.
        if (Schema::hasTable('relationships') && ! $this->foreignKeyExists()) {
            Schema::table('treatment_opportunities', function (Blueprint $table) {
                $table->foreign('relationship_id')
                      ->references('id')
                      ->on('relationships')
                      ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if ($this->foreignKeyExists()) {
            Schema::table('treatment_opportunities', function (Blueprint $table) {
                $table->dropForeign(['relationship_id']);
            });
        }

        Schema::table('treatment_opportunities', function (Blueprint $table) {
            if (Schema::hasColumn('treatment_opportunities', 'relationship_id')) {
                try {
                    $table->dropIndex(['relationship_id']);
                } catch (\Throwable $e) {
                    // index may not exist on partial builds — ignore
                }
                $table->dropColumn('relationship_id');
            }
        });
    }

    /** Does the treatment_opportunities → relationships FK already exist? */
    private function foreignKeyExists(): bool
    {
        try {
            $rows = DB::select(
                "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'treatment_opportunities'
                   AND CONSTRAINT_NAME = 'treatment_opportunities_relationship_id_foreign'
                   AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                 LIMIT 1"
            );
            return ! empty($rows);
        } catch (\Throwable $e) {
            return false;
        }
    }
};
