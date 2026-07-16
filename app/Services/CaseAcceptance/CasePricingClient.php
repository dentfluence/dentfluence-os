<?php

namespace App\Services\CaseAcceptance;

use App\Models\DecisionTreeNode;
use App\Services\Treatment\TreatmentPricingService;

/**
 * CasePricingClient — the ONLY path to money in the Case Acceptance Engine
 * (frozen §8). It calls the Treatment Module's TreatmentPricingService directly
 * (same process, no HTTP round-trip) so the ownership boundary holds: the
 * engine never reads price tables and never caches prices. Prices are always
 * live.
 */
class CasePricingClient
{
    public function __construct(private TreatmentPricingService $pricing) {}

    /**
     * The priced block for a decision-tree node, or null if the node isn't a
     * priced choice (no treatment bound / no group). Shape:
     * [
     *   'treatment_id' => int,
     *   'group'        => string|null,
     *   'base_price'   => float,
     *   'gst_pct'      => float,
     *   'options'      => [ ['id','name','price','is_default'], ... ],
     * ]
     */
    public function forNode(DecisionTreeNode $node): ?array
    {
        if (! $node->treatment_id) {
            return null;
        }

        $result = $this->pricing->pricing($node->treatment_id, $node->treatment_option_group);
        if ($result === null) {
            return null;
        }

        $group   = $node->treatment_option_group;
        $options = $group
            ? ($result['groups'][$group] ?? [])
            : [];   // no group = the treatment's base price is the price

        return [
            'treatment_id' => $result['treatment_id'],
            'group'        => $group,
            'base_price'   => $result['base_price'],
            'gst_pct'      => $result['gst_pct'],
            'options'      => $options,
        ];
    }

    /**
     * Pricing block for a bare treatment id (a doctor-added custom option that
     * has no decision-tree node). Base price only; no option groups.
     */
    public function forTreatment(int $treatmentId): ?array
    {
        $result = $this->pricing->pricing($treatmentId, null);
        if ($result === null) {
            return null;
        }

        return [
            'treatment_id' => $result['treatment_id'],
            'group'        => null,
            'base_price'   => $result['base_price'],
            'gst_pct'      => $result['gst_pct'],
            'options'      => [],
        ];
    }

    /**
     * The single rule for "what does this node cost given the patient's choice":
     * the chosen option's price, else the group's default option, else the
     * treatment's base price, else 0. Shared by the assembler (display) and
     * CaseSelectionService (running estimate) so the rule lives in one place.
     */
    public function priceFor(DecisionTreeNode $node, ?int $optionId = null): float
    {
        $priced = $this->forNode($node);
        if ($priced === null) {
            return 0.0;
        }

        $options = $priced['options'];

        if ($optionId !== null) {
            foreach ($options as $o) {
                if ((int) $o['id'] === (int) $optionId) {
                    return (float) $o['price'];
                }
            }
        }

        foreach ($options as $o) {
            if (! empty($o['is_default'])) {
                return (float) $o['price'];
            }
        }

        return (float) $priced['base_price'];
    }
}
