<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Missed Calls full-list page (Relationship Engine · Today's Actions).
 *
 * Adds two independent, additive "hide but don't destroy" pairs to
 * communication_queue, following the existing resolved_by/resolved_at
 * convention used by huddle_comments, billing_prompts, data_requests, and
 * dedup_candidates:
 *
 *   - ignored_at / ignored_by: a soft, reversible "never show this again in
 *     the missed-calls queue" flag. Separate from `status` because ignoring
 *     is a display-only exclusion, not a lifecycle change — the item is
 *     still "pending" underneath and can be un-ignored without resurrecting
 *     a closed/handled record.
 *
 *   - dismissed_at / dismissed_by: records WHO bulk-dismissed an item and
 *     WHEN, distinct from the generic `updated_at`/`last_modified_by` pair
 *     already on the table. Dismissing still flips status to 'closed' (the
 *     existing enum already used for "handled") — these columns exist so a
 *     bulk dismiss is auditable and distinguishable from any other way a
 *     queue item gets closed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_queue', function (Blueprint $table) {
            if (! Schema::hasColumn('communication_queue', 'ignored_at')) {
                $table->timestamp('ignored_at')->nullable()->after('last_modified_by');
            }
            if (! Schema::hasColumn('communication_queue', 'ignored_by')) {
                $table->unsignedBigInteger('ignored_by')->nullable()->after('ignored_at');
            }
            if (! Schema::hasColumn('communication_queue', 'dismissed_at')) {
                $table->timestamp('dismissed_at')->nullable()->after('ignored_by');
            }
            if (! Schema::hasColumn('communication_queue', 'dismissed_by')) {
                $table->unsignedBigInteger('dismissed_by')->nullable()->after('dismissed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('communication_queue', function (Blueprint $table) {
            foreach (['ignored_at', 'ignored_by', 'dismissed_at', 'dismissed_by'] as $col) {
                if (Schema::hasColumn('communication_queue', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
