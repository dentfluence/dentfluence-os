<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Finance module — Add Payroll entry
 * ─────────────────────────────────────────────────────────────────────────
 *  Adds a payroll entry for the logged-in staff member. Uses YEAR 2099 as a
 *  test marker so cleanup never touches real payroll records.
 */
class FinancePayrollTest extends DuskTestCase
{
    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('finance_payroll')->where('year', 2099)->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    public function test_a_payroll_entry_can_be_added(): void
    {
        $user = User::where('email', env('CRAWL_EMAIL'))->first();
        if (! $user) {
            $this->markTestSkipped('No user matching CRAWL_EMAIL found.');
        }

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/finance/payroll')
                    ->waitFor('select[name="user_id"]')
                    ->select('select[name="user_id"]', (string) $user->id)
                    ->select('select[name="month"]', '6')
                    ->clear('input[name="year"]')
                    ->type('input[name="year"]', '2099')
                    ->type('input[name="fixed_salary"]', '30000')
                    ->select('select[name="payment_mode"]', 'cash')
                    ->click('@payroll-save')
                    ->pause(3000);
        });

        $this->assertDatabaseHas('finance_payroll', [
            'user_id' => $user->id,
            'year'    => 2099,
        ]);
    }
}
