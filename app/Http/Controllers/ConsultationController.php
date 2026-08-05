<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConsultationRequest;
use App\Models\Consultation;
use App\Models\ConsultationCohaReport;
use App\Models\ConsultationSpecialtyModule;
use App\Models\Patient;
use App\Models\Prescription\Prescription;
use App\Models\Presentation;
use App\Models\User;
use App\Services\Prescription\PrescriptionQuickSaveService;
use App\Support\QrCodeGenerator;
use Illuminate\Http\Request;

class ConsultationController extends Controller
{
    /** Note field name used by the Prescription section embedded on this
     *  screen — deliberately NOT "prescription_notes" (that's already a live
     *  Consultation column used by the separate Brain quick-note UI on the
     *  patient create page; reusing it here would silently overwrite it). */
    private const RX_NOTE_FIELD = 'rx_general_instructions';

    public function __construct(private PrescriptionQuickSaveService $quickSave) {}

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
        $linkedPrescription = null; // No consultation saved yet — nothing to link/prefill from.

        // ── Backdated entry: load patient's past appointments (last 90 days) ──
        $pastAppointments = $patient->appointments()
            ->whereDate('appointment_date', '<=', today())
            ->orderByDesc('appointment_date')
            ->with('treatment:id,name')
            ->take(20)
            ->get(['id', 'appointment_date', 'status', 'treatment_id']);

        return view('consultations.create', compact('patient', 'doctors', 'pastConsultations', 'previousConsultation', 'consultation', 'pastAppointments', 'linkedPrescription'));
    }

    public function store(StoreConsultationRequest $request, Patient $patient)
    {
        $data = $request->validated();

        // Slice 6 (2026-08-03): ONE writer per business process. The standard
        // form's same_issue chip is retired — Same Issue records are created
        // only by sameIssueStore(). This guard catches stale tabs / direct
        // posts so the schema-incompatible chip shape can never come back.
        // ('same_issue' stays in the validator's in: list only so LEGACY
        // records can still round-trip through update().)
        if (($data['consultation_type'] ?? null) === 'same_issue') {
            return redirect()
                ->route('patients.consultations.same-issue.create', $patient)
                ->with('info', 'Same Issue visits are recorded on their own screen now — please use this form.');
        }

        // Always inject patient/branch from route — brain form doesn't send these
        $data['patient_id']        = $patient->id;
        // Doctor attribution (Slice 2, 2026-08-03): the form sends a visible doctor
        // select; fall back to the logged-in user only if it's somehow absent.
        // update() intentionally has no fallback — an absent key must never
        // reattribute an existing record.
        $data['doctor_id']         = $data['doctor_id'] ?? auth()->id();
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

        // ── Embedded Prescription section (2026-07-31) ─────────────────────────
        // The panel posts prescriptions_data / instructions_data as JSON strings.
        // These used to be decoded straight into consultations.prescriptions /
        // .instructions (dead JSON columns nothing ever read back) — that write
        // path is retired. The real save now goes through PrescriptionQuickSaveService
        // once the consultation exists, creating a real Prescription+PrescriptionItem
        // row keyed by consultation_id (below), same tables the standalone
        // Prescription module uses. Strip these keys before mass-assignment —
        // they aren't fillable on Consultation anyway, but no reason to carry them.
        unset($data['prescriptions_data'], $data['instructions_data'], $data['prescriptions'], $data['instructions']);

        // Pull out specialty_modules — handled separately after consultation create
        $specialtyModules = $data['specialty_modules'] ?? [];
        unset($data['specialty_modules']);

        $consultation = Consultation::create($data);

        // ── Embedded Prescription section: create the linked Prescription ─────
        // Only writes a Prescription row if the doctor actually entered a drug —
        // an empty panel should be a no-op, not an empty prescription record.
        if ($this->quickSave->panelHasDrugRows($request)) {
            $this->quickSave->createFromPanel($request, $patient, [
                'consultation_id' => $consultation->id,
                'chief_complaint' => $consultation->chief_complaint,
                'diagnosis'       => $consultation->primary_diagnosis,
                'source'          => Prescription::SOURCE_CONSULTATION,
            ], self::RX_NOTE_FIELD);
        }

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
            ->with('success', 'Consultation saved.')
            ->with('visit_gate', $this->visitGateData($patient));
    }

    /**
     * UX-04 (Freeze Spec, 2026-08-05) — post-consultation-save Treatment Visit
     * gate. Fires only when today's appointment is a TREATMENT appointment,
     * no visit has been recorded today, and the doctor hasn't already answered
     * "No Treatment Done Today". Read-side only; the modal renders once from
     * this flash on consultations/show. Returns null when the gate shouldn't
     * show, which the view treats as "no modal".
     */
    private function visitGateData(Patient $patient): ?array
    {
        $appointment = $patient->appointments()
            ->whereDate('appointment_date', today())
            ->where('type', 'treatment')
            ->where('status', '!=', 'cancelled')
            ->orderBy('appointment_time')
            ->first();
        if (! $appointment) {
            return null;
        }

        $visitToday = $patient->treatmentVisits()
            ->whereDate('visit_date', today())->exists();
        if ($visitToday) {
            return null;
        }

        $answeredNone = \App\Models\Activity::where('subject_type', Patient::class)
            ->where('subject_id', $patient->id)
            ->where('event', 'treatment_visit.none_today')
            ->whereDate('occurred_at', today())->exists();
        if ($answeredNone) {
            return null;
        }

        // Prefill plan ONLY when exactly one accepted plan exists — never guess.
        $acceptedPlans = $patient->treatmentPlans()
            ->whereNotNull('accepted_at')->pluck('id');

        return [
            'patient_id'     => $patient->id,
            'patient_name'   => $patient->name,
            'appointment_id' => $appointment->id,
            'plan_id'        => $acceptedPlans->count() === 1 ? $acceptedPlans->first() : null,
        ];
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

        // 2026-07-31 UX experiment: the Rx specifically tied to THIS consultation
        // (same query pattern as edit()/print() below) — closes the gap where
        // show.blade.php only surfaced "Previous Prescriptions" generically and
        // never distinguished the one actually written during this visit.
        $linkedPrescription = \App\Models\Prescription\Prescription::where('consultation_id', $consultation->id)
            ->where('status', '!=', \App\Models\Prescription\Prescription::STATUS_CANCELLED)
            ->with('items')
            ->latest()
            ->first();

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
            'linkedPrescription',
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

        // ── Phase 0 QR fix: same "scan to view online" QR as the treatment
        // plan printout — only surfaces a link if one already exists (see
        // TreatmentPlanController::printView for the identical logic). ──
        foreach ($consultation->treatmentPlans as $plan) {
            if ($url = Presentation::activeLinkUrlForPlan($plan->id)) {
                $plan->presentation_url = $url;
                $plan->presentation_qr  = QrCodeGenerator::dataUri($url);
            }
        }

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
        // Slice 6 (2026-08-03): typed workflows own their records' edits. The
        // generic form has no inputs for the typed columns (update_notes,
        // procedure_performed, …), so editing a typed record here would
        // silently corrupt it. Redirect to the canonical typed edit screen —
        // covers direct URL entry, not just the show-page links.
        if ($consultation->consultation_type === 'minor_visit') {
            return redirect()->route('patients.consultations.minor-visit.edit', [$patient, $consultation]);
        }
        if ($consultation->consultation_type === 'same_issue') {
            return redirect()->route('patients.consultations.same-issue.edit', [$patient, $consultation]);
        }
        if ($consultation->consultation_type === 'emergency') {
            return redirect()->route('patients.consultations.emergency.edit', [$patient, $consultation]);
        }
        if ($consultation->consultation_type === 'coha') {
            return redirect()->route('coha.edit', [$patient, $consultation]);
        }

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

        // Prefill the embedded Prescription section from whatever's already
        // linked to this consultation (same source the standalone module and
        // the Case Paper print view use — see ConsultationController::print()).
        $linkedPrescription = Prescription::where('consultation_id', $consultation->id)
            ->where('status', '!=', Prescription::STATUS_CANCELLED)
            ->with('items')
            ->latest()
            ->first();

        return view('consultations.create', compact('consultation', 'patient', 'doctors', 'pastConsultations', 'previousConsultation', 'linkedPrescription'));
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

        // ── Embedded Prescription section: retired dead JSON-column write ─────
        // See store() for the full explanation — same retirement, same real
        // Prescription+PrescriptionItem save below instead.
        unset($data['prescriptions_data'], $data['instructions_data'], $data['prescriptions'], $data['instructions']);

        // Pull out specialty_modules
        $specialtyModules = $data['specialty_modules'] ?? [];
        unset($data['specialty_modules']);

        $consultation->update($data);

        // ── Embedded Prescription section: update the linked Prescription ─────
        // Edits the existing Rx tied to this consultation in place if one
        // exists; otherwise creates one — same "one Rx per consultation"
        // shape as create. An emptied-out panel is left untouched rather than
        // deleting a real prescription record (cancel that from the
        // standalone Prescriptions tab instead — matches its own no
        // hard-delete policy).
        if ($this->quickSave->panelHasDrugRows($request)) {
            $context = [
                'chief_complaint' => $consultation->chief_complaint,
                'diagnosis'       => $consultation->primary_diagnosis,
            ];

            $existingRx = Prescription::where('consultation_id', $consultation->id)
                ->where('status', '!=', Prescription::STATUS_CANCELLED)
                ->latest()
                ->first();

            if ($existingRx) {
                $this->quickSave->updateFromPanel($existingRx, $request, $context, self::RX_NOTE_FIELD);
            } else {
                $this->quickSave->createFromPanel($request, $patient, array_merge($context, [
                    'consultation_id' => $consultation->id,
                    'source'          => Prescription::SOURCE_CONSULTATION,
                ]), self::RX_NOTE_FIELD);
            }
        }

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
            ->with('success', 'Consultation updated.')
            ->with('visit_gate', $this->visitGateData($consultation->patient));
    }

    public function destroy(Patient $patient, Consultation $consultation)
    {
        $consultation->delete();

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Consultation deleted.');
    }

    // REMOVED (Slice 8, 2026-08-03): forPatient() + its route
    // (patients.consultations.index) + consultations/index.blade.php were a
    // dead standalone listing — zero inbound links anywhere (verified by
    // repo-wide grep); the patient profile's Consultation tab is the real
    // listing. The orphaned blade file is deleted in the same slice.

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
            // Slice 3 (2026-08-03): backdating allowed, future dates never.
            'consultation_date'       => 'nullable|date|before_or_equal:today',
            'previous_consultation_id'=> 'nullable|exists:consultations,id',
            'update_notes'            => 'required|string',
            'additional_findings'     => 'nullable|string',
            'primary_diagnosis'       => 'nullable|string',
            'diagnosis_notes'         => 'nullable|string',
            'finishing_notes'         => 'nullable|string',
            // LEGACY (retired 2026-07-31, kept for rollback — see ConsultationController LEGACY block below)
            // 'prescriptions_data'      => 'nullable|string',
            // 'instructions_data'       => 'nullable|string',
        ]);

        // ── LEGACY — retired 2026-07-31 ────────────────────────────────────────
        // This form never actually posts prescriptions_data/instructions_data
        // (resources/views/consultations/same-issue.blade.php has no prescription
        // UI at all — confirmed via repo-wide grep), and consultations.prescriptions/
        // .instructions were confirmed to have zero read paths anywhere in the app
        // (web or API) — so this block was doubly dead. Commented out rather than
        // deleted per "Retire > Archive > Verify > Delete" policy. Do NOT reactivate
        // without also adding a <x-prescription-panel> to same-issue.blade.php AND
        // re-adding the validation rules above — reactivating half of this (just the
        // decode) would silently write to a column nothing reads, same problem as before.
        // if (!empty($data['prescriptions_data'])) {
        //     $data['prescriptions'] = json_decode($data['prescriptions_data'], true);
        // }
        // unset($data['prescriptions_data']);
        // if (!empty($data['instructions_data'])) {
        //     $data['instructions'] = json_decode($data['instructions_data'], true);
        // }
        // unset($data['instructions_data']);
        // ─────────────────────────────────────────────────────────────────────

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

    /**
     * Slice 6 (2026-08-03): Same Issue edit workflow — mirrors the Minor Visit
     * pattern (see minorVisitEdit above). Same Issue records previously fell
     * through to the generic edit form, which has no update_notes /
     * additional_findings inputs, so saving from it silently corrupted them.
     */
    public function sameIssueEdit(Patient $patient, Consultation $consultation)
    {
        abort_if($consultation->consultation_type !== 'same_issue', 404);

        $doctors = User::orderBy('name')->get();
        $previousConsultation = $consultation->previous_consultation_id
            ? Consultation::with(['doctor', 'treatmentPlans'])->find($consultation->previous_consultation_id)
            : null;

        return view('consultations.same-issue', compact('patient', 'consultation', 'doctors', 'previousConsultation'));
    }

    public function sameIssueUpdate(Request $request, Patient $patient, Consultation $consultation)
    {
        abort_if($consultation->consultation_type !== 'same_issue', 404);

        $data = $request->validate([
            'doctor_id'               => 'required|exists:users,id',
            'consultation_date'       => 'nullable|date|before_or_equal:today',
            // LEGACY SHAPE: records created by the retired create.blade.php
            // same_issue chip stored their content in chief_complaint /
            // hopi_final instead of update_notes. The edit screen surfaces
            // those fields only when populated, so both shapes stay editable
            // in the ONE canonical workflow. update_notes stays required for
            // typed-shape records (i.e. whenever no legacy chief_complaint
            // is being posted).
            'update_notes'            => 'required_without:chief_complaint|nullable|string',
            'chief_complaint'         => 'nullable|string',
            'hopi_final'              => 'nullable|string',
            'additional_findings'     => 'nullable|string',
            'primary_diagnosis'       => 'nullable|string',
            'diagnosis_notes'         => 'nullable|string',
            'finishing_notes'         => 'nullable|string',
            // previous_consultation_id is deliberately NOT accepted on update —
            // which visit this one continues is a fact set at creation.
        ]);

        $consultation->update($data);

        return redirect()
            ->route('consultations.show', $consultation)
            ->with('success', 'Same Issue consultation updated.');
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
            // Slice 3 (2026-08-03): backdating allowed, future dates never.
            'consultation_date'           => 'nullable|date|before_or_equal:today',
            'related_to_clinic_treatment' => 'required|boolean',
            'procedure_performed'         => 'required|string',
            'chief_complaint'             => 'nullable|string',
            'hopi_final'                  => 'nullable|string',
            'primary_diagnosis'           => 'nullable|string',
            'clinical_data'               => 'nullable|array',
            // Slice 1 fix (2026-08-01): the view used to post a single name="advice"
            // textarea duplicated across both the clinic-related and external/walk-in
            // branches (x-show only hides with CSS, both stayed in the DOM and both
            // posted) — whichever was later in DOM order silently overwrote the other
            // on submit. Now two distinct fields, merged into `advice` below based on
            // which branch was actually active.
            'advice_clinic_related'       => 'nullable|string',
            'advice_external'             => 'nullable|string',
            'finishing_notes'             => 'nullable|string',
            // Visit redesign (2026-07-31) — Charges, Follow-up added to match the
            // target Minor Visit workflow (Reason/Procedure/Notes/Charges/Follow-up).
            // 'charges' is NOT a Consultation column (no migration added, per
            // "don't change database unnecessarily") — it only drives a BillingPrompt
            // below, same mechanism TreatmentVisitService already uses.
            'charges'                     => 'nullable|numeric|min:0',
            'follow_up_date'              => 'nullable|date',
            'follow_up_note'              => 'nullable|string',
            // LEGACY (retired 2026-07-31, kept for rollback — see LEGACY block below)
            // 'prescriptions_data'          => 'nullable|string',
            // 'instructions_data'           => 'nullable|string',
        ]);

        $charges = $data['charges'] ?? null;
        unset($data['charges']); // not a Consultation column — handled via BillingPrompt below

        // Slice 1 fix (2026-08-01): resolve the two branch-specific advice fields into
        // the single `advice` column, keyed by which branch the user actually filled in.
        $data['advice'] = $data['related_to_clinic_treatment']
            ? ($data['advice_clinic_related'] ?? null)
            : ($data['advice_external'] ?? null);
        unset($data['advice_clinic_related'], $data['advice_external']);

        // ── LEGACY — retired 2026-07-31 ────────────────────────────────────────
        // Same retirement/reasoning as sameIssueStore() above: minor-visit.blade.php
        // has no prescription UI, and the target columns have zero read paths
        // anywhere in the app. Commented out, not deleted — do not reactivate
        // without adding the panel to the view AND restoring the validation rules.
        // if (!empty($data['prescriptions_data'])) {
        //     $data['prescriptions'] = json_decode($data['prescriptions_data'], true);
        // }
        // unset($data['prescriptions_data']);
        // if (!empty($data['instructions_data'])) {
        //     $data['instructions'] = json_decode($data['instructions_data'], true);
        // }
        // unset($data['instructions_data']);
        // ─────────────────────────────────────────────────────────────────────

        $consultation = Consultation::create(array_merge($data, [
            'patient_id'        => $patient->id,
            'branch_id'         => auth()->user()->branch_id ?? 1,
            'consultation_type' => 'minor_visit',
            'visit_type'        => 'routine',
            'status'            => 'completed',
            'consultation_date' => $data['consultation_date'] ?? now(),
        ]));

        // ── Visit redesign (2026-07-31): Minor Visit Charges ───────────────────
        // Reuses the existing billing_prompts mechanism (same table/shape
        // TreatmentVisitService already writes to for trigger_type='treatment_visit')
        // instead of adding a new column — front desk turns this into an invoice
        // manually from the Billing Prompts queue. No invoice is auto-created.
        if ($charges !== null && $charges > 0) {
            \App\Models\BillingPrompt::create([
                'patient_id'   => $patient->id,
                'trigger_type' => 'consultation',
                'trigger_id'   => $consultation->id,
                'description'  => 'Minor Visit charge: ' . ($consultation->chief_complaint ?: $consultation->procedure_performed ?: 'Rs. ' . $charges),
                'status'       => 'pending',
                'created_by'   => auth()->id(),
            ]);
        }

        return redirect()
            ->route('consultations.show', $consultation)
            ->with('success', 'Minor Visit saved.');
    }

    /**
     * Slice 1 fix (2026-08-01): Minor Visit had no edit workflow of its own —
     * the "Edit Consultation" link on show.blade.php sent every consultation,
     * including Minor Visit, to the generic edit()/update() pair, which renders
     * create.blade.php (the standard consultation form). That form has no
     * inputs for related_to_clinic_treatment, procedure_performed, or the
     * advice_clinic_related/advice_external pair, so saving from it risked
     * silently corrupting a Minor Visit record's fields. This gives Minor
     * Visit its own edit()/update(), mirroring minorVisitCreate()/Store().
     */
    public function minorVisitEdit(Patient $patient, Consultation $consultation)
    {
        $doctors = User::orderBy('name')->get();
        $lastTreatmentPlan = $patient->treatmentPlans()->latest()->first();

        return view('consultations.minor-visit', compact('patient', 'consultation', 'doctors', 'lastTreatmentPlan'));
    }

    public function minorVisitUpdate(Request $request, Patient $patient, Consultation $consultation)
    {
        $data = $request->validate([
            'doctor_id'                   => 'required|exists:users,id',
            // Slice 3 (2026-08-03): backdating allowed, future dates never.
            'consultation_date'           => 'nullable|date|before_or_equal:today',
            'related_to_clinic_treatment' => 'required|boolean',
            'procedure_performed'         => 'required|string',
            'chief_complaint'             => 'nullable|string',
            'hopi_final'                  => 'nullable|string',
            'primary_diagnosis'           => 'nullable|string',
            'clinical_data'               => 'nullable|array',
            'advice_clinic_related'       => 'nullable|string',
            'advice_external'             => 'nullable|string',
            'finishing_notes'             => 'nullable|string',
            'follow_up_date'              => 'nullable|date',
            'follow_up_note'              => 'nullable|string',
            // Charges is deliberately NOT accepted on update — it only ever
            // drove a one-time BillingPrompt at creation (see minorVisitStore());
            // re-processing it here on every edit would queue a duplicate
            // billing prompt each time the record is saved.
        ]);

        $data['advice'] = $data['related_to_clinic_treatment']
            ? ($data['advice_clinic_related'] ?? null)
            : ($data['advice_external'] ?? null);
        unset($data['advice_clinic_related'], $data['advice_external']);

        $consultation->update($data);

        return redirect()
            ->route('consultations.show', $consultation)
            ->with('success', 'Minor Visit updated.');
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
            // Slice 3 (2026-08-03): backdating allowed, future dates never.
            // NOTE: emergency posts datetime-local (has a time component), so the
            // ceiling is now, not today — before_or_equal:today would reject a
            // legitimate entry made this afternoon (today 14:30 > midnight).
            'consultation_date'            => 'nullable|date|before_or_equal:now',
            'chief_complaint'              => 'required|string',
            'hopi_final'                   => 'nullable|string',
            'clinical_data'                => 'nullable|array',
            'primary_diagnosis'            => 'nullable|string',
            'emergency_treatment_rendered' => 'required|string',
            'advice'                       => 'nullable|string',
            'finishing_notes'              => 'nullable|string',
            // LEGACY (retired 2026-07-31, kept for rollback — see LEGACY block below)
            // 'prescriptions_data'           => 'nullable|string',
            // 'instructions_data'            => 'nullable|string',
        ]);

        // ── LEGACY — retired 2026-07-31 ────────────────────────────────────────
        // Same retirement/reasoning as sameIssueStore() above: emergency.blade.php
        // has no prescription UI, and the target columns have zero read paths
        // anywhere in the app. Commented out, not deleted — do not reactivate
        // without adding the panel to the view AND restoring the validation rules.
        // if (!empty($data['prescriptions_data'])) {
        //     $data['prescriptions'] = json_decode($data['prescriptions_data'], true);
        // }
        // unset($data['prescriptions_data']);
        // if (!empty($data['instructions_data'])) {
        //     $data['instructions'] = json_decode($data['instructions_data'], true);
        // }
        // unset($data['instructions_data']);
        // ─────────────────────────────────────────────────────────────────────

        $consultation = Consultation::create(array_merge($data, [
            'patient_id'        => $patient->id,
            'branch_id'         => auth()->user()->branch_id ?? 1,
            'consultation_type' => 'emergency',
            'visit_type'        => 'emergency',
            'status'            => 'completed',
            'consultation_date' => $data['consultation_date'] ?? now(),
        ]));

        // ── Slice 9 (2026-08-03): embedded Prescription for Emergency ─────────
        // Emergency visits are where an immediate Rx (antibiotics, analgesics)
        // is MOST common, yet Rx lived only on the standard form. Same engine,
        // same rules as store(): a real Prescription row via
        // PrescriptionQuickSaveService, only if a drug was actually entered.
        if ($this->quickSave->panelHasDrugRows($request)) {
            $this->quickSave->createFromPanel($request, $patient, [
                'consultation_id' => $consultation->id,
                'chief_complaint' => $consultation->chief_complaint,
                'diagnosis'       => $consultation->primary_diagnosis,
                'source'          => Prescription::SOURCE_CONSULTATION,
            ], self::RX_NOTE_FIELD);
        }

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

    /**
     * Slice 9 (2026-08-03): Emergency edit workflow — completes typed-workflow
     * parity. Emergency records previously fell through to the generic edit
     * form, which has no emergency_treatment_rendered input.
     */
    public function emergencyEdit(Patient $patient, Consultation $consultation)
    {
        abort_if($consultation->consultation_type !== 'emergency', 404);

        $doctors = User::orderBy('name')->get();
        $linkedPrescription = Prescription::where('consultation_id', $consultation->id)
            ->where('status', '!=', Prescription::STATUS_CANCELLED)
            ->with('items')
            ->latest()
            ->first();

        return view('consultations.emergency', compact('patient', 'consultation', 'doctors', 'linkedPrescription'));
    }

    public function emergencyUpdate(Request $request, Patient $patient, Consultation $consultation)
    {
        abort_if($consultation->consultation_type !== 'emergency', 404);

        $data = $request->validate([
            'doctor_id'                    => 'required|exists:users,id',
            // Emergency posts datetime-local — ceiling is now, not midnight.
            'consultation_date'            => 'nullable|date|before_or_equal:now',
            'chief_complaint'              => 'required|string',
            'hopi_final'                   => 'nullable|string',
            'clinical_data'                => 'nullable|array',
            'primary_diagnosis'            => 'nullable|string',
            'emergency_treatment_rendered' => 'required|string',
            'advice'                       => 'nullable|string',
            'finishing_notes'              => 'nullable|string',
        ]);

        $consultation->update($data);

        // ── Embedded Prescription: same update-in-place rules as update() ─────
        // Edits the existing Rx tied to this visit if one exists; an emptied
        // panel never deletes a real prescription record.
        if ($this->quickSave->panelHasDrugRows($request)) {
            $context = [
                'chief_complaint' => $consultation->chief_complaint,
                'diagnosis'       => $consultation->primary_diagnosis,
            ];

            $existingRx = Prescription::where('consultation_id', $consultation->id)
                ->where('status', '!=', Prescription::STATUS_CANCELLED)
                ->latest()
                ->first();

            if ($existingRx) {
                $this->quickSave->updateFromPanel($existingRx, $request, $context, self::RX_NOTE_FIELD);
            } else {
                $this->quickSave->createFromPanel($request, $patient, array_merge($context, [
                    'consultation_id' => $consultation->id,
                    'source'          => Prescription::SOURCE_CONSULTATION,
                ]), self::RX_NOTE_FIELD);
            }
        }

        return redirect()
            ->route('consultations.show', $consultation)
            ->with('success', 'Emergency Visit updated.');
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
        // Slice 3 (2026-08-03): COHA previously had ZERO validation — raw
        // $request->input() straight into Consultation::create. Now validated
        // to the same standard as the other typed workflows. Section payloads
        // may arrive as arrays or JSON strings (parseSection handles both).
        $request->validate(self::cohaRules());

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

        // Slice 3 (2026-08-03): same validation as cohaStore() — was previously absent.
        $request->validate(self::cohaRules());

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
    /**
     * Shared validation rules for the COHA store/update pair (Slice 3, 2026-08-03).
     * The nine section payloads are posted either as arrays (normal form submit)
     * or JSON strings (Alpine hidden inputs) — parseSection() normalises both,
     * so validation accepts either shape but nothing else.
     */
    private static function cohaRules(): array
    {
        $section = ['nullable', function ($attribute, $value, $fail) {
            if (!is_array($value) && !is_string($value)) {
                $fail("The {$attribute} section must be an array or JSON string.");
            }
            if (is_string($value) && $value !== '' && json_decode($value, true) === null) {
                $fail("The {$attribute} section is not valid JSON.");
            }
        }];

        return [
            'doctor_id'           => ['required', 'exists:users,id'],
            // Backdating allowed (missed entries), future dates never.
            'consultation_date'   => ['nullable', 'date', 'before_or_equal:today'],
            'doctor_notes'        => ['nullable', 'string'],
            'monitoring_teeth'    => ['nullable', 'array'],
            'extraoral'           => $section,
            'soft_tissue'         => $section,
            'tooth_assessment'    => $section,
            'ortho_findings'      => $section,
            'perio_findings'      => $section,
            'esthetic_findings'   => $section,
            'risk_assessment'     => $section,
            'treatment_awareness' => $section,
        ];
    }

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
