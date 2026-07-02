<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\TreatmentVisit;
use App\Services\TreatmentVisitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TreatmentVisitController (web)
 * ------------------------------
 * Thin wrapper over TreatmentVisitService. All the save logic + side-effects
 * (billing prompt, lab case, recall task) now live in the service so the web
 * and the mobile API behave identically. The JSON response shape consumed by
 * the existing Alpine front-end ({ success, visit }) is unchanged.
 */
class TreatmentVisitController extends Controller
{
    public function __construct(private TreatmentVisitService $service)
    {
    }

    public function store(Request $request, Patient $patient): JsonResponse
    {
        $data  = $request->validate(TreatmentVisitService::rules());
        $visit = $this->service->create($patient, $data);

        return response()->json([
            'success' => true,
            'visit'   => $this->service->format($visit),
        ]);
    }

    public function update(Request $request, TreatmentVisit $visit): JsonResponse
    {
        $data  = $request->validate(TreatmentVisitService::rules());
        $visit = $this->service->update($visit, $data);

        return response()->json([
            'success' => true,
            'visit'   => $this->service->format($visit),
        ]);
    }

    public function destroy(TreatmentVisit $visit): JsonResponse
    {
        $visit->delete();

        return response()->json(['success' => true]);
    }

    // ── Print visit ───────────────────────────────────────────────────────────
    public function print(TreatmentVisit $visit)
    {
        $visit->load(['patient', 'doctor']);
        $print  = \App\Models\AppSetting::group('print');
        $clinic = \App\Models\AppSetting::group('clinic');
        return view('visits.print', compact('visit', 'print', 'clinic'));
    }
}
