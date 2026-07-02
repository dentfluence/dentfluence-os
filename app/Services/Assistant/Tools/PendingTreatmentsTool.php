<?php

namespace App\Services\Assistant\Tools;

use App\Models\TreatmentPlan;
use App\Models\User;

/**
 * PendingTreatmentsTool — treatment plans not yet accepted (or accepted but
 * not finished). Works for one patient, or clinic-wide when no patient given.
 * Read-only.
 */
class PendingTreatmentsTool implements AssistantTool
{
    use ResolvesPatient;

    public function name(): string
    {
        return 'pending_treatments';
    }

    public function description(): string
    {
        return 'List treatment plans that are still pending (proposed but not accepted). '
             . 'Give a patient to see theirs, or omit to see recent pending plans across the clinic.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'patient' => [
                    'type'        => 'string',
                    'description' => 'Optional. Patient name, phone, or ID. Omit for a clinic-wide list.',
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
        $query = trim((string) ($args['patient'] ?? ''));

        // ── Single patient ────────────────────────────────────────────────
        if ($query !== '') {
            $patient = $this->resolvePatient($query);
            if (!$patient) {
                return $this->patientNotFound($query);
            }

            $plans = $patient->treatmentPlans()->whereNull('accepted_at')->latest()->limit(10)->get();

            if ($plans->isEmpty()) {
                return [
                    'summary' => "Checked pending treatments for {$patient->patient_id} — none",
                    'content' => "{$patient->name} has no pending (un-accepted) treatment plans.",
                    'target'  => $patient,
                ];
            }

            $lines = $plans->map(function (TreatmentPlan $p) {
                $name = $p->plan_name ?: 'Treatment plan';
                return "- {$name} — " . $this->money($p->total) . " (proposed " . optional($p->created_at)->format('d M Y') . ")";
            })->implode("\n");

            return [
                'summary' => "Listed {$plans->count()} pending plan(s) for {$patient->patient_id}",
                'content' => "{$patient->name} has {$plans->count()} pending treatment plan(s):\n{$lines}",
                'target'  => $patient,
            ];
        }

        // ── Clinic-wide ───────────────────────────────────────────────────
        $plans = TreatmentPlan::whereNull('accepted_at')
            ->with('patient:id,patient_id,name')
            ->latest()->limit(10)->get();

        if ($plans->isEmpty()) {
            return ['summary' => 'Checked clinic pending treatments — none', 'content' => 'No pending treatment plans across the clinic right now.'];
        }

        $lines = $plans->map(function (TreatmentPlan $p) {
            $who  = $p->patient->name ?? 'Unknown';
            $name = $p->plan_name ?: 'Treatment plan';
            return "- {$who}: {$name} — " . $this->money($p->total);
        })->implode("\n");

        return [
            'summary' => "Listed {$plans->count()} clinic-wide pending plan(s)",
            'content' => "Recent pending treatment plans ({$plans->count()} shown):\n{$lines}",
        ];
    }
}
