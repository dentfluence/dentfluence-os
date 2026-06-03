<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Services\PatientProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PatientController extends Controller
{
    public function __construct(private PatientProfileService $profileService) {}

    // ── List ─────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = Patient::where('branch_id', Auth::user()->branch_id);

        // ── Search ──────────────────────────────────────────────────────────
        if ($q = $request->get('q')) {
            $query->where(fn($qb) =>
                $qb->where('name',       'like', "%{$q}%")
                   ->orWhere('phone',    'like', "%{$q}%")
                   ->orWhere('patient_id','like', "%{$q}%")
                   ->orWhere('email',    'like', "%{$q}%")
            );
        }

        // ── Filters ──────────────────────────────────────────────────────────
        if ($gender = $request->get('gender')) {
            $query->where('gender', $gender);
        }
        if ($area = $request->get('area')) {
            $query->where('area', 'like', "%{$area}%");
        }
        if ($ageMin = $request->get('age_min')) {
            // approximate via DOB; also covers age_years for unknown-DOB patients
            $query->where(fn($q) =>
                $q->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ?', [$ageMin])
                  ->orWhere(fn($q2) => $q2->where('dob_unknown', true)->where('age_years', '>=', $ageMin))
            );
        }
        if ($ageMax = $request->get('age_max')) {
            $query->where(fn($q) =>
                $q->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) <= ?', [$ageMax])
                  ->orWhere(fn($q2) => $q2->where('dob_unknown', true)->where('age_years', '<=', $ageMax))
            );
        }
        if ($membership = $request->get('membership')) {
            if ($membership === 'active') {
                $query->where('membership_status', 'active')
                      ->where(fn($q) => $q->whereNull('membership_expires_at')
                          ->orWhereDate('membership_expires_at', '>=', now()));
            } elseif ($membership === 'expired') {
                $query->where(fn($q) =>
                    $q->where('membership_status', 'expired')
                      ->orWhere(fn($q2) =>
                          $q2->where('membership_status', 'active')
                             ->whereDate('membership_expires_at', '<', now())
                      )
                );
            } elseif ($membership === 'not_enrolled') {
                $query->where('membership_status', 'not_enrolled');
            }
        }
        if ($followUp = $request->get('follow_up')) {
            $query->where('follow_up_status', $followUp);
        }
        if ($source = $request->get('source')) {
            $query->where('source', $source);
        }
        if ($birthdayMonth = $request->get('birthday_month')) {
            $query->whereMonth('date_of_birth', $birthdayMonth);
        }

        // ── Sort ────────────────────────────────────────────────────────────
        $sort = $request->get('sort', 'newest');
        match ($sort) {
            'name'       => $query->orderBy('name'),
            'patient_id' => $query->orderBy('patient_id'),
            'last_visit' => $query->orderBy('last_visit_date', 'desc')->orderBy('created_at', 'desc'),
            default      => $query->orderBy('created_at', 'desc'), // newest first
        };

        $patients = $query->with('tags')->paginate(30)->withQueryString();
        return view('patients.index', compact('patients'));
    }

    // ── Create / Store ────────────────────────────────────────────────────────

    public function create()
    {
        return view('patients.create');
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
            // Tab 3 — Medical & Dental
            'medical_conditions'  => ['nullable', 'array'],
            'current_medications' => ['nullable', 'string'],
            'dental_conditions'   => ['nullable', 'array'],
            // Tab 4 — Habits
            'habits'         => ['nullable', 'array'],
            'habit_frequency'=> ['nullable', 'array'],
            // Tab 5 — Source & Notes
            'source'               => ['nullable', 'string', 'max:100'],
            'source_referral_name' => ['nullable', 'string', 'max:150'],
            'source_camp_name'     => ['nullable', 'string', 'max:150'],
            'source_campaign'      => ['nullable', 'string', 'max:150'],
            'notes'                => ['nullable', 'string'],
        ]);

        // Build display name
        $nameParts = array_filter([
            $request->title,
            $request->first_name,
            $request->middle_name,
            $request->last_name,
        ]);
        $displayName = implode(' ', $nameParts);

        $patient = Patient::create([
            'title'       => $request->title,
            'first_name'  => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name'   => $request->last_name,
            'name'        => $displayName,
            'gender'      => $request->gender,
            'date_of_birth'  => $request->dob_unknown ? null : $request->dob,
            'dob_unknown' => (bool) $request->dob_unknown,
            'age_years'   => $request->age_years,
            'phone'       => $request->mobile,
            'alternate_phone'                => $request->alternate_phone,
            'email'                          => $request->email,
            'emergency_contact_name'         => $request->emergency_contact_name,
            'emergency_contact_relationship' => $request->emergency_contact_relationship,
            'emergency_contact_number'       => $request->emergency_contact_number,
            'address'     => $request->address,
            'area'        => $request->area,
            'city'        => $request->city,
            'pincode'     => $request->pincode,
            'medical_conditions'  => $request->medical_conditions ?? [],
            'current_medications' => $request->current_medications,
            'dental_conditions'   => $request->dental_conditions ?? [],
            'habits'              => $request->habits ?? [],
            'habit_frequency'     => $request->habit_frequency ?? [],
            'source'               => $request->source,
            'source_referral_name' => $request->source_referral_name,
            'source_camp_name'     => $request->source_camp_name,
            'source_campaign'      => $request->source_campaign,
            'chief_complaint'      => $request->notes,
            'branch_id'  => Auth::user()->branch_id,
            'created_by' => Auth::id(),
        ]);

        // Attach tags if provided
        if (!empty($request->tags)) {
            $patient->tags()->sync($request->tags);
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
            'membership_status'    => ['nullable', 'in:not_enrolled,active,expired'],
            'membership_expires_at'=> ['nullable', 'date'],
            'follow_up_status'     => ['nullable', 'in:none,due,pending,completed'],
            'follow_up_date'       => ['nullable', 'date'],
        ]);

        // Rebuild display name if name parts provided
        if ($request->filled('first_name') || $request->filled('last_name')) {
            $nameParts = array_filter([
                $request->title ?? $patient->title,
                $request->first_name ?? $patient->first_name,
                $request->middle_name ?? $patient->middle_name,
                $request->last_name ?? $patient->last_name,
            ]);
            $displayName = implode(' ', $nameParts);
        } else {
            $displayName = $request->name ?? $patient->name;
        }

        $patient->update(array_filter([
            'title'       => $request->title,
            'first_name'  => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name'   => $request->last_name,
            'name'        => $displayName,
            'phone'       => $request->phone,
            'alternate_phone' => $request->alternate_phone,
            'email'       => $request->email,
            'date_of_birth'  => $request->dob_unknown ? null : $request->dob,
            'dob_unknown' => $request->has('dob_unknown') ? (bool)$request->dob_unknown : null,
            'age_years'   => $request->age_years,
            'gender'      => $request->gender,
            'occupation'  => $request->occupation,
            'address'     => $request->address,
            'area'        => $request->area,
            'city'        => $request->city,
            'state'       => $request->state,
            'pincode'     => $request->pincode,
            'emergency_contact_name'         => $request->emergency_contact_name,
            'emergency_contact_relationship' => $request->emergency_contact_relationship,
            'emergency_contact_number'       => $request->emergency_contact_number,
            'medical_alert'      => $request->medical_alert,
            'medical_conditions' => $request->medical_conditions,
            'current_medications'=> $request->current_medications,
            'dental_conditions'  => $request->dental_conditions,
            'habits'             => $request->habits,
            'habit_frequency'    => $request->habit_frequency,
            'allergies'          => $request->allergies,
            'family_notes'       => $request->family_notes,
            'source'             => $request->source,
            'referred_by'        => $request->referred_by,
            'source_referral_name' => $request->source_referral_name,
            'source_camp_name'     => $request->source_camp_name,
            'source_campaign'      => $request->source_campaign,
            'membership_status'    => $request->membership_status,
            'membership_expires_at'=> $request->membership_expires_at,
            'follow_up_status'     => $request->follow_up_status,
            'follow_up_date'       => $request->follow_up_date,
        ], fn($v) => $v !== null));

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'patient' => $patient->fresh()]);
        }

        return back()->with('success', 'Patient updated.');
    }

    public function destroy(Patient $patient)
    {
        $patient->delete();
        return redirect()->route('patients.index')->with('success', 'Patient removed.');
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

        return response()->json(
            Patient::where('branch_id', Auth::user()->branch_id)
                ->where(fn($qb) =>
                    $qb->where('name',  'like', "%{$q}%")
                       ->orWhere('phone', 'like', "%{$q}%"))
                ->select('id', 'name', 'phone')
                ->orderBy('name')
                ->limit(10)
                ->get()
        );
    }
}