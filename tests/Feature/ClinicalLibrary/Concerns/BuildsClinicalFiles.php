<?php

namespace Tests\Feature\ClinicalLibrary\Concerns;

use App\Models\ClinicalFile;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;

/**
 * Shared fixtures for the Clinical Library suite.
 *
 * Every required-field list here was read off the model's $fillable and the
 * migrations, not guessed — the same discipline BuildsVisitFixtures records.
 * Nothing in this trait touches the filesystem: rows are created directly so a
 * search test never pays for an upload it is not testing.
 */
trait BuildsClinicalFiles
{
    private ?User $fixtureUploader = null;

    /** Every clinical file names an uploader — uploaded_by is NOT NULL by design. */
    protected function uploader(): User
    {
        return $this->fixtureUploader ??= $this->makeUser();
    }

    protected function makePatient(array $overrides = []): Patient
    {
        return Patient::create(array_merge([
            'name'      => 'Test Patient ' . uniqid(),
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ], $overrides));
    }

    protected function makeUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role'      => 'admin',
            'branch_id' => 1,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * A ClinicalFile row with no file behind it. Fine for search, parsing and
     * eligibility tests; anything that reads bytes must upload for real.
     */
    protected function makeFile(Patient $patient, array $overrides = []): ClinicalFile
    {
        return ClinicalFile::create(array_merge([
            'patient_id'        => $patient->id,
            'file_type'         => 'photo',
            'stage'             => 'general',
            'disk'              => 'local',
            'path'              => 'patients/' . $patient->id . '/clinical-files/' . uniqid() . '.jpg',
            'original_filename' => 'photo.jpg',
            'mime_type'         => 'image/jpeg',
            'file_size'         => 120000,
            'captured_at'       => now(),
            'consent_status'    => 'not_given',
            'uploaded_by'       => $this->uploader()->id,
        ], $overrides));
    }

    /** A plan item to hang a treatment status off, plus the plan it needs. */
    protected function makePlanItem(Patient $patient, string $billingProgress): TreatmentPlanItem
    {
        $plan = TreatmentPlan::create([
            'patient_id'  => $patient->id,
            'plan_type'   => 'best',
            'accepted_at' => now(),
        ]);

        return TreatmentPlanItem::create([
            'treatment_plan_id' => $plan->id,
            'treatment_name'    => 'Implant',
            'billing_progress'  => $billingProgress,
        ]);
    }
}
