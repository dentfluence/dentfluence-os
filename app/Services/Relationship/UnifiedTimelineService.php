<?php

namespace App\Services\Relationship;

use App\Models\Activity;
use App\Models\Appointment;
use App\Models\LeadActivity;
use App\Models\PatientNote;
use App\Models\Relationship;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UnifiedTimelineService — Phase 1 · Sprint 2/3 (Workstream B).
 *
 * Assembles a person's complete history into ONE chronological timeline by
 * merging the Activity ledger with the legacy activity/communication sources.
 *
 * This is a FAITHFUL MIRROR of ProfileController::buildTimeline — identical
 * sources, per-source limits, ordering, final cap, and entry formatting — so
 * the Sprint 3 cutover (profile reads via this service behind the
 * `activity.single_ledger_reads` flag) is invisible to the user and provably
 * at parity (see relationship:timeline-parity).
 *
 * Read-only. Never throws — each source is guarded, so one bad source can
 * never blank the timeline.
 *
 * Entry shape (identical to the legacy builder):
 *   ['date' => Carbon, 'type' => string, 'icon_type' => string,
 *    'title' => string, 'description' => ?string, 'actor' => ?string, 'meta' => ?string]
 */
class UnifiedTimelineService
{
    /** @return Collection<int, array<string,mixed>> newest-first, capped at 100. */
    public function for(Relationship $relationship, int $limit = 100): Collection
    {
        $entries = collect();

        // Use the SAME lead/patient selection as the profile (hasOne relations),
        // so household relationships (multiple patients) resolve identically.
        $lead    = $relationship->lead;
        $patient = $relationship->patient;

        $this->addActivities($entries, $relationship);
        if ($lead) {
            $this->addLeadActivities($entries, $lead);
        }
        if ($patient) {
            $this->addAppointments($entries, $patient);
            $this->addPatientCommunications($entries, $patient);
            $this->addTasks($entries, $patient);
            $this->addNotes($entries, $patient);
        }

        return $entries
            ->filter(fn ($e) => $e['date'] instanceof Carbon)
            ->sortByDesc('date')
            ->values()
            ->take($limit);
    }

    private function addActivities(Collection $entries, Relationship $relationship): void
    {
        $this->guard(function () use ($entries, $relationship) {
            Activity::where('relationship_id', $relationship->id)
                ->orderBy('occurred_at', 'desc')->limit(60)->get()
                ->each(function ($act) use ($entries) {
                    $entries->push([
                        'date'        => $act->occurred_at,
                        'type'        => 'activity',
                        'icon_type'   => $this->iconForEvent((string) $act->event),
                        'title'       => $act->description ?: ucfirst(str_replace(['.', '_'], ' ', (string) $act->event)),
                        'description' => null,
                        'actor'       => $act->actor_type ? $this->resolveActorName($act) : 'System',
                        'meta'        => $act->metadata ? $this->formatMeta((array) $act->metadata) : null,
                    ]);
                });
        });
    }

    private function addLeadActivities(Collection $entries, $lead): void
    {
        $this->guard(function () use ($entries, $lead) {
            LeadActivity::where('lead_id', $lead->id)
                ->orderBy('activity_date', 'desc')->limit(30)->get()
                ->each(function ($la) use ($entries) {
                    $entries->push([
                        'date'        => $this->toCarbon($la->activity_date ?? $la->created_at),
                        'type'        => 'communication',
                        'icon_type'   => $la->type ?? 'call',
                        'title'       => $la->label ?? ucfirst((string) ($la->type ?? 'Activity')),
                        'description' => $la->note,
                        'actor'       => $la->by,
                        'meta'        => $la->outcome,
                    ]);
                });
        });
    }

    private function addAppointments(Collection $entries, $patient): void
    {
        $this->guard(function () use ($entries, $patient) {
            Appointment::where('patient_id', $patient->id)
                ->orderBy('appointment_date', 'desc')->limit(30)->get()
                ->each(function ($appt) use ($entries) {
                    $entries->push([
                        'date'        => $this->toCarbon($appt->appointment_date),
                        'type'        => 'appointment',
                        'icon_type'   => 'appointment',
                        'title'       => 'Appointment — ' . ucfirst((string) ($appt->type ?? 'Visit')),
                        'description' => $appt->notes ?? null,
                        'actor'       => $appt->doctor_id ? $this->userName($appt->doctor_id) : null,
                        'meta'        => ucfirst((string) ($appt->status ?? '')),
                    ]);
                });
        });
    }

    private function addPatientCommunications(Collection $entries, $patient): void
    {
        $this->guard(function () use ($entries, $patient) {
            if (! Schema::hasTable('patient_communications')) {
                return;
            }
            DB::table('patient_communications')
                ->where('patient_id', $patient->id)
                ->orderBy('created_at', 'desc')->limit(20)->get()
                ->each(function ($comm) use ($entries) {
                    $entries->push([
                        'date'        => $this->toCarbon($comm->sent_at ?? $comm->created_at),
                        'type'        => 'communication',
                        'icon_type'   => $comm->type ?? 'call',
                        'title'       => ucfirst((string) ($comm->type ?? 'Communication')) . ' — ' . ucfirst((string) ($comm->direction ?? '')),
                        'description' => $comm->message ?? null,
                        'actor'       => $comm->staff_name ?? null,
                        'meta'        => ucfirst((string) ($comm->status ?? '')),
                    ]);
                });
        });
    }

    private function addTasks(Collection $entries, $patient): void
    {
        $this->guard(function () use ($entries, $patient) {
            Task::where('patient_id', $patient->id)
                ->orderBy('created_at', 'desc')->limit(20)->get()
                ->each(function ($task) use ($entries) {
                    $entries->push([
                        'date'        => $this->toCarbon($task->due_date ?? $task->created_at),
                        'type'        => 'task',
                        'icon_type'   => 'task',
                        'title'       => $task->title ?? $task->task_title ?? 'Task',
                        'description' => $task->description ?? null,
                        'actor'       => null,
                        'meta'        => ucfirst((string) ($task->status ?? '')),
                    ]);
                });
        });
    }

    private function addNotes(Collection $entries, $patient): void
    {
        $this->guard(function () use ($entries, $patient) {
            PatientNote::where('patient_id', $patient->id)
                ->orderBy('created_at', 'desc')->limit(10)->get()
                ->each(function ($note) use ($entries) {
                    $entries->push([
                        'date'        => $this->toCarbon($note->created_at),
                        'type'        => 'note',
                        'icon_type'   => 'note',
                        'title'       => 'Note — ' . ucfirst((string) ($note->note_type ?? 'General')),
                        'description' => $note->note,
                        'actor'       => null,
                        'meta'        => null,
                    ]);
                });
        });
    }

    // ── helpers (mirror ProfileController) ────────────────────────────────────

    private function guard(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            // a single failing source must not break the timeline
        }
    }

    private function toCarbon($value): ?Carbon
    {
        if ($value === null) {
            return null;
        }
        try {
            return $value instanceof Carbon ? $value : Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function iconForEvent(string $event): string
    {
        return match (true) {
            str_starts_with($event, 'call')        => 'call',
            str_starts_with($event, 'whatsapp')    => 'whatsapp',
            str_starts_with($event, 'appointment') => 'appointment',
            str_starts_with($event, 'payment')     => 'payment',
            str_starts_with($event, 'lead')        => 'lead',
            str_starts_with($event, 'recall')      => 'recall',
            str_starts_with($event, 'task')        => 'task',
            str_starts_with($event, 'note')        => 'note',
            default                                 => 'activity',
        };
    }

    private function resolveActorName($act): string
    {
        if (str_contains($act->actor_type ?? '', 'User')) {
            return $this->userName($act->actor_id) ?? 'Staff';
        }
        return 'System';
    }

    private function formatMeta(array $meta): ?string
    {
        $parts = [];
        foreach ($meta as $k => $v) {
            if (is_scalar($v) && strlen((string) $v) < 40) {
                $parts[] = ucfirst(str_replace('_', ' ', $k)) . ': ' . $v;
            }
            if (count($parts) >= 2) {
                break;
            }
        }
        return $parts ? implode(' · ', $parts) : null;
    }

    private function userName($userId): ?string
    {
        return DB::table('users')->where('id', $userId)->value('name');
    }
}
