<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksStaleUpdates;
use App\Http\Requests\Patient\StorePatientRequest;
use App\Http\Requests\Patient\UpdatePatientRequest;
use App\Models\Patient;
use App\Services\PatientProfileService;
use App\Services\PatientService;
use App\Services\Assistant\PatientScanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PatientController extends Controller
{
    use ChecksStaleUpdates;

    public function __construct(
        private PatientProfileService $profileService,
        private PatientService $patients,
    ) {}

    // ── List ─────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $branchId = Auth::user()->branch_id;

        // All the search / filter / sort logic now lives in PatientService so the
        // web list and the /api/v1/patients endpoint behave identically.
        $patients = $this->patients
            ->filteredQuery($branchId, $request->all())
            ->with('tags')
            ->paginate(30)
            ->withQueryString();

        // Distinct areas for the filter dropdown
        $areas = $this->patients->distinctAreas($branchId);

        return view('patients.index', compact('patients', 'areas'));
    }

    // ── Create / Store ────────────────────────────────────────────────────────

    public function create()
    {
        // The real "Add Patient" form is the self-contained modal included on
        // the patients list (partials/add-patient-modal). We send users there
        // with ?new=1, which auto-opens that modal. (The old patients.create
        // blade was actually a consultation form and is no longer used here.)
        return redirect()->route('patients.index', ['new' => 1]);
    }

    /**
     * Scan Form — read a photographed patient registration/intake form with the
     * local vision model and return pre-fill values for the Add Patient modal.
     * EXTRACTION ONLY: never writes to the database. Staff reviews the filled
     * tabs and taps Register.
     *
     * POST /patients/scan-form  (expects an "image" file upload)
     * Responds JSON: { ok: true, data: {...} } or { ok: false, message: "..." }
     */
    public function scanForm(Request $request, PatientScanService $scanner)
    {
        // Vision can be switched off entirely from config (shared kill-switch).
        if (!config('assistant.vision.enabled', true)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Form scanning is turned off. You can fill the patient in manually.',
            ], 422);
        }

        $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png,webp,heic|max:12288', // ~12MB
        ]);

        try {
            $data = $scanner->scan($request->file('image')->getRealPath());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            // Service throws friendly, human-readable messages — pass them through.
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function store(StorePatientRequest $request)
    {
        // Validation is the shared StorePatientRequest (web + API). It normalises
        // legacy aliases (mobile→phone, dob→date_of_birth, notes→chief_complaint)
        // so the whole app validates one canonical vocabulary.

        // ── Duplicate-phone guard ────────────────────────────────────────
        // Only quickCreate() checked for duplicates before, so the main
        // registration form silently created a second record for returning
        // patients — splitting their visit history, billing and recalls.
        // This is a soft warning, not a block: staff can confirm and proceed.
        if (! $request->boolean('confirm_duplicate') && ! $request->filled('link_to_patient_id')) {
            $dupes = $this->patients->findDuplicatesByPhone(
                $request->input('phone'),
                (int) Auth::user()->branch_id
            );

            if ($dupes->isNotEmpty()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success'    => false,
                        'duplicate'  => true,
                        'message'    => 'A patient with this mobile number already exists.',
                        'duplicates' => $dupes->map(fn ($p) => [
                            'id'    => $p->id,
                            'name'  => $p->name,
                            'phone' => $p->phone,
                            'url'   => route('patients.show', $p),
                        ])->values(),
                    ], 409);
                }

                return back()
                    ->withInput()
                    ->with('duplicate_patients', $dupes)
                    ->with('warning', 'A patient with this mobile number already exists. Open the existing record, or confirm to register a new patient (e.g. a family member sharing the number).');
            }
        }

        // The form sends `mobile`/`dob`/`notes`; the service maps those and
        // handles display-name assembly + tag syncing in one place.
        $patient = $this->patients->register($request->validated(), Auth::user());

        // Duplicate-screen "Register + link family" (Phase 3, Slice 3): the new
        // patient shares a number with an existing one → link them through the
        // canonical FamilyLinkService. A link failure never fails registration.
        if ($request->filled('link_to_patient_id')) {
            $existing = Patient::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
                ->find($request->integer('link_to_patient_id'));
            if ($existing && $existing->id !== $patient->id) {
                $type = $request->input('link_relationship_type', 'other');
                if (! in_array($type, \App\Services\Patient\FamilyLinkService::RELATIONSHIP_TYPES, true)) {
                    $type = 'other';
                }
                try {
                    app(\App\Services\Patient\FamilyLinkService::class)->addLink(
                        $patient,
                        $existing,
                        $type,
                        ['as_guardian' => $request->boolean('link_as_guardian')],
                        Auth::user()
                    );
                } catch (\InvalidArgumentException $e) {
                    // Patient is registered; a link error must not roll that back.
                }
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success'    => true,
                'patient'    => $patient->fresh(['tags']),
                'patient_url'=> route('patients.show', $patient),
            ]);
        }

        return redirect()->route('patients.show', $patient)
            ->with('success', 'Patient registered successfully.');
    }

    // ── Profile (show) ────────────────────────────────────────────────────────

    public function show(Patient $patient)
    {
        // Merged (archived) record → redirect its old URL to the surviving master.
        if ($patient->merged_into_id) {
            return redirect()->route('patients.show', $patient->merged_into_id)
                ->with('merged_notice', "Record {$patient->patient_id} ({$patient->name}) was merged into this patient.");
        }

        // Soft-deleted for any other reason (deleted record) stays a 404.
        if (method_exists($patient, 'trashed') && $patient->trashed()) {
            abort(404);
        }

        // Access trail (Phase A) — who opened which patient record, when.
        \App\Models\AuditLog::event('viewed', auth()->id(), [], [
            'module'         => 'patients',
            'auditable_type' => Patient::class,
            'auditable_id'   => $patient->id,
        ]);

        $data = $this->profileService->loadProfile($patient);

        // Family & Contacts (Phase 3, Slice 3) — read-only data for the profile section.
        $family          = app(\App\Services\Patient\FamilyLinkService::class);
        $activeMembership = $data['activeMembership'] ?? null;
        $data['familyLinks']          = $family->linksFor($patient);
        $data['familyGuardians']      = $family->guardiansFor($patient);
        $data['isMinor']              = $patient->isMinor();
        $data['householdCount']       = $patient->relationship_id
            ? Patient::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
                ->where('relationship_id', $patient->relationship_id)
                ->where('id', '!=', $patient->id)
                ->count()
            : 0;
        $data['membershipFamilyName'] = $activeMembership
            ? ($activeMembership->family_name ?: optional($activeMembership->familyHead)->family_name)
            : null;
        $data['canEditFamily']        = (bool) optional(auth()->user())->canAccess('patients', 'edit');

        return view('patients.show', $data);
    }

    // ── Edit / Update ─────────────────────────────────────────────────────────

    public function edit(Patient $patient)
    {
        return view('patients.edit', compact('patient'));
    }

    public function update(UpdatePatientRequest $request, Patient $patient)
    {
        // Validation is the shared UpdatePatientRequest (web + API); legacy
        // aliases (mobile/dob/notes) are normalised there. Partial update.

        // Optimistic lock — refuse the save if someone else edited this patient
        // since the form was loaded, instead of silently overwriting them.
        // No-op for clients that don't send updated_at (backward compatible).
        $this->assertNotStale($request, $patient);

        // Service rebuilds the display name and writes only the provided fields.
        $patient = $this->patients->updateFromInput($patient, $request->validated());

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'patient' => $patient]);
        }

        return back()->with('success', 'Patient updated.');
    }

    public function destroy(Patient $patient, \Illuminate\Http\Request $request)
    {
        // Backstop for the route-level `module:patients,delete` gate.
        abort_unless(Auth::user()?->canAccess('patients', 'delete'), 403);

        // Require password confirmation and a reason
        $request->validate([
            'reason'   => ['required', 'string', 'min:5', 'max:500'],
            'password' => ['required', 'string'],
        ]);

        if (!\Illuminate\Support\Facades\Hash::check($request->password, auth()->user()->password)) {
            return back()->withErrors(['password' => 'Incorrect password. Deletion cancelled.'])->withInput();
        }

        $this->patients->softDelete($patient, $request->reason); // soft delete

        return redirect()->route('patients.index')->with('success', 'Patient record deleted: '.$patient->name);
    }

    public function deactivate(Patient $patient, \Illuminate\Http\Request $request)
    {
        $request->validate([
            'reason'   => ['required', 'string', 'min:5', 'max:500'],
            'password' => ['required', 'string'],
        ]);

        if (!\Illuminate\Support\Facades\Hash::check($request->password, auth()->user()->password)) {
            return back()->withErrors(['password' => 'Incorrect password. Action cancelled.'])->withInput();
        }

        $this->patients->deactivate($patient, $request->reason, auth()->id());

        return back()->with('success', 'Patient deactivated. Reason saved.');
    }

    public function reactivate(Patient $patient)
    {
        $this->patients->reactivate($patient);

        return back()->with('success', 'Patient reactivated successfully.');
    }

    // ── Print patient profile ─────────────────────────────────────────────────
    public function print(Patient $patient)
    {
        $patient->load(['consultations' => fn($q) => $q->latest()->limit(10)->with('doctor')]);
        $print  = \App\Models\AppSetting::group('print');
        $clinic = \App\Models\AppSetting::group('clinic');
        return view('patients.print', compact('patient', 'print', 'clinic'));
    }

    // ── Relationship Notes ────────────────────────────────────────────────────

    public function storeRelationshipNote(Request $request, Patient $patient)
    {
        $request->validate([
            'note' => ['required', 'string', 'max:1000'],
            'type' => ['nullable', 'string', 'in:internal,call,whatsapp,email,sms'],
            'tags' => ['nullable', 'array'],
        ]);

        $note = $this->profileService->addRelationshipNote($patient, $request->all());
        $note->load('author');

        return response()->json(['success' => true, 'note' => $note]);
    }

    public function destroyRelationshipNote(Patient $patient, int $noteId)
    {
        $patient->relationshipNotes()->findOrFail($noteId)->delete();
        return response()->json(['success' => true]);
    }

    // ── Treatment Opportunities ───────────────────────────────────────────────

    public function storeOpportunity(Request $request, Patient $patient)
    {
        $request->validate([
            'type'            => ['required', 'string', 'max:100'],
            'status'          => ['nullable', 'string'],
            'priority'        => ['nullable', 'in:low,medium,high'],
            'follow_up_date'  => ['nullable', 'date'],
            'estimated_value' => ['nullable', 'numeric'],
            'notes'           => ['nullable', 'string'],
        ]);

        $opp = $this->profileService->saveOpportunity($patient, $request->all());
        return response()->json(['success' => true, 'opportunity' => $opp]);
    }

    public function updateOpportunity(Request $request, Patient $patient, int $oppId)
    {
        $opp = $this->profileService->saveOpportunity($patient, $request->all(), $oppId);
        return response()->json(['success' => true, 'opportunity' => $opp]);
    }

    public function destroyOpportunity(Patient $patient, int $oppId)
    {
        $patient->opportunities()->findOrFail($oppId)->delete();
        return response()->json(['success' => true]);
    }

    // ── Search (JSON) ─────────────────────────────────────────────────────────

    public function search(Request $request)
    {
        $q = trim($request->get('q', ''));
        if (strlen($q) < 2) return response()->json([]);

        $patients = $this->patients->suggest($q, Auth::user()->branch_id);

        // Shape each result for the search dropdown (_search.blade.php).
        // The dropdown links each row via result.url and shows result.initials + result.meta,
        // so we MUST return those keys — otherwise clicking a result goes nowhere.
        return response()->json(
            $patients->map(function ($p) {
                // Initials from the name, e.g. "John Doe" -> "JD"
                $initials = collect(explode(' ', trim($p->name)))
                    ->filter()
                    ->take(2)
                    ->map(fn($word) => mb_strtoupper(mb_substr($word, 0, 1)))
                    ->implode('');

                // Sub-line: patient ID and phone (whichever exist)
                $meta = collect([$p->patient_id, $p->phone])->filter()->implode(' • ');

                return [
                    'id'         => $p->id,
                    'name'       => $p->name,
                    'url'        => route('patients.show', $p->id), // link that opens the profile
                    'initials'   => $initials ?: '?',
                    'meta'       => $meta,
                    // patient_id/phone are also returned as their own keys (not just
                    // folded into `meta`) because the referral picker on the patient
                    // edit form (edit-patient-drawer.blade.php) reads these directly
                    // for the selected-patient chip.
                    'patient_id' => $p->patient_id,
                    'phone'      => $p->phone,
                ];
            })
        );
    }

    /**
     * Quick-create a patient from the appointment modal (minimal fields).
     * Returns 409 JSON { duplicate, patient } if phone already exists.
     * Returns 200 JSON { ok, patient } on success.
     */
    public function quickStore(Request $request)
    {
        // ⚠ TECH DEBT (Appointment→Arrived→Registration redesign):
        // Booking still mints a full Patient + TDC number, so no-shows consume a TDC.
        // This affects ALL appointment-booking paths, not just this one:
        //   • PatientController::quickStore()   (this method)
        //   • AppointmentController::store()     (Patient::create, ~line 160)
        //   • AppointmentService  (create/book)  (Patient::create, ~line 364)
        // Per the locked lifecycle a booking must create only an appointment lead
        // (name + phone); the Patient + TDC are minted at Registration/Arrival.
        // When the Appointments module is built, REMOVE patient-minting from ALL
        // THREE paths — booking must not create a Patient. Deferred intentionally
        // (Sumit, 2026-07-20); no interim logic added to the Patients module.
        $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'phone'      => ['required', 'string', 'max:20'],
        ]);

        // Dedup then mint through the ONE canonical entry point (register()).
        $existing = $this->patients->findDuplicatesByPhone(
            $request->input('phone'),
            (int) Auth::user()->branch_id
        )->first();

        // Phone already belongs to someone in this branch -> 409.
        if ($existing) {
            return response()->json([
                'duplicate' => true,
                'patient'   => [
                    'id'    => $existing->id,
                    'name'  => $existing->name,
                    'phone' => $existing->phone,
                ],
            ], 409);
        }

        $patient = $this->patients->register($request->only(['first_name', 'last_name', 'phone']), Auth::user());

        return response()->json([
            'ok'      => true,
            'patient' => [
                'id'    => $patient->id,
                'name'  => $patient->name,
                'phone' => $patient->phone,
            ],
        ]);
    }
}