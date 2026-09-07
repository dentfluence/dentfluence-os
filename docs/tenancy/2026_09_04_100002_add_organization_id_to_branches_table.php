<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenancy Step 2a — link branches to the tenant root.
 * ---------------------------------------------------
 * `branches` is the ONLY path from organization to branch-scoped data.
 * Everything else either carries organization_id directly or reaches it via
 * branch_id.
 *
 * DEFAULT 1 backfills the live rows in place: `branches` already holds
 * 'Main Clinic' (id 1, seeded 12 May) and any branch added since. The Step 3
 * backfill re-asserts this rather than relying on the default.
 *
 * The foreign key is safe to create here only because migration
 * 2026_09_04_100001 seeded organization id 1 before this file runs.
 *
 * No explicit ->index() is declared. Not because declaring both would duplicate
 * the index — Laravel emits the index first and MySQL reuses it for the foreign
 * key, so either spelling yields exactly one index, differing only in name. The
 * reason is the rule this retrofit applies ~80 times: a composite index LEADING
 * with organization_id already satisfies the foreign key by leftmost prefix. On
 * every spine table that will carry (organization_id, created_at) or
 * (organization_id, branch_id), a single-column index on organization_id is a
 * genuine redundant duplicate. One rule, applied without exception: never
 * declare an explicit ->index() on organization_id; the FK or the composite
 * always covers it.
 *
 * restrictOnDelete, not cascade — an organization row must never be able to
 * take a branch (and through it a clinic's entire history) with it. Org removal
 * is a soft delete plus a separate auth block, recorded in STEP4_DECISIONS.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('branches', 'organization_id')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->default(1)->after('id');

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('branches', 'organization_id')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }
};
