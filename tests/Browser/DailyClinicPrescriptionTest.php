<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Patient;
use App\Models\Prescription\Prescription;
use Illuminate\Support\Facades\DB;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Daily Clinic Helper — Test 12: Create a Prescription (Quick Rx)
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT THIS CHECKS (plain language):
 *  On a patient's Prescriptions tab, opens "Quick Prescription", enters a
 *  chief complaint, saves, and confirms a prescription was created in the
 *  database.
 *
 *  Creates its own throwaway patient and removes it (and the prescription)
 *  in tearDown.
 */
class DailyClinicPrescriptionTest extends DuskTestCase
{
    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $patientIds = Patient::where('last_name', 'DuskRx')->pluck('id');
        if ($patientIds->isNotEmpty()) {
            Prescription::whereIn('patient_id', $patientIds)->forceDelete();
            Patient::whereIn('id', $patientIds)->forceDelete();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    public function test_a_prescription_can_be_created(): void
    {
        $user = User::where('email', env('CRAWL_EMAIL'))->first();
        if (! $user) {
            $this->markTestSkipped('No user matching CRAWL_EMAIL found.');
        }

        $patient = Patient::create([
            'first_name' => 'DuskTest',
            'last_name'  => 'DuskRx',
            'name'       => 'DuskTest DuskRx',
            'gender'     => 'male',
            'phone'      => '9000000011',
            'branch_id'  => $user->branch_id ?? 1,
            'created_by' => $user->id,
        ]);
        $patientId = $patient->id;
        $complaint = 'DuskRx' . now()->format('His') . ' — post-extraction pain';

        $this->browse(function (Browser $browser) use ($user, $patientId, $complaint) {
            $browser->loginAs($user)
                    ->visit('/patients/' . $patientId)
                    ->waitFor('@tab-prescriptions')
                    ->click('@tab-prescriptions')
                    ->waitFor('@rx-new')
                    ->click('@rx-new')
                    ->waitFor('input[name="chief_complaint"]')
                    ->type('input[name="chief_complaint"]', $complaint)
                    ->click('@rx-save')
                    ->pause(3000);
        });

        $this->assertDatabaseHas('prescriptions', [
            'patient_id'      => $patientId,
            'chief_complaint' => $complaint,
        ]);
    }
}
