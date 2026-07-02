<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Patient;
use App\Models\PatientRelationshipNote;
use Illuminate\Support\Facades\DB;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Daily Clinic Helper — Test 10: Add a Note (Notes & Logs)
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT THIS CHECKS (plain language):
 *  On a patient's Notes & Logs tab, types a note, clicks Save Note, and
 *  confirms it was saved to the database and shows in the list.
 *
 *  Creates its own throwaway patient and removes it (and the note) in
 *  tearDown.
 */
class DailyClinicNotesTest extends DuskTestCase
{
    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $patientIds = Patient::where('last_name', 'DuskNote')->pluck('id');
        if ($patientIds->isNotEmpty()) {
            PatientRelationshipNote::whereIn('patient_id', $patientIds)->forceDelete();
            Patient::whereIn('id', $patientIds)->forceDelete();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    public function test_a_note_can_be_added(): void
    {
        $user = User::where('email', env('CRAWL_EMAIL'))->first();
        if (! $user) {
            $this->markTestSkipped('No user matching CRAWL_EMAIL found.');
        }

        $patient = Patient::create([
            'first_name' => 'DuskTest',
            'last_name'  => 'DuskNote',
            'name'       => 'DuskTest DuskNote',
            'gender'     => 'male',
            'phone'      => '9000000009',
            'branch_id'  => $user->branch_id ?? 1,
            'created_by' => $user->id,
        ]);
        $patientId = $patient->id;
        $note      = 'DuskNote' . now()->format('His') . ' — patient prefers morning appointments';

        $this->browse(function (Browser $browser) use ($user, $patientId, $note) {
            $browser->loginAs($user)
                    ->visit('/patients/' . $patientId)
                    ->waitFor('@tab-notes')
                    ->click('@tab-notes')
                    ->waitFor('@note-input')
                    ->type('@note-input', $note)
                    ->click('@note-save')
                    ->waitForText($note, 15)
                    ->assertSee($note);
        });

        $this->assertDatabaseHas('patient_relationship_notes', [
            'patient_id' => $patientId,
            'note'       => $note,
        ]);
    }
}
