<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PatientRelationshipNote;
use App\Models\TreatmentOpportunity;
use Illuminate\Support\Facades\Auth;

class PatientProfileService
{
    /**
     * Load everything the Patient Profile screen needs in one place.
     */
    public function loadProfile(Patient $patient): array
    {
        $patient->load([
            'appointments.treatment',
            'appointments.treatmentCategory',
            'relationshipNotes.author',
            'opportunities.author',
            'alerts',
            'treatmentVisits.doctor',
            'treatmentPlans.items',
            'treatmentPlans.creator',
            'consultations',
        ]);

        return [
            'patient'           => $patient,
            'recentVisits'      => $patient->appointments->take(10),
            'relationshipNotes' => $patient->relationshipNotes,
            'opportunities'     => $patient->opportunities,
            'doctors'           => \App\Models\User::where('role', 'doctor')->orderBy('name')->get(),
            'treatmentVisits'   => $patient->treatmentVisits,
            'treatments' => \App\Models\Treatment::select('id', 'name', 'default_price')->where('is_active', 1)->orderBy('name')->get(),
            'consultations'     => $patient->consultations,
        ];
    }

    /**
     * Add a relationship note.
     * public function addRelationshipNote(Patient $patient, array $data): PatientRelationshipNote
    {
        return $patient->relationshipNotes()->create([
            'note'       => $data['note'],
            'tags'       => $data['tags'] ?? [],
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Add / update a treatment opportunity.
     */
    public function saveOpportunity(Patient $patient, array $data, ?int $id = null): TreatmentOpportunity
    {
        $payload = [
            'type'            => $data['type'],
            'label'           => $data['label'] ?? null,
            'status'          => $data['status'] ?? 'prospect',
            'priority'        => $data['priority'] ?? 'medium',
            'follow_up_date'  => $data['follow_up_date'] ?? null,
            'estimated_value' => $data['estimated_value'] ?? null,
            'notes'           => $data['notes'] ?? null,
            'created_by'      => Auth::id(),
        ];

        if ($id) {
            $opp = TreatmentOpportunity::findOrFail($id);
            $opp->update($payload);
            return $opp;
        }

        return $patient->opportunities()->create($payload);
    }
}
