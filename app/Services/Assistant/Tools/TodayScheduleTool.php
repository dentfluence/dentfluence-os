<?php

namespace App\Services\Assistant\Tools;

use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;

/**
 * TodayScheduleTool — list appointments for a given day (defaults to today).
 * Read-only. Scoped to the staff member's branch.
 */
class TodayScheduleTool implements AssistantTool
{
    public function name(): string
    {
        return 'get_schedule';
    }

    public function description(): string
    {
        return "Get the clinic's appointment schedule for a specific date (defaults to today). "
             . "Use for questions like 'what's on today', 'who's coming tomorrow', or a date.";
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'date' => [
                    'type'        => 'string',
                    'description' => 'Date in YYYY-MM-DD format. Omit for today.',
                ],
                'mine_only' => [
                    'type'        => 'boolean',
                    'description' => "True to show only the current staff member's own appointments.",
                ],
            ],
            'required' => [],
        ];
    }

    public function category(): string
    {
        return 'read';
    }

    public function run(array $args, User $user): array
    {
        $date = !empty($args['date'])
            ? Carbon::parse($args['date'])->toDateString()
            : today()->toDateString();

        $query = Appointment::query()
            ->whereDate('appointment_date', $date)
            ->with(['patient:id,patient_id,name', 'doctor:id,name'])
            ->orderBy('appointment_time');

        // Scope to branch if the user has one.
        if (!empty($user->branch_id)) {
            $query->where('branch_id', $user->branch_id);
        }

        if (!empty($args['mine_only'])) {
            $query->where('doctor_id', $user->id);
        }

        $appts = $query->get();
        $label = Carbon::parse($date)->isToday() ? 'today' : Carbon::parse($date)->format('D, d M Y');

        if ($appts->isEmpty()) {
            return [
                'summary' => "Read schedule for {$date} — empty",
                'content' => "No appointments scheduled for {$label}.",
            ];
        }

        $lines = $appts->map(function (Appointment $a) {
            $time    = $a->appointment_time ? Carbon::parse($a->appointment_time)->format('H:i') : '--:--';
            $patient = $a->patient->name ?? 'Unknown';
            $doc     = $a->doctor->name ?? '—';
            $status  = $a->status ?? '';
            return "- {$time} | {$patient} | Dr. {$doc} | {$status}";
        })->implode("\n");

        return [
            'summary' => "Read schedule for {$date} — {$appts->count()} appointment(s)",
            'content' => "Schedule for {$label} ({$appts->count()} appointment(s)):\n{$lines}",
        ];
    }
}
