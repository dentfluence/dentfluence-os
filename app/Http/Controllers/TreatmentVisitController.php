<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\TreatmentVisit;
use App\Services\PatientProfileService;
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
    public function __construct(
        private TreatmentVisitService $service,
        private PatientProfileService $profileService,
    ) {
    }

    /**
     * Dedicated Treatment Visit form page (08-05, presentation-only).
     * Reuses PatientProfileService::tabData('visits') — the exact same
     * data the Treatment Visits tab fragment loads — so there is one
     * source of truth for what this page needs, not a second query set.
     */
    public function create(Request $request, Patient $patient)
    {
        $data = $this->profileService->tabData($patient, 'visits');

        return view('patients.treatment-visit-form', $data + [
            'prefillAppointmentId' => $request->query('appointment_id'),
            'prefillPlanId'        => $request->query('plan_id'),
        ]);
    }

    public function edit(TreatmentVisit $visit)
    {
        $patient = $visit->patient;
        $data    = $this->profileService->tabData($patient, 'visits');

        return view('patients.treatment-visit-form', $data + ['visit' => $visit]);
    }

    public function store(Request $request, Patient $patient): JsonResponse
    {
        $data  = $request->validate(TreatmentVisitService::rules());
        $visit = $this->service->create($patient, $data);

        return response()->json([
            'success' => true,
            'visit'   => $this->service->format($visit),
            // The visit just saved is history for the next one. Returning the
            // recomputed map keeps repeat-work detection correct without a
            // page reload, and without the browser deriving progress itself.
            'procedure_progress' => $this->progressMapFor($patient->id),
        ]);
    }

    public function update(Request $request, TreatmentVisit $visit): JsonResponse
    {
        $data  = $request->validate(TreatmentVisitService::rules());
        $visit = $this->service->update($visit, $data);

        return response()->json([
            'success' => true,
            'visit'   => $this->service->format($visit),
            'procedure_progress' => $this->progressMapFor($visit->patient_id),
        ]);
    }

    /**
     * Canonical per-(procedure, tooth) clinical progress for this patient.
     * Asked of DerivedProgressService, never computed here — see that class's
     * frozen invariant.
     *
     * @return array<string,string>
     */
    private function progressMapFor(int $patientId): array
    {
        return app(\App\Services\Clinical\DerivedProgressService::class)
            ->deriveProcedureProgressForPatient($patientId);
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
        // Closure sprint (08-05): case sheet was missing billed procedures
        // and lab case info because they were never eager-loaded here —
        // additive only, print.blade.php now renders them when present.
        $visit->load(['patient', 'doctor', 'visitItems', 'labCases.vendor']);
        $print  = \App\Models\AppSetting::group('print');
        $clinic = \App\Models\AppSetting::group('clinic');
        return view('visits.print', compact('visit', 'print', 'clinic'));
    }
}
