<?php

namespace App\Services\Treatment;

use App\Models\Treatment;
use App\Models\TreatmentOption;

/**
 * TreatmentPricingService
 * -----------------------
 * The Treatment Module's single entry point for structured, priced options.
 * This is the "brain" behind the pricing API; the Case Acceptance Engine's
 * CasePricingClient (Milestone 6) calls THIS service directly rather than
 * reading `treatment_options` itself — so the ownership boundary holds
 * (engine never touches price tables) without a real HTTP round-trip.
 *
 * Reads only. No caching (prices must always be live — see architecture §8).
 *
 * See docs/plan-case-acceptance-engine.md §4.1 / §8.
 */
class TreatmentPricingService
{
    /**
     * Live priced options for a treatment, optionally filtered to one group.
     *
     * Return shape (stable contract consumed by the engine + the API):
     * [
     *   'treatment_id'   => int,
     *   'treatment_name' => string,
     *   'base_price'     => float,   // the treatment's own default_price
     *   'gst_pct'        => float,
     *   'groups' => [
     *     'implant_system' => [
     *       ['id' => int, 'name' => string, 'price' => float, 'is_default' => bool],
     *       ...
     *     ],
     *     ...
     *   ],
     * ]
     *
     * When $group is given, only that key appears under 'groups'.
     */
    public function pricing(int $treatmentId, ?string $group = null): ?array
    {
        $treatment = Treatment::query()
            ->where('is_active', true)
            ->find($treatmentId);

        if (! $treatment) {
            return null;
        }

        $options = TreatmentOption::query()
            ->where('treatment_id', $treatmentId)
            ->active()
            ->when($group, fn ($q) => $q->forGroup($group))
            ->orderBy('group')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $groups = $options
            ->groupBy('group')
            ->map(fn ($rows) => $rows->map(fn (TreatmentOption $o) => [
                'id'         => $o->id,
                'name'       => $o->name,
                'price'      => (float) $o->price,
                'is_default' => (bool) $o->is_default,
            ])->values()->all())
            ->all();

        return [
            'treatment_id'   => $treatment->id,
            'treatment_name' => $treatment->name,
            'base_price'     => (float) $treatment->default_price,
            'gst_pct'        => (float) ($treatment->gst_pct ?? 0),
            'groups'         => $groups,
        ];
    }
}
