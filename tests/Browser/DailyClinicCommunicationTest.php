<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Patient;
use App\Models\PatientCommunication;
use Illuminate\Support\Facades\DB;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Daily Clinic Helper — Test 9: Log a Communication
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT THIS CHECKS (plain language):
 *  On a patient's Communications tab, opens "Add Communication", writes a
 *  note, saves (type/direction/status default to call/outgoing/sent), and
 *  confirms the communication was saved to the database.
 *
 *  Creates its own throwaway patient and removes it (and the communication)
 *  in tearDown.
 */
class DailyClinicCommunicationTest extends DuskTestCase
{
    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $patientIds = Patient::where('last_name', 'DuskComm')->pluck('id');
        if ($patientIds->isNotEmpty()) {
            PatientCommunication::whereIn('patient_id', $patientIds)->forceDelete();
            Patient::whereIn('id', $patientIds)->forceDelete();
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
        $patientId = $patient->id;
        $message   = 'DuskComm' . now()->format('His') . ' — called patient, confirmed appointment';

        $this->browse(function (Browser $browser) use ($user, $patientId, $message) {
            $browser->loginAs($user)
                    ->visit('/patients/' . $patientId)
                    ->waitFor('@tab-communications')
                    ->click('@tab-communications')
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
