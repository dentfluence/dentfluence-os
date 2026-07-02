<?php

namespace App\Services\Assistant\Tools;

use App\Models\User;

/**
 * PatientSummaryTool — a quick clinical snapshot of one patient.
 * Read-only. Pulls demographics, medical flags, balance, and recent activity.
 */
class PatientSummaryTool implements AssistantTool
{
    use ResolvesPatient;

    public function name(): string
    {
        return 'patient_summary';
    }

    public function description(): string
    {
        return 'Get a clinical snapshot of a patient: age, medical alerts/allergies, membership, '
             . 'outstanding balance, last visit, and recent activity. Use when asked to "tell me about" '
             . 'or "summarize" a patient.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'patient' => [
                    'type'        => 'string',
                    'description' => 'Patient name, phone, or patient ID (e.g. DF-00042).',
                ],
            ],
            'required' => ['patient'],
        ];
    }

    public function category(): string
    {
        return 'read';
    }

    public function run(array $args, User $user): array
    {
        $query   = (string) ($args['patient'] ?? '');
        $patient = $this->resolvePatient($query);

        if (!$patient) {
            return $this->patientNotFound($query);
        }

        $conditions = is_array($patient->medical_conditions) ? implode(', ', $patient->medical_conditions) : null;
        $allergies  = is_array($patient->allergies) ? implode(', ', $patient->allergies) : null;

        $lastConsult = $patient->consultations()->first();
        $activePlans = $patient->treatmentPlans()->whereNull('accepted_at')->count();

        $lines = [];
        $lines[] = "PATIENT: {$patient->name} ({$patient->patient_id})";
        $lines[] = "Age: " . ($patient->age ?? 'unknown') . "  •  Phone: " . ($patient->phone ?: '—');
        $lines[] = "Membership: " . ($patient->effective_membership_status ?? 'not_enrolled');

        if ($patient->medical_alert) {
            $lines[] = "⚠ Medical alert: {$patient->medical_alert}";
        }
        $lines[] = "Medical conditions: " . ($conditions ?: 'none recorded');
        $lines[] = "Allergies: " . ($allergies ?: 'none recorded');
        if ($patient->current_medications) {
            $lines[] = "Current medications: {$patient->current_medications}";
        }

        $lines[] = "Last visit: " . ($patient->last_visit_date ? $patient->last_visit_date->format('d M Y') : 'no visits recorded');
        $lines[] = "Outstanding balance: " . $this->money($patient->outstanding_balance);
        $lines[] = "Pending (un-accepted) treatment plans: {$activePlans}";

        if ($lastConsult) {
            $when = $lastConsult->consultation_date ? $lastConsult->consultation_date->format('d M Y') : '';
            $cc   = $lastConsult->chief_complaint ?: ($lastConsult->primary_diagnosis ?: 'no note');
            $lines[] = "Most recent consultation ({$lastConsult->typeLabel()}, {$when}): {$cc}";
        }

        return [
            'summary' => "Summarized patient {$patient->patient_id} ({$patient->name})",
            'content' => implode("\n", $lines),
            'target'  => $patient,
        ];
    }
}
