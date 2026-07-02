<?php

namespace App\Services\Relationship;

use App\Domain\Events\DomainEventBus;
use App\Domain\Events\Relationship\PatientRegistered;
use App\Domain\Events\Relationship\RelationshipLinked;
use App\Models\Patient;
use App\Support\Features\Feature;
use Illuminate\Support\Facades\Log;

/**
 * PatientRelationshipLinker — Phase 1 (Workstream A).
 *
 * The single, flag-gated seam that links a newly-created Patient to its Master
 * Relationship. Extracted so patient-creation code stays clean and so the gate
 * logic is unit-testable without touching the database.
 *
 * Behaviour:
 *   - Flag 'identity.link_patient' OFF (default) → NO-OP. Patient creation is
 *     completely unchanged. This is why Phase 1 Sprint 1 is behaviour-neutral.
 *   - Flag ON → link via RelationshipEngine (idempotent, never throws) and,
 *     if a link resulted, publish PatientRegistered + RelationshipLinked.
 *
 * Never breaks patient creation — all work is wrapped and swallowed on error.
 */
class PatientRelationshipLinker
{
    public function __construct(
        private readonly RelationshipEngine $engine,
        private readonly DomainEventBus $bus,
    ) {
    }

    public function link(Patient $patient): void
    {
        try {
            // Flag check is INSIDE the try so that even a flag-resolution error
            // (e.g. feature_flags not migrated yet) can never break patient
            // creation — it simply falls through to the legacy no-op.
            if (! Feature::enabled('identity.link_patient')) {
                return; // default: no change to patient creation
            }

            $this->engine->linkPatient($patient);

            if ($patient->exists) {
                $patient->refresh();
            }

            if ($patient->relationship_id) {
                $this->bus->publish(new RelationshipLinked(
                    relationshipId: (int) $patient->relationship_id,
                    subjectType: Patient::class,
                    subjectId: (int) $patient->id,
                ));
                $this->bus->publish(new PatientRegistered(
                    relationshipId: (int) $patient->relationship_id,
                    patientId: (int) $patient->id,
                ));
            }
        } catch (\Throwable $e) {
            // Linking must never break patient creation.
            Log::warning('PatientRelationshipLinker::link failed', [
                'patient_id' => $patient->id ?? null,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
