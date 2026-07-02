<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Patient;
use App\Models\Finance\FinanceMembershipPlan;
use App\Models\Finance\FinancePatientMembership;
use Illuminate\Support\Facades\DB;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Daily Clinic Helper — Test 6: Enroll a patient in Membership (AOCP)
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT THIS CHECKS (plain language):
 *  Opens the AOCP enrollment form on a patient, picks a membership plan,
 *  enters an amount + cash, confirms enrollment, and verifies a membership
 *  record was created in the database.
 *
 *  Creates its own throwaway patient and removes it (and the membership) in
 *  tearDown so real records are untouched. Skips cleanly if no membership
 *  plan exists in the system.
 */
class DailyClinicMembershipTest extends DuskTestCase
{
    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $patientIds = Patient::where('last_name', 'DuskMember')->pluck('id');
        if ($patientIds->isNotEmpty()) {
            FinancePatientMembership::whereIn('patient_id', $patientIds)->forceDelete();
            Patient::whereIn('id', $patientIds)->forceDelete();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    public function test_a_patient_can_be_enrolled_in_membership(): void
    {
        $user = User::where('email', env('CRAWL_EMAIL'))->first();
        if (! $user) {
            $this->markTestSkipped('No user matching CRAWL_EMAIL found.');
        }

        $plan = FinanceMembershipPlan::where('is_active', true)->first();
        if (! $plan) {
            $this->markTestSkipped('No active membership plan exists to enroll into.');
        }

        $patient = Patient::create([
            'first_name' => 'DuskTest',
            'last_name'  => 'DuskMember',
            'name'       => 'DuskTest DuskMember',
            'gender'     => 'male',
            'phone'      => '9000000005',
            'branch_id'  => $user->branch_id ?? 1,
            'created_by' => $user->id,
        ]);
        $patientId = $patient->id;

        $this->browse(function (Browser $browser) use ($user, $patientId, $plan) {
            $browser->loginAs($user)
                    ->visit('/patients/' . $patientId)
                    ->waitFor('@tab-membership')
                    // Reveal the enrollment modal (it lives in the page, hidden).
                    ->script("document.getElementById('enrollModal').classList.remove('hidden')");

            $browser->waitFor('input[name="plan_id"]')
                    // Select the plan radio reliably regardless of custom styling.
                    ->script(
                        "var r=document.querySelector('input[name=\"plan_id\"][value=\"{$plan->id}\"]');"
                        . "if(r){r.checked=true;r.dispatchEvent(new Event('change',{bubbles:true}));}"
                    );

            $browser->type('#enrollAmountPaid', '1000')
                    ->select('select[name="payment_mode"]', 'cash')
                    ->click('#enrollForm button[type="submit"]')
                    ->pause(3000);
        });

        $this->assertDatabaseHas('finance_patient_memberships', ['patient_id' => $patientId]);
    }
}
