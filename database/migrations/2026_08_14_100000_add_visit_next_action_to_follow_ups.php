<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visit → Next Action (CEO workflow correction, 2026-08-14).
 *
 * The doctor records the reception next action ONCE, at the chair, on the
 * Visit Log. That produces a canonical `follow_ups` row — the same table the
 * Action Board ("Follow-up Calls") and the Daily Huddle Comm List already
 * read, and which already gates correctly on `due_date <= today`.
 *
 * Two columns only:
 *   treatment_visit_id — the idempotency key. Re-saving the visit reconciles
 *                        against this instead of inserting duplicates, and it
 *                        lets the Huddle suppress the old "book a follow-up"
 *                        prompt once the doctor has already answered it.
 *   created_by         — which doctor issued the instruction. follow_ups had
 *                        assigned_to and completed_by but no author.
 *
 * Deliberately NOT unique on treatment_visit_id: one visit may carry several
 * next actions (CEO decision, 2026-08-14), and follow_ups soft-deletes, so a
 * unique index would block re-creating an action the doctor removed and
 * re-added.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            if (!Schema::hasColumn('follow_ups', 'treatment_visit_id')) {
                $table->foreignId('treatment_visit_id')
                      ->nullable()
                      ->after('lead_id')
                      ->constrained('treatment_visits')
                      ->nullOnDelete();
            }

            if (!Schema::hasColumn('follow_ups', 'created_by')) {
                $table->foreignId('created_by')
                      ->nullable()
                      ->after('assigned_to')
                      ->constrained('users')
                      ->nullOnDelete();
            }
        });

        // Guarded so a partially-applied re-run does not die on a duplicate
        // key name. Drives both the idempotent reconcile and the Huddle's
        // "already scheduled by the doctor" lookup.
        if (!$this->hasIndex('follow_ups_visit_status_index')) {
            Schema::table('follow_ups', function (Blueprint $table) {
                $table->index(['treatment_visit_id', 'status'], 'follow_ups_visit_status_index');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('follow_ups_visit_status_index')) {
            Schema::table('follow_ups', function (Blueprint $table) {
                $table->dropIndex('follow_ups_visit_status_index');
            });
        }

        Schema::table('follow_ups', function (Blueprint $table) {
            if (Schema::hasColumn('follow_ups', 'treatment_visit_id')) {
                $table->dropConstrainedForeignId('treatment_visit_id');
            }
            if (Schema::hasColumn('follow_ups', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
        });
    }

    private function hasIndex(string $name): bool
    {
        return collect(Schema::getIndexes('follow_ups'))
            ->contains(fn ($index) => ($index['name'] ?? null) === $name);
    }
};
