<?php

namespace App\Services\Relationship;

use App\Models\Lead;
use App\Models\Patient;
use App\Models\Relationship;
use App\Models\RelationshipJourney;
use App\Models\Activity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RelationshipEngine — the central service for the Relationship Engine.
 *
 * Responsibilities:
 *  - findOrCreate(): Ensures every person has exactly one Relationship record.
 *  - linkLead():     Links a Lead to its Relationship (or creates one).
 *  - linkPatient():  Links a Patient to its Relationship (or creates one).
 *  - getProfile():   Returns the full relationship profile for display.
 *
 * This service is deliberately simple in Phase 1. Phase 2+ builds
 * TodayActionsEngine, RulesEngine, etc. on top of what this creates.
 *
 * NEVER call this from a view or controller directly — always call it
 * from another service (LeadIngestService, PatientService, etc.).
 */
class RelationshipEngine
{
    /**
     * Find an existing Relationship by phone or email, or create a new one.
     *
     * Lookup order:
     *  1. Match by phone (most reliable in dental context)
     *  2. Match by email
     *  3. Create new
     *
     * @param array $data  Must include 'name'. May include 'phone', 'email', 'source'.
     */
    public function findOrCreate(array $data): Relationship
    {
        $phone = $data['phone'] ?? null;
        $email = $data['email'] ?? null;

        // 1. Try phone match (excludes soft-deleted)
        if ($phone) {
            $relationship = Relationship::byPhone($phone)->first();
            if ($relationship) {
                return $relationship;
            }
        }

        // 2. Try email match
        if ($email) {
            $relationship = Relationship::byEmail($email)->first();
            if ($relationship) {
                return $relationship;
            }
        }

        // 3. Create a new Relationship record
        return Relationship::create([
            'name'              => $data['name'] ?? 'Unknown',
            'phone'             => $phone,
            'email'             => $email,
            'source'            => $data['source'] ?? null,
            'status'            => 'active',
            'score'             => 0,
            'relationship_since' => now()->toDateString(),
        ]);
    }

    /**
     * Link a Lead to its Relationship and create the initial lead journey.
     *
     * Called by LeadIngestService immediately after lead creation.
     * Safe to call multiple times — checks if already linked.
     */
    public function linkLead(Lead $lead): void
    {
        try {
            DB::transaction(function () use ($lead) {
                // Already linked — nothing to do
                if ($lead->relationship_id) {
                    return;
                }

                $relationship = $this->findOrCreate([
                    'name'   => $lead->name,
                    'phone'  => $lead->phone ?: null,
                    'email'  => $lead->email,
                    'source' => $lead->lead_source,
                ]);

                // Set the FK on the lead
                $lead->relationship_id = $relationship->id;
                $lead->saveQuietly(); // avoid re-triggering the observer

                // Create the initial Lead journey if one doesn't exist
                $existingJourney = RelationshipJourney::where('relationship_id', $relationship->id)
                    ->where('type', RelationshipJourney::TYPE_LEAD)
                    ->first();

                if (! $existingJourney) {
                    RelationshipJourney::create([
                        'relationship_id' => $relationship->id,
                        'type'            => RelationshipJourney::TYPE_LEAD,
                        'state'           => RelationshipJourney::LEAD_NEW_ENQUIRY,
                        'metadata'        => ['lead_id' => $lead->id],
                        'started_at'      => now(),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            // Never break lead creation if relationship linking fails
            Log::warning('RelationshipEngine::linkLead failed', [
                'lead_id' => $lead->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Link a Patient to its Relationship (or create one if none exists).
     *
     * Called when a patient is created or converted from a lead.
     * Safe to call multiple times — checks if already linked.
     */
    public function linkPatient(Patient $patient): void
    {
        try {
            DB::transaction(function () use ($patient) {
                // Already linked — nothing to do
                if ($patient->relationship_id) {
                    return;
                }

                // Try to find a relationship already linked to a lead with the same phone/email
                $relationship = $this->findOrCreate([
                    'name'  => $patient->name,
                    'phone' => $patient->phone ?? null,
                    'email' => $patient->email ?? null,
                ]);

                // Set the FK on the patient
                $patient->relationship_id = $relationship->id;
                $patient->saveQuietly();
            });
        } catch (\Throwable $e) {
            Log::warning('RelationshipEngine::linkPatient failed', [
                'patient_id' => $patient->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Return the full relationship profile for display.
     *
     * Returns an array with the relationship record, all journeys, and
     * recent activities. This is the data contract for Phase 3's profile page.
     *
     * @param int $relationshipId
     * @return array{
     *     relationship: Relationship,
     *     lead: Lead|null,
     *     patient: Patient|null,
     *     journeys: \Illuminate\Database\Eloquent\Collection,
     *     activities: \Illuminate\Database\Eloquent\Collection,
     * }
     */
    public function getProfile(int $relationshipId): array
    {
        $relationship = Relationship::with([
            'lead',
            'patient',
            'journeys',
            'activities' => fn ($q) => $q->recent()->limit(50),
        ])->findOrFail($relationshipId);

        return [
            'relationship' => $relationship,
            'lead'         => $relationship->lead,
            'patient'      => $relationship->patient,
            'journeys'     => $relationship->journeys,
            'activities'   => $relationship->activities,
        ];
    }
}
