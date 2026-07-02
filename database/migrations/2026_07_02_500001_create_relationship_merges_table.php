<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 (Workstream A/G) — Relationship merge history.
 *
 * Records every merge so it is auditable AND reversible. `reassignments`
 * stores, per table, the exact row ids moved from the merged relationship to
 * the surviving one; `snapshot` stores the merged relationship's attributes.
 * `undone_at` marks a reversed merge.
 *
 * Additive, non-destructive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('relationship_merges')) {
            return;
        }

        Schema::create('relationship_merges', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('surviving_relationship_id')->index();
            $table->unsignedBigInteger('merged_relationship_id')->index();

            $table->string('reason')->nullable();          // manual | backfill | dedup_review
            $table->json('reassignments')->nullable();      // { table: [ids...] }
            $table->json('snapshot')->nullable();           // merged relationship attributes

            $table->unsignedBigInteger('merged_by')->nullable();  // user id, if a human did it
            $table->dateTime('undone_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relationship_merges');
    }
};
