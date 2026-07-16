<?php

namespace App\Services\CaseAcceptance;

use App\Models\CaseSelection;
use App\Models\DecisionTreeNode;
use App\Models\PatientJourney;

/**
 * CaseSelectionService — records the patient's mutable "cart" (case_selections)
 * and recomputes the running estimate on the fly (frozen §5.5/§8). Nothing here
 * is authoritative until accept; the estimate is never stored on a selection.
 */
class CaseSelectionService
{
    public function __construct(private CasePricingClient $pricing) {}

    /**
     * Record (or update) the patient's choice at a node. `treatmentOptionId`
     * is null for a plain choice with no priced option (e.g. picking a
     * procedure branch).
     */
    public function select(PatientJourney $journey, int $nodeId, ?int $treatmentOptionId = null): CaseSelection
    {
        return CaseSelection::updateOrCreate(
            ['patient_journey_id' => $journey->id, 'decision_tree_node_id' => $nodeId],
            ['treatment_option_id' => $treatmentOptionId, 'selected_at' => now()]
        );
    }

    public function clear(PatientJourney $journey, int $nodeId): void
    {
        CaseSelection::where('patient_journey_id', $journey->id)
            ->where('decision_tree_node_id', $nodeId)
            ->delete();
    }

    /** Sum of the resolved price for every actively-selected node. */
    public function runningEstimate(PatientJourney $journey): float
    {
        $journey->loadMissing('selections', 'decisionTree.nodes');
        $nodes = $journey->decisionTree?->nodes->keyBy('id') ?? collect();

        $total = 0.0;
        foreach ($journey->selections as $selection) {
            /** @var DecisionTreeNode|null $node */
            $node = $nodes->get($selection->decision_tree_node_id);
            if ($node) {
                $total += $this->pricing->priceFor($node, $selection->treatment_option_id);
            }
        }

        return round($total, 2);
    }
}
