<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Lab module — Add Lab Vendor
 * ─────────────────────────────────────────────────────────────────────────
 *  Opens the Add Lab Vendor modal, enters a name, saves, and confirms the
 *  vendor reached the database. (Creating a lab vendor also auto-syncs a
 *  finance vendor, so cleanup removes both.)
 */
class LabVendorTest extends DuskTestCase
{
    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('lab_vendors')->where('name', 'like', 'DuskLabVendor%')->delete();
        DB::table('finance_vendors')->where('vendor_name', 'like', 'DuskLabVendor%')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    public function test_a_lab_vendor_can_be_added(): void
    {
        $user = User::where('email', env('CRAWL_EMAIL'))->first();
        if (! $user) {
            $this->markTestSkipped('No user matching CRAWL_EMAIL found.');
        }

        $name = 'DuskLabVendor' . now()->format('His');

        $this->browse(function (Browser $browser) use ($user, $name) {
            $browser->loginAs($user)
                    ->visit('/lab-vendors')
                    ->waitFor('@labvendor-add')
                    ->click('@labvendor-add')
                    ->waitFor('@labvendor-name')
                    ->type('@labvendor-name', $name)
                    ->click('@labvendor-save')
                    ->waitUntilMissing('@labvendor-save', 15);
        });

        $this->assertDatabaseHas('lab_vendors', ['name' => $name]);
    }
}
