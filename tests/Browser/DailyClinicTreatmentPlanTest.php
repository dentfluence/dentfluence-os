<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Daily Clinic Helper — Test 3: Create a Treatment Plan
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT THIS CHECKS (plain language):
 *  On a patient's profile, opens the Treatment Plan tab, creates a new
 *  treatment option, adds one treatment line with a price, saves it, and
 *  confirms the plan was saved (shown on screen AND in the database).
 *
 *  It creates its own throwaway patient and deletes everything afterwards
 *  (tearDown), so your real records are never touched.
 */
class DailyClinicTreatmentPlanTest extends DuskTestCase
{
    protected ?Patient $patient = null;

    protected function tearDown(): void
    {
        // Remove the plan + the throwaway patient this test created.
        TreatmentPlan::where('plan_name', 'like', 'DuskPlan%')->forceDelete();
        Patient::where('last_name', 'DuskTPlan')->forceDelete();
        parent::tearDown();
    }

    public function test_a_treatment_plan_can_be_created(): void
    {
        $user = User::where('email', env('CRAWL_EMAIL'))->first();
        if (! $user) {
            $this->markTestSkipped('No user matching CRAWL_EMAIL found.');
        }

        // Throwaway patient for this test.
        $this->patient = Patient::create([
            'first_name' => 'DuskTest',
            'last_name'  => 'DuskTPlan',
            'name'       => 'DuskTest DuskTPlan',
            'gender'     => 'male',
            'phone'      => '9000000002',
            'branch_id'  => $user->branch_id ?? 1,
            'created_by' => $user->id,
        ]);

        $planName = 'DuskPlan' . now()->format('His');
        $patientId = $this->patient->id;

        $this->browse(function (Browser $browser) use ($user, $patientId, $planName) {
            $browser->loginAs($user)
                    ->visit('/patients/' . $patientId)
                    ->waitFor('@tab-treatment-plan')
                    ->click('@tab-treatment-plan')
                    ->waitFor('@tp-open-form')
                    ->click('@tp-open-form')
                    ->waitFor('@tp-plan-name')
                    ->type('@tp-plan-name', $planName)
                    ->click('@tp-add-treatment')
                    ->waitFor('@tp-tx-name')
                    ->type('@tp-tx-name', 'Root Canal Treatment')
                    ->type('@tp-tx-price', '5000')
                    ->click('@tp-save')
                    ->waitForText($planName, 15)
                    ->assertSee($planName);
        });

        $this->assertDatabaseHas('treatment_plans', ['plan_name' => $planName]);
    }
}
