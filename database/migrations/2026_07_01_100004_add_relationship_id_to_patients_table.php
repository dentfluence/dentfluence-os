<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — Relationship Engine Foundation
 * Add nullable relationship_id FK to the existing `patients` table.
 *
 * ADDITIVE ONLY — no columns removed, no data changed.
 * Existing patients will have relationship_id = null until RelationshipEngine
 * backfills them (future phase or manual artisan command).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('relationship_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('relationships')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropForeign(['relationship_id']);
            $table->dropColumn('relationship_id');
        });
    }
};
