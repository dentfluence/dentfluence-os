<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConsultationRequest;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;

class ConsultationController extends Controller
{
    public function create(Request $request, Patient $patient)
    {
        $doctors = User::where('role', 'doctor')->orderBy('name')->get();

        return view('consultations.create', compact('patient', 'doctors'));
    }

    public function store(StoreConsultationRequest $request, Patient $patient)
    {
        $consultation = Consultation::create($request->validated());

        return redirect()
            ->route('patients.consultations.show', [$patient, $consultation])
            ->with('success', 'Consultation saved successfully.');
    }

    public function show(Patient $patient, Consultation $consultation)
    {
        $consultation->load(['patient', 'doctor', 'responsible']);

        return view('consultations.show', compact('consultation'));
    }

    public function edit(Patient $patient, Consultation $consultation)
    {
        $doctors = User::where('role', 'doctor')->orderBy('name')->get();

        return view('consultations.create', compact('consultation', 'patient', 'doctors'));
    }

    public function update(StoreConsultationRequest $request, Patient $patient, Consultation $consultation)
    {
        $consultation->update($request->validated());

        return redirect()
            ->route('patients.consultations.show', [$patient, $consultation])
            ->with('success', 'Consultation updated successfully.');
    }

    public function destroy(Patient $patient, Consultation $consultation)
    {
        $consultation->delete();

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Consultation deleted.');
    }
}