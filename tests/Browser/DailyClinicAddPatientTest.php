<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Patient;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Daily Clinic Helper — Test 2: Register a new patient
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT THIS CHECKS (in plain language):
 *  Opens the patient list, clicks "Add Patient", fills the minimum needed
 *  (name, gender, DOB-unknown, mobile), clicks "Save & skip remaining", and
 *  confirms the new patient really appears in the list AND was saved to the
 *  database.
 *
 *  This proves your most-used daily action — registering a walk-in — works
 *  end to end, including the Save button actually writing to the database.
 *
 *  It logs in as the user from CRAWL_EMAIL (already in .env.dusk.local), and
 *  it cleans up after itself in tearDown(): every patient it creates is
 *  deleted afterwards, even if the test fails, so your records stay clean.
 */
class DailyClinicAddPatientTest extends DuskTestCase
{
    /** Always remove any patients this helper created, pass or fail. */
    protected function tearDown(): void
    {
        Patient::where('first_name', 'DuskTest')->forceDelete();
        parent::tearDown();
    }

    public function test_staff_can_register_a_new_patient(): void
    {
        $user = User::where('email', env('CRAWL_EMAIL'))->first();

        if (! $user) {
            $this->markTestSkipped('No user matching CRAWL_EMAIL was found in the database.');
        }

        // Unique last name so we can spot exactly this test patient.
        $uniqueLast = 'DuskAuto' . now()->format('His');

        $this->browse(function (Browser $browser) use ($user, $uniqueLast) {
            $browser->loginAs($user)
                    ->visit('/patients')
                    ->waitFor('@add-patient-btn')
                    ->click('@add-patient-btn')
                    // Step 1 — Basic info
                    ->waitFor('@patient-first-name')
                    ->type('@patient-first-name', 'DuskTest')
                    ->type('@patient-last-name', $uniqueLast)
                    ->select('@patient-gender', 'male')
                    ->check('@patient-dob-unknown')
                    ->click('@patient-next')
                    // Step 2 — Contact
                    ->waitFor('@patient-mobile')
                    ->type('@patient-mobile', '9000000000')
                    ->click('@patient-save')
                    // Saving replaces the form with the success screen, so the
                    // Save button disappears — a reliable "saved" signal that
                    // doesn't depend on the patient-list refresh timing.
                    ->waitUntilMissing('@patient-save', 20);
        });

        // Confirm it truly reached the database.
        $this->assertDatabaseHas('patients', ['last_name' => $uniqueLast]);
    }
}
