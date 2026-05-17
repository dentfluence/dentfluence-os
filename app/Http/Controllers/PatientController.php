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
        $query = Patient::where('branch_id', Auth::user()->branch_id)->orderBy('name');

        if ($q = $request->get('q')) {
            $query->where(fn($qb) =>
                $qb->where('name',  'like', "%{$q}%")
                   ->orWhere('phone', 'like', "%{$q}%")
                   ->orWhere('email', 'like', "%{$q}%")
            );
        }

        $patients = $query->paginate(30)->withQueryString();
        return view('patients.index', compact('patients'));
    }

    // ── Create / Store ────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'mobile'     => ['required', 'string', 'max:20'],
            'email'      => ['nullable', 'email', 'max:255'],
            'dob'        => ['nullable', 'date'],
            'gender'     => ['nullable', 'in:male,female,other,prefer_not_to_say'],
            'address'    => ['nullable', 'string', 'max:500'],
            'notes'      => ['nullable', 'string'],
        ]);

        $patient = Patient::create([
            'name'      => trim($request->first_name . ' ' . $request->last_name),
            'phone'     => $request->mobile,
            'email'     => $request->email,
            'date_of_birth' => $request->dob,
            'gender'    => $request->gender,
            'address'   => $request->address,
            'branch_id' => Auth::user()->branch_id,
            'created_by'=> Auth::id(),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'patient' => $patient]);
        }

        return redirect()->route('patients.show', $patient)
            ->with('success', 'Patient added successfully.');
    }

    // ── Profile (show) ────────────────────────────────────────────────────────

    public function show(Patient $patient)
    {
        $data = $this->profileService->loadProfile($patient);
        return view('patients.show', $data);
    }

    // ── Edit / Update ─────────────────────────────────────────────────────────

    public function update(Request $request, Patient $patient)
    {
        $request->validate([
            'name'       => ['required', 'string', 'max:200'],
            'phone'      => ['required', 'string', 'max:20'],
            'email'      => ['nullable', 'email'],
            'dob'        => ['nullable', 'date'],
            'gender'     => ['nullable', 'in:male,female,other,prefer_not_to_say'],
            'occupation' => ['nullable', 'string', 'max:150'],
            'address'    => ['nullable', 'string'],
            'city'       => ['nullable', 'string', 'max:100'],
            'state'      => ['nullable', 'string', 'max:100'],
            'pincode'    => ['nullable', 'string', 'max:10'],
            'medical_alert' => ['nullable', 'string'],
            'habits'     => ['nullable', 'array'],
            'allergies'  => ['nullable', 'array'],
            'family_notes' => ['nullable', 'string', 'max:500'],
            'source'     => ['nullable', 'string', 'max:100'],
            'referred_by'=> ['nullable', 'string'],
        ]);

        $patient->update($request->only([
            'name','phone','email','occupation',
            'address','city','state','pincode',
            'medical_alert','habits','allergies','family_notes',
            'source','referred_by',
        ]) + ['date_of_birth' => $request->dob, 'gender' => $request->gender]);

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