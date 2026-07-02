<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Finance module — Add Expense
 * ─────────────────────────────────────────────────────────────────────────
 *  Opens the New Expense form, enters a title + amount (date defaults to
 *  today), saves, and confirms the expense reached the database.
 *  Cleans up the test expense in tearDown.
 */
class FinanceExpenseTest extends DuskTestCase
{
    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('finance_expenses')->where('title', 'like', 'DuskExpense%')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    public function test_an_expense_can_be_added(): void
    {
        $user = User::where('email', env('CRAWL_EMAIL'))->first();
        if (! $user) {
            $this->markTestSkipped('No user matching CRAWL_EMAIL found.');
        }

        $title = 'DuskExpense' . now()->format('His');

        $this->browse(function (Browser $browser) use ($user, $title) {
            $browser->loginAs($user)
                    ->visit('/finance/expenses/create')
                    ->waitFor('input[name="title"]')
                    ->type('input[name="title"]', $title)
                    ->type('input[name="amount"]', '1500')
                    ->click('@expense-save')
                    ->pause(3000);
        });

        $this->assertDatabaseHas('finance_expenses', ['title' => $title]);
    }
}
