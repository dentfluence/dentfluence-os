<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Services\Treatment\TreatmentPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TreatmentPricingController (API v1)
 * -----------------------------------
 * Thin read-only endpoint over TreatmentPricingService — the Treatment
 * Module's live priced-options lookup. Serves the Case Journey builder's
 * live-cost UI (and any future client). No writes, no price caching.
 *
 * GET /api/v1/treatment-pricing?treatment_id=&group=
 *
 * See docs/plan-case-acceptance-engine.md §4.1.
 */
class TreatmentPricingController extends ApiController
{
    public function index(Request $request, TreatmentPricingService $pricing): JsonResponse
    {
        $data = $request->validate([
            'treatment_id' => ['required', 'integer', 'exists:treatments,id'],
            'group'        => ['nullable', 'string', 'max:50'],
        ]);

        $result = $pricing->pricing((int) $data['treatment_id'], $data['group'] ?? null);

        if ($result === null) {
            return $this->error('Treatment not found or inactive.', [], 404);
        }

        return $this->success($result, '');
    }
}
