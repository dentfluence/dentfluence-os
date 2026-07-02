<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Patient;
use App\Models\TreatmentVisit;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Daily Clinic Helper — Test 4: Record a Treatment Visit
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT THIS CHECKS (plain language):
 *  On a patient's profile, opens Treatment Visits → Add Visit, writes a
 *  clinical note, and saves. All other required fields (date, visit type,
 *  status, doctor) are pre-filled by the form. Confirms the visit was saved
 *  to the database.
 *
 *  Creates its own throwaway patient and deletes it (and the visit) in
 *  tearDown, so real records are untouched.
 */
class DailyClinicTreatmentVisitTest extends DuskTestCase
{
    protected function tearDown(): void
    {
        TreatmentVisit::where('notes', 'like', 'DuskVisit%')->forceDelete();
        Patient::where('last_name', 'DuskTVisit')->forceDelete();
        parent::tearDown();
    }

    public function test_a_treatment_visit_can_be_recorded(): void
    {
        $user = User::where('email', env('CRAWL_EMAIL'))->first();
        if (! $user) {
            $this->markTestSkipped('No user matching CRAWL_EMAIL found.');
        }

        $patient = Patient::create([
            'first_name' => 'DuskTest',
            'last_name'  => 'DuskTVisit',
            'name'       => 'DuskTest DuskTVisit',
            'gender'     => 'male',
            'phone'      => '9000000003',
            'branch_id'  => $user->branch_id ?? 1,
            'created_by' => $user->id,
        ]);

        $note = 'DuskVisit' . now()->format('His') . ' — scaling done, patient comfortable';
        $patientId = $patient->id;

        $this->browse(function (Browser $browser) use ($user, $patientId, $note) {
            $browser->loginAs($user)
                    ->visit('/patients/' . $patientId)
                    ->waitFor('@tab-visits')
                    ->click('@tab-visits')
                    ->waitFor('@visit-add')
                    ->click('@visit-add')
                    ->waitFor('@visit-notes')
                    ->type('@visit-notes', $note)
                    ->click('@visit-save')
                    // Modal closes on a successful save.
                    ->waitUntilMissing('@visit-save', 15);
        });

        $this->assertDatabaseHas('treatment_visits', [
            'patient_id' => $patientId,
            'notes'      => $note,
        ]);
    }
}
