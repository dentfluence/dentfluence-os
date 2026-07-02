<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Communication module — Add Treatment Opportunity (lead/pipeline)
 * ─────────────────────────────────────────────────────────────────────────
 *  On a patient profile, opens "+ Add Opportunity", picks a treatment type,
 *  saves, and confirms the opportunity reached the database.
 *  Creates its own throwaway patient and cleans up.
 */
class CommunicationOpportunityTest extends DuskTestCase
{
    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $ids = Patient::where('last_name', 'DuskOpp')->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('treatment_opportunities')->whereIn('patient_id', $ids)->delete();
            Patient::whereIn('id', $ids)->forceDelete();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    public function test_a_treatment_opportunity_can_be_added(): void
    {
        $user = User::where('email', env('CRAWL_EMAIL'))->first();
        if (! $user) {
            $this->markTestSkipped('No user matching CRAWL_EMAIL found.');
        }

        $patient = Patient::create([
            'first_name' => 'DuskTest',
            'last_name'  => 'DuskOpp',
            'name'       => 'DuskTest DuskOpp',
            'gender'     => 'male',
            'phone'      => '9000000040',
            'branch_id'  => $user->branch_id ?? 1,
            'created_by' => $user->id,
        ]);
        $patientId = $patient->id;

        $this->browse(function (Browser $browser) use ($user, $patientId) {
            $browser->loginAs($user)
                    ->visit('/patients/' . $patientId)
                    ->waitFor('@opp-add')
                    ->click('@opp-add')
                    ->waitFor('@opp-type')
                    ->select('@opp-type', 'implant')
                    ->click('@opp-save')
                    ->pause(3000);
        });

        $this->assertDatabaseHas('treatment_opportunities', [
            'patient_id' => $patientId,
            'type'       => 'implant',
        ]);
    }
}
