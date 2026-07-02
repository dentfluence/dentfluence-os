<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\{Complaint, DentalCondition, Diagnosis, Investigation, MedicalCondition, Medicine, MessageTemplate, PatientSource, Treatment};
use Illuminate\Http\Request;

class MastersController extends Controller
{
    // ── Generic helper ──────────────────────────────────────────────────────
    private function storeSimple(string $model, Request $request, string $tab): \Illuminate\Http\RedirectResponse
    {
        $request->validate(['name' => 'required|string|max:200']);
        $model::create($request->only('name'));
        return redirect()->route('settings.index', ['tab' => $tab])->with('success', 'Item added.');
    }

    private function destroySimple(string $model, int $id, string $tab): \Illuminate\Http\RedirectResponse
    {
        $model::findOrFail($id)->delete();
        return redirect()->route('settings.index', ['tab' => $tab])->with('success', 'Item removed.');
    }

    // ── Treatments ──────────────────────────────────────────────────────────
    public function storeTreatment(Request $request)
    {
        $request->validate(['name' => 'required|string|max:200', 'category' => 'nullable|string|max:100', 'default_price' => 'nullable|numeric|min:0', 'duration_mins' => 'nullable|integer|min:1']);
        Treatment::create($request->only('name', 'category', 'default_price', 'duration_mins', 'description'));
        return redirect()->route('settings.index', ['tab' => 'masters'])->with('success', 'Treatment added.');
    }
    public function destroyTreatment(int $id) { return $this->destroySimple(Treatment::class, $id, 'masters'); }

    // ── Complaints ───────────────────────────────────────────────────────────
    public function storeComplaint(Request $request)   { return $this->storeSimple(Complaint::class, $request, 'masters'); }
    public function destroyComplaint(int $id)          { return $this->destroySimple(Complaint::class, $id, 'masters'); }

    // ── Diagnoses ────────────────────────────────────────────────────────────
    public function storeDiagnosis(Request $request)
    {
        $request->validate(['name' => 'required|string|max:200', 'icd_code' => 'nullable|string|max:20']);
        Diagnosis::create($request->only('name', 'icd_code'));
        return redirect()->route('settings.index', ['tab' => 'masters'])->with('success', 'Diagnosis added.');
    }
    public function destroyDiagnosis(int $id) { return $this->destroySimple(Diagnosis::class, $id, 'masters'); }

    // ── Investigations ───────────────────────────────────────────────────────
    public function storeInvestigation(Request $request)
    {
        $request->validate(['name' => 'required|string|max:200', 'unit' => 'nullable|string|max:50']);
        Investigation::create($request->only('name', 'unit'));
        return redirect()->route('settings.index', ['tab' => 'masters'])->with('success', 'Investigation added.');
    }
    public function destroyInvestigation(int $id) { return $this->destroySimple(Investigation::class, $id, 'masters'); }

    // ── Medicines ────────────────────────────────────────────────────────────
    public function storeMedicine(Request $request)
    {
        $request->validate(['name' => 'required|string|max:200', 'type' => 'nullable|string|max:60', 'generic_name' => 'nullable|string|max:200', 'default_dosage' => 'nullable|string|max:60', 'default_frequency' => 'nullable|string|max:60', 'default_duration' => 'nullable|string|max:60', 'instructions' => 'nullable|string|max:500']);
        Medicine::create($request->only('name', 'generic_name', 'type', 'default_dosage', 'default_frequency', 'default_duration', 'instructions'));
        return redirect()->route('settings.index', ['tab' => 'clinical'])->with('success', 'Medicine added.');
    }
    public function destroyMedicine(int $id) { return $this->destroySimple(Medicine::class, $id, 'clinical'); }

    // ── Medical Conditions ───────────────────────────────────────────────────
    public function storeMedicalCondition(Request $request)  { return $this->storeSimple(MedicalCondition::class, $request, 'patient-defaults'); }
    public function destroyMedicalCondition(int $id)         { return $this->destroySimple(MedicalCondition::class, $id, 'patient-defaults'); }

    // ── Dental Conditions ────────────────────────────────────────────────────
    public function storeDentalCondition(Request $request)   { return $this->storeSimple(DentalCondition::class, $request, 'patient-defaults'); }
    public function destroyDentalCondition(int $id)          { return $this->destroySimple(DentalCondition::class, $id, 'patient-defaults'); }

    // ── Patient Sources ───────────────────────────────────────────────────────
    public function storePatientSource(Request $request)     { return $this->storeSimple(PatientSource::class, $request, 'patient-defaults'); }
    public function destroyPatientSource(int $id)            { return $this->destroySimple(PatientSource::class, $id, 'patient-defaults'); }

    // ── Message Templates ─────────────────────────────────────────────────────
    public function storeMessageTemplate(Request $request)
    {
        $request->validate(['name' => 'required|string|max:200', 'channel' => 'required|in:whatsapp,sms,email', 'type' => 'required|in:appointment_reminder,followup,recall,birthday,custom', 'body' => 'required|string|max:2000']);
        MessageTemplate::create($request->only('name', 'channel', 'type', 'body'));
        return redirect()->route('settings.index', ['tab' => 'growth'])->with('success', 'Template saved.');
    }
    public function destroyMessageTemplate(int $id) { return $this->destroySimple(MessageTemplate::class, $id, 'growth'); }
}
