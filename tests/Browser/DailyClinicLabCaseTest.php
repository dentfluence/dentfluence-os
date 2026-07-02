<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Patient;
use App\Models\LabCase;
use Illuminate\Support\Facades\DB;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Daily Clinic Helper — Test 11: Create a Lab Case
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT THIS CHECKS (plain language):
 *  On a patient's Lab Cases tab, opens "New Lab Case", picks a work type
 *  (sent date + status are pre-filled), saves, and confirms a lab case was
 *  created in the database.
 *
 *  Creates its own throwaway patient and removes it (and the lab case) in
 *  tearDown.
 */
class DailyClinicLabCaseTest extends DuskTestCase
{
    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $patientIds = Patient::where('last_name', 'DuskLab')->pluck('id');
        if ($patientIds->isNotEmpty()) {
            LabCase::whereIn('patient_id', $patientIds)->forceDelete();
            Patient::whereIn('id', $patientIds)->forceDelete();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    public function test_a_lab_case_can_be_created(): void
    {
        $user = User::where('email', env('CRAWL_EMAIL'))->first();
        if (! $user) {
            $this->markTestSkipped('No user matching CRAWL_EMAIL found.');
        }

        $patient = Patient::create([
            'first_name' => 'DuskTest',
            'last_name'  => 'DuskLab',
            'name'       => 'DuskTest DuskLab',
            'gender'     => 'male',
            'phone'      => '9000000010',
            'branch_id'  => $user->branch_id ?? 1,
            'created_by' => $user->id,
        ]);
        $patientId = $patient->id;

        $this->browse(function (Browser $browser) use ($user, $patientId) {
            $browser->loginAs($user)
                    ->visit('/patients/' . $patientId)
                    ->waitFor('@tab-lab')
                    ->click('@tab-lab')
                    ->waitFor('@lab-new')
                    ->click('@lab-new')
                    ->waitFor('select[name="work_type"]')
                    // Pick the first non-empty work type and notify Alpine.
                    ->script(
                        "var s=document.querySelector('select[name=\"work_type\"]');"
                        . "for(var i=0;i<s.options.length;i++){if(s.options[i].value){s.value=s.options[i].value;"
                        . "s.dispatchEvent(new Event('change',{bubbles:true}));break;}}"
                    );

            $browser->click('@lab-save')
                    ->waitUntilMissing('@lab-save', 15);
        });

        $this->assertDatabaseHas('lab_cases', ['patient_id' => $patientId]);
    }
}
