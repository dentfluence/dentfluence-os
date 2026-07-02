<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Prescriptions module — Add Drug to master catalogue (Rx settings)
 * ─────────────────────────────────────────────────────────────────────────
 *  Opens the New Drug form, enters a brand name, saves, and confirms the
 *  drug reached the rx_drugs master. Cleans up in tearDown.
 */
class PrescriptionDrugTest extends DuskTestCase
{
    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('rx_drugs')->where('brand_name', 'like', 'DuskDrug%')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    public function test_a_drug_can_be_added_to_the_master(): void
    {
        $user = User::where('email', env('CRAWL_EMAIL'))->first();
        if (! $user) {
            $this->markTestSkipped('No user matching CRAWL_EMAIL found.');
        }

        $name = 'DuskDrug' . now()->format('His');

        $this->browse(function (Browser $browser) use ($user, $name) {
            $browser->loginAs($user)
                    ->visit('/settings/prescription/drugs/create')
                    ->waitFor('input[name="brand_name"]')
                    ->type('input[name="brand_name"]', $name)
                    ->click('@drug-save')
                    ->pause(3000);
        });

        $this->assertDatabaseHas('rx_drugs', ['brand_name' => $name]);
    }
}
