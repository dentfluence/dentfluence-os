<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRE Automation flags — recall call-outcome side effects (2026-07-05).
 *
 * "Wrong Number" / "Invalid Number" -> contact_invalid_at lets the recall
 * queries stop re-surfacing a patient whose phone number is known-bad,
 * without touching the phone column itself (staff can still see/fix it).
 *
 * "Deceased" -> automations_disabled_at is a hard, permanent stop for every
 * automated trigger (recall, birthday, reminders) on this
 * patient. Deliberately separate from the existing per-trigger cooldown
 * stamps (recall_no_visit_queued_at etc.) which are temporary and trigger-
 * specific; this one is global and does not expire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->timestamp('contact_invalid_at')->nullable()->after('phone');
            $table->string('contact_invalid_reason')->nullable()->after('contact_invalid_at');

            // Anchor updated 2026-07-05: originally ->after('recall_anniversary_queued_at'),
            // but that column's migration (100001) was no-op'd when the Anniversary
            // Reminder feature was removed the same day, before either migration ever
            // ran. Re-anchored to recall_birthday_queued_at (the last real cooldown-stamp
            // column that does exist) so this migration doesn't reference a column that
            // will never be created.
            $table->timestamp('automations_disabled_at')->nullable()->after('recall_birthday_queued_at');
            $table->string('automations_disabled_reason')->nullable()->after('automations_disabled_at');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn([
                'contact_invalid_at',
                'contact_invalid_reason',
                'automations_disabled_at',
                'automations_disabled_reason',
            ]);
        });
    }
};
