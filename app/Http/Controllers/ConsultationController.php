<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConsultationRequest;
use App\Models\Consultation;
use App\Models\ConsultationCohaReport;
use App\Models\ConsultationSpecialtyModule;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;

class ConsultationController extends Controller
{
    public function create(Request $request, Patient $patient)
    {
        $doctors = User::orderBy('name')->get();

        // ── P2C9: Previous consultation context ─────────────────────────────────
        // Load last 5 consultations for the selector dropdown (excluding COHA type)
        $pastConsultations = $patient->consultations()
            ->where(function ($q) {
                $q->where('consultation_type', '!=', 'coha')
                  ->orWhereNull('consultation_type');
            })
            ->latest()
            ->take(5)
            ->with('doctor')
            ->get();

        // Auto-select the most recent one as the default previous context
        $previousConsultation = $pastConsultations->first();

        $consultation = null; // Ensures all partials have $consultation defined (nullsafe ?-> requires the variable to exist)

        // ── Backdated entry: load patient's past appointments (last 90 days) ──
        $pastAppointments = $patient->appointments()
            ->whereDate('appointment_date', '<=', today())
            ->orderByDesc('appointment_date')
            ->with('treatment:id,name')
            ->take(20)
            ->get(['id', 'appointment_date', 'status', 'treatment_id']);

        return view('consultations.create', compact('patient', 'doctors', 'pastConsultations', 'previousConsultation', 'consultation', 'pastAppointments'));
    }

    public function store(StoreConsultationRequest $request, Patient $patient)
    {
        $data = $request->validated();

        // Always inject patient/branch from route — brain form doesn't send these
        $data['patient_id']        = $patient->id;
        $data['branch_id']         = $data['branch_id'] ?? auth()->user()->branch_id ?? 1;
        $data['status']            = $data['status'] ?? 'completed';
        // ── Consultation (clinical) date ──────────────────────────────────────
        // The visible date picker is the source of truth. If it's blank, fall back
        // to a linked appointment's date, then to today. Note: created_at (the staff
        // entry log) is always stamped today by Laravel — backdating only moves the
        // clinical date, never the record of when the entry was actually made.
        if (empty($data['consultation_date']) && !empty($data['appointment_id'])) {
            $linkedAppt = \App\Models\Appointment::find($data['appointment_id']);
            if ($linkedAppt) {
                $data['consultation_date'] = $linkedAppt->appointment_date;
            }
        }
        $data['consultation_date'] = $data['consultation_date'] ?? now();

        // ── P2C: Sync consultation_type → visit_type (backward compat) ────────
        if (!empty($data['consultation_type'])) {
            $data['visit_type'] = $this->consultationTypeToVisitType($data['consultation_type']);
        }

        // ── P2C: Decode specialty JSON strings from Alpine x-model ────────────
        // The Alpine form posts specialty_findings and accepted_specialties as
        // JSON strings (via hidden inputs). Decode them before saving.
        if (isset($data['specialty_findings']) && is_string($data['specialty_findings'])) {
            $data['specialty_findings'] = json_decode($data['specialty_findings'], true);
        }
        if (isset($data['accepted_specialties']) && is_string($data['accepted_specialties'])) {
            $data['accepted_specialties'] = json_decode($data['accepted_specialties'], true);
        }

        // ── Prescription panel: decode JSON from universal component ──────────
        // Component posts prescriptions_data / instructions_data as JSON strings.
        // Map them into the model columns (prescriptions / instructions).
        if (!empty($data['prescriptions_data'])) {
            $data['prescriptions'] = is_string($data['prescriptions_data'])
                ? json_decode($data['prescriptions_data'], true)
                : $data['prescriptions_data'];
        }
        unset($data['prescriptions_data']);

        if (!empty($data['instructions_data'])) {
            $data['instructions'] = is_string($data['instructions_data'])
                ? json_decode($data['instructions_data'], true)
                : $data['instructions_data'];
        }
        unset($data['instructions_data']);

        // Pull out specialty_modules — handled separately after consultation create
        $specialtyModules = $data['specialty_modules'] ?? [];
        unset($data['specialty_modules']);

        $consultation = Consultation::create($data);

        // ── P2C: Save specialty module findings ───────────────────────────────
        // Each accepted specialty module is saved as a ConsultationSpecialtyModule row.
        foreach ($specialtyModules as $module) {
            if (empty($module['specialty_tag'])) continue;
            ConsultationSpecialtyModule::updateOrCreate(
                [
                    'consultation_id' => $consultation->id,
                    'specialty_tag'   => $module['specialty_tag'],
                ],
                [
                    'findings'    => $module['findings'] ?? [],
                    'accepted_at' => now(),
                    'rejected_at' => null,
                ]
            );
        }

        // NOTE (2026-07-09): file/photo upload was deliberately removed from this
        // controller. Consultation is a diagnosis/documentation form, not a photo
        // capture point — that's handled by the patient's Documents tab and the
        // mobile Capture Photo flow, both of which already write to clinical_files
        // via ClinicalFileUploadService. Adding a third upload entry point here
        // would just recreate the fragmentation the Clinical Library cleanup this
        // week was trying to remove. See memory: project_clinical_library_audit_0709.

        if ($request->expectsJson()) {
            return response()->json([
                'success'      => true,
                'message'      => 'Consultation saved.',
                'redirect_url' => route('consultations.show', $consultation),
            ]);
        }

        // P2C10b: "Save & Start Treatment Plan" button
        if ($request->filled('_save_and_plan')) {
            return redirect()->route('treatment-plans.from-consultation', [$patient, $consultation]);
        }

        // Stay on consultation view — doctor can continue to Rx or Treatment Plan from there
        return redirect()
            ->route('consultations.show', $consultation)
            ->with('success', 'Consultation saved.');
    }

    public function show(Consultation $consultation, Patient $patient = null)
    {
        $consultation->load(['patient', 'doctor', 'responsible', 'specialtyModules']);

        // Resolve patient — route may or may not inject it
        $patient = $patient ?? $consultation->patient;

        // ── Clinical Intelligence Panel data (rule-based, no AI) ─────────────
        // Patient already loaded above; fields (medical_alert, medical_conditions,
        // allergies, recall_status, next_recall_date) are direct attributes.

        // Previous consultations (last 5, excluding this one)
        $prevConsultations = $patient->consultations()
            ->where('id', '!=', $consultation->id)
            ->latest('consultation_date')
            ->take(5)
            ->with('doctor')
            ->get();

        // Last consultation summary
        $lastConsultation = $prevConsultations->first();

        // Previous prescriptions via Prescription model (last 5 for this patient)
        $prevPrescriptions = \App\Models\Prescription\Prescription::where('patient_id', $patient->id)
            ->latest()
            ->take(5)
            ->with('items')
            ->get();

        // Pending treatment plans
        $pendingTreatmentPlans = $patient->treatmentPlans()
            ->whereIn('status', ['pending', 'in_progress', 'approved'])
            ->latest()
            ->take(3)
            ->get();

        return view('consultations.show', compact(
            'consultation',
            'patient',
            'prevConsultations',
            'lastConsultation',
            'prevPrescriptions',
            'pendingTreatmentPlans'
        ));
    }

    public function print(Patient $patient, Consultation $consultation)
    {
        // Load only valid relationships (complaints / clinicalFindings are NOT defined on this model)
        $consultation->load(['patient', 'doctor', 'treatmentPlans.items', 'specialtyModules']);

        // Latest prescription (drafts included) tied to this consultation.
        // Embedded directly on the Case Paper so staff can avoid printing a
        // separate prescription sheet when one already exists for the visit.
        $prescription = \App\Models\Prescription\Prescription::where('consultation_id', $consultation->id)
            ->with('items')
            ->latest()
            ->first();

        $print  = \App\Models\AppSetting::group('print');
        $clinic = \App\Models\AppSetting::group('clinic');
        return view('consultations.print', compact('consultation', 'print', 'clinic', 'prescription'));
    }

    /** Standalone edit — resolves patient from the consultation itself. */
    public function editStandalone(Consultation $consultation)
    {
        $patient = $consultation->patient;
        return $this->edit($patient, $consultation);
    }

    /** Standalone update — resolves patient from the consultation itself. */
    public function updateStandalone(StoreConsultationRequest $request, Consultation $consultation)
    {
        $patient = $consultation->patient;
        return $this->update($request, $patient, $consultation);
    }

    public function edit(Patient $patient, Consultation $consultation)
    {
        $doctors = User::orderBy('name')->get();
        $pastConsultations = $patient->consultations()
            ->where('id', '!=', $consultation->id)
            ->where(function ($q) {
                $q->where('consultation_type', '!=', 'coha')->orWhereNull('consultation_type');
            })
            ->latest()
            ->take(5)
            ->with('doctor')
            ->get();
        // For edit: use already-linked previous consultation if set, else most recent
        $previousConsultation = $consultation->previous_consultation_id
            ? $pastConsultations->firstWhere('id', $consultation->previous_consultation_id)
              ?? Consultation::with('doctor')->find($consultation->previous_consultation_id)
            : $pastConsultations->first();

        return view('consultations.create', compact('consultation', 'patient', 'doctors', 'pastConsultations', 'previousConsultation'));
    }

    public function update(StoreConsultationRequest $request, Patient $patient, Consultation $consultation)
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? 'completed';

        // ── P2C: Sync consultation_type → visit_type ──────────────────────────
        if (!empty($data['consultation_type'])) {
            $data['visit_type'] = $this->consultationTypeToVisitType($data['consultation_type']);
        }

        // ── P2C: Decode specialty JSON strings ────────────────────────────────
        if (isset($data['specialty_findings']) && is_string($data['specialty_findings'])) {
            $data['specialty_findings'] = json_decode($data['specialty_findings'], true);
        }
        if (isset($data['accepted_specialties']) && is_string($data['accepted_specialties'])) {
            $data['accepted_specialties'] = json_decode($data['accepted_specialties'], true);
        }

        // ── Prescription panel: decode JSON from universal component ──────────
        if (!empty($data['prescriptions_data'])) {
            $data['prescriptions'] = is_string($data['prescriptions_data'])
                ? json_decode($data['prescriptions_data'], true)
                : $data['prescriptions_data'];
        }
        unset($data['prescriptions_data']);

        if (!empty($data['instructions_data'])) {
            $data['instructions'] = is_string($data['instructions_data'])
                ? json_decode($data['instructions_data'], true)
                : $data['instructions_data'];
        }
        unset($data['instructions_data']);

        // Pull out specialty_modules
        $specialtyModules = $data['specialty_modules'] ?? [];
        unset($data['specialty_modules']);

        $consultation->update($data);

        // ── P2C: Sync specialty modules ───────────────────────────────────────
        // Submitted modules = accepted. Any existing module NOT in the list
        // gets its rejected_at set (soft-reject).
        $submittedTags = array_column($specialtyModules, 'specialty_tag');

        foreach ($specialtyModules as $module) {
            if (empty($module['specialty_tag'])) continue;
            ConsultationSpecialtyModule::updateOrCreate(
                ['consultation_id' => $consultation->id, 'specialty_tag' => $module['specialty_tag']],
                ['findings' => $module['findings'] ?? [], 'accepted_at' => now(), 'rejected_at' => null]
            );
        }

        // Soft-reject modules removed by the doctor during edit
        if (!empty($submittedTags)) {
            $consultation->specialtyModules()
                ->whereNotIn('specialty_tag', $submittedTags)
                ->whereNull('rejected_at')
                ->update(['rejected_at' => now()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success'      => true,
                'message'      => 'Consultation updated.',
                'redirect_url' => route('consultations.show', $consultation),
            ]);
        }

        // Return to the consultation view so the doctor can continue their workflow
        return redirect()
            ->route('consultations.show', $consultation)
            ->with('success', 'Consultation updated.');
    }

    public function destroy(Patient $patient, Consultation $consultation)
    {
        $consultation->delete();

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Consultation deleted.');
    }

    public function forPatient(\App\Models\Patient $patient)
    {
        $consultations = $patient->consultations()->latest()->get();
        return view('consultations.index', compact('patient', 'consultations'));
    }

    // ── Same Issue ────────────────────────────────────────────────────────────

    /**
     * Show the Same Issue consultation form.
     * Auto-loads previous consultation data for context.
     */
    public function sameIssueCreate(Patient $patient)
    {
        $doctors = User::orderBy('name')->get();

        // Last non-coha consultation is the one being revisited
        $previousConsultation = $patient->consultations()
            ->whereNotIn('consultation_type', ['coha', 'minor_visit', 'emergency'])
            ->latest('consultation_date')
            ->with(['doctor', 'treatmentPlans'])
            ->first();

        return view('consultations.same-issue', compact('patient', 'doctors', 'previousConsultation'));
    }

    /**
     * Store a Same Issue consultation.
     */
    public function sameIssueStore(Request $request, Patient $patient)
    {
        $data = $request->validate([
            'doctor_id'               => 'required|exists:users,id',
            'consultation_date'       => 'nullable|date',
            'previous_consultation_id'=> 'nullable|exists:consultations,id',
            'update_notes'            => 'required|string',
            'additional_findings'     => 'nullable|string',
            'primary_diagnosis'       => 'nullable|string',
            'diagnosis_notes'         => 'nullable|string',
            'finishing_notes'         => 'nullable|string',
            'prescriptions_data'      => 'nullable|string',
            'instructions_data'       => 'nullable|string',
        ]);

        if (!empty($data['prescriptions_data'])) {
            $data['prescriptions'] = json_decode($data['prescriptions_data'], true);
        }
        unset($data['prescriptions_data']);

        if (!empty($data['instructions_data'])) {
            $data['instructions'] = json_decode($data['instructions_data'], true);
        }
        unset($data['instructions_data']);

        $consultation = Consultation::create(array_merge($data, [
            'patient_id'        => $patient->id,
            'branch_id'         => auth()->user()->branch_id ?? 1,
            'consultation_type' => 'same_issue',
            'visit_type'        => 'routine',
            'status'            => 'completed',
            'consultation_date' => $data['consultation_date'] ?? now(),
        ]));

        return redirect()
            ->route('consultations.show', $consultation)
            ->with('success', 'Same Issue consultation saved.');
    }

    // ── Minor Visit ───────────────────────────────────────────────────────────

    /**
     * Show the Minor Visit consultation form.
     */
    public function minorVisitCreate(Patient $patient)
    {
        $doctors = User::orderBy('name')->get();

        // Offer last completed treatment plan for context (clinic-related visits)
        $lastTreatmentPlan = $patient->treatmentPlans()
            ->latest()
            ->first();

        return view('consultations.minor-visit', compact('patient', 'doctors', 'lastTreatmentPlan'));
    }

    /**
     * Store a Minor Visit consultation.
     */
    public function minorVisitStore(Request $request, Patient $patient)
    {
        $data = $request->validate([
            'doctor_id'                   => 'required|exists:users,id',
            'consultation_date'           => 'nullable|date',
            'related_to_clinic_treatment' => 'required|boolean',
            'procedure_performed'         => 'required|string',
            'chief_complaint'             => 'nullable|string',
            'hopi_final'                  => 'nullable|string',
            'primary_diagnosis'           => 'nullable|string',
            'clinical_data'               => 'nullable|array',
            'advice'                      => 'nullable|string',
            'finishing_notes'             => 'nullable|string',
            'prescriptions_data'          => 'nullable|string',
            'instructions_data'           => 'nullable|string',
        ]);

        if (!empty($data['prescriptions_data'])) {
            $data['prescriptions'] = json_decode($data['prescriptions_data'], true);
        }
        unset($data['prescriptions_data']);

        if (!empty($data['instructions_data'])) {
            $data['instructions'] = json_decode($data['instructions_data'], true);
        }
        unset($data['instructions_data']);

        $consultation = Consultation::create(array_merge($data, [
            'patient_id'        => $patient->id,
            'branch_id'         => auth()->user()->branch_id ?? 1,
            'consultation_type' => 'minor_visit',
            'visit_type'        => 'routine',
            'status'            => 'completed',
            'consultation_date' => $data['consultation_date'] ?? now(),
        ]));

        return redirect()
            ->route('consultations.show', $consultation)
            ->with('success', 'Minor Visit saved.');
    }

    // ── Emergency Visit ───────────────────────────────────────────────────────

    /**
     * Show the Emergency Visit form.
     */
    public function emergencyCreate(Patient $patient)
    {
        $doctors = User::orderBy('name')->get();
        return view('consultations.emergency', compact('patient', 'doctors'));
    }

    /**
     * Store an Emergency Visit consultation.
     */
    public function emergencyStore(Request $request, Patient $patient)
    {
        $data = $request->validate([
            'doctor_id'                    => 'required|exists:users,id',
            'consultation_date'            => 'nullable|date',
            'chief_complaint'              => 'required|string',
            'hopi_final'                   => 'nullable|string',
            'clinical_data'                => 'nullable|array',
            'primary_diagnosis'            => 'nullable|string',
            'emergency_treatment_rendered' => 'required|string',
            'advice'                       => 'nullable|string',
            'finishing_notes'              => 'nullable|string',
            'prescriptions_data'           => 'nullable|string',
            'instructions_data'            => 'nullable|string',
        ]);

        if (!empty($data['prescriptions_data'])) {
            $data['prescriptions'] = json_decode($data['prescriptions_data'], true);
        }
        unset($data['prescriptions_data']);

        if (!empty($data['instructions_data'])) {
            $data['instructions'] = json_decode($data['instructions_data'], true);
        }
        unset($data['instructions_data']);

        $consultation = Consultation::create(array_merge($data, [
            'patient_id'        => $patient->id,
            'branch_id'         => auth()->user()->branch_id ?? 1,
            'consultation_type' => 'emergency',
            'visit_type'        => 'emergency',
            'status'            => 'completed',
            'consultation_date' => $data['consultation_date'] ?? now(),
        ]));

        // If "Convert to New Consultation" was clicked, redirect to new consultation pre-filled
        if ($request->filled('_convert_to_new')) {
            return redirect()
                ->route('patients.consultations.create', $patient)
                ->with('success', 'Emergency visit saved. Create a New Consultation for definitive planning.')
                ->with('from_emergency_id', $consultation->id);
        }

        return redirect()
            ->route('consultations.show', $consultation)
            ->with('success', 'Emergency Visit saved.');
    }

    // ── COHA (Comprehensive Oral Health Assessment) ───────────────────────────

    /**
     * Show the blank COHA assessment form for a patient.
     * P2C7a — dedicated view, separate from standard create.blade.php.
     */
    public function cohaCreate(Patient $patient)
    {
        $doctors    = User::orderBy('name')->get();
        $cohaReport = new ConsultationCohaReport(); // empty instance — blade uses null-coalescing throughout
        return view('consultations.coha', compact('patient', 'doctors', 'cohaReport'));
    }

    /**
     * Store a new COHA consultation + its ConsultationCohaReport.
     * P2C7b.
     */
    public function cohaStore(Request $request, Patient $patient)
    {
        // 1 — Create the Consultation record (type = coha)
        $consultation = Consultation::create([
            'patient_id'        => $patient->id,
            'doctor_id'         => $request->input('doctor_id', auth()->id()),
            'branch_id'         => auth()->user()->branch_id ?? 1,
            'consultation_type' => 'coha',
            'visit_type'        => 'routine',   // backward-compat
            'consultation_date' => $request->input('consultation_date', now()),
            'status'            => 'completed',
            'chief_complaint'   => 'Comprehensive Oral Health Assessment',
            'doctor_notes'      => $request->input('doctor_notes'),
            'primary_diagnosis' => null,
        ]);

        // 2 — Create the ConsultationCohaReport (all 9 sections as JSON)
        $cohaReport = ConsultationCohaReport::create([
            'consultation_id'    => $consultation->id,
            'patient_id'         => $patient->id,
            'doctor_id'          => $request->input('doctor_id', auth()->id()),
            'report_date'        => $request->input('consultation_date', now()),
            'extraoral'          => $this->parseSection($request, 'extraoral'),
            'soft_tissue'        => $this->parseSection($request, 'soft_tissue'),
            'tooth_assessment'   => $this->parseSection($request, 'tooth_assessment'),
            'ortho_findings'     => $this->parseSection($request, 'ortho_findings'),
            'perio_findings'     => $this->parseSection($request, 'perio_findings'),
            'esthetic_findings'  => $this->parseSection($request, 'esthetic_findings'),
            'risk_assessment'    => $this->parseSection($request, 'risk_assessment'),
            'monitoring_teeth'   => $request->input('monitoring_teeth', []),
            'treatment_awareness'=> $this->parseSection($request, 'treatment_awareness'),
            'doctor_notes'       => $request->input('doctor_notes'),
        ]);

        // 3 — Link COHA report back to consultation
        $consultation->update(['coha_report_id' => $cohaReport->id]);

        return redirect()
            ->route('coha.report', [$patient, $consultation])
            ->with('success', 'COHA assessment saved. Here is the patient report.');
    }

    /**
     * Show the COHA form pre-filled for editing an existing report.
     */
    public function cohaEdit(Patient $patient, Consultation $consultation)
    {
        abort_if($consultation->consultation_type !== 'coha', 404);
        $cohaReport = $consultation->cohaReport;
        $doctors    = User::orderBy('name')->get();
        return view('consultations.coha', compact('patient', 'consultation', 'cohaReport', 'doctors'));
    }

    /**
     * Update an existing COHA consultation + report.
     */
    public function cohaUpdate(Request $request, Patient $patient, Consultation $consultation)
    {
        abort_if($consultation->consultation_type !== 'coha', 404);

        $consultation->update([
            'doctor_id'         => $request->input('doctor_id', $consultation->doctor_id),
            'consultation_date' => $request->input('consultation_date', $consultation->consultation_date),
            'doctor_notes'      => $request->input('doctor_notes'),
        ]);

        $cohaReport = $consultation->cohaReport;
        if ($cohaReport) {
            $cohaReport->update([
                'report_date'        => $request->input('consultation_date', $cohaReport->report_date),
                'extraoral'          => $this->parseSection($request, 'extraoral'),
                'soft_tissue'        => $this->parseSection($request, 'soft_tissue'),
                'tooth_assessment'   => $this->parseSection($request, 'tooth_assessment'),
                'ortho_findings'     => $this->parseSection($request, 'ortho_findings'),
                'perio_findings'     => $this->parseSection($request, 'perio_findings'),
                'esthetic_findings'  => $this->parseSection($request, 'esthetic_findings'),
                'risk_assessment'    => $this->parseSection($request, 'risk_assessment'),
                'monitoring_teeth'   => $request->input('monitoring_teeth', []),
                'treatment_awareness'=> $this->parseSection($request, 'treatment_awareness'),
                'doctor_notes'       => $request->input('doctor_notes'),
            ]);
        }

        return redirect()
            ->route('coha.report', [$patient, $consultation])
            ->with('success', 'COHA assessment updated.');
    }

    /**
     * Show the printable patient-facing COHA report.
     * P2C7c.
     */
    public function cohaReport(Patient $patient, Consultation $consultation)
    {
        abort_if($consultation->consultation_type !== 'coha', 404);
        $cohaReport = $consultation->cohaReport;
        abort_if(!$cohaReport, 404);

        $clinic = \App\Models\AppSetting::group('clinic');
        $print  = \App\Models\AppSetting::group('print');

        return view('consultations.coha-print', compact(
            'patient', 'consultation', 'cohaReport', 'clinic', 'print'
        ));
    }

    /**
     * Helper: extract a named section from the request.
     * Handles both array inputs (from Blade form) and JSON strings.
     */
    private function parseSection(Request $request, string $section): array
    {
        $value = $request->input($section);
        if (is_array($value))  return $value;
        if (is_string($value)) return json_decode($value, true) ?? [];
        return [];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Map the new consultation_type enum back to the legacy visit_type enum.
     * Keeps old records consistent while new records use both columns.
     */
    private function consultationTypeToVisitType(string $consultationType): string
    {
        return match($consultationType) {
            'emergency'   => 'emergency',
            'followup',
            'same_issue',
            'recall_6m',
            'minor_visit',
            'coha'        => 'routine',
            default       => 'routine', // 'new' → routine
        };
    }
}
