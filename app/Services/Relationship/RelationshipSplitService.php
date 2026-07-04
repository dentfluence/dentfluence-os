<?php

namespace App\Services\Relationship;

use App\Models\Patient;
use App\Models\Relationship;
use App\Models\Scopes\BranchScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RelationshipSplitService — one-time repair for household phone-sharing merges.
 *
 * Before the identity model was corrected, patients were matched to an
 * existing Relationship by phone/email even if that Relationship already
 * belonged to another patient. This is common in Indian clinics — a family
 * shares one registered contact number — so several genuinely distinct
 * patients ended up sharing a single Relationship. Clinic ID (the Patient
 * record itself) is the only guaranteed-unique identity for a patient;
 * phone/email are contact details, not identity. See
 * RelationshipEngine::findOrCreateForPatient(), which prevents this from
 * happening again for any patient linked from now on.
 *
 * This service finds every Relationship currently linked to MORE than one
 * patient and gives every patient except the earliest-registered one a
 * fresh, dedicated Relationship — never touching clinical data, only the
 * relationship_id FK and the new Relationship rows themselves.
 *
 * Safe to re-run — once a Relationship has exactly one patient, its group is
 * no longer picked up by sharedGroups() and is skipped.
 */
class RelationshipSplitService
{
    /** Read-only: which relationships currently hold more than one patient. No writes. */
    public function analyze(): array
    {
        $groups = $this->sharedGroups();

        $patientsToSplit = 0;
        foreach ($groups as $patients) {
            $patientsToSplit += $patients->count() - 1; // keep one, split the rest
        }

        return [
            'mode'                  => 'dry-run',
            'shared_relationships'  => $groups->count(),
            'patients_to_split_off' => $patientsToSplit,
        ];
    }

    /** Write: split every extra patient off into its own dedicated Relationship. */
    public function apply(): array
    {
        $groups = $this->sharedGroups();

        $split = 0;
        $failed = 0;
        $errors = [];

        foreach ($groups as $patients) {
            // Keep the earliest-registered patient on the original relationship;
            // every other patient in the group gets its own new Relationship.
            $patients = $patients->values();
            $patients->shift();

            foreach ($patients as $patient) {
                try {
                    DB::transaction(function () use ($patient) {
                        $new = Relationship::create([
                            'name'               => $patient->name,
                            'phone'              => $patient->phone,
                            'email'              => $patient->email,
                            'source'             => 'other', // not a marketing source — data-repair split, no 'source' enum value fits better
                            'status'             => 'active',
                            'score'              => 0,
                            'relationship_since' => $patient->created_at?->toDateString() ?? now()->toDateString(),
                        ]);

                        $patient->relationship_id = $new->id;
                        $patient->saveQuietly();
                    });
                    $split++;
                } catch (\Throwable $e) {
                    $failed++;
                    Log::error('RelationshipSplitService::apply failed for patient', [
                        'patient_id' => $patient->id ?? null,
                        'error'      => $e->getMessage(),
                    ]);
                    if (count($errors) < 5) {
                        $errors[] = 'patient #' . ($patient->id ?? '?') . ': ' . $e->getMessage();
                    }
                }
            }
        }

        return ['mode' => 'applied', 'patients_split' => $split, 'failed' => $failed, 'errors' => $errors];
    }

    /**
     * Every patient whose relationship_id is shared with at least one other
     * patient, grouped by relationship_id and ordered oldest-first within
     * each group (so apply() knows who to leave in place).
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Patient>>
     */
    private function sharedGroups()
    {
        $sharedRelationshipIds = Patient::withoutGlobalScope(BranchScope::class)
            ->whereNotNull('relationship_id')
            ->select('relationship_id')
            ->groupBy('relationship_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('relationship_id');

        if ($sharedRelationshipIds->isEmpty()) {
            return collect();
        }

        return Patient::withoutGlobalScope(BranchScope::class)
            ->whereIn('relationship_id', $sharedRelationshipIds)
            ->orderBy('created_at')
            ->get()
            ->groupBy('relationship_id');
    }
}
