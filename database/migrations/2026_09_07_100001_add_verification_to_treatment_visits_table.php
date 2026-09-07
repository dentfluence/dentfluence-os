<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visit verification — the control layer under the doctor payout module.
 *
 * A visit is a claim about work done. Until someone with authority marks it
 * verified it stays a claim, and only verified work may ever reach a payout
 * (the payout model pays on BILLED work, so an unverified or back-dated visit
 * must not be billable-and-forgotten).
 *
 * Deliberately NOT a status enum value: `status` describes the CLINICAL state
 * of the visit (scheduled / in_chair / completed / cancelled / no_show) and is
 * written by the doctor. Verification is an ADMINISTRATIVE fact written by a
 * different person at a different time — one writer per fact, so it gets its
 * own columns.
 *
 * Nullable and with no default, so every existing row reads "not verified"
 * and nothing changes until the clinic starts verifying.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_visits', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable()->after('status');
            $table->unsignedBigInteger('verified_by')->nullable()->after('verified_at');
            $table->string('verification_note', 255)->nullable()->after('verified_by');

            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();

            // The unverified list is the screen this feature exists for, and it
            // filters on exactly this: verified_at IS NULL, newest first.
            $table->index(['verified_at', 'visit_date'], 'tv_verified_visit_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('treatment_visits', function (Blueprint $table) {
            $table->dropIndex('tv_verified_visit_date_idx');
            $table->dropForeign(['verified_by']);
            $table->dropColumn(['verified_at', 'verified_by', 'verification_note']);
        });
    }
};
