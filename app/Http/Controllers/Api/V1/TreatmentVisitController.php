<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\LabCase;
use App\Models\LabVendor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\TreatmentVisit;
use App\Models\User;
use App\Services\TreatmentVisitService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TreatmentVisitController (API v1)
 * ---------------------------------
 * Mobile create/update/delete for treatment visits. Uses the SAME
 * TreatmentVisitService as the web, so a visit saved on the phone fires the
 * identical side-effects (billing prompt, draft lab case, 6-month recall task).
 *
 *   GET    /api/v1/patients/{patient}/visits/form-options
 *   POST   /api/v1/patients/{patient}/visits
 *   PUT    /api/v1/visits/{visit}
 *   DELETE /api/v1/visits/{visit}
 *
 * Everything is branch-scoped: a visit/patient from another branch returns an
 * enveloped 404.
 */
class TreatmentVisitController extends ApiController
{
    public function __construct(private TreatmentVisitService $service)
    {
    }

    public function store(Request $request, $patient): JsonResponse
    {
        $p = $this->findPatient($request, $patient);

        $data = $request->validate(TreatmentVisitService::rules());
        // Doctor defaults to the logged-in user when the client omits it.
        $data['doctor_id'] = $data['doctor_id'] ?? $request->user()->id;

        $visit = $this->service->create($p, $data);

        return $this->success($this->service->format($visit), 'Treatment visit saved.', 201);
    }

    public function update(Request $request, $visit): JsonResponse
    {
        $v = $this->findVisit($request, $visit);

        $data = $request->validate(TreatmentVisitService::rules());
        $data['doctor_id'] = $data['doctor_id'] ?? $v->doctor_id ?? $request->user()->id;

        $v = $this->service->update($v, $data);

        return $this->success($this->service->format($v), 'Treatment visit updated.');
    }

    /**
     * Full visit detail for view / edit prefill. Uses the service's format()
     * (same shape the web returns) and merges the vitals columns, which format()
     * doesn't currently include, so the mobile edit form round-trips cleanly.
     */
    public function show(Request $request, $visit): JsonResponse
    {
        $v = $this->findVisit($request, $visit);
        $v->load(['doctor', 'visitItems']);

        $out = $this->service->format($v);
        $out += [
            'bp_systolic'      => $v->bp_systolic,
            'bp_diastolic'     => $v->bp_diastolic,
            'pulse_rate'       => $v->pulse_rate,
            'spo2'             => $v->spo2,
            'temperature'      => $v->temperature,
            'blood_sugar'      => $v->blood_sugar,
            'blood_sugar_type' => $v->blood_sugar_type,
            'weight'           => $v->weight,
            'vitals_notes'     => $v->vitals_notes,
        ];

        return $this->success($out, '');
    }

    public function destroy(Request $request, $visit): JsonResponse
    {
        $v = $this->findVisit($request, $visit);
        $v->delete();

        return $this->success(null, 'Treatment visit deleted.');
    }

    /**
     * Everything the mobile form needs to render: doctors, this patient's
     * treatment plans (+ items), lab vendors, the treatment catalogue + their
     * stage maps, and the lab work-category list.
     */
    public function formOptions(Request $request, $patient): JsonResponse
    {
        $p = $this->findPatient($request, $patient);

        $doctors = User::orderBy('name')->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->values();

        $plans = $p->treatmentPlans()
            ->with('items')
            ->latest()
            ->get()
            ->map(fn ($plan) => [
                'id'     => $plan->id,
                'name'   => $plan->plan_name,
                'status' => $plan->status,
                'items'  => $plan->items->map(fn ($it) => [
                    'id'             => $it->id,
                    'treatment_name' => $it->treatment_name,
                    'tooth_number'   => $it->tooth_number,
                    'unit_price'     => (float) $it->unit_price,
                    'status'         => $it->status,
                    'material_variants' => $it->material_variants ?? [],
                ])->values(),
            ])
            ->values();

        $labVendors = LabVendor::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($v) => ['id' => $v->id, 'name' => $v->name])
            ->values();

        return $this->success([
            'current_doctor_id'  => $request->user()->id,
            'doctors'            => $doctors,
            'treatment_plans'    => $plans,
            'lab_vendors'        => $labVendors,
            // Treatment names for the autocomplete, plus the per-treatment stage maps.
            'treatments'         => Treatment::orderBy('name')->pluck('name')->values(),
            'stages'             => TreatmentVisit::allStagesFromDb(),
            'lab_work_categories'=> LabCase::WORK_CATEGORIES,
        ], '');
    }

    // ── Branch-scoped lookups (enveloped 404 across branches) ──────────────────

    private function findPatient(Request $request, $id): Patient
    {
        $patient = Patient::where('branch_id', $request->user()->branch_id)
            ->whereKey($id)
            ->first();

        if (! $patient) {
            $this->notFound('Patient not found.');
        }

        return $patient;
    }

    private function findVisit(Request $request, $id): TreatmentVisit
    {
        $visit = TreatmentVisit::with('patient:id,branch_id,name')
            ->whereKey($id)
            ->first();

        if (! $visit || ! $visit->patient ||
            (int) $visit->patient->branch_id !== (int) $request->user()->branch_id) {
            $this->notFound('Treatment visit not found.');
        }

        return $visit;
    }

    private function notFound(string $message): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => [],
        ], 404));
    }
}
