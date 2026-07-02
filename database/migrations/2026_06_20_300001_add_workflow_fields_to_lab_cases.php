<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab Module v3 — Extended workflow fields
 *
 * Adds per-step date tracking, trial loop counter, and repeat-work reason
 * to support the full 7-step lab workflow:
 *   draft → order_placed → impression_sent|scan_sent → trial_received
 *   → trial_returned → final_received → complete
 *
 * Also adds is_repeat_work flag (replaces is_remake for clarity) and
 * repeat_reason so KPI can track why remakes happen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_cases', function (Blueprint $table) {

            // ── Per-step dates (for turnaround analytics per stage) ──────
            $table->date('order_placed_date')->nullable()->after('sent_date');
            $table->date('impression_sent_date')->nullable()->after('order_placed_date');
            $table->date('final_received_date')->nullable()->after('received_date');

            // ── Trial loop tracking ───────────────────────────────────────
            // Increments each time status moves to trial_received
            $table->unsignedTinyInteger('trial_round')->default(0)->after('final_received_date');

            // ── Repeat / remake work tracking ─────────────────────────────
            // repeat_reason: shade_mismatch | fit_issue | lab_error | patient_changed_mind | doctor_adjustment | other
            $table->string('repeat_reason', 50)->nullable()->after('remake_of_id');

            // ── Task link — last auto-created task for this case ─────────
            $table->unsignedBigInteger('active_task_id')->nullable()->after('expense_id');
        });
    }

    public function down(): void
    {
        Schema::table('lab_cases', function (Blueprint $table) {
            $table->dropColumn([
                'order_placed_date',
                'impression_sent_date',
                'final_received_date',
                'trial_round',
                'repeat_reason',
                'active_task_id',
            ]);
        });
    }
};
