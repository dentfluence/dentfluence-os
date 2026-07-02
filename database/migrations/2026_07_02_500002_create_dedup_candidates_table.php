<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 (Workstream A/G) — Deduplication review queue.
 *
 * When identity resolution finds that two relationships MIGHT be the same
 * person, a candidate row is queued here for HUMAN review before any merge.
 * Ambiguous matches are never auto-merged (clinical-safety rule).
 *
 * Additive, non-destructive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dedup_candidates')) {
            return;
        }

        Schema::create('dedup_candidates', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('relationship_id')->index();
            $table->unsignedBigInteger('candidate_relationship_id')->index();

            $table->string('match_reason');                 // phone | email | name_dob
            $table->unsignedTinyInteger('confidence')->nullable(); // 0..100
            $table->string('status')->default('pending');    // pending | merged | dismissed

            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();

            $table->timestamps();

            // One candidate pairing per (relationship, candidate).
            $table->unique(['relationship_id', 'candidate_relationship_id'], 'dedup_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dedup_candidates');
    }
};
