<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PatientController extends Controller
{
    public function index()
    {
        // DB has a single `name` column, not first_name/last_name
        $patients = Patient::where('branch_id', Auth::user()->branch_id)
            ->orderBy('name')
            ->paginate(30);

        return view('patients.index', compact('patients'));
    }

    public function create()
    {
        return view('patients.create');
    }

    /**
     * Store a new patient.
     * Modal sends: first_name, last_name, mobile
     * DB columns:  name, phone (+ optional mobile if column exists)
     */
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
            'dob'       => $request->dob,
            'gender'    => $request->gender,
            'address'   => $request->address,
            'notes'     => $request->notes,
            'branch_id' => Auth::user()->branch_id,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'patient' => $patient]);
        }

        return redirect()->route('patients.show', $patient)
            ->with('success', 'Patient added successfully.');
    }

    public function show(Patient $patient)
    {
        $patient->load(['appointments.treatment', 'appointments.treatmentCategory']);
        return view('patients.show', compact('patient'));
    }

    public function edit(Patient $patient)
    {
        return view('patients.edit', compact('patient'));
    }

    public function update(Request $request, Patient $patient)
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

        $patient->update([
            'name'    => trim($request->first_name . ' ' . $request->last_name),
            'phone'   => $request->mobile,
            'email'   => $request->email,
            'dob'     => $request->dob,
            'gender'  => $request->gender,
            'address' => $request->address,
            'notes'   => $request->notes,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'patient' => $patient->fresh()]);
        }

        return redirect()->route('patients.show', $patient)
            ->with('success', 'Patient updated.');
    }

    public function destroy(Patient $patient)
    {
        $patient->delete();
        return redirect()->route('patients.index')
            ->with('success', 'Patient deleted.');
    }
}