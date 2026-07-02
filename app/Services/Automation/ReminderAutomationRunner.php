<?php

namespace App\Services\Automation;

use App\Models\Appointment;
use App\Models\Task;
use App\Models\User;
use App\Services\Relationship\ActivityEngine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * ReminderAutomationRunner — Phase 2, Slice 5 (reminders cutover).
 *
 * The Automation-Engine-owned implementation of appointment reminder tasks
 * (tomorrow's appointments → a "Reminder call" task for reception today).
 *
 * WHY THIS EXISTS: the legacy App\Services\Relationship\AppointmentReminderEngine
 * hardcodes `created_by => null`, but tasks.created_by is NOT NULL — so it throws
 * and cannot actually create reminder tasks (see the pinned characterization test).
 * This runner is behaviourally identical to legacy's INTENT, with the bug fixed:
 * `created_by` is resolved to the system actor (codebase convention: Auth::id()
 * or the system user, id 1). Legacy is left untouched so its characterization
 * test (which pins the throw) stays valid when the flag is OFF.
 *
 * When automation.engine is ON, RunAppointmentReminders delegates here; when OFF,
 * the legacy engine runs exactly as before. One reminder path at a time — no
 * double-contact.
 */
class ReminderAutomationRunner
{
    /**
     * Generate reminder-call tasks for every appointment scheduled tomorrow.
     *
     * @return array{created:int, skipped:int}
     */
    public function generateAppointmentReminders(): array
    {
        $tomorrow  = now()->addDay()->toDateString();
        $createdBy = $this->systemActorId();
        $created   = 0;
        $skipped   = 0;

        Appointment::query()
            ->whereDate('appointment_date', $tomorrow)
            ->whereNotIn('status', ['cancelled', 'no_show', 'noshow'])
            ->with('patient')
            ->chunk(100, function ($appointments) use (&$created, &$skipped, $createdBy) {
                foreach ($appointments as $appointment) {
                    $patient = $appointment->patient ?? null;

                    if (! $patient) {
                        $skipped++;
                        continue;
                    }

                    // DEDUP / cooldown — one reminder per patient per day (same as legacy).
                    if ($this->alreadyReminded($patient->id, $patient->name)) {
                        $skipped++;
                        continue;
                    }

                    $appointmentTime = $appointment->appointment_time
                        ? ' at ' . $appointment->appointment_time
                        : '';

                    $task = Task::create([
                        'title'       => "Reminder call: {$patient->name}",
                        'category'    => 'call',
                        'status'      => 'pending',
                        'due_date'    => today(),
                        'patient_id'  => $patient->id,
                        'created_by'  => $createdBy, // ← the fix: valid system actor, never null
                        'branch_id'   => $appointment->branch_id ?? $patient->branch_id ?? 1,
                        'description' => "Auto-reminder. Appointment tomorrow{$appointmentTime}. " .
                                         "Patient: {$patient->name} | Phone: {$patient->phone}. " .
                                         "Appointment ID: #{$appointment->id}.",
                    ]);

                    $this->logToActivityEngine($patient, $appointment, $task);

                    $created++;
                }
            });

        Log::info('ReminderAutomationRunner run complete', [
            'created' => $created,
            'skipped' => $skipped,
            'date'    => today()->toDateString(),
        ]);

        return compact('created', 'skipped');
    }

    /**
     * Read-only shadow count: how many reminder tasks WOULD be created for
     * tomorrow's appointments right now. Writes nothing. Used by automation:parity.
     */
    public function previewCount(): int
    {
        $tomorrow = now()->addDay()->toDateString();
        $would    = 0;

        Appointment::query()
            ->whereDate('appointment_date', $tomorrow)
            ->whereNotIn('status', ['cancelled', 'no_show', 'noshow'])
            ->with('patient')
            ->chunk(100, function ($appointments) use (&$would) {
                foreach ($appointments as $appointment) {
                    $patient = $appointment->patient ?? null;
                    if (! $patient) {
                        continue;
                    }
                    if ($this->alreadyReminded($patient->id, $patient->name)) {
                        continue;
                    }
                    $would++;
                }
            });

        return $would;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Same dedup rule the legacy engine used: a reminder task today for this patient. */
    private function alreadyReminded(int $patientId, string $patientName): bool
    {
        return Task::where('category', 'call')
            ->whereDate('due_date', today())
            ->where('patient_id', $patientId)
            ->where('title', 'like', "Reminder call: {$patientName}%")
            ->exists();
    }

    /**
     * Resolve the creator for system-generated tasks.
     * Convention in this codebase: the authenticated user if present, else the
     * system user (id 1). Falls back to the lowest existing user id so it stays
     * valid even if id 1 was removed.
     */
    private function systemActorId(): int
    {
        return Auth::id()
            ?? (int) (User::where('role', 'admin')->min('id')
                ?? User::min('id')
                ?? 1);
    }

    /** Never throws — a logging failure must not block reminder generation. */
    private function logToActivityEngine($patient, Appointment $appointment, Task $task): void
    {
        try {
            app(ActivityEngine::class)->log(
                $patient,
                'reminder.task_created',
                null, // system action
                [
                    'appointment_id'   => $appointment->id,
                    'appointment_date' => $appointment->appointment_date,
                    'task_id'          => $task->id,
                    'source'           => 'automation_reminder_runner',
                ],
                $patient->relationship_id ?? null,
            );
        } catch (\Throwable $e) {
            Log::debug('ReminderAutomationRunner: ActivityEngine log failed', [
                'appointment_id' => $appointment->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }
}
