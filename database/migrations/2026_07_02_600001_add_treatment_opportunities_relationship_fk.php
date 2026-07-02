<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Companion to the ordering fix in
 * 2026_07_01_000001_add_relationship_id_to_treatment_opportunities.
 *
 * On a FRESH build, the 000001 migration adds the relationship_id column but
 * skips the foreign key (relationships does not exist yet at that point). This
 * migration — which runs AFTER relationships is created — attaches the FK.
 *
 * Idempotent and non-destructive:
 *   - On an EXISTING database the FK is already present (added historically by
 *     000001), so this detects it and does nothing.
 *   - On a fresh build it attaches the FK now.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('treatment_opportunities')
            && Schema::hasTable('relationships')
            && Schema::hasColumn('treatment_opportunities', 'relationship_id')
            && ! $this->foreignKeyExists()
        ) {
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
    }

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
