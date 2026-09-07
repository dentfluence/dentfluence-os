<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W-9 — a cancellation has to end somewhere.
 *
 * `appointments` already carries cancel_reason (free text) and cancelled_party,
 * and it keeps them: this table does not replace them, it records what was
 * DECIDED. Those two columns answer "why did this appointment die"; this table
 * answers "and then what happened to the patient", which nothing answered before.
 *
 * Why a separate table and not more columns on `appointments`:
 *  1. `appointments` is already 25+ fillable fields and is the hottest read in
 *     the app — the calendar loads it for a whole month at a time.
 *  2. An appointment can be cancelled, reverted (previous_status exists for
 *     exactly that) and cancelled again. Columns would overwrite; rows keep the
 *     history, and the history is the point — a patient cancelled twice is a
 *     different problem from a patient cancelled once.
 *  3. The recovery rate (rebooked or called back ÷ cancelled) is a GROUP BY on
 *     one small table instead of a scan of the big one.
 *
 * Deliberately NOT unique on appointment_id: see (2). Latest row wins for
 * display, all rows count for analytics.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_cancellations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('appointment_id')
                  ->constrained('appointments')
                  ->cascadeOnDelete();

            // Denormalised on purpose. Both are read by every analytics query and
            // neither can change for a given cancellation, so joining back to
            // `appointments` to fetch them buys nothing. patient_id is nullable
            // only because the appointments table allows a walk-in shell record.
            $table->foreignId('patient_id')
                  ->nullable()
                  ->constrained('patients')
                  ->nullOnDelete();

            $table->unsignedBigInteger('branch_id');

            // Structured reason. String, not enum: the list belongs to the clinic
            // and will change (App\Enums\CancellationReason is the source of truth
            // and the validation rule). An enum column would need a migration
            // every time Sumit adds a reason — that is a schema change for a
            // vocabulary change, which is the wrong shape.
            $table->string('reason_code', 40);

            // Whatever reception typed in addition. The dropdown is what gets
            // counted; this is what gets read when a number looks strange.
            $table->string('reason_note', 500)->nullable();

            $table->enum('cancelled_party', ['patient', 'clinic']);

            /**
             * The whole reason this row exists.
             *
             * callback      — reception will phone the patient on callback_date;
             *                 task_id points at the row in that day's task list.
             * not_returning — the patient said no. A real, countable answer.
             * rebooked      — a new appointment was made in the same breath;
             *                 rebooked_appointment_id points at it.
             * unspecified   — the caller did not say. The mobile API (M-9) still
             *                 posts the old two-field body, so it lands here
             *                 rather than being refused. Every `unspecified` row
             *                 is a patient nobody decided about: that count is a
             *                 KPI, not a null.
             */
            $table->enum('outcome', ['callback', 'not_returning', 'rebooked', 'unspecified'])
                  ->default('unspecified');

            $table->date('callback_date')->nullable();

            $table->foreignId('task_id')
                  ->nullable()
                  ->constrained('tasks')
                  ->nullOnDelete();

            $table->foreignId('rebooked_appointment_id')
                  ->nullable()
                  ->constrained('appointments')
                  ->nullOnDelete();

            $table->foreignId('cancelled_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamp('cancelled_at');

            $table->timestamps();

            // The recovery-rate query: outcome mix over a date window, per branch.
            $table->index(['branch_id', 'cancelled_at'], 'apptcancel_branch_date_idx');
            $table->index(['outcome', 'cancelled_at'], 'apptcancel_outcome_date_idx');
            $table->index(['reason_code', 'cancelled_at'], 'apptcancel_reason_date_idx');
            // "Show me this appointment's latest cancellation" on the quick-view card.
            $table->index(['appointment_id', 'cancelled_at'], 'apptcancel_appt_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_cancellations');
    }
};
