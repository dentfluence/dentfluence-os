<?php

namespace App\Services\Relationship;

use App\Models\DedupCandidate;
use App\Models\Lead;
use App\Models\Patient;
use App\Models\Relationship;
use App\Models\Scopes\BranchScope;
use App\Models\TreatmentOpportunity;

/**
 * RelationshipBackfillService — Phase 1 · Sprint 2 (Workstream G).
 *
 * Populates relationship_id across EXISTING leads and patients so the Master
 * Relationship becomes whole, and queues potential duplicates for HUMAN review.
 *
 * Safety principles (high-risk workstream — real patient data):
 *   - analyze() is a READ-ONLY dry run — it writes nothing.
 *   - apply() links via the existing, idempotent RelationshipEngine methods,
 *     so re-running never creates duplicates and a per-row failure is isolated.
 *   - NEVER auto-merges. Relationships that share an exact phone/email are only
 *     QUEUED into dedup_candidates for a human to review and merge deliberately.
 *   - Bypasses BranchScope so it always processes ALL clinics, regardless of
 *     who runs it (the scope is already a no-op under CLI, but we are explicit).
 *
 * This service is invoked only by the `relationship:backfill` command — never
 * automatically, and independently of the identity.link_patient feature flag
 * (the flag gates live patient-creation linking; this is a deliberate one-off).
 */
class RelationshipBackfillService
{
    public function __construct(
        private readonly RelationshipEngine $engine,
        private readonly IdentityResolver $resolver,
    ) {
    }

    /**
     * Read-only analysis of what apply() WOULD do. No writes.
     *
     * @return array<string,mixed>
     */
    public function analyze(): array
    {
        $leadWouldMatch = 0;
        $leadWouldCreate = 0;
        Lead::whereNull('relationship_id')
            ->select('id', 'phone', 'email')
            ->chunkById(500, function ($rows) use (&$leadWouldMatch, &$leadWouldCreate) {
                foreach ($rows as $r) {
                    $this->resolver->match(['phone' => $r->phone ?: null, 'email' => $r->email])
                        ? $leadWouldMatch++ : $leadWouldCreate++;
                }
            });

        $patWouldMatch = 0;
        $patWouldCreate = 0;
        $this->patientQuery()->whereNull('relationship_id')
            ->select('id', 'phone', 'email')
            ->chunkById(500, function ($rows) use (&$patWouldMatch, &$patWouldCreate) {
                foreach ($rows as $r) {
                    $this->resolver->match(['phone' => $r->phone ?: null, 'email' => $r->email])
                        ? $patWouldMatch++ : $patWouldCreate++;
                }
            });

        $oppUnlinked = TreatmentOpportunity::whereNull('relationship_id')->count();
        $oppLinkable = TreatmentOpportunity::whereNull('relationship_id')
            ->whereIn(
                'patient_id',
                Patient::withoutGlobalScope(BranchScope::class)->whereNotNull('relationship_id')->select('id')
            )->count();

        return [
            'mode'     => 'dry-run',
            'leads'    => [
                'unlinked'     => $leadWouldMatch + $leadWouldCreate,
                'would_match'  => $leadWouldMatch,
                'would_create' => $leadWouldCreate,
            ],
            'patients' => [
                'unlinked'     => $patWouldMatch + $patWouldCreate,
                'would_match'  => $patWouldMatch,
                'would_create' => $patWouldCreate,
            ],
            'opportunities' => [
                'unlinked' => $oppUnlinked,
                'linkable' => $oppLinkable, // via their patient's relationship
            ],
            'potential_duplicate_groups' => $this->countPotentialDuplicates(),
        ];
    }

    /**
     * Link all unlinked leads + patients, then queue dedup candidates.
     * Idempotent and restartable. Never auto-merges.
     *
     * @return array<string,mixed>
     */
    public function apply(): array
    {
        $linkedLeads = 0;
        $failedLeads = 0;
        Lead::whereNull('relationship_id')->orderBy('id')
            ->chunkById(200, function ($rows) use (&$linkedLeads, &$failedLeads) {
                foreach ($rows as $lead) {
                    try {
                        $this->engine->linkLead($lead); // idempotent; never throws to us
                        $linkedLeads++;
                    } catch (\Throwable $e) {
                        $failedLeads++;
                    }
                }
            });

        $linkedPatients = 0;
        $failedPatients = 0;
        $this->patientQuery()->whereNull('relationship_id')->orderBy('id')
            ->chunkById(200, function ($rows) use (&$linkedPatients, &$failedPatients) {
                foreach ($rows as $patient) {
                    try {
                        $this->engine->linkPatient($patient);
                        $linkedPatients++;
                    } catch (\Throwable $e) {
                        $failedPatients++;
                    }
                }
            });

        $opportunities = $this->linkOpportunities();

        return [
            'mode'                     => 'applied',
            'leads'                    => ['linked' => $linkedLeads, 'failed' => $failedLeads],
            'patients'                 => ['linked' => $linkedPatients, 'failed' => $failedPatients],
            'opportunities'            => $opportunities,
            'dedup_candidates_queued'  => $this->queueDedupCandidates(),
        ];
    }

    /**
     * Link treatment opportunities to a relationship via their patient.
     * An opportunity has no phone/email of its own — its identity is its
     * patient's, so we derive relationship_id from patient.relationship_id.
     *
     * @return array{linked:int, skipped:int}
     */
    private function linkOpportunities(): array
    {
        $linked = 0;
        $skipped = 0;

        TreatmentOpportunity::whereNull('relationship_id')->orderBy('id')
            ->chunkById(200, function ($rows) use (&$linked, &$skipped) {
                foreach ($rows as $opp) {
                    $relId = $opp->patient_id
                        ? Patient::withoutGlobalScope(BranchScope::class)
                            ->where('id', $opp->patient_id)->value('relationship_id')
                        : null;

                    if ($relId) {
                        $opp->relationship_id = $relId;
                        $opp->saveQuietly();
                        $linked++;
                    } else {
                        $skipped++; // patient not linked / no patient
                    }
                }
            });

        return ['linked' => $linked, 'skipped' => $skipped];
    }

    /**
     * Queue (NEVER merge) relationships that share an exact phone or email as
     * candidate pairs for human review. Idempotent — existing pairs are kept
     * with their review status. Returns the number of NEW pairs queued.
     */
    public function queueDedupCandidates(): int
    {
        $queued = 0;

        foreach (['phone', 'email'] as $field) {
            $values = Relationship::query()
                ->whereNotNull($field)
                ->where($field, '!=', '')
                ->groupBy($field)
                ->havingRaw('COUNT(*) > 1')
                ->pluck($field);

            foreach ($values as $value) {
                $ids = Relationship::where($field, $value)->orderBy('id')->pluck('id')->all();
                $primary = array_shift($ids); // lowest id = the likely survivor

                foreach ($ids as $dupId) {
                    $candidate = DedupCandidate::firstOrCreate(
                        ['relationship_id' => $primary, 'candidate_relationship_id' => $dupId],
                        ['match_reason' => $field, 'status' => 'pending'],
                    );
                    if ($candidate->wasRecentlyCreated) {
                        $queued++;
                    }
                }
            }
        }

        return $queued;
    }

    /** Count groups of relationships sharing an exact phone/email (report only). */
    private function countPotentialDuplicates(): int
    {
        $count = 0;
        foreach (['phone', 'email'] as $field) {
            $count += Relationship::query()
                ->whereNotNull($field)
                ->where($field, '!=', '')
                ->groupBy($field)
                ->havingRaw('COUNT(*) > 1')
                ->pluck($field)
                ->count();
        }
        return $count;
    }

    /** Patients across ALL branches (backfill is a cross-branch operator job). */
    private function patientQuery()
    {
        return Patient::withoutGlobalScope(BranchScope::class);
    }
}
