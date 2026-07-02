<?php

namespace App\Services\Assistant\Tools;

use App\Models\Consultation;
use App\Models\TreatmentVisit;
use App\Models\User;

/**
 * VisitHistoryTool — recent consultations and treatment visits for a patient.
 * Read-only. Answers "what happened last visit" / "show recent history".
 */
class VisitHistoryTool implements AssistantTool
{
    use ResolvesPatient;

    public function name(): string
    {
        return 'visit_history';
    }

    public function description(): string
    {
        return 'Show a patient\'s recent consultations and treatment visits (most recent first). '
             . 'Use for "what happened at the last visit" or "show recent history".';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'patient' => [
                    'type'        => 'string',
                    'description' => 'Patient name, phone, or patient ID.',
                ],
                'limit' => [
                    'type'        => 'integer',
                    'description' => 'How many of each to show (default 4).',
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

        $limit = max(1, min(10, (int) ($args['limit'] ?? 4)));

        $consults = $patient->consultations()->limit($limit)->get();
        $visits   = $patient->treatmentVisits()->limit($limit)->get();

        $lines = ["HISTORY — {$patient->name} ({$patient->patient_id})"];

        $lines[] = "";
        $lines[] = "Consultations:";
        if ($consults->isEmpty()) {
            $lines[] = "- none recorded";
        } else {
            foreach ($consults as $c) {
                /** @var Consultation $c */
                $when = optional($c->consultation_date)->format('d M Y') ?: '';
                $note = $c->chief_complaint ?: ($c->findings_summary_final ?: ($c->primary_diagnosis ?: 'no note'));
                $lines[] = "- {$when} · {$c->typeLabel()} · {$note}";
            }
        }

        $lines[] = "";
        $lines[] = "Treatment visits:";
        if ($visits->isEmpty()) {
            $lines[] = "- none recorded";
        } else {
            foreach ($visits as $v) {
                /** @var TreatmentVisit $v */
                $when = optional($v->visit_date)->format('d M Y') ?: '';
                $what = $v->treatment_name ?: ($v->procedure ?: 'visit');
                $paid = $v->is_fully_paid ? 'paid' : ('balance ' . $this->money($v->balance_due));
                $lines[] = "- {$when} · {$what} · {$v->status} · {$paid}";
            }
        }

        return [
            'summary' => "Read visit history for {$patient->patient_id}",
            'content' => implode("\n", $lines),
            'target'  => $patient,
        ];
    }
}
