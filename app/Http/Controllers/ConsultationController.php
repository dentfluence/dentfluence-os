<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConsultationRequest;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\User;
use App\Services\Cms\ClinicalMediaService;
use Illuminate\Http\Request;

class ConsultationController extends Controller
{
    public function create(Request $request, Patient $patient)
    {
        $doctors = User::orderBy('name')->get();
        $pastConsultations = $patient->consultations()->latest()->take(5)->get();
        return view('patients.create', compact('patient', 'doctors', 'pastConsultations'));
    }

    public function store(StoreConsultationRequest $request, Patient $patient)
    {
        $data = $request->validated();
        // Always inject patient/branch from route — brain form doesn't send these
        $data['patient_id']        = $patient->id;
        $data['branch_id']         = $data['branch_id'] ?? auth()->user()->branch_id ?? 1;
        $data['status']            = $data['status'] ?? 'completed';
        $data['consultation_date'] = $data['consultation_date'] ?? now();

        $consultation = Consultation::create($data);

        // ── CMS: register uploaded media ──
        $mediaService = app(ClinicalMediaService::class);
        $context = [
            'patient_id'      => $patient->id,
            'patient_name'    => $patient->name,
            'source_type'     => 'App\Models\Consultation',
            'source_id'       => $consultation->id,
            'treatment_name'  => $consultation->primary_diagnosis ?? 'Consultation',
            'tooth_no'        => $consultation->tooth_area ?? null,
            'treatment_stage' => 'before',
            'visit_date'      => $consultation->created_at->toDateString(),
        ];

        foreach (range(0, 8) as $i) {
            if ($request->hasFile('photo_' . $i)) {
                $mediaService->register(
                    $request->file('photo_' . $i),
                    array_merge($context, ['media_type' => 'photo', 'tags' => ['photo', 'before']])
                );
            }
        }

        foreach ($request->file('inv_file_iopa', []) as $file) {
            $mediaService->register($file, array_merge($context, ['media_type' => 'xray', 'tags' => ['iopa', 'xray']]));
        }

        foreach ($request->file('inv_file_opg', []) as $file) {
            $mediaService->register($file, array_merge($context, ['media_type' => 'opg', 'tags' => ['opg', 'xray']]));
        }

        foreach (range(0, 20) as $i) {
            if ($request->hasFile('scan_file_' . $i)) {
                $mediaService->register(
                    $request->file('scan_file_' . $i),
                    array_merge($context, ['media_type' => 'scan', 'tags' => ['scan']])
                );
            }
        }
        // ── End CMS ──

        if ($request->expectsJson()) {
            return response()->json([
                'success'      => true,
                'message'      => 'Consultation saved.',
                'redirect_url' => route('patients.show', $patient).'#consultation',
            ]);
        }

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Consultation saved successfully.');
    }

    public function show(Patient $patient, Consultation $consultation)
    {
        $consultation->load(['patient', 'doctor', 'responsible']);
        return view('consultations.show', compact('consultation'));
    }

    public function print(Patient $patient, Consultation $consultation)
    {
        $consultation->load(['patient', 'doctor', 'complaints', 'diagnoses', 'treatmentPlans.items', 'clinicalFindings']);
        $print  = \App\Models\AppSetting::group('print');
        $clinic = \App\Models\AppSetting::group('clinic');
        return view('consultations.print', compact('consultation', 'print', 'clinic'));
    }

    public function edit(Patient $patient, Consultation $consultation)
    {
        $doctors = User::orderBy('name')->get();
        $pastConsultations = $patient->consultations()->where('id', '!=', $consultation->id)->latest()->take(5)->get();
        return view('patients.create', compact('consultation', 'patient', 'doctors', 'pastConsultations'));
    }

    public function update(StoreConsultationRequest $request, Patient $patient, Consultation $consultation)
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? 'completed';
        $consultation->update($data);

        if ($request->expectsJson()) {
            return response()->json([
                'success'      => true,
                'message'      => 'Consultation updated.',
                'redirect_url' => route('patients.show', $patient).'#consultation',
            ]);
        }

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Consultation updated successfully.');
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
}
