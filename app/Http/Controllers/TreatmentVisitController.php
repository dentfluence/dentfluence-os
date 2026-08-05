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
        // G1 — service handles dependent cleanup (items, pending prompts)
        // and refuses to delete a visit whose items are already invoiced.
        $this->service->delete($visit);

        return response()->json(['success' => true]);
    }

    /**
     * UX-04 — record the explicit "No Treatment Done Today" answer from the
     * post-consultation gate. A recorded answer (not a silent dismissal):
     * it suppresses the gate for the rest of the day and renders the
     * progress strip's Treatment Visit step as "skipped".
     */
    public function noneToday(\Illuminate\Http\Request $request, Patient $patient): JsonResponse
    {
        $data = $request->validate([
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
        ]);

        app(\App\Services\Relationship\ActivityEngine::class)->log(
            subject:     $patient,
            event:       'treatment_visit.none_today',
            actor:       $request->user(),
            metadata:    [
                'patient_id'     => $patient->id,
                'appointment_id' => $data['appointment_id'] ?? null,
                'date'           => now()->toDateString(),
            ],
            description: 'Doctor confirmed no treatment was performed today',
        );

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
