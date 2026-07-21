<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientLink;
use App\Models\Scopes\BranchScope;
use App\Services\Patient\FamilyLinkService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * FamilyController — thin web endpoints for the Patient Profile "Family &
 * Contacts" section (Patients Phase 3, Slice 3).
 *
 * All business logic lives in FamilyLinkService; this controller only validates
 * input, resolves the counterpart patient, and delegates. Write actions are
 * gated by the `module:patients,edit` route middleware.
 */
class FamilyController extends Controller
{
    public function __construct(private readonly FamilyLinkService $family)
    {
    }

    /** Link an existing patient as a family member. */
    public function storeLink(Request $request, Patient $patient)
    {
        $data = $request->validate([
            'linked_patient_id' => ['required', 'integer', 'exists:patients,id'],
            'relationship_type' => ['required', Rule::in(FamilyLinkService::RELATIONSHIP_TYPES)],
            'as_guardian'       => ['sometimes', 'boolean'],
            'notes'             => ['nullable', 'string', 'max:150'],
        ]);

        $relative = $this->findPatient($data['linked_patient_id']);

        try {
            $this->family->addLink($patient, $relative, $data['relationship_type'], [
                'as_guardian' => (bool) ($data['as_guardian'] ?? false),
                'notes'       => $data['notes'] ?? null,
            ], $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['family' => $e->getMessage()]);
        }

        return back()->with('family_status', "Linked {$relative->name} as family.");
    }

    /** Change the relationship (and/or guardian flag) of an existing link. */
    public function updateLink(Request $request, Patient $patient, PatientLink $link)
    {
        $this->assertLinkBelongs($patient, $link);

        $data = $request->validate([
            'relationship_type' => ['required', Rule::in(FamilyLinkService::RELATIONSHIP_TYPES)],
            'as_guardian'       => ['sometimes', 'boolean'],
        ]);

        $relative = $this->counterpart($patient, $link);

        try {
            // updateLink (not addLink): an explicit edit may demote a guardian (F1).
            $this->family->updateLink($patient, $relative, $data['relationship_type'], [
                'as_guardian' => (bool) ($data['as_guardian'] ?? false),
            ], $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['family' => $e->getMessage()]);
        }

        return back()->with('family_status', 'Relationship updated.');
    }

    /** Remove a family link. */
    public function destroyLink(Request $request, Patient $patient, PatientLink $link)
    {
        $this->assertLinkBelongs($patient, $link);

        $relative = $this->counterpart($patient, $link);
        $this->family->removeLink($patient, $relative, $request->user());

        return back()->with('family_status', 'Family link removed.');
    }

    /** Attach a guardian — an existing patient, or a new person minted via register(). */
    public function storeGuardian(Request $request, Patient $patient)
    {
        $data = $request->validate([
            'existing_patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'name'                => ['required_without:existing_patient_id', 'nullable', 'string', 'max:120'],
            'phone'               => ['required_without:existing_patient_id', 'nullable', 'string', 'max:20'],
            'gender'              => ['nullable', 'in:male,female,other'],
            'age_years'           => ['nullable', 'integer', 'min:0', 'max:120'],
            'date_of_birth'       => ['nullable', 'date'],
            'relationship_type'   => ['nullable', Rule::in(FamilyLinkService::RELATIONSHIP_TYPES)],
            'notes'               => ['nullable', 'string', 'max:150'],
        ]);

        $guardian = ! empty($data['existing_patient_id'])
            ? $this->findPatient($data['existing_patient_id'])
            : array_filter([
                'name'          => $data['name'] ?? null,
                'phone'         => $data['phone'] ?? null,
                'gender'        => $data['gender'] ?? null,
                'age_years'     => $data['age_years'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
            ], fn ($v) => $v !== null);

        try {
            $this->family->attachGuardian($patient, $guardian, [
                'relationship_type' => $data['relationship_type'] ?? 'other',
                'notes'             => $data['notes'] ?? null,
            ], $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['family' => $e->getMessage()]);
        }

        return back()->with('family_status', 'Guardian assigned.');
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    /** Family crosses branches — resolve without the branch scope. */
    private function findPatient(int $id): Patient
    {
        return Patient::withoutGlobalScope(BranchScope::class)->findOrFail($id);
    }

    private function counterpart(Patient $patient, PatientLink $link): Patient
    {
        $id = (int) $link->patient_id === $patient->id ? $link->linked_patient_id : $link->patient_id;

        return $this->findPatient($id);
    }

    private function assertLinkBelongs(Patient $patient, PatientLink $link): void
    {
        abort_unless(
            (int) $link->patient_id === $patient->id || (int) $link->linked_patient_id === $patient->id,
            404
        );
    }
}
