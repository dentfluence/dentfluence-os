<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — Relationship Engine: TaskEngine
 *
 * Adds a nullable `relationship_id` FK to the tasks table.
 * Additive only — existing tasks are unaffected (relationship_id stays null).
 *
 * Auto-created tasks from TaskEngine::autoCreate() will have this set.
 * Hand-made tasks remain unlinked (null) unless staff explicitly links one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('relationship_id')
                  ->nullable()
                  ->after('patient_id')
                  ->constrained('relationships')
                  ->nullOnDelete();

            // Index to pull "all open tasks for a relationship" on the Relationship Profile
            $table->index('relationship_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['relationship_id']);
            $table->dropIndex(['relationship_id']);
            $table->dropColumn('relationship_id');
        });
    }
};
