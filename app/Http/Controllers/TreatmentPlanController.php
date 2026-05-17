<?php

namespace App\Http\Controllers;

use App\Models\Consultation;
use App\Models\TreatmentPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TreatmentPlanController extends Controller
{
    /**
     * POST /consultations/{consultation}/plans
     *
     * Creates or replaces all treatment plan rows for a consultation.
     * Accepts a JSON payload of "best" and "acceptable" plan rows coming from
     * the Alpine.js tpBest / tpAcceptable arrays.
     *
     * Expected body (JSON or form-encoded):
     *   treatment_plan_best        : JSON string  [ { tooth, treatment, cost }, … ]
     *   treatment_plan_acceptable  : JSON string  [ { tooth, treatment, cost }, … ]
     *   aocp_best                  : bool
     *   aocp_best_plan             : string
     *   aocp_acceptable            : bool
     *   aocp_acceptable_plan       : string
     */
    public function store(Request $request, Consultation $consultation): JsonResponse
    {
        $this->authorize('update', $consultation);

        $request->validate([
            'treatment_plan_best'       => ['nullable', 'string'],
            'treatment_plan_acceptable' => ['nullable', 'string'],
            'aocp_best'                 => ['nullable', 'boolean'],
            'aocp_best_plan'            => ['nullable', 'string', 'max:100'],
            'aocp_acceptable'           => ['nullable', 'boolean'],
            'aocp_acceptable_plan'      => ['nullable', 'string', 'max:100'],
        ]);

        $bestRows       = $this->parseRows($request->input('treatment_plan_best'));
        $acceptableRows = $this->parseRows($request->input('treatment_plan_acceptable'));

        DB::transaction(function () use ($consultation, $bestRows, $acceptableRows, $request) {
            // Remove existing non-deleted plans and rebuild fresh.
            // Using forceDelete here so we don't accumulate stale soft-deleted rows
            // every time the form is auto-saved.  Adjust to softDelete if you need history.
            $consultation->treatmentPlans()->forceDelete();

            $now = now();

            $rows = [];

            foreach ($bestRows as $row) {
                if (empty($row['treatment'])) {
                    continue;
                }
                $rows[] = [
                    'consultation_id'  => $consultation->id,
                    'plan_type'        => 'best',
                    'tooth_area'       => $row['tooth']       ?? null,
                    'procedure'        => $row['treatment']   ?? null,
                    'cost'             => isset($row['cost']) ? (float) $row['cost'] : null,
                    'status'           => 'pending',
                    'aocp_enabled'     => (bool) $request->boolean('aocp_best'),
                    'aocp_plan'        => $request->input('aocp_best_plan'),
                    'sort_order'       => $row['_idx']        ?? 0,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }

            foreach ($acceptableRows as $row) {
                if (empty($row['treatment'])) {
                    continue;
                }
                $rows[] = [
                    'consultation_id'  => $consultation->id,
                    'plan_type'        => 'acceptable',
                    'tooth_area'       => $row['tooth']       ?? null,
                    'procedure'        => $row['treatment']   ?? null,
                    'cost'             => isset($row['cost']) ? (float) $row['cost'] : null,
                    'status'           => 'pending',
                    'aocp_enabled'     => (bool) $request->boolean('aocp_acceptable'),
                    'aocp_plan'        => $request->input('aocp_acceptable_plan'),
                    'sort_order'       => $row['_idx']        ?? 0,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }

            if (! empty($rows)) {
                TreatmentPlan::insert($rows);
            }
        });

        $plans = $consultation->treatmentPlans()->orderBy('plan_type')->orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'message' => 'Treatment plans saved.',
            'plans'   => $plans,
            'totals'  => [
                'best'       => $plans->where('plan_type', 'best')->sum('cost'),
                'acceptable' => $plans->where('plan_type', 'acceptable')->sum('cost'),
            ],
        ]);
    }

    /**
     * DELETE /plans/{plan}
     *
     * Soft-deletes a single treatment plan row.
     * Returns updated totals for the parent consultation.
     */
    public function destroy(TreatmentPlan $plan): JsonResponse
    {
        $this->authorize('delete', $plan);

        $consultation = $plan->consultation;

        $plan->delete(); // soft delete via SoftDeletes trait on model

        $remaining = $consultation->treatmentPlans()->orderBy('plan_type')->orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'message' => 'Treatment plan row deleted.',
            'totals'  => [
                'best'       => $remaining->where('plan_type', 'best')->sum('cost'),
                'acceptable' => $remaining->where('plan_type', 'acceptable')->sum('cost'),
            ],
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Decode a JSON string coming from the Alpine.js payload.
     * Returns an array of row arrays, each tagged with a _idx key.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseRows(?string $json): array
    {
        if (empty($json)) {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->values()
            ->map(fn ($row, $idx) => array_merge((array) $row, ['_idx' => $idx]))
            ->all();
    }
}
