<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W-10 (2026-09-10). A Today's Actions row closed from the board came back
 * the next morning: the suppression row was date-scoped by design ("not
 * today"), so "Stop chasing", "Not needed" and every closes_task outcome
 * held for one day on the ten live-computed categories. Measured on the
 * clinic's own data — lab case #5 was closed "booked pickup" on 25 Aug,
 * 26 Aug and 9 Sep.
 *
 * `is_permanent` keeps the row out of the board on every later day too.
 * `dismissed_for_date` stays as the day it was handled (audit + the faded
 * "done today" render), and the unique index is untouched. A permanent
 * suppression is lifted when the date that drives the row moves — see
 * App\Observers\TodayActionDismissalLiftObserver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('today_action_dismissals', function (Blueprint $table) {
            if (! Schema::hasColumn('today_action_dismissals', 'is_permanent')) {
                $table->boolean('is_permanent')->default(false)->after('dismissed_for_date');
                $table->index(['category', 'subject_type', 'is_permanent'], 'today_action_dismissals_permanent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('today_action_dismissals', function (Blueprint $table) {
            if (Schema::hasColumn('today_action_dismissals', 'is_permanent')) {
                $table->dropIndex('today_action_dismissals_permanent');
                $table->dropColumn('is_permanent');
            }
        });
    }
};
