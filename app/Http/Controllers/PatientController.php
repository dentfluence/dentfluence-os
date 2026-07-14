<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksStaleUpdates;
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

    public function store(Request $request)
    {
        $request->validate([
            // Tab 1 — Basic Info
            'title'       => ['nullable', 'string', 'max:10'],
            'first_name'  => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name'   => ['required', 'string', 'max:100'],
            'gender'      => ['nullable', 'in:male,female,other,prefer_not_to_say'],
            'dob'         => ['nullable', 'date', 'required_if:dob_unknown,0'],
            'dob_unknown' => ['nullable', 'boolean'],
            'age_years'   => ['nullable', 'integer', 'min:0', 'max:150'],
            'tags'        => ['nullable', 'array'],
            // Tab 2 — Contact
            'mobile'                         => ['required', 'string', 'max:20'],
            'alternate_phone'                => ['nullable', 'string', 'max:20'],
            'email'                          => ['nullable', 'email', 'max:255'],
            'emergency_contact_name'         => ['nullable', 'string', 'max:100'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:50'],
            'emergency_contact_number'       => ['nullable', 'string', 'max:20'],
            'address'     => ['nullable', 'string', 'max:500'],
            'area'        => ['nullable', 'string', 'max:150'],
            'city'        => ['nullable', 'string', 'max:100'],
            'pincode'     => ['nullable', 'string', 'max:10'],
            'occupation'  => ['nullable', 'string', 'max:150'],
            // Tab 3 — Medical & Dental
            'medical_conditions'  => ['nullable', 'array'],
            'current_medications' => ['nullable', 'string'],
            'dental_conditions'   => ['nullable', 'array'],
            'medical_alert'       => ['nullable', 'string'],
            'allergies'           => ['nullable', 'array'],
            // Tab 4 — Habits
            'habits'         => ['nullable', 'array'],
            'habit_frequency'=> ['nullable', 'array'],
            // Tab 5 — Source & Notes
            'source'               => ['nullable', 'string', 'max:100'],
            'source_referral_name' => ['nullable', 'string', 'max:150'],
            'source_camp_name'     => ['nullable', 'string', 'max:150'],
            'source_campaign'      => ['nullable', 'string', 'max:150'],
            // Structured referral
            'referral_type'        => ['nullable', 'in:existing_patient,other'],
            'referred_patient_id'  => ['nullable', 'integer', 'exists:patients,id'],
            'referrer_name'        => ['nullable', 'string', 'max:150'],
            'referrer_mobile'      => ['nullable', 'string', 'max:20'],
            'referrer_type'        => ['nullable', 'in:Doctor,Friend,Family,Staff,Corporate,Other'],
            'referrer_notes'       => ['nullable', 'string', 'max:500'],
            'family_notes'         => ['nullable', 'string', 'max:500'],
            'notes'                => ['nullable', 'string'],
            // Set by the "Register anyway" confirmation when a possible
            // duplicate was surfaced (families do share one mobile number).
            'confirm_duplicate'    => ['nullable', 'boolean'],
        ]);

        // ── Duplicate-phone guard ────────────────────────────────────────
        // Only quickCreate() checked for duplicates before, so the main
        // registration form silently created a second record for returning
        // patients — splitting their visit history, billing and recalls.
        // This is a soft warning, not a block: staff can confirm and proceed.
        if (! $request->boolean('confirm_duplicate')) {
            $dupes = $this->patients->findDuplicatesByPhone(
                $request->input('mobile'),
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
        $patient = $this->patients->createFromInput($request->all(), Auth::user());

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
        // Access trail (Phase A) — who opened which patient record, when.
        \App\Models\AuditLog::event('viewed', auth()->id(), [], [
            'module'         => 'patients',
            'auditable_type' => Patient::class,
            'auditable_id'   => $patient->id,
        ]);

        $data = $this->profileService->loadProfile($patient);
        return view('patients.show', $data);
    }

    // ── Edit / Update ─────────────────────────────────────────────────────────

    public function edit(Patient $patient)
    {
        return view('patients.edit', compact('patient'));
    }

    public function update(Request $request, Patient $patient)
    {
        $request->validate([
            'title'       => ['nullable', 'string', 'max:10'],
            'first_name'  => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name'   => ['nullable', 'string', 'max:100'],
            'name'        => ['nullable', 'string', 'max:200'],
            'patient_id'  => ['nullable', 'string', 'max:30',
                              \Illuminate\Validation\Rule::unique('patients', 'patient_id')->ignore($patient->id)],
            'phone'       => ['required', 'string', 'max:20'],
            'alternate_phone' => ['nullable', 'string', 'max:20'],
            'email'       => ['nullable', 'email'],
            'dob'         => ['nullable', 'date'],
            'dob_unknown' => ['nullable', 'boolean'],
            'age_years'   => ['nullable', 'integer', 'min:0', 'max:150'],
            'gender'      => ['nullable', 'in:male,female,other,prefer_not_to_say'],
            'occupation'  => ['nullable', 'string', 'max:150'],
            'address'     => ['nullable', 'string'],
            'area'        => ['nullable', 'string', 'max:150'],
            'city'        => ['nullable', 'string', 'max:100'],
            'state'       => ['nullable', 'string', 'max:100'],
            'pincode'     => ['nullable', 'string', 'max:10'],
            'emergency_contact_name'         => ['nullable', 'string', 'max:100'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:50'],
            'emergency_contact_number'       => ['nullable', 'string', 'max:20'],
            'medical_alert'      => ['nullable', 'string'],
            'medical_conditions' => ['nullable', 'array'],
            'current_medications'=> ['nullable', 'string'],
            'dental_conditions'  => ['nullable', 'array'],
            'habits'             => ['nullable', 'array'],
            'habit_frequency'    => ['nullable', 'array'],
            'allergies'          => ['nullable', 'array'],
            'family_notes'       => ['nullable', 'string', 'max:500'],
            'source'             => ['nullable', 'string', 'max:100'],
            'referred_by'        => ['nullable', 'string'],
            'source_referral_name' => ['nullable', 'string', 'max:150'],
            'source_camp_name'     => ['nullable', 'string', 'max:150'],
            'source_campaign'      => ['nullable', 'string', 'max:150'],
            // Structured referral
            'referral_type'        => ['nullable', 'in:existing_patient,other'],
            'referred_patient_id'  => ['nullable', 'integer', 'exists:patients,id'],
            'referrer_name'        => ['nullable', 'string', 'max:150'],
            'referrer_mobile'      => ['nullable', 'string', 'max:20'],
            'referrer_type'        => ['nullable', 'in:Doctor,Friend,Family,Staff,Corporate,Other'],
            'referrer_notes'       => ['nullable', 'string', 'max:500'],
            'membership_status'    => ['nullable', 'in:not_enrolled,active,expired'],
            'membership_expires_at'=> ['nullable', 'date'],
            'follow_up_status'     => ['nullable', 'in:none,due,pending,completed'],
            'follow_up_date'       => ['nullable', 'date'],
            'tags'                 => ['nullable', 'array'],
        ]);

        // Optimistic lock — refuse the save if someone else edited this patient
        // since the form was loaded, instead of silently overwriting them.
        // No-op for clients that don't send updated_at (backward compatible).
        $this->assertNotStale($request, $patient);

        // Service rebuilds the display name and writes only the provided fields.
        $patient = $this->patients->updateFromInput($patient, $request->all());

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'patient' => $patient]);
        }

        return back()->with('success', 'Patient updated.');
    }

    public function destroy(Patient $patient, \Illuminate\Http\Request $request)
    {
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
        $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'phone'      => ['required', 'string', 'max:20'],
        ]);

        $result = $this->patients->quickCreate($request->all(), Auth::user());

        // Phone already belongs to someone in this branch -> 409.
        if (isset($result['duplicate'])) {
            $existing = $result['duplicate'];
            return response()->json([
                'duplicate' => true,
                'patient'   => [
                    'id'    => $existing->id,
                    'name'  => $existing->name,
                    'phone' => $existing->phone,
                ],
            ], 409);
        }

        $patient = $result['patient'];

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