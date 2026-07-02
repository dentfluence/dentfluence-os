<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Finance module — Add Vendor
 * ─────────────────────────────────────────────────────────────────────────
 *  Opens the New Vendor form, enters a name + type, saves, and confirms the
 *  vendor reached the database. Cleans up in tearDown.
 */
class FinanceVendorTest extends DuskTestCase
{
    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('finance_vendors')->where('vendor_name', 'like', 'DuskVendor%')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    public function test_a_vendor_can_be_added(): void
    {
        $user = User::where('email', env('CRAWL_EMAIL'))->first();
        if (! $user) {
            $this->markTestSkipped('No user matching CRAWL_EMAIL found.');
        }

        $name = 'DuskVendor' . now()->format('His');

        $this->browse(function (Browser $browser) use ($user, $name) {
            $browser->loginAs($user)
                    ->visit('/finance/vendors/create')
                    ->waitFor('input[name="vendor_name"]')
                    ->type('input[name="vendor_name"]', $name)
                    ->select('select[name="vendor_type"]', 'dental_supplier')
                    ->click('@vendor-save')
                    ->pause(3000);
        });

        $this->assertDatabaseHas('finance_vendors', ['vendor_name' => $name]);
    }
}
