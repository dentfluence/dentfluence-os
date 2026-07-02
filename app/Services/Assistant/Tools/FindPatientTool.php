<?php

namespace App\Services\Assistant\Tools;

use App\Models\Patient;
use App\Models\User;

/**
 * FindPatientTool — search the patient list by name, phone, or patient ID.
 * Read-only. Returns up to 5 matches so the model can pick / confirm.
 */
class FindPatientTool implements AssistantTool
{
    public function name(): string
    {
        return 'find_patient';
    }

    public function description(): string
    {
        return 'Search for patients by name, phone number, or patient ID (e.g. DF-00042). '
             . 'Use this whenever the user refers to a patient, before answering questions about them.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type'        => 'string',
                    'description' => 'Name, phone number, or patient ID to search for.',
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function category(): string
    {
        return 'read';
    }

    public function run(array $args, User $user): array
    {
        $query = trim((string) ($args['query'] ?? ''));

        if ($query === '') {
            return ['summary' => 'Patient search with empty query', 'content' => 'No search term was provided.'];
        }

        $matches = Patient::query()
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('first_name', 'like', "%{$query}%")
                  ->orWhere('last_name', 'like', "%{$query}%")
                  ->orWhere('phone', 'like', "%{$query}%")
                  ->orWhere('alternate_phone', 'like', "%{$query}%")
                  ->orWhere('patient_id', 'like', "%{$query}%");
            })
            ->orderByDesc('last_visit_date')
            ->limit(5)
            ->get();

        if ($matches->isEmpty()) {
            return [
                'summary' => "Searched patients for \"{$query}\" — no matches",
                'content' => "No patients found matching \"{$query}\".",
            ];
        }

        $lines = $matches->map(function (Patient $p) {
            $age = $p->age_numeric ? "{$p->age_numeric}y" : '—';
            return "- {$p->patient_id} | {$p->name} | {$age} | {$p->phone}";
        })->implode("\n");

        return [
            'summary' => "Searched patients for \"{$query}\" — {$matches->count()} match(es)",
            'content' => "Found {$matches->count()} patient(s):\n{$lines}",
            'target'  => $matches->count() === 1 ? $matches->first() : null,
        ];
    }
}
