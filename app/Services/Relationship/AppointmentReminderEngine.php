<?php

namespace App\Services\Relationship;

use App\Models\Appointment;
use App\Models\Task;
use Illuminate\Support\Facades\Log;

/**
 * AppointmentReminderEngine — Phase 4, Relationship Engine.
 *
 * Runs daily at 8:00am (via scheduler in routes/console.php).
 *
 * What it does:
 *   Finds every appointment scheduled for TOMORROW (status not cancelled/no-show),
 *   and auto-creates a "Reminder call" Task for today so reception knows to call.
 *
 * Deduplication:
 *   Before creating, checks whether a reminder Task already exists for the same
 *   appointment_id on today's date. Re-runs are safe.
 *
 * ActivityEngine:
 *   Logs 'reminder.task_created' for each task created, linked to the patient's
 *   relationship_id when available.
 *
 * @method void generateReminders()
 */
class AppointmentReminderEngine
{
    /**
     * Generate appointment reminder tasks for all appointments tomorrow.
     *
     * @return array{created: int, skipped: int}  Summary counts for logging/display.
     */
    public function generateReminders(): array
    {
        $tomorrow = now()->addDay()->toDateString();
        $created  = 0;
        $skipped  = 0;

        Appointment::query()
            ->whereDate('appointment_date', $tomorrow)
            ->whereNotIn('status', ['cancelled', 'no_show', 'noshow'])
            ->with('patient')
            ->chunk(100, function ($appointments) use (&$created, &$skipped) {
                foreach ($appointments as $appointment) {
                    $patient = $appointment->patient ?? null;

                    if (! $patient) {
                        $skipped++;
                        continue;
                    }

                    // ── Deduplication check ────────────────────────────────────
                    // Do not create a second reminder task for the same patient
                    // on the same day (safe to re-run at any time).
                    $alreadyExists = Task::where('category', 'call')
                        ->whereDate('due_date', today())
                        ->where('patient_id', $patient->id)
                        ->where('title', 'like', "Reminder call: {$patient->name}%")
                        ->exists();

                    if ($alreadyExists) {
                        $skipped++;
                        continue;
                    }

                    // ── Create the reminder task ───────────────────────────────
                    $appointmentTime = $appointment->appointment_time
                        ? ' at ' . $appointment->appointment_time
                        : '';

                    $task = Task::create([
                        'title'       => "Reminder call: {$patient->name}",
                        'category'    => 'call',
                        'status'      => 'pending',
                        'due_date'    => today(),
                        'patient_id'  => $patient->id,
                        'created_by'  => null,  // system-generated
                        'description' => "Auto-reminder. Appointment tomorrow{$appointmentTime}. " .
                                         "Patient: {$patient->name} | Phone: {$patient->phone}. " .
                                         "Appointment ID: #{$appointment->id}.",
                    ]);

                    // ── Log to ActivityEngine ──────────────────────────────────
                    $this->logToActivityEngine($patient, $appointment, $task);

                    $created++;
                }
            });

        Log::info('AppointmentReminderEngine run complete', [
            'created' => $created,
            'skipped' => $skipped,
            'date'    => today()->toDateString(),
        ]);

        return compact('created', 'skipped');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Log the reminder task creation to ActivityEngine.
     * Never throws — a logging failure must not block reminder generation.
     */
    private function logToActivityEngine($patient, Appointment $appointment, Task $task): void
    {
        try {
            app(ActivityEngine::class)->log(
                $patient,
                'reminder.task_created',
                null,  // system action
                [
                    'appointment_id'   => $appointment->id,
                    'appointment_date' => $appointment->appointment_date,
                    'task_id'          => $task->id,
                    'source'           => 'appointment_reminder_engine',
                ],
                $patient->relationship_id ?? null,
            );
        } catch (\Throwable $e) {
            Log::debug('AppointmentReminderEngine: ActivityEngine log failed', [
                'appointment_id' => $appointment->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }
}
