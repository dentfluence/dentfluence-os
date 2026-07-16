<?php

namespace App\Services\TreatmentPlan;

use App\Models\TreatmentOpportunity;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\Relationship\ActivityEngine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * TreatmentPlanOpportunitySync
 * ----------------------------
 * The single place a treatment plan's lifecycle is reflected onto its ONE
 * linked TreatmentOpportunity (keyed by treatment_plan_id — one opportunity
 * per plan, ever). Both the pre-acceptance ("presented → Estimate Given") and
 * the acceptance / decline transitions flow through here so the treatment plan
 * and the sales pipeline never drift apart.
 *
 * Stage mapping (plan lifecycle → opportunity status):
 *   presented (estimate shown, not yet accepted) → 'quoted'    (Estimate Given)
 *   accepted                                     → 'completed'  (Converted)
 *   declined / cancelled                         → 'declined'
 *
 * "One decision, not one-per-option": a missing tooth presented as implant vs
 * bridge lives as option-ranked items inside ONE plan, so it maps to ONE
 * opportunity here — the alternatives never spawn duplicate cards, and the
 * denominator of the accept-rate report stays honest.
 */
class TreatmentPlanOpportunitySync
{
    public function __construct(private ActivityEngine $activity) {}

    /**
     * Ensure the plan's single opportunity exists and sits at $status.
     * Idempotent — never creates a second opportunity for the same plan.
     *
     * @param  array{actor?:?User,created_by?:?int,priority?:string,source?:string,description?:string,declined_reason?:?string}  $opts
     */
    public function syncStage(TreatmentPlan $plan, string $status, array $opts = []): TreatmentOpportunity
    {
        return DB::transaction(function () use ($plan, $status, $opts) {
            $plan->loadMissing(['items', 'patient']);

            $relationshipId = $plan->patient?->relationship_id;
            $firstItem      = $plan->items->first();
            $value          = $plan->total ?: (float) $plan->items->sum('total');

            /** @var TreatmentOpportunity|null $existing */
            $existing = TreatmentOpportunity::where('treatment_plan_id', $plan->id)->first();

            // Never overwrite an existing relationship link with null.
            $attributes = ['status' => $status];
            if ($relationshipId) {
                $attributes['relationship_id'] = $relationshipId;
            }
            if (array_key_exists('declined_reason', $opts) && $opts['declined_reason'] !== null) {
                $attributes['declined_reason'] = $opts['declined_reason'];
            }

            if ($existing) {
                $existing->fill($attributes);
                // Backfill value / label only if they were never set.
                if (is_null($existing->estimated_value) && $value) {
                    $existing->estimated_value = $value;
                }
                if (empty($existing->label)) {
                    $existing->label = $firstItem?->treatment_name ?? $plan->plan_name;
                }
                $existing->save();
                $opportunity = $existing;
                $created     = false;
            } else {
                $opportunity = TreatmentOpportunity::create(array_merge($attributes, [
                    'patient_id'        => $plan->patient_id,
                    'treatment_plan_id' => $plan->id,
                    'type'              => 'other',
                    'label'             => $firstItem?->treatment_name ?? $plan->plan_name,
                    'priority'          => $opts['priority'] ?? 'medium',
                    'estimated_value'   => $value ?: null,
                    'created_by'        => $opts['created_by'] ?? Auth::id(),
                ]));
                $created = true;
            }

            // Fire 'opportunity.created' (which drives the opportunity_nudge_7d
            // rule — chase the pending estimate) only for a NEW, still-open
            // card. A card born already Converted/Declined, or any update, logs
            // a stage-changed event instead so we never nudge a closed one.
            $isOpen = ! in_array($status, ['completed', 'declined'], true);
            $event  = ($created && $isOpen) ? 'opportunity.created' : 'opportunity.stage_changed';

            $this->activity->log(
                subject:        $opportunity,
                event:          $event,
                actor:          $opts['actor'] ?? null,
                metadata:       [
                    'stage'             => $status,
                    'patient_id'        => $plan->patient_id,
                    'treatment_plan_id' => $plan->id,
                    'source'            => $opts['source'] ?? 'treatment_plan',
                ],
                relationshipId: $relationshipId,
                description:    $opts['description'] ?? 'Opportunity synced from treatment plan',
            );

            return $opportunity;
        });
    }
}
