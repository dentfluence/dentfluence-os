<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Daily Clinic Helper — Test 8: Documents tab loads
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT THIS CHECKS (plain language):
 *  Opens a patient's Documents tab and confirms it renders correctly,
 *  including the "Upload Files" control.
 *
 *  NOTE: The file uploader is a multi-step drag-and-drop modal that uploads
 *  in the background — too fragile to drive reliably in an automated test.
 *  So this is a "tab loads without breaking" smoke check, which is what
 *  catches the tab crashing. A full upload test belongs on a disposable
 *  test database at go-live.
 *
 *  Creates its own throwaway patient and removes it in tearDown.
 */
class DailyClinicDocumentsTest extends DuskTestCase
{
    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Patient::where('last_name', 'DuskDocs')->forceDelete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    public function test_documents_tab_loads(): void
    {
        $user = User::where('email', env('CRAWL_EMAIL'))->first();
        if (! $user) {
            $this->markTestSkipped('No user matching CRAWL_EMAIL found.');
        }

        $patient = Patient::create([
            'first_name' => 'DuskTest',
            'last_name'  => 'DuskDocs',
            'name'       => 'DuskTest DuskDocs',
            'gender'     => 'male',
            'phone'      => '9000000007',
            'branch_id'  => $user->branch_id ?? 1,
            'created_by' => $user->id,
        ]);
        $patientId = $patient->id;

        $this->browse(function (Browser $browser) use ($user, $patientId) {
            $browser->loginAs($user)
                    ->visit('/patients/' . $patientId)
                    ->waitFor('@tab-documents')
                    ->click('@tab-documents')
                    ->waitForText('Upload Files', 15)
                    ->assertSee('Upload Files');
        });
    }
}
