<?php

namespace App\Services\Assistant\Tools;

use App\Models\Patient;

/**
 * ResolvesPatient — shared helper so every patient-scoped tool finds the
 * patient the same way (by name, phone, or patient ID like DF-00042).
 */
trait ResolvesPatient
{
    /** Best single match for a free-text patient reference, or null. */
    protected function resolvePatient(string $query): ?Patient
    {
        $query = trim($query);
        if ($query === '') return null;

        return Patient::query()
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('first_name', 'like', "%{$query}%")
                  ->orWhere('last_name', 'like', "%{$query}%")
                  ->orWhere('phone', 'like', "%{$query}%")
                  ->orWhere('alternate_phone', 'like', "%{$query}%")
                  ->orWhere('patient_id', 'like', "%{$query}%");
            })
            ->orderByDesc('last_visit_date')
            ->first();
    }

    /** Standard "couldn't find them" result so phrasing is consistent. */
    protected function patientNotFound(string $query): array
    {
        return [
            'summary' => "Looked up patient \"{$query}\" — not found",
            'content' => "I couldn't find a patient matching \"{$query}\". Ask me to search by full name, phone, or patient ID.",
        ];
    }

    /** Format a rupee amount consistently. */
    protected function money($value): string
    {
        return 'Rs. ' . number_format((float) $value, 0);
    }
}
