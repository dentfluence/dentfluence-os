<?php

namespace App\Services\Smoke\Journeys;

use App\Enums\AppointmentStatus;
use App\Models\Activity;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\Automation\ReminderAutomationRunner;
use App\Services\PatientService;
use App\Services\Smoke\SmokeRun;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Appointments smoke journey (frozen module V1.0).
 *
 * TEST INFRASTRUCTURE ONLY — every write goes through the frozen canonical
 * AppointmentService (the module's write path); statuses are asserted against
 * the canonical AppointmentStatus enum; lifecycle events are counted in the
 * activities ledger the AppointmentActivityLogger writes. No overlap math,
 * status rules or reminder logic is re-implemented here.
 *
 * Safety: smoke appointments are booked ~180 days out at 07:00–08:00 so they
 * never collide with, or appear amongst, real near-term bookings. Reminder
 * generation (which targets TOMORROW's appointments) is only exercised in
 * rollback mode; commit mode uses the read-only previewCount().
 */
class AppointmentsSmokeJourney
{
    private const J = 'Appointments';

    public function __construct(
        private readonly AppointmentService $appointments,
        private readonly PatientService $patients,
        private readonly ReminderAutomationRunner $reminders,
    ) {
    }

    public function run(SmokeRun $run, User $actor, ?Patient $patient = null): void
    {
        $m = $run->marker();

        // Outbound-communication tripwire: snapshot queue tables before acting.
        $commsBefore = $this->outboundCounts();

        // Patient: reuse the Patients-journey adult, or mint one canonically.
        if (! $patient) {
            $patient = $this->patients->register([
                'first_name' => $m, 'last_name' => 'ApptOnly',
                'gender' => 'male', 'date_of_birth' => now()->subYears(30)->toDateString(),
                'phone' => $run->phone(9),
            ], $actor);
            $run->track($patient, "patient #{$patient->id} ({$m} ApptOnly)");
        }

        $doctor = User::where('is_active', true)->where('role', 'doctor')->first() ?? $actor;
        $date1  = now()->addDays(180)->toDateString();
        $date2  = now()->addDays(181)->toDateString();

        // ── 1–3. Create; exactly one row; every field persisted ──────────────
        $appt = $this->appointments->create([
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => $date1,
            'appointment_time' => '07:00',
            'duration_minutes' => 30,
            'type'             => 'consultation',
            'notes'            => "{$m} appointment 1",
        ], $actor);
        $run->track($appt, "appointment #{$appt->id} ({$m} #1)");

        $run->check(self::J, 'Appointment created via AppointmentService::create()', $appt->exists, SmokeRun::CRITICAL);
        $run->check(
            self::J,
            'Exactly one appointment row written (no duplicate booking)',
            Appointment::where('notes', "{$m} appointment 1")->count() === 1,
            SmokeRun::CRITICAL
        );

        $f = Appointment::find($appt->id);
        $run->check(
            self::J,
            'Patient / doctor / branch / date / time / duration persisted correctly',
            $f
                && (int) $f->patient_id === (int) $patient->id
                && (int) $f->doctor_id === (int) $doctor->id
                && (int) $f->branch_id === (int) $actor->branch_id
                && $f->appointment_date->toDateString() === $date1
                && substr((string) $f->appointment_time, 0, 5) === '07:00'
                && (int) $f->duration_minutes === 30,
            SmokeRun::CRITICAL
        );
        $run->check(
            self::J,
            'Lifecycle event appointment.booked recorded exactly once',
            $this->eventCount($appt->id, 'appointment.booked') === 1,
            SmokeRun::CRITICAL
        );

        // ── 4–5. Conflicting booking must be rejected and write nothing ──────
        $rejected = false;
        try {
            $dup = $this->appointments->create([
                'patient_id'       => $patient->id,
                'doctor_id'        => $doctor->id,
                'appointment_date' => $date1,
                'appointment_time' => '07:00',
                'duration_minutes' => 30,
                'notes'            => "{$m} conflict attempt",
            ], $actor);
            $run->track($dup, "appointment #{$dup->id} ({$m} unexpected conflict write)");
        } catch (ValidationException) {
            $rejected = true;
        }
        $run->check(self::J, 'Conflicting appointment rejected by the overlap guard', $rejected, SmokeRun::CRITICAL);
        $run->check(
            self::J,
            'Conflict attempt wrote no row (no duplicate booking record)',
            Appointment::where('notes', "{$m} conflict attempt")->count() === 0,
            SmokeRun::CRITICAL
        );

        // ── 6–8. Reschedule moves the SAME row; event exactly once ───────────
        $this->appointments->reschedule($appt, [
            'appointment_date' => $date1,
            'appointment_time' => '08:00',
        ], $actor);

        $f = Appointment::find($appt->id);
        $run->check(
            self::J,
            'Reschedule moved the SAME appointment (id kept, new slot persisted)',
            $f && substr((string) $f->appointment_time, 0, 5) === '08:00'
                && $f->appointment_date->toDateString() === $date1,
            SmokeRun::CRITICAL
        );
        $run->check(
            self::J,
            'Reschedule created no duplicate row',
            Appointment::where('notes', "{$m} appointment 1")->count() === 1,
            SmokeRun::CRITICAL
        );
        $run->check(
            self::J,
            'Lifecycle event appointment.rescheduled recorded exactly once',
            $this->eventCount($appt->id, 'appointment.rescheduled') === 1,
            SmokeRun::CRITICAL
        );

        // ── 9–11. Check-in persists; status representation is canonical ──────
        $this->appointments->updateStatus($appt, 'checkin', $actor);
        $f = Appointment::find($appt->id);
        $run->check(
            self::J,
            'Check-in persisted (status + checked_in_at)',
            $f && $f->status === AppointmentStatus::CheckIn->value && $f->checked_in_at !== null,
            SmokeRun::CRITICAL
        );
        $run->check(
            self::J,
            'Status value is canonical (parses via AppointmentStatus enum)',
            $f && AppointmentStatus::tryFrom((string) $f->status) !== null,
            SmokeRun::CRITICAL
        );
        $run->check(
            self::J,
            'Lifecycle event appointment.checked_in recorded exactly once',
            $this->eventCount($appt->id, 'appointment.checked_in') === 1
        );

        // ── 12–13. No-show on a separate test appointment ────────────────────
        $appt2 = $this->appointments->create([
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => $date2,
            'appointment_time' => '07:00',
            'duration_minutes' => 30,
            'notes'            => "{$m} appointment 2",
        ], $actor);
        $run->track($appt2, "appointment #{$appt2->id} ({$m} #2)");

        $this->appointments->updateStatus($appt2, 'no_show', $actor);
        $f2 = Appointment::find($appt2->id);
        $run->check(
            self::J,
            'No-show persisted with canonical status',
            $f2 && $f2->status === AppointmentStatus::NoShow->value,
            SmokeRun::CRITICAL
        );
        $run->check(
            self::J,
            'Lifecycle event appointment.missed recorded exactly once',
            $this->eventCount($appt2->id, 'appointment.missed') === 1
        );

        // ── 14–17. Cancel with reason, then revert; events exactly once ──────
        $appt3 = $this->appointments->create([
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => $date2,
            'appointment_time' => '08:00',
            'duration_minutes' => 30,
            'notes'            => "{$m} appointment 3",
        ], $actor);
        $run->track($appt3, "appointment #{$appt3->id} ({$m} #3)");

        $this->appointments->cancel($appt3, "{$m} cancellation", 'clinic', $actor);
        $f3 = Appointment::find($appt3->id);
        $run->check(
            self::J,
            'Cancellation persisted (status + reason + party)',
            $f3 && $f3->status === AppointmentStatus::Cancelled->value
                && $f3->cancel_reason === "{$m} cancellation"
                && $f3->cancelled_party === 'clinic',
            SmokeRun::CRITICAL
        );
        $run->check(
            self::J,
            'Lifecycle event appointment.cancelled recorded exactly once',
            $this->eventCount($appt3->id, 'appointment.cancelled') === 1
        );

        $this->appointments->revert($appt3, $actor);
        $f3 = Appointment::find($appt3->id);
        $run->check(
            self::J,
            'Revert restored the previous status and cleared cancel bookkeeping',
            $f3 && $f3->status === AppointmentStatus::Scheduled->value
                && $f3->previous_status === null && $f3->cancel_reason === null,
            SmokeRun::CRITICAL
        );
        $run->check(
            self::J,
            'Lifecycle event appointment.reverted recorded exactly once (cancel not double-logged)',
            $this->eventCount($appt3->id, 'appointment.reverted') === 1
                && $this->eventCount($appt3->id, 'appointment.cancelled') === 1
        );

        // ── 18–20. Hide from calendar persists and read scope excludes it ────
        $this->appointments->hide($appt2);
        $f2 = Appointment::find($appt2->id);
        $run->check(self::J, 'hidden_from_calendar persisted', (bool) $f2?->hidden_from_calendar, SmokeRun::CRITICAL);
        $run->check(
            self::J,
            'Calendar read scope (visibleOnCalendar) no longer exposes the hidden appointment',
            ! Appointment::visibleOnCalendar()->whereKey($appt2->id)->exists()
        );

        // ── 21–22. Delete a disposable appointment; soft delete + event ──────
        $this->appointments->delete($appt3, $actor);
        $run->check(
            self::J,
            'Deletion behaved as designed (soft delete: gone from reads, kept with deleted_at)',
            Appointment::find($appt3->id) === null
                && Appointment::withTrashed()->find($appt3->id)?->deleted_at !== null,
            SmokeRun::CRITICAL
        );
        $run->check(
            self::J,
            'Lifecycle event appointment.deleted recorded exactly once',
            $this->eventCount($appt3->id, 'appointment.deleted') === 1
        );

        // ── Reminder generation stays idempotent ─────────────────────────────
        if ($run->mode === SmokeRun::MODE_ROLLBACK) {
            // Real generator, run twice — everything is rolled back afterwards.
            $this->reminders->generateAppointmentReminders();
            $second = $this->reminders->generateAppointmentReminders();
            $run->check(
                self::J,
                'Reminder generation idempotent (second run creates 0 duplicates)',
                ($second['created'] ?? -1) === 0,
                SmokeRun::WORKFLOW,
                'second pass: ' . json_encode($second)
            );
        } else {
            // Commit mode must not create reminder tasks for REAL appointments:
            // use the read-only preview instead.
            $p1 = $this->reminders->previewCount();
            $p2 = $this->reminders->previewCount();
            $run->check(
                self::J,
                'Reminder preview stable and side-effect free (commit mode)',
                $p1 === $p2,
                SmokeRun::WORKFLOW,
                'generator not executed in commit mode by design'
            );
        }

        // ── No outbound patient communication was produced ───────────────────
        $run->check(
            self::J,
            'WhatsApp disabled / dry-run enforced during smoke',
            config('whatsapp.enabled') === false || config('whatsapp.dry_run') === true,
            SmokeRun::TECHNICAL
        );
        $run->check(
            self::J,
            'No outbound messages queued or sent by the smoke run',
            $this->outboundCounts() === $commsBefore,
            SmokeRun::CRITICAL,
            'wa_messages / communication_queue row counts changed during the journey'
        );

        // Commit-mode cleanup: lifecycle activity rows for the smoke appointments.
        $apptIds = [$appt->id, $appt2->id, $appt3->id];
        $run->onCleanup(function () use ($apptIds) {
            Activity::where('subject_type', Appointment::class)
                ->whereIn('subject_id', $apptIds)->delete();
        });
    }

    /** Activities-ledger count for one lifecycle event on one appointment. */
    private function eventCount(int $appointmentId, string $event): int
    {
        return Activity::where('subject_type', Appointment::class)
            ->where('subject_id', $appointmentId)
            ->where('event', $event)
            ->count();
    }

    /** Row counts of the outbound-communication tables (tripwire snapshot). */
    private function outboundCounts(): array
    {
        $counts = [];
        foreach (['wa_messages', 'communication_queue'] as $table) {
            if (Schema::hasTable($table)) {
                $counts[$table] = (int) DB::table($table)->count();
            }
        }

        return $counts;
    }
}
