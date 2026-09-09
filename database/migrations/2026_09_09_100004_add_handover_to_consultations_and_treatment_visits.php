<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N-2 — Doctor handover (2026-09-09).
 *
 * The doctor's message to the front desk for THIS patient, right now:
 * collect ₹X · offer AOCP · take an X-ray · book the next visit in N days ·
 * a free line. Saved with the same click as the consultation or visit; it
 * becomes the text of the popup the desk sees (N-1 ChairsideNotifier).
 *
 * One JSON column on each of the two chairside records. Not a table: a
 * handover has no life of its own — it is read once, at the desk, minutes
 * later. See App\Support\Handover for the shape and the one-line summary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->json('handover')->nullable()->after('follow_up_note');
        });

        Schema::table('treatment_visits', function (Blueprint $table) {
            $table->json('handover')->nullable()->after('next_visit_type');
        });
    }

    public function down(): void
    {
        Schema::table('consultations', fn (Blueprint $t) => $t->dropColumn('handover'));
        Schema::table('treatment_visits', fn (Blueprint $t) => $t->dropColumn('handover'));
    }
};
