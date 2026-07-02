<?php

namespace App\Services\Relationship;

use App\Models\Relationship;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RelationshipScoreEngine — Phase 6, Relationship Engine
 *
 * Calculates a 0–100 relationship health score for a given Relationship.
 * Weights come from config/relationship_score.php and must sum to 100.
 *
 * Each factor produces a normalised 0–1 value which is multiplied by its
 * configured weight. The sum is rounded to an integer and clamped to [0,100].
 *
 * Score factors (configurable):
 *   visit_frequency        (25) — days since last appointment
 *   recall_compliance      (20) — recalls that resulted in a booking
 *   treatment_completion   (20) — treatment plans completed vs created
 *   communication_response (15) — outbound comms with positive outcome
 *   membership_active      (10) — has an active membership
 *   referral_activity      (10) — number of referrals attributed to patient
 *
 * Called by RecalculateRelationshipScoreJob (queued, async).
 *
 * Usage:
 *   $score = app(RelationshipScoreEngine::class)->calculate($relationship);
 *   app(RelationshipScoreEngine::class)->recalculate($relationshipId);
 */
class RelationshipScoreEngine
{
    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calculate the relationship health score (0–100) without saving.
     *
     * @param  Relationship  $relationship  Must have patient relationship loaded or loadable.
     * @return int
     */
    public function calculate(Relationship $relationship): int
    {
        $factors = config('relationship_score.factors', []);
        $total   = 0;

        foreach ($factors as $key => $config) {
            $weight     = (int) ($config['weight'] ?? 0);
            $normalised = $this->computeFactor($key, $relationship, $config);
            $total     += $normalised * $weight;
        }

        return (int) min(100, max(0, round($total)));
    }

    /**
     * Recalculate the score and persist it to relationships.score.
     *
     * @param  int  $relationshipId
     */
    public function recalculate(int $relationshipId): void
    {
        try {
            $relationship = Relationship::findOrFail($relationshipId);
            $score        = $this->calculate($relationship);

            $relationship->update(['score' => $score]);

            Log::debug("RelationshipScoreEngine: recalculated score for relationship [{$relationshipId}] → {$score}");

        } catch (\Throwable $e) {
            Log::warning("RelationshipScoreEngine::recalculate failed for [{$relationshipId}]", [
                'error' => $e->getMessage(),
            ]);
            // Do not re-throw — score recalculation must never break the calling flow
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Factor computations — each returns a normalised float 0.0–1.0
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Dispatch to the correct factor calculator by key.
     */
    protected function computeFactor(string $key, Relationship $relationship, array $config): float
    {
        return match ($key) {
            'visit_frequency'        => $this->visitFrequency($relationship, $config),
            'recall_compliance'      => $this->recallCompliance($relationship),
            'treatment_completion'   => $this->treatmentCompletion($relationship),
            'communication_response' => $this->communicationResponse($relationship),
            'membership_active'      => $this->membershipActive($relationship),
            'referral_activity'      => $this->referralActivity($relationship),
            default                  => 0.0,
        };
    }

    /**
     * Visit frequency: full score if visited within ideal_days, decays linearly.
     * Score = max(0, 1 - days_since_last_visit / (ideal_days * 2))
     */
    protected function visitFrequency(Relationship $relationship, array $config): float
    {
        $idealDays = (int) ($config['ideal_days'] ?? 180);
        $patientId = $this->resolvePatientId($relationship);

        if (! $patientId) {
            return 0.0; // No linked patient yet — no visits possible
        }

        $lastVisit = DB::table('appointments')
            ->where('patient_id', $patientId)
            ->where('status', 'completed')
            ->orderByDesc('appointment_date')
            ->value('appointment_date');

        if (! $lastVisit) {
            return 0.0;
        }

        $daysSince = now()->diffInDays($lastVisit);

        return max(0.0, 1.0 - ($daysSince / ($idealDays * 2)));
    }

    /**
     * Recall compliance: ratio of recall queue items that resulted in a booking.
     * If no recalls exist for this patient, return 0.5 (neutral — not penalised).
     */
    protected function recallCompliance(Relationship $relationship): float
    {
        $patientId = $this->resolvePatientId($relationship);

        if (! $patientId) {
            return 0.5;
        }

        $total = DB::table('communication_queue')
            ->where('patient_id', $patientId)
            ->where('source_engine', 'recall')
            ->count();

        if ($total === 0) {
            return 0.5; // Neutral — patient has never been recalled
        }

        $responded = DB::table('communication_queue')
            ->where('patient_id', $patientId)
            ->where('source_engine', 'recall')
            ->whereIn('outcome', ['appointment_booked', 'completed', 'success'])
            ->count();

        return min(1.0, $responded / $total);
    }

    /**
     * Treatment completion: ratio of treatment plans with status = 'completed'
     * to all treatment plans created for this patient.
     * Neutral (0.5) if no plans exist.
     */
    protected function treatmentCompletion(Relationship $relationship): float
    {
        $patientId = $this->resolvePatientId($relationship);

        if (! $patientId) {
            return 0.5;
        }

        $total = DB::table('treatment_plans')
            ->where('patient_id', $patientId)
            ->count();

        if ($total === 0) {
            return 0.5;
        }

        $completed = DB::table('treatment_plans')
            ->where('patient_id', $patientId)
            ->where('status', 'completed')
            ->count();

        return min(1.0, $completed / $total);
    }

    /**
     * Communication response: ratio of outbound comms with a positive outcome.
     * Positive outcomes: 'appointment_booked', 'completed', 'success', 'interested'.
     * Neutral (0.5) if no comms exist.
     */
    protected function communicationResponse(Relationship $relationship): float
    {
        $patientId = $this->resolvePatientId($relationship);

        if (! $patientId) {
            return 0.5;
        }

        $total = DB::table('communication_queue')
            ->where('patient_id', $patientId)
            ->whereNotNull('outcome')
            ->count();

        if ($total === 0) {
            return 0.5;
        }

        $positive = DB::table('communication_queue')
            ->where('patient_id', $patientId)
            ->whereIn('outcome', ['appointment_booked', 'completed', 'success', 'interested'])
            ->count();

        return min(1.0, $positive / $total);
    }

    /**
     * Membership active: 1.0 if the patient has an active membership, else 0.0.
     */
    protected function membershipActive(Relationship $relationship): float
    {
        $patientId = $this->resolvePatientId($relationship);

        if (! $patientId) {
            return 0.0;
        }

        $active = DB::table('patient_memberships')
            ->where('patient_id', $patientId)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->exists();

        return $active ? 1.0 : 0.0;
    }

    /**
     * Referral activity: 1.0 if this patient has referred ≥1 lead.
     * 0.5 per referral up to 1.0 cap. Uses leads.referral_source matching patient name/phone.
     */
    protected function referralActivity(Relationship $relationship): float
    {
        // Check via relationship_id link on leads table
        $referralCount = DB::table('leads')
            ->where('relationship_id', $relationship->id)
            ->where('lead_source', 'referral')
            ->count();

        if ($referralCount === 0) {
            // Also check by patient phone (legacy — before relationship_id was added)
            $phone = $relationship->phone ?? null;
            if ($phone) {
                $referralCount = DB::table('leads')
                    ->where('referral_source', $phone)
                    ->count();
            }
        }

        return min(1.0, $referralCount * 0.5); // 0, 0.5, or 1.0
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve the patient_id for a Relationship.
     * Tries the loaded patient relation first, then queries the patients table.
     */
    protected function resolvePatientId(Relationship $relationship): ?int
    {
        // If the relationship has a direct patient_id column
        if (isset($relationship->patient_id)) {
            return $relationship->patient_id ?: null;
        }

        // Look up via patients table FK
        return DB::table('patients')
            ->where('relationship_id', $relationship->id)
            ->value('id');
    }
}
