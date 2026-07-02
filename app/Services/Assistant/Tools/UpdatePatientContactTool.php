<?php

namespace App\Services\Assistant\Tools;

use App\Models\User;

/**
 * UpdatePatientContactTool — update a patient's contact details.
 * Confirm-required (ConfirmableTool). Low-risk fields only: phone, email,
 * address. Does NOT touch clinical or financial data.
 */
class UpdatePatientContactTool implements ConfirmableTool
{
    use ResolvesPatient;

    public function name(): string
    {
        return 'update_patient_contact';
    }

    public function description(): string
    {
        return "Update a patient's contact details — phone, email, and/or address. "
             . 'Use for "change X\'s phone number to…" or "update X\'s address". Confirms before saving.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'patient' => ['type' => 'string', 'description' => 'Patient name, phone, or ID.'],
                'phone'   => ['type' => 'string', 'description' => 'New phone number.'],
                'email'   => ['type' => 'string', 'description' => 'New email address.'],
                'address' => ['type' => 'string', 'description' => 'New address.'],
            ],
            'required' => ['patient'],
        ];
    }

    public function category(): string
    {
        return 'write';
    }

    public function preview(array $args, User $user): string
    {
        $p = $this->resolvePatient((string) ($args['patient'] ?? ''));
        $name = $p?->name ?? '(unknown patient)';
        $changes = $this->changes($args);
        return $changes
            ? "Update {$name}'s contact — " . implode(', ', $changes)
            : "Update {$name}'s contact (nothing specified)";
    }

    public function run(array $args, User $user): array
    {
        $patient = $this->resolvePatient((string) ($args['patient'] ?? ''));
        if (!$patient) {
            return $this->patientNotFound((string) ($args['patient'] ?? ''));
        }

        $update = [];
        foreach (['phone', 'email', 'address'] as $f) {
            if (!empty($args[$f])) {
                $update[$f] = trim((string) $args[$f]);
            }
        }

        if (empty($update)) {
            return ['summary' => 'Update contact — nothing to change', 'content' => 'No new phone, email, or address was given.'];
        }

        $patient->update($update);

        return [
            'summary' => "Updated contact for {$patient->patient_id}",
            'content' => "Done — updated {$patient->name}'s " . implode(', ', array_keys($update)) . '.',
            'target'  => $patient,
        ];
    }

    protected function changes(array $args): array
    {
        $c = [];
        if (!empty($args['phone']))   $c[] = "phone → {$args['phone']}";
        if (!empty($args['email']))   $c[] = "email → {$args['email']}";
        if (!empty($args['address'])) $c[] = "address → {$args['address']}";
        return $c;
    }
}
