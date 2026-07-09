<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Patient;
use App\Models\PatientCommunication;
use App\Services\Relationship\RelationshipEngine;
use Illuminate\Support\Facades\DB;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Daily Clinic Helper — Test 9: Log a Communication
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT THIS CHECKS (plain language):
 *  On the patient's Relationship Profile → Communication tab, opens
 *  "Log Communication", writes a note, saves (type/direction/status default
 *  to call/outgoing/sent), and confirms the communication was saved to the
 *  database.
 *
 *  2026-07-09: the standalone "Communications" tab on the patient profile
 *  page was retired (duplicate of this one — see Relationship Profile). The
 *  logging UI now lives only on the Relationship Profile, so this test
 *  explicitly links the throwaway patient to a Relationship first (normally
 *  done by RelationshipEngine::linkPatient() during real patient creation).
 *
 *  Creates its own throwaway patient (+ relationship) and removes both (and
 *  the communication) in tearDown.
 */
class DailyClinicCommunicationTest extends DuskTestCase
{
    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $patientIds = Patient::where('last_name', 'DuskComm')->pluck('id');
        if ($patientIds->isNotEmpty()) {
            $relationshipIds = Patient::whereIn('id', $patientIds)->pluck('relationship_id')->filter();
            PatientCommunication::whereIn('patient_id', $patientIds)->forceDelete();
            Patient::whereIn('id', $patientIds)->forceDelete();
            if ($relationshipIds->isNotEmpty()) {
                DB::table('relationships')->whereIn('id', $relationshipIds)->delete();
            }
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    public function test_a_communication_can_be_logged(): void
    {
        $user = User::where('email', env('CRAWL_EMAIL'))->first();
        if (! $user) {
            $this->markTestSkipped('No user matching CRAWL_EMAIL found.');
        }

        $patient = Patient::create([
            'first_name' => 'DuskTest',
            'last_name'  => 'DuskComm',
            'name'       => 'DuskTest DuskComm',
            'gender'     => 'male',
            'phone'      => '9000000008',
            'branch_id'  => $user->branch_id ?? 1,
            'created_by' => $user->id,
        ]);

        // Normal patient creation links a Relationship via RelationshipEngine;
        // this test bypasses that flow, so link it explicitly.
        app(RelationshipEngine::class)->linkPatient($patient);
        $patient->refresh();
        $this->assertNotNull($patient->relationship_id, 'Test patient must be linked to a Relationship to reach the Communication tab.');

        $patientId      = $patient->id;
        $relationshipId = $patient->relationship_id;
        $message        = 'DuskComm' . now()->format('His') . ' — called patient, confirmed appointment';

        $this->browse(function (Browser $browser) use ($user, $relationshipId, $message) {
            $browser->loginAs($user)
                    ->visit('/relationship/' . $relationshipId)
                    ->waitFor('@rp-tab-communication')
                    ->click('@rp-tab-communication')
                    ->waitFor('@comm-add')
                    ->click('@comm-add')
                    ->waitFor('@comm-message')
                    ->type('@comm-message', $message)
                    ->click('@comm-save')
                    // The add form closes once the communication saves.
                    ->waitUntilMissing('@comm-save', 15);
        });

        $this->assertDatabaseHas('patient_communications', [
            'patient_id' => $patientId,
            'message'    => $message,
        ]);
    }
}
