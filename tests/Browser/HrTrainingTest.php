<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  HR module — Schedule a Training session
 * ─────────────────────────────────────────────────────────────────────────
 *  Opens the New Training form, enters a title + date (type defaults to
 *  one-time), saves, and confirms the session reached the database.
 */
class HrTrainingTest extends DuskTestCase
{
    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('hr_training_sessions')->where('title', 'like', 'DuskTraining%')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    public function test_a_training_session_can_be_scheduled(): void
    {
        $user = User::where('email', env('CRAWL_EMAIL'))->first();
        if (! $user) {
            $this->markTestSkipped('No user matching CRAWL_EMAIL found.');
        }

        $title = 'DuskTraining' . now()->format('His');

        $this->browse(function (Browser $browser) use ($user, $title) {
            $date = now()->format('Y-m-d');
            $browser->loginAs($user)
                    ->visit('/hr/training/create')
                    ->waitFor('input[name="title"]')
                    ->type('input[name="title"]', $title)
                    // Date inputs reject Dusk type() ("invalid element state"); set value directly.
                    ->script("document.querySelector('input[name=\"scheduled_date\"]').value = '{$date}';");

            $browser->click('@training-save')->pause(3000);
        });

        $this->assertDatabaseHas('hr_training_sessions', ['title' => $title]);
    }
}
