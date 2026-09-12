<?php

use App\Services\Relationship\TodayActionOptions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A call that never connected must not complete an action.
 *
 * Production was seeded on 3 Jul 2026, when every call_outcome row defaulted
 * to closes_task = 1. The per-outcome values corrected on 8/10 Jul only ever
 * existed in the local database, so on prod "No answer" wrote a dismissal row
 * and the patient rendered as Done — a call that never happened, marked
 * handled (found 12 Sep 2026 on appointment_reminders).
 *
 * TodayActionOptions::isNonContact() now enforces this in code regardless of
 * the stored value; this migration aligns the stored value so Settings and
 * the drawer's "Marks this action complete" hint tell the same truth.
 *
 * Non-destructive: it flips one boolean on outcome rows that never legitimately
 * close an action. Labels, notes rules and every other outcome are untouched,
 * and a clinic can still edit these rows in Settings > Call Outcomes.
 * 'wrong_number' / 'invalid_number' are deliberately excluded — they DO
 * resolve the action.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('action_option_lists')
            ->where('option_type', 'call_outcome')
            ->whereIn('key', TodayActionOptions::nonContactKeys())
            ->update(['closes_task' => false]);
    }

    public function down(): void
    {
        // Deliberately empty. The previous per-row values were the seed bug
        // this migration corrects; restoring them would reintroduce it, and
        // they are not recoverable per-row anyway. Edit individual outcomes
        // in Settings > Call Outcomes if a clinic genuinely wants one back.
    }
};
