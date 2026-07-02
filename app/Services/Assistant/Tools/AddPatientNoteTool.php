<?php

namespace App\Services\Assistant\Tools;

use App\Models\PatientNote;
use App\Models\User;

/**
 * AddPatientNoteTool — append a clinical note to a patient's record.
 * ----------------------------------------------------------------------------
 * Category 'clinical' → this is CONFIRM-REQUIRED. The assistant proposes it and
 * the staff member taps "Confirm" before it writes. Demonstrates the confirm-card
 * flow with a low-blast-radius clinical write.
 */
class AddPatientNoteTool implements ConfirmableTool
{
    use ResolvesPatient;

    public function name(): string
    {
        return 'add_patient_note';
    }

    public function description(): string
    {
        return 'Add a clinical note to a patient\'s record. Use for "add a note to X that…" or '
             . '"record on X\'s file that…". This is a clinical action and will ask for confirmation.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'patient' => ['type' => 'string', 'description' => 'Patient name, phone, or ID.'],
                'note'    => ['type' => 'string', 'description' => 'The note text to add to the record.'],
            ],
            'required' => ['patient', 'note'],
        ];
    }

    public function category(): string
    {
        return 'clinical'; // → confirm-required
    }

    /** Shown on the confirm card before anything is written. */
    public function preview(array $args, User $user): string
    {
        $name = '(unknown patient)';
        if (!empty($args['patient']) && ($p = $this->resolvePatient((string) $args['patient']))) {
            $name = $p->name;
        }
        $note = trim((string) ($args['note'] ?? ''));
        $short = mb_strlen($note) > 80 ? mb_substr($note, 0, 80) . '…' : $note;

        return "Add a clinical note to {$name}: \"{$short}\"";
    }

    public function run(array $args, User $user): array
    {
        $patient = $this->resolvePatient((string) ($args['patient'] ?? ''));
        $note    = trim((string) ($args['note'] ?? ''));

        if (!$patient) {
            return $this->patientNotFound((string) ($args['patient'] ?? ''));
        }
        if ($note === '') {
            return ['summary' => 'Add note — empty', 'content' => 'The note text was empty, nothing added.'];
        }

        $record = PatientNote::create([
            'patient_id' => $patient->id,
            'note'       => $note,
            'note_type'  => 'clinical',
            'created_by' => $user->id,
        ]);

        return [
            'summary' => "Added clinical note to {$patient->patient_id}",
            'content' => "Done — added a clinical note to {$patient->name}'s record.",
            'target'  => $record,
        ];
    }
}
